=== RelateWP ===
Contributors: rootstuff
Tags: relationships, content modeling, related posts, post connections, custom relationships
Requires at least: 6.4
Tested up to: 6.9
Stable tag: 0.1.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A developer-first relationship layer for WordPress. Define belongsTo, hasMany, and belongsToMany relationships between any post types using dedicated database tables.

== Description ==

RelateWP provides a clean, Laravel-inspired API for defining and querying relationships between WordPress content types. Connections are stored in dedicated database tables — not postmeta — giving you real relational integrity and fast queries.

**Key features:**

* **Multiple cardinality types** — `belongsTo`, `hasMany`, `hasOne`, and `belongsToMany`
* **Bidirectional by default** — query from either side of a relationship
* **Same-type relationships** — support for roles and symmetric connections
* **Dedicated database tables** — no postmeta abuse
* **WP_Query integration** — use the `relatewp_related` parameter in any WP_Query
* **REST API** — full CRUD endpoints for headless or decoupled setups
* **Gutenberg sidebar** — searchable selector to manage connections per post
* **Related Content block** — drop-in block with list, grid, and inline layouts
* **Admin columns** — show related items in the posts list table
* **Connection metadata** — store per-connection key/value data
* **Object cache support** — cached queries with automatic invalidation
* **Developer hooks** — actions fire on connect, disconnect, and bulk operations

== Installation ==

1. Upload the `relatewp` folder to `/wp-content/plugins/`
2. Activate through the Plugins admin page
3. Register your relationships on the `relatewp_init` hook (see Usage below)

The plugin automatically creates two database tables on activation:

* `{prefix}relatewp_relationships` — stores connections between objects
* `{prefix}relatewp_relationship_meta` — optional metadata per connection

== Usage ==

Register relationships in your theme's `functions.php` or a custom plugin:

    use RelateWP\Schema;

    add_action( 'relatewp_init', function () {
        Schema::belongsToMany( 'resource', 'post', 'resource_posts', [
            'labels' => [
                'from' => 'Related Posts',
                'to'   => 'Related Resources',
            ],
        ] );
    } );

Query related content in templates:

    use RelateWP\Relation;

    $related = Relation::get( 'resource_posts', get_the_ID() );

Or use the **Related Content** block in the block editor — select a relationship and choose between list, grid, or inline layout.

== Frequently Asked Questions ==

= Does this replace ACF? =

No. RelateWP handles content relationships only. You can use it alongside ACF, Meta Box, or any custom fields plugin.

= Where is the data stored? =

In two dedicated database tables, not in `wp_postmeta`. This ensures relational integrity and fast queries.

= What happens when I deactivate the plugin? =

Nothing. Your data stays in the database. Deleting the plugin through the admin will drop the tables permanently.

= Can I query relationships with WP_Query? =

Yes. Use the `relatewp_related` parameter:

    $query = new WP_Query( [
        'post_type'        => 'book',
        'relatewp_related' => [
            'rel_type'  => 'author_books',
            'object_id' => $author_id,
        ],
    ] );

== Screenshots ==

1. Gutenberg sidebar panel for managing related content
2. Related Content block with grid layout
3. Admin column showing connected items

== Changelog ==

= 0.1.0 =
* Initial release.
* Core relationship engine with Registry, Relation, Schema, and Cache.
* All cardinality types: belongsTo, hasMany, hasOne, belongsToMany.
* Bidirectional, symmetric, and role-based same-type relationships.
* REST API with 7 endpoints across 3 controllers.
* Gutenberg sidebar panels with search, connect, and auto-save.
* WP_Query integration via the `relatewp_related` parameter.
* Related Content block with list, grid, and inline layouts.
* Admin columns for related items in post list tables.
* Connection metadata API (getMeta, setMeta, deleteMeta).
* Sortable relationship ordering with drag-and-drop reorder controls in the sidebar.
* Post deletion cleanup.
* Object cache layer with automatic invalidation.
* Batch connection fetching to avoid n+1 queries in resolvers.
* `SchemaStore` interface and `relatewp_schema_stores` filter for add-ons to inject schemas from any source.
* Shared `relatewp` admin menu with an Overview page listing registered relationships.
* Developer hooks: `relatewp_init`, `relatewp_connected`, `relatewp_disconnected`, `relatewp_disconnected_all`.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
