<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_Quiz_Ajax {

	public static function init() : void {
		$actions = array( 'answer', 'finish', 'can_play' );
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_wsh_quiz_' . $action, array( __CLASS__, $action ) );
			add_action( 'wp_ajax_nopriv_wsh_quiz_' . $action, array( __CLASS__, $action ) );
		}
	}

	protected static function get_published_quiz( int $quiz_id ) {
		$quiz = get_post( $quiz_id );
		if ( ! $quiz || 'wsh_quiz' !== $quiz->post_type || 'publish' !== $quiz->post_status ) {
			wp_send_json_error( array( 'message' => __( 'This quiz is not available.', 'wsh-quiz' ) ), 404 );
		}

		return $quiz;
	}

	public static function answer() : void {
		check_ajax_referer( 'wsh_quiz_play', 'nonce' );

		$quiz_id     = isset( $_POST['quiz_id'] ) ? (int) $_POST['quiz_id'] : 0;
		$question_id = isset( $_POST['question_id'] ) ? sanitize_text_field( wp_unslash( $_POST['question_id'] ) ) : '';
		$option      = isset( $_POST['option'] ) ? (int) $_POST['option'] : -1;

		if ( $quiz_id < 1 || '' === $question_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid quiz question.', 'wsh-quiz' ) ), 400 );
		}

		self::get_published_quiz( $quiz_id );

		$question = wsh_quiz_find_question( $quiz_id, $question_id );
		if ( ! $question ) {
			wp_send_json_error( array( 'message' => __( 'Question not found.', 'wsh-quiz' ) ), 404 );
		}

		$is_correct = ( $option === (int) $question['correct'] );

		wp_send_json_success(
			array(
				'correct'      => $is_correct,
				'correctIndex' => (int) $question['correct'],
				'score'        => $is_correct ? (int) $question['score'] : 0,
				'explanation'  => $question['explanation'],
			)
		);
	}

	public static function finish() : void {
		check_ajax_referer( 'wsh_quiz_play', 'nonce' );

		$quiz_id  = isset( $_POST['quiz_id'] ) ? (int) $_POST['quiz_id'] : 0;
		$duration = isset( $_POST['duration'] ) ? max( 0, (int) $_POST['duration'] ) : 0;
		$raw      = isset( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : '';

		self::get_published_quiz( $quiz_id );

		$data         = wsh_quiz_get_data( $quiz_id );
		$availability = wsh_quiz_availability( $data );
		if ( empty( $availability['available'] ) ) {
			wp_send_json_error( array( 'message' => $availability['message'] ), 403 );
		}

		$player = self::collect_player( $data );
		if ( is_wp_error( $player ) ) {
			wp_send_json_error( array( 'message' => $player->get_error_message() ), 403 );
		}

		if ( WSH_Quiz_Results::replay_blocked( $quiz_id, $data, (int) $player['user_id'], (string) $player['email'] ) ) {
			wp_send_json_error( array( 'message' => __( 'You have already played this quiz.', 'wsh-quiz' ) ), 403 );
		}

		$submitted = json_decode( is_string( $raw ) ? $raw : '', true );
		if ( ! is_array( $submitted ) ) {
			$submitted = array();
		}

		$checked = self::score_answers( $quiz_id, $submitted );

		$result_id = WSH_Quiz_Results::save(
			array(
				'quiz_id'    => $quiz_id,
				'user_id'    => $player['user_id'],
				'first_name' => $player['first_name'],
				'last_name'  => $player['last_name'],
				'email'      => $player['email'],
				'score'      => $checked['score'],
				'max_score'  => $checked['max'],
				'duration'   => $duration,
				'answers'    => $checked['marks'],
			)
		);
		$rank  = 0;
		$board = array();

		if ( ! empty( $data['leaderboard'] ) ) {
			$rank  = WSH_Quiz_Results::rank( $quiz_id, $checked['score'], $duration );
			$board = self::public_leaderboard( $quiz_id );
		}

		if ( empty( $data['allow_replay'] ) ) {
			WSH_Quiz_Results::mark_browser_played( $quiz_id, $result_id );
		}

		wp_send_json_success(
			array(
				'score'       => $checked['score'],
				'max'         => $checked['max'],
				'correct'     => $checked['correct_count'],
				'total'       => $checked['total'],
				'marks'       => $checked['marks'],
				'rank'        => $rank,
				'duration'    => $duration,
				'resultId'    => $result_id,
				'leaderboard' => $board,
			)
		);
	}

	public static function can_play() : void {
		check_ajax_referer( 'wsh_quiz_play', 'nonce' );

		$quiz_id = isset( $_POST['quiz_id'] ) ? (int) $_POST['quiz_id'] : 0;
		self::get_published_quiz( $quiz_id );

		$data   = wsh_quiz_get_data( $quiz_id );
		$player = self::collect_player( $data );
		if ( is_wp_error( $player ) ) {
			wp_send_json_error( array( 'message' => $player->get_error_message() ), 403 );
		}

		if ( WSH_Quiz_Results::replay_blocked( $quiz_id, $data, (int) $player['user_id'], (string) $player['email'] ) ) {
			wp_send_json_error( array( 'message' => __( 'You have already played this quiz.', 'wsh-quiz' ) ), 403 );
		}

		wp_send_json_success( array( 'canPlay' => true ) );
	}

	/**
	 * @return array{user_id:int, first_name:string, last_name:string, email:string}|WP_Error
	 */
	protected static function collect_player( array $data ) {
		$mode = $data['entry_mode'];

		if ( 'account' === $mode && ! is_user_logged_in() ) {
			return new WP_Error( 'login_required', __( 'You need an account to play this quiz.', 'wsh-quiz' ) );
		}

		$user = is_user_logged_in() ? wp_get_current_user() : null;

		$first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( $user ) {
			$first = $first ?: (string) $user->first_name;
			$last  = $last ?: (string) $user->last_name;
			$email = $email ?: (string) $user->user_email;
			if ( '' === $first ) {
				$first = (string) $user->display_name;
			}
		}

		if ( 'guest' === $mode && ! $user ) {
			if ( '' === $first || '' === $last || ! is_email( $email ) ) {
				return new WP_Error( 'player_required', __( 'Please enter your first name, last name and email.', 'wsh-quiz' ) );
			}
		}

		return array(
			'user_id'    => $user ? (int) $user->ID : 0,
			'first_name' => $first,
			'last_name'  => $last,
			'email'      => $email,
		);
	}

	/**
	 * Recalculate the score from stored questions.
	 *
	 * @param array $submitted Client answers.
	 */
	protected static function score_answers( int $quiz_id, array $submitted ) : array {
		$data    = wsh_quiz_get_data( $quiz_id );
		$by_id   = array();
		$max     = 0;
		$marks   = array();
		$score   = 0;
		$correct = 0;

		foreach ( $data['questions'] as $question ) {
			$by_id[ $question['id'] ] = $question;
			$max                     += (int) $question['score'];
		}

		foreach ( $data['questions'] as $question ) {
			$picked = null;
			foreach ( $submitted as $row ) {
				if ( is_array( $row ) && isset( $row['id'] ) && $row['id'] === $question['id'] ) {
					$picked = isset( $row['option'] ) ? (int) $row['option'] : -1;
					break;
				}
			}

			$is_correct = ( null !== $picked && $picked === (int) $question['correct'] );
			if ( $is_correct ) {
				$score   += (int) $question['score'];
				$correct += 1;
			}

			$marks[] = $is_correct ? 1 : 0;
		}

		return array(
			'score'         => $score,
			'max'           => $max,
			'correct_count' => $correct,
			'total'         => count( $data['questions'] ),
			'marks'         => $marks,
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function public_leaderboard( int $quiz_id, int $limit = 10 ) : array {
		$rows   = WSH_Quiz_Results::leaderboard( $quiz_id, $limit );
		$public = array();

		foreach ( $rows as $index => $row ) {
			$public[] = array(
				'rank'     => $index + 1,
				'name'     => WSH_Quiz_Results::display_name( $row ),
				'score'    => (int) $row->score,
				'max'      => (int) $row->max_score,
				'duration' => (int) $row->duration,
			);
		}

		return $public;
	}
}
