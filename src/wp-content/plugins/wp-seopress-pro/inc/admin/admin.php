<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Admin.
 *
 * @package Admin
 */

defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

/**
 * SEOPress PRO Options.
 *
 * @package SEOPress PRO
 * @subpackage Admin
 */
class seopress_pro_options {

	/**
	 * Holds the values to be used in the fields callbacks.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Start up.
	 */
	public function __construct() {
		// License activation / deactivation / automatic activation.
		require_once __DIR__ . '/callbacks/License.php';

		// Google Analytics OAuth handling (code exchange, logout, AJAX status).
		require_once __DIR__ . '/callbacks/GoogleAnalyticsOAuth.php';

		add_action( 'admin_menu', array( $this, 'add_plugin_page' ), 20 );
		add_action( 'admin_init', array( $this, 'pro_set_default_values' ), 10 );
		add_action( 'network_admin_menu', array( $this, 'add_network_plugin_page' ), 10 );
		add_action( 'admin_init', array( $this, 'load_sections' ), 5 );
		add_action( 'admin_init', array( $this, 'load_callbacks' ), 5 );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'admin_init', array( $this, 'metabox_init' ) );

		add_action( 'admin_init', array( $this, 'feature_save' ), 30 );
		add_action( 'admin_init', array( $this, 'feature_title' ), 20 );
		add_action( 'admin_init', array( $this, 'pre_save_options' ), 50 );
	}

	/**
	 * Feature save.
	 *
	 * @return string
	 */
	public function feature_save() {
		$html = '';
		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
			$html .= '<div id="seopress-notice-save" class="sp-components-snackbar-list">';
		} else {
			$html .= '<div id="seopress-notice-save" class="sp-components-snackbar-list" style="display:none">';
		}
		$html .= '<div class="sp-components-snackbar">
                <div class="sp-components-snackbar__content">
                    <span class="dashicons dashicons-yes"></span>
                    ' . __( 'Your settings have been saved.', 'wp-seopress-pro' ) . '
                </div>
            </div>
        </div>';

		return $html;
	}

	/**
	 * Feature title.
	 *
	 * @param string $feature The feature.
	 * @return string
	 */
	public function feature_title( $feature ) {
		global $title;

		$html = '<h1>' . $title;

		if ( null !== $feature ) {
			if ( '1' == seopress_get_toggle_option( $feature ) ) {
				$toggle = '"1"';
			} else {
				$toggle = '"0"';
			}

			$html .= '<input type="checkbox" name="toggle-' . $feature . '" id="toggle-' . $feature . '" class="toggle" data-toggle=' . $toggle . '>';
			$html .= '<label for="toggle-' . $feature . '"></label>';

			$html .= $this->feature_save();

			if ( '1' == seopress_get_toggle_option( $feature ) ) {
				$html .= '<span id="titles-state-default" class="feature-state"><span class="dashicons dashicons-arrow-left-alt"></span>' . __( 'Click to disable this feature', 'wp-seopress-pro' ) . '</span>';
				$html .= '<span id="titles-state" class="feature-state feature-state-off"><span class="dashicons dashicons-arrow-left-alt"></span>' . __( 'Click to enable this feature', 'wp-seopress-pro' ) . '</span>';
			} else {
				$html .= '<span id="titles-state-default" class="feature-state"><span class="dashicons dashicons-arrow-left-alt"></span>' . __( 'Click to enable this feature', 'wp-seopress-pro' ) . '</span>';
				$html .= '<span id="titles-state" class="feature-state feature-state-off"><span class="dashicons dashicons-arrow-left-alt"></span>' . __( 'Click to disable this feature', 'wp-seopress-pro' ) . '</span>';
			}
		}

		$html .= '</h1>';

		return $html;
	}

	/**
	 * Set default values.
	 *
	 * @return void
	 */
	public function pro_set_default_values() {
		if ( defined( 'SEOPRESS_WPMAIN_VERSION' ) ) {
			return;
		}

		$seopress_pro_option_name = get_option( 'seopress_pro_option_name', array() );

		// WooCommerce.
		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$seopress_pro_option_name['seopress_woocommerce_cart_page_no_index']             = '1';
			$seopress_pro_option_name['seopress_woocommerce_checkout_page_no_index']         = '1';
			$seopress_pro_option_name['seopress_woocommerce_customer_account_page_no_index'] = '1';
			$seopress_pro_option_name['seopress_woocommerce_product_og_price']               = '1';
			$seopress_pro_option_name['seopress_woocommerce_product_og_currency']            = '1';
			$seopress_pro_option_name['seopress_woocommerce_meta_generator']                 = '1';
		}

		// DublinCore.
		$seopress_pro_option_name['seopress_dublin_core_enable'] = '1';

		// 404.
		$seopress_pro_option_name['seopress_404_cleaning'] = '1';

		// Check if the value is an array (important!).
		if ( is_array( $seopress_pro_option_name ) ) {
			add_option( 'seopress_pro_option_name', $seopress_pro_option_name );
		}

		// BOT.
		$seopress_bot_option_name = get_option( 'seopress_bot_option_name', array() );

		$seopress_bot_option_name['seopress_bot_scan_settings_post_types']['post']['include'] = '1';
		$seopress_bot_option_name['seopress_bot_scan_settings_post_types']['page']['include'] = '1';
		$seopress_bot_option_name['seopress_bot_scan_settings_404']                           = '1';

		// Check if the value is an array (important!).
		if ( is_array( $seopress_bot_option_name ) ) {
			add_option( 'seopress_bot_option_name', $seopress_bot_option_name );
		}
	}

	/**
	 * Add options page.
	 */
	public function add_network_plugin_page() {
		if ( has_filter( 'seopress_seo_admin_menu' ) ) {
			$sp_seo_admin_menu['icon'] = '';
			$sp_seo_admin_menu['icon'] = apply_filters( 'seopress_seo_admin_menu', $sp_seo_admin_menu['icon'] );
		} else {
			$sp_seo_admin_menu['icon'] = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48c3ZnIGlkPSJ1dWlkLTRmNmE4YTQxLTE4ZTMtNGY3Ny1iNWE5LTRiMWIzOGFhMmRjOSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB2aWV3Qm94PSIwIDAgODk5LjY1NSA0OTQuMzA5NCI+PHBhdGggaWQ9InV1aWQtYTE1NWMxY2EtZDg2OC00NjUzLTg0NzctOGRkODcyNDBhNzY1IiBkPSJNMzI3LjM4NDksNDM1LjEyOGwtMjk5Ljk5OTktLjI0OTdjLTE2LjI3MzUsMS4xOTM3LTI4LjQ5ODEsMTUuMzUzOC0yNy4zMDQ0LDMxLjYyNzMsMS4wNzE5LDE0LjYxMjgsMTIuNjkxNiwyNi4yMzI1LDI3LjMwNDQsMjcuMzA0NGwyOTkuOTk5OSwuMjQ5N2MxNi4yNzM1LTEuMTkzNywyOC40OTgxLTE1LjM1MzgsMjcuMzA0NC0zMS42MjczLTEuMDcxOC0xNC42MTI4LTEyLjY5MTYtMjYuMjMyNS0yNy4zMDQ0LTI3LjMwNDRaIiBzdHlsZT0iZmlsbDojZmZmOyIvPjxwYXRoIGlkPSJ1dWlkLWUzMGJhNGM2LTQ3NjktNDY2Yi1hMDNhLWU2NDRjNTE5OGU1NiIgZD0iTTI3LjM4NDksNTguOTMxN2wyOTkuOTk5OSwuMjQ5N2MxNi4yNzM1LTEuMTkzNywyOC40OTgxLTE1LjM1MzcsMjcuMzA0NC0zMS42MjczLTEuMDcxOC0xNC42MTI4LTEyLjY5MTYtMjYuMjMyNS0yNy4zMDQ0LTI3LjMwNDRMMjcuMzg0OSwwQzExLjExMTQsMS4xOTM3LTEuMTEzMiwxNS4zNTM3LC4wODA1LDMxLjYyNzNjMS4wNzE5LDE0LjYxMjgsMTIuNjkxNiwyNi4yMzI1LDI3LjMwNDQsMjcuMzA0NFoiIHN0eWxlPSJmaWxsOiNmZmY7Ii8+PHBhdGggaWQ9InV1aWQtMmJiZDUyZDYtYWVjMS00Njg5LTlkNGMtMjNjMzVkNGYyMmI4IiBkPSJNNjUyLjQ4NSwuMjg0OWMtMTI0LjkzODgsLjA2NC0yMzAuMTU1NCw5My40MTMyLTI0NS4xMDAxLDIxNy40NTVIMjcuMzg0OWMtMTYuMjczNSwxLjE5MzctMjguNDk4MSwxNS4zNTM3LTI3LjMwNDQsMzEuNjI3MiwxLjA3MTksMTQuNjEyOCwxMi42OTE2LDI2LjIzMjUsMjcuMzA0NCwyNy4zMDQ0SDQwNy4zODQ5YzE2LjIyOTgsMTM1LjQ0NTQsMTM5LjE4NywyMzIuMDg4OCwyNzQuNjMyMywyMTUuODU4OSwxMzUuNDQ1NS0xNi4yMjk4LDIzMi4wODg4LTEzOS4xODY5LDIxNS44NTg5LTI3NC42MzI0Qzg4Mi45OTIxLDkzLjY4MzQsNzc3LjU4ODQsLjIxMTIsNjUyLjQ4NSwuMjg0OVptMCw0MzMuNDIxN2MtMTAyLjk3NTQsMC0xODYuNDUzMy04My40NzgtMTg2LjQ1MzMtMTg2LjQ1MzMsMC0xMDIuOTc1Myw4My40NzgxLTE4Ni40NTMzLDE4Ni40NTMzLTE4Ni40NTMzLDEwMi45NzU0LDAsMTg2LjQ1MzMsODMuNDc4LDE4Ni40NTMzLDE4Ni40NTMzLC4wNTI0LDEwMi45NzUzLTgzLjM4MywxODYuNDk1OS0xODYuMzU4MywxODYuNTQ4My0uMDMxNiwwLS4wNjM0LDAtLjA5NTEsMHYtLjA5NVoiIHN0eWxlPSJmaWxsOiNmZmY7Ii8+PC9zdmc+';
		}

		$sp_seo_admin_menu['title'] = __( 'SEO', 'wp-seopress-pro' );
		if ( has_filter( 'seopress_seo_admin_menu_title' ) ) {
			$sp_seo_admin_menu['title'] = apply_filters( 'seopress_seo_admin_menu_title', $sp_seo_admin_menu['title'] );
		}

		add_menu_page( __( 'SEO Network settings', 'wp-seopress-pro' ), $sp_seo_admin_menu['title'], seopress_capability( 'manage_options', 'menu' ), 'seopress-network-option', array( $this, 'create_network_admin_page' ), $sp_seo_admin_menu['icon'], 90 );
	}

	/**
	 * Add plugin page.
	 *
	 * @return void
	 */
	public function add_plugin_page() {
		add_submenu_page( 'seopress-option', __( 'PRO', 'wp-seopress-pro' ), __( 'PRO', 'wp-seopress-pro' ), seopress_capability( 'manage_options', 'pro' ), 'seopress-pro-page', array( $this, 'seopress_pro_page' ) );
		if ( '1' == seopress_get_toggle_option( 'rich-snippets' ) ) {
			add_submenu_page( 'seopress-option', __( 'Schemas', 'wp-seopress-pro' ), __( 'Schemas', 'wp-seopress-pro' ), seopress_capability( 'edit_schemas', 'menu' ), 'edit.php?post_type=seopress_schemas', null );
		}
		if ( '1' == seopress_get_toggle_option( '404' ) ) {
			add_submenu_page( 'seopress-option', __( 'Redirections', 'wp-seopress-pro' ), __( 'Redirections', 'wp-seopress-pro' ), seopress_capability( 'edit_redirections', 'menu' ), 'edit.php?post_type=seopress_404', null );
		}
		if ( '1' == seopress_get_toggle_option( 'bot' ) ) {
			add_submenu_page( 'seopress-option', __( 'Broken links', 'wp-seopress-pro' ), __( 'Broken links', 'wp-seopress-pro' ), seopress_capability( 'edit_broken_links', 'menu' ), 'edit.php?post_type=seopress_bot', null );
		}
		add_submenu_page( 'seopress-option', __( 'License', 'wp-seopress-pro' ), __( 'License', 'wp-seopress-pro' ), seopress_capability( 'manage_options', 'menu' ), 'seopress-license', array( $this, 'seopress_license_page' ) );
	}

	/**
	 * SEOPress PRO page.
	 *
	 * @return void
	 */
	public function seopress_pro_page() {
		require_once __DIR__ . '/admin-pages/Pro.php';
	}

	/**
	 * SEOPress license page.
	 *
	 * @return void
	 */
	public function seopress_license_page() {
		require_once __DIR__ . '/admin-pages/License.php';
	}

	/**
	 * Create network admin page.
	 *
	 * @return void
	 */
	public function create_network_admin_page() {
		require_once __DIR__ . '/admin-pages/NetworkAdmin.php';
	}

	/**
	 * Page init.
	 *
	 * @return void
	 */
	public function page_init() {
		require_once __DIR__ . '/settings/Main.php';
		require_once __DIR__ . '/settings/Bot.php';
		require_once __DIR__ . '/blocks/features-list.php';
		if ( version_compare( SEOPRESS_VERSION, '6.3.1', '>' ) || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG === true ) ) {
			require_once __DIR__ . '/blocks/tasks.php';
		}
		require_once __DIR__ . '/blocks/insights.php';
		require_once __DIR__ . '/wizard/wizard.php';
		require_once __DIR__ . '/admin-pages/Tools.php';
	}

	/**
	 * Metabox init.
	 *
	 * @return void
	 */
	public function metabox_init() {
		require_once __DIR__ . '/metaboxes/admin-metaboxes-form.php';
		require_once __DIR__ . '/metaboxes/admin-content-analysis-metaboxes-form.php';
	}

	/**
	 * Sanitize.
	 *
	 * @param array $input The input.
	 * @return array
	 */
	public function sanitize( $input ) {
		require_once __DIR__ . '/sanitize/Sanitize.php';

		return seopress_pro_sanitize_options_fields( $input );
	}

	/**
	 * Load sections.
	 *
	 * @return void
	 */
	public function load_sections() {
		require_once __DIR__ . '/ui-components.php';
		require_once __DIR__ . '/sections/Pro.php';
		require_once __DIR__ . '/sections/Bot.php';
	}

	/**
	 * Load callbacks.
	 *
	 * @return void
	 */
	public function load_callbacks() {
		require_once __DIR__ . '/callbacks/Bot.php';
	}

	/**
	 * Pre save options.
	 *
	 * @return void
	 */
	public function pre_save_options() {
		add_filter( 'pre_update_option_seopress_pro_option_name', array( $this, 'pre_seopress_pro_option_name' ), 10, 2 );
	}

	/**
	 * Pre seopress pro option name.
	 *
	 * @param array $new_value The new value.
	 * @param array $old_value The old value.
	 * @return array
	 */
	public function pre_seopress_pro_option_name( $new_value, $old_value ) {
		flush_rewrite_rules( false );
		return $new_value;
	}
}

if ( is_admin() ) {
	$my_settings_page = new seopress_pro_options();
}
