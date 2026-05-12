<?php

declare( strict_types=1 );

namespace RelateWP\Hooks;

/**
 * Optional hook point for post-save relationship processing.
 *
 * In v0.1, relationship saving is handled client-side via the REST API sync
 * endpoint. This handler exists as a hook point for future server-side
 * processing (e.g., classic editor form submissions in v0.2).
 */
final class SavePostHandler {

	public static function register(): void {
		// Reserved for v0.2 classic editor metabox support.
		// add_action( 'save_post', [ self::class, 'on_save' ], 20, 2 );
	}
}
