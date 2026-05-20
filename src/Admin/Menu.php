<?php

declare( strict_types=1 );

namespace Rootstuff\Relationships\Admin;

defined( 'ABSPATH' ) || exit;

use Rootstuff\Relationships\Registry;

/**
 * Shared top-level admin menu so add-ons can register submenus
 * under a single "Rootstuff Relationships" parent rather than cluttering the
 * sidebar with a menu per plugin.
 *
 * The menu only renders when an add-on has registered at least
 * one submenu via the `rootstuff_relationships_admin_submenus` filter, so the
 * free-only install stays clean.
 *
 * @since 0.2.0
 */
final class Menu {

	public const SLUG    = 'rootstuff-relationships';
	public const CAP     = 'manage_options';
	public const ICON    = 'dashicons-networking';

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ], 9 );
	}

	public static function add_menu(): void {
		$submenus = self::get_submenus();

		if ( empty( $submenus ) ) {
			return;
		}

		add_menu_page(
			__( 'Rootstuff Relationships', 'rootstuff-relationships' ),
			__( 'Rootstuff Relationships', 'rootstuff-relationships' ),
			self::CAP,
			self::SLUG,
			[ self::class, 'render_overview' ],
			self::ICON,
			58
		);

		add_submenu_page(
			self::SLUG,
			__( 'Overview', 'rootstuff-relationships' ),
			__( 'Overview', 'rootstuff-relationships' ),
			self::CAP,
			self::SLUG,
			[ self::class, 'render_overview' ]
		);

		foreach ( $submenus as $submenu ) {
			add_submenu_page(
				self::SLUG,
				$submenu['title'] ?? '',
				$submenu['menu_title'] ?? ( $submenu['title'] ?? '' ),
				$submenu['capability'] ?? self::CAP,
				$submenu['slug'] ?? '',
				$submenu['callback'] ?? '__return_null'
			);
		}
	}

	/**
	 * Get registered add-on submenus.
	 *
	 * Add-ons should not call add_submenu_page() directly; they should
	 * filter this list and let the menu helper register the page. This
	 * keeps load order predictable and submenu ordering controllable.
	 *
	 * @return array<int, array{slug: string, title: string, menu_title: string, capability: string, callback: callable, position?: int}>
	 */
	public static function get_submenus(): array {
		/**
		 * Filter the Rootstuff Relationships admin submenus.
		 *
		 * @since 0.2.0
		 *
		 * @param array $submenus
		 */
		$submenus = apply_filters( 'rootstuff_relationships_admin_submenus', [] );

		usort( $submenus, function ( $a, $b ) {
			return ( $a['position'] ?? 50 ) <=> ( $b['position'] ?? 50 );
		} );

		return $submenus;
	}

	/**
	 * Helper for add-ons: register a submenu page on the next admin_menu hook.
	 */
	public static function add_submenu( array $submenu ): void {
		add_filter( 'rootstuff_relationships_admin_submenus', function ( array $submenus ) use ( $submenu ) {
			$submenus[] = $submenu;
			return $submenus;
		} );
	}

	/**
	 * Default overview page: a simple at-a-glance view of registered relationships.
	 */
	public static function render_overview(): void {
		$relationships = Registry::all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Rootstuff Relationships', 'rootstuff-relationships' ); ?></h1>
			<p><?php esc_html_e( 'Registered relationships and add-on tools.', 'rootstuff-relationships' ); ?></p>

			<h2><?php esc_html_e( 'Registered Relationships', 'rootstuff-relationships' ); ?></h2>

			<?php if ( empty( $relationships ) ) : ?>
				<p><?php esc_html_e( 'No relationships are registered yet.', 'rootstuff-relationships' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Key', 'rootstuff-relationships' ); ?></th>
							<th><?php esc_html_e( 'From', 'rootstuff-relationships' ); ?></th>
							<th><?php esc_html_e( 'To', 'rootstuff-relationships' ); ?></th>
							<th><?php esc_html_e( 'Cardinality', 'rootstuff-relationships' ); ?></th>
							<th><?php esc_html_e( 'Bidirectional', 'rootstuff-relationships' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $relationships as $key => $def ) : ?>
							<tr>
								<td><code><?php echo esc_html( $key ); ?></code></td>
								<td><?php echo esc_html( $def['from']['post_type'] ?? '' ); ?></td>
								<td><?php echo esc_html( $def['to']['post_type'] ?? '' ); ?></td>
								<td><?php echo esc_html( $def['cardinality'] ); ?></td>
								<td><?php echo $def['bidirectional'] ? esc_html__( 'Yes', 'rootstuff-relationships' ) : esc_html__( 'No', 'rootstuff-relationships' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
