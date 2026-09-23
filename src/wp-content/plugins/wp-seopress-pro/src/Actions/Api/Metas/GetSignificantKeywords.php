<?php

namespace SEOPressPro\Actions\Api\Metas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEOPress\Core\Hooks\ExecuteHooks;

class GetSignificantKeywords implements ExecuteHooks {
	/**
	 * @var int|null
	 */
	private $current_user;

	public function hooks() {
		$this->current_user = wp_get_current_user()->ID;
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public function register() {
		register_rest_route(
			'seopress/v1',
			'/posts/(?P<id>\d+)/significant-keywords',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'processGet' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param, $request, $key ) {
							return is_numeric( $param );
						},
					),
				),
				'permission_callback' => function ( $request ) {
					$post_id      = $request['id'];
					$current_user = $this->current_user ? $this->current_user : wp_get_current_user()->ID;

					if ( ! user_can( $current_user, 'edit_post', $post_id ) ) {
						return false;
					}

					return true;
				},
			)
		);
	}

	/**
	 * @since 5.1.0
	 */
	public function processGet( \WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );

		$post    = get_post( $id );
		$content = seopress_pro_get_service( 'SignificantKeywords' )->getFullContentByPost( $post );

		$keywords = seopress_pro_get_service( 'SignificantKeywords' )->retrieveSignificantKeywords( $content );
		$data     = seopress_pro_get_service( 'SignificantKeywords' )->computeKeywords( $keywords, $content, $id );

		return new \WP_REST_Response( array( 'suggestions' => $data ) );
	}
}
