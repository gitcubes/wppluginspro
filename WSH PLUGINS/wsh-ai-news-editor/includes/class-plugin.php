<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Plugin {

	protected static $instance = null;

	public static function instance() : self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_hooks();
	}

	private function init_hooks() : void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init_components' ) );
	}

	public function load_textdomain() : void {
		load_plugin_textdomain(
			'wsh-ai-news-editor',
			false,
			dirname( WSH_AINE_BASENAME ) . '/languages'
		);
	}

	public function init_components() : void {
		WSH_AINE_Admin::init();
		WSH_AINE_Menu::init();
		WSH_AINE_License::init();
		WSH_AINE_Standings_Shortcode::init();
		WSH_AINE_Round_Fixtures_Shortcode::init();
		WSH_AINE_Live_Score_Shortcode::init();
		WSH_AINE_License_Page::init();
		WSH_AINE_Settings_Page::init();
		WSH_AINE_Local_Media_Page::init();
		WSH_AINE_Google_News_Page::init();
		WSH_AINE_AI_Editor_Page::init();
		WSH_AINE_YouTube_News_Page::init();
		WSH_AINE_Twitter_News_Page::init();
		WSH_AINE_Grok_News_Page::init();
		WSH_AINE_Perplexity_News_Page::init();
		WSH_AINE_Sports_News_Page::init();
		WSH_AINE_Insta_News_Page::init();
		WSH_AINE_Social_Network_News_Page::init();
		WSH_AINE_AI_Post_Metabox::init();

	}
}