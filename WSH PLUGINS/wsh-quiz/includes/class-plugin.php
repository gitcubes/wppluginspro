<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_Quiz_Plugin {

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
		add_action( 'plugins_loaded', array( 'WSH_Quiz_Install', 'maybe_upgrade' ) );
		add_action( 'widgets_init', array( 'WSH_Quiz_Widget', 'register' ) );
		add_action( 'init', array( $this, 'init_components' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite' ), 30 );
	}

	public function load_textdomain() : void {
		load_plugin_textdomain(
			'wsh-quiz',
			false,
			dirname( WSH_QUIZ_BASENAME ) . '/languages'
		);
	}

	public function init_components() : void {
		WSH_Quiz_Post_Type::init();
		WSH_Quiz_Pages::init();
		WSH_Quiz_Settings::init();
		WSH_Quiz_License::init();
		WSH_Quiz_Admin::init();
		WSH_Quiz_Metabox::init();
		WSH_Quiz_Assets::init();
		WSH_Quiz_Ajax::init();
		WSH_Quiz_Shortcode::init();
		WSH_Quiz_Block::init();
		WSH_Quiz_Frontend::init();
	}

	public function maybe_flush_rewrite() : void {
		if ( ! get_option( 'wsh_quiz_flush_rewrite' ) ) {
			return;
		}

		flush_rewrite_rules( false );
		delete_option( 'wsh_quiz_flush_rewrite' );
	}
}
