<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PRO module: Referrer Analytics (Search / Social / Direct / Internal / Referral).
 */
class WSH_VC_Pro_Referrer {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Register "Referrer Analytics" submenu under the PRO menu.
	 *
	 * Parent slug mora da se poklapa sa glavnim PRO menijem (license page).
	 */
	public static function register_menu() {

		$parent_slug = 'wsh_views_counter_pro'; // isti kao u Geo klasi.
		$capability  = 'manage_options';

		add_submenu_page(
			$parent_slug,
			__( 'Referrer Analytics', 'wsh-views-counter-pro' ),
			__( 'Referrer Analytics', 'wsh-views-counter-pro' ),
			$capability,
			'wsh-vc-pro-referrer',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render Referrer Analytics page.
	 */
	public static function render_page() {
		global $wpdb;

		// License guard – isto kao u Geo klasi.
		if ( ! class_exists( 'WSH_VC_Pro_License' ) || ! WSH_VC_Pro_License::is_active() ) {
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'PRO features are locked. Please activate your license.', 'wsh-views-counter-pro' ) .
				'</p></div>';
			return;
		}

		$table_ref      = $wpdb->prefix . 'wsh_views_counter_ref';
		$table_sessions = $wpdb->prefix . 'wsh_views_sessions';

		// Safety: check that the referrer table exists.
		$like_ref  = $wpdb->esc_like( $table_ref );
		$ref_found = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$like_ref
			)
		);

		if ( $ref_found !== $table_ref ) {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Referrer analytics table (wsh_views_counter_ref) not found. Please make sure the free WSH Views Counter plugin is active and has created this table.', 'wsh-views-counter-pro' ) .
				'</p></div>';
			return;
		}

		// ===== Post type filter (post / page / product / ...) =====.
		$supported_types = array();
		if ( class_exists( 'WSH_Views_Counter' ) ) {
			$supported_types = WSH_Views_Counter::get_supported_post_types();
		}
		if ( ! is_array( $supported_types ) ) {
			$supported_types = array( 'post', 'page' );
		}

		$selected_post_type = isset( $_GET['wsh_ref_post_type'] )
			? sanitize_text_field( wp_unslash( $_GET['wsh_ref_post_type'] ) )
			: '';

		if ( $selected_post_type && ! in_array( $selected_post_type, $supported_types, true ) ) {
			$selected_post_type = '';
		}

		// ===== Date range filter (last 30 days default) =====.
		$today        = current_time( 'Y-m-d' );
		$default_from = date( 'Y-m-d', strtotime( '-30 days', current_time( 'timestamp' ) ) );

		$from = isset( $_GET['wsh_ref_from'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_ref_from'] ) ) : $default_from; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to   = isset( $_GET['wsh_ref_to'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_ref_to'] ) ) : $today;         // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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

		// ===== Query: traffic by referrer type =====.
		$sql_types  = "SELECT ref_type, SUM(view_count) AS views
		               FROM {$table_ref}
		               WHERE view_date BETWEEN %s AND %s";
		$params_types = array( $from, $to );

		if ( $selected_post_type ) {
			$sql_types      .= " AND post_type = %s";
			$params_types[]  = $selected_post_type;
		}

		$sql_types .= " GROUP BY ref_type
		                HAVING views > 0
		                ORDER BY views DESC";

		$ref_types = $wpdb->get_results(
			$wpdb->prepare( $sql_types, $params_types ),
			ARRAY_A
		);

		// ===== Query: top referrer domains =====.
		$sql_domains  = "SELECT ref_type, ref_domain, SUM(view_count) AS views
		                 FROM {$table_ref}
		                 WHERE view_date BETWEEN %s AND %s
		                   AND ref_domain <> ''";
		$params_domains = array( $from, $to );

		if ( $selected_post_type ) {
			$sql_domains      .= " AND post_type = %s";
			$params_domains[]  = $selected_post_type;
		}

		$sql_domains .= " GROUP BY ref_type, ref_domain
		                  HAVING views > 0
		                  ORDER BY views DESC
		                  LIMIT 50";

		$ref_domains = $wpdb->get_results(
			$wpdb->prepare( $sql_domains, $params_domains ),
			ARRAY_A
		);

		// ===== Landing pages (sessions table) =====.
		$landing = array();
		$exit    = array();

		// Provera da li tabela sessions postoji – nije obavezno da ruši ekran.
		$like_sess  = $wpdb->esc_like( $table_sessions );
		$sess_found = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$like_sess
			)
		);

		if ( $sess_found === $table_sessions ) {

			// Landing pages.
			$sql_landing  = "SELECT s.first_post_id AS post_id,
			                        COUNT(*) AS sessions,
			                        SUM(s.hit_count) AS hits
			                 FROM {$table_sessions} s";
			$join_landing = '';
			$where_landing = $wpdb->prepare(
				" WHERE s.first_seen BETWEEN %s AND %s
				  AND s.first_post_id > 0",
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			);

			if ( $selected_post_type ) {
				$join_landing .= " LEFT JOIN {$wpdb->posts} p1 ON p1.ID = s.first_post_id";
				$where_landing .= $wpdb->prepare( " AND p1.post_type = %s", $selected_post_type );
			}

			$sql_landing .= $join_landing . $where_landing .
				" GROUP BY s.first_post_id
				  HAVING sessions > 0
				  ORDER BY sessions DESC
				  LIMIT 20";

			$landing = $wpdb->get_results( $sql_landing, ARRAY_A );

			// Exit pages.
			$sql_exit  = "SELECT s.last_post_id AS post_id,
			                     COUNT(*) AS sessions,
			                     SUM(s.hit_count) AS hits
			              FROM {$table_sessions} s";
			$join_exit  = '';
			$where_exit = $wpdb->prepare(
				" WHERE s.last_seen BETWEEN %s AND %s
				  AND s.last_post_id > 0",
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			);

			if ( $selected_post_type ) {
				$join_exit .= " LEFT JOIN {$wpdb->posts} p2 ON p2.ID = s.last_post_id";
				$where_exit .= $wpdb->prepare( " AND p2.post_type = %s", $selected_post_type );
			}

			$sql_exit .= $join_exit . $where_exit .
				" GROUP BY s.last_post_id
				  HAVING sessions > 0
				  ORDER BY sessions DESC
				  LIMIT 20";

			$exit = $wpdb->get_results( $sql_exit, ARRAY_A );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Referrer Analytics (PRO)', 'wsh-views-counter-pro' ); ?></h1>

			<form method="get" style="margin-bottom: 15px;">
				<input type="hidden" name="page" value="wsh-vc-pro-referrer" />
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Date range', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<label>
								<?php esc_html_e( 'From:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_ref_from" value="<?php echo esc_attr( $from ); ?>" />
							</label>
							&nbsp;&nbsp;
							<label>
								<?php esc_html_e( 'To:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_ref_to" value="<?php echo esc_attr( $to ); ?>" />
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Post type', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<select name="wsh_ref_post_type">
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

			<div class="wsh-vc-pro-ref-layout" style="display:flex; gap:20px; flex-wrap:wrap;">
				<div style="flex:1; min-width:280px;">
					<h2><?php esc_html_e( 'Traffic by Referrer Type', 'wsh-views-counter-pro' ); ?></h2>
					<?php if ( empty( $ref_types ) ) : ?>
						<p><?php esc_html_e( 'No referrer data available for this period.', 'wsh-views-counter-pro' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Referrer type', 'wsh-views-counter-pro' ); ?></th>
									<th style="width:120px; text-align:right;"><?php esc_html_e( 'Views', 'wsh-views-counter-pro' ); ?></th>
									<th style="width:80px; text-align:right;"><?php esc_html_e( 'Share', 'wsh-views-counter-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$total_views = 0;
								foreach ( $ref_types as $row ) {
									$total_views += (int) $row['views'];
								}

								foreach ( $ref_types as $row ) :
									$type  = $row['ref_type'];
									$views = (int) $row['views'];
									$share = $total_views > 0 ? round( ( $views / $total_views ) * 100 ) : 0;
									?>
									<tr>
										<td><?php echo esc_html( self::get_ref_type_label( $type ) ); ?></td>
										<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $views ) ); ?></td>
										<td style="text-align:right;"><?php echo esc_html( $share ); ?>%</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
							<tfoot>
								<tr>
									<th><?php esc_html_e( 'Total', 'wsh-views-counter-pro' ); ?></th>
									<th style="text-align:right;"><?php echo esc_html( number_format_i18n( $total_views ) ); ?></th>
									<th></th>
								</tr>
							</tfoot>
						</table>
					<?php endif; ?>
				</div>

				<div style="flex:1; min-width:280px;">
					<h2><?php esc_html_e( 'Top Referrer Domains', 'wsh-views-counter-pro' ); ?></h2>
					<?php if ( empty( $ref_domains ) ) : ?>
						<p><?php esc_html_e( 'No referrer domains for this period.', 'wsh-views-counter-pro' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Domain', 'wsh-views-counter-pro' ); ?></th>
									<th style="width:120px; text-align:right;"><?php esc_html_e( 'Views', 'wsh-views-counter-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $ref_domains as $row ) : ?>
									<tr>
										<td>
											<?php
											$domain = $row['ref_domain'];
											$type   = $row['ref_type'];

											if ( $domain ) {
												$url = ( 0 === strpos( $domain, 'http' ) ) ? $domain : 'https://' . $domain;
												echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer"><strong>' . esc_html( $domain ) . '</strong></a><br>';
											}
											echo '<span class="description">' . esc_html( self::get_ref_type_label( $type ) ) . '</span>';
											?>
										</td>
										<td style="text-align:right;"><?php echo esc_html( number_format_i18n( (int) $row['views'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>

			<div class="wsh-vc-pro-ref-layout" style="display:flex; gap:20px; flex-wrap:wrap; margin-top:25px;">
				<div style="flex:1; min-width:280px;">
					<h2><?php esc_html_e( 'Top Landing Pages (sessions)', 'wsh-views-counter-pro' ); ?></h2>
					<?php if ( empty( $landing ) ) : ?>
						<p><?php esc_html_e( 'No session data available for this period.', 'wsh-views-counter-pro' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Page', 'wsh-views-counter-pro' ); ?></th>
									<th style="width:90px; text-align:right;"><?php esc_html_e( 'Sessions', 'wsh-views-counter-pro' ); ?></th>
									<th style="width:90px; text-align:right;"><?php esc_html_e( 'Hits', 'wsh-views-counter-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $landing as $row ) : ?>
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
										<td style="text-align:right;"><?php echo esc_html( number_format_i18n( (int) $row['sessions'] ) ); ?></td>
										<td style="text-align:right;"><?php echo esc_html( number_format_i18n( (int) $row['hits'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p class="description">
							<?php esc_html_e( 'Landing page = first page in a user session.', 'wsh-views-counter-pro' ); ?>
						</p>
					<?php endif; ?>
				</div>

				<div style="flex:1; min-width:280px;">
					<h2><?php esc_html_e( 'Top Exit Pages (sessions)', 'wsh-views-counter-pro' ); ?></h2>
					<?php if ( empty( $exit ) ) : ?>
						<p><?php esc_html_e( 'No session data available for this period.', 'wsh-views-counter-pro' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Page', 'wsh-views-counter-pro' ); ?></th>
									<th style="width:90px; text-align:right;"><?php esc_html_e( 'Sessions', 'wsh-views-counter-pro' ); ?></th>
									<th style="width:90px; text-align:right;"><?php esc_html_e( 'Hits', 'wsh-views-counter-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $exit as $row ) : ?>
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
										<td style="text-align:right;"><?php echo esc_html( number_format_i18n( (int) $row['sessions'] ) ); ?></td>
										<td style="text-align:right;"><?php echo esc_html( number_format_i18n( (int) $row['hits'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p class="description">
							<?php esc_html_e( 'Exit page = last page in a user session.', 'wsh-views-counter-pro' ); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>

			<p style="margin-top:15px; text-align:center; font-size:11px; color:#777;">
				<?php esc_html_e( 'Referrer data is collected by the free WSH Views Counter plugin and unlocked in this PRO report.', 'wsh-views-counter-pro' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Human-friendly labels for ref_type.
	 */
	protected static function get_ref_type_label( $type ) {
		$type = (string) $type;

		switch ( $type ) {
			case 'search':
				return __( 'Search (Google, Bing...)', 'wsh-views-counter-pro' );
			case 'social':
				return __( 'Social networks', 'wsh-views-counter-pro' );
			case 'direct':
				return __( 'Direct', 'wsh-views-counter-pro' );
			case 'internal':
				return __( 'Internal (same site)', 'wsh-views-counter-pro' );
			case 'referral':
				return __( 'External referral', 'wsh-views-counter-pro' );
			default:
				return $type !== '' ? ucfirst( $type ) : __( 'Unknown', 'wsh-views-counter-pro' );
		}
	}
}
