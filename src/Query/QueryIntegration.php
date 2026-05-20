<?php

declare( strict_types=1 );

namespace Rootstuff\Relationships\Query;

defined( 'ABSPATH' ) || exit;

use Rootstuff\Relationships\Database\Tables;
use Rootstuff\Relationships\Registry;

/**
 * Integrates with WP_Query via `posts_clauses` to support the `rootstuff_relationships_related` parameter.
 *
 * Usage:
 *   $query = new WP_Query([
 *       'post_type'  => 'book',
 *       'rootstuff_relationships_related' => [
 *           'rel_type'   => 'author_books',
 *           'object_id'  => 42,
 *           'side'       => 'author',
 *       ],
 *       'orderby' => 'rootstuff_relationships_sort_order',
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
		$related_args = $query->get( 'rootstuff_relationships_related' );

		if ( empty( $related_args ) || ! is_array( $related_args ) ) {
			return $clauses;
		}

		$rel_type  = sanitize_key( $related_args['rel_type'] ?? '' );
		$object_id = absint( $related_args['object_id'] ?? 0 );
		$side      = isset( $related_args['side'] ) ? sanitize_key( $related_args['side'] ) : null;

		if ( ! $rel_type || ! $object_id || ! Registry::exists( $rel_type ) ) {
			return $clauses;
		}

		$direction = Registry::resolveSide( $rel_type, $object_id, $side );

		global $wpdb;
		$rel_table = Tables::relationships();
		$alias     = 'rootstuff_rel';

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
		if ( 'rootstuff_relationships_sort_order' === $orderby ) {
			$order               = strtoupper( $query->get( 'order' ) ) === 'DESC' ? 'DESC' : 'ASC';
			$clauses['orderby']  = "{$alias}.sort_order {$order}, {$alias}.id {$order}";
		}

		return $clauses;
	}
}
