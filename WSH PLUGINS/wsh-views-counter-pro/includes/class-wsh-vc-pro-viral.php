<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PRO+ module: Viral Detection Engine.
 *
 * Detects posts with a strong traffic spike:
 * - compares current views (last X hours) in live hits table
 * - with baseline average per day (previous Y days)
 */
class WSH_VC_Pro_Viral {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Register submenu under PRO menu.
	 */
	public static function register_menu() {

		$parent_slug = 'wsh_views_counter_pro';
		$capability  = 'manage_options';

		add_submenu_page(
			$parent_slug,
			__( 'Viral Detection Engine', 'wsh-views-counter-pro' ),
			__( 'Viral Detection', 'wsh-views-counter-pro' ),
			$capability,
			'wsh-vc-pro-viral',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render Viral Detection page.
	 */
	public static function render_page() {
		global $wpdb;

		// License guard.
		if ( ! class_exists( 'WSH_VC_Pro_License' ) || ! WSH_VC_Pro_License::is_active() ) {
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'PRO features are locked. Please activate your license.', 'wsh-views-counter-pro' ) .
				'</p></div>';
			return;
		}

		$live_table = $wpdb->prefix . 'wsh_views_live_hits';

		// Check that live hits table exists.
		$like        = $wpdb->esc_like( $live_table );
		$found_table = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$like
			)
		);

		if ( $found_table !== $live_table ) {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Live hits table (wsh_views_live_hits) not found. Please make sure the free WSH Views Counter plugin is active and database tables are created.', 'wsh-views-counter-pro' ) .
				'</p></div>';
			return;
		}

		// ===== Filters: post type + detection parameters =====.

		// Supported post types from free plugin (fallback: post/page/product).
		$supported_types = array( 'post', 'page', 'product' );
		if ( class_exists( 'WSH_Views_Counter' ) && method_exists( 'WSH_Views_Counter', 'get_supported_post_types' ) ) {
			$supported_types = WSH_Views_Counter::get_supported_post_types();
		}
		if ( ! is_array( $supported_types ) || empty( $supported_types ) ) {
			$supported_types = array( 'post', 'page', 'product' );
		}

		// Selected post type (optional).
		$selected_post_type = isset( $_GET['wsh_viral_post_type'] )
			? sanitize_text_field( wp_unslash( $_GET['wsh_viral_post_type'] ) )
			: '';

		if ( $selected_post_type && ! in_array( $selected_post_type, $supported_types, true ) ) {
			$selected_post_type = '';
		}

		// Current window in hours (how far back we look for "spike" traffic).
		$current_hours = isset( $_GET['wsh_viral_hours'] )
			? (int) $_GET['wsh_viral_hours'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: 24;
		if ( $current_hours < 1 ) {
			$current_hours = 1;
		}
		if ( $current_hours > 72 ) {
			$current_hours = 72;
		}

		// Baseline window in days (how many days before that we use for "normal" traffic).
		$baseline_days = isset( $_GET['wsh_viral_baseline_days'] )
			? (int) $_GET['wsh_viral_baseline_days'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: 7;
		if ( $baseline_days < 1 ) {
			$baseline_days = 1;
		}
		if ( $baseline_days > 60 ) {
			$baseline_days = 60;
		}

		// Minimum views in current window.
		$min_views = isset( $_GET['wsh_viral_min_views'] )
			? (int) $_GET['wsh_viral_min_views'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: 50;
		if ( $min_views < 1 ) {
			$min_views = 1;
		}

		// Minimum lift (ratio current / baseline_avg_per_day).
		$min_ratio = isset( $_GET['wsh_viral_min_ratio'] )
			? (float) $_GET['wsh_viral_min_ratio'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: 2.0;
		if ( $min_ratio < 1.0 ) {
			$min_ratio = 1.0;
		}

		// Time windows.
		$now_ts          = current_time( 'timestamp' );
		$current_from_ts = $now_ts - ( $current_hours * HOUR_IN_SECONDS );
		$current_to_ts   = $now_ts;

		$baseline_to_ts   = $current_from_ts;
		$baseline_from_ts = $baseline_to_ts - ( $baseline_days * DAY_IN_SECONDS );

		$current_from_dt  = date( 'Y-m-d H:i:s', $current_from_ts );
		$current_to_dt    = date( 'Y-m-d H:i:s', $current_to_ts );
		$baseline_from_dt = date( 'Y-m-d H:i:s', $baseline_from_ts );
		$baseline_to_dt   = date( 'Y-m-d H:i:s', $baseline_to_ts );

		// ===== Load data from live hits =====.
		$viral_rows = self::detect_viral_posts(
			$live_table,
			$current_from_dt,
			$current_to_dt,
			$baseline_from_dt,
			$baseline_to_dt,
			$baseline_days,
			$min_views,
			$min_ratio,
			$selected_post_type
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Viral Detection Engine (PRO+)', 'wsh-views-counter-pro' ); ?></h1>

			<form method="get" style="margin-bottom: 15px;">
				<input type="hidden" name="page" value="wsh-vc-pro-viral" />
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Post type', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<select name="wsh_viral_post_type">
								<option value="">
									<?php esc_html_e( 'All supported types', 'wsh-views-counter-pro' ); ?>
								</option>
								<?php foreach ( $supported_types as $pt ) : ?>
									<option value="<?php echo esc_attr( $pt ); ?>" <?php selected( $selected_post_type, $pt ); ?>>
										<?php echo esc_html( $pt ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Current window (spike detection)', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<label>
								<?php esc_html_e( 'Last', 'wsh-views-counter-pro' ); ?>
								<input type="number" name="wsh_viral_hours" value="<?php echo esc_attr( $current_hours ); ?>" min="1" max="72" style="width:60px;" />
								<?php esc_html_e( 'hours', 'wsh-views-counter-pro' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'How far back to look for current spike views (e.g. 24 = last 24 hours).', 'wsh-views-counter-pro' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Baseline window', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<label>
								<?php esc_html_e( 'Previous', 'wsh-views-counter-pro' ); ?>
								<input type="number" name="wsh_viral_baseline_days" value="<?php echo esc_attr( $baseline_days ); ?>" min="1" max="60" style="width:60px;" />
								<?php esc_html_e( 'days (before current window)', 'wsh-views-counter-pro' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Used to calculate the “normal” average per day. For example: 7 days.', 'wsh-views-counter-pro' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Detection thresholds', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<label style="display:inline-block; margin-right:20px;">
								<?php esc_html_e( 'Min current views:', 'wsh-views-counter-pro' ); ?>
								<input type="number" name="wsh_viral_min_views" value="<?php echo esc_attr( $min_views ); ?>" min="1" style="width:80px;" />
							</label>

							<label style="display:inline-block;">
								<?php esc_html_e( 'Min spike factor:', 'wsh-views-counter-pro' ); ?>
								<input type="number" name="wsh_viral_min_ratio" value="<?php echo esc_attr( $min_ratio ); ?>" min="1" step="0.1" style="width:80px;" />
								<?php esc_html_e( '× vs baseline average', 'wsh-views-counter-pro' ); ?>
							</label>

							<p class="description">
								<?php esc_html_e( 'Example: 50 views and 3× means: current views ≥ 50 AND current views are at least 3 times higher than the baseline average per day.', 'wsh-views-counter-pro' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">&nbsp;</th>
						<td>
							<button class="button button-primary">
								<?php esc_html_e( 'Detect viral posts', 'wsh-views-counter-pro' ); ?>
							</button>
						</td>
					</tr>
					</tbody>
				</table>
			</form>

			<p class="description">
				<?php
				printf(
					/* translators: 1: date from, 2: date to, 3: date from baseline, 4: date to baseline */
					esc_html__(
						'Current window: %1$s → %2$s. Baseline window: %3$s → %4$s.',
						'wsh-views-counter-pro'
					),
					esc_html( $current_from_dt ),
					esc_html( $current_to_dt ),
					esc_html( $baseline_from_dt ),
					esc_html( $baseline_to_dt )
				);
				?>
			</p>

			<hr />

			<h2><?php esc_html_e( 'Detected Viral Posts', 'wsh-views-counter-pro' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Posts are sorted by spike factor (current views vs baseline average per day). Baseline is computed from the selected baseline window before the current window.', 'wsh-views-counter-pro' ); ?>
			</p>

			<?php
			if ( empty( $viral_rows ) ) {
				echo '<p>' . esc_html__( 'No viral posts found for the selected parameters.', 'wsh-views-counter-pro' ) . '</p>';
			} else {
				self::render_viral_table( $viral_rows, $current_hours, $baseline_days );
			}
			?>

			<p style="margin-top:15px; text-align:center; font-size:11px; color:#777;">
				<?php esc_html_e( 'Viral Detection Engine uses recent live hits from the wsh_views_live_hits table. If your host cleans this table aggressively, results may be limited.', 'wsh-views-counter-pro' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Detect viral posts by comparing current vs baseline periods.
	 *
	 * @param string $live_table       Live hits table name.
	 * @param string $current_from_dt  Current window start (Y-m-d H:i:s).
	 * @param string $current_to_dt    Current window end.
	 * @param string $baseline_from_dt Baseline window start.
	 * @param string $baseline_to_dt   Baseline window end.
	 * @param int    $baseline_days    Baseline days count.
	 * @param int    $min_views        Minimum current views.
	 * @param float  $min_ratio        Minimum ratio.
	 * @param string $post_type_filter Optional post type filter.
	 *
	 * @return array
	 */
	protected static function detect_viral_posts(
		$live_table,
		$current_from_dt,
		$current_to_dt,
		$baseline_from_dt,
		$baseline_to_dt,
		$baseline_days,
		$min_views,
		$min_ratio,
		$post_type_filter = ''
	) {
		global $wpdb;

		// 1) Current window: aggregate views per post.
		$sql_current  = "SELECT post_id, post_type, COUNT(*) AS views
						 FROM {$live_table}
						 WHERE view_time BETWEEN %s AND %s";
		$params_cur   = array( $current_from_dt, $current_to_dt );

		if ( $post_type_filter ) {
			$sql_current   .= " AND post_type = %s";
			$params_cur[]   = $post_type_filter;
		}

		$sql_current .= " GROUP BY post_id, post_type";

		$current_rows = $wpdb->get_results(
			$wpdb->prepare( $sql_current, $params_cur ),
			ARRAY_A
		);

		if ( empty( $current_rows ) ) {
			return array();
		}

		$current_map = array(); // post_id => [views, post_type].
		foreach ( $current_rows as $row ) {
			$post_id   = (int) $row['post_id'];
			$post_type = $row['post_type'];
			$views     = (int) $row['views'];

			if ( $post_id <= 0 || $views <= 0 ) {
				continue;
			}

			$current_map[ $post_id ] = array(
				'views'     => $views,
				'post_type' => $post_type,
			);
		}

		if ( empty( $current_map ) ) {
			return array();
		}

		$post_ids = array_keys( $current_map );

		// 2) Baseline window: aggregate views per post.
		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		$sql_baseline  = "SELECT post_id, COUNT(*) AS views
						  FROM {$live_table}
						  WHERE view_time BETWEEN %s AND %s
						    AND post_id IN ($placeholders)";
		$params_base   = array_merge(
			array( $baseline_from_dt, $baseline_to_dt ),
			$post_ids
		);

		if ( $post_type_filter ) {
			$sql_baseline .= " AND post_type = %s";
			$params_base[] = $post_type_filter;
		}

		$sql_baseline .= " GROUP BY post_id";

		$baseline_rows = $wpdb->get_results(
			$wpdb->prepare( $sql_baseline, $params_base ),
			ARRAY_A
		);

		$baseline_map = array(); // post_id => baseline_total.
		if ( ! empty( $baseline_rows ) ) {
			foreach ( $baseline_rows as $row ) {
				$post_id = (int) $row['post_id'];
				$views   = (int) $row['views'];

				if ( $post_id > 0 && $views > 0 ) {
					$baseline_map[ $post_id ] = $views;
				}
			}
		}

		// 3) Build viral list.
		$result = array();

		foreach ( $current_map as $post_id => $info ) {
			$current_views = (int) $info['views'];
			if ( $current_views < $min_views ) {
				continue;
			}

			$baseline_total = isset( $baseline_map[ $post_id ] ) ? (int) $baseline_map[ $post_id ] : 0;
			$baseline_avg   = 0.0;
			$ratio          = 0.0;
			$is_new_spike   = false;

			if ( $baseline_total > 0 && $baseline_days > 0 ) {
				$baseline_avg = $baseline_total / (float) $baseline_days;
			}

			if ( $baseline_avg > 0 ) {
				$ratio = $current_views / $baseline_avg;
			} else {
				// No baseline – treat as "new spike" if it passes min_views.
				$is_new_spike = true;
				$ratio        = 999.0; // effectively infinite.
			}

			if ( $ratio < $min_ratio && ! $is_new_spike ) {
				continue;
			}

			// Build item.
			$result[] = array(
				'post_id'        => $post_id,
				'post_type'      => $info['post_type'],
				'current_views'  => $current_views,
				'baseline_total' => $baseline_total,
				'baseline_avg'   => $baseline_avg,
				'ratio'          => $ratio,
				'is_new_spike'   => $is_new_spike,
			);
		}

		if ( empty( $result ) ) {
			return array();
		}

		// 4) Sort by ratio descending, then by current_views descending.
		usort(
			$result,
			static function( $a, $b ) {
				if ( $a['ratio'] === $b['ratio'] ) {
					if ( $a['current_views'] === $b['current_views'] ) {
						return 0;
					}
					// More current views first.
					return ( $a['current_views'] > $b['current_views'] ) ? -1 : 1;
				}

				return ( $a['ratio'] > $b['ratio'] ) ? -1 : 1;
			}
		);

		// Limit to top 50 viral posts.
		return array_slice( $result, 0, 50 );
	}

	/**
	 * Render viral posts table.
	 *
	 * @param array $rows         Viral rows.
	 * @param int   $current_hours Current hours window.
	 * @param int   $baseline_days Baseline days.
	 */
	protected static function render_viral_table( $rows, $current_hours, $baseline_days ) {
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:40px;">#</th>
					<th><?php esc_html_e( 'Post', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:90px;"><?php esc_html_e( 'Post type', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:120px; text-align:right;">
						<?php
						printf(
							/* translators: %d: hours */
							esc_html__( 'Current (%dh)', 'wsh-views-counter-pro' ),
							(int) $current_hours
						);
						?>
					</th>
					<th style="width:120px; text-align:right;">
						<?php
						printf(
							/* translators: %d: days */
							esc_html__( 'Baseline total (%dd)', 'wsh-views-counter-pro' ),
							(int) $baseline_days
						);
						?>
					</th>
					<th style="width:130px; text-align:right;">
						<?php esc_html_e( 'Baseline avg/day', 'wsh-views-counter-pro' ); ?>
					</th>
					<th style="width:130px; text-align:right;">
						<?php esc_html_e( 'Spike factor', 'wsh-views-counter-pro' ); ?>
					</th>
					<th style="width:130px; text-align:left;">
						<?php esc_html_e( 'Notes', 'wsh-views-counter-pro' ); ?>
					</th>
				</tr>
			</thead>
			<tbody>
				<?php
				$i = 1;
				foreach ( $rows as $row ) :
					$post_id        = (int) $row['post_id'];
					$post_type      = $row['post_type'];
					$current_views  = (int) $row['current_views'];
					$baseline_total = (int) $row['baseline_total'];
					$baseline_avg   = (float) $row['baseline_avg'];
					$ratio          = (float) $row['ratio'];
					$is_new_spike   = (bool) $row['is_new_spike'];

					$title = get_the_title( $post_id );
					if ( '' === $title ) {
						$title = sprintf( __( 'Post #%d', 'wsh-views-counter-pro' ), $post_id );
					}
					$link       = get_permalink( $post_id );
					$edit_link  = get_edit_post_link( $post_id );
					$ratio_label = $is_new_spike
						? esc_html__( 'New spike', 'wsh-views-counter-pro' )
						: number_format_i18n( $ratio, 2 ) . '×';

					// Simple highlight: big spikes get bold label.
					$ratio_html = $ratio_label;
					if ( ! $is_new_spike && $ratio >= 5.0 ) {
						$ratio_html = '<strong style="color:#d63638;">' . esc_html( $ratio_label ) . '</strong>';
					} elseif ( $is_new_spike ) {
						$ratio_html = '<strong style="color:#008a20;">' . esc_html( $ratio_label ) . '</strong>';
					}
					?>
					<tr>
						<td><?php echo (int) $i; ?></td>
						<td>
							<a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $title ); ?>
							</a>
							<?php if ( $edit_link ) : ?>
								<br /><a href="<?php echo esc_url( $edit_link ); ?>" style="font-size:11px;"><?php esc_html_e( 'Edit', 'wsh-views-counter-pro' ); ?></a>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $post_type ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $current_views ) ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $baseline_total ) ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $baseline_avg, 2 ) ); ?></td>
						<td style="text-align:right;">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo $ratio_html;
							?>
						</td>
						<td>
							<?php
							if ( $is_new_spike ) {
								esc_html_e( 'No baseline history – strong new spike.', 'wsh-views-counter-pro' );
							} elseif ( $baseline_total === 0 ) {
								esc_html_e( 'No baseline data available.', 'wsh-views-counter-pro' );
							} elseif ( $ratio >= 5.0 ) {
								esc_html_e( 'Viral! Traffic is much higher than usual.', 'wsh-views-counter-pro' );
							} elseif ( $ratio >= 2.0 ) {
								esc_html_e( 'Strong spike vs normal traffic.', 'wsh-views-counter-pro' );
							} else {
								esc_html_e( 'Slight spike.', 'wsh-views-counter-pro' );
							}
							?>
						</td>
					</tr>
					<?php
					$i++;
				endforeach;
				?>
			</tbody>
		</table>
		<?php
	}
}
