<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PRO widget: Popular / Trending posts.
 */
class WSH_VC_Pro_Widget_Popular extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'wsh_vc_pro_popular',
			__( 'WSH Popular Posts PRO', 'wsh-views-counter-pro' ),
			array(
				'description' => __(
					'Display most popular or trending posts based on WSH Views Counter data. Shortcode: [wsh_popular_posts], [wsh_popular_posts mode="trending" post_type="post" days="7" limit="5"]',
					'wsh-views-counter-pro'
				),
			)
		);
	}

	/**
	 * Hook to widgets_init.
	 */
	public static function init() {
		add_action(
			'widgets_init',
			function() {
				register_widget( 'WSH_VC_Pro_Widget_Popular' );
			}
		);
	}

	/**
	 * Frontend display.
	 */
	public function widget( $args, $instance ) {

		$title     = isset( $instance['title'] ) ? $instance['title'] : '';
		$mode      = isset( $instance['mode'] ) ? $instance['mode'] : 'popular'; // popular|trending
		$post_type = isset( $instance['post_type'] ) ? $instance['post_type'] : 'post';
		$days      = isset( $instance['days'] ) ? (int) $instance['days'] : 7;
		$limit     = isset( $instance['limit'] ) ? (int) $instance['limit'] : 5;

		echo isset( $args['before_widget'] ) ? $args['before_widget'] : '';

		if ( ! empty( $title ) && isset( $args['before_title'], $args['after_title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $title ) . $args['after_title'];
		}

		if ( ! class_exists( 'WSH_VC_Pro_Trending' ) ) {
			echo '<p>' . esc_html__( 'Trending engine is not available.', 'wsh-views-counter-pro' ) . '</p>';
			echo isset( $args['after_widget'] ) ? $args['after_widget'] : '';
			return;
		}

		// >>> OVDJE SE UZIMAJU SAMO INT ID-evi POSTOVA <<<
		$post_ids = WSH_VC_Pro_Trending::get_posts(
			array(
				'mode'      => $mode,
				'limit'     => $limit,
				'days'      => $days,
				'post_type' => $post_type,
			)
		);

		// Ako je neko od filtera vratio nešto čudno, očistimo na int.
		$post_ids = array_filter(
			array_map(
				'intval',
				is_array( $post_ids ) ? $post_ids : array()
			)
		);

		if ( empty( $post_ids ) ) {
			echo '<p>' . esc_html__( 'No posts found for the selected criteria.', 'wsh-views-counter-pro' ) . '</p>';
			echo isset( $args['after_widget'] ) ? $args['after_widget'] : '';
			return;
		}

		$query = new WP_Query(
			array(
				'post__in'            => $post_ids,
				'post_type'           => $post_type,
				'orderby'             => 'post__in',
				'posts_per_page'      => count( $post_ids ),
				'ignore_sticky_posts' => true,
			)
		);

		if ( $query->have_posts() ) {
			echo "<style type='text/css'>ul.wsh-vc-pro-popular-widget-list li { padding-bottom: 10px; } </style>";
			echo '<ul class="wsh-vc-pro-popular-widget-list">';
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id = get_the_ID();

				echo '<li class="wsh-vc-pro-popular-widget-item">';
				echo '<a href="' . esc_url( get_permalink() ) . '">';
				echo esc_html( get_the_title() );
				echo '</a>';

				$views = 0;
				if ( method_exists( 'WSH_Views_Counter', 'get_post_views' ) ) {
					$views = (int) WSH_Views_Counter::get_post_views( $post_id );
				}

				echo ' <span class="wsh-vc-pro-popular-widget-views">('
					 . esc_html( number_format_i18n( $views ) )
					 . ')</span>';

				echo '</li>';
			}
			echo '</ul>';

			wp_reset_postdata();
		}

		echo isset( $args['after_widget'] ) ? $args['after_widget'] : '';
	}

	/**
	 * Backend form.
	 */
	public function form( $instance ) {

		$title     = isset( $instance['title'] ) ? $instance['title'] : __( 'Popular Posts', 'wsh-views-counter-pro' );
		$mode      = isset( $instance['mode'] ) ? $instance['mode'] : 'popular';
		$post_type = isset( $instance['post_type'] ) ? $instance['post_type'] : 'post';
		$days      = isset( $instance['days'] ) ? (int) $instance['days'] : 7;
		$limit     = isset( $instance['limit'] ) ? (int) $instance['limit'] : 5;

		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'wsh-views-counter-pro' ); ?>
			</label>
			<input class="widefat"
				   id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				   type="text"
				   value="<?php echo esc_attr( $title ); ?>" />
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'mode' ) ); ?>">
				<?php esc_html_e( 'Mode:', 'wsh-views-counter-pro' ); ?>
			</label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'mode' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'mode' ) ); ?>"
					class="widefat">
				<option value="popular" <?php selected( $mode, 'popular' ); ?>>
					<?php esc_html_e( 'Most popular', 'wsh-views-counter-pro' ); ?>
				</option>
				<option value="trending" <?php selected( $mode, 'trending' ); ?>>
					<?php esc_html_e( 'Trending (last days boost)', 'wsh-views-counter-pro' ); ?>
				</option>
			</select>
		</p>

		<?php
		// Dozvoljeni post types iz free plugina.
		$supported_types = array( 'post', 'page');
		if ( class_exists( 'WSH_Views_Counter' ) && method_exists( 'WSH_Views_Counter', 'get_supported_post_types' ) ) {
			$supported_types = WSH_Views_Counter::get_supported_post_types();
		}
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'post_type' ) ); ?>">
				<?php esc_html_e( 'Post type:', 'wsh-views-counter-pro' ); ?>
			</label>
			<select
				id="<?php echo esc_attr( $this->get_field_id( 'post_type' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'post_type' ) ); ?>"
				class="widefat"
			>
				<?php foreach ( $supported_types as $pt ) : ?>
					<option value="<?php echo esc_attr( $pt ); ?>" <?php selected( $post_type, $pt ); ?>>
						<?php echo esc_html( $pt ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'days' ) ); ?>">
				<?php esc_html_e( 'Number of days to look back:', 'wsh-views-counter-pro' ); ?>
			</label>
			<input class="tiny-text"
				   id="<?php echo esc_attr( $this->get_field_id( 'days' ) ); ?>"
				   name="<?php echo esc_attr( $this->get_field_name( 'days' ) ); ?>"
				   type="number"
				   step="1"
				   min="1"
				   value="<?php echo esc_attr( $days ); ?>" />
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>">
				<?php esc_html_e( 'Number of posts to show:', 'wsh-views-counter-pro' ); ?>
			</label>
			<input class="tiny-text"
				   id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"
				   name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>"
				   type="number"
				   step="1"
				   min="1"
				   value="<?php echo esc_attr( $limit ); ?>" />
		</p>

		<p>
			<small>
				<?php esc_html_e( 'You can also use the shortcode:', 'wsh-views-counter-pro' ); ?>
				<code>[wsh_popular_posts]</code>
			</small>
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance              = array();
		$instance['title']     = sanitize_text_field( $new_instance['title'] );
		$instance['mode']      = in_array( $new_instance['mode'], array( 'popular', 'trending' ), true ) ? $new_instance['mode'] : 'popular';
		$instance['post_type'] = sanitize_key( $new_instance['post_type'] );
		$instance['days']      = max( 1, (int) $new_instance['days'] );
		$instance['limit']     = max( 1, (int) $new_instance['limit'] );

		return $instance;
	}
}
