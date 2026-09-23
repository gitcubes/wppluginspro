<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_Quiz_Block {

	public static function init() : void {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
	}

	public static function register() : void {
		register_block_type(
			'wsh-quiz/embed',
			array(
				'api_version'     => 2,
				'title'           => __( 'WSH Quiz', 'wsh-quiz' ),
				'description'     => __( 'Embed a quiz in this post.', 'wsh-quiz' ),
				'category'        => 'widgets',
				'icon'            => 'forms',
				'keywords'        => array( 'quiz', 'poll', 'game' ),
				'attributes'      => array(
					'quizId' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);
	}

	public static function render( array $attributes ) : string {
		$quiz_id = isset( $attributes['quizId'] ) ? (int) $attributes['quizId'] : 0;
		return WSH_Quiz_Frontend::render( $quiz_id );
	}
}
