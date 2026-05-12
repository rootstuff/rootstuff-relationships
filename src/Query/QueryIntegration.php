<?php

declare( strict_types=1 );

namespace Rootstuff\Relationships\Query;

use Rootstuff\Relationships\Database\Tables;
use Rootstuff\Relationships\Registry;

/**
 * Integrates with WP_Query via `posts_clauses` to support the `rs_related` parameter.
 *
 * Usage:
 *   $query = new WP_Query([
 *       'post_type'  => 'book',
 *       'rs_related' => [
 *           'rel_type'   => 'author_books',
 *           'object_id'  => 42,
 *           'direction'  => 'from',
 *       ],
 *       'orderby' => 'rs_sort_order',
 *       'order'   => 'ASC',
 *   ]);
 */
final class QueryIntegration {

	public static function register(): void {
		add_filter( 'posts_clauses', [ self::class, 'filter_clauses' ], 10, 2 );
	}

	/**
	 * @param array     $clauses SQL clause array from WP_Query.
	 * @param \WP_Query $query   The query instance.
	 */
	public static function filter_clauses( array $clauses, \WP_Query $query ): array {
		$rs_related = $query->get( 'rs_related' );

		if ( empty( $rs_related ) || ! is_array( $rs_related ) ) {
			return $clauses;
		}

		$rel_type  = sanitize_key( $rs_related['rel_type'] ?? '' );
		$object_id = absint( $rs_related['object_id'] ?? 0 );
		$direction = sanitize_key( $rs_related['direction'] ?? 'from' );

		if ( ! $rel_type || ! $object_id || ! Registry::exists( $rel_type ) ) {
			return $clauses;
		}

		global $wpdb;
		$rel_table = Tables::relationships();
		$alias     = 'rs_rel';

		if ( 'from' === $direction ) {
			$clauses['join'] .= $wpdb->prepare(
				" INNER JOIN {$rel_table} AS {$alias} ON ({$wpdb->posts}.ID = {$alias}.to_object_id AND {$alias}.rel_type = %s AND {$alias}.from_object_id = %d)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rel_type,
				$object_id
			);
		} else {
			$clauses['join'] .= $wpdb->prepare(
				" INNER JOIN {$rel_table} AS {$alias} ON ({$wpdb->posts}.ID = {$alias}.from_object_id AND {$alias}.rel_type = %s AND {$alias}.to_object_id = %d)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rel_type,
				$object_id
			);
		}

		$orderby = $query->get( 'orderby' );
		if ( 'rs_sort_order' === $orderby ) {
			$order               = strtoupper( $query->get( 'order' ) ) === 'DESC' ? 'DESC' : 'ASC';
			$clauses['orderby']  = "{$alias}.sort_order {$order}, {$alias}.id {$order}";
		}

		return $clauses;
	}
}
