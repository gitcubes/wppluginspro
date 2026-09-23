<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PRO module: Gutenberg blocks (Popular / Trending posts).
 */
class WSH_VC_Pro_Blocks {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
	}

	/**
	 * Register block type + editor script.
	 */
	public static function register_blocks() {

		// Gutenberg nije dostupan (veoma stari WP).
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Editor skripta za blok.
		wp_register_script(
			'wsh-vc-pro-blocks-popular',
			WSH_VC_PRO_URL . 'assets/js/blocks-popular.js',
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-editor', 'wp-block-editor' ),
			WSH_VC_PRO_VERSION,
			true
		);

		// Dozvoljeni post type-ovi iz free plugina, da ih JS dobije.
		$supported_types = array( 'post', 'page' );
		if ( class_exists( 'WSH_Views_Counter' ) && method_exists( 'WSH_Views_Counter', 'get_supported_post_types' ) ) {
			$supported_types = WSH_Views_Counter::get_supported_post_types();
		}

		wp_localize_script(
			'wsh-vc-pro-blocks-popular',
			'WSH_VC_Pro_Blocks_Data',
			array(
				'postTypes'    => $supported_types,
				'defaultTitle' => __( 'Popular Posts', 'wsh-views-counter-pro' ),
			)
		);

		// Dinamički blok – renderovanje ide preko PHP callback-a.
		register_block_type(
			'wsh-vc-pro/popular-posts',
			array(
				'editor_script'   => 'wsh-vc-pro-blocks-popular',
				'render_callback' => array( __CLASS__, 'render_popular_posts_block' ),
				'attributes'      => array(
					'title'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'mode'     => array(
						'type'    => 'string',
						'default' => 'popular', // popular | trending
					),
					'postType' => array(
						'type'    => 'string',
						'default' => 'post',
					),
					'days'     => array(
						'type'    => 'number',
						'default' => 7,
					),
					'limit'    => array(
						'type'    => 'number',
						'default' => 5,
					),
				),
			)
		);
	}

		/**
	 * Render callback za blok (frontend).
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_popular_posts_block( $attributes, $content ) {

		// Ako nemamo trending engine ili free plugin, nema smisla da renderujemo.
		if ( ! class_exists( 'WSH_VC_Pro_Trending' ) || ! class_exists( 'WSH_Views_Counter' ) ) {
			return '';
		}

		$atts = wp_parse_args(
			$attributes,
			array(
				'title'    => '',
				'mode'     => 'popular',
				'postType' => 'post',
				'days'     => 7,
				'limit'    => 5,
			)
		);

		$mode      = in_array( $atts['mode'], array( 'popular', 'trending' ), true ) ? $atts['mode'] : 'popular';
		$post_type = sanitize_key( $atts['postType'] );
		$days      = max( 1, (int) $atts['days'] );
		$limit     = max( 1, (int) $atts['limit'] );

		// Uzimamo SAMO ID-eve postova iz trending engine-a.
		$post_ids = WSH_VC_Pro_Trending::get_posts(
			array(
				'mode'      => $mode,
				'limit'     => $limit,
				'days'      => $days,
				'post_type' => $post_type,
			)
		);

		$post_ids = array_filter(
			array_map(
				'intval',
				is_array( $post_ids ) ? $post_ids : array()
			)
		);

		if ( empty( $post_ids ) ) {
			return ''; // ili možeš da vratiš poruku, ali za blok je lepše da bude prazan.
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

		if ( ! $query->have_posts() ) {
			return '';
		}

		ob_start();

		?>
		<div class="wsh-vc-pro-popular-block">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<h2 class="wsh-vc-pro-popular-block-title">
					<?php echo esc_html( $atts['title'] ); ?>
				</h2>
			<?php endif; ?>

			<ul class="wsh-vc-pro-popular-widget-list">
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					$post_id = get_the_ID();

					$views = 0;
					if ( method_exists( 'WSH_Views_Counter', 'get_post_views' ) ) {
						$views = (int) WSH_Views_Counter::get_post_views( $post_id );
					}
					?>
					<li class="wsh-vc-pro-popular-widget-item">
						<a href="<?php echo esc_url( get_permalink() ); ?>">
							<?php echo esc_html( get_the_title() ); ?>
						</a>
						<span class="wsh-vc-pro-popular-widget-views">
							(<?php echo esc_html( number_format_i18n( $views ) ); ?>)
						</span>
					</li>
				<?php endwhile; ?>
			</ul>
		</div>
		<?php

		wp_reset_postdata();

		return ob_get_clean();
	}

}
