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

class WSH_License_Manager {

	public static function init() {
		WSH_License_CPT::init();
		WSH_License_API::init();
		WSH_License_WooCommerce::init();
	}
}

add_action( 'plugins_loaded', array( 'WSH_License_Manager', 'init' ) );

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
	if ( empty( $_POST['billing_wsh_site_url'] ) ) {
		wc_add_notice( __( 'Please enter the site URL for your license.', 'wsh-license-manager' ), 'error' );
	}
} );


