<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_Quiz_Install {

	public static function activate() : void {
		WSH_Quiz_Post_Type::register();
		WSH_Quiz_Results::create_table();
		WSH_Quiz_Pages::ensure_pages();
		flush_rewrite_rules();
		update_option( 'wsh_quiz_version', WSH_QUIZ_VERSION );
	}

	public static function deactivate() : void {
		flush_rewrite_rules();
	}

	/**
	 * Create missing tables when the plugin was already active before this version.
	 */
	public static function maybe_upgrade() : void {
		$stored = (string) get_option( 'wsh_quiz_version', '' );
		if ( $stored === WSH_QUIZ_VERSION ) {
			return;
		}

		WSH_Quiz_Results::create_table();
		WSH_Quiz_Pages::ensure_pages();
		update_option( 'wsh_quiz_flush_rewrite', 1 );
		update_option( 'wsh_quiz_version', WSH_QUIZ_VERSION );

		if ( function_exists( 'do_action' ) ) {
			do_action( 'litespeed_purge_all' );
		}
	}
}
