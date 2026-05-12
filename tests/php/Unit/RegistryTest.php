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
}
