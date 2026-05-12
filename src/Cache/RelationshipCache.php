<?php

declare( strict_types=1 );

namespace RelateWP\Cache;

final class RelationshipCache {

	private const GROUP = 'relatewp';

	public static function get( string $key ): mixed {
		$value = wp_cache_get( $key, self::GROUP );
		return false === $value ? null : $value;
	}

	public static function set( string $key, mixed $value, int $ttl = 3600 ): void {
		wp_cache_set( $key, $value, self::GROUP, $ttl );
	}

	public static function delete( string $key ): void {
		wp_cache_delete( $key, self::GROUP );
	}

	/**
	 * Flush all caches related to a specific relationship type and object.
	 */
	public static function flush_for_object( string $rel_type, int $object_id ): void {
		self::delete( self::build_key( $rel_type, $object_id, 'from' ) );
		self::delete( self::build_key( $rel_type, $object_id, 'to' ) );
		self::delete( self::build_key( $rel_type, $object_id, 'both' ) );
		self::delete( self::build_ids_key( $rel_type, $object_id, 'from' ) );
		self::delete( self::build_ids_key( $rel_type, $object_id, 'to' ) );
		self::delete( self::build_ids_key( $rel_type, $object_id, 'both' ) );
	}

	public static function build_key( string $rel_type, int $object_id, string $direction ): string {
		return "{$rel_type}:{$object_id}:{$direction}";
	}

	public static function build_ids_key( string $rel_type, int $object_id, string $direction ): string {
		return "{$rel_type}:{$object_id}:{$direction}:ids";
	}
}
