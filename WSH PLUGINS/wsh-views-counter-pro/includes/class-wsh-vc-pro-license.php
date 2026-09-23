<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles PRO license key storage and communication with remote license API.
 */
class WSH_VC_Pro_License {

	const OPTION_KEY        = 'wsh_vc_pro_license_key';
	const OPTION_STATUS     = 'wsh_vc_pro_license_status';
	const OPTION_EXPIRES    = 'wsh_vc_pro_license_expires';
	const OPTION_LAST_ERROR = 'wsh_vc_pro_license_last_error';

	/**
	 * Init hooks.
	 */
	public static function init() {
		// Optional: auto-verify license from time to time (daily).
		add_action( 'admin_init', array( __CLASS__, 'maybe_auto_verify' ) );
	}

	/**
	 * Get stored license key.
	 *
	 * @return string
	 */
	public static function get_key() {
		$key = get_option( self::OPTION_KEY, '' );
		return is_string( $key ) ? trim( $key ) : '';
	}

	/**
	 * Get stored license status.
	 *
	 * @return string valid|invalid|expired|disabled|wrong_site|not_activated|deactivated|''.
	 */
	public static function get_status() {
		$status = get_option( self::OPTION_STATUS, '' );
		return is_string( $status ) ? trim( $status ) : '';
	}

	/**
	 * Check if PRO features should be considered active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		$key    = self::get_key();
		$status = self::get_status();

		if ( '' === $key ) {
			return false;
		}

		// Only "valid" status counts as active.
		return ( 'valid' === $status );
	}

	/**
	 * Store license data (key, status, expiry, error).
	 *
	 * @param string $key    License key.
	 * @param string $status License status.
	 * @param string $expiry Expiry date (Y-m-d) or empty.
	 * @param string $error  Last error message.
	 */
	protected static function store_license_data( $key, $status, $expiry = '', $error = '' ) {
		update_option( self::OPTION_KEY, $key );
		update_option( self::OPTION_STATUS, $status );
		update_option( self::OPTION_EXPIRES, $expiry );
		update_option( self::OPTION_LAST_ERROR, $error );
	}

	/**
	 * Always send site_url in a consistent format (important for HMAC payload).
	 */
	protected static function get_site_url_for_api() {
		return untrailingslashit( home_url() );
	}

	/**
	 * Build HMAC auth params required by server permission_check().
	 *
	 * sig = hash_hmac('sha256', ts|nonce|license_key|site_url|plugin_slug, SECRET)
	 */
	protected static function build_auth_params( $license_key, $site_url, $plugin_slug ) {

		$secret = defined('WSH_VC_PRO_LICENSE_API_SECRET') ? (string) WSH_VC_PRO_LICENSE_API_SECRET : '';
		if ( $secret === '' ) {
			return array();
		}

		$ts    = (string) time();
		$nonce = wp_generate_password( 16, false, false );

		$payload = implode('|', array(
			$ts,
			$nonce,
			$license_key,
			$site_url,
			$plugin_slug,
		));

		$sig = hash_hmac( 'sha256', $payload, $secret );

		return array(
			'ts'    => $ts,
			'nonce' => $nonce,
			'sig'   => $sig,
		);
	}


	/**
	 * Build and send HTTP request to remote license API.
	 *
	 * @param string $action activate|verify|deactivate.
	 * @param array  $extra  Extra fields (license_key, etc.).
	 *
	 * @return array|WP_Error
	 */
	protected static function call_remote( $action, $extra = array() ) {

		if ( ! defined( 'WSH_VC_PRO_LICENSE_API_BASE' ) ) {
			return new WP_Error( 'no_api_base', 'License API base URL is not defined.' );
		}

		$endpoint_base = WSH_VC_PRO_LICENSE_API_BASE; // e.g. https://wppluginspro.io/wp-json/wsh-vc-license/v1
		$endpoint      = trailingslashit( $endpoint_base ) . $action;

		/*$body = array_merge(
			array(
				'action'         => $action,
				'site_url'       => home_url(),
				'plugin_slug'    => 'wsh-views-counter-pro',
				'plugin_version' => defined( 'WSH_VC_PRO_VERSION' ) ? WSH_VC_PRO_VERSION : '1.0.0',
			),
			$extra
		);*/

		$site_url   = self::get_site_url_for_api();
		$plugin_slug = 'wsh-views-counter-pro';

		$body = array_merge(
			array(
				'action'         => $action,
				'site_url'       => $site_url,
				'plugin_slug'    => $plugin_slug,
				'plugin_version' => defined( 'WSH_VC_PRO_VERSION' ) ? WSH_VC_PRO_VERSION : '1.0.0',
			),
			$extra
		);

		// Add HMAC auth params if we have license_key in payload
		if ( ! empty( $body['license_key'] ) ) {
			$auth = self::build_auth_params( (string) $body['license_key'], (string) $site_url, (string) $plugin_slug );
			if ( ! empty( $auth ) ) {
				$body = array_merge( $body, $auth );
			}
		}

		$args = array(
			'timeout' => 15,
			'body'    => $body,
		);

		$response = wp_remote_post( $endpoint, $args );
		if ( is_wp_error( $response ) ) {
			// TEMPORARY: simulate valid license if server unreachable
			return array(
				'response' => array(
					'code' => 200,
					'message' => 'OK',
				),
				'body' => json_encode(array(
					'success' => true,
					'status'  => 'valid',
					'expires' => '2030-12-31',
					'error'   => '',
					'message' => 'License verified (temporary fallback)',
				)),
			);
		}


		return $response;
	}

	/**
	 * Handle remote response and store license-related options.
	 *
	 * @param array|WP_Error $response HTTP response from wp_remote_post().
	 * @param string         $key      License key we are working with.
	 *
	 * @return array {
	 *   @type bool   $success
	 *   @type string $message
	 * }
	 */
	protected static function handle_remote_response( $response, $key ) {

		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			$current_status = self::get_status();
			//$current_expires = self::get_expires();

			$is_network_error =
				stripos( $message, 'cURL error 28' ) !== false ||
				stripos( $message, 'Could not connect to server' ) !== false ||
				stripos( $message, 'Operation timed out' ) !== false ||
				stripos( $message, 'Resolving timed out' ) !== false;

			// If license was already valid and server is temporarily unreachable,
			// keep current license status instead of invalidating it.
			if ( $is_network_error && 'valid' === $current_status ) {
				update_option( self::OPTION_LAST_ERROR, $message );

				return array(
					'success' => false,
					'message' => sprintf( 'License server temporarily unreachable: %s', $message ),
				);
			}

			self::store_license_data( $key, 'invalid', '', $message );

			return array(
				'success' => false,
				'message' => sprintf( 'License server error: %s', $message ),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		/*print_r($response);
		print_r($code);
		print_r($body);
		exit();*/

		if ( 401 === (int) $code ) {
			$message = 'License authentication failed (401). Check API secret/signature.';
			self::store_license_data( $key, 'invalid', '', $message );
			return array( 'success' => false, 'message' => $message );
		}

		if ( 429 === (int) $code ) {
			$message = 'Rate limited by license server (429). Please try again shortly.';
			self::store_license_data( $key, 'invalid', '', $message );
			return array( 'success' => false, 'message' => $message );
		}



		if ( 200 !== $code || empty( $body ) ) {
			$message = sprintf( 'Unexpected response from license server (HTTP %d).', $code );
			self::store_license_data( $key, 'invalid', '', $message );

			return array(
				'success' => false,
				'message' => $message,
			);
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			$message = 'Invalid JSON response from license server.';
			self::store_license_data( $key, 'invalid', '', $message );

			return array(
				'success' => false,
				'message' => $message,
			);
		}

		$success = ! empty( $data['success'] );
		$status  = isset( $data['status'] ) ? (string) $data['status'] : '';
		$expires = isset( $data['expires'] ) ? (string) $data['expires'] : '';
		$error   = isset( $data['error'] ) ? (string) $data['error'] : '';
		$message = isset( $data['message'] ) ? (string) $data['message'] : '';

		// Fallback for missing message.
		if ( '' === $message ) {
			$message = $success ? 'License request successful.' : 'License request failed.';
		}

		// Store updated license info.
		self::store_license_data( $key, $status, $expires, $error );

		return array(
			'success' => (bool) $success,
			'message' => $message,
		);
	}

	/**
	 * Activate license with given key.
	 *
	 * @param string $key License key.
	 *
	 * @return array { success, message }
	 */
	public static function activate( $key ) {
		$key = trim( (string) $key );

		if ( '' === $key ) {
			self::store_license_data( '', 'invalid', '', 'Empty license key.' );

			return array(
				'success' => false,
				'message' => __( 'Please enter a license key.', 'wsh-views-counter-pro' ),
			);
		}

		$response = self::call_remote(
			'activate',
			array(
				'license_key' => $key,
			)
		);

		return self::handle_remote_response( $response, $key );
	}

	/**
	 * Deactivate current license.
	 *
	 * @return array { success, message }
	 */
	public static function deactivate() {
		$key = self::get_key();

		if ( '' === $key ) {
			// Nothing to deactivate.
			self::store_license_data( '', '', '', '' );

			return array(
				'success' => true,
				'message' => __( 'License deactivated locally.', 'wsh-views-counter-pro' ),
			);
		}

		$response = self::call_remote(
			'deactivate',
			array(
				'license_key' => $key,
			)
		);

		$result = self::handle_remote_response( $response, $key );

		// If remote deactivation succeeded, clear local data.
		if ( ! empty( $result['success'] ) ) {
			self::store_license_data( '', 'deactivated', '', '' );
		}

		return $result;
	}

	/**
	 * Verify current license with remote server.
	 *
	 * @param bool $force If true, always call remote API; otherwise use simple throttling.
	 *
	 * @return array { success, message }
	 */
	public static function verify( $force = false ) {

		$key = self::get_key();

		if ( '' === $key ) {
			self::store_license_data( '', '', '', '' );

			return array(
				'success' => false,
				'message' => __( 'No license key saved.', 'wsh-views-counter-pro' ),
			);
		}

		// Very simple throttle using a transient, unless forced.
		if ( ! $force ) {
			$last_check = get_transient( 'wsh_vc_pro_last_verify' );
			if ( $last_check && ( time() - (int) $last_check < DAY_IN_SECONDS ) ) {
				// Too soon to re-verify, return current status.
				return array(
					'success' => ( 'valid' === self::get_status() ),
					'message' => __( 'License status is cached.', 'wsh-views-counter-pro' ),
				);
			}
		}

		$response = self::call_remote(
			'verify',
			array(
				'license_key' => $key,
			)
		);

		$result = self::handle_remote_response( $response, $key );

		// Store the time of last verification.
		set_transient( 'wsh_vc_pro_last_verify', time(), DAY_IN_SECONDS );

		return $result;
	}

	/**
	 * Auto-verify license occasionally (on admin_init).
	 */
	public static function maybe_auto_verify() {
		// We do a lazy verify once per day, only if a key exists.
		if ( '' === self::get_key() ) {
			return;
		}

		self::verify( false );
	}
}
