<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Social_Url_Helper {

	public static function detect_platform( string $url ) : string {
		$url = trim( $url );

		if ( '' === $url ) {
			return '';
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		$host = is_string( $host ) ? strtolower( $host ) : '';

		if ( false !== strpos( $host, 'instagram.com' ) ) {
			return 'instagram';
		}

		if ( false !== strpos( $host, 'x.com' ) || false !== strpos( $host, 'twitter.com' ) ) {
			return 'twitter';
		}

		if ( false !== strpos( $host, 'linkedin.com' ) ) {
			return 'linkedin';
		}

		if ( false !== strpos( $host, 'youtube.com' ) || false !== strpos( $host, 'youtu.be' ) ) {
			return 'youtube';
		}

		if ( false !== strpos( $host, 'tiktok.com' ) ) {
			return 'tiktok';
		}

		return '';
	}

	public static function is_supported( string $url ) : bool {
		return '' !== self::detect_platform( $url );
	}
}