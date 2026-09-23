<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main loader for WSH Views Counter PRO.
 */
class WSH_VC_Pro_Loader {

	/**
	 * Init hook.
	 */
	public static function init() {

		// Always load license + admin menu.
		require_once WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-license.php';
		require_once WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-admin-menu.php';

		WSH_VC_Pro_License::init();
		WSH_VC_Pro_Admin_Menu::init();

		// PRO analytics modules (later) will require the free plugin.
		if ( ! class_exists( 'WSH_Views_Counter' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_free_required' ) );
			return;
		}

		// Example for future PRO features:
		// if ( WSH_VC_Pro_License::is_active() ) {
		//     require_once WSH_VC_PRO_PATH . 'includes/class-wsh-vc-pro-geo.php';
		//     WSH_VC_Pro_Geo::init();
		// }
	}

	/**
	 * Activation handler.
	 */
	public static function on_activation() {
		// Schedule daily license check, if not already.
		if ( ! wp_next_scheduled( 'wsh_vc_pro_license_daily_check' ) ) {
			wp_schedule_event( time() + 3600, 'daily', 'wsh_vc_pro_license_daily_check' );
		}
	}

	/**
	 * Deactivation handler.
	 */
	public static function on_deactivation() {
		// Unschedule daily license check.
		$timestamp = wp_next_scheduled( 'wsh_vc_pro_license_daily_check' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wsh_vc_pro_license_daily_check' );
		}
	}

	/**
	 * Notice: free plugin required for PRO analytics features.
	 */
	public static function notice_free_required() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<strong>WSH Views Counter PRO</strong> requires the free plugin
				<strong>WSH Views Counter</strong> to be installed and active for PRO analytics features.
				The license screen will still work, but advanced analytics will be disabled.
			</p>
		</div>
		<?php
	}
}

// Cron hook for license check.
add_action( 'wsh_vc_pro_license_daily_check', array( 'WSH_VC_Pro_License', 'cron_check_license' ) );
