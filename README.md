# Rootstuff Relationships

A developer-first relationship layer for WordPress content modeling. Define `belongsTo`, `hasMany`, and `belongsToMany` relationships between any post types using dedicated database tables — no postmeta abuse, no ACF dependency.

## Requirements

- WordPress 6.4+
- PHP 8.0+

## Installation

```bash
composer install
npm install && npm run build
```

Activate the plugin through the WordPress admin or WP-CLI:

```bash
wp plugin activate rootstuff-relationships
```

The plugin automatically creates two database tables on activation:

- `{prefix}rs_relationships` — stores connections between objects
- `{prefix}rs_relationship_meta` — optional metadata per connection

## Defining Relationships

Register relationships on the `rs_relationships_init` action using the `Schema` class:

```php
use Rootstuff\Relationships\Schema;

add_action('rs_relationships_init', function () {

    // Many-to-many: resources ↔ posts
    Schema::belongsToMany('resource', 'post', 'resource_posts', [
        'labels' => [
            'from' => 'Related Posts',
            'to'   => 'Related Resources',
        ],
    ]);

    // One-to-many: author → books
    Schema::hasMany('author', 'book', 'author_books');

    // One-to-many from the child side
    Schema::belongsTo('book', 'author', 'book_author');

    // One-to-one
    Schema::hasOne('employee', 'badge', 'employee_badge');
});
```

All relationships are bidirectional by default.

### Same-Type Relationships

When both sides share the same post type, you must either define **roles** (for directional relationships) or mark the relationship as **symmetric**:

```php
// Directional: parent/child pages
Schema::belongsToMany('post', 'post', 'related_posts', [
    'roles' => ['source', 'related'],
]);

// Symmetric: related posts (direction doesn't matter)
Schema::belongsToMany('post', 'post', 'similar_posts', [
    'symmetric' => true,
]);
```

## PHP API

The `Relation` class provides the core CRUD operations:

```php
use Rootstuff\Relationships\Relation;

// Connect two objects
Relation::connect('resource_posts', $resource_id, $post_id);

// Disconnect
Relation::disconnect('resource_posts', $resource_id, $post_id);

// Check if a connection exists
Relation::exists('resource_posts', $resource_id, $post_id);

// Get related posts — auto-detects which side you're on
$posts = Relation::get('resource_posts', $post_id);

// Explicit post type (zero-overhead, skips auto-detect)
$resources = Relation::get('resource_posts', $post_id, 'post');

// Get related IDs only (lightweight, cached)
$ids = Relation::getIds('resource_posts', $resource_id);

// Sync: replace all connections with a new ordered set
Relation::sync('resource_posts', $resource_id, 'resource', [10, 23, 45]);
```

### Side Resolution

The optional `$side` parameter tells the plugin which side of the relationship your object is on. It accepts:

- **A post type name** — `'post'`, `'resource'`, `'author'`, etc.
- **A role name** — for same-type relationships with roles
- **`null`** (default) — auto-detects via `get_post_type($object_id)`

For same-type relationships with roles:

```php
$related = Relation::get('related_posts', $post_id, 'source');
```

For symmetric relationships, no side is needed — results from both directions are merged automatically.

## WP_Query Integration

Query related posts directly with `WP_Query` using the `rs_related` parameter:

```php
$related = new WP_Query([
    'post_type'  => 'book',
    'rs_related' => [
        'rel_type'  => 'author_books',
        'object_id' => $author_id,
        'side'      => 'author',    // optional, auto-detects if omitted
    ],
    'orderby' => 'rs_sort_order',
    'order'   => 'ASC',
]);
```

## REST API

Endpoints are registered under `/wp-json/rootstuff-rel/v1/`:

| Endpoint | Method | Description |
|---|---|---|
| `/relationships` | GET | List all registered relationship schemas |
| `/connections/{rel_type}/{object_id}` | GET | Get connections for an object |
| `/connections/{rel_type}` | POST | Create a connection |
| `/connections/{rel_type}/{from_id}/{to_id}` | DELETE | Remove a connection |
| `/connections/{rel_type}/{object_id}/sync` | POST | Sync (replace) all connections |
| `/search` | GET | Search posts by type for the editor selector |

GET and sync endpoints accept a `side` parameter (post type or role name). All mutation endpoints require `edit_posts` capability.

## Gutenberg Integration

When relationships are registered, a sidebar panel automatically appears in the block editor for each relevant post type. The panel provides:

- Searchable selector to find and add related content
- Ordered list of connected items with remove buttons
- Automatic sync on post save

## Hooks

### Actions

- `rs_relationship_connected` — fires after a connection is created
- `rs_relationship_disconnected` — fires after a connection is removed
- `rs_relationship_disconnected_all` — fires after all connections for an object are removed

## Caching

All `getIds()` queries are cached using the WordPress object cache (`rs_relationships` group). Caches are automatically invalidated on connect, disconnect, and sync operations.

## Uninstall

Deactivating the plugin does nothing to the data. Deleting it through the WordPress admin runs `uninstall.php`, which drops both database tables permanently.

## License

GPL-2.0-or-later
