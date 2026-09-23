<?php
/**
 * Plugin Name: SEOPress PRO
 * Plugin URI: https://www.seopress.org/wordpress-seo-plugins/pro/
 * Description: The PRO version of SEOPress. SEOPress required (free).
 * Version: 9.7.3
 * Author: The SEO Guys at SEOPress
 * Author URI: https://www.seopress.org/wordpress-seo-plugins/pro/
 * License: GPLv3 or later
 * Text Domain: wp-seopress-pro
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.2
 *
 * @package SEOPressPRO
 */

/*
	Copyright 2016 - 2026 - Benjamin Denis  (email : contact@seopress.org)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 3, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// To prevent calling the plugin directly.
defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

/**
 * Define constants
 */
define( 'SEOPRESS_PRO_VERSION', '9.7.3' );
define( 'SEOPRESS_PRO_AUTHOR', 'Benjamin Denis' );
define( 'STORE_URL_SEOPRESS', 'https://www.seopress.org' );
define( 'ITEM_ID_SEOPRESS', 113 );
define( 'ITEM_NAME_SEOPRESS', 'SEOPress PRO' );
define( 'SEOPRESS_LICENSE_PAGE', 'seopress-license' );
define( 'SEOPRESS_PRO_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'SEOPRESS_PRO_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'SEOPRESS_PRO_ASSETS_DIR', SEOPRESS_PRO_PLUGIN_DIR_URL . 'assets' );
define( 'SEOPRESS_PRO_PUBLIC_URL', SEOPRESS_PRO_PLUGIN_DIR_URL . 'public' );
define( 'SEOPRESS_PRO_PUBLIC_PATH', SEOPRESS_PRO_PLUGIN_DIR_PATH . 'public' );
define( 'SEOPRESS_PRO_TEMPLATE_DIR', SEOPRESS_PRO_PLUGIN_DIR_PATH . 'templates' );
define( 'SEOPRESS_PRO_TEMPLATE_JSON_SCHEMAS', SEOPRESS_PRO_TEMPLATE_DIR . '/json-schemas' );
define( 'SEOPRESS_PRO_TEMPLATE_STOP_WORDS', SEOPRESS_PRO_TEMPLATE_DIR . '/stop-words' );

/**
 * Kernel
 */
use SEOPressPro\Core\Kernel;
require_once SEOPRESS_PRO_PLUGIN_DIR_PATH . 'seopress-autoload.php';

if ( file_exists( SEOPRESS_PRO_PLUGIN_DIR_PATH . '/vendor/autoload.php' ) && file_exists( WP_PLUGIN_DIR . '/wp-seopress/seopress-autoload.php' ) ) {
	require_once WP_PLUGIN_DIR . '/wp-seopress/seopress-autoload.php';
	require_once SEOPRESS_PRO_PLUGIN_DIR_PATH . '/seopress-pro-functions.php';
	require_once SEOPRESS_PRO_PLUGIN_DIR_PATH . '/inc/admin/cron.php';

	Kernel::execute(
		array(
			'file'      => __FILE__,
			'slug'      => 'wp-seopress-pro',
			'main_file' => 'seopress-pro',
			'root'      => __DIR__,
		)
	);
}

/**
 * CRON
 *
 * @return void
 */
function seopress_pro_cron() {
	// CRON - 404 cleaning.
	if ( ! wp_next_scheduled( 'seopress_404_cron_cleaning' ) ) {
		wp_schedule_event( time(), 'daily', 'seopress_404_cron_cleaning' );
	}

	// CRON - GA stats in dashboard.
	if ( ! wp_next_scheduled( 'seopress_google_analytics_cron' ) ) {
		wp_schedule_event( time(), 'hourly', 'seopress_google_analytics_cron' );
	}

	// CRON - Matomo stats in dashboard.
	if ( ! wp_next_scheduled( 'seopress_matomo_analytics_cron' ) ) {
		wp_schedule_event( time(), 'hourly', 'seopress_matomo_analytics_cron' );
	}

	// CRON - Page Speed Insights.
	if ( ! wp_next_scheduled( 'seopress_page_speed_insights_cron' ) ) {
		wp_schedule_event( time(), 'daily', 'seopress_page_speed_insights_cron' );
	}

	// CRON - 404 errors Email Alerts.
	if ( ! wp_next_scheduled( 'seopress_404_email_alerts_cron' ) ) {
		wp_schedule_event( time(), 'weekly', 'seopress_404_email_alerts_cron' );
	}

	// CRON - Insights from GSC.
	if ( ! wp_next_scheduled( 'seopress_insights_gsc_cron' ) ) {
		wp_schedule_event( time(), 'daily', 'seopress_insights_gsc_cron' );
	}

	// CRON - SEO Alerts.
	if ( ! wp_next_scheduled( 'seopress_alerts_cron' ) ) {
		wp_schedule_event( time(), 'twicedaily', 'seopress_alerts_cron' );
	}
}

/**
 * Loaded
 *
 * @return void
 */
function seopress_pro_loaded() {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		return;
	}

	if ( ! is_plugin_active( 'wp-seopress/seopress.php' ) ) {// If SEOPress Free NOT activated.
		deactivate_plugins( 'wp-seopress-pro/seopress-pro.php' );
		add_action( 'admin_notices', 'seopress_pro_admin_notices' );
	}
}
add_action( 'plugins_loaded', 'seopress_pro_loaded' );

/**
 * Install plugins
 *
 * @param string $plugin_slug The plugin slug.
 * @throws Exception If the plugin installation fails.
 * @return void
 */
function seopress_pro_install_plugin( $plugin_slug ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	WP_Filesystem();

	$skin     = new Automatic_Upgrader_Skin();
	$upgrader = new WP_Upgrader( $skin );

	if ( ! empty( $plugin_slug ) ) {
		ob_start();

		try {
			$plugin_information = plugins_api(
				'plugin_information',
				array(
					'slug'   => $plugin_slug,
					'fields' => array(
						'short_description' => false,
						'sections'          => false,
						'requires'          => false,
						'rating'            => false,
						'ratings'           => false,
						'downloaded'        => false,
						'last_updated'      => false,
						'added'             => false,
						'tags'              => false,
						'homepage'          => false,
						'donate_link'       => false,
						'author_profile'    => false,
						'author'            => false,
					),
				)
			);

			if ( is_wp_error( $plugin_information ) ) {
				throw new Exception( $plugin_information->get_error_message() );
			}

			$package  = $plugin_information->download_link;
			$download = $upgrader->download_package( $package );

			if ( is_wp_error( $download ) ) {
				throw new Exception( $download->get_error_message() );
			}

			$working_dir = $upgrader->unpack_package( $download, true );

			if ( is_wp_error( $working_dir ) ) {
				throw new Exception( $working_dir->get_error_message() );
			}

			$result = $upgrader->install_package(
				array(
					'source'                      => $working_dir,
					'destination'                 => WP_PLUGIN_DIR,
					'clear_destination'           => false,
					'abort_if_destination_exists' => false,
					'clear_working'               => true,
					'hook_extra'                  => array(
						'type'   => 'plugin',
						'action' => 'install',
					),
				)
			);

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			$activate = true;
		} catch ( Exception $e ) {
			$e->getMessage();
		}

		ob_end_clean();
	}

	wp_clean_plugins_cache();
}

/**
 * Hooks activation
 *
 * @return void
 */
function seopress_pro_activation() {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	if ( ! function_exists( 'activate_plugins' ) ) {
		return;
	}

	if ( ! function_exists( 'get_plugins' ) ) {
		return;
	}

	$plugins = get_plugins();
	if ( empty( $plugins['wp-seopress/seopress.php'] ) ) { // If SEOPress Free is NOT installed.
		seopress_pro_install_plugin( 'wp-seopress' );
		activate_plugins( 'wp-seopress/seopress.php' );
	}

	if ( ! empty( $plugins['wp-seopress/seopress.php'] ) ) { // If SEOPress Free is installed.
		if ( ! is_plugin_active( 'wp-seopress/seopress.php' ) ) { // If SEOPress Free is not activated.
			activate_plugins( 'wp-seopress/seopress.php' );
		}
		add_option( 'seopress_pro_activated', 'yes', '', false );

		flush_rewrite_rules( false );

		seopress_pro_cron();
	}

	// Add Redirections caps to user with "manage_options" capability.
	$roles = get_editable_roles();
	if ( ! empty( $roles ) ) {
		foreach ( $GLOBALS['wp_roles']->role_objects as $key => $role ) {
			if ( isset( $roles[ $key ] ) && $role->has_cap( 'manage_options' ) ) {
				$role->add_cap( 'edit_redirection' );
				$role->add_cap( 'edit_redirections' );
				$role->add_cap( 'edit_others_redirections' );
				$role->add_cap( 'publish_redirections' );
				$role->add_cap( 'read_redirection' );
				$role->add_cap( 'read_private_redirections' );
				$role->add_cap( 'delete_redirection' );
				$role->add_cap( 'delete_redirections' );
				$role->add_cap( 'delete_others_redirections' );
				$role->add_cap( 'delete_published_redirections' );
			}
			if ( isset( $roles[ $key ] ) && $role->has_cap( 'manage_options' ) ) {
				$role->add_cap( 'edit_schema' );
				$role->add_cap( 'edit_schemas' );
				$role->add_cap( 'edit_others_schemas' );
				$role->add_cap( 'publish_schemas' );
				$role->add_cap( 'read_schema' );
				$role->add_cap( 'read_private_schemas' );
				$role->add_cap( 'delete_schema' );
				$role->add_cap( 'delete_schemas' );
				$role->add_cap( 'delete_others_schemas' );
				$role->add_cap( 'delete_published_schemas' );
			}
		}
	}

	do_action( 'seopress_pro_activation' );
}
register_activation_hook( __FILE__, 'seopress_pro_activation' );

/**
 * Hooks deactivation
 *
 * @return void
 */
function seopress_pro_deactivation() {
	delete_option( 'seopress_pro_activated' );
	flush_rewrite_rules( false );
	wp_clear_scheduled_hook( 'seopress_404_cron_cleaning' );
	wp_clear_scheduled_hook( 'seopress_google_analytics_cron' );
	wp_clear_scheduled_hook( 'seopress_page_speed_insights_cron' );
	wp_clear_scheduled_hook( 'seopress_404_email_alerts_cron' );
	wp_clear_scheduled_hook( 'seopress_insights_gsc_cron' );
	wp_clear_scheduled_hook( 'seopress_matomo_analytics_cron' );
	wp_clear_scheduled_hook( 'seopress_404_background_cleanup' ); // Clear 404 limit cleanup cron.
	do_action( 'seopress_pro_deactivation' );
}
register_deactivation_hook( __FILE__, 'seopress_pro_deactivation' );

/**
 * Loads the SEOPress PRO admin + core + API
 *
 * @return void
 */
function seopress_pro_plugins_loaded() {
	// CRON.
	seopress_pro_cron();

	global $pagenow;

	if ( ! function_exists( 'seopress_capability' ) ) {
		return;
	}

	$plugin_dir = plugin_dir_path( __FILE__ );

	if ( is_admin() || is_network_admin() ) {
		require_once $plugin_dir . '/inc/admin/admin.php';
		require_once $plugin_dir . '/inc/admin/ajax.php';

		if ( 'post-new.php' === $pagenow || 'post.php' === $pagenow ) {
			require_once $plugin_dir . '/inc/admin/metaboxes/admin-metaboxes.php';
		}

		if ( 'index.php' === $pagenow || ( isset( $_GET['page'] ) && 'seopress-option' === $_GET['page'] ) ) {
			require_once $plugin_dir . '/inc/admin/wp-dashboard/google-analytics.php';
			require_once $plugin_dir . '/inc/admin/wp-dashboard/matomo.php';
		}

		// CSV Import.
		if ( wp_doing_ajax() || ( isset( $_GET['page'] ) && 'seopress_csv_importer' === $_GET['page'] ) ) {
			include_once $plugin_dir . '/inc/admin/import/class-csv-wizard.php';
		}

		// Bot.
		require_once $plugin_dir . '/inc/admin/bot/bot.php';
		require_once $plugin_dir . '/inc/functions/bot/seopress-bot.php';
	}

	// Watchers.
	if ( ! isset( $_GET['bricks'] ) ) {
		require_once $plugin_dir . '/inc/admin/watchers/index.php';
	}

	// Redirections.
	if ( is_admin() ) {
		if ( function_exists( 'seopress_get_toggle_option' ) && '1' === seopress_get_toggle_option( '404' ) ) {
			require_once $plugin_dir . '/inc/admin/redirections/redirections.php';
		}
	}

	// Bricks.
	if ( ! isset( $_GET['bricks'] ) ) {
		require_once $plugin_dir . '/inc/functions/options.php';
	}

	require_once $plugin_dir . '/inc/admin/admin-bar/admin-bar.php';

	// Elementor.
	if ( did_action( 'elementor/loaded' ) ) {
		require_once $plugin_dir . '/inc/admin/page-builders/elementor/elementor-widgets.php';
	}

	// TranslationsPress.
	if ( ! class_exists( 'SEOPRESS_Language_Packs' ) ) {
		if ( is_admin() || is_network_admin() ) {
			require_once $plugin_dir . '/inc/admin/updater/t15s-registry.php';
		}
	}

	// Blocks registration.
	require_once $plugin_dir . '/inc/functions/blocks.php';
}
add_action( 'plugins_loaded', 'seopress_pro_plugins_loaded', 999 );

/**
 * Loads the SEOPress PRO i18n.
 *
 * @return void
 */
function seopress_pro_init_t15s() {
	// i18n.
	load_plugin_textdomain( 'wp-seopress-pro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	if ( class_exists( 'SEOPRESS_Language_Packs' ) ) {
		$t15s_updater = new SEOPRESS_Language_Packs(
			'wp-seopress-pro',
			'https://packages.translationspress.com/seopress/wp-seopress-pro/packages.json'
		);
	}
}
add_action( 'init', 'seopress_pro_init_t15s' );

/**
 * Loads the JS/CSS in admin.
 *
 * @return void
 */
function seopress_pro_admin_scripts() {
	$prefix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	if ( seopress_get_service( 'ToggleOption' )->getToggleAi() !== '1' ) {
		return;
	}

	$seopress_ai_generate_seo_meta = array(
		'seopress_nonce'                => wp_create_nonce( 'seopress_ai_generate_seo_meta_nonce' ),
		'seopress_ai_generate_seo_meta' => admin_url( 'admin-ajax.php' ),
		'i18n'                          => array(
			'alt_text_not_found' => __( 'Alternative text input could not be found.', 'wp-seopress-pro' ),
		),
	);

	wp_enqueue_script( 'seopress-pro-ai', plugins_url( 'assets/js/seopress-pro-ai' . $prefix . '.js', __FILE__ ), array( 'jquery' ), SEOPRESS_PRO_VERSION, true );
	wp_localize_script( 'seopress-pro-ai', 'seopressAjaxAIMetaSEO', $seopress_ai_generate_seo_meta );
}
add_action( 'seopress_seo_metabox_init', 'seopress_pro_admin_scripts' );

/**
 * Loads Google Page Speed Insights scripts.
 *
 * @return void
 */
function seopress_pro_admin_ps_scripts() {
	$prefix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

	wp_enqueue_script( 'seopress-page-speed', plugins_url( 'assets/js/seopress-page-speed' . $prefix . '.js', __FILE__ ), array( 'jquery', 'jquery-ui-accordion' ), SEOPRESS_PRO_VERSION, true );

	$seopress_request_page_speed = array(
		'seopress_nonce'              => wp_create_nonce( 'seopress_request_page_speed_nonce' ),
		'seopress_request_page_speed' => admin_url( 'admin-ajax.php' ),
	);
	wp_localize_script( 'seopress-page-speed', 'seopressAjaxRequestPageSpeed', $seopress_request_page_speed );

	$seopress_clear_page_speed_cache = array(
		'seopress_nonce'                  => wp_create_nonce( 'seopress_clear_page_speed_cache_nonce' ),
		'seopress_clear_page_speed_cache' => admin_url( 'admin-ajax.php' ),
	);
	wp_localize_script( 'seopress-page-speed', 'seopressAjaxClearPageSpeedCache', $seopress_clear_page_speed_cache );
}

/**
 * Enqueues scripts for the SEOPRESS PRO options page
 *
 * @param string $hook The current admin screen.
 * @return void
 */
function seopress_pro_add_admin_options_scripts( $hook ) {
	$prefix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	global $typenow;
	global $pagenow;

	wp_register_style( 'seopress-pro-admin', plugins_url( 'assets/css/seopress-pro' . $prefix . '.css', __FILE__ ), array(), SEOPRESS_PRO_VERSION );
	wp_enqueue_style( 'seopress-pro-admin' );

	// AI in post types list.
	if ( 'edit.php' === $hook ) {
		seopress_pro_admin_scripts();
	}

	// SEO Dashboard.
	wp_register_style( 'seopress-ga-dashboard-widget', plugins_url( 'assets/css/seopress-pro-dashboard' . $prefix . '.css', __FILE__ ), array(), SEOPRESS_PRO_VERSION );
	if ( isset( $_GET['page'] ) && 'seopress-option' === $_GET['page'] ) {
		wp_enqueue_style( 'seopress-ga-dashboard-widget' );
	}

	// Dashboard GA.
	if ( isset( get_current_screen()->id ) && get_current_screen()->id === 'dashboard' || ( isset( $_GET['page'] ) && 'seopress-option' === $_GET['page'] ) ) {
		if ( ( current_user_can( seopress_capability( 'manage_options', 'cron' ) ) || seopress_advanced_security_ga_widget_check() === true ) ) {
			if ( function_exists( 'seopress_get_toggle_option' ) && '1' === seopress_get_toggle_option( 'google-analytics' ) ) {
				$service = seopress_pro_get_service( 'GoogleAnalyticsWidgetsOptionPro' );

				if ( ! empty( $service ) && method_exists( $service, 'getGA4DashboardWidget' ) && '1' !== $service ) {
					wp_enqueue_style( 'seopress-ga-dashboard-widget' );

					// GA API.
					wp_enqueue_script( 'seopress-pro-ga-embed', plugins_url( 'assets/js/chart.bundle.min.js', __FILE__ ), array(), SEOPRESS_PRO_VERSION, true );

					wp_enqueue_script( 'seopress-pro-ga', plugins_url( 'assets/js/seopress-pro-ga' . $prefix . '.js', __FILE__ ), array( 'jquery', 'jquery-ui-tabs' ), SEOPRESS_PRO_VERSION, true );

					$seopress_request_google_analytics = array(
						'seopress_nonce' => wp_create_nonce( 'seopress_request_google_analytics_nonce' ),
						'seopress_request_google_analytics' => admin_url( 'admin-ajax.php' ),
					);
					wp_localize_script( 'seopress-pro-ga', 'seopressAjaxRequestGoogleAnalytics', $seopress_request_google_analytics );
				}
			}
		}
	}

	// Dashboard Matomo.
	if ( isset( get_current_screen()->id ) && get_current_screen()->id === 'dashboard' || ( isset( $_GET['page'] ) && 'seopress-option' === $_GET['page'] ) ) {
		if ( ( current_user_can( seopress_capability( 'manage_options', 'cron' ) ) || seopress_advanced_security_matomo_widget_check() === true ) ) {
			if ( function_exists( 'seopress_get_toggle_option' ) && '1' === seopress_get_toggle_option( 'google-analytics' ) ) {
				$service = seopress_pro_get_service( 'GoogleAnalyticsWidgetsOptionPro' );
				if ( ! empty( $service ) && method_exists( $service, 'getMatomoDashboardWidget' ) && '1' !== $service ) {
					wp_enqueue_style( 'seopress-ga-dashboard-widget' );

					// Matomo API.
					wp_enqueue_script( 'seopress-pro-ga-embed', plugins_url( 'assets/js/chart.bundle.min.js', __FILE__ ), array(), SEOPRESS_PRO_VERSION, true );

					wp_enqueue_script( 'seopress-pro-matomo', plugins_url( 'assets/js/seopress-pro-matomo' . $prefix . '.js', __FILE__ ), array( 'jquery', 'jquery-ui-tabs' ), SEOPRESS_PRO_VERSION, true );

					$seopress_request_matomo_analytics = array(
						'seopress_nonce' => wp_create_nonce( 'seopress_request_matomo_analytics_nonce' ),
						'seopress_request_matomo_analytics' => admin_url( 'admin-ajax.php' ),
					);
					wp_localize_script( 'seopress-pro-matomo', 'seopressAjaxRequestMatomoAnalytics', $seopress_request_matomo_analytics );
				}
			}
		}
	}

	// Local Business widget.
	if ( 'widgets.php' === $pagenow ) {
		wp_enqueue_script( 'seopress-pro-lb-widget', plugins_url( 'assets/js/seopress-pro-lb-widget' . $prefix . '.js', __FILE__ ), array( 'jquery', 'jquery-ui-tabs' ), SEOPRESS_PRO_VERSION, true );

		$seopress_pro_lb_widget = array(
			'seopress_nonce'         => wp_create_nonce( 'seopress_pro_lb_widget_nonce' ),
			'seopress_pro_lb_widget' => admin_url( 'admin-ajax.php' ),
		);
		wp_localize_script( 'seopress-pro-lb-widget', 'seopressAjaxLocalBusinessOrder', $seopress_pro_lb_widget );
	}

	// .htaccess: Network Admin page now uses React (HtaccessTab component handles AJAX save).

	// Google Page Speed.
	if ( 'edit.php' === $hook ) {
		seopress_pro_admin_ps_scripts();
	}

	// Bot Tabs.
	if ( isset( $_GET['page'] ) && 'seopress-bot-batch' === $_GET['page'] ) {
		wp_enqueue_style( 'seopress-datatable', plugins_url( 'assets/css/datatables.min.css', __FILE__ ), array(), SEOPRESS_PRO_VERSION );

		wp_enqueue_script( 'seopress-datatable', plugins_url( 'assets/js/datatables.min.js', __FILE__ ), array( 'jquery' ), SEOPRESS_PRO_VERSION );
		wp_enqueue_script( 'seopress-bot-admin-tabs', plugins_url( 'assets/js/seopress-bot-tabs' . $prefix . '.js', __FILE__ ), array( 'jquery-ui-tabs' ), SEOPRESS_PRO_VERSION );

		$seopress_bot = array(
			'seopress_nonce'       => wp_create_nonce( 'seopress_request_bot_nonce' ),
			'seopress_request_bot' => admin_url( 'admin-ajax.php' ),
		);
		wp_localize_script( 'seopress-bot-admin-tabs', 'seopressAjaxBot', $seopress_bot );
	}

	// Media Library.
	if ( 'upload.php' === $pagenow || 'attachment' === $typenow ) {
		$active = seopress_get_service( 'ToggleOption' )->getToggleAi();

		if ( '1' === $active ) {
			$seopress_ai_generate_seo_meta = array(
				'seopress_nonce'                => wp_create_nonce( 'seopress_ai_generate_seo_meta_nonce' ),
				'seopress_ai_generate_seo_meta' => admin_url( 'admin-ajax.php' ),
				'i18n'                          => array(
					'alt_text_not_found' => __( 'Alternative text input could not be found.', 'wp-seopress-pro' ),
				),
			);

			wp_enqueue_script( 'seopress-pro-ai', plugins_url( 'assets/js/seopress-pro-ai' . $prefix . '.js', __FILE__ ), array( 'jquery' ), SEOPRESS_PRO_VERSION, true );

			wp_localize_script( 'seopress-pro-ai', 'seopressAjaxAIMetaSEO', $seopress_ai_generate_seo_meta );
		}
	}

	// Video xml sitemap.
	if ( isset( $_GET['page'] ) && 'seopress-import-export' === $_GET['page'] ) {
		wp_enqueue_script( 'seopress-pro-video-sitemap-ajax', plugins_url( 'assets/js/seopress-pro-video-sitemap' . $prefix . '.js', __FILE__ ), array( 'jquery' ), SEOPRESS_PRO_VERSION, true );

		// Force regenerate video xml sitemap.
		$seopress_video_regenerate = array(
			'seopress_nonce'            => wp_create_nonce( 'seopress_video_regenerate_nonce' ),
			'seopress_video_regenerate' => admin_url( 'admin-ajax.php' ),
			'i18n'                      => array(
				'video' => __( 'Regeneration completed!', 'wp-seopress-pro' ),
			),
		);
		wp_localize_script( 'seopress-pro-video-sitemap-ajax', 'seopressAjaxVdeoRegenerate', $seopress_video_regenerate );
	}

	// License.
	if ( isset( $_GET['page'] ) && 'seopress-license' === $_GET['page'] ) {
		wp_enqueue_script( 'seopress-license', plugins_url( 'assets/js/seopress-pro-license' . $prefix . '.js', __FILE__ ), array( 'jquery' ), SEOPRESS_PRO_VERSION, true );

		$seopress_request_reset_license = array(
			'seopress_nonce'                 => wp_create_nonce( 'seopress_request_reset_license_nonce' ),
			'seopress_request_reset_license' => admin_url( 'admin-ajax.php' ),
		);
		wp_localize_script( 'seopress-license', 'seopressAjaxResetLicense', $seopress_request_reset_license );
	}

	// Chatbot.
	if ( function_exists( 'is_seopress_page' ) && is_seopress_page() ) {
		$livechat         = seopress_pro_get_service( 'AdvancedOptionPro' )->getAppearanceDashboardLiveChat();
		$is_seopress_page = 0;
		$display_livechat = 1;

		if ( '1' === $livechat ) {
			$display_livechat = 0;
		}

		if ( 'seopress_404' !== get_current_screen()->post_type && 'seopress_bot' !== get_current_screen()->post_type && 'seopress_schemas' !== get_current_screen()->post_type ) {
			$is_seopress_page = 1;
		}
		if ( 1 === $display_livechat && 1 === $is_seopress_page ) {
			wp_enqueue_script( 'seopress-pro-chatbot', plugins_url( 'assets/js/seopress-pro-chatbot.min.js', __FILE__ ), array(), SEOPRESS_PRO_VERSION, true );
			wp_add_inline_style( 'seopress-pro-admin', '#chatbase-bubble-button::after {content: " ' . esc_html__( 'Help?', 'wp-seopress-pro' ) . '";}' );
		}
	}
}

add_action( 'admin_enqueue_scripts', 'seopress_pro_add_admin_options_scripts', 10, 1 );

/**
 * Register PRO page in the free plugin's SPA via filters.
 */
function seopress_pro_register_react_page() {
	// Add PRO to supported pages.
	add_filter( 'seopress_settings_supported_pages', function ( $pages ) {
		$pages['seopress-pro-page'] = array(
			'type'   => 'pro',
			'option' => 'seopress_pro_option_name',
		);
		$pages['seopress-network-option'] = array(
			'type'   => 'network-admin',
			'option' => 'seopress_pro_mu_option_name',
		);
		return $pages;
	} );

	// Add PRO to navigation.
	add_filter( 'seopress_settings_all_pages', function ( $pages ) {
		$pages[] = array(
			'slug'    => 'seopress-pro-page',
			'type'    => 'pro',
			'feature' => null,
			'label'   => __( 'PRO', 'wp-seopress-pro' ),
			'url'     => admin_url( 'admin.php?page=seopress-pro-page' ),
		);
		$pages[] = array(
			'slug'    => 'seopress-network-option',
			'type'    => 'network-admin',
			'feature' => null,
			'label'   => __( 'SEO Network settings', 'wp-seopress-pro' ),
			'url'     => admin_url( 'admin.php?page=seopress-network-option' ),
		);
		return $pages;
	} );

	// Mark PRO as React-ready.
	add_filter( 'seopress_settings_react_ready_pages', function ( $pages ) {
		$pages[] = 'pro';
		$pages[] = 'network-admin';
		return $pages;
	} );

	// Register REST endpoints for SettingsContext.
	add_filter( 'seopress_settings_api_endpoints', function ( $endpoints ) {
		$endpoints['pro']           = '/seopress/v1/options/pro-settings';
		$endpoints['network-admin'] = '/seopress/v1/options/pro-mu-settings';
		return $endpoints;
	} );

	// Register PRO feature toggle keys so they appear in SEOPRESS_SETTINGS_DATA.FEATURE_TOGGLES.
	add_filter( 'seopress_settings_feature_toggle_keys', function ( $features ) {
		$pro_features = array(
			'404', 'rich-snippets', 'local-business', 'woocommerce', 'edd',
			'dublin-core', 'robots', 'llms', 'news', 'inspect-url',
			'breadcrumbs', 'white-label', 'ai', 'alerts',
		);
		return array_merge( $features, $pro_features );
	} );
}
add_action( 'plugins_loaded', 'seopress_pro_register_react_page' );

/**
 * Enqueue PRO React settings bundle on SEOPress settings pages.
 * Loads after the free plugin's settings JS so the extension registry is available.
 */
function seopress_pro_enqueue_react_settings( $hook ) {
	// Match by page slug suffix -- the hook prefix varies depending on the parent menu slug
	// (e.g. seopress_page_, seo_page_, or custom white-label prefix).
	$page_slugs = array(
		'seopress-titles',
		'seopress-xml-sitemap',
		'seopress-social',
		'seopress-google-analytics',
		'seopress-instant-indexing',
		'seopress-advanced',
		'seopress-import-export',
		'seopress-pro-page',
		'seopress-network-option',
	);

	$is_settings_page = false;
	$matched_slug     = '';
	foreach ( $page_slugs as $slug ) {
		if ( str_ends_with( $hook, '_page_' . $slug ) || 'toplevel_page_' . $slug === $hook ) {
			$is_settings_page = true;
			$matched_slug     = $slug;
			break;
		}
	}

	if ( ! $is_settings_page ) {
		return;
	}

	$asset_file = plugin_dir_path( __FILE__ ) . 'public/admin/settings/index.asset.php';
	$asset      = file_exists( $asset_file ) ? require $asset_file : array( 'dependencies' => array(), 'version' => SEOPRESS_PRO_VERSION );

	wp_enqueue_script(
		'seopress-pro-admin-settings',
		plugins_url( 'public/admin/settings/index.js', __FILE__ ),
		array_merge( $asset['dependencies'], array( 'seopress-admin-settings' ) ),
		$asset['version'],
		true
	);

	// Load translations for the PRO React settings bundle.
	// Point to wp-content/languages/plugins/ where TranslationsPress language packs are stored.
	wp_set_script_translations( 'seopress-pro-admin-settings', 'wp-seopress-pro', WP_LANG_DIR . '/plugins' );

	// Merge translations from lazy-loaded webpack chunks into the main script.
	// TranslationsPress generates a separate JSON per chunk, but wp_set_script_translations
	// only loads the one matching the main bundle. This filter merges all of them.
	add_filter(
		'pre_load_script_translations',
		function ( $translations, $file, $handle, $domain ) {
			if ( 'seopress-pro-admin-settings' !== $handle || 'wp-seopress-pro' !== $domain ) {
				return $translations;
			}

			$locale    = determine_locale();
			$cache_key = 'seopress_pro_i18n_merged_' . $locale;
			$cached    = wp_cache_get( $cache_key, 'seopress' );

			if ( false !== $cached ) {
				return $cached;
			}

			$lang_dir = WP_LANG_DIR . '/plugins';
			$merged   = array( 'locale_data' => array( 'messages' => array( '' => array() ) ) );

			foreach ( glob( $lang_dir . '/wp-seopress-pro-' . $locale . '-*.json' ) as $json_file ) {
				$content = file_get_contents( $json_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( ! $content ) {
					continue;
				}
				$data = json_decode( $content, true );
				if ( ! isset( $data['locale_data']['messages'] ) ) {
					continue;
				}
				foreach ( $data['locale_data']['messages'] as $key => $value ) {
					if ( '' === $key ) {
						if ( empty( $merged['locale_data']['messages'][''] ) ) {
							$merged['locale_data']['messages'][''] = $value;
						}
						continue;
					}
					$merged['locale_data']['messages'][ $key ] = $value;
				}
			}

			$result = wp_json_encode( $merged );

			wp_cache_set( $cache_key, $result, 'seopress' );

			return $result;
		},
		10,
		4
	);

	// Localize PRO tools data on all SEOPress settings pages (needed for SPA navigation).
	{
		$is_redirections_enabled = function_exists( 'seopress_get_toggle_option' ) && '1' === seopress_get_toggle_option( '404' );

		$is_video_sitemap_enabled = false;
		if ( function_exists( 'seopress_get_toggle_option' )
			&& '1' === seopress_get_toggle_option( 'xml-sitemap' )
			&& function_exists( 'seopress_get_service' )
			&& '1' === seopress_get_service( 'SitemapOption' )->isEnabled()
			&& function_exists( 'seopress_pro_get_service' )
			&& method_exists( seopress_pro_get_service( 'SitemapOptionPro' ), 'getSitemapVideoEnable' )
			&& '1' === seopress_pro_get_service( 'SitemapOptionPro' )->getSitemapVideoEnable()
		) {
			$is_video_sitemap_enabled = true;
		}

		$docs = function_exists( 'seopress_get_docs_links' ) ? seopress_get_docs_links() : array();

		wp_localize_script( 'seopress-pro-admin-settings', 'SEOPRESS_PRO_TOOLS_DATA', array(
			'ADMIN_URL'                => admin_url(),
			'GA_OAUTH_NONCE'           => wp_create_nonce( 'seopress_ga_oauth_nonce' ),
			'CSV_IMPORTER_URL'         => admin_url( 'admin.php?page=seopress_csv_importer' ),
			'IS_REDIRECTIONS_ENABLED'  => $is_redirections_enabled,
			'IS_VIDEO_SITEMAP_ENABLED' => $is_video_sitemap_enabled,
			'VIDEO_NONCE'              => wp_create_nonce( 'seopress_video_regenerate_nonce' ),
			'CSV_EXPORT_NONCE'         => wp_create_nonce( 'seopress_export_csv_metadata_nonce' ),
			'PRO_PAGE_URL'             => admin_url( 'admin.php?page=seopress-pro-page' ),
			'SITEMAP_PAGE_URL'         => admin_url( 'admin.php?page=seopress-xml-sitemap' ),
			'VIDEO_SITEMAP_URL'        => get_option( 'home' ) . '/video1.xml',
			'REDIRECTS_NONCES'         => array(
				'import_redirections'         => wp_create_nonce( 'seopress_import_redirections_nonce' ),
				'import_redirections_plugin'  => wp_create_nonce( 'seopress_import_redirections_plugin_nonce' ),
				'import_yoast_redirections'   => wp_create_nonce( 'seopress_import_yoast_redirections_nonce' ),
				'import_rk_redirections'      => wp_create_nonce( 'seopress_import_rk_redirections_nonce' ),
				'import_aioseo_redirections'  => wp_create_nonce( 'seopress_import_aioseo_redirections_nonce' ),
				'import_smartcrawl_redirections' => wp_create_nonce( 'seopress_import_smartcrawl_redirections_nonce' ),
				'export_redirections'         => wp_create_nonce( 'seopress_export_redirections_nonce' ),
				'export_redirections_htaccess' => wp_create_nonce( 'seopress_export_redirections_htaccess_nonce' ),
				'export_slug_changes'         => wp_create_nonce( 'seopress_export_slug_changes_nonce' ),
				'export_404'                  => wp_create_nonce( 'seopress_export_404_nonce' ),
				'clean_404'                   => wp_create_nonce( 'seopress_clean_404_nonce' ),
				'clean_counters'              => wp_create_nonce( 'seopress_clean_counters_nonce' ),
				'clean_all'                   => wp_create_nonce( 'seopress_clean_all_nonce' ),
			),
			'REDIRECTIONS_LIST_URL'    => admin_url( 'edit.php?post_type=seopress_404' ),
			'DOCS_REDIRECTS_QUERY'     => isset( $docs['redirects']['query'] ) ? $docs['redirects']['query'] : '',
			'CSV_EXAMPLE_URL'          => 'https://www.seopress.org/wp-content/uploads/csv/seopress-redirections-example.csv',
			'CSV_IMPORT_RESULT'        => ( function () {
				$key    = 'seopress_csv_import_result_' . get_current_user_id();
				$result = get_transient( $key );
				if ( false !== $result ) {
					delete_transient( $key );
					return $result;
				}
				return null;
			} )(),
		) );
	}

	// Localize PRO settings data on the PRO page.
	if ( 'seopress-pro-page' === $matched_slug ) {
		$pro_options = get_option( 'seopress_pro_option_name', array() );

		// Read .htaccess content and determine status.
		$htaccess_content = '';
		$htaccess_status  = 'ok';
		$htaccess_path    = ABSPATH . '.htaccess';

		if ( ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT === true )
			|| ( defined( 'SEOPRESS_BLOCK_HTACCESS' ) && SEOPRESS_BLOCK_HTACCESS === true ) ) {
			$htaccess_status = 'blocked';
		} elseif ( ! is_network_admin() && is_multisite() ) {
			$htaccess_status = 'multisite';
		} elseif ( isset( $_SERVER['SERVER_SOFTWARE'] ) && 0 === stripos( $_SERVER['SERVER_SOFTWARE'], 'nginx' ) ) {
			$htaccess_status = 'nginx';
		} elseif ( ! file_exists( $htaccess_path ) || ! is_writable( $htaccess_path ) ) {
			$htaccess_status = 'not_writable';
		} else {
			$htaccess_content = file_get_contents( $htaccess_path ); // phpcs:ignore
		}

		// Get post types for Google News and breadcrumbs.
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$pt_list    = array();
		foreach ( $post_types as $pt ) {
			$pt_list[] = array(
				'name'  => $pt->name,
				'label' => $pt->label,
			);
		}

		// Get taxonomies for breadcrumbs.
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		$tax_list   = array();
		foreach ( $taxonomies as $tax ) {
			$tax_list[] = array(
				'name'  => $tax->name,
				'label' => $tax->label,
			);
		}

		// Get local business types.
		$local_business_types = array();
		if ( class_exists( 'SEOPressPro\Helpers\Settings\LocalBusinessHelper' ) ) {
			$local_business_types = \SEOPressPro\Helpers\Settings\LocalBusinessHelper::getListTypes();
		}

		// Get docs links.
		$docs_links = function_exists( 'seopress_get_docs_links' ) ? seopress_get_docs_links() : array();

		wp_localize_script( 'seopress-pro-admin-settings', 'SEOPRESS_PRO_DATA', array(
			// Tab visibility conditions.
			'IS_WOOCOMMERCE_ACTIVE'   => is_plugin_active( 'woocommerce/woocommerce.php' ),
			'IS_EDD_ACTIVE'           => is_plugin_active( 'easy-digital-downloads/easy-digital-downloads.php' ),
			'IS_MULTISITE'            => is_multisite(),
			'IS_SUBDOMAIN_INSTALL'    => defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL,
			'IS_SUBDIRECTORY_INSTALL' => defined( 'SUBDOMAIN_INSTALL' ) && ! SUBDOMAIN_INSTALL,
			'IS_ELEMENTOR_ACTIVE'     => did_action( 'elementor/loaded' ) > 0,

			// Feature toggle states.
			'IS_404_ENABLED'          => function_exists( 'seopress_get_toggle_option' ) && '1' === seopress_get_toggle_option( '404' ),
			'IS_RICH_SNIPPETS_ENABLED' => function_exists( 'seopress_get_toggle_option' ) && '1' === seopress_get_toggle_option( 'rich-snippets' ),
			'IS_BOT_ENABLED'          => function_exists( 'seopress_get_toggle_option' ) && '1' === seopress_get_toggle_option( 'bot' ),

			// URLs.
			'ADMIN_URL'               => admin_url(),
			'AJAX_URL'                => admin_url( 'admin-ajax.php' ),
			'SCHEMAS_URL'             => admin_url( 'edit.php?post_type=seopress_schemas' ),
			'REDIRECTS_URL'           => admin_url( 'edit.php?post_type=seopress_404' ),
			'SITE_URL'                => get_home_url(),
			'WIDGETS_URL'             => admin_url( 'widgets.php' ),

			// Nonces.
			'NONCES'                  => array(
				'htaccess'       => wp_create_nonce( 'seopress_save_htaccess_nonce' ),
				'pagespeed'      => wp_create_nonce( 'seopress_pagespeed_nonce' ),
				'pagespeed_run'  => wp_create_nonce( 'seopress_request_page_speed_nonce' ),
				'pagespeed_clear' => wp_create_nonce( 'seopress_clear_page_speed_cache_nonce' ),
				'gsc'            => wp_create_nonce( 'seopress_gsc_nonce' ),
				'gsc_bot'        => wp_create_nonce( 'seopress_nonce_search_console' ),
				'robots'         => wp_create_nonce( 'seopress_robots_nonce' ),
				'ai_check'      => wp_create_nonce( 'seopress_ai_check_license_key_nonce' ),
				'ga_oauth'       => wp_create_nonce( 'seopress_ga_oauth_nonce' ),
			),

			// Data for tabs.
			'POST_TYPES'              => $pt_list,
			'TAXONOMIES'              => $tax_list,
			'LOCAL_BUSINESS_TYPES'    => $local_business_types,
			'OPENING_HOURS_DAYS'     => class_exists( 'SEOPress\Helpers\OpeningHoursHelper' )
				? \SEOPress\Helpers\OpeningHoursHelper::getDays()
				: array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ),
			'DOCS_LINKS'              => $docs_links,

			// Pre-loaded content.
			'ROBOTS_FILE_CONTENT'     => isset( $pro_options['seopress_robots_file'] ) ? $pro_options['seopress_robots_file'] : '',
			'HTACCESS_FILE_CONTENT'   => $htaccess_content,
			'HTACCESS_STATUS'         => $htaccess_status,

			// PageSpeed Insights cached results.
			'PAGESPEED_MOBILE'        => get_transient( 'seopress_results_page_speed' ) ? json_decode( get_transient( 'seopress_results_page_speed' ), true ) : null,
			'PAGESPEED_DESKTOP'       => get_transient( 'seopress_results_page_speed_desktop' ) ? json_decode( get_transient( 'seopress_results_page_speed_desktop' ), true ) : null,

			// Google Search Console.
			'GSC_BATCH_SIZE'          => apply_filters( 'seopress_search_console_batch_process', 20 ),

			// AI Logs.
			'AI_LOGS'                 => get_transient( 'seopress_pro_ai_logs' ) ? json_decode( get_transient( 'seopress_pro_ai_logs' ), true ) : null,
			// AI API key constants defined in wp-config.php.
			'AI_CONSTANTS'            => array(
				'openai'  => defined( 'SEOPRESS_OPENAI_KEY' ),
				'deepseek' => defined( 'SEOPRESS_DEEPSEEK_KEY' ),
				'gemini'  => defined( 'SEOPRESS_GEMINI_KEY' ),
				'mistral' => defined( 'SEOPRESS_MISTRAL_KEY' ),
				'claude'  => defined( 'SEOPRESS_CLAUDE_KEY' ),
			),
			'AI_KEY_HINTS'            => call_user_func( function () {
				$hints     = array();
				$providers = array(
					'openai'  => 'SEOPRESS_OPENAI_KEY',
					'deepseek' => 'SEOPRESS_DEEPSEEK_KEY',
					'gemini'  => 'SEOPRESS_GEMINI_KEY',
					'mistral' => 'SEOPRESS_MISTRAL_KEY',
					'claude'  => 'SEOPRESS_CLAUDE_KEY',
				);
				$options   = get_option( 'seopress_pro_option_name' );

				foreach ( $providers as $key => $constant ) {
					$raw = '';
					if ( defined( $constant ) && ! empty( constant( $constant ) ) ) {
						$raw = constant( $constant );
					} elseif ( ! empty( $options[ 'seopress_ai_' . $key . '_api_key' ] ) ) {
						$raw = $options[ 'seopress_ai_' . $key . '_api_key' ];
					}

					if ( ! empty( $raw ) && strlen( $raw ) > 4 ) {
						$hints[ $key ] = '••••...' . substr( $raw, -4 );
					} elseif ( ! empty( $raw ) ) {
						$hints[ $key ] = str_repeat( '•', strlen( $raw ) );
					} else {
						$hints[ $key ] = '';
					}
				}

				return $hints;
			} ),
		) );
	}

	// Localize data on the Network Admin page.
	if ( 'seopress-network-option' === $matched_slug ) {
		// Read .htaccess content and determine status.
		$htaccess_content = '';
		$htaccess_status  = 'ok';
		$htaccess_path    = ABSPATH . '.htaccess';

		if ( ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT === true )
			|| ( defined( 'SEOPRESS_BLOCK_HTACCESS' ) && SEOPRESS_BLOCK_HTACCESS === true ) ) {
			$htaccess_status = 'blocked';
		} elseif ( isset( $_SERVER['SERVER_SOFTWARE'] ) && 0 === stripos( $_SERVER['SERVER_SOFTWARE'], 'nginx' ) ) {
			$htaccess_status = 'nginx';
		} elseif ( ! file_exists( $htaccess_path ) || ! is_writable( $htaccess_path ) ) {
			$htaccess_status = 'not_writable';
		} else {
			$htaccess_content = file_get_contents( $htaccess_path ); // phpcs:ignore
		}

		$docs_links = function_exists( 'seopress_get_docs_links' ) ? seopress_get_docs_links() : array();

		wp_localize_script( 'seopress-pro-admin-settings', 'SEOPRESS_PRO_DATA', array(
			'IS_MULTISITE'            => is_multisite(),
			'IS_NETWORK_ADMIN'        => is_network_admin(),
			'IS_SUBDOMAIN_INSTALL'    => defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL,
			'IS_SUBDIRECTORY_INSTALL' => defined( 'SUBDOMAIN_INSTALL' ) && ! SUBDOMAIN_INSTALL,

			'ADMIN_URL'               => admin_url(),
			'AJAX_URL'                => admin_url( 'admin-ajax.php' ),
			'SITE_URL'                => get_home_url(),

			'NONCES'                  => array(
				'htaccess' => wp_create_nonce( 'seopress_save_htaccess_nonce' ),
			),

			'DOCS_LINKS'              => $docs_links,
			'HTACCESS_FILE_CONTENT'   => $htaccess_content,
			'HTACCESS_STATUS'         => $htaccess_status,
			'ROBOTS_FILE_CONTENT'     => '',

			'PAGE_TYPE'               => 'network-admin',
		) );
	}
}
add_action( 'admin_enqueue_scripts', 'seopress_pro_enqueue_react_settings', 20, 1 );

/**
 * Display SEOPress PRO notices.
 *
 * @return void
 */
function seopress_pro_admin_notices() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! is_plugin_active( 'wp-seopress/seopress.php' ) ) {
		?>
		<div class="notice error">
			<p>
				<?php echo wp_kses_post( __( 'Please enable <strong>SEOPress</strong> in order to use SEOPRESS PRO.', 'wp-seopress-pro' ) ); ?>
				<a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wp-seopress&TB_iframe=true&width=600&height=550' ) ); ?>" class="thickbox btn btnPrimary" target="_blank">
					<?php esc_html_e( 'Enable / Download now!', 'wp-seopress-pro' ); ?>
				</a>
			</p>
		</div>
		<?php
	} else {
		if ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ) {
			return;
		}

		/**
		 * Display a message if license key is not activated to receive automatic updates
		 *
		 * @return void
		 */
		if ( 'valid' !== get_option( 'seopress_pro_license_status' ) && ! is_multisite() ) {
			$screen_id = get_current_screen();
			if ( ( 'seopress-option' === $screen_id->parent_base && 'seo_page_seopress-license' !== $screen_id->base ) && ( isset( $_GET['page'] ) && 'seopress-setup' !== $_GET['page'] ) ) {
				$docs = seopress_get_docs_links();

				$class = 'seopress-notice is-error';

				$message = '<p><strong>' . esc_html__( 'Welcome to SEOPress PRO!', 'wp-seopress-pro' ) . '</strong></p>';

				$message .= '<p>' . esc_html__( 'Please activate your license to receive automatic updates and get premium support.', 'wp-seopress-pro' ) . '</p>';

				$message .= '<p><a class="btn btnPrimary" href="' . esc_url( admin_url( 'admin.php?page=seopress-license' ) ) . '">' . esc_html__( 'Activate License', 'wp-seopress-pro' ) . '</a></p>';

				printf( '<div class="%1$s">%2$s</div>', esc_attr( $class ), wp_kses_post( $message ) );
			}
		}
	}
}
add_action( 'admin_notices', 'seopress_pro_admin_notices' );

/**
 * Add plugin action links
 *
 * @param array  $links The plugin action links.
 * @param string $file The plugin file.
 * @return array The plugin action links.
 */
function seopress_pro_plugin_action_links( $links, $file ) {
	static $this_plugin;

	if ( ! $this_plugin ) {
		$this_plugin = plugin_basename( __FILE__ );
	}

	if ( $file === $this_plugin ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=seopress-pro-page' ) ) . '">' . esc_html__( 'Settings', 'wp-seopress-pro' ) . '</a>';
		$website_link  = '<a href="https://www.seopress.org/support/" target="_blank">' . esc_html__( 'Support', 'wp-seopress-pro' ) . '</a>';

		// Combine license link logic to reduce function calls.
		$license_status = get_option( 'seopress_pro_license_status' );
		$license_link   = '';

		if ( ! is_multisite() ) {
			$license_link  = '<a href="' . esc_url( admin_url( 'admin.php?page=seopress-license' ) ) . '">';
			$license_link .= ( 'valid' !== $license_status ) ? '<span style="color:red;font-weight:bold">' . esc_html__( 'Activate your license', 'wp-seopress-pro' ) . '</span>' : esc_html__( 'License', 'wp-seopress-pro' );
			$license_link .= '</a>';
		}

		// Simplify condition checks.
		$is_white_label = is_plugin_active( 'wp-seopress-pro/seopress-pro.php' ) &&
						method_exists( seopress_get_service( 'ToggleOption' ), 'getToggleWhiteLabel' ) &&
						'1' === seopress_get_service( 'ToggleOption' )->getToggleWhiteLabel() &&
						'1' === seopress_pro_get_service( 'OptionPro' )->getWhiteLabelHelpLinks();

		if ( $is_white_label ) {
			array_unshift( $links, $settings_link );
		} else {
			array_unshift( $links, $settings_link, $website_link, $license_link );
		}
	}

	return $links;
}
add_filter( 'plugin_action_links', 'seopress_pro_plugin_action_links', 10, 2 );

/**
 * SEOPress PRO Updater
 *
 * @return void
 */
if ( ! class_exists( 'SEOPRESS_Updater' ) ) {
	// Load our custom updater.
	require_once plugin_dir_path( __FILE__ ) . 'inc/admin/updater/plugin-updater.php';
	require_once plugin_dir_path( __FILE__ ) . 'inc/admin/updater/plugin-upgrader.php';
}

/**
 * SEOPress PRO Updater.
 *
 * @return void
 */
function SEOPRESS_Updater() { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	// To support auto-updates, this needs to run during the wp_version_check cron job for privileged users.
	// Also allow WP-CLI context so `wp plugin update` works without --user flag.
	$doing_cron = defined( 'DOING_CRON' ) && DOING_CRON;
	$doing_cli  = defined( 'WP_CLI' ) && WP_CLI;
	if ( ! current_user_can( 'manage_options' ) && ! $doing_cron && ! $doing_cli ) {
		return;
	}

	// Retrieve our license key from the DB.
	$license_key = defined( 'SEOPRESS_LICENSE_KEY' ) && ! empty( SEOPRESS_LICENSE_KEY ) && is_string( SEOPRESS_LICENSE_KEY ) ? SEOPRESS_LICENSE_KEY : trim( (string) get_option( 'seopress_pro_license_key' ) );

	// Setup the updater.
	$edd_updater = new SEOPRESS_Updater(
		STORE_URL_SEOPRESS,
		__FILE__,
		array(
			'version' => SEOPRESS_PRO_VERSION,
			'license' => $license_key,
			'item_id' => ITEM_ID_SEOPRESS,
			'author'  => SEOPRESS_PRO_AUTHOR,
			'url'     => esc_url( get_option( 'home' ) ),
			'beta'    => false,
		)
	);
}
add_action( 'init', 'SEOPRESS_Updater', 0 );

/**
 * Highlight Current menu when Editing Post Type.
 *
 * @param string $current_menu The current menu.
 * @return string
 */
function seopress_submenu_current( $current_menu ) {
	global $pagenow, $typenow;
	if ( 'post-new.php' === $pagenow || 'post.php' === $pagenow ) {
		if ( 'seopress_404' === $typenow || 'seopress_bot' === $typenow || 'seopress_backlinks' === $typenow || 'seopress_schemas' === $typenow ) {
			global $plugin_page;
			$plugin_page = 'seopress-option';
		}
	}

	return $current_menu;
}
add_filter( 'parent_file', 'seopress_submenu_current' );