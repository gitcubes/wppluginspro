<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Remote_Image_Finder {

	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	public static function find_from_url( string $url ) : string {
		$url = esc_url_raw( trim( $url ) );

		if ( '' === $url ) {
			return '';
		}

		$cache_key = 'wsh_aine_remote_img_' . md5( $url );
		$cached    = get_transient( $cache_key );

		if ( is_string( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 5,
				'user-agent'  => 'Mozilla/5.0 (compatible; WSH-AI-News-Editor/1.0; +WordPress)',
			)
		);

		if ( is_wp_error( $response ) ) {
			set_transient( $cache_key, '', self::CACHE_TTL );
			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code || ! is_string( $body ) || '' === trim( $body ) ) {
			set_transient( $cache_key, '', self::CACHE_TTL );
			return '';
		}

		$image = self::extract_meta_image( $body, $url );

		set_transient( $cache_key, $image, self::CACHE_TTL );

		return $image;
	}

	protected static function extract_meta_image( string $html, string $base_url ) : string {
		$patterns = array(
			'/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i',
			'/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i',
			'/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i',
			'/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image["\']/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $html, $matches ) && ! empty( $matches[1] ) ) {
				$image = html_entity_decode( trim( (string) $matches[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$image = self::make_absolute_url( $image, $base_url );

				if ( filter_var( $image, FILTER_VALIDATE_URL ) ) {
					return esc_url_raw( $image );
				}
			}
		}

		return '';
	}

	protected static function make_absolute_url( string $maybe_relative, string $base_url ) : string {
		if ( preg_match( '#^https?://#i', $maybe_relative ) ) {
			return $maybe_relative;
		}

		if ( 0 === strpos( $maybe_relative, '//' ) ) {
			$scheme = wp_parse_url( $base_url, PHP_URL_SCHEME );
			return ( $scheme ? $scheme : 'https' ) . ':' . $maybe_relative;
		}

		if ( 0 === strpos( $maybe_relative, '/' ) ) {
			$scheme = wp_parse_url( $base_url, PHP_URL_SCHEME );
			$host   = wp_parse_url( $base_url, PHP_URL_HOST );

			if ( $scheme && $host ) {
				return $scheme . '://' . $host . $maybe_relative;
			}
		}

		return $maybe_relative;
	}
}