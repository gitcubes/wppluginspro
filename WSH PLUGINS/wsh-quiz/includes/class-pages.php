<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the public Quiz page and routes play / results views on it.
 */
class WSH_Quiz_Pages {

	const OPTION_LIST = 'wsh_quiz_page_list';

	public static function init() : void {
		add_action( 'init', array( __CLASS__, 'register_rewrite' ), 20 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 99 );
		add_filter( 'document_title_parts', array( __CLASS__, 'document_title' ) );
	}

	/**
	 * Create the Quiz archive page once. We do not delete it on deactivate.
	 */
	public static function ensure_pages() : void {
		$page_id = (int) get_option( self::OPTION_LIST, 0 );
		$page    = $page_id > 0 ? get_post( $page_id ) : null;

		if ( $page && 'page' === $page->post_type && 'trash' !== $page->post_status ) {
			return;
		}

		$created = wp_insert_post(
			array(
				'post_title'   => __( 'Quiz', 'wsh-quiz' ),
				'post_name'    => 'quiz',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[wsh_quiz_list]',
				'post_author'  => get_current_user_id() ?: 1,
			),
			true
		);

		if ( ! is_wp_error( $created ) && $created > 0 ) {
			update_option( self::OPTION_LIST, (int) $created );
		}
	}

	public static function query_vars( array $vars ) : array {
		$vars[] = 'wsh_quiz_id';
		$vars[] = 'wsh_quiz_view';
		return $vars;
	}

	public static function register_rewrite() : void {
		$page_id = wsh_quiz_get_list_page_id();
		if ( $page_id < 1 ) {
			return;
		}

		$slug = get_post_field( 'post_name', $page_id );
		if ( ! is_string( $slug ) || '' === $slug ) {
			return;
		}

		add_rewrite_rule(
			'^' . preg_quote( $slug, '/' ) . '/play/([0-9]+)/?$',
			'index.php?page_id=' . $page_id . '&wsh_quiz_view=play&wsh_quiz_id=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^' . preg_quote( $slug, '/' ) . '/results/([0-9]+)/?$',
			'index.php?page_id=' . $page_id . '&wsh_quiz_view=results&wsh_quiz_id=$matches[1]',
			'top'
		);
	}

	public static function current_view() : string {
		$view = get_query_var( 'wsh_quiz_view' );
		if ( ! $view && isset( $_GET['wsh_quiz_view'] ) ) {
			$view = sanitize_key( wp_unslash( $_GET['wsh_quiz_view'] ) );
		}

		return in_array( $view, array( 'play', 'results', 'list' ), true ) ? $view : 'list';
	}

	public static function current_quiz_id() : int {
		$id = (int) get_query_var( 'wsh_quiz_id' );
		if ( $id < 1 && isset( $_GET['wsh_quiz_id'] ) ) {
			$id = (int) $_GET['wsh_quiz_id'];
		}

		return $id;
	}

	public static function is_quiz_page() : bool {
		$page_id = wsh_quiz_get_list_page_id();
		return $page_id > 0 && is_page( $page_id );
	}

	public static function render_current_view() : string {
		$view    = self::current_view();
		$quiz_id = self::current_quiz_id();

		if ( 'play' === $view && $quiz_id > 0 ) {
			$output = WSH_Quiz_Frontend::render( $quiz_id );
			return '' !== $output ? $output : '<p class="wsh-quiz-board__empty">' . esc_html__( 'This quiz cannot be played yet.', 'wsh-quiz' ) . '</p>';
		}

		if ( 'results' === $view && $quiz_id > 0 ) {
			$board = WSH_Quiz_Frontend::render_leaderboard( $quiz_id );
			return $board ?: '<p class="wsh-quiz-board__empty">' . esc_html__( 'This quiz has no public leaderboard.', 'wsh-quiz' ) . '</p>';
		}

		return WSH_Quiz_Frontend::render_list();
	}

	public static function filter_content( string $content ) : string {
		// nasportal page.php calls the_content() without a loop, so do not require in_the_loop().
		if ( ! self::is_quiz_page() ) {
			return $content;
		}

		return self::render_current_view();
	}

	public static function document_title( array $parts ) : array {
		if ( ! self::is_quiz_page() ) {
			return $parts;
		}

		$view    = self::current_view();
		$quiz_id = self::current_quiz_id();
		$title   = $quiz_id > 0 ? get_the_title( $quiz_id ) : '';

		if ( 'play' === $view && '' !== $title ) {
			$parts['title'] = $title;
		}

		if ( 'results' === $view && '' !== $title ) {
			$parts['title'] = sprintf(
				/* translators: %s: quiz title */
				__( 'Results: %s', 'wsh-quiz' ),
				$title
			);
		}

		return $parts;
	}
}
