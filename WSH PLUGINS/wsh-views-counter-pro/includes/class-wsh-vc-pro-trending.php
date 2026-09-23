<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PRO module: Trending / Popular posts helper + shortcode.
 */
class WSH_VC_Pro_Trending {

	/**
	 * Init hooks.
	 */
	public static function init() {
		// Shortcode: [wsh_popular_posts]
		add_shortcode( 'wsh_popular_posts', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Generic helper used by widget + shortcode.
	 *
	 * Returns an array of post IDs.
	 *
	 * Args:
	 * - mode: popular|trending
	 * - limit: int
	 * - days: int
	 * - post_type: string
	 */
	public static function get_posts( $args = array() ) {
		$defaults = array(
			'mode'      => 'popular', // popular | trending
			'limit'     => 5,
			'days'      => 7,
			'post_type' => 'post',
		);

		$args = wp_parse_args( $args, $defaults );

		$mode      = $args['mode'];
		$limit     = (int) $args['limit'];
		$days      = (int) $args['days'];
		$post_type = sanitize_key( $args['post_type'] );

		if ( $limit <= 0 ) {
			$limit = 5;
		}
		if ( $days <= 0 ) {
			$days = 7;
		}

		// Allowed post types – reuse free plugin helper.
		if ( class_exists( 'WSH_Views_Counter' ) ) {
			$supported = WSH_Views_Counter::get_supported_post_types();
		} else {
			$supported = array( 'post', 'page', 'product' );
		}

		if ( ! in_array( $post_type, $supported, true ) ) {
			$post_type = 'post';
		}

		if ( 'trending' === $mode ) {
			return self::get_trending_post_ids( $post_type, $days, $limit );
		}

		// Default: popular – koristimo helper iz free plugina.
		if ( function_exists( 'wsh_views_counter_get_posts' ) ) {
			return wsh_views_counter_get_posts( $limit, $post_type, $days, 'popular' );
		}

		return array();
	}

	/**
	 * Basic "trending" formula based on weighted recent views.
	 *
	 * - views u zadnja 2 dana * 3
	 * - views u danima 3–7 * 1
	 */
	protected static function get_trending_post_ids( $post_type, $days, $limit ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wsh_views_counter';

		$today_ts   = current_time( 'timestamp' );
		$to_date    = gmdate( 'Y-m-d', $today_ts );
		$from_date  = gmdate( 'Y-m-d', strtotime( '-' . $days . ' days', $today_ts ) );
		$recent_cut = gmdate( 'Y-m-d', strtotime( '-2 days', $today_ts ) );

		$sql = $wpdb->prepare(
			"SELECT post_id,
					SUM(
						CASE 
							WHEN view_date >= %s THEN view_count * 3
							ELSE view_count
						END
					) AS score
			 FROM {$table}
			 WHERE post_type = %s
			   AND view_date BETWEEN %s AND %s
			 GROUP BY post_id
			 ORDER BY score DESC
			 LIMIT %d",
			$recent_cut,
			$post_type,
			$from_date,
			$to_date,
			$limit
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( empty( $rows ) ) {
			return array();
		}

		$ids = array();
		foreach ( $rows as $row ) {
			$ids[] = (int) $row['post_id'];
		}

		return $ids;
	}

	/**
	 * Shortcode renderer for [wsh_popular_posts].
	 *
	 * Attributes:
	 * - mode="popular|trending"
	 * - post_type="post"
	 * - days="7"
	 * - limit="5"
	 * - layout="list"
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'mode'      => 'popular',
				'post_type' => 'post',
				'days'      => 7,
				'limit'     => 5,
				'layout'    => 'list',
			),
			$atts,
			'wsh_popular_posts'
		);

		$ids = self::get_posts(
			array(
				'mode'      => $atts['mode'],
				'limit'     => (int) $atts['limit'],
				'days'      => (int) $atts['days'],
				'post_type' => $atts['post_type'],
			)
		);

		if ( empty( $ids ) ) {
			return '';
		}

		$query = new WP_Query(
			array(
				'post__in'            => $ids,
				'post_type'           => $atts['post_type'],
				'orderby'             => 'post__in',
				'posts_per_page'      => count( $ids ),
				'ignore_sticky_posts' => true,
			)
		);

		if ( ! $query->have_posts() ) {
			return '';
		}

		ob_start();

		echo '<div class="wsh-vc-pro-popular wsh-vc-pro-popular-shortcode layout-' . esc_attr( $atts['layout'] ) . '">';
		echo '<ul class="wsh-vc-pro-popular-list">';

		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();

			echo '<li class="wsh-vc-pro-popular-item">';
			echo '<a href="' . esc_url( get_permalink() ) . '">';
			echo esc_html( get_the_title() );
			echo '</a>';

			$views = 0;
			if ( method_exists( 'WSH_Views_Counter', 'get_post_views' ) ) {
				$views = (int) WSH_Views_Counter::get_post_views( $post_id );
			}
			if ( $views > 0 ) {
				echo ' <span class="wsh-vc-pro-popular-views">(' . esc_html( number_format_i18n( $views ) ) . ')</span>';
			}

			echo '</li>';
		}

		echo '</ul>';
		echo '</div>';

		wp_reset_postdata();

		return ob_get_clean();
	}
}
