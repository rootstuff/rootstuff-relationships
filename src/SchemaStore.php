<?php

declare( strict_types=1 );

namespace RelateWP;

/**
 * Pluggable storage layer for relationship definitions.
 *
 * Implement this interface and register the instance via the
 * `relatewp_schema_stores` filter to inject schemas from any source
 * (database, JSON file, remote API, etc.) without bypassing core
 * validation. Definitions returned here are passed through
 * Registry::register() with the same rules as code-defined relationships.
 *
 * @since 0.2.0
 */
interface SchemaStore {

	/**
	 * Return relationship definitions to register.
	 *
	 * @return array<string, array> Keyed by relationship key. Values are
	 *                              definition arrays accepted by
	 *                              Registry::register().
	 */
	public function load(): array;
}
