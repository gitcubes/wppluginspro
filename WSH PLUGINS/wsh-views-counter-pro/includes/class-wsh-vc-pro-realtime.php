<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PRO module: Real-time Analytics (Active users & last hits).
 */
class WSH_VC_Pro_Realtime {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'wp_ajax_wsh_vc_pro_realtime_stats', array( __CLASS__, 'ajax_realtime_stats' ) );
	}

	/**
	 * Register "Real-time Analytics" submenu under the PRO menu.
	 */
	public static function register_menu() {

		$parent_slug = 'wsh_views_counter_pro'; // PRO top-level menu slug.
		$capability  = 'manage_options';

		add_submenu_page(
			$parent_slug,
			__( 'Real-time Analytics', 'wsh-views-counter-pro' ),
			__( 'Real-time Analytics', 'wsh-views-counter-pro' ),
			$capability,
			'wsh-vc-pro-realtime',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render main Real-time Analytics page (shell + initial data + JS).
	 */
	public static function render_page() {
		// License guard.
		if ( ! class_exists( 'WSH_VC_Pro_License' ) || ! WSH_VC_Pro_License::is_active() ) {
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'PRO features are locked. Please activate your license.', 'wsh-views-counter-pro' ) .
				'</p></div>';
			return;
		}

		// Window (in minutes) for "active users".
		$window = isset( $_GET['wsh_rt_window'] )
			? (int) $_GET['wsh_rt_window']
			: 5;

		if ( $window <= 0 ) {
			$window = 5;
		}

		// Initial stats for first render (AJAX će posle osvežavati).
		$stats = self::get_realtime_stats( $window );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Real-time Analytics (PRO)', 'wsh-views-counter-pro' ); ?></h1>

			<form method="get" style="margin-bottom: 15px;">
				<input type="hidden" name="page" value="wsh-vc-pro-realtime" />
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Active window', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<select name="wsh_rt_window">
								<?php
								$options = array( 3, 5, 10, 15 );
								foreach ( $options as $opt ) :
									?>
									<option value="<?php echo (int) $opt; ?>" <?php selected( $window, $opt ); ?>>
										<?php
										printf(
											/* translators: %d: minutes */
											esc_html__( 'Last %d minutes', 'wsh-views-counter-pro' ),
											(int) $opt
										);
										?>
									</option>
									<?php
								endforeach;
								?>
							</select>
							&nbsp;&nbsp;
							<button class="button button-primary">
								<?php esc_html_e( 'Apply', 'wsh-views-counter-pro' ); ?>
							</button>
							&nbsp;&nbsp;
							<span class="description">
								<?php esc_html_e( 'This window is used to calculate "active users right now".', 'wsh-views-counter-pro' ); ?>
							</span>
						</td>
					</tr>
					</tbody>
				</table>
			</form>

			<div id="wsh-vc-pro-rt-summary" style="display:flex; flex-wrap:wrap; gap:20px; margin-bottom:20px;">
				<div class="card" style="flex:1; min-width:220px;">
					<h2><?php esc_html_e( 'Active users now', 'wsh-views-counter-pro' ); ?></h2>
					<p style="font-size:26px; font-weight:bold; margin-top:5px;" id="wsh-rt-active-users">
						<?php echo isset( $stats['active_users'] ) ? (int) $stats['active_users'] : 0; ?>
					</p>
					<p class="description">
						<?php
						printf(
							/* translators: %d: minutes */
							esc_html__( 'Users with activity in the last %d minutes.', 'wsh-views-counter-pro' ),
							(int) $window
						);
						?>
					</p>
				</div>

				<div class="card" style="flex:1; min-width:220px;">
					<h2><?php esc_html_e( 'Hits in this window', 'wsh-views-counter-pro' ); ?></h2>
					<p style="font-size:26px; font-weight:bold; margin-top:5px;" id="wsh-rt-hits">
						<?php echo isset( $stats['hits_window'] ) ? (int) $stats['hits_window'] : 0; ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Total page views recorded in the active window.', 'wsh-views-counter-pro' ); ?>
					</p>
				</div>

				<div class="card" style="flex:1; min-width:220px;">
					<h2><?php esc_html_e( 'Distinct active pages', 'wsh-views-counter-pro' ); ?></h2>
					<p style="font-size:26px; font-weight:bold; margin-top:5px;" id="wsh-rt-active-pages">
						<?php echo isset( $stats['active_pages'] ) ? (int) $stats['active_pages'] : 0; ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'How many different posts/pages are currently active.', 'wsh-views-counter-pro' ); ?>
					</p>
				</div>
			</div>

			<div style="display:flex; flex-wrap:wrap; gap:20px;">
				<div style="flex:1; min-width:280px;">
					<h2><?php esc_html_e( 'Top Active Pages (now)', 'wsh-views-counter-pro' ); ?></h2>
					<div id="wsh-rt-top-posts">
						<?php self::render_top_posts_table( $stats ); ?>
					</div>
				</div>

				<div style="flex:1; min-width:280px;">
					<h2><?php esc_html_e( 'Live Feed (last hits)', 'wsh-views-counter-pro' ); ?></h2>
					<div id="wsh-rt-live-feed">
						<?php self::render_live_feed_table( $stats ); ?>
					</div>
				</div>
			</div>

			<p style="margin-top:15px; text-align:center; font-size:11px; color:#777;">
				<?php esc_html_e( 'Real-time data is based on the lightweight wsh_views_live_hits table updated on each page view.', 'wsh-views-counter-pro' ); ?>
			</p>
		</div>

		<script type="text/javascript">
		(function($){
			function wshVcProRealtimeRefresh() {
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					dataType: 'json',
					data: {
						action: 'wsh_vc_pro_realtime_stats',
						window: <?php echo (int) $window; ?>
					},
					success: function(response) {
						if (!response || !response.success || !response.data) {
							return;
						}
						var data = response.data;

						if (typeof data.active_users !== 'undefined') {
							$('#wsh-rt-active-users').text(data.active_users);
						}
						if (typeof data.hits_window !== 'undefined') {
							$('#wsh-rt-hits').text(data.hits_window);
						}
						if (typeof data.active_pages !== 'undefined') {
							$('#wsh-rt-active-pages').text(data.active_pages);
						}
						if (data.top_posts_html) {
							$('#wsh-rt-top-posts').html(data.top_posts_html);
						}
						if (data.live_feed_html) {
							$('#wsh-rt-live-feed').html(data.live_feed_html);
						}
					}
				});
			}

			// Initial auto-refresh every 10 seconds.
			setInterval(wshVcProRealtimeRefresh, 10000);
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * AJAX handler for refreshing real-time stats.
	 */
	public static function ajax_realtime_stats() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to view real-time stats.', 'wsh-views-counter-pro' ),
				),
				403
			);
		}

		if ( ! class_exists( 'WSH_VC_Pro_License' ) || ! WSH_VC_Pro_License::is_active() ) {
			wp_send_json_error(
				array(
					'message' => __( 'License is not active.', 'wsh-views-counter-pro' ),
				),
				403
			);
		}

		$window = isset( $_POST['window'] ) ? (int) $_POST['window'] : 5;
		if ( $window <= 0 ) {
			$window = 5;
		}

		$stats = self::get_realtime_stats( $window );

		ob_start();
		self::render_top_posts_table( $stats );
		$top_posts_html = ob_get_clean();

		ob_start();
		self::render_live_feed_table( $stats );
		$live_feed_html = ob_get_clean();

		$payload = array(
			'active_users'   => isset( $stats['active_users'] ) ? (int) $stats['active_users'] : 0,
			'hits_window'    => isset( $stats['hits_window'] ) ? (int) $stats['hits_window'] : 0,
			'active_pages'   => isset( $stats['active_pages'] ) ? (int) $stats['active_pages'] : 0,
			'top_posts_html' => $top_posts_html,
			'live_feed_html' => $live_feed_html,
		);

		wp_send_json_success( $payload );
	}

	/**
	 * Core query logic: read from wsh_views_live_hits.
	 *
	 * @param int $window Minutes.
	 * @return array
	 */
	protected static function get_realtime_stats( $window = 5 ) {
		global $wpdb;

		$live_table = $wpdb->prefix . 'wsh_views_live_hits';

		// Check table exists.
		$like  = $wpdb->esc_like( $live_table );
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$like
			)
		);

		if ( $found !== $live_table ) {
			return array(
				'active_users' => 0,
				'hits_window'  => 0,
				'active_pages' => 0,
				'top_posts'    => array(),
				'live_feed'    => array(),
			);
		}

		$window      = (int) $window;
		$now_ts      = current_time( 'timestamp' );
		$threshold_ts = $now_ts - ( $window * MINUTE_IN_SECONDS );
		$threshold   = date( 'Y-m-d H:i:s', $threshold_ts );

		// Active users = distinct sessions in window.
		$active_users = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT session_key)
				 FROM {$live_table}
				 WHERE view_time >= %s",
				$threshold
			)
		);

		// Hits in window.
		$hits_window = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$live_table}
				 WHERE view_time >= %s",
				$threshold
			)
		);

		// Top active posts in window.
		$top_posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id,
				        COUNT(*) AS hits,
				        COUNT(DISTINCT session_key) AS users
				 FROM {$live_table}
				 WHERE view_time >= %s
				   AND post_id > 0
				 GROUP BY post_id
				 ORDER BY users DESC, hits DESC
				 LIMIT 10",
				$threshold
			),
			ARRAY_A
		);

		$active_pages = ! empty( $top_posts ) ? count( $top_posts ) : 0;

		// Live feed (last 20 hits, regardless of window).
		$live_feed = $wpdb->get_results(
			"SELECT *
			 FROM {$live_table}
			 ORDER BY view_time DESC
			 LIMIT 20",
			ARRAY_A
		);

		return array(
			'active_users' => $active_users,
			'hits_window'  => $hits_window,
			'active_pages' => $active_pages,
			'top_posts'    => $top_posts,
			'live_feed'    => $live_feed,
		);
	}

	/**
	 * Render table with top active posts (server-side + for AJAX HTML).
	 *
	 * @param array $stats
	 * @return void
	 */
	protected static function render_top_posts_table( $stats ) {

		$top_posts = isset( $stats['top_posts'] ) && is_array( $stats['top_posts'] )
			? $stats['top_posts']
			: array();

		if ( empty( $top_posts ) ) {
			echo '<p>' . esc_html__( 'No active pages in this window.', 'wsh-views-counter-pro' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Page', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:90px; text-align:right;"><?php esc_html_e( 'Users', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:90px; text-align:right;"><?php esc_html_e( 'Hits', 'wsh-views-counter-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $top_posts as $row ) : ?>
					<tr>
						<td>
							<?php
							$post_id = (int) $row['post_id'];
							$title   = get_the_title( $post_id );
							if ( '' === $title ) {
								$title = sprintf( __( 'Post #%d', 'wsh-views-counter-pro' ), $post_id );
							}
							$link = get_permalink( $post_id );
							?>
							<a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $title ); ?>
							</a>
						</td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( (int) $row['users'] ) ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( (int) $row['hits'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render live feed table (last hits).
	 *
	 * @param array $stats
	 * @return void
	 */
	protected static function render_live_feed_table( $stats ) {

		$live_feed = isset( $stats['live_feed'] ) && is_array( $stats['live_feed'] )
			? $stats['live_feed']
			: array();

		if ( empty( $live_feed ) ) {
			echo '<p>' . esc_html__( 'No live hits recorded yet.', 'wsh-views-counter-pro' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'wsh-views-counter-pro' ); ?></th>
					<th><?php esc_html_e( 'Page', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:120px;"><?php esc_html_e( 'Referrer type', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:90px;"><?php esc_html_e( 'Country', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:80px; text-align:center;"><?php esc_html_e( 'Device', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:80px; text-align:center;"><?php esc_html_e( 'User', 'wsh-views-counter-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $live_feed as $row ) : ?>
					<tr>
						<td>
							<?php
							$ts = strtotime( $row['view_time'] );
							echo esc_html( date_i18n( 'H:i:s', $ts ) );
							?>
						</td>
						<td>
							<?php
							$post_id = (int) $row['post_id'];
							$title   = $post_id ? get_the_title( $post_id ) : '';
							if ( '' === $title && $post_id ) {
								$title = sprintf( __( 'Post #%d', 'wsh-views-counter-pro' ), $post_id );
							}
							if ( $post_id ) {
								$link = get_permalink( $post_id );
								?>
								<a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $title ); ?>
								</a>
								<?php
							} else {
								echo '&mdash;';
							}
							?>
						</td>
						<td>
							<?php echo esc_html( self::get_ref_type_label( $row['ref_type'] ) ); ?>
							<?php
							if ( ! empty( $row['ref_domain'] ) ) {
								echo '<br><span class="description">' . esc_html( $row['ref_domain'] ) . '</span>';
							}
							?>
						</td>
						<td>
							<?php echo esc_html( strtoupper( (string) $row['country_code'] ) ); ?>
						</td>
						<td style="text-align:center;">
							<?php
							$is_mobile = (int) $row['is_mobile'] === 1;
							echo $is_mobile
								? esc_html__( 'Mobile', 'wsh-views-counter-pro' )
								: esc_html__( 'Desktop', 'wsh-views-counter-pro' );
							?>
						</td>
						<td style="text-align:center;">
							<?php
							$is_logged = (int) $row['is_logged'] === 1;
							echo $is_logged
								? esc_html__( 'Logged-in', 'wsh-views-counter-pro' )
								: esc_html__( 'Guest', 'wsh-views-counter-pro' );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Small helper – same mapping as in Referrer Analytics.
	 *
	 * @param string $type
	 * @return string
	 */
	protected static function get_ref_type_label( $type ) {
		$type = (string) $type;

		switch ( $type ) {
			case 'search':
				return __( 'Search', 'wsh-views-counter-pro' );
			case 'social':
				return __( 'Social', 'wsh-views-counter-pro' );
			case 'direct':
				return __( 'Direct', 'wsh-views-counter-pro' );
			case 'internal':
				return __( 'Internal', 'wsh-views-counter-pro' );
			case 'referral':
				return __( 'Referral', 'wsh-views-counter-pro' );
			default:
				return $type !== '' ? ucfirst( $type ) : __( 'Unknown', 'wsh-views-counter-pro' );
		}
	}
}
