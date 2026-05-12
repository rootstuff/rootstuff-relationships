<?php

declare( strict_types=1 );

namespace Rootstuff\Relationships;

use Rootstuff\Relationships\Database\Installer;
use Rootstuff\Relationships\Query\QueryIntegration;
use Rootstuff\Relationships\REST\RelationshipsController;
use Rootstuff\Relationships\REST\ConnectionsController;
use Rootstuff\Relationships\REST\SearchController;
use Rootstuff\Relationships\Hooks\PostDeleteHandler;

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
		add_action( 'rest_api_init', [ self::class, 'register_rest_routes' ] );
		add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue_editor_assets' ] );

		QueryIntegration::register();
		PostDeleteHandler::register();
	}

	public static function fire_init(): void {
		/**
		 * Fires when relationship types should be registered.
		 *
		 * Themes and plugins should hook here to call Registry::register().
		 */
		do_action( 'rs_relationships_init' );
	}

	public static function register_rest_routes(): void {
		( new RelationshipsController() )->register_routes();
		( new ConnectionsController() )->register_routes();
		( new SearchController() )->register_routes();
	}

	public static function enqueue_editor_assets(): void {
		$asset_file = ROOTSTUFF_REL_PATH . 'assets/js/build/editor.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'rootstuff-relationships-editor',
			ROOTSTUFF_REL_URL . 'assets/js/build/editor.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'rootstuff-relationships-editor',
			ROOTSTUFF_REL_URL . 'assets/css/editor.css',
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

			if ( $same_type && $symmetric && $current_post_type === $from_post_type ) {
				$panels[] = [
					'relType'  => $key,
					'side'     => $current_post_type,
					'label'    => $definition['labels']['from'] ?? $key,
					'postType' => $from_post_type,
				];
				continue;
			}

			if ( $same_type && ! empty( $roles ) && $current_post_type === $from_post_type ) {
				$panels[] = [
					'relType'  => $key,
					'side'     => $roles[0],
					'label'    => $definition['labels']['from'] ?? $key,
					'postType' => $to_post_type,
				];
				if ( $definition['bidirectional'] ) {
					$panels[] = [
						'relType'  => $key,
						'side'     => $roles[1],
						'label'    => $definition['labels']['to'] ?? $key,
						'postType' => $from_post_type,
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
				];
			}

			if ( $definition['bidirectional'] && $current_post_type === $to_post_type && ! $same_type ) {
				$panels[] = [
					'relType'  => $key,
					'side'     => $to_post_type,
					'label'    => $definition['labels']['to'] ?? $key,
					'postType' => $from_post_type,
				];
			}
		}

		wp_localize_script( 'rootstuff-relationships-editor', 'rsRelationships', [
			'panels'    => $panels,
			'restBase'  => rest_url( 'rootstuff-rel/v1' ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
		] );
	}
}
