<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PRO module: Developer / Integrations
 *
 * - REST API ( /wp-json/wsh-views/v1/... )
 * - JS event API (CustomEvent on view)
 * - PHP helper functions
 * - Webhook helper (for viral / custom events)
 */
class WSH_VC_Pro_Developer {

	/**
	 * Boot hooks.
	 */
	public static function init() {
		// REST API endpoints.
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );

		// JS event API (frontend).
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_js_event_api' ), 20 );

		// Webhook: primer – zakači se na viral detection ako klasa postoji.
		add_action( 'wsh_vc_pro_viral_trigger', array( __CLASS__, 'handle_viral_webhook' ), 10, 3 );

        // Admin stranica "For Developers".
	    add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

    
    /**
     * Register "For Developers" submenu under PRO menu.
     */
    public static function register_menu() {

        $parent_slug = 'wsh_views_counter_pro'; // glavni PRO meni.
        $capability  = 'manage_options';

        add_submenu_page(
            $parent_slug,
            __( 'Developer Integrations', 'wsh-views-counter-pro' ),
            __( 'For Developers', 'wsh-views-counter-pro' ),
            $capability,
            'wsh-vc-pro-developer',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Render Developer docs page.
     */
    public static function render_page() {

        // Mala PRO zaštita.
        if ( ! class_exists( 'WSH_VC_Pro_License' ) || ! WSH_VC_Pro_License::is_active() ) {
            echo '<div class="notice notice-warning"><p>' .
                esc_html__( 'PRO features are locked. Please activate your license to use developer integrations.', 'wsh-views-counter-pro' ) .
                '</p></div>';
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'WSH Views Counter – Developer Integrations', 'wsh-views-counter-pro' ); ?></h1>

            <p class="description">
                <?php esc_html_e( 'Use these APIs and helper functions to integrate WSH Views Counter PRO with your theme, custom dashboards or external services.', 'wsh-views-counter-pro' ); ?>
            </p>

            <hr />

            <h2><?php esc_html_e( '1. REST API', 'wsh-views-counter-pro' ); ?></h2>
            <p>
                <?php esc_html_e( 'Base URL:', 'wsh-views-counter-pro' ); ?>
                <code><?php echo esc_html( rest_url( 'wsh-views/v1/' ) ); ?></code>
            </p>

            <h3><?php esc_html_e( 'Endpoint: Single post stats', 'wsh-views-counter-pro' ); ?></h3>
            <p><code>/wp-json/wsh-views/v1/post/&lt;ID&gt;?days=30</code></p>
            <ul>
                <li><code>id</code> – <?php esc_html_e( 'Post ID.', 'wsh-views-counter-pro' ); ?></li>
                <li><code>days</code> – <?php esc_html_e( 'How many days back for daily stats (default 30).', 'wsh-views-counter-pro' ); ?></li>
            </ul>

            <h3><?php esc_html_e( 'Endpoint: Top / trending posts', 'wsh-views-counter-pro' ); ?></h3>
            <p><code>/wp-json/wsh-views/v1/top-posts?post_type=post&amp;days=7&amp;limit=10&amp;mode=trending</code></p>
            <ul>
                <li><code>post_type</code> – <code>post</code>, <code>page</code>, <code>product</code>…</li>
                <li><code>days</code> – <?php esc_html_e( 'Number of days back.', 'wsh-views-counter-pro' ); ?></li>
                <li><code>limit</code> – <?php esc_html_e( 'Number of posts to return.', 'wsh-views-counter-pro' ); ?></li>
                <li><code>mode</code> – <code>popular</code> / <code>trending</code></li>
            </ul>

            <hr />

            <h2><?php esc_html_e( '2. JS Event API', 'wsh-views-counter-pro' ); ?></h2>
            <p>
                <?php esc_html_e( 'On every recorded view, the plugin dispatches a global CustomEvent on window:', 'wsh-views-counter-pro' ); ?>
            </p>
            <pre><code>window.addEventListener('wshViewsCounter:view', function (e) {
                console.log('View for post', e.detail.postId);
                // send pixel, analytics event, custom tracking...
            });</code></pre>

            <hr />

            <h2><?php esc_html_e( '3. PHP helper functions', 'wsh-views-counter-pro' ); ?></h2>

            <h3><code>wsh_vc_pro_get_trending_posts()</code></h3>
            <pre><code>$ids = wsh_vc_pro_get_trending_posts( array(
                'post_type' =&gt; 'post',
                'days'      =&gt; 7,
                'limit'     =&gt; 5,
            ) );

            $query = new WP_Query( array(
                'post__in'       =&gt; $ids,
                'orderby'        =&gt; 'post__in',
                'posts_per_page' =&gt; 5,
            ) );</code></pre>

                    <h3><code>wsh_vc_pro_get_post_stats()</code></h3>
            <pre><code>$stats = wsh_vc_pro_get_post_stats( get_the_ID(), 30 );

            $total = $stats['total'];   // total views
            $daily = $stats['daily'];   // date =&gt; views</code></pre>

            <hr />

            <h2><?php esc_html_e( '4. Webhooks (e.g. Viral Detection)', 'wsh-views-counter-pro' ); ?></h2>
            <p>
                <?php esc_html_e( 'When a post is detected as viral, the plugin fires the action', 'wsh-views-counter-pro' ); ?>
                <code>do_action( 'wsh_vc_pro_viral_trigger', $post_id, $score, $context );</code>
            </p>
            <p>
                <?php esc_html_e( 'You can hook your own webhooks by returning one or more endpoints:', 'wsh-views-counter-pro' ); ?>
            </p>
            <pre><code>add_filter( 'wsh_vc_pro_webhook_endpoints', function( $endpoints ) {
                $endpoints[] = 'https://example.com/my-webhook';
                return $endpoints;
            });</code></pre>

            <p class="description">
                <?php esc_html_e( 'The plugin will POST a JSON payload with post ID, title, permalink, viral score and context to each endpoint.', 'wsh-views-counter-pro' ); ?>
            </p>

        </div>
        <?php
    }


	/**
	 * Register REST API routes under /wp-json/wsh-views/v1/...
	 */
	public static function register_rest_routes() {

		// Basic post stats.
		register_rest_route(
			'wsh-views/v1',
			'/post/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_get_post_stats' ),
				'permission_callback' => array( __CLASS__, 'rest_permissions' ),
				'args'                => array(
					'id'   => array(
						'description' => 'Post ID',
						'type'        => 'integer',
						'required'    => true,
					),
					'days' => array(
						'description' => 'Number of days back for daily stats',
						'type'        => 'integer',
						'required'    => false,
						'default'     => 30,
					),
				),
			)
		);

		// Top posts / trending posts.
		register_rest_route(
			'wsh-views/v1',
			'/top-posts',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_get_top_posts' ),
				'permission_callback' => array( __CLASS__, 'rest_permissions' ),
				'args'                => array(
					'post_type' => array(
						'description' => 'Post type (post, page, product...)',
						'type'        => 'string',
						'required'    => false,
						'default'     => 'post',
					),
					'days'      => array(
						'description' => 'Number of days back',
						'type'        => 'integer',
						'required'    => false,
						'default'     => 7,
					),
					'limit'     => array(
						'description' => 'Number of posts to return',
						'type'        => 'integer',
						'required'    => false,
						'default'     => 10,
					),
					'mode'      => array(
						'description' => 'popular|trending',
						'type'        => 'string',
						'required'    => false,
						'default'     => 'popular',
					),
				),
			)
		);
	}

	/**
	 * Permission callback – only logged-in users with edit_posts
	 * and active PRO license can use REST endpoints.
	 */
	public static function rest_permissions( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}
		if ( ! class_exists( 'WSH_VC_Pro_License' ) || ! WSH_VC_Pro_License::is_active() ) {
			return false;
		}
		return true;
	}

	/**
	 * REST: single post stats.
	 * - total views (meta)
	 * - daily views for last X days (from wsh_views_counter)
	 */
	public static function rest_get_post_stats( WP_REST_Request $request ) {
		global $wpdb;

		$post_id = (int) $request->get_param( 'id' );
		$days    = max( 1, (int) $request->get_param( 'days' ) );

		if ( $post_id <= 0 ) {
			return new WP_Error( 'wsh_vc_invalid_id', __( 'Invalid post ID.', 'wsh-views-counter-pro' ), array( 'status' => 400 ) );
		}

		// Total views via helper (free plugin).
		$total_views = 0;
		if ( class_exists( 'WSH_Views_Counter' ) && method_exists( 'WSH_Views_Counter', 'get_post_views' ) ) {
			$total_views = (int) WSH_Views_Counter::get_post_views( $post_id );
		}

		// Daily views from log table.
		$table = $wpdb->prefix . 'wsh_views_counter';

		$from_date = gmdate( 'Y-m-d', strtotime( '-' . $days . ' days' ) );
		$to_date   = gmdate( 'Y-m-d' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT view_date, SUM(view_count) AS views
				 FROM {$table}
				 WHERE post_id = %d
				   AND view_date BETWEEN %s AND %s
				 GROUP BY view_date
				 ORDER BY view_date ASC",
				$post_id,
				$from_date,
				$to_date
			),
			ARRAY_A
		);

		$daily = array();
		foreach ( (array) $rows as $row ) {
			$daily[ $row['view_date'] ] = (int) $row['views'];
		}

		return array(
			'post_id'     => $post_id,
			'total_views' => $total_views,
			'daily'       => $daily,
		);
	}

	/**
	 * REST: top posts (popular or trending).
	 */
	public static function rest_get_top_posts( WP_REST_Request $request ) {

		$post_type = sanitize_key( $request->get_param( 'post_type' ) );
		$days      = max( 1, (int) $request->get_param( 'days' ) );
		$limit     = max( 1, (int) $request->get_param( 'limit' ) );
		$mode      = $request->get_param( 'mode' );

		$mode = in_array( $mode, array( 'popular', 'trending' ), true ) ? $mode : 'popular';

		$post_ids = array();

		// 1) Trending engine (PRO).
		if ( 'trending' === $mode && class_exists( 'WSH_VC_Pro_Trending' ) ) {
			$post_ids = WSH_VC_Pro_Trending::get_posts(
				array(
					'mode'      => 'trending',
					'limit'     => $limit,
					'days'      => $days,
					'post_type' => $post_type,
				)
			);
		} else {
			// 2) Popular via free helper.
			if ( function_exists( 'wsh_views_counter_get_posts' ) ) {
				$post_ids = wsh_views_counter_get_posts( $limit, $post_type, $days, 'popular' );
			}
		}

		$post_ids = array_filter(
			array_map( 'intval', is_array( $post_ids ) ? $post_ids : array() )
		);

		$data = array();
		foreach ( $post_ids as $pid ) {
			$data[] = array(
				'id'    => $pid,
				'title' => get_the_title( $pid ),
				'link'  => get_permalink( $pid ),
			);
		}

		return array(
			'post_type' => $post_type,
			'mode'      => $mode,
			'days'      => $days,
			'limit'     => $limit,
			'items'     => $data,
		);
	}

	/**
	 * Enqueue JS that dispatches a CustomEvent('wshViewsCounter:view') on each page view.
	 */
	public static function enqueue_js_event_api() {

		// Work only on frontend.
		if ( is_admin() ) {
			return;
		}

		// Free plugin JS mora da postoji (post_id se prosledjuje preko wshViewsCounter global-a).
		if ( ! wp_script_is( 'wsh-views-counter-cvc-js', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_script(
			'wsh-vc-pro-dev-events',
			WSH_VC_PRO_URL . 'assets/js/wsh-vc-pro-dev-events.js',
			array( 'wsh-views-counter-cvc-js' ),
			WSH_VC_PRO_VERSION,
			true
		);
	}

	/**
	 * Handle viral webhook: called when Viral Engine trigeruje akciju.
	 *
	 * @param int   $post_id  Post ID.
	 * @param float $score    Viral score.
	 * @param array $context  Additional data.
	 */
	public static function handle_viral_webhook( $post_id, $score, $context = array() ) {

		$post_id = (int) $post_id;
		$score   = (float) $score;

		// Developers can define one or more webhook URLs via filter.
		$endpoints = apply_filters( 'wsh_vc_pro_webhook_endpoints', array() );
		$endpoints = array_filter( (array) $endpoints );

		if ( empty( $endpoints ) || $post_id <= 0 ) {
			return;
		}

		$payload = array(
			'event'   => 'viral_detected',
			'post_id' => $post_id,
			'title'   => get_the_title( $post_id ),
			'link'    => get_permalink( $post_id ),
			'score'   => $score,
			'context' => $context,
		 );

		foreach ( $endpoints as $url ) {
			$url = esc_url_raw( $url );
			if ( empty( $url ) ) {
				continue;
			}

			wp_remote_post(
				$url,
				array(
					'timeout' => 5,
					'blocking'=> false,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode( $payload ),
				)
			);
		}
	}
}

/**
 * ===== PHP helper functions (developer friendly) =====
 */

/**
 * Get trending posts (IDs) via PRO engine.
 *
 * @param array $args {
 *     @type string $post_type Post type (default 'post').
 *     @type int    $days      Number of days back (default 7).
 *     @type int    $limit     Number of posts (default 10).
 * }
 * @return int[] Array of post IDs.
 */
function wsh_vc_pro_get_trending_posts( $args = array() ) {
	$defaults = array(
		'post_type' => 'post',
		'days'      => 7,
		'limit'     => 10,
	);
	$args = wp_parse_args( $args, $defaults );

	if ( ! class_exists( 'WSH_VC_Pro_Trending' ) ) {
		return array();
	}

	$ids = WSH_VC_Pro_Trending::get_posts(
		array(
			'mode'      => 'trending',
			'limit'     => (int) $args['limit'],
			'days'      => (int) $args['days'],
			'post_type' => sanitize_key( $args['post_type'] ),
		)
	);

	return array_filter(
		array_map( 'intval', is_array( $ids ) ? $ids : array() )
	);
}

/**
 * Get basic stats for a post (total + last X days array).
 *
 * @param int $post_id Post ID.
 * @param int $days    Days back (default 30).
 * @return array {
 *    @type int   $total Total views.
 *    @type array $daily Associative array date => views.
 * }
 */
function wsh_vc_pro_get_post_stats( $post_id, $days = 30 ) {
	global $wpdb;

	$post_id = (int) $post_id;
	$days    = max( 1, (int) $days );

	if ( $post_id <= 0 ) {
		return array(
			'total' => 0,
			'daily' => array(),
		);
	}

	$total = 0;
	if ( class_exists( 'WSH_Views_Counter' ) && method_exists( 'WSH_Views_Counter', 'get_post_views' ) ) {
		$total = (int) WSH_Views_Counter::get_post_views( $post_id );
	}

	$table     = $wpdb->prefix . 'wsh_views_counter';
	$from_date = gmdate( 'Y-m-d', strtotime( '-' . $days . ' days' ) );
	$to_date   = gmdate( 'Y-m-d' );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT view_date, SUM(view_count) AS views
			 FROM {$table}
			 WHERE post_id = %d
			   AND view_date BETWEEN %s AND %s
			 GROUP BY view_date
			 ORDER BY view_date ASC",
			$post_id,
			$from_date,
			$to_date
		),
		ARRAY_A
	);

	$daily = array();
	foreach ( (array) $rows as $row ) {
		$daily[ $row['view_date'] ] = (int) $row['views'];
	}

	return array(
		'total' => $total,
		'daily' => $daily,
	);
}
