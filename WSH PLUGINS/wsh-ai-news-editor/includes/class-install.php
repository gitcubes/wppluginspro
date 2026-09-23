<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Install {

	public static function activate() : void {
		if ( ! get_option( 'wsh_aine_settings' ) ) {
			add_option( 'wsh_aine_settings', array(
				'country'             => 'RS',
				'language'            => 'sr',
				'default_post_status' => 'draft',
				'default_author'      => get_current_user_id(),
				'openai_api_key'      => '',
				'openai_model'        => 'gpt-4.1-mini',
				'openai_temperature'  => '0.7',
				'local_sources'       => array(),
				'google_sources'      => array(),
			) );
		}
	}

	public static function deactivate() : void {
		// Za sada ništa.
	}
}