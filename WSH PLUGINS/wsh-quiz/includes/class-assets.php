<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_Quiz_Assets {

	public static function init() : void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_frontend' ), 99 );
		add_action( 'template_redirect', array( __CLASS__, 'uncache_quiz_page' ) );
		add_action( 'wp_head', array( __CLASS__, 'print_frontend_css' ), 999 );
		add_action( 'wp_footer', array( __CLASS__, 'print_frontend_css' ), 1 );
	}

	public static function maybe_enqueue_frontend() : void {
		if ( is_active_widget( false, false, 'wsh_quiz_promo', true ) || WSH_Quiz_Pages::is_quiz_page() ) {
			self::enqueue_frontend();
		}
	}

	public static function enqueue_admin( string $hook ) : void {
		$screen      = get_current_screen();
		$is_settings = false !== strpos( $hook, 'wsh-quiz-settings' );

		if ( ! $is_settings && ( ! $screen || 'wsh_quiz' !== $screen->post_type ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'wp-color-picker' );

		wp_enqueue_style(
			'wsh-quiz-admin',
			WSH_QUIZ_URL . 'assets/css/admin.css',
			array( 'wp-color-picker' ),
			WSH_QUIZ_VERSION
		);

		wp_enqueue_script(
			'wsh-quiz-admin',
			WSH_QUIZ_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			WSH_QUIZ_VERSION,
			true
		);

		$quiz_id = 0;
		if ( $screen && 'wsh_quiz' === $screen->post_type ) {
			$quiz_id = (int) get_the_ID();
			if ( $quiz_id < 1 && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$quiz_id = (int) $_GET['post'];
			}
		}

		$player_count = $quiz_id > 0 ? WSH_Quiz_Results::player_count( $quiz_id ) : 0;

		wp_localize_script(
			'wsh-quiz-admin',
			'wshQuizAdmin',
			array(
				'isPro'        => WSH_Quiz_Access::is_pro(),
				'upgradeUrl'   => WSH_Quiz_Access::get_upgrade_url(),
				'upgradeLabel' => WSH_Quiz_Access::get_upgrade_label(),
				'yesLabel'     => __( 'Yes', 'wsh-quiz' ),
				'noLabel'      => __( 'No', 'wsh-quiz' ),
				'playerCount'  => $player_count,
				'siteLocale'   => WSH_Quiz_AI::site_locale(),
				'aiPromptTemplates' => array_map(
					array( 'WSH_Quiz_AI', 'prompt_template' ),
					array_combine( array_keys( WSH_Quiz_AI::languages() ), array_keys( WSH_Quiz_AI::languages() ) )
				),
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'adminNonce'   => wp_create_nonce( 'wsh_quiz_admin' ),
				'i18n'         => array(
					'selectImage'         => __( 'Select image', 'wsh-quiz' ),
					'useImage'            => __( 'Use this image', 'wsh-quiz' ),
					'remove'              => __( 'Remove', 'wsh-quiz' ),
					'question'            => __( 'Question', 'wsh-quiz' ),
					'proLocked'           => __( 'This question type requires an active Pro license.', 'wsh-quiz' ),
					'deleteQuiz'          => __( 'Delete this quiz and all its games? This cannot be undone.', 'wsh-quiz' ),
					'clearResults'        => __( 'Delete all games and results for this quiz? The quiz itself will stay.', 'wsh-quiz' ),
					'removeGame'          => __( 'Remove game', 'wsh-quiz' ),
					'removeGameConfirm'   => __( 'Remove this player\'s game and result? They will be able to play again.', 'wsh-quiz' ),
					'noResults'           => __( 'No results yet.', 'wsh-quiz' ),
					'name'                => __( 'Name', 'wsh-quiz' ),
					'email'               => __( 'Email', 'wsh-quiz' ),
					'score'               => __( 'Score', 'wsh-quiz' ),
					'time'                => __( 'Time', 'wsh-quiz' ),
					'date'                => __( 'Date', 'wsh-quiz' ),
					'error'               => __( 'Could not complete this action. Please try again.', 'wsh-quiz' ),
					'generating'          => __( 'Generating questions…', 'wsh-quiz' ),
					'generated'           => __( 'Questions generated. Review them, then save the quiz.', 'wsh-quiz' ),
					'emptyTopic'          => __( 'Enter a quiz description or an article URL.', 'wsh-quiz' ),
					'removeQuestion'      => __( 'Remove this question?', 'wsh-quiz' ),
					'cannotRemovePlayed'  => __( 'Questions cannot be removed after players have already played this quiz.', 'wsh-quiz' ),
				),
			)
		);
	}

	public static function enqueue_block_editor() : void {
		wp_enqueue_script(
			'wsh-quiz-block',
			WSH_QUIZ_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-data' ),
			WSH_QUIZ_VERSION,
			true
		);

		$quizzes = get_posts(
			array(
				'post_type'      => 'wsh_quiz',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$options = array(
			array(
				'label' => __( 'Select a quiz', 'wsh-quiz' ),
				'value' => 0,
			),
		);

		foreach ( $quizzes as $quiz ) {
			$options[] = array(
				'label' => $quiz->post_title,
				'value' => (int) $quiz->ID,
			);
		}

		wp_localize_script(
			'wsh-quiz-block',
			'wshQuizBlock',
			array(
				'quizzes' => $options,
			)
		);

		wp_set_script_translations( 'wsh-quiz-block', 'wsh-quiz', WSH_QUIZ_PATH . 'languages' );
	}

	public static function frontend_version() : string {
		$file = WSH_QUIZ_PATH . 'assets/css/frontend.css';
		$time = is_readable( $file ) ? (string) filemtime( $file ) : WSH_QUIZ_VERSION;
		return WSH_QUIZ_VERSION . '.' . $time;
	}

	public static function list_overrides_css() : string {
		return implode(
			'',
			array(
				'.static-page-content .wsh-quiz-list p,.static-page-content .wsh-quiz-list h3,.wsh-quiz-list p,.wsh-quiz-list h3{margin:0!important;padding:0!important}',
				'.wsh-quiz-list__logo{width:80px!important;height:80px!important;max-width:80px!important}',
				'.wsh-quiz-list__slogan{font-family:"STIX Two Text",Georgia,serif!important;font-size:32px!important;font-weight:500!important;line-height:1.25!important}',
				'.wsh-quiz-list__item{display:flex!important;grid-template-columns:none!important;grid-template-areas:none!important;align-items:stretch!important;gap:20px!important;padding:18px!important}',
				'.wsh-quiz-list__main{flex:1 1 auto!important;min-width:0!important}',
				'.wsh-quiz-list__media{width:82%!important;max-width:620px!important}',
				'.wsh-quiz-list__copy{display:flex!important;flex-direction:column!important;gap:15px!important;width:82%!important;max-width:620px!important;padding-top:14px!important}',
				'.wsh-quiz-list__sponsor{flex:0 0 168px!important;width:168px!important;grid-area:auto!important}',
				'.wsh-quiz-list__meta{display:flex!important;align-items:baseline!important;justify-content:space-between!important;gap:12px!important;font-family:"STIX Two Text",Georgia,serif!important;font-style:italic!important;font-size:15px!important;margin:0!important}',
				'.wsh-quiz-list__stats{flex:0 0 auto!important;font-style:normal!important;font-weight:600!important;white-space:nowrap!important}',
				'.wsh-quiz-list__title,.wsh-quiz-list__title a{font-family:"NeueHaasGroteskText Pro Md","NeueHaasGroteskText Pro",sans-serif!important;margin:0!important;font-size:26px!important;line-height:1.25!important}',
				'.wsh-quiz-list__excerpt,.wsh-quiz-list__actions{font-family:"STIX Two Text",Georgia,serif!important;margin:0!important}',
				'.wsh-quiz-list__excerpt{font-size:17px!important;line-height:1.5!important}',
				'.wsh-quiz[hidden],.wsh-quiz [hidden]{display:none!important}',
				'.wsh-quiz,.wsh-quiz-board{display:block!important;max-width:820px!important;margin:0 auto 32px!important;padding:0!important;border:0!important;background:transparent!important;box-shadow:none!important}',
				'.wsh-quiz__hero,.wsh-quiz .wsh-quiz-page-nav,.wsh-quiz__kicker,.wsh-quiz__description{display:none!important}',
				'.wsh-quiz__head{display:flex!important;align-items:baseline!important;justify-content:space-between!important;gap:16px!important;margin:0 0 22px!important}',
				'.wsh-quiz__title,.wsh-quiz-board__title{font-family:"NeueHaasGroteskText Pro Md","NeueHaasGroteskText Pro",sans-serif!important;font-size:32px!important;font-weight:500!important;line-height:1.2!important;letter-spacing:0!important;margin:0!important}',
				'.static-page-content a.wsh-quiz__back,.wsh-quiz__back{flex:0 0 auto!important;color:#1d2327!important;font-size:15px!important;font-weight:600!important;letter-spacing:0!important;text-decoration:none!important}',
				'.static-page-content a.wsh-quiz__back:hover,.wsh-quiz__back:hover{color:#d63638!important;text-decoration:underline!important}',
				'.wsh-quiz__board-time,.wsh-quiz-board__time,.wsh-quiz__time{font-variant-numeric:tabular-nums!important;color:#646970!important;font-weight:600!important;letter-spacing:0!important}',
				'.wsh-quiz__stage{display:block!important;padding:28px!important;border:1px solid #dcdcde!important;border-radius:12px!important;background:#fff!important}',
				'.wsh-quiz__played{display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;gap:12px!important;min-height:220px!important;padding:36px 24px!important;text-align:center!important}',
				'.wsh-quiz__played-title{margin:0!important;font-family:"NeueHaasGroteskText Pro Md","NeueHaasGroteskText Pro",sans-serif!important;font-size:22px!important;font-weight:600!important;line-height:1.35!important;text-align:center!important}',
				'.wsh-quiz__played-rule{margin:0 auto!important;max-width:440px!important;font-family:"STIX Two Text",Georgia,serif!important;font-size:16px!important;line-height:1.5!important;color:#646970!important;text-align:center!important}',
				'.wsh-quiz__prompt{position:relative!important;min-height:200px!important;margin:0 0 18px!important;padding:36px 48px 36px 28px!important;background:#f4f4f5!important;border-radius:8px!important}',
				'.wsh-quiz__question{display:flex!important;align-items:center!important;justify-content:center!important;min-height:128px!important;margin:0!important;padding:0!important;background:transparent!important;font-size:26px!important;font-weight:500!important;text-align:center!important;letter-spacing:0!important}',
				'.wsh-quiz__hint-btn{position:absolute!important;top:14px!important;right:14px!important;width:28px!important;height:28px!important;border:1.5px solid #8c8f94!important;border-radius:50%!important;background:#fff!important;color:#646970!important}',
				'.wsh-quiz__hint{position:absolute!important;top:50px!important;right:14px!important;max-width:320px!important;margin:0!important;padding:10px 12px!important;background:#fff!important;border-radius:8px!important;font-size:14px!important;text-align:left!important}',
				'.wsh-quiz__option{font-family:"NeueHaasGroteskText Pro Md","NeueHaasGroteskText Pro",sans-serif!important;font-size:18px!important;padding:20px 18px!important;border:0!important;border-radius:8px!important;background:#f4f4f5!important;text-align:center!important;letter-spacing:0!important}',
				'.wsh-quiz__option.is-correct{background:#e4f6e8!important;color:#007017!important;font-weight:700!important}',
				'.wsh-quiz__option.is-wrong{background:#fde8e8!important;color:#b32d2e!important;font-weight:700!important}',
				'.wsh-quiz__feedback{margin-top:16px!important;padding:14px 16px!important;border-radius:8px!important;font-size:16px!important;line-height:1.45!important;letter-spacing:0!important}',
				'.wsh-quiz__feedback.is-ok{background:#e4f6e8!important;color:#007017!important}',
				'.wsh-quiz__feedback.is-miss{background:#fde8e8!important;color:#8a2424!important}',
				'.wsh-quiz__giveup{margin-left:auto!important;padding:0!important;border:0!important;background:transparent!important;color:#646970!important}',
				'.wsh-quiz__footer{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:12px!important;margin-top:18px!important}',
				'.wsh-quiz__skip{padding:0!important;border:0!important;background:transparent!important;color:#646970!important;font-size:15px!important;font-weight:600!important;letter-spacing:0!important;text-decoration:underline!important}',
				'.wsh-quiz__track{display:flex!important;align-items:center!important;gap:12px!important;margin:0 0 22px!important}',
				'.wsh-quiz__bar{flex:1!important;height:8px!important;margin:0!important;background:#f0f0f1!important}',
				'.wsh-quiz__credit,p.wsh-quiz__credit,div.wsh-quiz__credit{display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;gap:6px!important;margin:28px 0 0!important;padding:0!important;border:0!important;background:transparent!important;color:#646970!important;text-align:center!important}',
				'.wsh-quiz__credit img{width:100%!important;max-width:640px!important;max-height:180px!important;height:auto!important;object-fit:contain!important}',
				'.wsh-quiz__credit-label{font-size:11px!important;font-weight:500!important;text-transform:lowercase!important;letter-spacing:.02em!important;color:#646970!important}',
				'.wsh-quiz__branding,.wsh-quiz__branding a{color:#646970!important;text-align:center!important;text-decoration:none!important}',
				'.wsh-quiz-page-nav{margin:24px 0 0!important;text-align:center!important}',
				'.wsh-quiz__result-hero{display:flex!important;flex-direction:column!important;align-items:center!important;padding:28px 20px!important;margin:0 0 24px!important;background:#f4f4f5!important;border-radius:8px!important;text-align:center!important}',
				'.wsh-quiz__score,.wsh-quiz__score-num{font-size:52px!important;font-weight:600!important;line-height:1!important;margin:0!important;letter-spacing:0!important}',
				'.wsh-quiz__score-caption{font-size:15px!important;margin:8px 0 0!important;color:#646970!important}',
				'.wsh-quiz__result-kicker{font-size:16px!important;margin:0 0 8px!important;letter-spacing:0!important}',
				'.wsh-quiz__rank{display:inline-block!important;margin:12px 0 0!important;padding:6px 12px!important;border-radius:999px!important;background:#fff!important;font-size:14px!important;font-weight:600!important}',
				'.wsh-quiz__share-list{display:flex!important;flex-wrap:wrap!important;gap:8px!important}',
				'.wsh-quiz button.wsh-quiz__share{margin:0!important;padding:10px 14px!important;border:0!important;border-radius:8px!important;background:#f4f4f5!important;color:#1d2327!important;font-size:14px!important;letter-spacing:0!important}',
				'.wsh-quiz__board-title,.wsh-quiz__board h4{font-size:20px!important;margin:0 0 12px!important}',
				'.wsh-quiz__board-list li.is-place-1,.wsh-quiz-board__list li.is-place-1{background:#fff6d8!important;font-weight:700!important;border-radius:8px!important}',
				'.wsh-quiz__board-list li.is-place-2,.wsh-quiz-board__list li.is-place-2{background:#f3f3f5!important;font-weight:700!important;border-radius:8px!important}',
				'.wsh-quiz__board-list li.is-place-3,.wsh-quiz-board__list li.is-place-3{background:#f8eee4!important;font-weight:700!important;border-radius:8px!important}',
				'.wsh-quiz__board-list li.is-place-1 .wsh-quiz__board-rank,.wsh-quiz-board__list li.is-place-1 .wsh-quiz-board__rank{color:#b8860b!important;font-size:18px!important}',
				'.wsh-quiz__board-list li.is-place-2 .wsh-quiz__board-rank,.wsh-quiz-board__list li.is-place-2 .wsh-quiz-board__rank{color:#6d7177!important;font-size:17px!important}',
				'.wsh-quiz__board-list li.is-place-3 .wsh-quiz__board-rank,.wsh-quiz-board__list li.is-place-3 .wsh-quiz-board__rank{color:#a35d24!important;font-size:16px!important}',
				'.wsh-quiz__trophy{width:88px!important;height:88px!important;margin:0 0 10px!important}',
				'.wsh-quiz__trophy-icon{width:88px!important;height:88px!important}',
				'.static-page-content .wsh-quiz p,.static-page-content .wsh-quiz h3,.static-page-content .wsh-quiz button{letter-spacing:0!important}',
				'.wsh-quiz__gate{max-width:460px!important;margin:0 auto!important;padding:12px 0!important}',
				'.wsh-quiz__gate-lead{margin:0 0 22px!important;font-size:17px!important;line-height:1.5!important;text-align:center!important;color:#646970!important}',
				'.wsh-quiz__player-form{display:grid!important;gap:16px!important;width:100%!important;max-width:460px!important;margin:0 auto!important}',
				'.wsh-quiz__player-row{display:grid!important;grid-template-columns:1fr 1fr!important;gap:12px!important}',
				'.wsh-quiz__player-form label{display:grid!important;gap:6px!important;margin:0!important}',
				'.wsh-quiz__player-form label span{font-size:13px!important;font-weight:600!important;letter-spacing:0!important}',
				'.wsh-quiz__player-form input,.wsh-quiz__player-form input[type=text],.wsh-quiz__player-form input[type=email]{appearance:none!important;-webkit-appearance:none!important;width:100%!important;height:48px!important;margin:0!important;padding:12px 14px!important;border:1px solid #dcdcde!important;border-radius:8px!important;background:#fff!important;box-shadow:none!important;font-size:16px!important;letter-spacing:0!important}',
				'.wsh-quiz button.wsh-quiz__start,.wsh-quiz a.wsh-quiz__start{display:inline-flex!important;align-items:center!important;justify-content:center!important;width:100%!important;min-height:48px!important;margin:4px 0 0!important;padding:12px 16px!important;border:0!important;border-radius:8px!important;font-size:16px!important;font-weight:700!important;letter-spacing:0!important}',
			)
		);
	}

	public static function uncache_quiz_page() : void {
		if ( ! WSH_Quiz_Pages::is_quiz_page() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! defined( 'LSCACHE_NO_CACHE' ) ) {
			define( 'LSCACHE_NO_CACHE', true );
		}

		nocache_headers();
	}

	public static function print_frontend_css() : void {
		static $printed = false;

		if ( $printed ) {
			return;
		}

		if ( ! WSH_Quiz_Pages::is_quiz_page() && ! is_active_widget( false, false, 'wsh_quiz_promo', true ) ) {
			return;
		}

		$printed = true;
		$file    = WSH_QUIZ_PATH . 'assets/css/frontend.css';
		$css     = is_readable( $file ) ? (string) file_get_contents( $file ) : '';

		echo '<style id="wsh-quiz-frontend-live">' . $css . WSH_Quiz_Settings::css_variables() . self::list_overrides_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function enqueue_frontend() : void {
		wp_enqueue_style(
			'wsh-quiz-frontend',
			WSH_QUIZ_URL . 'assets/css/frontend.css',
			array(),
			self::frontend_version()
		);

		wp_add_inline_style( 'wsh-quiz-frontend', WSH_Quiz_Settings::css_variables() . self::list_overrides_css() );

		wp_enqueue_script(
			'wsh-quiz-frontend',
			WSH_QUIZ_URL . 'assets/js/frontend.js',
			array(),
			WSH_QUIZ_VERSION,
			true
		);

		wp_localize_script(
			'wsh-quiz-frontend',
			'wshQuizPlayer',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wsh_quiz_play' ),
				'i18n'    => array(
					'next'        => __( 'Next', 'wsh-quiz' ),
					'skip'        => __( 'Skip question', 'wsh-quiz' ),
					'finish'      => __( 'See results', 'wsh-quiz' ),
					'correct'     => __( 'Correct', 'wsh-quiz' ),
					'wrong'       => __( 'Wrong', 'wsh-quiz' ),
					'score'       => __( 'Your score', 'wsh-quiz' ),
					'restart'     => __( 'Play again', 'wsh-quiz' ),
					'share'       => __( 'Share result', 'wsh-quiz' ),
					'error'       => __( 'Could not check this answer. Please try again.', 'wsh-quiz' ),
					'poweredBy'     => __( 'Powered by WSH Quiz', 'wsh-quiz' ),
					'of'            => __( 'of', 'wsh-quiz' ),
					'giveUpConfirm' => __( 'Give up this quiz?', 'wsh-quiz' ),
					'correctCount'  => __( 'correct', 'wsh-quiz' ),
					'rank'          => __( 'Your rank', 'wsh-quiz' ),
					'hint'          => __( 'Hint', 'wsh-quiz' ),
					'place1'        => __( '1st place', 'wsh-quiz' ),
					'place2'        => __( '2nd place', 'wsh-quiz' ),
					'place3'        => __( '3rd place', 'wsh-quiz' ),
					'alreadyPlayed'     => __( 'You have already played this quiz.', 'wsh-quiz' ),
					'alreadyPlayedRule' => __( 'This quiz can be played only once. Logged-in players are recognized by their account. Guests are recognized by the email they entered and this browser.', 'wsh-quiz' ),
				),
			)
		);
	}
}
