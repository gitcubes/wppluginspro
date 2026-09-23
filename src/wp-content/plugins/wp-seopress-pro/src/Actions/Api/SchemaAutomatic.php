<?php

namespace SEOPressPro\Actions\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEOPress\Core\Hooks\ExecuteHooks;

/**
 * REST API endpoint for automatic schemas (seopress_schemas CPT).
 *
 * @since 9.7
 */
class SchemaAutomatic implements ExecuteHooks {

	/**
	 * @var int|null
	 */
	private $current_user;

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function hooks() {
		$this->current_user = wp_get_current_user()->ID;
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register() {
		register_rest_route(
			'seopress/v1',
			'/schemas',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'processGetAll' ),
				'permission_callback' => function () {
					$current_user = $this->current_user ? $this->current_user : wp_get_current_user()->ID;
					return user_can( $current_user, 'edit_schemas' );
				},
				'args'                => array(
					'type' => array(
						'validate_callback' => function ( $param ) {
							return is_string( $param );
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'seopress/v1',
			'/schemas/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'processGet' ),
				'permission_callback' => function ( $request ) {
					return current_user_can( 'edit_schema', (int) $request['id'] );
				},
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'seopress/v1',
			'/schemas',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'processCreate' ),
				'permission_callback' => function () {
					return current_user_can( 'publish_schemas' );
				},
			)
		);

		register_rest_route(
			'seopress/v1',
			'/schemas/(?P<id>\d+)',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'processUpdate' ),
				'permission_callback' => function ( $request ) {
					return current_user_can( 'edit_schema', (int) $request['id'] );
				},
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'seopress/v1',
			'/schemas/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'processDelete' ),
				'permission_callback' => function ( $request ) {
					return current_user_can( 'delete_schema', (int) $request['id'] );
				},
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Get the meta keys for a given schema type.
	 *
	 * @param string $type The schema type.
	 *
	 * @return array
	 */
	private function get_meta_keys_for_type( $type ) {
		$meta_prefixes = array(
			'articles'    => '_seopress_pro_rich_snippets_article',
			'localbusiness' => '_seopress_pro_rich_snippets_lb',
			'faq'         => '_seopress_pro_rich_snippets_faq',
			'howto'       => '_seopress_pro_rich_snippets_how_to',
			'courses'     => '_seopress_pro_rich_snippets_courses',
			'recipes'     => '_seopress_pro_rich_snippets_recipes',
			'jobs'        => '_seopress_pro_rich_snippets_jobs',
			'videos'      => '_seopress_pro_rich_snippets_videos',
			'events'      => '_seopress_pro_rich_snippets_events',
			'products'    => '_seopress_pro_rich_snippets_product',
			'softwareapp' => '_seopress_pro_rich_snippets_softwareapp',
			'services'    => '_seopress_pro_rich_snippets_service',
			'review'      => '_seopress_pro_rich_snippets_review',
			'custom'      => '_seopress_pro_rich_snippets_custom',
		);

		return isset( $meta_prefixes[ $type ] ) ? $meta_prefixes[ $type ] : '';
	}

	/**
	 * Build schema data from a post.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return array
	 */
	private function build_schema_data( $post ) {
		$id   = $post->ID;
		$type = get_post_meta( $id, '_seopress_pro_rich_snippets_type', true );

		$data = array(
			'id'    => $id,
			'title' => $post->post_title,
			'type'  => sanitize_text_field( $type ),
			'rules' => get_post_meta( $id, '_seopress_pro_rich_snippets_rules', true ),
			'meta'  => array(),
		);

		// Collect all meta starting with the type prefix.
		$prefix = $this->get_meta_keys_for_type( $type );
		if ( ! empty( $prefix ) ) {
			$all_meta = get_post_meta( $id );
			foreach ( $all_meta as $key => $values ) {
				if ( 0 === strpos( $key, $prefix ) ) {
					$data['meta'][ $key ] = maybe_unserialize( $values[0] );
				}
			}
		}

		return $data;
	}

	/**
	 * GET /seopress/v1/schemas — List all automatic schemas.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function processGetAll( \WP_REST_Request $request ) {
		$args = array(
			'post_type'      => 'seopress_schemas',
			'posts_per_page' => 100,
			'post_status'    => 'publish',
		);

		$type = $request->get_param( 'type' );
		if ( ! empty( $type ) ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_seopress_pro_rich_snippets_type',
					'value' => $type,
				),
			);
		}

		$query    = new \WP_Query( $args );
		$schemas  = array();

		foreach ( $query->posts as $post ) {
			$schemas[] = $this->build_schema_data( $post );
		}

		return new \WP_REST_Response(
			array(
				'data'  => $schemas,
				'total' => $query->found_posts,
			)
		);
	}

	/**
	 * GET /seopress/v1/schemas/{id} — Get a single automatic schema.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function processGet( \WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post || 'seopress_schemas' !== $post->post_type ) {
			return new \WP_Error(
				'not_found',
				__( 'Schema not found.', 'wp-seopress-pro' ),
				array( 'status' => 404 )
			);
		}

		return new \WP_REST_Response( $this->build_schema_data( $post ) );
	}

	/**
	 * POST /seopress/v1/schemas — Create a new automatic schema.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function processCreate( \WP_REST_Request $request ) {
		$params = $request->get_json_params();

		if ( empty( $params['title'] ) || empty( $params['type'] ) ) {
			return new \WP_Error(
				'missing_parameters',
				__( 'Title and type are required.', 'wp-seopress-pro' ),
				array( 'status' => 400 )
			);
		}

		$post_id = wp_insert_post(
			array(
				'post_title'  => sanitize_text_field( $params['title'] ),
				'post_type'   => 'seopress_schemas',
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_seopress_pro_rich_snippets_type', sanitize_text_field( $params['type'] ) );

		if ( ! empty( $params['rules'] ) ) {
			update_post_meta( $post_id, '_seopress_pro_rich_snippets_rules', map_deep( $params['rules'], 'sanitize_text_field' ) );
		}

		if ( ! empty( $params['meta'] ) && is_array( $params['meta'] ) ) {
			$sanitized_meta = map_deep( $params['meta'], 'sanitize_text_field' );
			foreach ( $sanitized_meta as $key => $value ) {
				if ( 0 === strpos( $key, '_seopress_pro_rich_snippets_' ) ) {
					update_post_meta( $post_id, $key, $value );
				}
			}
		}

		return new \WP_REST_Response(
			array(
				'code' => 'created',
				'id'   => $post_id,
				'data' => $this->build_schema_data( get_post( $post_id ) ),
			),
			201
		);
	}

	/**
	 * PUT /seopress/v1/schemas/{id} — Update an automatic schema.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function processUpdate( \WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post || 'seopress_schemas' !== $post->post_type ) {
			return new \WP_Error(
				'not_found',
				__( 'Schema not found.', 'wp-seopress-pro' ),
				array( 'status' => 404 )
			);
		}

		$params = $request->get_json_params();

		if ( ! empty( $params['title'] ) ) {
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => sanitize_text_field( $params['title'] ),
				)
			);
		}

		if ( isset( $params['type'] ) ) {
			update_post_meta( $id, '_seopress_pro_rich_snippets_type', sanitize_text_field( $params['type'] ) );
		}

		if ( isset( $params['rules'] ) ) {
			update_post_meta( $id, '_seopress_pro_rich_snippets_rules', map_deep( $params['rules'], 'sanitize_text_field' ) );
		}

		if ( ! empty( $params['meta'] ) && is_array( $params['meta'] ) ) {
			$sanitized_meta = map_deep( $params['meta'], 'sanitize_text_field' );
			foreach ( $sanitized_meta as $key => $value ) {
				if ( 0 === strpos( $key, '_seopress_pro_rich_snippets_' ) ) {
					update_post_meta( $id, $key, $value );
				}
			}
		}

		return new \WP_REST_Response(
			array(
				'code' => 'updated',
				'data' => $this->build_schema_data( get_post( $id ) ),
			)
		);
	}

	/**
	 * DELETE /seopress/v1/schemas/{id} — Delete an automatic schema.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function processDelete( \WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( ! $post || 'seopress_schemas' !== $post->post_type ) {
			return new \WP_Error(
				'not_found',
				__( 'Schema not found.', 'wp-seopress-pro' ),
				array( 'status' => 404 )
			);
		}

		wp_delete_post( $id, true );

		return new \WP_REST_Response(
			array(
				'code' => 'deleted',
				'id'   => $id,
			)
		);
	}
}
