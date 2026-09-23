<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Google_URL_Resolver {

	const CACHE_TTL = 12 * HOUR_IN_SECONDS;
	const MAX_HOPS  = 5;

	public static function resolve( string $url ) : string {
		$url = esc_url_raw( trim( $url ) );

		if ( '' === $url ) {
			return '';
		}

		if ( strpos( $url, 'news.google.com' ) === false ) {
			return $url;
		}

		$cache_key = 'wsh_aine_gnews_url_' . md5( $url );
		$cached    = get_transient( $cache_key );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$resolved = self::follow_redirects( $url );

		if ( '' === $resolved ) {
			$resolved = $url;
		}

		set_transient( $cache_key, $resolved, self::CACHE_TTL );

		return $resolved;
	}

	protected static function follow_redirects( string $url ) : string {
		$current = $url;

		for ( $i = 0; $i < self::MAX_HOPS; $i++ ) {
			$response = wp_remote_get(
				$current,
				array(
					'timeout'     => 15,
					'redirection' => 0,
					'user-agent'  => 'Mozilla/5.0 (compatible; WSH-AI-News-Editor/1.0; +WordPress)',
					'headers'     => array(
						'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				error_log( 'GOOGLE RESOLVER ERROR: ' . $response->get_error_message() );
				return $current;
			}

			$code     = wp_remote_retrieve_response_code( $response );
			$location = wp_remote_retrieve_header( $response, 'location' );

			error_log( 'GOOGLE RESOLVER STEP ' . $i . ' URL: ' . $current . ' CODE: ' . $code . ' LOCATION: ' . print_r( $location, true ) );

			if ( $code >= 300 && $code < 400 && ! empty( $location ) ) {
				$next = esc_url_raw( self::make_absolute_url( (string) $location, $current ) );

				if ( '' === $next ) {
					return $current;
				}

				$current = $next;
				continue;
			}

			return $current;
		}

		return $current;
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