# Extending Rootstuff Relationships

Rootstuff Relationships is designed to be extended by add-on plugins. This document describes the stable extension API. Anything not listed here is internal and may change without notice.

## Public Classes

These classes form the stable public API. Their method signatures will not change without a major version bump and a deprecation cycle.

### `Rootstuff\Relationships\Schema`

Semantic helpers for code-defined relationships. Hook into `rootstuff_relationships_init` to register.

- `Schema::hasMany( string $from, string $to, string $key, array $overrides = [] )`
- `Schema::belongsTo( string $from, string $to, string $key, array $overrides = [] )`
- `Schema::belongsToMany( string $from, string $to, string $key, array $overrides = [] )`
- `Schema::hasOne( string $from, string $to, string $key, array $overrides = [] )`

### `Rootstuff\Relationships\Registry`

The relationship registry. Used by core and add-ons to inspect what's registered.

**Public methods (stable):**
- `Registry::get( string $key ): ?array`
- `Registry::all(): array`
- `Registry::exists( string $key ): bool`
- `Registry::for_post_type( string $post_type ): array`
- `Registry::resolveSide( string $rel_type, ?int $object_id = null, ?string $side = null ): string`
- `Registry::validate_definition( string $key, array $definition ): void` *(0.2.0)* — Validates a definition without registering it. Throws `InvalidArgumentException` on failure. Useful for UIs and REST endpoints that want to surface errors before saving.

**Direct registration (use sparingly):**
- `Registry::register( string $key, array $definition ): void`

Prefer `Schema::*` helpers or a `SchemaStore` over calling `Registry::register()` directly.

**Internal:**
- `Registry::reset()` — testing only.

### `Rootstuff\Relationships\Relation`

Connection CRUD and queries. All methods are public API.

- `Relation::connect( string $rel_type, int $from_id, int $to_id, array $args = [] ): int|false`
- `Relation::disconnect( string $rel_type, int $from_id, int $to_id ): bool`
- `Relation::exists( string $rel_type, int $from_id, int $to_id ): bool`
- `Relation::get( string $rel_type, int $object_id, ?string $side = null, array $args = [] ): array`
- `Relation::getIds( string $rel_type, int $object_id, ?string $side = null ): array`
- `Relation::sync( string $rel_type, int $object_id, ?string $side, array $connected_ids ): void`
- `Relation::disconnectAll( int $object_id ): int`
- `Relation::getConnectionId( string $rel_type, int $from_id, int $to_id ): ?int`

**Batch fetching (since 0.3.0):**
- `Relation::getConnectionsForObjects( string $rel_type, int[] $object_ids, string $direction ): array<int, int[]>` — Single-query lookup of related IDs for many parent objects at once. Returns one entry per requested ID (empty array when no connections). Seeds the per-object cache as a side effect, so subsequent `getIds()` calls hit cache. Use this from resolvers (REST, GraphQL, etc.) that fan out across many parents to avoid n+1 queries. The `$direction` argument must be `'from'` or `'to'` — symmetric same-type relationships should issue both calls and merge.

**Connection meta:**
- `Relation::getMeta( int $connection_id, string $key ): mixed`
- `Relation::getAllMeta( int $connection_id ): array`
- `Relation::setMeta( int $connection_id, string $key, mixed $value ): bool`
- `Relation::deleteMeta( int $connection_id, string $key ): bool`
- `Relation::deleteAllMeta( int $connection_id ): int`

### `Rootstuff\Relationships\SchemaStore` (interface, since 0.2.0)

Implement this to inject relationship definitions from any source (database, JSON file, remote API).

```php
namespace MyAddon;

use Rootstuff\Relationships\SchemaStore;

class JsonFileStore implements SchemaStore {
    public function load(): array {
        $data = json_decode( file_get_contents( __DIR__ . '/schemas.json' ), true );
        return $data ?: [];
    }
}
```

Then register it via the filter:

```php
add_filter( 'rootstuff_relationships_schema_stores', function ( array $stores ) {
    $stores[] = new \MyAddon\JsonFileStore();
    return $stores;
} );
```

Each store's returned definitions are passed through `Registry::register()` with the same validation as code-defined relationships. **Code-defined relationships always win on key conflicts** — if the same key was registered during `rootstuff_relationships_init`, the store's version is skipped.

Invalid definitions are logged via `error_log()` and skipped, so a single bad schema in a store cannot fatal the site.

## Hooks

### Filters

| Filter | Since | Purpose |
|---|---|---|
| `rootstuff_relationships_schema_stores` | 0.2.0 | Append `SchemaStore` instances to load schemas from custom sources. |
| `rootstuff_relationships_search_query_args` | 0.1.0 | Modify the WP_Query args used by the editor's search-to-connect endpoint. |

### Actions

| Action | Since | Arguments | Purpose |
|---|---|---|---|
| `rootstuff_relationships_init` | 0.1.0 | — | Register relationships via `Schema::*` or `Registry::register()`. Fires on `init` priority 5. |
| `rootstuff_relationships_connected` | 0.1.0 | `$rel_type, $from_id, $to_id, $connection_id` | After a connection is created. |
| `rootstuff_relationships_disconnected` | 0.1.0 | `$rel_type, $from_id, $to_id` | After a connection is removed. |
| `rootstuff_relationships_disconnected_all` | 0.1.0 | `$object_id, $deleted_count` | After all connections for an object are removed (e.g. on post deletion). |

## Constants

| Constant | Purpose |
|---|---|
| `ROOTSTUFF_REL_VERSION` | Plugin version. Add-ons should check this against their minimum supported version. |
| `ROOTSTUFF_REL_PATH` | Absolute filesystem path to the plugin directory (with trailing slash). |
| `ROOTSTUFF_REL_URL` | URL to the plugin directory (with trailing slash). |
| `ROOTSTUFF_REL_FILE` | Absolute path to the main plugin file. |
| `ROOTSTUFF_REL_BASENAME` | Plugin basename for `is_plugin_active()` checks. |

## Database Tables

Two tables are created at activation. Direct queries are not part of the public API — go through `Relation::*`.

- `{prefix}rootstuff_rel_relationships`
- `{prefix}rootstuff_rel_relationship_meta`

## REST API

All routes namespaced under `/wp-json/rootstuff-relationships/v1/`. See [README.md](README.md) for the endpoint reference.

Add-ons that need their own REST routes should use a distinct namespace (e.g. `rootstuff-relationships-myaddon/v1`) to avoid collisions.

## Recommended Add-on Conventions

- Namespace your PHP under `Rootstuff\Relationships\YourAddon\` so the relationship is obvious.
- Check for `class_exists( 'Rootstuff\\Relationships\\Plugin' )` and `version_compare( ROOTSTUFF_REL_VERSION, 'X.Y.Z', '>=' )` at activation. Bail with an admin notice if either fails.
- Register admin pages as submenus of the shared `rootstuff-relationships` menu slug (added in 0.2.0) so all Rootstuff Relationships UI lives under one parent.
- Use object cache groups prefixed with `rootstuff_rel_yourname_` to avoid collisions.

## Versioning

Rootstuff Relationships follows [Semantic Versioning](https://semver.org/). A breaking change to anything documented here will:

1. Be deprecated in a minor release with a `_deprecated_function()` notice.
2. Be removed no sooner than the next major release.

Internal classes and methods (anything not listed above) may change in any release.
