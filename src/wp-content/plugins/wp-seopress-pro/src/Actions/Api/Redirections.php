<?php

namespace SEOPressPro\Actions\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEOPress\Core\Hooks\ExecuteHooks;

class Redirections implements ExecuteHooks {

	private $current_user;

	public function hooks() {
		$this->current_user = wp_get_current_user()->ID;
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * @since 8.8.0
	 *
	 * @return void
	 */
	public function register() {
		register_rest_route(
			'seopress/v1',
			'/redirections',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'processGetAll' ),
				'args'                => array(
					'id'      => array(
						'validate_callback' => function ( $param, $request, $key ) {
							return is_numeric( $param );
						},
					),
					'enabled' => array(
						'validate_callback' => function ( $param, $request, $key ) {
							if ( $param === 'yes' || $param === 'no' ) {
								return $param;
							}
							return false;
						},
					),
					'type'    => array(
						'validate_callback' => function ( $param, $request, $key ) {
							$types = array(
								'301',
								'302',
								'307',
								'404',
								'410',
								'451',
							);

							if ( is_array( $param ) ) {
								return array_intersect( $param, $types ) === $param;
							}

							return in_array( $param, $types );
						},
					),
				),
				'permission_callback' => function () {

					$current_user = $this->current_user ? $this->current_user : wp_get_current_user()->ID;

					if ( ! user_can( $current_user, 'read_redirection' ) ) {
						return false;
					}

					return true;
				},
			)
		);
	}

	/**
	 * @since 8.8.0
	 *
	 * @param \WP_REST_Request $request
	 */
	public function processGetAll( \WP_REST_Request $request ) {
		$id      = $request->get_param( 'id' );
		$enabled = $request->get_param( 'enabled' );
		$type    = $request->get_param( 'type' );

		$args = array(
			'post_type'      => 'seopress_404',
			'posts_per_page' => '-1',
		);

		if ( ! empty( $id ) ) {
			$args['p'] = $id;
		}

		if ( ! empty( $enabled ) ) {
			if ( $enabled === 'yes' ) {
				$args['meta_query'] = array(
					array(
						'key'     => '_seopress_redirections_enabled',
						'value'   => 'yes',
						'compare' => '=',
					),
				);
			} elseif ( $enabled === 'no' ) {
				$args['meta_query'] = array(
					'relation' => 'OR',
					array(
						'key'     => '_seopress_redirections_enabled',
						'value'   => 'no',
						'compare' => '=',
					),
					array(
						'key'     => '_seopress_redirections_enabled',
						'compare' => 'NOT EXISTS',
					),
				);
			}
		}
		if ( ! empty( $type ) ) {
			if ( is_array( $type ) ) {
				$args['meta_query'][] = array(
					'key'     => '_seopress_redirections_type',
					'value'   => $type,
					'compare' => 'IN',
				);
			} else {
				$args['meta_query'][] = array(
					'key'     => '_seopress_redirections_type',
					'value'   => $type,
					'compare' => '=',
				);
			}
		}

		$seopress_redirects_query = new \WP_Query( $args );

		$posts    = $seopress_redirects_query->posts;
		$response = array();

		foreach ( $posts as $post ) {
			$response[ $post->ID ] = array(
				'origin'                => esc_html( $post->post_title ),
				'destination'           => esc_url( get_post_meta( $post->ID, '_seopress_redirections_value', true ) ),
				'enabled'               => sanitize_text_field( get_post_meta( $post->ID, '_seopress_redirections_enabled', true ) ),
				'type'                  => sanitize_text_field( get_post_meta( $post->ID, '_seopress_redirections_type', true ) ),
				'param'                 => sanitize_text_field( get_post_meta( $post->ID, '_seopress_redirections_param', true ) ),
				'enabled_regex'         => sanitize_text_field( get_post_meta( $post->ID, '_seopress_redirections_enabled_regex', true ) ),
				'logged_status'         => sanitize_text_field( get_post_meta( $post->ID, '_seopress_redirections_logged_status', true ) ),
				'ip'                    => sanitize_text_field( get_post_meta( $post->ID, '_seopress_redirections_ip', true ) ),
				'ua'                    => sanitize_text_field( get_post_meta( $post->ID, 'seopress_redirections_ua', true ) ),
				'full_origin'           => esc_url( get_post_meta( $post->ID, 'seopress_redirections_referer', true ) ),
				'date_request'          => sanitize_text_field( get_post_meta( $post->ID, '_seopress_redirections_date_request', true ) ),
				'count'                 => absint( get_post_meta( $post->ID, 'seopress_404_count', true ) ),
				'redirect_date_request' => sanitize_text_field( get_post_meta( $post->ID, '_seopress_404_redirect_date_request', true ) ),
			);
		}

		wp_send_json_success( $response );
		return;
	}
}
