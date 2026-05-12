<?php

declare( strict_types=1 );

namespace RelateWP;

/**
 * Semantic sugar for defining relationships using Laravel-style terminology.
 *
 * Each method wraps Registry::register() with sensible defaults.
 */
final class Schema {

	/**
	 * Define a one-to-many relationship from the "one" side.
	 *
	 * Example: Schema::hasMany('author', 'book', 'author_books');
	 *
	 * @param string $from_post_type The "one" side post type.
	 * @param string $to_post_type   The "many" side post type.
	 * @param string $key            Relationship key.
	 * @param array  $overrides      Optional overrides for the definition.
	 */
	public static function hasMany(
		string $from_post_type,
		string $to_post_type,
		string $key,
		array $overrides = []
	): void {
		Registry::register( $key, array_merge( [
			'from'          => [ 'object_type' => 'post', 'post_type' => $from_post_type ],
			'to'            => [ 'object_type' => 'post', 'post_type' => $to_post_type ],
			'cardinality'   => 'one_to_many',
			'bidirectional' => true,
		], $overrides ) );
	}

	/**
	 * Define a one-to-many relationship from the "many" side.
	 *
	 * This is the inverse of hasMany and registers with the same cardinality.
	 * Use when you want to declare from the child's perspective.
	 *
	 * Example: Schema::belongsTo('book', 'author', 'author_books');
	 *
	 * @param string $from_post_type The "many" side (child) post type.
	 * @param string $to_post_type   The "one" side (parent) post type.
	 * @param string $key            Relationship key.
	 * @param array  $overrides      Optional overrides for the definition.
	 */
	public static function belongsTo(
		string $from_post_type,
		string $to_post_type,
		string $key,
		array $overrides = []
	): void {
		Registry::register( $key, array_merge( [
			'from'          => [ 'object_type' => 'post', 'post_type' => $to_post_type ],
			'to'            => [ 'object_type' => 'post', 'post_type' => $from_post_type ],
			'cardinality'   => 'one_to_many',
			'bidirectional' => true,
		], $overrides ) );
	}

	/**
	 * Define a many-to-many relationship.
	 *
	 * Example: Schema::belongsToMany('project', 'technology', 'project_technologies');
	 *
	 * @param string $from_post_type First post type.
	 * @param string $to_post_type   Second post type.
	 * @param string $key            Relationship key.
	 * @param array  $overrides      Optional overrides for the definition.
	 */
	public static function belongsToMany(
		string $from_post_type,
		string $to_post_type,
		string $key,
		array $overrides = []
	): void {
		Registry::register( $key, array_merge( [
			'from'          => [ 'object_type' => 'post', 'post_type' => $from_post_type ],
			'to'            => [ 'object_type' => 'post', 'post_type' => $to_post_type ],
			'cardinality'   => 'many_to_many',
			'bidirectional' => true,
		], $overrides ) );
	}

	/**
	 * Define a one-to-one relationship.
	 *
	 * Example: Schema::hasOne('user_profile', 'user', 'profile_user');
	 *
	 * @param string $from_post_type First post type.
	 * @param string $to_post_type   Second post type.
	 * @param string $key            Relationship key.
	 * @param array  $overrides      Optional overrides for the definition.
	 */
	public static function hasOne(
		string $from_post_type,
		string $to_post_type,
		string $key,
		array $overrides = []
	): void {
		Registry::register( $key, array_merge( [
			'from'          => [ 'object_type' => 'post', 'post_type' => $from_post_type ],
			'to'            => [ 'object_type' => 'post', 'post_type' => $to_post_type ],
			'cardinality'   => 'one_to_one',
			'bidirectional' => true,
		], $overrides ) );
	}
}
