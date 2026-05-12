<?php

declare( strict_types=1 );

namespace RelateWP;

use InvalidArgumentException;

final class Registry {

	/** @var array<string, array> */
	private static array $relationships = [];

	private const VALID_CARDINALITIES = [ 'one_to_one', 'one_to_many', 'many_to_many' ];
	private const VALID_OBJECT_TYPES  = [ 'post', 'user', 'term' ];

	private const DEFAULTS = [
		'from'          => [],
		'to'            => [],
		'cardinality'   => 'many_to_many',
		'bidirectional' => true,
		'labels'        => [ 'from' => '', 'to' => '' ],
		'roles'         => [],
		'symmetric'     => false,
		'sortable'      => false,
		'admin_column'  => false,
		'meta_fields'   => [],
	];

	/**
	 * Register a new relationship type.
	 *
	 * @param string $key        Unique relationship key (e.g. 'author_books').
	 * @param array  $definition Relationship definition.
	 *
	 * @throws InvalidArgumentException When the definition is invalid.
	 */
	public static function register( string $key, array $definition ): void {
		if ( isset( self::$relationships[ $key ] ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Relationship "%s" is already registered.', $key )
			);
		}

		$definition = wp_parse_args( $definition, self::DEFAULTS );

		self::validate( $key, $definition );

		$definition['from'] = wp_parse_args( $definition['from'], [
			'object_type' => 'post',
			'post_type'   => '',
		] );

		$definition['to'] = wp_parse_args( $definition['to'], [
			'object_type' => 'post',
			'post_type'   => '',
		] );

		if ( empty( $definition['labels']['from'] ) ) {
			$definition['labels']['from'] = $key;
		}
		if ( empty( $definition['labels']['to'] ) ) {
			$definition['labels']['to'] = $key;
		}

		self::$relationships[ $key ] = $definition;
	}

	/**
	 * Get a single relationship definition.
	 */
	public static function get( string $key ): ?array {
		return self::$relationships[ $key ] ?? null;
	}

	/**
	 * Get all registered relationship definitions.
	 *
	 * @return array<string, array>
	 */
	public static function all(): array {
		return self::$relationships;
	}

	/**
	 * Check if a relationship type exists.
	 */
	public static function exists( string $key ): bool {
		return isset( self::$relationships[ $key ] );
	}

	/**
	 * Find all relationship types that involve a given post type.
	 *
	 * @return array<string, array> Keyed by relationship key.
	 */
	public static function for_post_type( string $post_type ): array {
		$matches = [];

		foreach ( self::$relationships as $key => $def ) {
			$from_type = $def['from']['post_type'] ?? '';
			$to_type   = $def['to']['post_type'] ?? '';

			if ( $from_type === $post_type || $to_type === $post_type ) {
				$matches[ $key ] = $def;
			}
		}

		return $matches;
	}

	/**
	 * Resolve a side hint to the internal 'from' or 'to' direction.
	 *
	 * Accepts a post type name, a role name, or null for auto-detection.
	 * Returns 'from', 'to', or 'both' (for symmetric same-type relationships).
	 *
	 * @param string      $rel_type  Registered relationship key.
	 * @param int|null    $object_id Object ID for auto-detection (uses get_post_type).
	 * @param string|null $side      Post type, role name, or null.
	 *
	 * @return string 'from', 'to', or 'both'.
	 *
	 * @throws InvalidArgumentException When the side cannot be resolved.
	 */
	public static function resolveSide( string $rel_type, ?int $object_id = null, ?string $side = null ): string {
		$definition = self::get( $rel_type );

		if ( null === $definition ) {
			throw new InvalidArgumentException(
				sprintf( 'Relationship type "%s" is not registered.', $rel_type )
			);
		}

		$from_type  = $definition['from']['post_type'] ?? '';
		$to_type    = $definition['to']['post_type'] ?? '';
		$roles      = $definition['roles'] ?? [];
		$symmetric  = $definition['symmetric'] ?? false;
		$same_type  = ( $from_type === $to_type && '' !== $from_type );

		if ( null === $side && null !== $object_id ) {
			$side = get_post_type( $object_id );
			if ( false === $side ) {
				$side = null;
			}
		}

		if ( null === $side ) {
			if ( $symmetric ) {
				return 'both';
			}
			throw new InvalidArgumentException(
				sprintf( 'Cannot auto-detect side for relationship "%s": no object ID or post type provided.', $rel_type )
			);
		}

		if ( ! empty( $roles ) ) {
			if ( isset( $roles[0] ) && $roles[0] === $side ) {
				return 'from';
			}
			if ( isset( $roles[1] ) && $roles[1] === $side ) {
				return 'to';
			}
		}

		if ( $same_type && empty( $roles ) ) {
			if ( $symmetric ) {
				return 'both';
			}
			throw new InvalidArgumentException(
				sprintf( 'Ambiguous side for same-type relationship "%s". Provide roles or mark as symmetric.', $rel_type )
			);
		}

		if ( $side === $from_type ) {
			return 'from';
		}
		if ( $side === $to_type ) {
			return 'to';
		}

		throw new InvalidArgumentException(
			sprintf( 'Cannot resolve side "%s" for relationship "%s".', $side, $rel_type )
		);
	}

	/**
	 * Reset the registry. Intended for testing only.
	 *
	 * @internal
	 */
	public static function reset(): void {
		self::$relationships = [];
	}

	private static function validate( string $key, array $definition ): void {
		if ( empty( $key ) || strlen( $key ) > 64 ) {
			throw new InvalidArgumentException(
				'Relationship key must be between 1 and 64 characters.'
			);
		}

		if ( empty( $definition['from'] ) || ! is_array( $definition['from'] ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Relationship "%s" requires a "from" definition.', $key )
			);
		}

		if ( empty( $definition['to'] ) || ! is_array( $definition['to'] ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Relationship "%s" requires a "to" definition.', $key )
			);
		}

		$cardinality = $definition['cardinality'];
		if ( ! in_array( $cardinality, self::VALID_CARDINALITIES, true ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Relationship "%s" has invalid cardinality "%s". Valid: %s',
					$key,
					$cardinality,
					implode( ', ', self::VALID_CARDINALITIES )
				)
			);
		}

		foreach ( [ 'from', 'to' ] as $side ) {
			$object_type = $definition[ $side ]['object_type'] ?? 'post';
			if ( ! in_array( $object_type, self::VALID_OBJECT_TYPES, true ) ) {
				throw new InvalidArgumentException(
					sprintf(
						'Relationship "%s" %s side has invalid object_type "%s".',
						$key,
						$side,
						$object_type
					)
				);
			}
		}

		$from_type = $definition['from']['post_type'] ?? '';
		$to_type   = $definition['to']['post_type'] ?? '';
		$roles     = $definition['roles'] ?? [];
		$symmetric = $definition['symmetric'] ?? false;
		$same_type = ( $from_type === $to_type && '' !== $from_type );

		if ( $symmetric && ! $same_type ) {
			throw new InvalidArgumentException(
				sprintf( 'Relationship "%s" cannot be symmetric when post types differ.', $key )
			);
		}

		if ( $same_type && ! $symmetric && empty( $roles ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Same-type relationship "%s" requires roles or symmetric flag.', $key )
			);
		}

		if ( ! empty( $roles ) ) {
			if ( ! is_array( $roles ) || count( $roles ) !== 2 ) {
				throw new InvalidArgumentException(
					sprintf( 'Relationship "%s" roles must be an array of exactly two strings.', $key )
				);
			}
			if ( $roles[0] === $roles[1] ) {
				throw new InvalidArgumentException(
					sprintf( 'Relationship "%s" role names must be distinct.', $key )
				);
			}
		}
	}
}
