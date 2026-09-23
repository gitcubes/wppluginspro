<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PRO module: Export PRO (CSV exports for analytics).
 */
class WSH_VC_Pro_Export {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_wsh_vc_pro_export_run', array( __CLASS__, 'handle_export' ) );
	}

	/**
	 * Register "Export PRO" submenu under the PRO menu.
	 */
	public static function register_menu() {

		$parent_slug = 'wsh_views_counter_pro'; // PRO top-level menu slug (license page).
		$capability  = 'manage_options';

		add_submenu_page(
			$parent_slug,
			__( 'Export PRO', 'wsh-views-counter-pro' ),
			__( 'Export PRO', 'wsh-views-counter-pro' ),
			$capability,
			'wsh-vc-pro-export',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render Export PRO page (form for CSV export).
	 */
	public static function render_page() {

		// License guard.
		if ( ! class_exists( 'WSH_VC_Pro_License' ) || ! WSH_VC_Pro_License::is_active() ) {
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'PRO features are locked. Please activate your license.', 'wsh-views-counter-pro' ) .
				'</p></div>';
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wsh-views-counter-pro' ) );
		}

		// Supported post types (same as in free plugin).
		$supported_post_types = array();
		if ( class_exists( 'WSH_Views_Counter' ) ) {
			$supported_post_types = WSH_Views_Counter::get_supported_post_types();
		}
		if ( ! is_array( $supported_post_types ) || empty( $supported_post_types ) ) {
			$supported_post_types = array( 'post', 'page' );
		}

		// Default date range: last 30 days.
		$today        = current_time( 'Y-m-d' );
		$default_from = date( 'Y-m-d', strtotime( '-30 days', current_time( 'timestamp' ) ) );

		// Pre-fill values from GET (da ostane izabran izbor kada se vrate nazad).
		$from            = isset( $_GET['wsh_export_from'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_export_from'] ) ) : $default_from; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to              = isset( $_GET['wsh_export_to'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_export_to'] ) ) : $today;           // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_type   = isset( $_GET['wsh_export_post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_export_post_type'] ) ) : '';
		$selected_report = isset( $_GET['wsh_export_report'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_export_report'] ) ) : 'post_summary';

        // NOVO: default / pre-fill za limit
        $selected_limit  = isset( $_GET['wsh_export_limit'] ) ? (int) $_GET['wsh_export_limit'] : 1000;
        $allowed_limits  = array( 100, 1000, 2000, 5000, 10000 );
        if ( ! in_array( $selected_limit, $allowed_limits, true ) ) {
            $selected_limit = 1000;
        }

		// Simple validation.
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
			$from = $default_from;
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
			$to = $today;
		}
		if ( strtotime( $from ) > strtotime( $to ) ) {
			$tmp  = $from;
			$from = $to;
			$to   = $tmp;
		}

		// Report types (key => label).
		$report_types = array(
			'post_summary'   => __( 'Post Summary (total views per post)', 'wsh-views-counter-pro' ),
			'daily_post'     => __( 'Daily Views per Post', 'wsh-views-counter-pro' ),
			'geo_countries'  => __( 'Geo – Countries (summary)', 'wsh-views-counter-pro' ),
			'referrers'      => __( 'Referrers (summary)', 'wsh-views-counter-pro' ),
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Export PRO', 'wsh-views-counter-pro' ); ?></h1>

			<p class="description" style="max-width:700px;">
				<?php esc_html_e( 'Generate CSV exports for your analytics data. Use the options below to choose the period, post type and report type.', 'wsh-views-counter-pro' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px;">
				<?php wp_nonce_field( 'wsh_vc_pro_export', 'wsh_vc_pro_export_nonce' ); ?>
				<input type="hidden" name="action" value="wsh_vc_pro_export_run" />

				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Date range', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<label>
								<?php esc_html_e( 'From:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_export_from" value="<?php echo esc_attr( $from ); ?>" />
							</label>
							&nbsp;&nbsp;
							<label>
								<?php esc_html_e( 'To:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_export_to" value="<?php echo esc_attr( $to ); ?>" />
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Post type', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<select name="wsh_export_post_type">
								<option value="">
									<?php esc_html_e( 'All post types', 'wsh-views-counter-pro' ); ?>
								</option>
								<?php foreach ( $supported_post_types as $pt ) : ?>
									<option value="<?php echo esc_attr( $pt ); ?>" <?php selected( $selected_type, $pt ); ?>>
										<?php echo esc_html( $pt ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Optional: limit the export to a specific post type.', 'wsh-views-counter-pro' ); ?>
							</p>
						</td>
					</tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Number of posts', 'wsh-views-counter-pro' ); ?>
                        </th>
                        <td>
                            <select name="wsh_export_limit">
                                <?php foreach ( array( 100, 1000, 2000, 5000, 10000 ) as $limit_val ) : ?>
                                    <option value="<?php echo (int) $limit_val; ?>"
                                        <?php selected( $selected_limit, $limit_val ); ?>>
                                        <?php echo (int) $limit_val; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e( 'Maximum number of posts for the Post Summary report (top posts by views).', 'wsh-views-counter-pro' ); ?>
                            </p>
                        </td>
                    </tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Report type', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<select name="wsh_export_report">
								<?php foreach ( $report_types as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_report, $key ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Choose what kind of dataset you want to export.', 'wsh-views-counter-pro' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<?php esc_html_e( 'Format', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<select name="wsh_export_format" disabled="disabled">
								<option value="csv" selected="selected">CSV</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Currently only CSV is supported. You can open it in Excel, Google Sheets or any spreadsheet tool.', 'wsh-views-counter-pro' ); ?>
							</p>
						</td>
					</tr>

					</tbody>
				</table>

				<?php submit_button( __( 'Export as CSV', 'wsh-views-counter-pro' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle CSV export (admin-post).
	 */
	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to export data.', 'wsh-views-counter-pro' ) );
		}

		if ( ! isset( $_POST['wsh_vc_pro_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wsh_vc_pro_export_nonce'] ) ), 'wsh_vc_pro_export' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wsh-views-counter-pro' ) );
		}

		// License guard.
		if ( ! class_exists( 'WSH_VC_Pro_License' ) || ! WSH_VC_Pro_License::is_active() ) {
			wp_die( esc_html__( 'PRO license is not active. Export is locked.', 'wsh-views-counter-pro' ) );
		}

		$from       = isset( $_POST['wsh_export_from'] ) ? sanitize_text_field( wp_unslash( $_POST['wsh_export_from'] ) ) : '';
		$to         = isset( $_POST['wsh_export_to'] ) ? sanitize_text_field( wp_unslash( $_POST['wsh_export_to'] ) ) : '';
		$post_type  = isset( $_POST['wsh_export_post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['wsh_export_post_type'] ) ) : '';
		$report     = isset( $_POST['wsh_export_report'] ) ? sanitize_text_field( wp_unslash( $_POST['wsh_export_report'] ) ) : 'post_summary';

        // NOVO: limit
        $max_posts = isset( $_POST['wsh_export_limit'] ) ? (int) $_POST['wsh_export_limit'] : 1000;
        $allowed_limits = array( 100, 1000, 2000, 5000, 10000 );
        if ( ! in_array( $max_posts, $allowed_limits, true ) ) {
            $max_posts = 1000;
        }

		// Validation – fallback na zadnjih 30 dana ako nešto nije ok.
		$today        = current_time( 'Y-m-d' );
		$default_from = date( 'Y-m-d', strtotime( '-30 days', current_time( 'timestamp' ) ) );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
			$from = $default_from;
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
			$to = $today;
		}
		if ( strtotime( $from ) > strtotime( $to ) ) {
			$tmp  = $from;
			$from = $to;
			$to   = $tmp;
		}

		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		ignore_user_abort( true );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		// Ako postoji neki output buffer, očisti ga (da ne puni memoriju).
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		switch ( $report ) {
			case 'post_summary':
				self::export_post_summary( $from, $to, $post_type, $max_posts );
				break;

			case 'daily_post':
				self::export_daily_post( $from, $to, $post_type );
				break;

			case 'geo_countries':
				self::export_geo_countries( $from, $to, $post_type );
				break;

			case 'referrers':
				self::export_referrers( $from, $to, $post_type );
				break;

			default:
				wp_die( esc_html__( 'Unknown report type.', 'wsh-views-counter-pro' ) );
		}

		exit;
	}

	/**
	 * Export: Post Summary (total views per post in period).
	 * Stream u chunk-ovima da ne puni memoriju.
	 */
	protected static function export_post_summary( $from, $to, $post_type, $max_posts = 0 ) {
		global $wpdb;

		$table_main = $wpdb->prefix . 'wsh_views_counter';

		$params = array( $from, $to );
		$sql_base = "
			FROM {$table_main} wv
			LEFT JOIN {$wpdb->posts} p
			       ON p.ID = wv.post_id
			WHERE wv.view_date BETWEEN %s AND %s
			  AND p.post_status = 'publish'
		";

		if ( ! empty( $post_type ) ) {
			$sql_base .= " AND p.post_type = %s";
			$params[]  = $post_type;
		}

		$sql_group_order = "
			GROUP BY wv.post_id, p.post_type
			ORDER BY total_views DESC
		";

		$filename = 'wsh-export-post-summary-' . $from . '_to_' . $to . '.csv';
        self::send_csv_headers( $filename );

        $fh = fopen( 'php://output', 'w' );
        if ( ! $fh ) {
            wp_die( esc_html__( 'Could not open output stream.', 'wsh-views-counter-pro' ) );
        }

        fputcsv(
            $fh,
            array(
                'post_id',
                'post_type',
                'post_title',
                'post_url',
                'total_views',
                'views_mobile',
                'views_desktop',
                'views_logged_in',
            )
        );

        $limit          = 2000; // chunk veličina
        $offset         = 0;
        $total_exported = 0;

        do {
            $sql = "
                SELECT wv.post_id,
                       p.post_type,
                       SUM(wv.view_count) AS total_views,
                       SUM(wv.is_mobile)  AS views_mobile,
                       SUM(wv.is_desktop) AS views_desktop,
                       SUM(wv.is_logged)  AS views_logged
                {$sql_base}
                {$sql_group_order}
                LIMIT %d OFFSET %d
            ";

            $params_chunk = array_merge( $params, array( $limit, $offset ) );

            $rows = $wpdb->get_results(
                $wpdb->prepare( $sql, $params_chunk ),
                ARRAY_A
            );

            if ( empty( $rows ) ) {
                break;
            }

            foreach ( $rows as $row ) {

                if ( $max_posts > 0 && $total_exported >= $max_posts ) {
                    // Đon iz oba nivoa petlje.
                    break 2;
                }

                $post_id   = (int) $row['post_id'];
                $type      = $row['post_type'];
                $title     = $post_id ? get_the_title( $post_id ) : '';
                $url       = $post_id ? get_permalink( $post_id ) : '';
                $total     = (int) $row['total_views'];
                $mobile    = (int) $row['views_mobile'];
                $desktop   = (int) $row['views_desktop'];
                $logged_in = (int) $row['views_logged'];

                fputcsv(
                    $fh,
                    array(
                        $post_id,
                        $type,
                        $title,
                        $url,
                        $total,
                        $mobile,
                        $desktop,
                        $logged_in,
                    )
                );

                $total_exported++;
            }

            $offset += $limit;
            $wpdb->flush();

            if ( function_exists( 'flush' ) ) {
                flush();
            }

        } while ( count( $rows ) === $limit && ( 0 === $max_posts || $total_exported < $max_posts ) );

        fclose( $fh );
	}

	/**
	 * Export: Daily Views per Post – chunkovan.
	 */
	protected static function export_daily_post( $from, $to, $post_type ) {
		global $wpdb;

		$table_main = $wpdb->prefix . 'wsh_views_counter';

		$params   = array( $from, $to );
		$sql_base = "
			FROM {$table_main} wv
			LEFT JOIN {$wpdb->posts} p
			       ON p.ID = wv.post_id
			WHERE wv.view_date BETWEEN %s AND %s
			  AND p.post_status = 'publish'
		";

		if ( ! empty( $post_type ) ) {
			$sql_base .= " AND p.post_type = %s";
			$params[]  = $post_type;
		}

		$sql_group_order = "
			GROUP BY wv.view_date, wv.post_id, p.post_type
			ORDER BY wv.view_date ASC, daily_views DESC
		";

		$filename = 'wsh-export-daily-post-' . $from . '_to_' . $to . '.csv';
		self::send_csv_headers( $filename );

		$fh = fopen( 'php://output', 'w' );
		if ( ! $fh ) {
			wp_die( esc_html__( 'Could not open output stream.', 'wsh-views-counter-pro' ) );
		}

		fputcsv(
			$fh,
			array(
				'view_date',
				'post_id',
				'post_type',
				'post_title',
				'post_url',
				'daily_views',
				'views_mobile',
				'views_desktop',
				'views_logged_in',
			)
		);

		$limit  = 2000;
		$offset = 0;

		do {
			$sql = "
				SELECT wv.view_date,
				       wv.post_id,
				       p.post_type,
				       SUM(wv.view_count) AS daily_views,
				       SUM(wv.is_mobile)  AS views_mobile,
				       SUM(wv.is_desktop) AS views_desktop,
				       SUM(wv.is_logged)  AS views_logged
				{$sql_base}
				{$sql_group_order}
				LIMIT %d OFFSET %d
			";

			$params_chunk = array_merge( $params, array( $limit, $offset ) );

			$rows = $wpdb->get_results(
				$wpdb->prepare( $sql, $params_chunk ),
				ARRAY_A
			);

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$post_id   = (int) $row['post_id'];
				$type      = $row['post_type'];
				$title     = $post_id ? get_the_title( $post_id ) : '';
				$url       = $post_id ? get_permalink( $post_id ) : '';
				$date      = $row['view_date'];
				$views     = (int) $row['daily_views'];
				$mobile    = (int) $row['views_mobile'];
				$desktop   = (int) $row['views_desktop'];
				$logged_in = (int) $row['views_logged'];

				fputcsv(
					$fh,
					array(
						$date,
						$post_id,
						$type,
						$title,
						$url,
						$views,
						$mobile,
						$desktop,
						$logged_in,
					)
				);
			}

			$offset += $limit;
			$wpdb->flush();

			if ( function_exists( 'flush' ) ) {
				flush();
			}

		} while ( count( $rows ) === $limit );

		fclose( $fh );
	}

	/**
	 * Export: Geo – Countries (summary) – chunkovan.
	 */
	protected static function export_geo_countries( $from, $to, $post_type ) {
		global $wpdb;

		$table_geo = $wpdb->prefix . 'wsh_views_counter_geo';

		$params   = array( $from, $to );
		$sql_base = "
			FROM {$table_geo}
			WHERE view_date BETWEEN %s AND %s
		";

		if ( ! empty( $post_type ) ) {
			$sql_base .= " AND post_type = %s";
			$params[]  = $post_type;
		}

		$sql_group_order = "
			AND country_code <> ''
			GROUP BY country_code, country_name
			ORDER BY views DESC
		";

		$filename = 'wsh-export-geo-countries-' . $from . '_to_' . $to . '.csv';
		self::send_csv_headers( $filename );

		$fh = fopen( 'php://output', 'w' );
		if ( ! $fh ) {
			wp_die( esc_html__( 'Could not open output stream.', 'wsh-views-counter-pro' ) );
		}

		fputcsv(
			$fh,
			array(
				'country_code',
				'country_name',
				'views',
			)
		);

		$limit  = 2000;
		$offset = 0;

		do {
			$sql = "
				SELECT country_code,
				       country_name,
				       SUM(view_count) AS views
				{$sql_base}
				{$sql_group_order}
				LIMIT %d OFFSET %d
			";

			$params_chunk = array_merge( $params, array( $limit, $offset ) );

			$rows = $wpdb->get_results(
				$wpdb->prepare( $sql, $params_chunk ),
				ARRAY_A
			);

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$code  = strtoupper( (string) $row['country_code'] );
				$name  = $row['country_name'] ? $row['country_name'] : $code;
				$views = (int) $row['views'];

				fputcsv(
					$fh,
					array(
						$code,
						$name,
						$views,
					)
				);
			}

			$offset += $limit;
			$wpdb->flush();

			if ( function_exists( 'flush' ) ) {
				flush();
			}

		} while ( count( $rows ) === $limit );

		fclose( $fh );
	}

	/**
	 * Export: Referrers (summary) – chunkovan.
	 */
	protected static function export_referrers( $from, $to, $post_type ) {
		global $wpdb;

		$table_ref = $wpdb->prefix . 'wsh_views_counter_ref';

		$params   = array( $from, $to );
		$sql_base = "
			FROM {$table_ref} r
			LEFT JOIN {$wpdb->posts} p
			       ON p.ID = r.post_id
			WHERE r.view_date BETWEEN %s AND %s
			  AND p.post_status = 'publish'
		";

		if ( ! empty( $post_type ) ) {
			$sql_base .= " AND p.post_type = %s";
			$params[]  = $post_type;
		}

		$sql_group_order = "
			GROUP BY r.ref_type, r.ref_domain
			ORDER BY views DESC
		";

		$filename = 'wsh-export-referrers-' . $from . '_to_' . $to . '.csv';
		self::send_csv_headers( $filename );

		$fh = fopen( 'php://output', 'w' );
		if ( ! $fh ) {
			wp_die( esc_html__( 'Could not open output stream.', 'wsh-views-counter-pro' ) );
		}

		fputcsv(
			$fh,
			array(
				'ref_type',
				'ref_domain',
				'views',
			)
		);

		$limit  = 2000;
		$offset = 0;

		do {
			$sql = "
				SELECT r.ref_type,
				       r.ref_domain,
				       SUM(r.view_count) AS views
				{$sql_base}
				{$sql_group_order}
				LIMIT %d OFFSET %d
			";

			$params_chunk = array_merge( $params, array( $limit, $offset ) );

			$rows = $wpdb->get_results(
				$wpdb->prepare( $sql, $params_chunk ),
				ARRAY_A
			);

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$type  = $row['ref_type'];
				$host  = $row['ref_domain'];
				$views = (int) $row['views'];

				fputcsv(
					$fh,
					array(
						$type,
						$host,
						$views,
					)
				);
			}

			$offset += $limit;
			$wpdb->flush();

			if ( function_exists( 'flush' ) ) {
				flush();
			}

		} while ( count( $rows ) === $limit );

		fclose( $fh );
	}

	/**
	 * Common helper: send CSV headers.
	 */
	protected static function send_csv_headers( $filename ) {
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"' );
		}
	}
}
