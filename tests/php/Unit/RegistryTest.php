<?php

declare( strict_types=1 );

namespace Rootstuff\Relationships\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rootstuff\Relationships\Registry;
use Rootstuff\Relationships\Schema;
use InvalidArgumentException;

/**
 * @covers \Rootstuff\Relationships\Registry
 * @covers \Rootstuff\Relationships\Schema
 */
class RegistryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Registry::reset();
	}

	protected function tearDown(): void {
		Registry::reset();
		parent::tearDown();
	}

	public function test_register_and_get(): void {
		Registry::register( 'author_books', [
			'from' => [ 'object_type' => 'post', 'post_type' => 'author' ],
			'to'   => [ 'object_type' => 'post', 'post_type' => 'book' ],
		] );

		$def = Registry::get( 'author_books' );

		$this->assertNotNull( $def );
		$this->assertSame( 'author', $def['from']['post_type'] );
		$this->assertSame( 'book', $def['to']['post_type'] );
		$this->assertSame( 'many_to_many', $def['cardinality'] );
		$this->assertTrue( $def['bidirectional'] );
	}

	public function test_get_returns_null_for_unknown(): void {
		$this->assertNull( Registry::get( 'nonexistent' ) );
	}

	public function test_exists(): void {
		$this->assertFalse( Registry::exists( 'test' ) );

		Registry::register( 'test', [
			'from' => [ 'object_type' => 'post', 'post_type' => 'page' ],
			'to'   => [ 'object_type' => 'post', 'post_type' => 'post' ],
		] );

		$this->assertTrue( Registry::exists( 'test' ) );
	}

	public function test_duplicate_key_throws(): void {
		Registry::register( 'dupe', [
			'from' => [ 'object_type' => 'post', 'post_type' => 'a' ],
			'to'   => [ 'object_type' => 'post', 'post_type' => 'b' ],
		] );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'already registered' );

		Registry::register( 'dupe', [
			'from' => [ 'object_type' => 'post', 'post_type' => 'c' ],
			'to'   => [ 'object_type' => 'post', 'post_type' => 'd' ],
		] );
	}

	public function test_invalid_cardinality_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'invalid cardinality' );

		Registry::register( 'bad', [
			'from'        => [ 'object_type' => 'post', 'post_type' => 'a' ],
			'to'          => [ 'object_type' => 'post', 'post_type' => 'b' ],
			'cardinality' => 'invalid_value',
		] );
	}

	public function test_missing_from_throws(): void {
		$this->expectException( InvalidArgumentException::class );

		Registry::register( 'no_from', [
			'from' => [],
			'to'   => [ 'object_type' => 'post', 'post_type' => 'b' ],
		] );
	}

	public function test_all_returns_registered(): void {
		Registry::register( 'rel_a', [
			'from' => [ 'object_type' => 'post', 'post_type' => 'a' ],
			'to'   => [ 'object_type' => 'post', 'post_type' => 'b' ],
		] );
		Registry::register( 'rel_b', [
			'from' => [ 'object_type' => 'post', 'post_type' => 'c' ],
			'to'   => [ 'object_type' => 'post', 'post_type' => 'd' ],
		] );

		$all = Registry::all();

		$this->assertCount( 2, $all );
		$this->assertArrayHasKey( 'rel_a', $all );
		$this->assertArrayHasKey( 'rel_b', $all );
	}

	public function test_for_post_type(): void {
		Registry::register( 'project_tech', [
			'from' => [ 'object_type' => 'post', 'post_type' => 'project' ],
			'to'   => [ 'object_type' => 'post', 'post_type' => 'technology' ],
		] );
		Registry::register( 'other', [
			'from' => [ 'object_type' => 'post', 'post_type' => 'news' ],
			'to'   => [ 'object_type' => 'post', 'post_type' => 'work' ],
		] );

		$matches = Registry::for_post_type( 'project' );
		$this->assertCount( 1, $matches );
		$this->assertArrayHasKey( 'project_tech', $matches );

		$tech = Registry::for_post_type( 'technology' );
		$this->assertCount( 1, $tech );
		$this->assertArrayHasKey( 'project_tech', $tech );
	}

	public function test_schema_has_many(): void {
		Schema::hasMany( 'author', 'book', 'schema_hm' );

		$def = Registry::get( 'schema_hm' );
		$this->assertNotNull( $def );
		$this->assertSame( 'one_to_many', $def['cardinality'] );
		$this->assertSame( 'author', $def['from']['post_type'] );
		$this->assertSame( 'book', $def['to']['post_type'] );
	}

	public function test_schema_belongs_to(): void {
		Schema::belongsTo( 'book', 'author', 'schema_bt' );

		$def = Registry::get( 'schema_bt' );
		$this->assertNotNull( $def );
		$this->assertSame( 'one_to_many', $def['cardinality'] );
		$this->assertSame( 'author', $def['from']['post_type'] );
		$this->assertSame( 'book', $def['to']['post_type'] );
	}

	public function test_schema_belongs_to_many(): void {
		Schema::belongsToMany( 'project', 'tech', 'schema_btm' );

		$def = Registry::get( 'schema_btm' );
		$this->assertNotNull( $def );
		$this->assertSame( 'many_to_many', $def['cardinality'] );
		$this->assertSame( 'project', $def['from']['post_type'] );
		$this->assertSame( 'tech', $def['to']['post_type'] );
	}

	public function test_schema_has_one(): void {
		Schema::hasOne( 'profile', 'user', 'schema_ho' );

		$def = Registry::get( 'schema_ho' );
		$this->assertNotNull( $def );
		$this->assertSame( 'one_to_one', $def['cardinality'] );
	}

	public function test_default_labels_use_key(): void {
		Registry::register( 'fallback_labels', [
			'from' => [ 'object_type' => 'post', 'post_type' => 'a' ],
			'to'   => [ 'object_type' => 'post', 'post_type' => 'b' ],
		] );

		$def = Registry::get( 'fallback_labels' );
		$this->assertSame( 'fallback_labels', $def['labels']['from'] );
		$this->assertSame( 'fallback_labels', $def['labels']['to'] );
	}

	public function test_resolve_side_by_post_type(): void {
		Registry::register( 'res_test', [
			'from' => [ 'object_type' => 'post', 'post_type' => 'resource' ],
			'to'   => [ 'object_type' => 'post', 'post_type' => 'post' ],
		] );

		$this->assertSame( 'from', Registry::resolveSide( 'res_test', null, 'resource' ) );
		$this->assertSame( 'to', Registry::resolveSide( 'res_test', null, 'post' ) );
	}

	public function test_resolve_side_invalid_throws(): void {
		Registry::register( 'res_inv', [
			'from' => [ 'object_type' => 'post', 'post_type' => 'page' ],
			'to'   => [ 'object_type' => 'post', 'post_type' => 'post' ],
		] );

		$this->expectException( InvalidArgumentException::class );
		Registry::resolveSide( 'res_inv', null, 'nonexistent' );
	}

	public function test_same_type_without_roles_or_symmetric_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'requires roles or symmetric' );

		Registry::register( 'bad_same', [
			'from' => [ 'object_type' => 'post', 'post_type' => 'post' ],
			'to'   => [ 'object_type' => 'post', 'post_type' => 'post' ],
		] );
	}

	public function test_symmetric_requires_same_type(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'cannot be symmetric' );

		Registry::register( 'bad_sym', [
			'from'      => [ 'object_type' => 'post', 'post_type' => 'a' ],
			'to'        => [ 'object_type' => 'post', 'post_type' => 'b' ],
			'symmetric' => true,
		] );
	}

	public function test_roles_must_be_two_elements(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'exactly two' );

		Registry::register( 'bad_roles', [
			'from'  => [ 'object_type' => 'post', 'post_type' => 'post' ],
			'to'    => [ 'object_type' => 'post', 'post_type' => 'post' ],
			'roles' => [ 'only_one' ],
		] );
	}

	public function test_roles_must_be_distinct(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'distinct' );

		Registry::register( 'bad_dupe_roles', [
			'from'  => [ 'object_type' => 'post', 'post_type' => 'post' ],
			'to'    => [ 'object_type' => 'post', 'post_type' => 'post' ],
			'roles' => [ 'same', 'same' ],
		] );
	}

	public function test_resolve_side_by_role(): void {
		Registry::register( 'rel_posts', [
			'from'  => [ 'object_type' => 'post', 'post_type' => 'post' ],
			'to'    => [ 'object_type' => 'post', 'post_type' => 'post' ],
			'roles' => [ 'source', 'related' ],
		] );

		$this->assertSame( 'from', Registry::resolveSide( 'rel_posts', null, 'source' ) );
		$this->assertSame( 'to', Registry::resolveSide( 'rel_posts', null, 'related' ) );
	}

	public function test_resolve_side_symmetric_returns_both(): void {
		Registry::register( 'sym_posts', [
			'from'      => [ 'object_type' => 'post', 'post_type' => 'post' ],
			'to'        => [ 'object_type' => 'post', 'post_type' => 'post' ],
			'symmetric' => true,
		] );

		$this->assertSame( 'both', Registry::resolveSide( 'sym_posts', null, 'post' ) );
		$this->assertSame( 'both', Registry::resolveSide( 'sym_posts', null, null ) );
	}

	public function test_schema_symmetric(): void {
		Schema::belongsToMany( 'post', 'post', 'schema_sym', [
			'symmetric' => true,
		] );

		$def = Registry::get( 'schema_sym' );
		$this->assertTrue( $def['symmetric'] );
	}

	public function test_schema_roles(): void {
		Schema::belongsToMany( 'post', 'post', 'schema_roles', [
			'roles' => [ 'parent', 'child' ],
		] );

		$def = Registry::get( 'schema_roles' );
		$this->assertSame( [ 'parent', 'child' ], $def['roles'] );
	}
}
