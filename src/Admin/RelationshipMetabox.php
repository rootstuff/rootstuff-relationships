<?php

declare( strict_types=1 );

namespace RelateWP\Admin;

defined( 'ABSPATH' ) || exit;

use RelateWP\Registry;
use RelateWP\Relation;

/**
 * Classic-editor fallback for managing relationships.
 *
 * Renders a metabox per relationship on post-edit screens where the block
 * editor is not in use (Classic Editor plugin active, post type opted out
 * of REST, etc.). On block-editor screens the Gutenberg sidebar handles
 * the same job, so this class self-disables there.
 *
 * UX is intentionally minimal: a native `<select multiple>` listing the
 * currently-connected items plus an alphabetical pool of additional
 * candidates (capped at 200). For full search and drag-to-reorder users
 * should be on the block editor.
 */
final class RelationshipMetabox {

	private const NONCE_ACTION  = 'relatewp_metabox_save';
	private const NONCE_FIELD   = 'relatewp_metabox_nonce';
	private const PANELS_FIELD  = 'relatewp_metabox_panels';
	private const VALUES_FIELD  = 'relatewp_connections';
	private const PICKER_LIMIT  = 200;

	public static function register(): void {
		add_action( 'add_meta_boxes', [ self::class, 'add_meta_boxes' ], 10, 2 );
		add_action( 'save_post', [ self::class, 'save' ], 20, 2 );
	}

	public static function add_meta_boxes( string $post_type, $post ): void {
		if ( function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( $post_type ) ) {
			return;
		}

		foreach ( self::panels_for_post_type( $post_type ) as $panel ) {
			$id = sprintf( 'relatewp_%s_%s', $panel['relType'], $panel['side'] );

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

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		printf(
			'<input type="hidden" name="%s[]" value="%s" />',
			esc_attr( self::PANELS_FIELD ),
			esc_attr( $rel_type . '|' . $side )
		);

		$connected_ids = array_map( 'intval', Relation::getIds( $rel_type, $post->ID, $side ) );
		$candidates    = self::candidate_posts( $target_type, $connected_ids );

		echo '<div class="relatewp-metabox">';

		if ( empty( $candidates ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html( sprintf(
					/* translators: %s: target post type slug. */
					__( 'No %s posts found to connect.', 'relatewp' ),
					$target_type
				) )
			);
			echo '</div>';
			return;
		}

		$field_name = sprintf( '%s[%s][%s]', self::VALUES_FIELD, $rel_type, $side );

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Hold Cmd (macOS) or Ctrl (Windows/Linux) to select multiple.', 'relatewp' )
		);

		printf(
			'<select name="%s[]" multiple size="10" style="width:100%%;min-height:220px;">',
			esc_attr( $field_name )
		);

		foreach ( $candidates as $candidate ) {
			$is_selected = in_array( (int) $candidate->ID, $connected_ids, true );
			$title       = get_the_title( $candidate );

			printf(
				'<option value="%d"%s>%s</option>',
				(int) $candidate->ID,
				$is_selected ? ' selected' : '',
				esc_html( '' !== $title ? $title : sprintf( '(no title #%d)', (int) $candidate->ID ) )
			);
		}

		echo '</select>';

		if ( count( $candidates ) >= self::PICKER_LIMIT ) {
			printf(
				'<p class="description">%s</p>',
				esc_html( sprintf(
					/* translators: %d: maximum candidates shown in the fallback metabox. */
					__( 'Showing the first %d. Switch to the block editor for full search on larger libraries.', 'relatewp' ),
					self::PICKER_LIMIT
				) )
			);
		}

		echo '</div>';
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
	 * @return array<int, array{relType:string,side:string,label:string,postType:string}>
	 */
	private static function panels_for_post_type( string $post_type ): array {
		$panels = [];

		foreach ( Registry::all() as $key => $definition ) {
			$from_post_type = $definition['from']['post_type'] ?? '';
			$to_post_type   = $definition['to']['post_type'] ?? '';
			$roles          = $definition['roles'] ?? [];
			$symmetric      = $definition['symmetric'] ?? false;
			$same_type      = ( $from_post_type === $to_post_type && '' !== $from_post_type );

			if ( $same_type && $symmetric && $post_type === $from_post_type ) {
				$panels[] = [
					'relType'  => $key,
					'side'     => $post_type,
					'label'    => $definition['labels']['from'] ?? $key,
					'postType' => $from_post_type,
				];
				continue;
			}

			if ( $same_type && ! empty( $roles ) && $post_type === $from_post_type ) {
				$panels[] = [
					'relType'  => $key,
					'side'     => $roles[0],
					'label'    => $definition['labels']['from'] ?? $key,
					'postType' => $to_post_type,
				];
				if ( ! empty( $definition['bidirectional'] ) ) {
					$panels[] = [
						'relType'  => $key,
						'side'     => $roles[1],
						'label'    => $definition['labels']['to'] ?? $key,
						'postType' => $from_post_type,
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
				];
			}

			if ( ! empty( $definition['bidirectional'] ) && $post_type === $to_post_type && ! $same_type ) {
				$panels[] = [
					'relType'  => $key,
					'side'     => $to_post_type,
					'label'    => $definition['labels']['to'] ?? $key,
					'postType' => $from_post_type,
				];
			}
		}

		return $panels;
	}

	/**
	 * @param int[] $connected_ids
	 * @return \WP_Post[]
	 */
	private static function candidate_posts( string $target_type, array $connected_ids ): array {
		$connected_ids = array_values( array_unique( array_map( 'absint', $connected_ids ) ) );

		$connected = empty( $connected_ids ) ? [] : get_posts( [
			'post_type'           => $target_type,
			'post_status'         => [ 'publish', 'draft', 'pending', 'private', 'future' ],
			'posts_per_page'      => count( $connected_ids ),
			'post__in'            => $connected_ids,
			'orderby'             => 'post__in',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		] );

		$pool = get_posts( [
			'post_type'           => $target_type,
			'post_status'         => [ 'publish', 'draft', 'pending', 'private', 'future' ],
			'posts_per_page'      => self::PICKER_LIMIT,
			'orderby'             => 'title',
			'order'               => 'ASC',
			'post__not_in'        => $connected_ids, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- bounded list of the user's existing connections.
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		] );

		return array_merge( $connected, $pool );
	}
}
