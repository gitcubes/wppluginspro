<?php
/**
 * Google Analytics OAuth handling.
 *
 * Handles the OAuth code exchange callback and logout on the analytics settings page,
 * and provides AJAX endpoints for the React UI.
 *
 * @package SEOPress PRO
 */

defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

/**
 * Create a configured Google Client instance.
 *
 * @return \Google\Client|null
 */
function seopress_ga_oauth_get_client() {
	$client_id     = seopress_get_service( 'GoogleAnalyticsOption' )->getAuthClientId();
	$client_secret = seopress_get_service( 'GoogleAnalyticsOption' )->getAuthSecretId();

	if ( empty( $client_id ) || empty( $client_secret ) ) {
		return null;
	}

	require_once SEOPRESS_PRO_PLUGIN_DIR_PATH . '/vendor/autoload.php';

	$client = new \Google\Client();
	$client->setApplicationName( 'SEOPress' );
	$client->setClientId( $client_id );
	$client->setClientSecret( $client_secret );
	$client->setRedirectUri( admin_url( 'admin.php?page=seopress-google-analytics' ) );
	$client->setScopes( array( 'https://www.googleapis.com/auth/analytics.readonly' ) );
	$client->setApprovalPrompt( 'force' );
	$client->setAccessType( 'offline' );
	$client->setIncludeGrantedScopes( true );
	$client->setPrompt( 'consent' );

	return $client;
}

/**
 * Handle OAuth code exchange and logout on the analytics settings page.
 *
 * Runs on admin_init so it catches the Google redirect before React renders.
 *
 * @return void
 */
function seopress_ga_oauth_handle_callback() {
	// Only run on the analytics settings page.
	if ( ! isset( $_GET['page'] ) || 'seopress-google-analytics' !== $_GET['page'] ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Handle logout.
	if ( isset( $_GET['logout'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'seopress-ga-logout' ) ) {
		$options                  = get_option( 'seopress_google_analytics_option_name1', array() );
		$options['refresh_token'] = null;
		$options['access_token']  = null;
		$options['code']          = '';
		$options['debug']         = '';
		update_option( 'seopress_google_analytics_option_name1', $options, 'yes' );
		update_option( 'seopress_google_analytics_lock_option_name', '', 'yes' );

		wp_safe_redirect( admin_url( 'admin.php?page=seopress-google-analytics' ) );
		exit;
	}

	// Handle code exchange from Google OAuth redirect.
	if ( isset( $_GET['code'] ) ) {
		$client = seopress_ga_oauth_get_client();

		if ( null === $client ) {
			return;
		}

		if ( null !== seopress_pro_get_service( 'GoogleAnalyticsOptionPro' )->getAccessToken() ) {
			return; // Already authenticated.
		}

		$client->authenticate( sanitize_text_field( wp_unslash( $_GET['code'] ) ) );
		$token = $client->getAccessToken();

		if ( is_array( $token ) && isset( $token['access_token'] ) ) {
			$options                  = get_option( 'seopress_google_analytics_option_name1', array() );
			$options['access_token']  = $token['access_token'];
			$options['refresh_token'] = isset( $token['refresh_token'] ) ? $token['refresh_token'] : null;
			$options['debug']         = $token;
			$options['code']          = sanitize_text_field( wp_unslash( $_GET['code'] ) );
			update_option( 'seopress_google_analytics_option_name1', $options, 'yes' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=seopress-google-analytics' ) );
		exit;
	}
}
add_action( 'admin_init', 'seopress_ga_oauth_handle_callback', 5 );

/**
 * AJAX: Get Google Analytics OAuth status and auth URL.
 *
 * @return void
 */
function seopress_ga_oauth_get_status() {
	check_ajax_referer( 'seopress_ga_oauth_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied.' ) );
	}

	$access_token = seopress_pro_get_service( 'GoogleAnalyticsOptionPro' )->getAccessToken();
	$is_connected = null !== $access_token;

	$auth_url   = '';
	$logout_url = '';

	if ( ! $is_connected ) {
		$client = seopress_ga_oauth_get_client();
		if ( null !== $client ) {
			$auth_url = $client->createAuthUrl();
		}
	} else {
		$logout_url = wp_nonce_url(
			admin_url( 'admin.php?page=seopress-google-analytics&logout=1' ),
			'seopress-ga-logout'
		);
	}

	wp_send_json_success( array(
		'is_connected' => $is_connected,
		'auth_url'     => $auth_url,
		'logout_url'   => $logout_url,
	) );
}
add_action( 'wp_ajax_seopress_ga_oauth_get_status', 'seopress_ga_oauth_get_status' );

/**
 * AJAX: Logout from Google Analytics.
 *
 * @return void
 */
function seopress_ga_oauth_logout() {
	check_ajax_referer( 'seopress_ga_oauth_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied.' ) );
	}

	$options                  = get_option( 'seopress_google_analytics_option_name1', array() );
	$options['refresh_token'] = null;
	$options['access_token']  = null;
	$options['code']          = '';
	$options['debug']         = '';
	update_option( 'seopress_google_analytics_option_name1', $options, 'yes' );
	update_option( 'seopress_google_analytics_lock_option_name', '', 'yes' );

	wp_send_json_success();
}
add_action( 'wp_ajax_seopress_ga_oauth_logout', 'seopress_ga_oauth_logout' );
