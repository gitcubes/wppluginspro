<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_Quiz_Admin {

	public static function init() : void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_notices', array( __CLASS__, 'license_notice' ) );
		add_action( 'admin_footer', array( __CLASS__, 'print_results_modal' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_results_for_post' ) );
		add_action( 'wp_trash_post', array( __CLASS__, 'delete_results_for_post' ) );
		add_action( 'wp_ajax_wsh_quiz_admin_list_results', array( __CLASS__, 'ajax_list_results' ) );
		add_action( 'wp_ajax_wsh_quiz_admin_delete_result', array( __CLASS__, 'ajax_delete_result' ) );
		add_action( 'wp_ajax_wsh_quiz_admin_clear_results', array( __CLASS__, 'ajax_clear_results' ) );
		add_action( 'wp_ajax_wsh_quiz_admin_delete_quiz', array( __CLASS__, 'ajax_delete_quiz' ) );
		add_action( 'wp_ajax_wsh_quiz_admin_generate', array( __CLASS__, 'ajax_generate' ) );
	}

	public static function register_menu() : void {
		add_submenu_page(
			'edit.php?post_type=wsh_quiz',
			__( 'Settings', 'wsh-quiz' ),
			__( 'Settings', 'wsh-quiz' ),
			'manage_options',
			'wsh-quiz-settings',
			array( 'WSH_Quiz_Settings', 'render' )
		);

		add_submenu_page(
			'edit.php?post_type=wsh_quiz',
			__( 'License', 'wsh-quiz' ),
			__( 'License', 'wsh-quiz' ),
			'manage_options',
			'wsh-quiz-license',
			array( 'WSH_Quiz_License_Page', 'render' )
		);
	}

	public static function license_notice() : void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'wsh_quiz' ) ) {
			return;
		}

		if ( false !== strpos( (string) $screen->id, 'wsh-quiz-settings' ) ) {
			return;
		}

		if ( WSH_Quiz_Access::is_pro() ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'You are on the Free plan. Image/video questions and AI generation stay locked until a license is active.', 'wsh-quiz' ),
			esc_url( WSH_Quiz_Access::get_upgrade_url() ),
			esc_html( WSH_Quiz_Access::get_upgrade_label() )
		);
	}

	public static function delete_results_for_post( int $post_id ) : void {
		if ( 'wsh_quiz' === get_post_type( $post_id ) ) {
			WSH_Quiz_Results::delete_for_quiz( $post_id );
		}
	}

	public static function render_actions( int $quiz_id, bool $show_count = true ) : void {
		if ( $quiz_id < 1 || 'auto-draft' === get_post_status( $quiz_id ) ) {
			echo '<p class="description">' . esc_html__( 'Save the quiz first to manage games and results.', 'wsh-quiz' ) . '</p>';
			return;
		}

		$count = WSH_Quiz_Results::player_count( $quiz_id );
		?>
		<div class="wsh-quiz-actions" data-quiz-id="<?php echo esc_attr( (string) $quiz_id ); ?>">
			<?php if ( $show_count ) : ?>
				<p class="wsh-quiz-player-count">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of players */
							_n( '%s player', '%s players', $count, 'wsh-quiz' ),
							number_format_i18n( $count )
						)
					);
					?>
				</p>
			<?php endif; ?>
			<p class="wsh-quiz-action-buttons">
				<button type="button" class="button wsh-quiz-view-results"><?php esc_html_e( 'View results', 'wsh-quiz' ); ?></button>
				<button type="button" class="button wsh-quiz-clear-results"><?php esc_html_e( 'Delete all games', 'wsh-quiz' ); ?></button>
				<button type="button" class="button button-link-delete wsh-quiz-delete-quiz"><?php esc_html_e( 'Delete quiz', 'wsh-quiz' ); ?></button>
			</p>
		</div>
		<?php
	}

	public static function print_results_modal() : void {
		$screen = get_current_screen();
		if ( ! $screen || 'wsh_quiz' !== $screen->post_type ) {
			return;
		}
		?>
		<div class="wsh-quiz-modal" id="wsh-quiz-results-modal" hidden>
			<div class="wsh-quiz-modal__box" role="dialog" aria-modal="true" aria-labelledby="wsh-quiz-results-title">
				<div class="wsh-quiz-modal__head">
					<h2 id="wsh-quiz-results-title"><?php esc_html_e( 'Quiz results', 'wsh-quiz' ); ?></h2>
					<button type="button" class="button-link wsh-quiz-modal-close"><?php esc_html_e( 'Close', 'wsh-quiz' ); ?></button>
				</div>
				<div class="wsh-quiz-modal__body"></div>
			</div>
		</div>
		<div class="wsh-quiz-modal" id="wsh-quiz-image-modal" hidden>
			<div class="wsh-quiz-modal__box wsh-quiz-image-modal__box" role="dialog" aria-modal="true">
				<div class="wsh-quiz-modal__head">
					<h2><?php esc_html_e( 'Image', 'wsh-quiz' ); ?></h2>
					<button type="button" class="button-link wsh-quiz-modal-close"><?php esc_html_e( 'Close', 'wsh-quiz' ); ?></button>
				</div>
				<img src="" alt="" />
			</div>
		</div>
		<?php
	}

	protected static function require_quiz_cap( string $cap ) : int {
		check_ajax_referer( 'wsh_quiz_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'wsh-quiz' ) ), 403 );
		}

		$quiz_id = isset( $_POST['quiz_id'] ) ? (int) $_POST['quiz_id'] : 0;
		$quiz    = get_post( $quiz_id );

		if ( ! $quiz || 'wsh_quiz' !== $quiz->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Quiz not found.', 'wsh-quiz' ) ), 404 );
		}

		if ( ! current_user_can( $cap, $quiz_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'wsh-quiz' ) ), 403 );
		}

		return $quiz_id;
	}

	protected static function results_payload( int $quiz_id ) : array {
		$rows = array();

		foreach ( WSH_Quiz_Results::list_admin( $quiz_id ) as $index => $row ) {
			$rows[] = array(
				'id'       => (int) $row->id,
				'rank'     => $index + 1,
				'name'     => WSH_Quiz_Results::display_name( $row ),
				'email'    => (string) $row->email,
				'score'    => (int) $row->score,
				'max'      => (int) $row->max_score,
				'duration' => wsh_quiz_format_duration( (int) $row->duration ),
				'created'  => $row->created ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->created ) : '',
			);
		}

		$count = WSH_Quiz_Results::player_count( $quiz_id );

		return array(
			'count' => $count,
			'label' => sprintf(
				/* translators: %s: number of players */
				_n( '%s player', '%s players', $count, 'wsh-quiz' ),
				number_format_i18n( $count )
			),
			'rows'  => $rows,
		);
	}

	public static function ajax_list_results() : void {
		wp_send_json_success( self::results_payload( self::require_quiz_cap( 'edit_post' ) ) );
	}

	public static function ajax_delete_result() : void {
		$quiz_id   = self::require_quiz_cap( 'edit_post' );
		$result_id = isset( $_POST['result_id'] ) ? (int) $_POST['result_id'] : 0;

		if ( ! WSH_Quiz_Results::delete_one( $result_id, $quiz_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Game not found.', 'wsh-quiz' ) ), 404 );
		}

		wp_send_json_success( self::results_payload( $quiz_id ) );
	}

	public static function ajax_clear_results() : void {
		$quiz_id = self::require_quiz_cap( 'edit_post' );
		WSH_Quiz_Results::delete_for_quiz( $quiz_id );

		wp_send_json_success(
			array(
				'count'   => 0,
				'label'   => sprintf(
					/* translators: %s: number of players */
					_n( '%s player', '%s players', 0, 'wsh-quiz' ),
					number_format_i18n( 0 )
				),
				'message' => __( 'All games and results were deleted.', 'wsh-quiz' ),
			)
		);
	}

	public static function ajax_delete_quiz() : void {
		$quiz_id = self::require_quiz_cap( 'delete_post' );
		WSH_Quiz_Results::delete_for_quiz( $quiz_id );
		wp_delete_post( $quiz_id, true );

		wp_send_json_success(
			array(
				'redirect' => admin_url( 'edit.php?post_type=wsh_quiz' ),
			)
		);
	}

	public static function ajax_generate() : void {
		check_ajax_referer( 'wsh_quiz_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'wsh-quiz' ) ), 403 );
		}

		$result = WSH_Quiz_AI::generate(
			array(
				'topic'      => isset( $_POST['topic'] ) ? wp_unslash( (string) $_POST['topic'] ) : '',
				'url'        => isset( $_POST['url'] ) ? wp_unslash( (string) $_POST['url'] ) : '',
				'count'      => isset( $_POST['count'] ) ? (int) $_POST['count'] : 5,
				'difficulty' => isset( $_POST['difficulty'] ) ? sanitize_key( wp_unslash( (string) $_POST['difficulty'] ) ) : 'medium',
				'timer'      => isset( $_POST['timer'] ) ? (int) $_POST['timer'] : 0,
				'photo'      => isset( $_POST['photo'] ) ? (int) $_POST['photo'] : 0,
				'video'      => isset( $_POST['video'] ) ? (int) $_POST['video'] : 0,
				'language'   => isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['language'] ) ) : '',
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		if ( empty( $result['questions'] ) ) {
			wp_send_json_error( array( 'message' => __( 'AI did not return any questions.', 'wsh-quiz' ) ) );
		}

		wp_send_json_success( $result );
	}
}
