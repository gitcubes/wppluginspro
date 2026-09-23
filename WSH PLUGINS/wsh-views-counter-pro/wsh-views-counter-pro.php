<?php
/**
 * Plugin Name: WSH Views Counter PRO
 * Plugin URI:  https://websolutions.online/wshplugins/wsh-views-counter-pro
 * Description: PRO add-on for WSH Views Counter. Adds advanced analytics and features.
 * Version:     1.0.0
 * Author:      Web Solutions Hub LLC
 * Author URI:  https://websolutions.online
 * License:     GPLv2 or later
 * Text Domain: wsh-views-counter-pro
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Basic constants.
 */
if ( ! defined( 'WSH_VC_PRO_VERSION' ) ) {
	define( 'WSH_VC_PRO_VERSION', '1.0.0' );
}

if ( ! defined( 'WSH_VC_PRO_FILE' ) ) {
	define( 'WSH_VC_PRO_FILE', __FILE__ );
}

if ( ! defined( 'WSH_VC_PRO_PATH' ) ) {
	define( 'WSH_VC_PRO_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WSH_VC_PRO_URL' ) ) {
	define( 'WSH_VC_PRO_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * License API base URL (your license server).
 * Example: https://wppluginspro.io/wp-json/wsh-vc-license/v1
 */
if ( ! defined( 'WSH_VC_PRO_LICENSE_API_BASE' ) ) {
	define( 'WSH_VC_PRO_LICENSE_API_BASE', 'https://wppluginspro.io/wp-json/wsh-license/v1' );
}

if ( ! defined('WSH_VC_PRO_LICENSE_API_SECRET') ) {
	define('WSH_VC_PRO_LICENSE_API_SECRET', '^#UZ~FEx4.@,he6J0;x,r#K//CIq^:2;^rrnSm!>H#bd!.(6j^$ET+(7whZ^7^DI');
}

/**
 * Core loader – handles license class, admin menu (license page), etc.
 */
require_once WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-loader.php';

// Initialize loader on plugins_loaded (license, license page, etc.).
add_action( 'plugins_loaded', array( 'WSH_VC_Pro_Loader', 'init' ) );

/**
 * Load PRO feature modules (e.g. Geo Analytics).
 *
 * VAŽNO:
 * - PRO moduli se učitavaju samo ako je free plugin aktivan.
 * - Sam modul (Geo) u render_page proverava licencu i prikazuje "locked" poruku ako nije validna.
 */
add_action( 'plugins_loaded', 'wsh_vc_pro_init_features', 20 );

function wsh_vc_pro_init_features() {

	// Free plugin mora da postoji.
	if ( ! class_exists( 'WSH_Views_Counter' ) ) {
		return;
	}

	// License klasa mora da postoji (učitava je loader).
	if ( ! class_exists( 'WSH_VC_Pro_License' ) ) {
		return;
	}

	// Učitaj Geo modul.
	$geo_file = WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-geo.php';
	if ( file_exists( $geo_file ) ) {
		require_once $geo_file;

		if ( class_exists( 'WSH_VC_Pro_Geo' ) ) {
			WSH_VC_Pro_Geo::init();
		}
	}

	// Referrer Analytics module.
	$ref_file = WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-referrer.php';
	if ( file_exists( $ref_file ) ) {
		require_once $ref_file;

		if ( class_exists( 'WSH_VC_Pro_Referrer' ) ) {
			WSH_VC_Pro_Referrer::init();
		}
	}

	// Real-time Analytics module.
	$rt_file = WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-realtime.php';
	if ( file_exists( $rt_file ) ) {
		require_once $rt_file;
		if ( class_exists( 'WSH_VC_Pro_Realtime' ) ) {
			WSH_VC_Pro_Realtime::init();
		}
	}

	// Taxonomy Analytics module.
	$tax_file = WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-taxonomy.php';
	if ( file_exists( $tax_file ) ) {
		require_once $tax_file;
		if ( class_exists( 'WSH_VC_Pro_Taxonomy' ) ) {
			WSH_VC_Pro_Taxonomy::init();
		}
	}

	// Export PRO module.
	$export_file = WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-export.php';
	if ( file_exists( $export_file ) ) {
		require_once $export_file;
		if ( class_exists( 'WSH_VC_Pro_Export' ) ) {
			WSH_VC_Pro_Export::init();
		}
	}

	// Trending engine + shortcode.
	$trending_file = WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-trending.php';
	if ( file_exists( $trending_file ) ) {
		require_once $trending_file;
		if ( class_exists( 'WSH_VC_Pro_Trending' ) ) {
			WSH_VC_Pro_Trending::init();
		}
	}

	// Popular posts widget.
	$widget_file = WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-widget-popular.php';
	if ( file_exists( $widget_file ) ) {
		require_once $widget_file;
		if ( class_exists( 'WSH_VC_Pro_Widget_Popular' ) ) {
			WSH_VC_Pro_Widget_Popular::init();
		}
	}

	// Gutenberg blocks (Popular / Trending).
	$blocks_file = WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-blocks.php';
	if ( file_exists( $blocks_file ) ) {
		require_once $blocks_file;
		if ( class_exists( 'WSH_VC_Pro_Blocks' ) ) {
			WSH_VC_Pro_Blocks::init();
		}
	}

	// Advanced Charts & Heatmaps module.
	$charts_file = WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-charts.php';
	if ( file_exists( $charts_file ) ) {
		require_once $charts_file;
		if ( class_exists( 'WSH_VC_Pro_Charts' ) ) {
			WSH_VC_Pro_Charts::init();
		}
	}

	// Heatmaps PRO.
	$heat_file = WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-heatmaps.php';
	if ( file_exists( $heat_file ) ) {
		require_once $heat_file;
		if ( class_exists( 'WSH_VC_Pro_Heatmaps' ) ) {
			WSH_VC_Pro_Heatmaps::init();
		}
	}

	// Author Analytics module.
	$authors_file = WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-authors.php';
	if ( file_exists( $authors_file ) ) {
		require_once $authors_file;
		if ( class_exists( 'WSH_VC_Pro_Authors' ) ) {
			WSH_VC_Pro_Authors::init();
		}
	}

	// WooCommerce Analytics PRO module.
	$woo_file = WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-woocommerce.php';
	if ( file_exists( $woo_file ) ) {
		require_once $woo_file;
		if ( class_exists( 'WSH_VC_Pro_WooCommerce' ) ) {
			WSH_VC_Pro_WooCommerce::init();
		}
	}

	// Multi-Post Compare (PRO+) module.
	$compare_file = WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-compare.php';
	if ( file_exists( $compare_file ) ) {
		require_once $compare_file;
		if ( class_exists( 'WSH_VC_Pro_Compare' ) ) {
			WSH_VC_Pro_Compare::init();
		}
	}

	// Viral Detection Engine module.
	$viral_file = WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-viral.php';
	if ( file_exists( $viral_file ) ) {
		require_once $viral_file;

		if ( class_exists( 'WSH_VC_Pro_Viral' ) ) {
			WSH_VC_Pro_Viral::init();
		}
	}

	// Developer / Integrations module.
	$dev_file = WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-developer.php';
	if ( file_exists( $dev_file ) ) {
		require_once $dev_file;
		if ( class_exists( 'WSH_VC_Pro_Developer' ) ) {
			WSH_VC_Pro_Developer::init();
		}
	}


	// Kasnije: Referrer, Realtime, Compare...
	// $ref_file = WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-referrer.php';
	// if ( file_exists( $ref_file ) ) {
	//     require_once $ref_file;
	//     if ( class_exists( 'WSH_VC_Pro_Referrer' ) ) {
	//         WSH_VC_Pro_Referrer::init();
	//     }
	// }
}

/**
 * Activation hook for PRO plugin.
 */
function wsh_vc_pro_activate() {
	// Ensure loader is available.
	if ( class_exists( 'WSH_VC_Pro_Loader' ) ) {
		WSH_VC_Pro_Loader::on_activation();
	}
}
register_activation_hook( __FILE__, 'wsh_vc_pro_activate' );

/**
 * Deactivation hook for PRO plugin.
 */
function wsh_vc_pro_deactivate() {
	if ( class_exists( 'WSH_VC_Pro_Loader' ) ) {
		WSH_VC_Pro_Loader::on_deactivation();
	}
}
register_deactivation_hook( __FILE__, 'wsh_vc_pro_deactivate' );
