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

// The site is chosen later, when the customer activates the plugin.
// Checkout only collects billing details.




