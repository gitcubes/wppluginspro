<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utilities for License Manager.
 *
 * - normalize_site(): returns canonical host without www, lowercase.
 *   Examples:
 *     https://www.Example.com/shop -> example.com
 *     example.com -> example.com
 *     http://example.com:8080/path -> example.com
 *     www.example.com -> example.com
 */
class WSH_License_Utils {

	/**
	 * Normalize a site URL to a canonical host (no scheme, no path, no port, no www).
	 *
	 * @param mixed $site_url Raw site URL from user/client.
	 * @return string Normalized host (example.com) or empty string if invalid.
	 */
	public static function normalize_site( $site_url ) {

		// Force string, trim whitespace.
		$site_url = is_string( $site_url ) ? trim( $site_url ) : '';
		if ( '' === $site_url ) {
			return '';
		}

		// Decode entities just in case (e.g. &amp; etc.)
		if ( function_exists( 'wp_specialchars_decode' ) ) {
			$site_url = wp_specialchars_decode( $site_url, ENT_QUOTES );
		}

		// Remove surrounding quotes/spaces.
		$site_url = trim( $site_url, " \t\n\r\0\x0B\"'" );

		// Remove common prefixes that users paste.
		// (We will still handle scheme properly later.)
		$site_url = preg_replace( '#^\s*(?:url:)?\s*#i', '', $site_url );

		// If there's no scheme, prepend one so parse_url can work reliably.
		// (parse_url behaves differently across PHP versions when scheme is missing.)
		$has_scheme = (bool) preg_match( '#^[a-z][a-z0-9+\-.]*://#i', $site_url );
		$to_parse   = $has_scheme ? $site_url : 'https://' . ltrim( $site_url, '/' );

		$parts = wp_parse_url( $to_parse );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		$host = isset( $parts['host'] ) ? (string) $parts['host'] : '';

		// Some inputs like "example.com:8080" without scheme can end up in "path".
		// Try fallback: if host is empty, treat path as host candidate.
		if ( '' === $host && ! empty( $parts['path'] ) ) {
			$host_candidate = (string) $parts['path'];
			// Remove path after first slash if any.
			$host_candidate = preg_split( '#/#', $host_candidate, 2 )[0];
			$host = $host_candidate;
		}

		$host = strtolower( trim( $host ) );
		if ( '' === $host ) {
			return '';
		}

		// Strip brackets for IPv6 like [::1]
		$host = trim( $host, '[]' );

		// Strip trailing dot.
		$host = rtrim( $host, '.' );

		// Remove leading www.
		$host = preg_replace( '#^www\.#i', '', $host );

		// Remove port if present in host string (rare but possible).
		// e.g. example.com:8080
		$host = preg_replace( '#:\d+$#', '', $host );

		// Basic validation:
		// Allow domain names and IPs. Keep it permissive but safe.
		$is_ip = (bool) filter_var( $host, FILTER_VALIDATE_IP );
		if ( ! $is_ip ) {
			// Domain validation (simple + permissive).
			// Must contain at least one dot and allowed chars.
			if ( ! preg_match( '#^[a-z0-9][a-z0-9\-\.]*[a-z0-9]$#i', $host ) ) {
				return '';
			}
			// Optional: require at least one dot (comment out if you want to allow "localhost")
			// if ( false === strpos( $host, '.' ) ) { return ''; }
		}

		return $host;
	}

	/**
	 * Convenience: normalize current server site.
	 *
	 * @return string
	 */
	public static function normalize_current_site() {
		return self::normalize_site( home_url() );
	}

	/**
	 * Sites already stored on a license.
	 *
	 * Older licenses only have wsh_site_url. That host stays the first slot
	 * and is not rewritten until the customer activates or deactivates.
	 *
	 * @param int $license_id License post ID.
	 * @return string[]
	 */
	public static function activated_sites( $license_id ) {
		$sites  = array();
		$stored = get_post_meta( $license_id, 'wsh_sites', true );

		if ( is_array( $stored ) ) {
			foreach ( $stored as $site ) {
				$site = self::normalize_site( $site );
				if ( '' !== $site && ! in_array( $site, $sites, true ) ) {
					$sites[] = $site;
				}
			}
		}

		$legacy = self::normalize_site( (string) get_post_meta( $license_id, 'wsh_site_url', true ) );
		if ( '' !== $legacy && ! in_array( $legacy, $sites, true ) ) {
			array_unshift( $sites, $legacy );
		}

		return $sites;
	}

	/**
	 * Persist the activation list and keep wsh_site_url as the first site.
	 *
	 * @param int      $license_id License post ID.
	 * @param string[] $sites      Normalized hosts.
	 */
	public static function save_activated_sites( $license_id, array $sites ) {
		$clean = array();

		foreach ( $sites as $site ) {
			$site = self::normalize_site( $site );
			if ( '' === $site || self::is_staging_site( $site ) || in_array( $site, $clean, true ) ) {
				continue;
			}
			$clean[] = $site;
		}

		update_post_meta( $license_id, 'wsh_sites', $clean );

		if ( $clean ) {
			update_post_meta( $license_id, 'wsh_site_url', $clean[0] );
		} else {
			delete_post_meta( $license_id, 'wsh_site_url' );
		}
	}

	/**
	 * Slot count for a license. A missing value stays 1 so older keys
	 * do not become unlimited. Stored 0 means unlimited.
	 *
	 * @param int $license_id License post ID.
	 * @return int
	 */
	public static function max_sites( $license_id ) {
		$raw = get_post_meta( $license_id, 'wsh_max_sites', true );
		if ( '' === $raw || false === $raw || null === $raw ) {
			return 1;
		}

		return max( 0, (int) $raw );
	}

	/**
	 * Staging, local and platform preview hosts do not use a license slot.
	 *
	 * @param string $site Normalized host.
	 * @return bool
	 */
	public static function is_staging_site( $site ) {
		$site = self::normalize_site( $site );
		if ( '' === $site ) {
			return false;
		}

		if ( in_array( $site, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
			return true;
		}

		foreach ( array( '.local', '.test', '.localhost', '.invalid' ) as $suffix ) {
			$len = strlen( $suffix );
			if ( strlen( $site ) > $len && substr( $site, -$len ) === $suffix ) {
				return true;
			}
		}

		$labels = array( 'staging', 'stage', 'dev', 'development', 'test', 'testing', 'local' );
		foreach ( explode( '.', $site ) as $label ) {
			if ( in_array( $label, $labels, true ) ) {
				return true;
			}
		}

		$platforms = array(
			'.mystagingwebsite.com',
			'.kinsta.cloud',
			'.flywheelstaging.com',
			'.wpcomstaging.com',
			'.cloudwaysapps.com',
			'.wpstage.net',
		);
		foreach ( $platforms as $suffix ) {
			$len = strlen( $suffix );
			if ( strlen( $site ) > $len && substr( $site, -$len ) === $suffix ) {
				return true;
			}
		}

		return (bool) apply_filters( 'wsh_license_is_staging_site', false, $site );
	}
}
