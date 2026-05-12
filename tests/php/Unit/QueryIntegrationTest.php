<?php

declare( strict_types=1 );

namespace Rootstuff\Relationships\Tests\Unit;

use WP_UnitTestCase;
use WP_Query;
use Rootstuff\Relationships\Registry;
use Rootstuff\Relationships\Relation;
use Rootstuff\Relationships\Database\Installer;
use Rootstuff\Relationships\Query\QueryIntegration;

/**
 * @covers \Rootstuff\Relationships\Query\QueryIntegration
 */
class QueryIntegrationTest extends WP_UnitTestCase {

	private int $author_id;
	private int $book_1_id;
	private int $book_2_id;
	private int $unrelated_id;

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

		register_post_type( 'author', [ 'public' => true ] );
		register_post_type( 'book', [ 'public' => true ] );

		$this->author_id = self::factory()->post->create( [
			'post_type'  => 'author',
			'post_title' => 'Test Author',
		] );
		$this->book_1_id = self::factory()->post->create( [
			'post_type'  => 'book',
			'post_title' => 'Alpha Book',
		] );
		$this->book_2_id = self::factory()->post->create( [
			'post_type'  => 'book',
			'post_title' => 'Beta Book',
		] );
		$this->unrelated_id = self::factory()->post->create( [
			'post_type'  => 'book',
			'post_title' => 'Unrelated Book',
		] );

		Relation::connect( 'author_books', $this->author_id, $this->book_1_id, [ 'sort_order' => 1 ] );
		Relation::connect( 'author_books', $this->author_id, $this->book_2_id, [ 'sort_order' => 0 ] );
	}

	protected function tearDown(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'rs_relationships';
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore
		Registry::reset();
		parent::tearDown();
	}

	public function test_wp_query_returns_related_posts(): void {
		$query = new WP_Query( [
			'post_type'  => 'book',
			'rs_related' => [
				'rel_type'  => 'author_books',
				'object_id' => $this->author_id,
				'direction' => 'from',
			],
		] );

		$this->assertSame( 2, $query->found_posts );

		$ids = wp_list_pluck( $query->posts, 'ID' );
		$this->assertContains( $this->book_1_id, $ids );
		$this->assertContains( $this->book_2_id, $ids );
		$this->assertNotContains( $this->unrelated_id, $ids );
	}

	public function test_wp_query_sort_order(): void {
		$query = new WP_Query( [
			'post_type'  => 'book',
			'rs_related' => [
				'rel_type'  => 'author_books',
				'object_id' => $this->author_id,
				'direction' => 'from',
			],
			'orderby' => 'rs_sort_order',
			'order'   => 'ASC',
		] );

		$ids = wp_list_pluck( $query->posts, 'ID' );
		$this->assertSame( $this->book_2_id, $ids[0] );
		$this->assertSame( $this->book_1_id, $ids[1] );
	}

	public function test_wp_query_reverse_direction(): void {
		$query = new WP_Query( [
			'post_type'  => 'author',
			'rs_related' => [
				'rel_type'  => 'author_books',
				'object_id' => $this->book_1_id,
				'direction' => 'to',
			],
		] );

		$this->assertSame( 1, $query->found_posts );
		$this->assertSame( $this->author_id, $query->posts[0]->ID );
	}

	public function test_wp_query_without_rs_related_unaffected(): void {
		$query = new WP_Query( [
			'post_type'      => 'book',
			'posts_per_page' => -1,
		] );

		$this->assertSame( 3, $query->found_posts );
	}
}
