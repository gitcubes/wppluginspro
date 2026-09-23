<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sidebar promo for the latest playable quiz.
 */
class WSH_Quiz_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'wsh_quiz_promo',
			__( 'WSH Quiz promo', 'wsh-quiz' ),
			array(
				'description' => __( 'Promote a quiz in the sidebar with a slogan and a play link.', 'wsh-quiz' ),
			)
		);
	}

	public static function register() : void {
		register_widget( __CLASS__ );
	}

	public function widget( $args, $instance ) : void {
		$instance = $this->normalize( $instance );
		$quiz_id  = (int) $instance['quiz_id'];

		if ( $quiz_id < 1 ) {
			$quiz_id = wsh_quiz_get_latest_playable_id();
		}

		if ( $quiz_id < 1 ) {
			return;
		}

		$quiz = get_post( $quiz_id );
		if ( ! $quiz || 'wsh_quiz' !== $quiz->post_type || 'publish' !== $quiz->post_status ) {
			return;
		}

		$data         = wsh_quiz_get_data( $quiz_id );
		$availability = wsh_quiz_availability( $data );
		if ( empty( $availability['available'] ) ) {
			return;
		}

		$sponsor = wsh_quiz_get_sponsor( $data );
		$title   = $instance['title'] ? $instance['title'] : get_the_title( $quiz );
		$slogan  = $instance['slogan'];
		$button  = $instance['button'] ? $instance['button'] : __( 'Start the quiz', 'wsh-quiz' );
		$brand   = wsh_quiz_get_global_settings();

		if ( '' === $slogan && ! empty( $brand['slogan'] ) ) {
			$slogan = (string) $brand['slogan'];
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<div class="wsh-quiz-widget">';

		if ( $slogan ) {
			echo '<p class="wsh-quiz-widget__slogan">' . esc_html( $slogan ) . '</p>';
		}

		$featured = wsh_quiz_get_featured_url( $quiz_id, 'medium' );
		if ( $featured ) {
			echo '<p class="wsh-quiz-widget__media"><a href="' . esc_url( wsh_quiz_get_play_url( $quiz_id ) ) . '"><img src="' . esc_url( $featured ) . '" alt="" /></a></p>';
		}

		echo '<p class="wsh-quiz-widget__quiz">' . esc_html( get_the_title( $quiz ) ) . '</p>';

		if ( $sponsor['logo'] || $sponsor['name'] ) {
			echo '<div class="wsh-quiz-widget__sponsor">';
			echo '<p class="wsh-quiz-widget__sponsor-label">' . esc_html__( 'Quiz sponsor', 'wsh-quiz' ) . '</p>';
			if ( $sponsor['logo'] ) {
				echo '<img src="' . esc_url( $sponsor['logo'] ) . '" alt="' . esc_attr( $sponsor['name'] ) . '" />';
			} elseif ( $sponsor['name'] ) {
				echo '<p class="wsh-quiz-widget__sponsor-name">' . esc_html( $sponsor['name'] ) . '</p>';
			}
			echo '</div>';
		}

		echo '<p class="wsh-quiz-widget__actions">';
		echo '<a class="wsh-quiz-widget__play" href="' . esc_url( wsh_quiz_get_play_url( $quiz_id ) ) . '">' . esc_html( $button ) . '</a>';
		if ( ! empty( $data['leaderboard'] ) ) {
			echo '<a class="wsh-quiz-widget__results" href="' . esc_url( wsh_quiz_get_results_url( $quiz_id ) ) . '">' . esc_html__( 'View results', 'wsh-quiz' ) . '</a>';
		}
		echo '</p>';
		echo '<p class="wsh-quiz-widget__all"><a href="' . esc_url( wsh_quiz_get_list_url() ) . '">' . esc_html__( 'All quizzes', 'wsh-quiz' ) . '</a></p>';
		echo '</div>';

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function form( $instance ) : void {
		$instance = $this->normalize( $instance );
		$quizzes  = get_posts(
			array(
				'post_type'      => 'wsh_quiz',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title', 'wsh-quiz' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'slogan' ) ); ?>"><?php esc_html_e( 'Slogan', 'wsh-quiz' ); ?></label>
			<textarea class="widefat" rows="3" id="<?php echo esc_attr( $this->get_field_id( 'slogan' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'slogan' ) ); ?>"><?php echo esc_textarea( $instance['slogan'] ); ?></textarea>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'button' ) ); ?>"><?php esc_html_e( 'Button text', 'wsh-quiz' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'button' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'button' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['button'] ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'quiz_id' ) ); ?>"><?php esc_html_e( 'Quiz', 'wsh-quiz' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'quiz_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'quiz_id' ) ); ?>">
				<option value="0" <?php selected( 0, (int) $instance['quiz_id'] ); ?>><?php esc_html_e( 'Latest playable quiz', 'wsh-quiz' ); ?></option>
				<?php foreach ( $quizzes as $quiz ) : ?>
					<option value="<?php echo (int) $quiz->ID; ?>" <?php selected( (int) $instance['quiz_id'], (int) $quiz->ID ); ?>>
						<?php echo esc_html( $quiz->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) : array {
		return $this->normalize( $new_instance );
	}

	protected function normalize( $instance ) : array {
		$instance = is_array( $instance ) ? $instance : array();

		return array(
			'title'   => isset( $instance['title'] ) ? sanitize_text_field( $instance['title'] ) : __( 'Quiz', 'wsh-quiz' ),
			'slogan'  => isset( $instance['slogan'] ) ? sanitize_textarea_field( $instance['slogan'] ) : '',
			'button'  => isset( $instance['button'] ) ? sanitize_text_field( $instance['button'] ) : __( 'Start the quiz', 'wsh-quiz' ),
			'quiz_id' => isset( $instance['quiz_id'] ) ? (int) $instance['quiz_id'] : 0,
		);
	}
}
