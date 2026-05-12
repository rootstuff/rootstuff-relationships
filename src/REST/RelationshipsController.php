<?php

declare( strict_types=1 );

namespace RelateWP\REST;

use RelateWP\Registry;
use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Server;

final class RelationshipsController extends WP_REST_Controller {

	protected $namespace = 'relatewp/v1';
	protected $rest_base = 'relationships';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<rel_type>[a-z0-9_]+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
				'args'                => [
					'rel_type' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					],
				],
			],
		] );
	}

	public function get_items_permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
	}

	public function get_items( $request ) {
		$relationships = Registry::all();

		$data = [];
		foreach ( $relationships as $key => $definition ) {
			$data[] = self::prepare_definition( $key, $definition );
		}

		return new WP_REST_Response( $data, 200 );
	}

	public function get_item( $request ) {
		$rel_type   = $request->get_param( 'rel_type' );
		$definition = Registry::get( $rel_type );

		if ( null === $definition ) {
			return new WP_REST_Response(
				[ 'message' => sprintf( 'Relationship type "%s" not found.', $rel_type ) ],
				404
			);
		}

		return new WP_REST_Response( self::prepare_definition( $rel_type, $definition ), 200 );
	}

	private static function prepare_definition( string $key, array $definition ): array {
		return [
			'key'           => $key,
			'from'          => $definition['from'],
			'to'            => $definition['to'],
			'cardinality'   => $definition['cardinality'],
			'bidirectional' => $definition['bidirectional'],
			'labels'        => $definition['labels'],
			'sortable'      => $definition['sortable'],
		];
	}
}
