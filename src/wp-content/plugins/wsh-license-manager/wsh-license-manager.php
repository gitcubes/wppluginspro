<?php
/**
 * Plugin Name: WSH License Manager
 * Description: Central license manager for WSH PRO plugins (WooCommerce Subscriptions based).
 * Author:      Web Solutions Hub LLC
 * Version:     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WSH_LICENSE_MANAGER_VERSION' ) ) {
	define( 'WSH_LICENSE_MANAGER_VERSION', '1.0.0' );
}

if ( ! defined( 'WSH_LICENSE_MANAGER_PATH' ) ) {
	define( 'WSH_LICENSE_MANAGER_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WSH_LICENSE_MANAGER_URL' ) ) {
	define( 'WSH_LICENSE_MANAGER_URL', plugin_dir_url( __FILE__ ) );
}

// Optional: base URL for API namespace.
if ( ! defined( 'WSH_LICENSE_API_NAMESPACE' ) ) {
	// This will create endpoints like /wp-json/wsh-vc-license/v1/activate
	//define( 'WSH_LICENSE_API_NAMESPACE', 'wsh-vc-license/v1' ); BKP
	define( 'WSH_LICENSE_API_NAMESPACE', 'wsh-license/v1' );
}

// Load core classes.
require_once WSH_LICENSE_MANAGER_PATH . 'includes/class-wsh-license-utils.php';
require_once WSH_LICENSE_MANAGER_PATH . 'includes/class-wsh-license-cpt.php';
require_once WSH_LICENSE_MANAGER_PATH . 'includes/class-wsh-license-api.php';
require_once WSH_LICENSE_MANAGER_PATH . 'includes/class-wsh-license-woocommerce.php';
require_once WSH_LICENSE_MANAGER_PATH . 'includes/class-wsh-plugin-storage.php';
require_once WSH_LICENSE_MANAGER_PATH . 'includes/class-wsh-plugin-catalog.php';

class WSH_License_Manager {

	public static function init() {
		WSH_License_CPT::init();
		WSH_License_API::init();
		WSH_License_WooCommerce::init();
		WSH_Plugin_Storage::init();
		WSH_Plugin_Catalog::init();
	}
}

add_action( 'plugins_loaded', array( 'WSH_License_Manager', 'init' ) );

// Ensure API secret exists (one-time). You can later move this to a Settings page.
add_action('admin_init', function () {
	if ( ! current_user_can('manage_options') ) {
		return;
	}
	$opt = get_option('wsh_license_api_secret', '');
	if (empty($opt)) {
		// Generates random secret, stores once.
		update_option('wsh_license_api_secret', wp_generate_password(64, true, true));
	}
});

// Add "Site URL" field to the checkout billing section.
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {

	$fields['billing']['billing_wsh_site_url'] = array(
		'type'        => 'text',
		'label'       => __( 'Site URL for license', 'wsh-license-manager' ),
		'placeholder' => __( 'https://example.com', 'wsh-license-manager' ),
		'required'    => true,
		'class'       => array( 'form-row-wide' ),
		'priority'    => 120,
	);

	return $fields;
} );

// Validate "Site URL" field.
add_action( 'woocommerce_checkout_process', function () {
	$raw = isset($_POST['billing_wsh_site_url']) ? wp_unslash($_POST['billing_wsh_site_url']) : '';
	$normalized = WSH_License_Utils::normalize_site($raw);

	if ( $normalized === '' ) {
		wc_add_notice( __( 'Please enter a valid site URL for your license.', 'wsh-license-manager' ), 'error' );
	}
} );

/*
add_action( 'woocommerce_checkout_process', function () {
	if ( empty( $_POST['billing_wsh_site_url'] ) ) {
		wc_add_notice( __( 'Please enter the site URL for your license.', 'wsh-license-manager' ), 'error' );
	}
} );
 */

add_action( 'woocommerce_checkout_update_order_meta', function ( $order_id ) {

	if ( ! isset($_POST['billing_wsh_site_url']) ) {
		return;
	}

	$raw = wp_unslash($_POST['billing_wsh_site_url']);
	$normalized = WSH_License_Utils::normalize_site($raw);

	if ( $normalized !== '' ) {
		update_post_meta( $order_id, '_billing_wsh_site_url', $normalized );
	}
} );




