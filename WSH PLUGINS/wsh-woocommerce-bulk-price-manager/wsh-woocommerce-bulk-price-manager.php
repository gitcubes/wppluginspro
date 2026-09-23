<?php

/**
 * Plugin Name: WSH WooCommerce Bulk Price Manager
 * Plugin URI:  https://wppluginspro.io/
 * Description: Bulk/mass update WooCommerce product prices with Preview (Dry Run) and Undo. Supports simple + variable products.
 * Version:     1.0.0
 * Author:      Web Solutions Hub LLC
 * Author URI:  https://wppluginspro.io/
 * License:     GPLv2 or later
 * Text Domain: wsh-wcbpm
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
	exit;
}

// Define plugin version constant for assets/versioning
if ( ! defined( 'WSH_WCBPM_VERSION' ) ) {
	define( 'WSH_WCBPM_VERSION', WSH_WCBPM::VERSION );
}

// License server API base URL.
if ( ! defined( 'WSH_LICENSE_API_BASE' ) ) {
	define( 'WSH_LICENSE_API_BASE', 'https://wppluginspro.io/wp-json/wsh-license/v1' );
}

// Plugin slug as known by your license server.
if ( ! defined( 'WSH_WCBPM_PLUGIN_SLUG' ) ) {
	define( 'WSH_WCBPM_PLUGIN_SLUG', 'wsh-woocommerce-bulk-price-manager' );
}

final class WSH_WCBPM
{

	/** Plugin version */
	const VERSION = '1.0.0';

	/** Minimum requirements */
	const MIN_PHP = '7.4';

	/** Singleton instance */
	private static $instance = null;

	/** Plugin file */
	public $file;

	/** Plugin path */
	public $path;

	/** Plugin URL */
	public $url;

	/**
	 * Get instance.
	 *
	 * @return WSH_WCBPM
	 */
	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{
		$this->file = __FILE__;
		$this->path = plugin_dir_path(__FILE__);
		$this->url  = plugin_dir_url(__FILE__);

		// Boot.
		add_action('plugins_loaded', array($this, 'boot'), 5);

		// Activation / deactivation.
		register_activation_hook(__FILE__, array(__CLASS__, 'on_activate'));
		register_deactivation_hook(__FILE__, array(__CLASS__, 'on_deactivate'));
	}

	/**
	 * Boot plugin after other plugins load.
	 */
	public function boot()
	{

		// PHP requirement.
		if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
			add_action('admin_notices', array($this, 'notice_php_too_low'));
			return;
		}

		// Load translations.
		load_plugin_textdomain('wsh-wcbpm', false, dirname(plugin_basename(__FILE__)) . '/languages');

		// WooCommerce requirement.
		if (! $this->is_woocommerce_active()) {
			add_action('admin_notices', array($this, 'notice_woocommerce_missing'));
			return;
		}

		// Include core files.
		$this->includes();

		// Init modules.
		$this->init_modules();

		/**
		 * Hook point for PRO add-on (future):
		 * PRO plugin can hook into this to register extra modules safely.
		 */
		do_action('wsh_wcbpm_loaded');
	}

	/**
	 * Include required files (FREE core).
	 */
	private function includes() {

		//Free files
		$files = array(
			'includes/class-filter-engine.php',
			'includes/class-price-calculator.php',
			'includes/class-undo-manager.php',
			'includes/class-dry-run.php',
			'includes/class-csv-export.php', // kad ga uključiš
			'includes/class-admin-ui.php',
		);

		foreach ( $files as $rel ) {
			$full = trailingslashit( $this->path ) . $rel;
			if ( file_exists( $full ) ) {
				require_once $full;
			} else {
				// Debug samo: vidi u debug.log ako nešto fali
				error_log( 'WSH_WCBPM missing file: ' . $full );
			}
		}

		//Licence Class
		require_once __DIR__ . '/includes/class-wsh-wcbpm-license.php';
		WSH_WCBPM_License::init();

		
		// PRO files
		$pro_files = array(
			'includes/class-preset-manager.php',
			'includes/class-import-manager.php',
			'includes/class-scheduler.php',
		);

		if ( class_exists( 'WSH_WCBPM_License' ) && WSH_WCBPM_License::is_active() ) {
			foreach ( $pro_files as $rel ) {
				$full = trailingslashit( $this->path ) . $rel;
				if ( file_exists( $full ) ) {
					require_once $full;
				} else {
					// Debug samo: vidi u debug.log ako nešto fali
					error_log( 'WSH_WCBPM missing file: ' . $full );
				}
			}
		}
	}

	/**
	 * Init modules.
	 */
	private function init_modules()
	{

		// Defensive: if a file is missing, don't fatal.
		if (class_exists('WSH_WCBPM_Undo_Manager')) {
			WSH_WCBPM_Undo_Manager::init();
		}

		if (class_exists('WSH_WCBPM_CSV_Export')) {
			WSH_WCBPM_CSV_Export::init();
		}

		if (class_exists('WSH_WCBPM_Admin_UI')) {
			WSH_WCBPM_Admin_UI::init();
		}

		if (class_exists('WSH_WCBPM_Import_Manager')) {
			WSH_WCBPM_Import_Manager::init();
		}

		if (class_exists('WSH_WCBPM_Scheduler')) {
			WSH_WCBPM_Scheduler::init();
		}
	}

	/**
	 * Check whether WooCommerce is active.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active()
	{
		return class_exists('WooCommerce');
	}

	/**
	 * Activation tasks (create tables etc.).
	 */
	public static function on_activate()
	{

		// PHP check on activation too.
		if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die(
				esc_html__('WSH WooCommerce Bulk Price Manager requires a newer PHP version.', 'wsh-wcbpm')
			);
		}

		// Create Undo table for FREE "Undo last bulk update".
		self::create_undo_table();

		self::create_presets_table();

		// Mark installed version.
		update_option('wsh_wcbpm_version', self::VERSION, false);
	}

	/**
	 * Deactivation tasks.
	 */
	public static function on_deactivate()
	{
		// Nothing destructive here. (We keep data.)
	}

	/**
	 * Create Undo table.
	 *
	 * Stores only the last operation (single step rollback).
	 */
	public static function create_undo_table() {
		global $wpdb;

		$table = $wpdb->prefix . 'wsh_wcbpm_undo';
		$charset_collate = $wpdb->get_charset_collate();

		// 1) Kreiraj tabelu ako ne postoji (bez dbDelta).
		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			batch_id VARCHAR(64) NOT NULL,
			created_at DATETIME NOT NULL,
			created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			scope VARCHAR(32) NOT NULL DEFAULT 'both',
			items_count INT(11) NOT NULL DEFAULT 0,
			items LONGTEXT NOT NULL,
			PRIMARY KEY (id),
			KEY batch_id (batch_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$wpdb->query( $sql );

		// Ako CREATE nije uspeo, loguj grešku.
		if ( ! empty( $wpdb->last_error ) ) {
			error_log( 'WSH_WCBPM: create_undo_table CREATE error: ' . $wpdb->last_error );
			error_log( 'WSH_WCBPM: SQL was: ' . $sql );
			return;
		}

		// 2) Minimalna migracija: dodaj kolone ako fale (bez dbDelta).
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 );
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}

		$wanted = array(
			'batch_id'     => "ALTER TABLE {$table} ADD COLUMN batch_id VARCHAR(64) NOT NULL",
			'created_at'   => "ALTER TABLE {$table} ADD COLUMN created_at DATETIME NOT NULL",
			'created_by'   => "ALTER TABLE {$table} ADD COLUMN created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0",
			'scope'        => "ALTER TABLE {$table} ADD COLUMN scope VARCHAR(32) NOT NULL DEFAULT 'both'",
			'items_count'  => "ALTER TABLE {$table} ADD COLUMN items_count INT(11) NOT NULL DEFAULT 0",
			'items'        => "ALTER TABLE {$table} ADD COLUMN items LONGTEXT NOT NULL",
		);
		
		foreach ( $wanted as $col => $alter_sql ) {
			if ( ! in_array( $col, $columns, true ) ) {
				$wpdb->query( $alter_sql );
				if ( ! empty( $wpdb->last_error ) ) {
					error_log( "WSH_WCBPM: create_undo_table ALTER error for {$col}: " . $wpdb->last_error );
					error_log( 'WSH_WCBPM: ALTER SQL was: ' . $alter_sql );
				}
			}
		}
	}

	/**
	 * Create Presets table.
	 *
	 */
	public static function create_presets_table() {
		global $wpdb;

		$table = $wpdb->prefix . 'wsh_wcbpm_presets';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			description TEXT NULL,
			payload LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY created_at (created_at),
			KEY created_by (created_by)
		) {$charset_collate};";

		$wpdb->query($sql);

		if ( ! empty($wpdb->last_error) ) {
			error_log('WSH_WCBPM: create_presets_table error: ' . $wpdb->last_error);
		}
	}



	/**
	 * Admin notice: WooCommerce missing.
	 */
	public function notice_woocommerce_missing()
	{
		if (! current_user_can('activate_plugins')) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__('WSH WooCommerce Bulk Price Manager requires WooCommerce to be installed and active.', 'wsh-wcbpm');
		echo '</p></div>';
	}

	/**
	 * Admin notice: PHP too low.
	 */
	public function notice_php_too_low()
	{
		if (! current_user_can('activate_plugins')) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		printf(
			/* translators: 1: required PHP version, 2: current PHP version */
			esc_html__('WSH WooCommerce Bulk Price Manager requires PHP %1$s+ (you are running %2$s).', 'wsh-wcbpm'),
			esc_html(self::MIN_PHP),
			esc_html(PHP_VERSION)
		);
		echo '</p></div>';
	}
}

// Bootstrap.
WSH_WCBPM::instance();

/**
 * Helpful global accessor (optional).
 *
 * @return WSH_WCBPM
 */
function wsh_wcbpm()
{
	return WSH_WCBPM::instance();
}
