<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_Quiz_Metabox {

	public static function init() : void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'save_post_wsh_quiz', array( __CLASS__, 'save' ), 10, 2 );
	}

	public static function register() : void {
		add_meta_box(
			'wsh_quiz_settings',
			__( 'Quiz settings', 'wsh-quiz' ),
			array( __CLASS__, 'render_settings' ),
			'wsh_quiz',
			'normal',
			'high'
		);

		add_meta_box(
			'wsh_quiz_builder',
			__( 'Quiz builder', 'wsh-quiz' ),
			array( __CLASS__, 'render' ),
			'wsh_quiz',
			'normal',
			'high'
		);

		add_meta_box(
			'wsh_quiz_embed',
			__( 'Embed', 'wsh-quiz' ),
			array( __CLASS__, 'render_embed' ),
			'wsh_quiz',
			'side',
			'high'
		);

		add_meta_box(
			'wsh_quiz_actions',
			__( 'Quiz actions', 'wsh-quiz' ),
			array( __CLASS__, 'render_actions' ),
			'wsh_quiz',
			'side',
			'high'
		);
	}

	public static function render( WP_Post $post ) : void {
		wp_nonce_field( 'wsh_quiz_save', 'wsh_quiz_nonce' );

		$data         = wsh_quiz_get_data( (int) $post->ID );
		$is_pro       = WSH_Quiz_Access::is_pro();
		$types        = wsh_quiz_question_types();
		$upgrade      = WSH_Quiz_Access::get_upgrade_url();
		$has_games    = WSH_Quiz_Results::player_count( (int) $post->ID ) > 0;
		$site_locale  = WSH_Quiz_AI::site_locale();
		$ai_languages = WSH_Quiz_AI::languages();
		$ai_prompt    = WSH_Quiz_AI::default_prompt( $site_locale, (string) $post->post_title, (string) ( $data['description'] ?? '' ) );

		include WSH_QUIZ_PATH . 'templates/admin/metabox-builder.php';
	}

	public static function render_settings( WP_Post $post ) : void {
		$data   = wsh_quiz_get_data( (int) $post->ID );
		$is_pro = WSH_Quiz_Access::is_pro();

		include WSH_QUIZ_PATH . 'templates/admin/metabox-settings.php';
	}

	public static function render_actions( WP_Post $post ) : void {
		WSH_Quiz_Admin::render_actions( (int) $post->ID );
	}

	public static function render_embed( WP_Post $post ) : void {
		if ( $post->ID < 1 ) {
			echo '<p>' . esc_html__( 'Save the quiz first to get a shortcode.', 'wsh-quiz' ) . '</p>';
			return;
		}

		printf(
			'<p><a href="%1$s">%2$s</a></p><p><a href="%3$s">%4$s</a></p><p><code>[wsh_quiz id="%5$d"]</code></p><p class="description">%6$s</p>',
			esc_url( wsh_quiz_get_play_url( (int) $post->ID ) ),
			esc_html__( 'Public play page', 'wsh-quiz' ),
			esc_url( wsh_quiz_get_results_url( (int) $post->ID ) ),
			esc_html__( 'Public results page', 'wsh-quiz' ),
			(int) $post->ID,
			esc_html__( 'The plugin also creates a Quiz page with the current list.', 'wsh-quiz' )
		);
	}

	public static function save( int $post_id, WP_Post $post ) : void {
		if ( ! isset( $_POST['wsh_quiz_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wsh_quiz_nonce'] ) ), 'wsh_quiz_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw      = isset( $_POST['wsh_quiz'] ) && is_array( $_POST['wsh_quiz'] ) ? wp_unslash( $_POST['wsh_quiz'] ) : array();
		$existing = wsh_quiz_get_data( $post_id );
		$incoming = wsh_quiz_normalize_data( $raw );
		$is_pro   = WSH_Quiz_Access::is_pro();

		if ( ! $is_pro ) {
			$incoming['branding']  = true;
			$incoming['questions'] = self::filter_questions_for_free_plan( $incoming['questions'], $existing['questions'] );
		}

		if ( WSH_Quiz_Results::player_count( $post_id ) > 0 ) {
			$incoming['questions'] = self::keep_played_questions( $incoming['questions'], $existing['questions'] );
		}

		update_post_meta( $post_id, '_wsh_quiz_data', $incoming );
	}

	/**
	 * Keep already published Pro questions, but do not accept new Pro questions without a license.
	 *
	 * @param array $incoming Incoming questions.
	 * @param array $existing Previously saved questions.
	 */
	protected static function filter_questions_for_free_plan( array $incoming, array $existing ) : array {
		$existing_by_id = array();
		foreach ( $existing as $question ) {
			$existing_by_id[ $question['id'] ] = $question;
		}

		$kept = array();

		foreach ( $incoming as $question ) {
			if ( ! wsh_quiz_is_pro_feature( $question['type'] ) ) {
				$question['media_type'] = 'none';
				$question['media_url']  = '';
				$question['media_id']   = 0;
				$kept[]                 = $question;
				continue;
			}

			if ( isset( $existing_by_id[ $question['id'] ] ) ) {
				$kept[] = $existing_by_id[ $question['id'] ];
			}
		}

		return $kept;
	}

	/**
	 * After people have played, existing questions cannot be removed.
	 *
	 * @param array $incoming Incoming questions.
	 * @param array $existing Previously saved questions.
	 */
	protected static function keep_played_questions( array $incoming, array $existing ) : array {
		$incoming_by_id = array();
		foreach ( $incoming as $question ) {
			$incoming_by_id[ $question['id'] ] = $question;
		}

		$merged = array();
		foreach ( $existing as $question ) {
			$merged[] = $incoming_by_id[ $question['id'] ] ?? $question;
		}

		$existing_ids = array();
		foreach ( $existing as $question ) {
			$existing_ids[ $question['id'] ] = true;
		}

		foreach ( $incoming as $question ) {
			if ( empty( $existing_ids[ $question['id'] ] ) ) {
				$merged[] = $question;
			}
		}

		return $merged;
	}
}
