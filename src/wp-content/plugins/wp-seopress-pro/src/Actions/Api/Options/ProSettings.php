<?php // phpcs:ignore

namespace SEOPressPro\Actions\Api\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEOPress\Core\Hooks\ExecuteHooks;

/**
 * PRO Settings REST API endpoint.
 */
class ProSettings implements ExecuteHooks {
	/**
	 * Current user ID
	 *
	 * @var int
	 */
	private $current_user = '';

	/**
	 * @since 9.7.0
	 */
	public function hooks() {
		$this->current_user = wp_get_current_user()->ID;
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * Permission check.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return boolean
	 */
	public function permissionCheck( \WP_REST_Request $request ) {
		$current_user = $this->current_user ? $this->current_user : wp_get_current_user()->ID;
		if ( ! user_can( $current_user, 'manage_options' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register() {
		register_rest_route(
			'seopress/v1',
			'/options/pro-settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'processGet' ),
				'permission_callback' => array( $this, 'permissionCheck' ),
			)
		);

		register_rest_route(
			'seopress/v1',
			'/options/pro-settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'processPost' ),
				'permission_callback' => array( $this, 'permissionCheck' ),
			)
		);

		register_rest_route(
			'seopress/v1',
			'/options/pro-mu-settings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'processGetMu' ),
				'permission_callback' => array( $this, 'permissionCheck' ),
			)
		);

		register_rest_route(
			'seopress/v1',
			'/options/pro-mu-settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'processPostMu' ),
				'permission_callback' => array( $this, 'permissionCheck' ),
			)
		);
	}

	/**
	 * Process POST request.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function processPost( \WP_REST_Request $request ) {
		$new_options = $request->get_json_params();

		if ( empty( $new_options ) || ! is_array( $new_options ) ) {
			return new \WP_Error(
				'invalid_data',
				__( 'Invalid data provided.', 'wp-seopress-pro' ),
				array( 'status' => 400 )
			);
		}

		// Sanitize using the existing PRO sanitize function.
		if ( function_exists( 'seopress_pro_sanitize_options_fields' ) ) {
			$sanitized_options = seopress_pro_sanitize_options_fields( $new_options );
		} else {
			$sanitized_options = map_deep( $new_options, 'sanitize_text_field' );
		}

		update_option( 'seopress_pro_option_name', $sanitized_options );

		do_action( 'seopress_pro_settings_updated', $sanitized_options );

		// Mask API keys before returning.
		$safe_options = $sanitized_options;
		foreach ( $safe_options as $key => $value ) {
			if ( $this->isApiKeyField( $key ) && ! empty( $value ) ) {
				$safe_options[ $key ] = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
			}
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Settings saved successfully.', 'wp-seopress-pro' ),
				'data'    => $safe_options,
			),
			200
		);
	}

	/**
	 * Process GET request.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function processGet( \WP_REST_Request $request ) {
		$options = get_option( 'seopress_pro_option_name' );

		if ( empty( $options ) ) {
			return new \WP_REST_Response( array() );
		}

		$data = array();

		// AI API key constants map.
		$ai_key_constants = array(
			'seopress_ai_openai_api_key'  => 'SEOPRESS_OPENAI_KEY',
			'seopress_ai_deepseek_api_key' => 'SEOPRESS_DEEPSEEK_KEY',
			'seopress_ai_gemini_api_key'  => 'SEOPRESS_GEMINI_KEY',
			'seopress_ai_mistral_api_key' => 'SEOPRESS_MISTRAL_KEY',
			'seopress_ai_claude_api_key'  => 'SEOPRESS_CLAUDE_KEY',
		);

		foreach ( $options as $key => $value ) {
			// Mask API keys for security.
			if ( $this->isApiKeyField( $key ) ) {
				$has_constant = isset( $ai_key_constants[ $key ] ) && defined( $ai_key_constants[ $key ] ) && ! empty( constant( $ai_key_constants[ $key ] ) );

				if ( $has_constant || ! empty( $value ) ) {
					$data[ $key ] = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
				} else {
					$data[ $key ] = $value;
				}
			} else {
				$data[ $key ] = $value;
			}
		}

		return new \WP_REST_Response( $data );
	}

	/**
	 * Process GET request for multisite options.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function processGetMu( \WP_REST_Request $request ) {
		$options = get_option( 'seopress_pro_mu_option_name' );

		if ( empty( $options ) ) {
			return new \WP_REST_Response( array() );
		}

		return new \WP_REST_Response( $options );
	}

	/**
	 * Process POST request for multisite options.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function processPostMu( \WP_REST_Request $request ) {
		$new_options = $request->get_json_params();

		if ( empty( $new_options ) || ! is_array( $new_options ) ) {
			return new \WP_Error(
				'invalid_data',
				__( 'Invalid data provided.', 'wp-seopress-pro' ),
				array( 'status' => 400 )
			);
		}

		$sanitized_mu_options = map_deep( $new_options, 'sanitize_text_field' );
		update_option( 'seopress_pro_mu_option_name', $sanitized_mu_options );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Settings saved successfully.', 'wp-seopress-pro' ),
				'data'    => $new_options,
			),
			200
		);
	}

	/**
	 * Check if a field is an API key that should be masked.
	 *
	 * @param string $key The field key.
	 *
	 * @return boolean
	 */
	private function isApiKeyField( $key ) {
		$api_key_fields = array(
			'seopress_ai_openai_api_key',
			'seopress_ai_deepseek_api_key',
			'seopress_ai_gemini_api_key',
			'seopress_ai_mistral_api_key',
			'seopress_ai_claude_api_key',
			'seopress_ps_api_key',
			'seopress_google_analytics_auth_secret_id',
			'seopress_google_analytics_auth_client_id',
		);

		return in_array( $key, $api_key_fields, true );
	}
}
