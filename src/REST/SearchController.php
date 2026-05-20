<?php

declare( strict_types=1 );

namespace Rootstuff\Relationships\REST;

defined( 'ABSPATH' ) || exit;

use Rootstuff\Relationships\Registry;
use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Server;

final class SearchController extends WP_REST_Controller {

	protected $namespace = 'rootstuff-relationships/v1';
	protected $rest_base = 'search';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'search' ],
				'permission_callback' => [ $this, 'search_permissions_check' ],
				'args'                => [
					'rel_type' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => function ( $value ) {
							return Registry::exists( $value );
						},
					],
					'side' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					],
					's' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				'exclude' => [ // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
					'default' => [],
					'type'    => 'array',
					'items'   => [ 'type' => 'integer' ],
				],
					'per_page' => [
						'default'           => 10,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return (int) $value > 0 && (int) $value <= 50;
						},
					],
				],
			],
		] );
	}

	public function search_permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Search for posts to connect.
	 *
	 * The `side` parameter identifies which side the current object is on.
	 * The search returns posts from the opposite side.
	 */
	public function search( $request ) {
		$rel_type = $request->get_param( 'rel_type' );
		$side     = $request->get_param( 'side' );
		$search   = $request->get_param( 's' );
		$exclude  = array_map( 'absint', $request->get_param( 'exclude' ) );
		$per_page = (int) $request->get_param( 'per_page' );

		$definition = Registry::get( $rel_type );
		$direction  = Registry::resolveSide( $rel_type, null, $side );

		$target_side = ( 'from' === $direction ) ? 'to' : 'from';
		$post_type   = $definition[ $target_side ]['post_type'] ?? 'post';

		$query_args = [
			'post_type'      => $post_type,
			's'              => $search,
			'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
			'posts_per_page' => $per_page,
			'post__not_in'   => $exclude, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
			'orderby'        => 'relevance',
		];

		/**
		 * Filter the search query args for the relationship selector.
		 *
		 * @param array  $query_args WP_Query arguments.
		 * @param string $rel_type   Relationship type key.
		 * @param string $side       Side identifier (post type or role).
		 */
		$query_args = apply_filters( 'rootstuff_relationships_search_query_args', $query_args, $rel_type, $side );

		$posts   = get_posts( $query_args );
		$results = [];

		foreach ( $posts as $post ) {
			$results[] = [
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'status'    => $post->post_status,
				'post_type' => $post->post_type,
			];
		}

		return new WP_REST_Response( $results, 200 );
	}
}
