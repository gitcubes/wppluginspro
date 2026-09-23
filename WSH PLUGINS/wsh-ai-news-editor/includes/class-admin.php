<?php
if (! defined('ABSPATH')) {
	exit;
}

class WSH_AINE_Admin
{

	public static function init(): void
	{
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
	}

	public static function enqueue(string $hook): void
	{
		if (strpos($hook, 'wsh-ai-news-editor') === false) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'wsh-aine-admin',
			WSH_AINE_URL . 'assets/css/admin.css?v=' . time(),
			array(),
			WSH_AINE_VERSION
		);

		wp_enqueue_style(
			'wsh-aine-live-score',
			WSH_AINE_URL . 'assets/css/live-score.css',
			array( 'wsh-aine-admin' ),
			WSH_AINE_VERSION
		);

		wp_enqueue_script(
			'wsh-aine-admin',
			WSH_AINE_URL . 'assets/js/admin.js?v=' . time(),
			array('jquery'),
			WSH_AINE_VERSION,
			true
		);

		wp_localize_script(
			'wsh-aine-admin',
			'wshAineAdmin',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce'   => wp_create_nonce('wsh_aine_generate_ai'),
				'strings' => array(
					'selectImage' => __('Select Image', 'wsh-ai-news-editor'),
					'useImage'    => __('Use this image', 'wsh-ai-news-editor'),
				),
			)
		);

		wp_enqueue_script(
			'wsh-aine-twitter-widgets-admin',
			'https://platform.twitter.com/widgets.js',
			array(),
			null,
			true
		);
	}
}
