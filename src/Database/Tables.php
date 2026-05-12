<?php

declare( strict_types=1 );

namespace RelateWP\Database;

final class Tables {

	public static function relationships(): string {
		global $wpdb;
		return $wpdb->prefix . 'relatewp_relationships';
	}

	public static function relationship_meta(): string {
		global $wpdb;
		return $wpdb->prefix . 'relatewp_relationship_meta';
	}
}
