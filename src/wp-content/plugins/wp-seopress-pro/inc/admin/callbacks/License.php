<?php //phpcs:ignore
/**
 * SEOPress PRO License callbacks.
 *
 * @package SEOPress PRO
 * @subpackage Callbacks
 */

defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

/**
 * Clear EDD updater version info caches.
 *
 * Removes all edd_sl_* and edd_api_request_* options so that stale
 * package URLs (which embed the old license key) are not reused after
 * a license change.
 *
 * @return void
 */
function seopress_clear_edd_updater_cache() {
	global $wpdb;

	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'edd\_sl\_%' OR option_name LIKE 'edd\_api\_request\_%' OR option_name LIKE 'edd\_sl\_failed\_http\_%'" );

	// Also clear the site transient so WordPress re-checks for updates.
	delete_site_transient( 'update_plugins' );
}

/**
 * Automatic license key activation callback.
 *
 * @return void
 */
function seopress_automatic_license_activation() {
	if ( ! current_user_can( seopress_capability( 'manage_options', 'license' ) ) ) {
		return;
	}

	// Get license key from SEOPRESS_LICENSE_KEY define callback.
	$license = defined( 'SEOPRESS_LICENSE_KEY' ) && ! empty( SEOPRESS_LICENSE_KEY ) && is_string( SEOPRESS_LICENSE_KEY ) ? SEOPRESS_LICENSE_KEY : null;
	if ( ! isset( $license ) ) {
		return;
	}

	// Handle domain name changes (local / staging / live site) callback.
	$prev_home_url = get_option( 'seopress_pro_license_home_url' );
	if ( empty( $prev_home_url ) ) {
		update_option( 'seopress_pro_license_home_url', get_option( 'home' ) );
	}
	if ( isset( $prev_home_url ) && get_option( 'home' ) !== $prev_home_url ) {
		delete_option( 'seopress_pro_license_status' );
		delete_option( 'seopress_pro_license_automatic_attempt' );
		update_option( 'seopress_pro_license_home_url', get_option( 'home' ) );
	} else {
		return false;
	}

	// Already activated?
	if ( 'valid' === get_option( 'seopress_pro_license_status' ) ) {
		return;
	}

	// Already executed?
	if ( get_option( 'seopress_pro_license_automatic_attempt' ) === '1' ) {
		return;
	}
	update_option( 'seopress_pro_license_automatic_attempt', 1 );

	// data to send in our API request callback.
	$api_params = array(
		'edd_action'  => 'activate_license',
		'license'     => $license,
		'item_id'     => ITEM_ID_SEOPRESS, // the ID of our product in EDD.
		'url'         => get_option( 'home' ),
		'environment' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
	);

	// Call the custom API callback.
	$response = wp_remote_post(
		STORE_URL_SEOPRESS,
		array(
			'user-agent' => 'WordPress/' . get_bloginfo( 'version' ),
			'timeout'    => 15,
			'sslverify'  => false,
			'body'       => $api_params,
		)
	);

	// make sure the response came back okay.
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
		} else {
			$message = __( 'An error occurred, please try again. Response code: ', 'wp-seopress-pro' ) . wp_remote_retrieve_response_code( $response );
		}
	} else {
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );
		if ( false === $license_data->success ) {
			switch ( $license_data->error ) {
				case 'expired':
					$message = /* translators: %s: localized expiration date */ sprintf(
						__( 'Your license key expired on %s.', 'wp-seopress-pro' ),
						date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
					);
					break;

				case 'disabled':
				case 'revoked':
					$message = __( 'Your license key has been disabled.', 'wp-seopress-pro' );
					break;

				case 'missing':
					$message = __( 'Invalid license.', 'wp-seopress-pro' );
					break;

				case 'invalid':
				case 'site_inactive':
					$message = __( 'Your license is not active for this URL.', 'wp-seopress-pro' );
					break;

				case 'item_name_mismatch':
					/* translators: %s: SEOPress PRO */
					$message = sprintf( __( 'This appears to be an invalid license key for %s.', 'wp-seopress-pro' ), ITEM_NAME_SEOPRESS );
					break;

				case 'no_activations_left':
					$message = __( 'Your license key has reached its activation limit.', 'wp-seopress-pro' );
					break;

				default:
					$message = __( 'An error occurred, please try again.', 'wp-seopress-pro' );
					break;
			}
		}
	}

	// Check if anything passed on a message constituting a failure.
	if ( ! empty( $message ) ) {
		update_option( 'seopress_pro_license_key_error', $message );
		return;
	}

	// $license_data->license will be either "valid" or "invalid".
	if ( ! defined( 'SEOPRESS_LICENSE_KEY' ) ) {
		if ( 'valid' === $license_data->license ) {
			update_option( 'seopress_pro_license_key', $license );
		}
	}

	update_option( 'seopress_pro_license_status', $license_data->license );

	// Store the expiry timestamp for condition evaluation.
	if ( isset( $license_data->expires ) && 'lifetime' !== $license_data->expires ) {
		$expiry_timestamp = strtotime( $license_data->expires );
		update_option( 'seopress_pro_license_expiry', $expiry_timestamp, false );
	} elseif ( isset( $license_data->expires ) && 'lifetime' === $license_data->expires ) {
		// Lifetime license - set expiry far in the future.
		update_option( 'seopress_pro_license_expiry', strtotime( '+100 years' ), false );
	}

	// Invalidate EDD updater caches so the new license key is used for updates.
	seopress_clear_edd_updater_cache();

	return;
}
add_action( 'admin_init', 'seopress_automatic_license_activation' );

/**
 * Activate license key callback.
 *
 * @return void
 */
function seopress_activate_license() {
	// Listen for our activate button to be clicked.
	if ( isset( $_POST['seopress_license_activate'] ) ) {

		// Run a quick security check.
		if ( ! check_admin_referer( 'seopress_nonce', 'seopress_nonce' ) ) {
			return;
		} // Get out if we didn't click the Activate button.

		// Retrieve the license from the database.
		$license = defined( 'SEOPRESS_LICENSE_KEY' ) && ! empty( SEOPRESS_LICENSE_KEY ) && is_string( SEOPRESS_LICENSE_KEY ) ? SEOPRESS_LICENSE_KEY : trim( (string) get_option( 'seopress_pro_license_key' ) );

		// Data to send in our API request.
		$api_params = array(
			'edd_action'  => 'activate_license',
			'license'     => $license,
			'item_id'     => ITEM_ID_SEOPRESS, // The ID of our product in EDD.
			'url'         => get_option( 'home' ),
			'environment' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
		);

		// Call the custom API.
		$response = wp_remote_post(
			STORE_URL_SEOPRESS,
			array(
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ),
				'timeout'    => 15,
				'sslverify'  => false,
				'body'       => $api_params,
			)
		);

		// Make sure the response came back okay.
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = __( 'An error occurred, please try again. Response code: ', 'wp-seopress-pro' ) . wp_remote_retrieve_response_code( $response );
			}
		} else {
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );
			if ( false === $license_data->success ) {
				switch ( $license_data->error ) {
					case 'expired':
						$message = sprintf(
						/* translators: %s: localized expiration date */
							__( 'Your license key expired on %s.', 'wp-seopress-pro' ),
							date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
						);
						break;

					case 'disabled':
					case 'revoked':
						$message = __( 'Your license key has been disabled.', 'wp-seopress-pro' );
						break;

					case 'missing':
						$message = __( 'Invalid license.', 'wp-seopress-pro' );
						break;

					case 'invalid':
					case 'site_inactive':
						$message = __( 'Your license is not active for this URL.', 'wp-seopress-pro' );
						break;

					case 'item_name_mismatch':
						/* translators: %s: SEOPress PRO */
						$message = sprintf( __( 'This appears to be an invalid license key for %s.', 'wp-seopress-pro' ), ITEM_NAME_SEOPRESS );
						break;

					case 'no_activations_left':
						$message = __( 'Your license key has reached its activation limit.', 'wp-seopress-pro' );
						break;

					default:
						$message = __( 'An error occurred, please try again.', 'wp-seopress-pro' );
						break;
				}
			}
		}

		// Check if anything passed on a message constituting a failure.
		if ( ! empty( $message ) ) {
			$base_url = admin_url( 'admin.php?page=' . SEOPRESS_LICENSE_PAGE );
			$redirect = add_query_arg(
				array(
					'sl_activation' => 'false',
					'message'       => urlencode( $message ),
				),
				$base_url
			);

			wp_redirect( $redirect );
			exit();
		}

		// $license_data->license will be either "valid" or "invalid".
		if ( ! defined( 'SEOPRESS_LICENSE_KEY' ) ) {
			if ( 'valid' === $license_data->license ) {
				update_option( 'seopress_pro_license_key', $license );
			}
		}

		update_option( 'seopress_pro_license_status', $license_data->license );

		// Store the expiry timestamp for condition evaluation.
		if ( isset( $license_data->expires ) && 'lifetime' !== $license_data->expires ) {
			$expiry_timestamp = strtotime( $license_data->expires );
			update_option( 'seopress_pro_license_expiry', $expiry_timestamp, false );
		} elseif ( isset( $license_data->expires ) && 'lifetime' === $license_data->expires ) {
			// Lifetime license - set expiry far in the future.
			update_option( 'seopress_pro_license_expiry', strtotime( '+100 years' ), false );
		}

		// Invalidate EDD updater caches so the new license key is used for updates.
		seopress_clear_edd_updater_cache();

		wp_safe_redirect( admin_url( 'admin.php?page=' . SEOPRESS_LICENSE_PAGE ) );
		exit();
	}
}
add_action( 'admin_init', 'seopress_activate_license' );

/**
 * Deactivate license key callback.
 *
 * @return void
 */
function seopress_deactivate_license() {
	// Listen for our activate button to be clicked.
	if ( isset( $_POST['seopress_license_deactivate'] ) ) {

		// Run a quick security check.
		if ( ! check_admin_referer( 'seopress_nonce', 'seopress_nonce' ) ) {
			return;
		} // Get out if we didn't click the Activate button.

		// Retrieve the license from the database.
		$license = defined( 'SEOPRESS_LICENSE_KEY' ) && ! empty( SEOPRESS_LICENSE_KEY ) && is_string( SEOPRESS_LICENSE_KEY ) ? SEOPRESS_LICENSE_KEY : trim( (string) get_option( 'seopress_pro_license_key' ) );

		// Data to send in our API request.
		$api_params = array(
			'edd_action'  => 'deactivate_license',
			'license'     => $license,
			'item_id'     => ITEM_ID_SEOPRESS, // The ID of our product in EDD.
			'url'         => get_option( 'home' ),
			'environment' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production',
		);

		// Call the custom API.
		$response = wp_remote_post(
			STORE_URL_SEOPRESS,
			array(
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ),
				'timeout'    => 15,
				'sslverify'  => false,
				'body'       => $api_params,
			)
		);

		// Make sure the response came back okay.
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = __( 'An error occurred, please try again.', 'wp-seopress-pro' );
			}

			$base_url = admin_url( 'admin.php?page=' . SEOPRESS_LICENSE_PAGE );
			$redirect = add_query_arg(
				array(
					'sl_activation' => 'false',
					'message'       => urlencode( $message ),
				),
				$base_url
			);

			wp_safe_redirect( $redirect );
			exit();
		}

		// Decode the license data.
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// $license_data->license will be either "deactivated" or "failed".
		if ( 'deactivated' === $license_data->license ) {
			delete_option( 'seopress_pro_license_status' );
			delete_option( 'seopress_pro_license_expiry' );

			// Invalidate EDD updater caches to prevent stale package URLs.
			seopress_clear_edd_updater_cache();
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . SEOPRESS_LICENSE_PAGE ) );
		exit();
	}
}
add_action( 'admin_init', 'seopress_deactivate_license' );
