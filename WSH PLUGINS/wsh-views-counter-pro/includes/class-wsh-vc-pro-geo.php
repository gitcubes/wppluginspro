<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PRO module: Geo Analytics (countries & cities).
 */
class WSH_VC_Pro_Geo {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Register "Geo Analytics" submenu under the PRO menu.
	 *
	 * Parent slug must match the PRO top-level menu slug.
	 */
	public static function register_menu() {

		$parent_slug = 'wsh_views_counter_pro'; // PRO license page slug.
		$capability  = 'manage_options';

		add_submenu_page(
			$parent_slug,
			__( 'Geo Analytics', 'wsh-views-counter-pro' ),
			__( 'Geo Analytics', 'wsh-views-counter-pro' ),
			$capability,
			'wsh-vc-pro-geo',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render Geo Analytics page.
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

		// Use GEO aggregation table (not the main views table).
		$table_geo = $wpdb->prefix . 'wsh_views_counter_geo';

		// Safety: check that the geo table exists.
		$like        = $wpdb->esc_like( $table_geo );
		$found_table = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$like
			)
		);

		if ( $found_table !== $table_geo ) {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Geo analytics table (wsh_views_counter_geo) not found. Please make sure the free WSH Views Counter plugin is active and has created this table.', 'wsh-views-counter-pro' ) .
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

		$selected_post_type = isset( $_GET['wsh_geo_post_type'] )
			? sanitize_text_field( wp_unslash( $_GET['wsh_geo_post_type'] ) )
			: '';

		if ( $selected_post_type && ! in_array( $selected_post_type, $supported_types, true ) ) {
			$selected_post_type = '';
		}

		// ===== Date range filter (last 30 days default) =====.
		$today        = current_time( 'Y-m-d' );
		$default_from = date( 'Y-m-d', strtotime( '-30 days', current_time( 'timestamp' ) ) );

		$from = isset( $_GET['wsh_geo_from'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_geo_from'] ) ) : $default_from; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to   = isset( $_GET['wsh_geo_to'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_geo_to'] ) ) : $today;         // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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

		// Selected country (for "Top posts in country" block).
		$selected_country_code = isset( $_GET['wsh_geo_country'] )
			? strtoupper( sanitize_text_field( wp_unslash( $_GET['wsh_geo_country'] ) ) )
			: '';

		// ===== Query: top countries =====.
		$sql_countries  = "SELECT country_code, country_name, SUM(view_count) AS views
		                   FROM {$table_geo}
		                   WHERE view_date BETWEEN %s AND %s";
		$params_countries = array( $from, $to );

		if ( $selected_post_type ) {
			$sql_countries     .= " AND post_type = %s";
			$params_countries[] = $selected_post_type;
		}

		$sql_countries .= " AND country_code <> ''
		                    GROUP BY country_code, country_name
		                    ORDER BY views DESC
		                    LIMIT 50";

		$countries = $wpdb->get_results(
			$wpdb->prepare( $sql_countries, $params_countries ),
			ARRAY_A
		);

		// Map for country labels (used later for "Top posts in ...").
		$country_labels = array();
		foreach ( $countries as $row ) {
			$code              = strtoupper( (string) $row['country_code'] );
			$country_labels[ $code ] = $row['country_name'] ? $row['country_name'] : $code;
		}

		// ===== Query: top cities =====.
		$sql_cities  = "SELECT country_code, country_name, city, SUM(view_count) AS views
		                FROM {$table_geo}
		                WHERE view_date BETWEEN %s AND %s";
		$params_cities = array( $from, $to );

		if ( $selected_post_type ) {
			$sql_cities      .= " AND post_type = %s";
			$params_cities[]  = $selected_post_type;
		}

		$sql_cities .= " AND city <> ''
		                GROUP BY country_code, country_name, city
		                ORDER BY views DESC
		                LIMIT 50";

		$cities = $wpdb->get_results(
			$wpdb->prepare( $sql_cities, $params_cities ),
			ARRAY_A
		);

		// ===== If a country is selected, load top posts for that country =====.
		$top_posts = array();
		if ( $selected_country_code ) {

			$sql_posts  = "SELECT post_id, SUM(view_count) AS views
			               FROM {$table_geo}
			               WHERE view_date BETWEEN %s AND %s
			                 AND country_code = %s";
			$params_posts = array( $from, $to, $selected_country_code );

			if ( $selected_post_type ) {
				$sql_posts      .= " AND post_type = %s";
				$params_posts[]  = $selected_post_type;
			}

			$sql_posts .= " GROUP BY post_id
			                ORDER BY views DESC
			                LIMIT 20";

			$top_posts = $wpdb->get_results(
				$wpdb->prepare( $sql_posts, $params_posts ),
				ARRAY_A
			);
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Geo Analytics (PRO)', 'wsh-views-counter-pro' ); ?></h1>

			<form method="get" style="margin-bottom: 15px;">
				<input type="hidden" name="page" value="wsh-vc-pro-geo" />
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Date range', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<label>
								<?php esc_html_e( 'From:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_geo_from" value="<?php echo esc_attr( $from ); ?>" />
							</label>
							&nbsp;&nbsp;
							<label>
								<?php esc_html_e( 'To:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_geo_to" value="<?php echo esc_attr( $to ); ?>" />
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Post type', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<select name="wsh_geo_post_type">
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

			<div class="wsh-vc-pro-geo-layout" style="display:flex; gap:20px; flex-wrap:wrap;">
				<div style="flex:1; min-width:280px;">
					<h2><?php esc_html_e( 'Top Countries', 'wsh-views-counter-pro' ); ?></h2>
					<?php if ( empty( $countries ) ) : ?>
						<p><?php esc_html_e( 'No geo data available for this period.', 'wsh-views-counter-pro' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th style="width:40px;">#</th>
									<th><?php esc_html_e( 'Country', 'wsh-views-counter-pro' ); ?></th>
									<th style="width:100px; text-align:right;"><?php esc_html_e( 'Views', 'wsh-views-counter-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$i = 1;
								foreach ( $countries as $row ) :
									$code         = strtoupper( (string) $row['country_code'] );
									$country_name = $row['country_name'] ? $row['country_name'] : $code;
									$views        = (int) $row['views'];

									// Build link that selects this country (preserving date + post type).
									$link_args = array(
										'page'             => 'wsh-vc-pro-geo',
										'wsh_geo_from'     => $from,
										'wsh_geo_to'       => $to,
										'wsh_geo_post_type'=> $selected_post_type,
										'wsh_geo_country'  => $code,
									);
									$country_link = add_query_arg( $link_args, admin_url( 'admin.php' ) );
									?>
									<tr>
										<td><?php echo (int) $i; ?></td>
										<td>
											<a href="<?php echo esc_url( $country_link ); ?>">
												<?php echo esc_html( $country_name ); ?>
											</a>
											<?php if ( $selected_country_code === $code ) : ?>
												<span style="color:#2271b1;">&larr; <?php esc_html_e( 'selected', 'wsh-views-counter-pro' ); ?></span>
											<?php endif; ?>
										</td>
										<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $views ) ); ?></td>
									</tr>
									<?php
									$i++;
								endforeach;
								?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

				<div style="flex:1; min-width:280px;">
					<h2><?php esc_html_e( 'Top Cities', 'wsh-views-counter-pro' ); ?></h2>
					<?php if ( empty( $cities ) ) : ?>
						<p><?php esc_html_e( 'No geo data available for this period.', 'wsh-views-counter-pro' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th style="width:40px;">#</th>
									<th><?php esc_html_e( 'City', 'wsh-views-counter-pro' ); ?></th>
									<th style="width:120px;"><?php esc_html_e( 'Country', 'wsh-views-counter-pro' ); ?></th>
									<th style="width:100px; text-align:right;"><?php esc_html_e( 'Views', 'wsh-views-counter-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$j = 1;
								foreach ( $cities as $row ) :
									$city         = $row['city'];
									$code         = strtoupper( (string) $row['country_code'] );
									$country_name = $row['country_name'] ? $row['country_name'] : $code;
									$views        = (int) $row['views'];
									?>
									<tr>
										<td><?php echo (int) $j; ?></td>
										<td><?php echo esc_html( $city ); ?></td>
										<td><?php echo esc_html( $country_name ); ?></td>
										<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $views ) ); ?></td>
									</tr>
									<?php
									$j++;
								endforeach;
								?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>

			<?php
			// Third block: top posts in selected country.
			if ( $selected_country_code ) :
				$label = isset( $country_labels[ $selected_country_code ] )
					? $country_labels[ $selected_country_code ]
					: $selected_country_code;
				?>
				<div style="margin-top:30px;">
					<h2>
						<?php
						printf(
							/* translators: %s: country name */
							esc_html__( 'Top Posts in %s', 'wsh-views-counter-pro' ),
							esc_html( $label )
						);
						?>
					</h2>

					<?php if ( empty( $top_posts ) ) : ?>
						<p><?php esc_html_e( 'No post views recorded for this country in the selected period.', 'wsh-views-counter-pro' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th style="width:40px;">#</th>
									<th><?php esc_html_e( 'Post', 'wsh-views-counter-pro' ); ?></th>
									<th style="width:100px; text-align:right;"><?php esc_html_e( 'Views', 'wsh-views-counter-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$k = 1;
								foreach ( $top_posts as $row ) :
									$post_id = (int) $row['post_id'];
									$views   = (int) $row['views'];
									if ( ! $post_id ) {
										continue;
									}
									$title = get_the_title( $post_id );
									if ( '' === $title ) {
										$title = sprintf( __( 'Post #%d', 'wsh-views-counter-pro' ), $post_id );
									}
									$link = get_permalink( $post_id );
									?>
									<tr>
										<td><?php echo (int) $k; ?></td>
										<td>
											<a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer">
												<?php echo esc_html( $title ); ?>
											</a>
										</td>
										<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $views ) ); ?></td>
									</tr>
									<?php
									$k++;
								endforeach;
								?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<p style="margin-top:15px; text-align:center; font-size:11px; color:#777;">
				<?php esc_html_e( 'Geo data is collected by the free WSH Views Counter plugin and unlocked in this PRO report.', 'wsh-views-counter-pro' ); ?>
			</p>
		</div>
		<?php
	}
}
