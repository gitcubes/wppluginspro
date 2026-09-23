<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_Quiz_Frontend {

	public static function init() : void {
		// Assets are enqueued from render() when a quiz is actually output.
	}

	public static function render( int $quiz_id ) : string {
		if ( $quiz_id < 1 ) {
			return '';
		}

		$quiz = get_post( $quiz_id );
		if ( ! $quiz || 'wsh_quiz' !== $quiz->post_type || 'publish' !== $quiz->post_status ) {
			return '';
		}

		$data      = wsh_quiz_get_data( $quiz_id );
		$questions = wsh_quiz_get_public_questions( $quiz_id );

		if ( empty( $questions ) ) {
			return '';
		}

		WSH_Quiz_Assets::enqueue_frontend();

		$show_branding   = ! WSH_Quiz_Access::can( 'no_branding' );
		$availability    = wsh_quiz_availability( $data );
		$sponsor         = wsh_quiz_get_sponsor( $data );
		$user            = is_user_logged_in() ? wp_get_current_user() : null;
		$already_played  = WSH_Quiz_Results::replay_blocked(
			(int) $quiz->ID,
			$data,
			$user ? (int) $user->ID : 0,
			$user ? (string) $user->user_email : ''
		);

		ob_start();
		include WSH_QUIZ_PATH . 'templates/frontend/player.php';
		return (string) ob_get_clean();
	}

	public static function render_list( int $limit = 20 ) : string {
		$posts = get_posts(
			array(
				'post_type'      => 'wsh_quiz',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( empty( $posts ) ) {
			return '';
		}

		WSH_Quiz_Assets::enqueue_frontend();

		$items = array();
		foreach ( $posts as $quiz ) {
			$data         = wsh_quiz_get_data( (int) $quiz->ID );
			$availability = wsh_quiz_availability( $data );
			$items[]      = array(
				'quiz'         => $quiz,
				'data'         => $data,
				'sponsor'      => wsh_quiz_get_sponsor( $data ),
				'availability' => $availability,
			);
		}

		ob_start();
		include WSH_QUIZ_PATH . 'templates/frontend/list.php';
		return (string) ob_get_clean();
	}

	public static function render_leaderboard( int $quiz_id, int $limit = 10 ) : string {
		if ( $quiz_id < 1 ) {
			return '';
		}

		$data = wsh_quiz_get_data( $quiz_id );
		if ( empty( $data['leaderboard'] ) ) {
			return '';
		}

		WSH_Quiz_Assets::enqueue_frontend();

		$rows    = WSH_Quiz_Ajax::public_leaderboard( $quiz_id, $limit );
		$title   = get_the_title( $quiz_id );
		$sponsor = wsh_quiz_get_sponsor( $data );

		ob_start();
		include WSH_QUIZ_PATH . 'templates/frontend/leaderboard.php';
		return (string) ob_get_clean();
	}
}
