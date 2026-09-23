<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PRO module: Advanced Charts & Heatmaps.
 */
class WSH_VC_Pro_Charts {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Register submenu under PRO menu.
	 */
	public static function register_menu() {

		$parent_slug = 'wsh_views_counter_pro'; // PRO license page slug.
		$capability  = 'manage_options';

		add_submenu_page(
			$parent_slug,
			__( 'Advanced Charts & Heatmaps', 'wsh-views-counter-pro' ),
			__( 'Charts & Heatmaps', 'wsh-views-counter-pro' ),
			$capability,
			'wsh-vc-pro-charts',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue JS only on our page.
	 */
	public static function enqueue_assets( $hook ) {

		// Jednostavnije: proverimo "page" preko $_GET, ovo je 100% sigurno.
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'wsh-vc-pro-charts' !== $current_page ) {
			return;
		}

		// Chart.js sa CDN-a (možeš kasnije prebaciti lokalno).
		wp_enqueue_script(
			'wsh-vc-pro-chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js',
			array(),
			'4.4.0',
			true
		);
	}


	/**
	 * Render Charts & Heatmaps page.
	 */
	public static function render_page() {
		global $wpdb;

		// Extra license guard.
		if ( ! WSH_VC_Pro_License::is_active() ) {
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'PRO features are locked. Please activate your license.', 'wsh-views-counter-pro' ) .
				'</p></div>';
			return;
		}

		$table_main = $wpdb->prefix . 'wsh_views_counter';
		$table_live = $wpdb->prefix . 'wsh_views_live_hits';

		// Safety: check that main table exists.
		$like        = $wpdb->esc_like( $table_main );
		$found_table = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$like
			)
		);

		if ( $found_table !== $table_main ) {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Main views table (wsh_views_counter) not found. Please make sure the free WSH Views Counter plugin is active and has created this table.', 'wsh-views-counter-pro' ) .
				'</p></div>';
			return;
		}

		// ===== Post type filter =====.
		$supported_types = array();
		if ( class_exists( 'WSH_Views_Counter' ) && method_exists( 'WSH_Views_Counter', 'get_supported_post_types' ) ) {
			$supported_types = WSH_Views_Counter::get_supported_post_types();
		}

		if ( ! is_array( $supported_types ) || empty( $supported_types ) ) {
			$supported_types = array( 'post', 'page' );
		}

		$selected_post_type = isset( $_GET['wsh_charts_post_type'] )
			? sanitize_text_field( wp_unslash( $_GET['wsh_charts_post_type'] ) )
			: '';

		if ( $selected_post_type && ! in_array( $selected_post_type, $supported_types, true ) ) {
			$selected_post_type = '';
		}

		// ===== Date range (default: last 30 days) =====.
		$today        = current_time( 'Y-m-d' );
		$default_from = date( 'Y-m-d', strtotime( '-30 days', current_time( 'timestamp' ) ) );

		$from = isset( $_GET['wsh_charts_from'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_charts_from'] ) ) : $default_from; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to   = isset( $_GET['wsh_charts_to'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_charts_to'] ) ) : $today;         // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Basic validation: yyyy-mm-dd.
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
			$from = $default_from;
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
			$to = $today;
		}

		// Ensure from <= to.
		if ( strtotime( $from ) > strtotime( $to ) ) {
			$tmp  = $from;
			$from = $to;
			$to   = $tmp;
		}

		// ==== 1) Daily totals + devices (za line chart) ====.
		$sql  = "SELECT view_date,
		                SUM(view_count) AS total_views,
		                SUM(is_mobile)  AS mobile_views,
		                SUM(is_desktop) AS desktop_views
		         FROM {$table_main}
		         WHERE view_date BETWEEN %s AND %s";
		$params = array( $from, $to );

		if ( $selected_post_type ) {
			$sql     .= " AND post_type = %s";
			$params[] = $selected_post_type;
		}

		$sql .= " GROUP BY view_date
		          ORDER BY view_date ASC";

		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $params ),
			ARRAY_A
		);

		$chart_labels = array();
		$chart_total  = array();
		$chart_mobile = array();
		$chart_desktop = array();

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $r ) {
				$chart_labels[]  = $r['view_date'];
				$chart_total[]   = (int) $r['total_views'];
				$chart_mobile[]  = (int) $r['mobile_views'];
				$chart_desktop[] = (int) $r['desktop_views'];
			}
		}

		// ==== 2) Heatmap (day-of-week x hour) from live hits ====.
		$heatmap_data = array(); // [dow][hour] => hits.
		$max_hits     = 0;

		// Inicijalizuj praznu matricu.
		for ( $dow = 1; $dow <= 7; $dow++ ) {
			$heatmap_data[ $dow ] = array();
			for ( $h = 0; $h < 24; $h++ ) {
				$heatmap_data[ $dow ][ $h ] = 0;
			}
		}

		// Proveri da li live tabela postoji.
		$found_live = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$wpdb->esc_like( $table_live )
			)
		);

		if ( $found_live === $table_live ) {
			$from_dt = $from . ' 00:00:00';
			$to_dt   = $to   . ' 23:59:59';

			$sql_hm = "SELECT DATE(view_time) AS day,
			                  HOUR(view_time) AS hour,
			                  COUNT(*) AS hits
			           FROM {$table_live}
			           WHERE view_time BETWEEN %s AND %s";

			$params_hm = array( $from_dt, $to_dt );

			if ( $selected_post_type ) {
				$sql_hm    .= " AND post_type = %s";
				$params_hm[] = $selected_post_type;
			}

			$sql_hm .= " GROUP BY day, hour";

			$rows_hm = $wpdb->get_results(
				$wpdb->prepare( $sql_hm, $params_hm ),
				ARRAY_A
			);

			if ( ! empty( $rows_hm ) ) {
				foreach ( $rows_hm as $r ) {
					$day  = $r['day'];
					$hour = (int) $r['hour'];
					$hits = (int) $r['hits'];

					$ts  = strtotime( $day );
					$dow = (int) gmdate( 'N', $ts ); // 1 (Mon) - 7 (Sun).

					if ( $dow >= 1 && $dow <= 7 && $hour >= 0 && $hour <= 23 ) {
						$heatmap_data[ $dow ][ $hour ] += $hits;
						if ( $heatmap_data[ $dow ][ $hour ] > $max_hits ) {
							$max_hits = $heatmap_data[ $dow ][ $hour ];
						}
					}
				}
			}
		}

		// Priprema JS podataka za Chart.js.
		$chart_js_data = array(
			'labels'  => $chart_labels,
			'total'   => $chart_total,
			'mobile'  => $chart_mobile,
			'desktop' => $chart_desktop,
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Advanced Charts & Heatmaps (PRO)', 'wsh-views-counter-pro' ); ?></h1>

			<form method="get" style="margin-bottom: 15px;">
				<input type="hidden" name="page" value="wsh-vc-pro-charts" />
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Date range', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<label>
								<?php esc_html_e( 'From:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_charts_from" value="<?php echo esc_attr( $from ); ?>" />
							</label>
							&nbsp;&nbsp;
							<label>
								<?php esc_html_e( 'To:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_charts_to" value="<?php echo esc_attr( $to ); ?>" />
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Post type', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<select name="wsh_charts_post_type">
								<option value="">
									<?php esc_html_e( 'All post types', 'wsh-views-counter-pro' ); ?>
								</option>
								<?php foreach ( $supported_types as $pt ) : ?>
									<option value="<?php echo esc_attr( $pt ); ?>" <?php selected( $selected_post_type, $pt ); ?>>
										<?php echo esc_html( $pt ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							&nbsp;&nbsp;
							<button class="button button-primary">
								<?php esc_html_e( 'Filter', 'wsh-views-counter-pro' ); ?>
							</button>
						</td>
					</tr>
					</tbody>
				</table>
			</form>

						<h2><?php esc_html_e( 'Views over time', 'wsh-views-counter-pro' ); ?></h2>
			<?php if ( empty( $chart_labels ) ) : ?>
				<p><?php esc_html_e( 'No data for the selected period.', 'wsh-views-counter-pro' ); ?></p>
			<?php else : ?>
				<div class="wsh-vc-pro-chart-wrap" style="max-width:100%; height:360px;">
					<canvas id="wsh-vc-pro-chart-views" style="width:100%; height:100%;"></canvas>
				</div>

				<script type="text/javascript">
					document.addEventListener('DOMContentLoaded', function() {
						if ( typeof Chart === 'undefined' ) {
							return;
						}

						const canvas = document.getElementById('wsh-vc-pro-chart-views');
						if ( ! canvas ) {
							return;
						}

						const data = <?php echo wp_json_encode( $chart_js_data ); ?>;

						new Chart(canvas.getContext('2d'), {
							type: 'line',
							data: {
								labels: data.labels,
								datasets: [
									{
										label: '<?php echo esc_js( __( 'Total views', 'wsh-views-counter-pro' ) ); ?>',
										data: data.total,
										borderWidth: 2,
										tension: 0.2
									},
									{
										label: '<?php echo esc_js( __( 'Mobile', 'wsh-views-counter-pro' ) ); ?>',
										data: data.mobile,
										borderWidth: 2,
										tension: 0.2
									},
									{
										label: '<?php echo esc_js( __( 'Desktop', 'wsh-views-counter-pro' ) ); ?>',
										data: data.desktop,
										borderWidth: 2,
										tension: 0.2
									}
								]
							},
							options: {
								responsive: true,
								maintainAspectRatio: false,
								scales: {
									y: {
										beginAtZero: true
									}
								}
							}
						});
					});
				</script>
			<?php endif; ?>


			<hr />

			<h2><?php esc_html_e( 'Traffic heatmap (day of week × hour)', 'wsh-views-counter-pro' ); ?></h2>
			<?php if ( $max_hits <= 0 ) : ?>
				<p><?php esc_html_e( 'No live hits data available for this period.', 'wsh-views-counter-pro' ); ?></p>
			<?php else : ?>

				<style>
					table.wsh-vc-pro-heatmap {
						border-collapse: collapse;
						width: 100%;
						max-width: 100%;
						font-size: 11px;
					}
					table.wsh-vc-pro-heatmap th,
					table.wsh-vc-pro-heatmap td {
						border: 1px solid #e5e5e5;
						padding: 4px;
						text-align: center;
					}
					table.wsh-vc-pro-heatmap td span {
						display: block;
					}
				</style>

				<table class="wsh-vc-pro-heatmap">
					<thead>
					<tr>
						<th><?php esc_html_e( 'Day / Hour', 'wsh-views-counter-pro' ); ?></th>
						<?php for ( $h = 0; $h < 24; $h++ ) : ?>
							<th><?php echo esc_html( $h ); ?></th>
						<?php endfor; ?>
					</tr>
					</thead>
					<tbody>
					<?php
					$day_labels = array(
						1 => __( 'Mon', 'wsh-views-counter-pro' ),
						2 => __( 'Tue', 'wsh-views-counter-pro' ),
						3 => __( 'Wed', 'wsh-views-counter-pro' ),
						4 => __( 'Thu', 'wsh-views-counter-pro' ),
						5 => __( 'Fri', 'wsh-views-counter-pro' ),
						6 => __( 'Sat', 'wsh-views-counter-pro' ),
						7 => __( 'Sun', 'wsh-views-counter-pro' ),
					);

					for ( $dow = 1; $dow <= 7; $dow++ ) :
						?>
						<tr>
							<th><?php echo esc_html( $day_labels[ $dow ] ); ?></th>
							<?php for ( $h = 0; $h < 24; $h++ ) :
								$hits   = isset( $heatmap_data[ $dow ][ $h ] ) ? (int) $heatmap_data[ $dow ][ $h ] : 0;
								$ratio  = $max_hits > 0 ? ( $hits / $max_hits ) : 0;
								$alpha  = $ratio > 0 ? max( 0.1, $ratio ) : 0;
								$style  = $hits > 0
									? 'background-color: rgba(30,136,229,' . esc_attr( $alpha ) . '); color:#111;'
									: 'background-color:#f9f9f9; color:#999;';
								?>
								<td style="<?php echo esc_attr( $style ); ?>">
									<span><?php echo esc_html( $hits ); ?></span>
								</td>
							<?php endfor; ?>
						</tr>
					<?php endfor; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p style="margin-top:15px; text-align:center; font-size:11px; color:#777;">
				<?php esc_html_e( 'Charts are based on aggregated daily views, heatmap uses live hits table for hour-by-hour distribution.', 'wsh-views-counter-pro' ); ?>
			</p>
		</div>
		<?php
	}
}
