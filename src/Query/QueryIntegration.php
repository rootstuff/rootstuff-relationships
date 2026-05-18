<?php

declare( strict_types=1 );

namespace RelateWP\Query;

defined( 'ABSPATH' ) || exit;

use RelateWP\Database\Tables;
use RelateWP\Registry;

/**
 * Integrates with WP_Query via `posts_clauses` to support the `relatewp_related` parameter.
 *
 * Usage:
 *   $query = new WP_Query([
 *       'post_type'  => 'book',
 *       'relatewp_related' => [
 *           'rel_type'   => 'author_books',
 *           'object_id'  => 42,
 *           'side'       => 'author',
 *       ],
 *       'orderby' => 'relatewp_sort_order',
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
		$relatewp_related = $query->get( 'relatewp_related' );

		if ( empty( $relatewp_related ) || ! is_array( $relatewp_related ) ) {
			return $clauses;
		}

		$rel_type  = sanitize_key( $relatewp_related['rel_type'] ?? '' );
		$object_id = absint( $relatewp_related['object_id'] ?? 0 );
		$side      = isset( $relatewp_related['side'] ) ? sanitize_key( $relatewp_related['side'] ) : null;

		if ( ! $rel_type || ! $object_id || ! Registry::exists( $rel_type ) ) {
			return $clauses;
		}

		$direction = Registry::resolveSide( $rel_type, $object_id, $side );

		global $wpdb;
		$rel_table = Tables::relationships();
		$alias     = 'relatewp_rel';

		if ( 'both' === $direction ) {
			$clauses['join'] .= $wpdb->prepare(
				" INNER JOIN {$rel_table} AS {$alias} ON ({$alias}.rel_type = %s AND (({$alias}.from_object_id = %d AND {$wpdb->posts}.ID = {$alias}.to_object_id) OR ({$alias}.to_object_id = %d AND {$wpdb->posts}.ID = {$alias}.from_object_id)))", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rel_type,
				$object_id,
				$object_id
			);
		} elseif ( 'from' === $direction ) {
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
		if ( 'relatewp_sort_order' === $orderby ) {
			$order               = strtoupper( $query->get( 'order' ) ) === 'DESC' ? 'DESC' : 'ASC';
			$clauses['orderby']  = "{$alias}.sort_order {$order}, {$alias}.id {$order}";
		}

		return $clauses;
	}
}
