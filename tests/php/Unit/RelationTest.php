<?php

declare( strict_types=1 );

namespace Rootstuff\Relationships\Tests\Unit;

use WP_UnitTestCase;
use Rootstuff\Relationships\Registry;
use Rootstuff\Relationships\Relation;
use Rootstuff\Relationships\Database\Installer;
use InvalidArgumentException;

/**
 * Integration-style tests that require a WordPress database.
 *
 * @covers \Rootstuff\Relationships\Relation
 */
class RelationTest extends WP_UnitTestCase {

	private int $author_id;
	private int $book_1_id;
	private int $book_2_id;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		Installer::install();
	}

	protected function setUp(): void {
		parent::setUp();
		Registry::reset();

		Registry::register( 'author_books', [
			'from'        => [ 'object_type' => 'post', 'post_type' => 'author' ],
			'to'          => [ 'object_type' => 'post', 'post_type' => 'book' ],
			'cardinality' => 'many_to_many',
		] );

		Registry::register( 'exclusive', [
			'from'        => [ 'object_type' => 'post', 'post_type' => 'a' ],
			'to'          => [ 'object_type' => 'post', 'post_type' => 'b' ],
			'cardinality' => 'one_to_many',
		] );

		register_post_type( 'author', [ 'public' => true ] );
		register_post_type( 'book', [ 'public' => true ] );

		$this->author_id = self::factory()->post->create( [
			'post_type'  => 'author',
			'post_title' => 'Jane Doe',
		] );
		$this->book_1_id = self::factory()->post->create( [
			'post_type'  => 'book',
			'post_title' => 'Book One',
		] );
		$this->book_2_id = self::factory()->post->create( [
			'post_type'  => 'book',
			'post_title' => 'Book Two',
		] );
	}

	protected function tearDown(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'rs_relationships';
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore
		Registry::reset();
		parent::tearDown();
	}

	public function test_connect_and_exists(): void {
		$id = Relation::connect( 'author_books', $this->author_id, $this->book_1_id );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
		$this->assertTrue( Relation::exists( 'author_books', $this->author_id, $this->book_1_id ) );
	}

	public function test_connect_prevents_self_connection(): void {
		$result = Relation::connect( 'author_books', $this->author_id, $this->author_id );
		$this->assertFalse( $result );
	}

	public function test_connect_prevents_duplicate(): void {
		Relation::connect( 'author_books', $this->author_id, $this->book_1_id );
		$duplicate = Relation::connect( 'author_books', $this->author_id, $this->book_1_id );

		$this->assertFalse( $duplicate );
	}

	public function test_disconnect(): void {
		Relation::connect( 'author_books', $this->author_id, $this->book_1_id );
		$this->assertTrue( Relation::exists( 'author_books', $this->author_id, $this->book_1_id ) );

		$result = Relation::disconnect( 'author_books', $this->author_id, $this->book_1_id );
		$this->assertTrue( $result );
		$this->assertFalse( Relation::exists( 'author_books', $this->author_id, $this->book_1_id ) );
	}

	public function test_disconnect_nonexistent_returns_false(): void {
		$result = Relation::disconnect( 'author_books', 999, 888 );
		$this->assertFalse( $result );
	}

	public function test_get_ids_by_post_type(): void {
		Relation::connect( 'author_books', $this->author_id, $this->book_1_id );
		Relation::connect( 'author_books', $this->author_id, $this->book_2_id );

		$ids = Relation::getIds( 'author_books', $this->author_id, 'author' );

		$this->assertCount( 2, $ids );
		$this->assertContains( $this->book_1_id, $ids );
		$this->assertContains( $this->book_2_id, $ids );
	}

	public function test_get_ids_auto_detect(): void {
		Relation::connect( 'author_books', $this->author_id, $this->book_1_id );
		Relation::connect( 'author_books', $this->author_id, $this->book_2_id );

		$ids = Relation::getIds( 'author_books', $this->author_id );

		$this->assertCount( 2, $ids );
		$this->assertContains( $this->book_1_id, $ids );
		$this->assertContains( $this->book_2_id, $ids );
	}

	public function test_get_ids_reverse_side(): void {
		Relation::connect( 'author_books', $this->author_id, $this->book_1_id );

		$ids = Relation::getIds( 'author_books', $this->book_1_id, 'book' );

		$this->assertCount( 1, $ids );
		$this->assertContains( $this->author_id, $ids );
	}

	public function test_get_ids_reverse_auto_detect(): void {
		Relation::connect( 'author_books', $this->author_id, $this->book_1_id );

		$ids = Relation::getIds( 'author_books', $this->book_1_id );

		$this->assertCount( 1, $ids );
		$this->assertContains( $this->author_id, $ids );
	}

	public function test_get_returns_posts(): void {
		Relation::connect( 'author_books', $this->author_id, $this->book_1_id );

		$posts = Relation::get( 'author_books', $this->author_id, 'author' );

		$this->assertCount( 1, $posts );
		$this->assertSame( $this->book_1_id, $posts[0]->ID );
		$this->assertSame( 'Book One', $posts[0]->post_title );
	}

	public function test_get_auto_detect(): void {
		Relation::connect( 'author_books', $this->author_id, $this->book_1_id );

		$posts = Relation::get( 'author_books', $this->author_id );

		$this->assertCount( 1, $posts );
		$this->assertSame( $this->book_1_id, $posts[0]->ID );
	}

	public function test_disconnect_all(): void {
		Relation::connect( 'author_books', $this->author_id, $this->book_1_id );
		Relation::connect( 'author_books', $this->author_id, $this->book_2_id );

		$deleted = Relation::disconnectAll( $this->author_id );

		$this->assertSame( 2, $deleted );
		$this->assertEmpty( Relation::getIds( 'author_books', $this->author_id, 'author' ) );
	}

	public function test_unregistered_type_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		Relation::connect( 'nonexistent', 1, 2 );
	}

	public function test_one_to_many_cardinality_enforcement(): void {
		$a1 = self::factory()->post->create( [ 'post_type' => 'a' ] );
		$a2 = self::factory()->post->create( [ 'post_type' => 'a' ] );
		$b1 = self::factory()->post->create( [ 'post_type' => 'b' ] );

		Relation::connect( 'exclusive', $a1, $b1 );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cardinality violation' );

		Relation::connect( 'exclusive', $a2, $b1 );
	}

	public function test_sync_adds_and_removes(): void {
		Relation::connect( 'author_books', $this->author_id, $this->book_1_id );

		Relation::sync( 'author_books', $this->author_id, 'author', [ $this->book_2_id ] );

		$ids = Relation::getIds( 'author_books', $this->author_id, 'author' );
		$this->assertCount( 1, $ids );
		$this->assertContains( $this->book_2_id, $ids );
		$this->assertNotContains( $this->book_1_id, $ids );
	}

	public function test_sort_order_preserved(): void {
		Relation::connect( 'author_books', $this->author_id, $this->book_2_id, [ 'sort_order' => 0 ] );
		Relation::connect( 'author_books', $this->author_id, $this->book_1_id, [ 'sort_order' => 1 ] );

		$ids = Relation::getIds( 'author_books', $this->author_id, 'author' );

		$this->assertSame( [ $this->book_2_id, $this->book_1_id ], $ids );
	}
}
