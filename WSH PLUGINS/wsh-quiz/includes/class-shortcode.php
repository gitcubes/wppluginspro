<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_Quiz_Shortcode {

	public static function init() : void {
		add_shortcode( 'wsh_quiz', array( __CLASS__, 'render' ) );
		add_shortcode( 'wsh_quiz_list', array( __CLASS__, 'render_list' ) );
		add_shortcode( 'wsh_quiz_leaderboard', array( __CLASS__, 'render_leaderboard' ) );
	}

	public static function render( $atts ) : string {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'wsh_quiz'
		);

		return WSH_Quiz_Frontend::render( (int) $atts['id'] );
	}

	public static function render_list( $atts ) : string {
		if ( WSH_Quiz_Pages::is_quiz_page() && 'list' !== WSH_Quiz_Pages::current_view() ) {
			return WSH_Quiz_Pages::render_current_view();
		}

		$atts = shortcode_atts(
			array(
				'limit' => 20,
			),
			$atts,
			'wsh_quiz_list'
		);

		return WSH_Quiz_Frontend::render_list( (int) $atts['limit'] );
	}

	public static function render_leaderboard( $atts ) : string {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'limit' => 10,
			),
			$atts,
			'wsh_quiz_leaderboard'
		);

		return WSH_Quiz_Frontend::render_leaderboard( (int) $atts['id'], (int) $atts['limit'] );
	}
}
