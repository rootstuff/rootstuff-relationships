<?php

declare( strict_types=1 );

namespace RelateWP;

/**
 * No-op SchemaStore representing code-defined relationships.
 *
 * Code-defined relationships are registered directly via the
 * `relatewp_init` action calling Registry::register(). This store
 * exists so consumers that introspect registered stores see a
 * consistent representation that includes the implicit code source.
 *
 * @since 0.2.0
 */
final class CodeSchemaStore implements SchemaStore {

	public function load(): array {
		return [];
	}
}
