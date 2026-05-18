<?php

declare( strict_types=1 );

namespace RelateWP\Admin;

defined( 'ABSPATH' ) || exit;

use RelateWP\Registry;
use RelateWP\Relation;

final class AdminColumns {

	public static function register(): void {
		add_action( 'admin_init', [ self::class, 'setup_columns' ] );
	}

	public static function setup_columns(): void {
		$registered = Registry::all();

		foreach ( $registered as $key => $definition ) {
			if ( empty( $definition['admin_column'] ) ) {
				continue;
			}

			$from_type = $definition['from']['post_type'] ?? '';
			$to_type   = $definition['to']['post_type'] ?? '';

			if ( $from_type ) {
				self::add_column_for_post_type( $from_type, $key, $definition );
			}

			if ( $to_type && $to_type !== $from_type && $definition['bidirectional'] ) {
				self::add_column_for_post_type( $to_type, $key, $definition );
			}
		}
	}

	private static function add_column_for_post_type( string $post_type, string $rel_key, array $definition ): void {
		$from_type = $definition['from']['post_type'] ?? '';
		$label     = ( $post_type === $from_type )
			? ( $definition['labels']['from'] ?? $rel_key )
			: ( $definition['labels']['to'] ?? $rel_key );

		$column_id = 'relatewp_' . $rel_key;

		add_filter( "manage_{$post_type}_posts_columns", function ( array $columns ) use ( $column_id, $label ): array {
			$columns[ $column_id ] = esc_html( $label );
			return $columns;
		} );

		add_action( "manage_{$post_type}_posts_custom_column", function ( string $column, int $post_id ) use ( $column_id, $rel_key ): void {
			if ( $column !== $column_id ) {
				return;
			}

			$related = Relation::get( $rel_key, $post_id, null, [ 'limit' => 5 ] );

			if ( empty( $related ) ) {
				echo '&mdash;';
				return;
			}

			$links = [];
			foreach ( $related as $post ) {
				$links[] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( get_edit_post_link( $post->ID, 'raw' ) ?: '' ),
					esc_html( get_the_title( $post ) )
				);
			}

			$total = count( Relation::getIds( $rel_key, $post_id ) );
			$output = implode( ', ', $links );

			if ( $total > 5 ) {
				$output .= sprintf( ' <span class="relatewp-admin-more">+%d</span>', $total - 5 );
			}

			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per-link above
		}, 10, 2 );
	}
}
