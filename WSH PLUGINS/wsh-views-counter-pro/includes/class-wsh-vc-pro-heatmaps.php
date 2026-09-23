<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PRO module: Heatmaps Analytics (based on wsh_views_live_hits).
 */
class WSH_VC_Pro_Heatmaps {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Register submenu.
	 */
	public static function register_menu() {

		$parent_slug = 'wsh_views_counter_pro';
		$capability  = 'manage_options';

		add_submenu_page(
			$parent_slug,
			__( 'Heatmaps PRO', 'wsh-views-counter-pro' ),
			__( 'Heatmaps PRO', 'wsh-views-counter-pro' ),
			$capability,
			'wsh-vc-pro-heatmaps',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render page.
	 */
	public static function render_page() {
		global $wpdb;

		// Extra license guard.
		if ( ! class_exists( 'WSH_VC_Pro_License' ) || ! WSH_VC_Pro_License::is_active() ) {
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'PRO features are locked. Please activate your license.', 'wsh-views-counter-pro' ) .
				'</p></div>';
			return;
		}

		// Koristimo live hits tabelu iz FREE plugina.
		$log_table = $wpdb->prefix . 'wsh_views_live_hits';

		// Safety: proveri da li tabela postoji.
		$like        = $wpdb->esc_like( $log_table );
		$found_table = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$like
			)
		);

		if ( $found_table !== $log_table ) {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Live hits table (wsh_views_live_hits) not found. Please make sure the free WSH Views Counter plugin is active and database tables are created.', 'wsh-views-counter-pro' ) .
				'</p></div>';
			return;
		}

		// ===== Date range filter (default poslednjih 7 dana) =====.
		$today        = current_time( 'Y-m-d' );
		$default_from = date( 'Y-m-d', strtotime( '-7 days', current_time( 'timestamp' ) ) );

		$from = isset( $_GET['wsh_heat_from'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_heat_from'] ) ) : $default_from; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to   = isset( $_GET['wsh_heat_to'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_heat_to'] ) ) : $today;           // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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

		// Koliko dana pokriva period (za tekst u info boxu).
		$range_days = max(
			1,
			( (int) floor( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS ) ) + 1
		);

		// Pretvorimo u datetime granice.
		$from_dt = $from . ' 00:00:00';
		$to_dt   = $to . ' 23:59:59';

		// ===== 1) Hourly heatmap (date × hour) =====.
		$sql = $wpdb->prepare(
			"SELECT DATE(view_time) AS d, HOUR(view_time) AS h, COUNT(*) AS cnt
			 FROM {$log_table}
			 WHERE view_time BETWEEN %s AND %s
			 GROUP BY d, h
			 ORDER BY d ASC, h ASC",
			$from_dt,
			$to_dt
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		$days_matrix = array(); // [date][hour] = count

		foreach ( $rows as $row ) {
			$day  = isset( $row['d'] ) ? $row['d'] : '';
			$hour = isset( $row['h'] ) ? (int) $row['h'] : 0;
			$cnt  = isset( $row['cnt'] ) ? (int) $row['cnt'] : 0;

			if ( empty( $day ) ) {
				continue;
			}

			if ( ! isset( $days_matrix[ $day ] ) ) {
				$days_matrix[ $day ] = array_fill( 0, 24, 0 );
			}

			if ( $hour >= 0 && $hour <= 23 ) {
				$days_matrix[ $day ][ $hour ] = $cnt;
			}
		}

		// Ako nema podataka.
		$has_data = ! empty( $days_matrix );

		// ===== 2) Weekly aggregate (day_of_week × hour) + "best time" =====.
		$week_matrix  = array();
		$day_totals   = array();
		$hour_totals  = array_fill( 0, 24, 0 );

		for ( $d = 0; $d < 7; $d++ ) {
			$week_matrix[ $d ] = array_fill( 0, 24, 0 );
			$day_totals[ $d ]  = 0;
		}

		if ( $has_data ) {
			foreach ( $days_matrix as $date => $hours ) {
				$ts        = strtotime( $date );
				$day_index = (int) gmdate( 'w', $ts ); // 0 (Sun) – 6 (Sat)

				foreach ( $hours as $h => $cnt ) {
					$cnt = (int) $cnt;

					if ( $h >= 0 && $h <= 23 && $cnt > 0 ) {
						$week_matrix[ $day_index ][ $h ] += $cnt;
						$day_totals[ $day_index ]        += $cnt;
						$hour_totals[ $h ]               += $cnt;
					}
				}
			}
		}

		// Izračunaj najbolji dan i sat.
		$weekday_labels = array(
			0 => __( 'Sunday', 'wsh-views-counter-pro' ),
			1 => __( 'Monday', 'wsh-views-counter-pro' ),
			2 => __( 'Tuesday', 'wsh-views-counter-pro' ),
			3 => __( 'Wednesday', 'wsh-views-counter-pro' ),
			4 => __( 'Thursday', 'wsh-views-counter-pro' ),
			5 => __( 'Friday', 'wsh-views-counter-pro' ),
			6 => __( 'Saturday', 'wsh-views-counter-pro' ),
		);

		$best_day_index = -1;
		$best_day_hits  = 0;
		foreach ( $day_totals as $idx => $total ) {
			if ( $total > $best_day_hits ) {
				$best_day_hits  = $total;
				$best_day_index = $idx;
			}
		}

		$best_hour      = -1;
		$best_hour_hits = 0;
		foreach ( $hour_totals as $h => $total ) {
			if ( $total > $best_hour_hits ) {
				$best_hour_hits = $total;
				$best_hour      = $h;
			}
		}

		$best_day_label = ( $best_day_index >= 0 && isset( $weekday_labels[ $best_day_index ] ) )
			? $weekday_labels[ $best_day_index ]
			: '';

		$best_hour_label = ( $best_hour >= 0 )
			? sprintf( '%02d:00', $best_hour )
			: '';

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Heatmaps (PRO)', 'wsh-views-counter-pro' ); ?></h1>

			<form method="get" style="margin-bottom: 15px;">
				<input type="hidden" name="page" value="wsh-vc-pro-heatmaps" />
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Date range', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<label>
								<?php esc_html_e( 'From:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_heat_from" value="<?php echo esc_attr( $from ); ?>" />
							</label>
							&nbsp;&nbsp;
							<label>
								<?php esc_html_e( 'To:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_heat_to" value="<?php echo esc_attr( $to ); ?>" />
							</label>
							&nbsp;&nbsp;
							<button class="button button-primary">
								<?php esc_html_e( 'Filter', 'wsh-views-counter-pro' ); ?>
							</button>
						</td>
					</tr>
					</tbody>
				</table>
			</form>

			<?php if ( $has_data && $best_day_hits > 0 && $best_hour_hits > 0 && $best_day_label && $best_hour_label ) : ?>
				<div class="notice notice-info" style="margin-top:15px;">
					<p>
						<strong><?php esc_html_e( 'Best time to publish (based on heatmap):', 'wsh-views-counter-pro' ); ?></strong><br />
						<?php
						printf(
							/* translators: 1: day name, 2: hour, 3: number of days */
							esc_html__( '%1$s around %2$s (site time), based on the last %3$d days of activity.', 'wsh-views-counter-pro' ),
							esc_html( $best_day_label ),
							esc_html( $best_hour_label ),
							(int) $range_days
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Hourly Heatmap (per day)', 'wsh-views-counter-pro' ); ?></h2>

			<?php
			if ( ! $has_data ) {
				echo '<p>' . esc_html__( 'No live hits recorded for the selected period.', 'wsh-views-counter-pro' ) . '</p>';
			} else {
				echo self::render_heatmap_table_daily( $days_matrix );
			}
			?>

			<h2 style="margin-top: 40px;"><?php esc_html_e( 'Weekly Heatmap (day of week × hour)', 'wsh-views-counter-pro' ); ?></h2>

			<?php
			if ( ! $has_data ) {
				echo '<p>' . esc_html__( 'No data to display weekly heatmap.', 'wsh-views-counter-pro' ) . '</p>';
			} else {
				echo self::render_heatmap_table_weekly( $week_matrix );
			}
			?>

			<p style="margin-top:15px; text-align:center; font-size:11px; color:#777;">
				<?php esc_html_e( 'Heatmaps are based on recent live hits stored in the wsh_views_live_hits table. Older data may be cleaned via cron.', 'wsh-views-counter-pro' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render day × hour heatmap.
	 *
	 * @param array $days_matrix [ 'Y-m-d' => [0..23] ].
	 * @return string
	 */
	protected static function render_heatmap_table_daily( $days_matrix ) {

		if ( empty( $days_matrix ) || ! is_array( $days_matrix ) ) {
			return '';
		}

		// Nađi max vrednost.
		$max = 0;
		foreach ( $days_matrix as $hours ) {
			foreach ( $hours as $cnt ) {
				$cnt = (int) $cnt;
				if ( $cnt > $max ) {
					$max = $cnt;
				}
			}
		}

		ob_start();
		?>
		<table class="widefat striped" style="border-collapse:collapse; table-layout:fixed; font-size:11px;">
			<thead>
				<tr>
					<th style="width:90px;"><?php esc_html_e( 'Date', 'wsh-views-counter-pro' ); ?></th>
					<?php for ( $h = 0; $h < 24; $h++ ) : ?>
						<th style="text-align:center;"><?php echo esc_html( $h ); ?></th>
					<?php endfor; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $days_matrix as $date => $hours ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $date ); ?></strong></td>
						<?php foreach ( $hours as $cnt ) : ?>
							<?php
							$cnt   = (int) $cnt;
							$color = self::color_scale( $cnt, $max );
							?>
							<td style="text-align:center; background:<?php echo esc_attr( $color ); ?>; color:#fff;">
								<?php echo $cnt > 0 ? esc_html( $cnt ) : '&nbsp;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render weekly heatmap (Sun–Sat × 24h).
	 *
	 * @param array $week_matrix [0..6][0..23].
	 * @return string
	 */
	protected static function render_heatmap_table_weekly( $week_matrix ) {

		if ( empty( $week_matrix ) || ! is_array( $week_matrix ) ) {
			return '';
		}

		$labels = array(
			__( 'Sunday', 'wsh-views-counter-pro' ),
			__( 'Monday', 'wsh-views-counter-pro' ),
			__( 'Tuesday', 'wsh-views-counter-pro' ),
			__( 'Wednesday', 'wsh-views-counter-pro' ),
			__( 'Thursday', 'wsh-views-counter-pro' ),
			__( 'Friday', 'wsh-views-counter-pro' ),
			__( 'Saturday', 'wsh-views-counter-pro' ),
		);

		$max = 0;
		foreach ( $week_matrix as $row ) {
			foreach ( $row as $cnt ) {
				$cnt = (int) $cnt;
				if ( $cnt > $max ) {
					$max = $cnt;
				}
			}
		}

		ob_start();
		?>
		<table class="widefat striped" style="border-collapse:collapse; table-layout:fixed; font-size:11px;">
			<thead>
				<tr>
					<th style="width:110px;"><?php esc_html_e( 'Day', 'wsh-views-counter-pro' ); ?></th>
					<?php for ( $h = 0; $h < 24; $h++ ) : ?>
						<th style="text-align:center;"><?php echo esc_html( $h ); ?></th>
					<?php endfor; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $week_matrix as $day_index => $hours ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $labels[ $day_index ] ); ?></strong></td>
						<?php foreach ( $hours as $cnt ) : ?>
							<?php
							$cnt   = (int) $cnt;
							$color = self::color_scale( $cnt, $max );
							?>
							<td style="text-align:center; background:<?php echo esc_attr( $color ); ?>; color:#fff;">
								<?php echo $cnt > 0 ? esc_html( $cnt ) : '&nbsp;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php

		return ob_get_clean();
	}

	/**
	 * Simple color scale from 0 → max (dark gray → red).
	 *
	 * @param int $value Current cell value.
	 * @param int $max   Global max.
	 * @return string CSS color.
	 */
	protected static function color_scale( $value, $max ) {
		$value = (int) $value;
		$max   = (int) $max;

		if ( $max <= 0 || $value <= 0 ) {
			return '#444';
		}

		$ratio = $value / $max;
		if ( $ratio < 0 ) {
			$ratio = 0;
		}
		if ( $ratio > 1 ) {
			$ratio = 1;
		}

		$red   = (int) floor( 200 + 55 * $ratio );      // 200–255
		$green = (int) floor( 80 * ( 1 - $ratio ) );    // 80–0
		$blue  = (int) floor( 60 * ( 1 - $ratio ) );    // 60–0

		return sprintf( 'rgb(%d,%d,%d)', $red, $green, $blue );
	}
}
