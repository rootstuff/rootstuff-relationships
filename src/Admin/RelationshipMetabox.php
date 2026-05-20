<?php

declare( strict_types=1 );

namespace Rootstuff\Relationships\Admin;

defined( 'ABSPATH' ) || exit;

use Rootstuff\Relationships\Registry;
use Rootstuff\Relationships\Relation;

/**
 * Classic-editor fallback for managing relationships.
 *
 * Renders a metabox per relationship on post-edit screens where the block
 * editor is not in use (Classic Editor plugin active, post type opted out
 * of REST, etc.). On block-editor screens the Gutenberg sidebar handles
 * the same job, so this class self-disables there.
 *
 * The metabox itself is a thin server shell: it emits the candidate pool
 * as JSON plus a hidden-input mirror of the currently-connected IDs, then
 * the bundled vanilla-JS enhancer renders a two-zone UI (connected list
 * with remove/reorder; searchable available list with add) and keeps the
 * hidden inputs in sync. Form submit hits the standard save_post handler.
 */
final class RelationshipMetabox {

	private const NONCE_ACTION  = 'rootstuff_rel_metabox_save';
	private const NONCE_FIELD   = 'rootstuff_rel_metabox_nonce';
	private const PANELS_FIELD  = 'rootstuff_rel_metabox_panels';
	private const VALUES_FIELD  = 'rootstuff_rel_connections';
	private const PICKER_LIMIT  = 200;

	public static function register(): void {
		add_action( 'add_meta_boxes', [ self::class, 'add_meta_boxes' ], 10, 2 );
		add_action( 'save_post', [ self::class, 'save' ], 20, 2 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
			return;
		}

		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = $screen && isset( $screen->post_type ) ? (string) $screen->post_type : '';
		if ( '' === $post_type ) {
			return;
		}

		if ( function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( $post_type ) ) {
			return;
		}

		if ( empty( self::panels_for_post_type( $post_type ) ) ) {
			return;
		}

		wp_enqueue_style(
			'rootstuff-relationships-metabox',
			ROOTSTUFF_REL_URL . 'assets/css/metabox.css',
			[],
			ROOTSTUFF_REL_VERSION
		);

		wp_enqueue_script(
			'rootstuff-relationships-metabox',
			ROOTSTUFF_REL_URL . 'assets/js/metabox.js',
			[],
			ROOTSTUFF_REL_VERSION,
			true
		);
	}

	public static function add_meta_boxes( string $post_type, $post ): void {
		if ( function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( $post_type ) ) {
			return;
		}

		foreach ( self::panels_for_post_type( $post_type ) as $panel ) {
			$id = sprintf( 'rootstuff_rel_%s_%s', $panel['relType'], $panel['side'] );

			add_meta_box(
				$id,
				$panel['label'],
				[ self::class, 'render' ],
				$post_type,
				'normal',
				'default',
				$panel
			);
		}
	}

	public static function render( $post, array $box ): void {
		$panel = $box['args'] ?? [];

		if ( empty( $panel['relType'] ) || empty( $panel['side'] ) || empty( $panel['postType'] ) ) {
			return;
		}

		$rel_type    = (string) $panel['relType'];
		$side        = (string) $panel['side'];
		$target_type = (string) $panel['postType'];
		$sortable    = ! empty( $panel['sortable'] );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		printf(
			'<input type="hidden" name="%s[]" value="%s" />',
			esc_attr( self::PANELS_FIELD ),
			esc_attr( $rel_type . '|' . $side )
		);

		$connected_ids   = array_map( 'intval', Relation::getIds( $rel_type, $post->ID, $side ) );
		$connected_posts = self::fetch_connected( $target_type, $connected_ids );
		$pool_posts      = self::fetch_pool( $target_type, $connected_ids );

		$field_name = sprintf( '%s[%s][%s][]', self::VALUES_FIELD, $rel_type, $side );

		$payload = [
			'fieldName'  => $field_name,
			'sortable'   => $sortable,
			'connected'  => array_map( static function ( \WP_Post $p ): array {
				return [
					'id'    => (int) $p->ID,
					'title' => self::display_title( $p ),
				];
			}, $connected_posts ),
			'candidates' => array_map( static function ( \WP_Post $p ): array {
				return [
					'id'    => (int) $p->ID,
					'title' => self::display_title( $p ),
				];
			}, $pool_posts ),
			'atLimit'    => count( $pool_posts ) >= self::PICKER_LIMIT,
			'labels'     => [
				'connected' => __( 'Connected', 'rootstuff-relationships' ),
				'addLabel'  => __( 'Add new', 'rootstuff-relationships' ),
				'search'    => __( 'Search…', 'rootstuff-relationships' ),
				'add'       => __( 'Add', 'rootstuff-relationships' ),
				'remove'    => __( 'Remove', 'rootstuff-relationships' ),
				'moveUp'    => __( 'Move up', 'rootstuff-relationships' ),
				'moveDown'  => __( 'Move down', 'rootstuff-relationships' ),
				'empty'     => __( 'No connections yet.', 'rootstuff-relationships' ),
				'poolEmpty' => sprintf(
					/* translators: %s: target post type slug. */
					__( 'No %s posts available to connect.', 'rootstuff-relationships' ),
					$target_type
				),
				'noResults' => __( 'No matches.', 'rootstuff-relationships' ),
				'poolLimit' => sprintf(
					/* translators: %d: maximum candidates shown in the fallback metabox. */
					__( 'Showing the first %d. Switch to the block editor for full search on larger libraries.', 'rootstuff-relationships' ),
					self::PICKER_LIMIT
				),
			],
		];
		?>
		<div class="rootstuff-rel-mb"
			data-rel="<?php echo esc_attr( $rel_type ); ?>"
			data-side="<?php echo esc_attr( $side ); ?>"
			data-post-type="<?php echo esc_attr( $target_type ); ?>">
			<div class="rootstuff-rel-mb__hidden">
				<?php foreach ( $connected_posts as $cp ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( (string) (int) $cp->ID ); ?>" />
				<?php endforeach; ?>
			</div>
			<script type="application/json" class="rootstuff-rel-mb__data"><?php
				// JSON_HEX_* flags ensure the payload is safe to embed inside <script>.
				echo wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode with HEX flags is script-safe.
			?></script>
			<div class="rootstuff-rel-mb__mount"></div>
			<noscript>
				<p class="rootstuff-rel-mb__noscript">
					<?php esc_html_e( 'JavaScript is required to manage relationships from the classic editor. Existing connections are preserved on save.', 'rootstuff-relationships' ); ?>
				</p>
			</noscript>
		</div>
		<?php
	}

	public static function save( int $post_id, $post = null ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$panels_raw = [];
		if ( isset( $_POST[ self::PANELS_FIELD ] ) && is_array( $_POST[ self::PANELS_FIELD ] ) ) {
			$panels_raw = array_map(
				static fn ( $p ): string => sanitize_text_field( wp_unslash( (string) $p ) ),
				$_POST[ self::PANELS_FIELD ] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- per-element unslash + sanitize inside the array_map callback above.
			);
		}

		if ( empty( $panels_raw ) ) {
			return;
		}

		$submitted = [];
		if ( isset( $_POST[ self::VALUES_FIELD ] ) && is_array( $_POST[ self::VALUES_FIELD ] ) ) {
			// Top-level keys are relationship/side slugs validated below; per-row IDs are absint'd before use.
			$submitted = wp_unslash( $_POST[ self::VALUES_FIELD ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- structurally validated and per-element sanitized below.
		}

		foreach ( array_unique( $panels_raw ) as $panel_id ) {
			$parts = explode( '|', $panel_id, 2 );
			if ( 2 !== count( $parts ) ) {
				continue;
			}

			$rel_type = sanitize_key( $parts[0] );
			$side     = sanitize_key( $parts[1] );

			if ( '' === $rel_type || ! Registry::exists( $rel_type ) ) {
				continue;
			}

			$ids = $submitted[ $rel_type ][ $side ] ?? [];
			if ( ! is_array( $ids ) ) {
				$ids = [];
			}

			$cleaned = array_values( array_filter(
				array_map( 'absint', $ids ),
				static fn ( int $id ): bool => $id > 0
			) );

			try {
				Relation::sync( $rel_type, $post_id, '' !== $side ? $side : null, $cleaned );
			} catch ( \InvalidArgumentException $e ) {
				continue;
			}
		}
	}

	/**
	 * @return array<int, array{relType:string,side:string,label:string,postType:string,sortable:bool}>
	 */
	private static function panels_for_post_type( string $post_type ): array {
		$panels = [];

		foreach ( Registry::all() as $key => $definition ) {
			$from_post_type = $definition['from']['post_type'] ?? '';
			$to_post_type   = $definition['to']['post_type'] ?? '';
			$roles          = $definition['roles'] ?? [];
			$symmetric      = $definition['symmetric'] ?? false;
			$sortable       = ! empty( $definition['sortable'] );
			$same_type      = ( $from_post_type === $to_post_type && '' !== $from_post_type );

			if ( $same_type && $symmetric && $post_type === $from_post_type ) {
				$panels[] = [
					'relType'  => $key,
					'side'     => $post_type,
					'label'    => $definition['labels']['from'] ?? $key,
					'postType' => $from_post_type,
					'sortable' => $sortable,
				];
				continue;
			}

			if ( $same_type && ! empty( $roles ) && $post_type === $from_post_type ) {
				$panels[] = [
					'relType'  => $key,
					'side'     => $roles[0],
					'label'    => $definition['labels']['from'] ?? $key,
					'postType' => $to_post_type,
					'sortable' => $sortable,
				];
				if ( ! empty( $definition['bidirectional'] ) ) {
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

			if ( $post_type === $from_post_type ) {
				$panels[] = [
					'relType'  => $key,
					'side'     => $from_post_type,
					'label'    => $definition['labels']['from'] ?? $key,
					'postType' => $to_post_type,
					'sortable' => $sortable,
				];
			}

			if ( ! empty( $definition['bidirectional'] ) && $post_type === $to_post_type && ! $same_type ) {
				$panels[] = [
					'relType'  => $key,
					'side'     => $to_post_type,
					'label'    => $definition['labels']['to'] ?? $key,
					'postType' => $from_post_type,
					'sortable' => $sortable,
				];
			}
		}

		return $panels;
	}

	/**
	 * @param int[] $ids
	 * @return \WP_Post[]
	 */
	private static function fetch_connected( string $target_type, array $ids ): array {
		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
		if ( empty( $ids ) ) {
			return [];
		}

		return get_posts( [
			'post_type'           => $target_type,
			'post_status'         => [ 'publish', 'draft', 'pending', 'private', 'future' ],
			'posts_per_page'      => count( $ids ),
			'post__in'            => $ids,
			'orderby'             => 'post__in',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		] );
	}

	/**
	 * @param int[] $exclude_ids
	 * @return \WP_Post[]
	 */
	private static function fetch_pool( string $target_type, array $exclude_ids ): array {
		$exclude_ids = array_values( array_unique( array_map( 'absint', $exclude_ids ) ) );

		return get_posts( [
			'post_type'           => $target_type,
			'post_status'         => [ 'publish', 'draft', 'pending', 'private', 'future' ],
			'posts_per_page'      => self::PICKER_LIMIT,
			'orderby'             => 'title',
			'order'               => 'ASC',
			'post__not_in'        => $exclude_ids, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- bounded list of the user's existing connections.
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		] );
	}

	private static function display_title( \WP_Post $post ): string {
		$title = get_the_title( $post );
		if ( '' === $title ) {
			/* translators: %d: post ID. */
			$title = sprintf( __( '(no title #%d)', 'rootstuff-relationships' ), (int) $post->ID );
		}
		return $title;
	}
}
