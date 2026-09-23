<?php
/**
 * Plugin Name: WSH WooCommerce Bulk Taxonomy Editor
 * Plugin URI:  https://wppluginspro.io/
 * Description: Bulk edit WooCommerce product taxonomies from Products list. FREE: product categories only (product_cat). PRO unlocks tags, brands, attributes, custom taxonomies.
 * Version:     1.0.0
 * Author:      Web Solutions Hub LLC
 * Author URI:  https://wppluginspro.io/
 * License:     GPLv2 or later
 * Text Domain: wsh-wcbte
 */

if ( ! defined('ABSPATH') ) exit;

define('WSH_WCBTE_VERSION', '1.0.0');
define('WSH_WCBTE_PATH', plugin_dir_path(__FILE__));
define('WSH_WCBTE_URL', plugin_dir_url(__FILE__));

// License server API base URL.
if ( ! defined( 'WSH_LICENSE_API_BASE' ) ) {
	define( 'WSH_LICENSE_API_BASE', 'https://wppluginspro.io/wp-json/wsh-license/v1' );
}

// Plugin slug on license server.
if ( ! defined( 'WSH_WCBTE_PLUGIN_SLUG' ) ) {
	define( 'WSH_WCBTE_PLUGIN_SLUG', 'wsh-woo-bulk-taxonomy-editor' );
}

require_once WSH_WCBTE_PATH . 'includes/class-wsh-wcbte-admin.php';
require_once WSH_WCBTE_PATH . 'includes/class-wsh-wcbte-license.php';
require_once WSH_WCBTE_PATH . 'includes/class-wsh-wcbte-license-page.php';

add_action('plugins_loaded', function () {
	if ( is_admin() ) {
		WSH_WCBTE_License::init();
		WSH_WCBTE_License_Page::init();
		WSH_WCBTE_Admin::init();
	}
});
