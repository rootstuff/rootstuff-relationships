<?php

declare( strict_types=1 );

namespace Rootstuff\Relationships\Database;

defined( 'ABSPATH' ) || exit;

final class Tables {

	public static function relationships(): string {
		global $wpdb;
		return $wpdb->prefix . 'rootstuff_rel_relationships';
	}

	public static function relationship_meta(): string {
		global $wpdb;
		return $wpdb->prefix . 'rootstuff_rel_relationship_meta';
	}
}
