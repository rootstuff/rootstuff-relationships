<?php
/**
 * Uninstall routine for RelateWP.
 *
 * Drops custom database tables when the plugin is deleted.
 *
 * @package RelateWP
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}relatewp_relationship_meta" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}relatewp_relationships" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

delete_option( 'relatewp_db_version' );
