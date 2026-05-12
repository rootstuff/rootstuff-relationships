<?php

declare( strict_types=1 );

namespace RelateWP\REST;

use RelateWP\Registry;
use RelateWP\Relation;
use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

final class ConnectionsController extends WP_REST_Controller {

	protected $namespace = 'relatewp/v1';
	protected $rest_base = 'connections';

	public function register_routes(): void {
		$rel_type_arg = [
			'required'          => true,
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => function ( $value ) {
				return Registry::exists( $value );
			},
		];

		$id_arg = [
			'required'          => true,
			'sanitize_callback' => 'absint',
			'validate_callback' => function ( $value ) {
				return is_numeric( $value ) && (int) $value > 0;
			},
		];

		// GET /connections/{rel_type}/{object_id}
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<rel_type>[a-z0-9_]+)/(?P<object_id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_connections' ],
				'permission_callback' => [ $this, 'read_permissions_check' ],
				'args'                => [
					'rel_type'  => $rel_type_arg,
					'object_id' => $id_arg,
					'side' => [
						'default'           => null,
						'sanitize_callback' => function ( $value ) {
							return null === $value ? null : sanitize_key( $value );
						},
					],
				],
			],
		] );

		// POST /connections/{rel_type}
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<rel_type>[a-z0-9_]+)', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_connection' ],
				'permission_callback' => [ $this, 'write_permissions_check' ],
				'args'                => [
					'rel_type'   => $rel_type_arg,
					'from_id'    => $id_arg,
					'to_id'      => $id_arg,
					'sort_order' => [
						'default'           => 0,
						'sanitize_callback' => 'absint',
					],
				],
			],
		] );

		// DELETE /connections/{rel_type}/{from_id}/{to_id}
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<rel_type>[a-z0-9_]+)/(?P<from_id>\d+)/(?P<to_id>\d+)', [
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_connection' ],
				'permission_callback' => [ $this, 'write_permissions_check' ],
				'args'                => [
					'rel_type' => $rel_type_arg,
					'from_id'  => $id_arg,
					'to_id'    => $id_arg,
				],
			],
		] );

		// POST /connections/{rel_type}/{object_id}/sync
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<rel_type>[a-z0-9_]+)/(?P<object_id>\d+)/sync', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync_connections' ],
				'permission_callback' => [ $this, 'write_permissions_check' ],
				'args'                => [
					'rel_type'      => $rel_type_arg,
					'object_id'     => $id_arg,
					'side'          => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					],
					'connected_ids' => [
						'required' => true,
						'type'     => 'array',
						'items'    => [ 'type' => 'integer' ],
					],
				],
			],
		] );
	}

	public function read_permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
	}

	public function write_permissions_check( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage relationships.', 'relatewp' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	public function get_connections( $request ) {
		$rel_type  = $request->get_param( 'rel_type' );
		$object_id = (int) $request->get_param( 'object_id' );
		$side      = $request->get_param( 'side' );

		$posts = Relation::get( $rel_type, $object_id, $side, [
			'post_status' => [ 'publish', 'draft', 'pending', 'private' ],
		] );

		$connections = [];
		$ids         = Relation::getIds( $rel_type, $object_id, $side );

		foreach ( $posts as $post ) {
			$connections[] = [
				'connected_object' => [
					'id'        => $post->ID,
					'type'      => 'post',
					'title'     => get_the_title( $post ),
					'status'    => $post->post_status,
					'post_type' => $post->post_type,
					'edit_link' => get_edit_post_link( $post->ID, 'raw' ),
				],
				'sort_order' => array_search( $post->ID, $ids, true ),
			];
		}

		return new WP_REST_Response( [
			'rel_type'    => $rel_type,
			'object_id'   => $object_id,
			'side'        => $side,
			'connections' => $connections,
			'total'       => count( $connections ),
		], 200 );
	}

	public function create_connection( $request ) {
		$rel_type   = $request->get_param( 'rel_type' );
		$from_id    = (int) $request->get_param( 'from_id' );
		$to_id      = (int) $request->get_param( 'to_id' );
		$sort_order = (int) $request->get_param( 'sort_order' );

		try {
			$connection_id = Relation::connect( $rel_type, $from_id, $to_id, [
				'sort_order' => $sort_order,
			] );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'relatewp_connection_error', $e->getMessage(), [ 'status' => 400 ] );
		}

		if ( false === $connection_id ) {
			return new WP_Error(
				'relatewp_connection_failed',
				__( 'Failed to create connection.', 'relatewp' ),
				[ 'status' => 500 ]
			);
		}

		return new WP_REST_Response( [
			'id'       => $connection_id,
			'rel_type' => $rel_type,
			'from_id'  => $from_id,
			'to_id'    => $to_id,
		], 201 );
	}

	public function delete_connection( $request ) {
		$rel_type = $request->get_param( 'rel_type' );
		$from_id  = (int) $request->get_param( 'from_id' );
		$to_id    = (int) $request->get_param( 'to_id' );

		try {
			$deleted = Relation::disconnect( $rel_type, $from_id, $to_id );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'relatewp_disconnect_error', $e->getMessage(), [ 'status' => 400 ] );
		}

		if ( ! $deleted ) {
			return new WP_Error(
				'relatewp_connection_not_found',
				__( 'Connection not found.', 'relatewp' ),
				[ 'status' => 404 ]
			);
		}

		return new WP_REST_Response( [ 'deleted' => true ], 200 );
	}

	public function sync_connections( $request ) {
		$rel_type      = $request->get_param( 'rel_type' );
		$object_id     = (int) $request->get_param( 'object_id' );
		$side          = $request->get_param( 'side' );
		$connected_ids = $request->get_param( 'connected_ids' );

		try {
			Relation::sync( $rel_type, $object_id, $side, $connected_ids );
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error( 'relatewp_sync_error', $e->getMessage(), [ 'status' => 400 ] );
		}

		$updated_ids = Relation::getIds( $rel_type, $object_id, $side );

		return new WP_REST_Response( [
			'rel_type'      => $rel_type,
			'object_id'     => $object_id,
			'side'          => $side,
			'connected_ids' => $updated_ids,
		], 200 );
	}
}
