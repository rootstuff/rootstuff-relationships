<?php
/**
 * Uninstall routine for Rootstuff Relationships.
 *
 * Drops custom database tables when the plugin is deleted.
 *
 * @package Rootstuff\Relationships
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rs_relationship_meta" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rs_relationships" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

delete_option( 'rootstuff_rel_db_version' );
