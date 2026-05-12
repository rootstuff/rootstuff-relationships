<?php

declare( strict_types=1 );

namespace RelateWP\Hooks;

use RelateWP\Relation;

/**
 * Cleans up relationship connections when a post is permanently deleted.
 */
final class PostDeleteHandler {

	public static function register(): void {
		add_action( 'before_delete_post', [ self::class, 'on_delete' ] );
	}

	/**
	 * Remove all connections involving the deleted post.
	 *
	 * Fires on `before_delete_post` (permanent deletion only, not trash).
	 */
	public static function on_delete( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		Relation::disconnectAll( $post_id );
	}
}
