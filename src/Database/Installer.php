<?php

declare( strict_types=1 );

namespace Rootstuff\Relationships\Database;

final class Installer {

	private const DB_VERSION     = '1.0.0';
	private const DB_VERSION_KEY = 'rootstuff_rel_db_version';

	public static function install(): void {
		$installed_version = get_option( self::DB_VERSION_KEY );

		if ( self::DB_VERSION === $installed_version ) {
			return;
		}

		self::create_tables();
		update_option( self::DB_VERSION_KEY, self::DB_VERSION );
	}

	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$rel_table       = Tables::relationships();
		$meta_table      = Tables::relationship_meta();

		$sql = "CREATE TABLE {$rel_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			rel_type varchar(64) NOT NULL,
			from_object_id bigint(20) unsigned NOT NULL,
			from_object_type varchar(20) NOT NULL DEFAULT 'post',
			to_object_id bigint(20) unsigned NOT NULL,
			to_object_type varchar(20) NOT NULL DEFAULT 'post',
			sort_order int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_relationship (rel_type,from_object_id,to_object_id),
			KEY idx_rel_from (rel_type,from_object_id),
			KEY idx_rel_to (rel_type,to_object_id),
			KEY idx_from_object (from_object_id,from_object_type),
			KEY idx_to_object (to_object_id,to_object_type)
		) {$charset_collate};

		CREATE TABLE {$meta_table} (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			rel_id bigint(20) unsigned NOT NULL,
			meta_key varchar(255) NOT NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY idx_rel_id (rel_id),
			KEY idx_meta_key (meta_key(191))
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
