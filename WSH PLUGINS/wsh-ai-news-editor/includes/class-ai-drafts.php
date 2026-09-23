<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_AI_Drafts {

	const TRANSIENT_PREFIX = 'wsh_aine_ai_seed_';
	const OPTION_PREFIX    = 'wsh_aine_ai_seed_option_';
	const TTL              = 30 * MINUTE_IN_SECONDS;

	public static function store_seed( array $payload ) : string {
		$payload = WSH_AINE_AI_Payload::normalize( $payload );
		$token   = self::generate_token();

		$data = array(
			'user_id'    => get_current_user_id(),
			'created_at' => time(),
			'expires_at' => time() + self::TTL,
			'payload'    => $payload,
		);

		$key = self::TRANSIENT_PREFIX . $token;

		$saved = set_transient( $key, $data, self::TTL );

		// Fallback ako transient ne bude sačuvan kako treba.
		if ( ! $saved || ! is_array( get_transient( $key ) ) ) {
			update_option( self::OPTION_PREFIX . $token, $data, false );
		}

		return $token;
	}

	public static function get_seed( string $token ) : array {
		$token = self::sanitize_token( $token );

		if ( '' === $token ) {
			return array();
		}

		$data = get_transient( self::TRANSIENT_PREFIX . $token );

		// Fallback na option ako transient nije pronađen.
		if ( ! is_array( $data ) ) {
			$data = get_option( self::OPTION_PREFIX . $token, false );
		}

		if ( ! is_array( $data ) ) {
			return array();
		}

		$expires_at = isset( $data['expires_at'] ) ? (int) $data['expires_at'] : 0;

		if ( $expires_at && time() > $expires_at ) {
			self::clear_seed( $token );
			return array();
		}

		$user_id = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;

		if ( $user_id !== get_current_user_id() ) {
			return array();
		}

		$payload = isset( $data['payload'] ) && is_array( $data['payload'] ) ? $data['payload'] : array();

		return WSH_AINE_AI_Payload::normalize( $payload );
	}

	public static function clear_seed( string $token ) : void {
		$token = self::sanitize_token( $token );

		if ( '' === $token ) {
			return;
		}

		delete_transient( self::TRANSIENT_PREFIX . $token );
		delete_option( self::OPTION_PREFIX . $token );
	}

	protected static function generate_token() : string {
		try {
			return wp_generate_password( 32, false, false ) . bin2hex( random_bytes( 8 ) );
		} catch ( Exception $e ) {
			return wp_generate_password( 48, false, false );
		}
	}

	protected static function sanitize_token( string $token ) : string {
		$token = preg_replace( '/[^a-zA-Z0-9]/', '', $token );
		return is_string( $token ) ? $token : '';
	}
}