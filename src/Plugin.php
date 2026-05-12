<?php

declare( strict_types=1 );

namespace RelateWP;

use RelateWP\Database\Installer;
use RelateWP\Query\QueryIntegration;
use RelateWP\REST\RelationshipsController;
use RelateWP\REST\ConnectionsController;
use RelateWP\REST\SearchController;
use RelateWP\Hooks\PostDeleteHandler;
use RelateWP\Admin\AdminColumns;

final class Plugin {

	private static bool $initialized = false;

	public static function activate(): void {
		Installer::install();
	}

	public static function deactivate(): void {
		// Nothing to clean on deactivation; tables persist until uninstall.
	}

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		add_action( 'init', [ self::class, 'fire_init' ], 5 );
		add_action( 'init', [ self::class, 'register_blocks' ] );
		add_action( 'rest_api_init', [ self::class, 'register_rest_routes' ] );
		add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue_editor_assets' ] );

		QueryIntegration::register();
		PostDeleteHandler::register();

		if ( is_admin() ) {
			AdminColumns::register();
		}
	}

	public static function fire_init(): void {
		/**
		 * Fires when relationship types should be registered.
		 *
		 * Themes and plugins should hook here to call Registry::register().
		 */
		do_action( 'relatewp_init' );
	}

	public static function register_blocks(): void {
		$asset_file = RELATEWP_PATH . 'assets/js/build/related-content.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_register_script(
			'relatewp-related-content-editor',
			RELATEWP_URL . 'assets/js/build/related-content.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		register_block_type( RELATEWP_PATH . 'blocks/related-content' );
	}

	public static function register_rest_routes(): void {
		( new RelationshipsController() )->register_routes();
		( new ConnectionsController() )->register_routes();
		( new SearchController() )->register_routes();
	}

	public static function enqueue_editor_assets(): void {
		$asset_file = RELATEWP_PATH . 'assets/js/build/editor.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'relatewp-editor',
			RELATEWP_URL . 'assets/js/build/editor.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'relatewp-editor',
			RELATEWP_URL . 'assets/css/editor.css',
			[],
			$asset['version']
		);

		$registered = Registry::all();
		$current_post_type = get_post_type() ?: '';

		$panels = [];
		foreach ( $registered as $key => $definition ) {
			$from_post_type = $definition['from']['post_type'] ?? '';
			$to_post_type   = $definition['to']['post_type'] ?? '';
			$roles          = $definition['roles'] ?? [];
			$symmetric      = $definition['symmetric'] ?? false;
			$same_type      = ( $from_post_type === $to_post_type && '' !== $from_post_type );

			$sortable = ! empty( $definition['sortable'] );

			if ( $same_type && $symmetric && $current_post_type === $from_post_type ) {
				$panels[] = [
					'relType'  => $key,
					'side'     => $current_post_type,
					'label'    => $definition['labels']['from'] ?? $key,
					'postType' => $from_post_type,
					'sortable' => $sortable,
				];
				continue;
			}

			if ( $same_type && ! empty( $roles ) && $current_post_type === $from_post_type ) {
				$panels[] = [
					'relType'  => $key,
					'side'     => $roles[0],
					'label'    => $definition['labels']['from'] ?? $key,
					'postType' => $to_post_type,
					'sortable' => $sortable,
				];
				if ( $definition['bidirectional'] ) {
					$panels[] = [
						'relType'  => $key,
						'side'     => $roles[1],
						'label'    => $definition['labels']['to'] ?? $key,
						'postType' => $from_post_type,
						'sortable' => $sortable,
					];
				}
				continue;
			}

			if ( $current_post_type === $from_post_type ) {
				$panels[] = [
					'relType'  => $key,
					'side'     => $from_post_type,
					'label'    => $definition['labels']['from'] ?? $key,
					'postType' => $to_post_type,
					'sortable' => $sortable,
				];
			}

			if ( $definition['bidirectional'] && $current_post_type === $to_post_type && ! $same_type ) {
				$panels[] = [
					'relType'  => $key,
					'side'     => $to_post_type,
					'label'    => $definition['labels']['to'] ?? $key,
					'postType' => $from_post_type,
					'sortable' => $sortable,
				];
			}
		}

		wp_localize_script( 'relatewp-editor', 'relateWP', [
			'panels'    => $panels,
			'restBase'  => rest_url( 'relatewp/v1' ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
		] );
	}
}
