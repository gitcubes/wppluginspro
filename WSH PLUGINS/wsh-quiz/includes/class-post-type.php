<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_Quiz_Post_Type {

	const POST_TYPE = 'wsh_quiz';

	public static function init() : void {
		self::register();
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	public static function register() : void {
		$labels = array(
			'name'               => __( 'Quizzes', 'wsh-quiz' ),
			'singular_name'      => __( 'Quiz', 'wsh-quiz' ),
			'add_new'            => __( 'Add New', 'wsh-quiz' ),
			'add_new_item'       => __( 'Add New Quiz', 'wsh-quiz' ),
			'edit_item'          => __( 'Edit Quiz', 'wsh-quiz' ),
			'new_item'           => __( 'New Quiz', 'wsh-quiz' ),
			'view_item'          => __( 'View Quiz', 'wsh-quiz' ),
			'search_items'       => __( 'Search Quizzes', 'wsh-quiz' ),
			'not_found'          => __( 'No quizzes found.', 'wsh-quiz' ),
			'not_found_in_trash' => __( 'No quizzes found in Trash.', 'wsh-quiz' ),
			'all_items'          => __( 'All Quizzes', 'wsh-quiz' ),
			'menu_name'          => __( 'WSH Quiz', 'wsh-quiz' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-forms',
				'menu_position'       => 26,
				'supports'            => array( 'title', 'thumbnail' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	public static function columns( array $columns ) : array {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['wsh_quiz_schedule']  = __( 'Schedule', 'wsh-quiz' );
				$new['wsh_quiz_window']    = __( 'Active', 'wsh-quiz' );
				$new['wsh_quiz_questions'] = __( 'Questions', 'wsh-quiz' );
				$new['wsh_quiz_players']   = __( 'Players', 'wsh-quiz' );
				$new['wsh_quiz_actions']   = __( 'Actions', 'wsh-quiz' );
				$new['wsh_quiz_shortcode'] = __( 'Shortcode', 'wsh-quiz' );
			}
		}

		return $new;
	}

	public static function column_content( string $column, int $post_id ) : void {
		$data = wsh_quiz_get_data( $post_id );

		if ( 'wsh_quiz_schedule' === $column ) {
			echo esc_html( wsh_quiz_schedule_label( $data['schedule'] ) );
			return;
		}

		if ( 'wsh_quiz_window' === $column ) {
			$start = wsh_quiz_format_date( $data['start_date'] );
			$end   = wsh_quiz_format_date( $data['end_date'] );
			if ( $start || $end ) {
				echo esc_html( trim( $start . ' – ' . $end, ' –' ) );
			} else {
				echo '—';
			}
			return;
		}

		if ( 'wsh_quiz_questions' === $column ) {
			echo esc_html( (string) count( $data['questions'] ) );
			return;
		}

		if ( 'wsh_quiz_players' === $column ) {
			printf(
				'<span class="wsh-quiz-player-count" data-quiz-id="%1$d">%2$s</span>',
				(int) $post_id,
				esc_html( number_format_i18n( WSH_Quiz_Results::player_count( $post_id ) ) )
			);
			return;
		}

		if ( 'wsh_quiz_actions' === $column ) {
			WSH_Quiz_Admin::render_actions( $post_id, false );
			return;
		}

		if ( 'wsh_quiz_shortcode' === $column ) {
			printf(
				'<code>[wsh_quiz id="%d"]</code>',
				(int) $post_id
			);
		}
	}
}
