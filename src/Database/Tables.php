<?php

declare( strict_types=1 );

namespace Rootstuff\Relationships\Database;

final class Tables {

	public static function relationships(): string {
		global $wpdb;
		return $wpdb->prefix . 'rs_relationships';
	}

	public static function relationship_meta(): string {
		global $wpdb;
		return $wpdb->prefix . 'rs_relationship_meta';
	}
}
