<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Handles PRO license key storage and communication with remote license API.
 */
class WSH_WCBTE_License {

	const OPTION_KEY        = 'wsh_wcbte_license_key';
	const OPTION_STATUS     = 'wsh_wcbte_license_status';
	const OPTION_EXPIRES    = 'wsh_wcbte_license_expires';
	const OPTION_LAST_ERROR = 'wsh_wcbte_license_last_error';

	const TRANSIENT_LAST_VERIFY = 'wsh_wcbte_last_verify';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_auto_verify' ) );
	}

	public static function get_key() : string {
		$key = get_option( self::OPTION_KEY, '' );
		return is_string( $key ) ? trim( $key ) : '';
	}

	public static function get_status() : string {
		$status = get_option( self::OPTION_STATUS, '' );
		return is_string( $status ) ? trim( $status ) : '';
	}

	public static function get_expires() : string {
		$expires = get_option( self::OPTION_EXPIRES, '' );
		return is_string( $expires ) ? trim( $expires ) : '';
	}

	public static function get_last_error() : string {
		$err = get_option( self::OPTION_LAST_ERROR, '' );
		return is_string( $err ) ? trim( $err ) : '';
	}

	public static function is_active() : bool {
		$key    = self::get_key();
		$status = self::get_status();
		return ( '' !== $key && 'valid' === $status );
	}

	protected static function store_license_data( string $key, string $status, string $expiry = '', string $error = '' ) : void {
		update_option( self::OPTION_KEY, $key );
		update_option( self::OPTION_STATUS, $status );
		update_option( self::OPTION_EXPIRES, $expiry );
		update_option( self::OPTION_LAST_ERROR, $error );
	}

	protected static function get_site_url_for_api() : string {
		return untrailingslashit( home_url() );
	}

	protected static function build_auth_params( string $license_key, string $site_url, string $plugin_slug ) : array {

		$secret = defined('WSH_WCBTE_LICENSE_API_SECRET') ? (string) WSH_WCBTE_LICENSE_API_SECRET : '^#UZ~FEx4.@,he6J0;x,r#K//CIq^:2;^rrnSm!>H#bd!.(6j^$ET+(7whZ^7^DI';

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

	protected static function call_remote( string $action, array $extra = array() ) {

		if ( ! defined( 'WSH_LICENSE_API_BASE' ) ) {
			return new WP_Error( 'no_api_base', 'License API base URL is not defined.' );
		}

		$endpoint   = trailingslashit( WSH_LICENSE_API_BASE ) . $action;
		$site_url   = self::get_site_url_for_api();
		$plugin_slug = defined('WSH_WCBTE_PLUGIN_SLUG') ? WSH_WCBTE_PLUGIN_SLUG : 'wsh-woo-bulk-taxonomy-editor';

		$body = array_merge(
			array(
				'action'         => $action,
				'site_url'       => $site_url,
				'plugin_slug'    => $plugin_slug,
				'plugin_version' => defined( 'WSH_WCBTE_VERSION' ) ? WSH_WCBTE_VERSION : '1.0.0',
			),
			$extra
		);

		if ( ! empty( $body['license_key'] ) ) {
			$auth = self::build_auth_params( (string) $body['license_key'], $site_url, $plugin_slug );
			if ( ! empty( $auth ) ) {
				$body = array_merge( $body, $auth );
			}
		}

		return wp_remote_post( $endpoint, array(
			'timeout' => 15,
			'body'    => $body,
		) );
	}

	protected static function handle_remote_response( $response, string $key ) : array {

		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			self::store_license_data( $key, 'invalid', '', $msg );
			return array( 'success' => false, 'message' => sprintf( 'License server error: %s', $msg ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code || empty( $body ) ) {
			$msg = sprintf( 'Unexpected response from license server (HTTP %d).', $code );
			self::store_license_data( $key, 'invalid', '', $msg );
			return array( 'success' => false, 'message' => $msg );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			$msg = 'Invalid JSON response from license server.';
			self::store_license_data( $key, 'invalid', '', $msg );
			return array( 'success' => false, 'message' => $msg );
		}

		$success = ! empty( $data['success'] );
		$status  = isset( $data['status'] ) ? (string) $data['status'] : '';
		$expires = isset( $data['expires'] ) ? (string) $data['expires'] : '';
		$error   = isset( $data['error'] ) ? (string) $data['error'] : '';
		$message = isset( $data['message'] ) ? (string) $data['message'] : '';

		if ( '' === $message ) {
			$message = $success ? 'License request successful.' : 'License request failed.';
		}

		self::store_license_data( $key, $status, $expires, $error );

		return array( 'success' => (bool) $success, 'message' => $message );
	}

	public static function activate( string $key ) : array {
		$key = trim( $key );

		if ( '' === $key ) {
			self::store_license_data( '', 'invalid', '', 'Empty license key.' );
			return array( 'success' => false, 'message' => __( 'Please enter a license key.', 'wsh-wcbte' ) );
		}

		$response = self::call_remote( 'activate', array( 'license_key' => $key ) );
		$result   = self::handle_remote_response( $response, $key );

		delete_transient( self::TRANSIENT_LAST_VERIFY );

		return $result;
	}

	public static function deactivate() : array {
		$key = self::get_key();

		if ( '' === $key ) {
			self::store_license_data( '', '', '', '' );
			return array( 'success' => true, 'message' => __( 'License cleared locally.', 'wsh-wcbte' ) );
		}

		$response = self::call_remote( 'deactivate', array( 'license_key' => $key ) );
		$result   = self::handle_remote_response( $response, $key );

		if ( ! empty( $result['success'] ) ) {
			self::store_license_data( '', 'deactivated', '', '' );
		}

		delete_transient( self::TRANSIENT_LAST_VERIFY );

		return $result;
	}

	public static function verify( bool $force = false ) : array {
		$key = self::get_key();

		if ( '' === $key ) {
			self::store_license_data( '', '', '', '' );
			return array( 'success' => false, 'message' => __( 'No license key saved.', 'wsh-wcbte' ) );
		}

		if ( ! $force ) {
			$last = get_transient( self::TRANSIENT_LAST_VERIFY );
			if ( $last && ( time() - (int) $last < DAY_IN_SECONDS ) ) {
				return array(
					'success' => ( 'valid' === self::get_status() ),
					'message' => __( 'License status is cached.', 'wsh-wcbte' ),
				);
			}
		}

		$response = self::call_remote( 'verify', array( 'license_key' => $key ) );
		$result   = self::handle_remote_response( $response, $key );

		set_transient( self::TRANSIENT_LAST_VERIFY, time(), DAY_IN_SECONDS );

		return $result;
	}

	public static function maybe_auto_verify() : void {
		if ( '' === self::get_key() ) return;
		self::verify( false );
	}
}
