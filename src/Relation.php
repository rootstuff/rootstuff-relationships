<?php

declare( strict_types=1 );

namespace RelateWP;

use InvalidArgumentException;
use RelateWP\Cache\RelationshipCache;
use RelateWP\Database\Tables;

final class Relation {

	/**
	 * Create a connection between two objects.
	 *
	 * @param string $rel_type Registered relationship key.
	 * @param int    $from_id  Source object ID.
	 * @param int    $to_id    Target object ID.
	 * @param array  $args     Optional: sort_order, meta.
	 *
	 * @return int|false The connection ID, or false on failure.
	 */
	public static function connect( string $rel_type, int $from_id, int $to_id, array $args = [] ): int|false {
		self::validate_rel_type( $rel_type );

		$definition = Registry::get( $rel_type );

		if ( $from_id === $to_id ) {
			return false;
		}

		self::enforce_cardinality( $rel_type, $from_id, $to_id, $definition );

		global $wpdb;
		$table = Tables::relationships();

		$sort_order = (int) ( $args['sort_order'] ?? 0 );

		$result = $wpdb->insert(
			$table,
			[
				'rel_type'         => $rel_type,
				'from_object_id'   => $from_id,
				'from_object_type' => $definition['from']['object_type'],
				'to_object_id'     => $to_id,
				'to_object_type'   => $definition['to']['object_type'],
				'sort_order'       => $sort_order,
			],
			[ '%s', '%d', '%s', '%d', '%s', '%d' ]
		);

		if ( false === $result ) {
			return false;
		}

		$connection_id = (int) $wpdb->insert_id;

		if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
			foreach ( $args['meta'] as $key => $value ) {
				self::setMeta( $connection_id, $key, $value );
			}
		}

		RelationshipCache::flush_for_object( $rel_type, $from_id );
		RelationshipCache::flush_for_object( $rel_type, $to_id );

		/**
		 * Fires after a relationship connection is created.
		 *
		 * @param string $rel_type      Relationship type key.
		 * @param int    $from_id       Source object ID.
		 * @param int    $to_id         Target object ID.
		 * @param int    $connection_id The new connection row ID.
		 */
		do_action( 'relatewp_connected', $rel_type, $from_id, $to_id, $connection_id );

		return $connection_id;
	}

	/**
	 * Remove a connection between two objects.
	 *
	 * @return bool True if a row was deleted.
	 */
	public static function disconnect( string $rel_type, int $from_id, int $to_id ): bool {
		self::validate_rel_type( $rel_type );

		global $wpdb;
		$table = Tables::relationships();

		$connection_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE rel_type = %s AND from_object_id = %d AND to_object_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rel_type,
				$from_id,
				$to_id
			)
		);

		$deleted = $wpdb->delete(
			$table,
			[
				'rel_type'       => $rel_type,
				'from_object_id' => $from_id,
				'to_object_id'   => $to_id,
			],
			[ '%s', '%d', '%d' ]
		);

		if ( $deleted ) {
			if ( $connection_id ) {
				self::deleteAllMeta( $connection_id );
			}
			RelationshipCache::flush_for_object( $rel_type, $from_id );
			RelationshipCache::flush_for_object( $rel_type, $to_id );

			do_action( 'relatewp_disconnected', $rel_type, $from_id, $to_id );
		}

		return (bool) $deleted;
	}

	/**
	 * Get related objects as WP_Post instances.
	 *
	 * @param string      $rel_type  Relationship key.
	 * @param int         $object_id The object to query from.
	 * @param string|null $side      Post type, role name, or null for auto-detect.
	 * @param array       $args      Optional: post_status, orderby, order, limit.
	 *
	 * @return \WP_Post[]
	 */
	public static function get( string $rel_type, int $object_id, ?string $side = null, array $args = [] ): array {
		$ids = self::getIds( $rel_type, $object_id, $side );

		if ( empty( $ids ) ) {
			return [];
		}

		$query_args = [
			'post_type'      => 'any',
			'post__in'       => $ids,
			'posts_per_page' => (int) ( $args['limit'] ?? -1 ),
			'post_status'    => $args['post_status'] ?? 'publish',
			'orderby'        => 'post__in',
			'order'          => 'ASC',
		];

		$posts = get_posts( $query_args );

		return $posts;
	}

	/**
	 * Get related object IDs (lightweight, no hydration).
	 *
	 * @param string      $rel_type  Relationship key.
	 * @param int         $object_id The object to query from.
	 * @param string|null $side      Post type, role name, or null for auto-detect.
	 *
	 * @return int[]
	 */
	public static function getIds( string $rel_type, int $object_id, ?string $side = null ): array {
		self::validate_rel_type( $rel_type );

		$direction = Registry::resolveSide( $rel_type, $object_id, $side );

		if ( 'both' === $direction ) {
			return self::getIdsBoth( $rel_type, $object_id );
		}

		$cache_key = RelationshipCache::build_ids_key( $rel_type, $object_id, $direction );
		$cached    = RelationshipCache::get( $cache_key );

		if ( null !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = Tables::relationships();

		if ( 'from' === $direction ) {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT to_object_id FROM {$table} WHERE rel_type = %s AND from_object_id = %d ORDER BY sort_order ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$rel_type,
					$object_id
				)
			);
		} else {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT from_object_id FROM {$table} WHERE rel_type = %s AND to_object_id = %d ORDER BY sort_order ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$rel_type,
					$object_id
				)
			);
		}

		$ids = array_map( 'intval', $ids );

		RelationshipCache::set( $cache_key, $ids );

		return $ids;
	}

	/**
	 * Batch fetch related IDs for many parent objects in a single query.
	 *
	 * Designed for resolvers that need to avoid n+1 queries (e.g. GraphQL
	 * connection resolvers fanning out across many parent nodes). Returns
	 * one entry per requested object ID — with an empty array when no
	 * connections exist — and seeds the per-object cache for each one,
	 * so subsequent `getIds()` calls hit cache.
	 *
	 * @since 0.3.0
	 *
	 * @param string $rel_type   Registered relationship key.
	 * @param int[]  $object_ids Parent object IDs to look up.
	 * @param string $direction  'from' or 'to'. Same-type symmetric
	 *                           relationships should query both sides
	 *                           via two calls; this method does not
	 *                           handle the 'both' case.
	 *
	 * @return array<int, int[]> Map of parent object ID to its related
	 *                           IDs, ordered by sort_order ASC, id ASC.
	 */
	public static function getConnectionsForObjects( string $rel_type, array $object_ids, string $direction ): array {
		self::validate_rel_type( $rel_type );

		if ( ! in_array( $direction, [ 'from', 'to' ], true ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Invalid direction "%s". Expected "from" or "to".', esc_html( $direction ) )
			);
		}

		$object_ids = array_values( array_unique( array_map( 'intval', $object_ids ) ) );
		$object_ids = array_filter( $object_ids, fn( $id ) => $id > 0 );

		$result = array_fill_keys( $object_ids, [] );

		if ( empty( $object_ids ) ) {
			return $result;
		}

		$missing = [];
		foreach ( $object_ids as $id ) {
			$cache_key = RelationshipCache::build_ids_key( $rel_type, $id, $direction );
			$cached    = RelationshipCache::get( $cache_key );
			if ( null !== $cached ) {
				$result[ $id ] = $cached;
			} else {
				$missing[] = $id;
			}
		}

		if ( empty( $missing ) ) {
			return $result;
		}

		global $wpdb;
		$table        = Tables::relationships();
		$placeholders = implode( ',', array_fill( 0, count( $missing ), '%d' ) );

		if ( 'from' === $direction ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "SELECT from_object_id AS parent, to_object_id AS related FROM {$table} WHERE rel_type = %s AND from_object_id IN ({$placeholders}) ORDER BY sort_order ASC, id ASC";
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = "SELECT to_object_id AS parent, from_object_id AS related FROM {$table} WHERE rel_type = %s AND to_object_id IN ({$placeholders}) ORDER BY sort_order ASC, id ASC";
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( [ $rel_type ], $missing ) ) );

		$grouped = array_fill_keys( $missing, [] );
		foreach ( (array) $rows as $row ) {
			$parent             = (int) $row->parent;
			$related            = (int) $row->related;
			$grouped[ $parent ] = $grouped[ $parent ] ?? [];
			$grouped[ $parent ][] = $related;
		}

		foreach ( $grouped as $parent_id => $ids ) {
			$cache_key = RelationshipCache::build_ids_key( $rel_type, $parent_id, $direction );
			RelationshipCache::set( $cache_key, $ids );
			$result[ $parent_id ] = $ids;
		}

		return $result;
	}

	/**
	 * Get IDs from both directions for symmetric relationships.
	 */
	private static function getIdsBoth( string $rel_type, int $object_id ): array {
		$cache_key = RelationshipCache::build_ids_key( $rel_type, $object_id, 'both' );
		$cached    = RelationshipCache::get( $cache_key );

		if ( null !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = Tables::relationships();

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT CASE WHEN from_object_id = %d THEN to_object_id ELSE from_object_id END AS related_id
				FROM {$table}
				WHERE rel_type = %s AND (from_object_id = %d OR to_object_id = %d)
				ORDER BY sort_order ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$object_id,
				$rel_type,
				$object_id,
				$object_id
			)
		);

		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );

		RelationshipCache::set( $cache_key, $ids );

		return $ids;
	}

	/**
	 * Check if a connection exists between two objects.
	 */
	public static function exists( string $rel_type, int $from_id, int $to_id ): bool {
		self::validate_rel_type( $rel_type );

		global $wpdb;
		$table = Tables::relationships();

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE rel_type = %s AND from_object_id = %d AND to_object_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rel_type,
				$from_id,
				$to_id
			)
		);

		return $count > 0;
	}

	/**
	 * Remove all connections for a given object across all relationship types.
	 *
	 * Used on post deletion to clean up orphaned connections.
	 */
	public static function disconnectAll( int $object_id ): int {
		global $wpdb;
		$table = Tables::relationships();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, rel_type, from_object_id, to_object_id FROM {$table} WHERE from_object_id = %d OR to_object_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$object_id,
				$object_id
			)
		);

		foreach ( $rows as $row ) {
			self::deleteAllMeta( (int) $row->id );
			RelationshipCache::flush_for_object( $row->rel_type, (int) $row->from_object_id );
			RelationshipCache::flush_for_object( $row->rel_type, (int) $row->to_object_id );
		}

		$deleted_from = $wpdb->delete( $table, [ 'from_object_id' => $object_id ], [ '%d' ] );
		$deleted_to   = $wpdb->delete( $table, [ 'to_object_id' => $object_id ], [ '%d' ] );

		$total = ( $deleted_from ?: 0 ) + ( $deleted_to ?: 0 );

		if ( $total > 0 ) {
			do_action( 'relatewp_disconnected_all', $object_id, $total );
		}

		return $total;
	}

	/**
	 * Replace all connections for an object with a new set of IDs.
	 *
	 * Used by the Gutenberg sidebar to sync the full list on save.
	 *
	 * @param string      $rel_type      Relationship key.
	 * @param int         $object_id     Source object ID.
	 * @param string|null $side          Post type, role name, or null for auto-detect.
	 * @param int[]       $connected_ids Ordered list of IDs to connect.
	 */
	public static function sync( string $rel_type, int $object_id, ?string $side, array $connected_ids ): void {
		self::validate_rel_type( $rel_type );

		$direction   = Registry::resolveSide( $rel_type, $object_id, $side );
		$current_ids = self::getIdsInternal( $rel_type, $object_id, $direction );
		$new_ids     = array_map( 'intval', $connected_ids );

		$to_remove = array_diff( $current_ids, $new_ids );
		$to_add    = array_diff( $new_ids, $current_ids );

		foreach ( $to_remove as $id ) {
			if ( 'from' === $direction ) {
				self::disconnect( $rel_type, $object_id, $id );
			} else {
				self::disconnect( $rel_type, $id, $object_id );
			}
		}

		foreach ( array_values( $new_ids ) as $order => $id ) {
			if ( in_array( $id, $to_add, true ) ) {
				if ( 'from' === $direction ) {
					self::connect( $rel_type, $object_id, $id, [ 'sort_order' => $order ] );
				} else {
					self::connect( $rel_type, $id, $object_id, [ 'sort_order' => $order ] );
				}
			} else {
				self::update_sort_order( $rel_type, $object_id, $id, $direction, $order );
			}
		}
	}

	/**
	 * Internal getIds that accepts a resolved 'from'/'to' direction.
	 * Used by sync and cardinality enforcement where the direction is already known.
	 */
	private static function getIdsInternal( string $rel_type, int $object_id, string $direction ): array {
		$cache_key = RelationshipCache::build_ids_key( $rel_type, $object_id, $direction );
		$cached    = RelationshipCache::get( $cache_key );

		if ( null !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = Tables::relationships();

		if ( 'from' === $direction ) {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT to_object_id FROM {$table} WHERE rel_type = %s AND from_object_id = %d ORDER BY sort_order ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$rel_type,
					$object_id
				)
			);
		} else {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT from_object_id FROM {$table} WHERE rel_type = %s AND to_object_id = %d ORDER BY sort_order ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$rel_type,
					$object_id
				)
			);
		}

		$ids = array_map( 'intval', $ids );

		RelationshipCache::set( $cache_key, $ids );

		return $ids;
	}

	/**
	 * Update the sort order for an existing connection.
	 */
	private static function update_sort_order( string $rel_type, int $object_id, int $connected_id, string $direction, int $order ): void {
		global $wpdb;
		$table = Tables::relationships();

		if ( 'from' === $direction ) {
			$wpdb->update(
				$table,
				[ 'sort_order' => $order ],
				[
					'rel_type'       => $rel_type,
					'from_object_id' => $object_id,
					'to_object_id'   => $connected_id,
				],
				[ '%d' ],
				[ '%s', '%d', '%d' ]
			);
		} else {
			$wpdb->update(
				$table,
				[ 'sort_order' => $order ],
				[
					'rel_type'       => $rel_type,
					'from_object_id' => $connected_id,
					'to_object_id'   => $object_id,
				],
				[ '%d' ],
				[ '%s', '%d', '%d' ]
			);
		}

		RelationshipCache::flush_for_object( $rel_type, $object_id );
		RelationshipCache::flush_for_object( $rel_type, $connected_id );
	}

	private static function validate_rel_type( string $rel_type ): void {
		if ( ! Registry::exists( $rel_type ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Relationship type "%s" is not registered.', esc_html( $rel_type ) )
			);
		}
	}

	/**
	 * Get a single meta value for a connection.
	 *
	 * @return mixed|null The unserialized value, or null if not found.
	 */
	public static function getMeta( int $connection_id, string $key ): mixed {
		global $wpdb;
		$table = Tables::relationship_meta();

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$table} WHERE rel_id = %d AND meta_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$connection_id,
				$key
			)
		);

		return null === $value ? null : maybe_unserialize( $value );
	}

	/**
	 * Get all meta for a connection.
	 *
	 * @return array<string, mixed> Keyed by meta_key.
	 */
	public static function getAllMeta( int $connection_id ): array {
		global $wpdb;
		$table = Tables::relationship_meta();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$table} WHERE rel_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$connection_id
			)
		);

		$meta = [];
		foreach ( $rows as $row ) {
			$meta[ $row->meta_key ] = maybe_unserialize( $row->meta_value );
		}

		return $meta;
	}

	/**
	 * Set a meta value for a connection (insert or update).
	 */
	public static function setMeta( int $connection_id, string $key, mixed $value ): bool {
		global $wpdb;
		$table      = Tables::relationship_meta();
		$serialized = maybe_serialize( $value );

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_id FROM {$table} WHERE rel_id = %d AND meta_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$connection_id,
				$key
			)
		);

		if ( $existing ) {
			$result = $wpdb->update(
				$table,
				[ 'meta_value' => $serialized ],
				[ 'meta_id' => (int) $existing ],
				[ '%s' ],
				[ '%d' ]
			);
			return false !== $result;
		}

		$result = $wpdb->insert(
			$table,
			[
				'rel_id'     => $connection_id,
				'meta_key'   => $key,
				'meta_value' => $serialized,
			],
			[ '%d', '%s', '%s' ]
		);

		return false !== $result;
	}

	/**
	 * Delete a specific meta key for a connection.
	 */
	public static function deleteMeta( int $connection_id, string $key ): bool {
		global $wpdb;
		$table = Tables::relationship_meta();

		$deleted = $wpdb->delete(
			$table,
			[
				'rel_id'   => $connection_id,
				'meta_key' => $key,
			],
			[ '%d', '%s' ]
		);

		return (bool) $deleted;
	}

	/**
	 * Delete all meta for a connection.
	 */
	public static function deleteAllMeta( int $connection_id ): int {
		global $wpdb;
		$table = Tables::relationship_meta();

		$deleted = $wpdb->delete(
			$table,
			[ 'rel_id' => $connection_id ],
			[ '%d' ]
		);

		return $deleted ?: 0;
	}

	/**
	 * Find the connection ID for a specific link between two objects.
	 *
	 * @return int|null The connection row ID, or null if not found.
	 */
	public static function getConnectionId( string $rel_type, int $from_id, int $to_id ): ?int {
		self::validate_rel_type( $rel_type );

		global $wpdb;
		$table = Tables::relationships();

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE rel_type = %s AND from_object_id = %d AND to_object_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rel_type,
				$from_id,
				$to_id
			)
		);

		return $id ? (int) $id : null;
	}

	/**
	 * Enforce cardinality constraints before inserting.
	 */
	private static function enforce_cardinality( string $rel_type, int $from_id, int $to_id, array $definition ): void {
		$cardinality = $definition['cardinality'];

		if ( 'one_to_one' === $cardinality ) {
			$existing_from = self::getIdsInternal( $rel_type, $from_id, 'from' );
			if ( ! empty( $existing_from ) ) {
				throw new InvalidArgumentException(
					sprintf( 'Cardinality violation: "%s" is one-to-one, source %d already has a connection.', esc_html( $rel_type ), (int) $from_id )
				);
			}
			$existing_to = self::getIdsInternal( $rel_type, $to_id, 'to' );
			if ( ! empty( $existing_to ) ) {
				throw new InvalidArgumentException(
					sprintf( 'Cardinality violation: "%s" is one-to-one, target %d already has a connection.', esc_html( $rel_type ), (int) $to_id )
				);
			}
		}

		if ( 'one_to_many' === $cardinality ) {
			$existing_to = self::getIdsInternal( $rel_type, $to_id, 'to' );
			if ( ! empty( $existing_to ) ) {
				throw new InvalidArgumentException(
					sprintf( 'Cardinality violation: "%s" is one-to-many, target %d already belongs to another source.', esc_html( $rel_type ), (int) $to_id )
				);
			}
		}
	}
}
