<?php

declare( strict_types=1 );

namespace Rootstuff\Relationships;

defined( 'ABSPATH' ) || exit;

use Rootstuff\Relationships\Database\Installer;
use Rootstuff\Relationships\Query\QueryIntegration;
use Rootstuff\Relationships\REST\RelationshipsController;
use Rootstuff\Relationships\REST\ConnectionsController;
use Rootstuff\Relationships\REST\SearchController;
use Rootstuff\Relationships\Hooks\PostDeleteHandler;
use Rootstuff\Relationships\Admin\AdminColumns;
use Rootstuff\Relationships\Admin\Menu;
use Rootstuff\Relationships\Admin\RelationshipMetabox;

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

		if ( defined( 'ROOTSTUFF_REL_PRO_VERSION' ) ) {
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
			Menu::register();
			RelationshipMetabox::register();
		}
	}

	public static function fire_init(): void {
		/**
		 * Fires when relationship types should be registered.
		 *
		 * Themes and plugins should hook here to call Registry::register().
		 */
		do_action( 'rootstuff_relationships_init' );

		/**
		 * Filter the list of SchemaStore instances to load schemas from.
		 *
		 * Add-ons should append their store to the array. Each store's
		 * load() return value is registered through Registry::register()
		 * with normal validation. Keys already registered (typically by
		 * code-defined relationships) are skipped, so code always wins.
		 *
		 * @since 0.2.0
		 *
		 * @param SchemaStore[] $stores
		 */
		$stores = apply_filters( 'rootstuff_relationships_schema_stores', [] );

		foreach ( $stores as $store ) {
			if ( ! $store instanceof SchemaStore ) {
				continue;
			}

			foreach ( $store->load() as $key => $definition ) {
				if ( Registry::exists( $key ) ) {
					continue;
				}

				try {
					Registry::register( $key, $definition );
				} catch ( \InvalidArgumentException $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
						error_log( sprintf( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated behind WP_DEBUG; surfaces add-on schema errors during development.
							'Rootstuff Relationships: failed to load schema "%s": %s',
							$key,
							$e->getMessage()
						) );
					}
				}
			}
		}
	}

	public static function register_blocks(): void {
		$asset_file = ROOTSTUFF_REL_PATH . 'assets/js/build/related-content.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_register_script(
			'rootstuff-relationships-related-content-editor',
			ROOTSTUFF_REL_URL . 'assets/js/build/related-content.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		register_block_type( ROOTSTUFF_REL_PATH . 'blocks/related-content' );
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

		wp_localize_script( 'rootstuff-relationships-editor', 'rootstuffRelationships', [
			'panels' => $panels,
		] );
	}
}
