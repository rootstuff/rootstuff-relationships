<?php

declare( strict_types=1 );

namespace Rootstuff\Relationships;

use InvalidArgumentException;
use Rootstuff\Relationships\Cache\RelationshipCache;
use Rootstuff\Relationships\Database\Tables;

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
		do_action( 'rs_relationship_connected', $rel_type, $from_id, $to_id, $connection_id );

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
			RelationshipCache::flush_for_object( $rel_type, $from_id );
			RelationshipCache::flush_for_object( $rel_type, $to_id );

			do_action( 'rs_relationship_disconnected', $rel_type, $from_id, $to_id );
		}

		return (bool) $deleted;
	}

	/**
	 * Get related objects as WP_Post instances.
	 *
	 * @param string $rel_type  Relationship key.
	 * @param int    $object_id The object to query from.
	 * @param string $direction 'from' = get objects this is connected TO,
	 *                          'to'   = get objects connected FROM this.
	 * @param array  $args      Optional: post_status, orderby, order, limit.
	 *
	 * @return \WP_Post[]
	 */
	public static function get( string $rel_type, int $object_id, string $direction = 'from', array $args = [] ): array {
		$ids = self::getIds( $rel_type, $object_id, $direction );

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
	 * @param string $rel_type  Relationship key.
	 * @param int    $object_id The object to query from.
	 * @param string $direction 'from' or 'to'.
	 *
	 * @return int[]
	 */
	public static function getIds( string $rel_type, int $object_id, string $direction = 'from' ): array {
		self::validate_rel_type( $rel_type );

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
				"SELECT rel_type, from_object_id, to_object_id FROM {$table} WHERE from_object_id = %d OR to_object_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$object_id,
				$object_id
			)
		);

		foreach ( $rows as $row ) {
			RelationshipCache::flush_for_object( $row->rel_type, (int) $row->from_object_id );
			RelationshipCache::flush_for_object( $row->rel_type, (int) $row->to_object_id );
		}

		$deleted_from = $wpdb->delete( $table, [ 'from_object_id' => $object_id ], [ '%d' ] );
		$deleted_to   = $wpdb->delete( $table, [ 'to_object_id' => $object_id ], [ '%d' ] );

		$total = ( $deleted_from ?: 0 ) + ( $deleted_to ?: 0 );

		if ( $total > 0 ) {
			do_action( 'rs_relationship_disconnected_all', $object_id, $total );
		}

		return $total;
	}

	/**
	 * Replace all connections for an object with a new set of IDs.
	 *
	 * Used by the Gutenberg sidebar to sync the full list on save.
	 *
	 * @param string $rel_type     Relationship key.
	 * @param int    $object_id    Source object ID.
	 * @param string $direction    'from' or 'to'.
	 * @param int[]  $connected_ids Ordered list of IDs to connect.
	 */
	public static function sync( string $rel_type, int $object_id, string $direction, array $connected_ids ): void {
		self::validate_rel_type( $rel_type );

		$current_ids = self::getIds( $rel_type, $object_id, $direction );
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
				sprintf( 'Relationship type "%s" is not registered.', $rel_type )
			);
		}
	}

	/**
	 * Enforce cardinality constraints before inserting.
	 */
	private static function enforce_cardinality( string $rel_type, int $from_id, int $to_id, array $definition ): void {
		$cardinality = $definition['cardinality'];

		if ( 'one_to_one' === $cardinality ) {
			$existing_from = self::getIds( $rel_type, $from_id, 'from' );
			if ( ! empty( $existing_from ) ) {
				throw new InvalidArgumentException(
					sprintf( 'Cardinality violation: "%s" is one-to-one, source %d already has a connection.', $rel_type, $from_id )
				);
			}
			$existing_to = self::getIds( $rel_type, $to_id, 'to' );
			if ( ! empty( $existing_to ) ) {
				throw new InvalidArgumentException(
					sprintf( 'Cardinality violation: "%s" is one-to-one, target %d already has a connection.', $rel_type, $to_id )
				);
			}
		}

		if ( 'one_to_many' === $cardinality ) {
			$existing_to = self::getIds( $rel_type, $to_id, 'to' );
			if ( ! empty( $existing_to ) ) {
				throw new InvalidArgumentException(
					sprintf( 'Cardinality violation: "%s" is one-to-many, target %d already belongs to another source.', $rel_type, $to_id )
				);
			}
		}
	}
}
