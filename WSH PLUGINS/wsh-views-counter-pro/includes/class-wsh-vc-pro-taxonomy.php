<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PRO module: Taxonomy Analytics (categories, tags, custom taxonomies).
 */
class WSH_VC_Pro_Taxonomy {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Register "Taxonomy Analytics" submenu under the PRO menu.
	 */
	public static function register_menu() {

		$parent_slug = 'wsh_views_counter_pro'; // PRO top-level menu slug (license page).
		$capability  = 'manage_options';

		add_submenu_page(
			$parent_slug,
			__( 'Taxonomy Analytics', 'wsh-views-counter-pro' ),
			__( 'Taxonomy Analytics', 'wsh-views-counter-pro' ),
			$capability,
			'wsh-vc-pro-taxonomy',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render Taxonomy Analytics page.
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

		$table_main = $wpdb->prefix . 'wsh_views_counter';

		// Safety: main table must exist.
		$like        = $wpdb->esc_like( $table_main );
		$found_table = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$like
			)
		);

		if ( $found_table !== $table_main ) {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Main views table (wsh_views_counter) not found. Please make sure the free WSH Views Counter plugin is active.', 'wsh-views-counter-pro' ) .
				'</p></div>';
			return;
		}

		// ===== Supported post types (same as in free plugin) =====.
		$supported_post_types = array();
		if ( class_exists( 'WSH_Views_Counter' ) ) {
			$supported_post_types = WSH_Views_Counter::get_supported_post_types();
		}
		if ( ! is_array( $supported_post_types ) || empty( $supported_post_types ) ) {
			$supported_post_types = array( 'post', 'page' );
		}

		$selected_post_type = isset( $_GET['wsh_tax_post_type'] )
			? sanitize_text_field( wp_unslash( $_GET['wsh_tax_post_type'] ) )
			: '';

		if ( $selected_post_type && ! in_array( $selected_post_type, $supported_post_types, true ) ) {
			$selected_post_type = '';
		}

		// ===== Date range filter (last 30 days default) =====.
		$today        = current_time( 'Y-m-d' );
		$default_from = date( 'Y-m-d', strtotime( '-30 days', current_time( 'timestamp' ) ) );

		$from = isset( $_GET['wsh_tax_from'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_tax_from'] ) ) : $default_from; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to   = isset( $_GET['wsh_tax_to'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_tax_to'] ) ) : $today;         // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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

		// ===== Taxonomy filter (category, post_tag, product_cat, custom...) =====.
		$available_taxonomies = self::get_available_taxonomies( $supported_post_types );

		$selected_taxonomy = isset( $_GET['wsh_tax_taxonomy'] )
			? sanitize_text_field( wp_unslash( $_GET['wsh_tax_taxonomy'] ) )
			: '';

		if ( $selected_taxonomy && ! isset( $available_taxonomies[ $selected_taxonomy ] ) ) {
			$selected_taxonomy = '';
		}

		// Default taxonomy: category if available, otherwise prvi u listi.
		if ( '' === $selected_taxonomy && ! empty( $available_taxonomies ) ) {
			if ( isset( $available_taxonomies['category'] ) ) {
				$selected_taxonomy = 'category';
			} else {
				$keys              = array_keys( $available_taxonomies );
				$selected_taxonomy = reset( $keys );
			}
		}

		// Selected term (for "Top posts in term" block).
		$selected_term_id = isset( $_GET['wsh_tax_term'] )
			? (int) $_GET['wsh_tax_term']
			: 0;

		// ===== Query: top taxonomy terms (by views in period) =====.
		$top_terms = array();

		if ( $selected_taxonomy ) {
			$params = array( $from, $to, $selected_taxonomy );

			$sql = "
				SELECT t.term_id,
				       t.name,
				       tt.taxonomy,
				       SUM(wv.view_count) AS views
				FROM {$table_main} wv
				LEFT JOIN {$wpdb->posts} p
				       ON p.ID = wv.post_id
				INNER JOIN {$wpdb->term_relationships} tr
				       ON tr.object_id = p.ID
				INNER JOIN {$wpdb->term_taxonomy} tt
				       ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t
				       ON t.term_id = tt.term_id
				WHERE wv.view_date BETWEEN %s AND %s
				  AND tt.taxonomy = %s
				  AND p.post_status = 'publish'
			";

			if ( $selected_post_type ) {
				$sql     .= " AND p.post_type = %s";
				$params[] = $selected_post_type;
			}

			$sql .= " GROUP BY t.term_id, t.name, tt.taxonomy
			          ORDER BY views DESC
			          LIMIT 100";

			$top_terms = $wpdb->get_results(
				$wpdb->prepare( $sql, $params ),
				ARRAY_A
			);
		}

		// Map term_id => name (for label when selected).
		$term_labels = array();
		foreach ( $top_terms as $row ) {
			$term_labels[ (int) $row['term_id'] ] = $row['name'];
		}

		// ===== If a term is selected, load top posts for that term =====.
		$top_posts = array();

		if ( $selected_taxonomy && $selected_term_id > 0 ) {

			$params_posts = array(
				$from,
				$to,
				$selected_taxonomy,
				$selected_term_id,
			);

			$sql_posts = "
				SELECT wv.post_id,
				       SUM(wv.view_count) AS views
				FROM {$table_main} wv
				LEFT JOIN {$wpdb->posts} p
				       ON p.ID = wv.post_id
				INNER JOIN {$wpdb->term_relationships} tr
				       ON tr.object_id = p.ID
				INNER JOIN {$wpdb->term_taxonomy} tt
				       ON tt.term_taxonomy_id = tr.term_taxonomy_id
				WHERE wv.view_date BETWEEN %s AND %s
				  AND tt.taxonomy = %s
				  AND tt.term_id = %d
				  AND p.post_status = 'publish'
			";

			if ( $selected_post_type ) {
				$sql_posts     .= " AND p.post_type = %s";
				$params_posts[] = $selected_post_type;
			}

			$sql_posts .= " GROUP BY wv.post_id
			                ORDER BY views DESC
			                LIMIT 30";

			$top_posts = $wpdb->get_results(
				$wpdb->prepare( $sql_posts, $params_posts ),
				ARRAY_A
			);
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Taxonomy Analytics (PRO)', 'wsh-views-counter-pro' ); ?></h1>

			<form method="get" style="margin-bottom: 15px;">
				<input type="hidden" name="page" value="wsh-vc-pro-taxonomy" />
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Date range', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<label>
								<?php esc_html_e( 'From:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_tax_from" value="<?php echo esc_attr( $from ); ?>" />
							</label>
							&nbsp;&nbsp;
							<label>
								<?php esc_html_e( 'To:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_tax_to" value="<?php echo esc_attr( $to ); ?>" />
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Post type', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<select name="wsh_tax_post_type">
								<option value="">
									<?php esc_html_e( 'All post types', 'wsh-views-counter-pro' ); ?>
								</option>
								<?php foreach ( $supported_post_types as $pt ) : ?>
									<option value="<?php echo esc_attr( $pt ); ?>" <?php selected( $selected_post_type, $pt ); ?>>
										<?php echo esc_html( $pt ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Taxonomy', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<select name="wsh_tax_taxonomy">
								<?php if ( empty( $available_taxonomies ) ) : ?>
									<option value=""><?php esc_html_e( 'No taxonomies available.', 'wsh-views-counter-pro' ); ?></option>
								<?php else : ?>
									<?php foreach ( $available_taxonomies as $tax_slug => $tax_obj ) : ?>
										<option value="<?php echo esc_attr( $tax_slug ); ?>" <?php selected( $selected_taxonomy, $tax_slug ); ?>>
											<?php echo esc_html( $tax_obj->labels->name ); ?> (<?php echo esc_html( $tax_slug ); ?>)
										</option>
									<?php endforeach; ?>
								<?php endif; ?>
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

			<div class="wsh-vc-pro-tax-layout" style="display:flex; gap:20px; flex-wrap:wrap;">
				<div style="flex:1; min-width:280px;">
					<h2><?php esc_html_e( 'Top Terms', 'wsh-views-counter-pro' ); ?></h2>
					<?php if ( empty( $top_terms ) ) : ?>
						<p><?php esc_html_e( 'No taxonomy data available for this period.', 'wsh-views-counter-pro' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th style="width:40px;">#</th>
									<th><?php esc_html_e( 'Term', 'wsh-views-counter-pro' ); ?></th>
									<th style="width:120px; text-align:right;"><?php esc_html_e( 'Views', 'wsh-views-counter-pro' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$i = 1;
								foreach ( $top_terms as $row ) :
									$term_id = (int) $row['term_id'];
									$name    = $row['name'];
									$views   = (int) $row['views'];

									$link_args = array(
										'page'              => 'wsh-vc-pro-taxonomy',
										'wsh_tax_from'      => $from,
										'wsh_tax_to'        => $to,
										'wsh_tax_post_type' => $selected_post_type,
										'wsh_tax_taxonomy'  => $selected_taxonomy,
										'wsh_tax_term'      => $term_id,
									);

									$term_link = add_query_arg( $link_args, admin_url( 'admin.php' ) );
									?>
									<tr>
										<td><?php echo (int) $i; ?></td>
										<td>
											<a href="<?php echo esc_url( $term_link ); ?>">
												<?php echo esc_html( $name ); ?>
											</a>
											<?php if ( $selected_term_id === $term_id ) : ?>
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

						<p class="description" style="margin-top:8px;">
							<?php esc_html_e( 'Tip: click on a term to see the most viewed posts within that term below.', 'wsh-views-counter-pro' ); ?>
						</p>
					<?php endif; ?>
				</div>

				<div style="flex:1; min-width:280px;">
					<h2><?php esc_html_e( 'Top Posts per Selected Term', 'wsh-views-counter-pro' ); ?></h2>
					<?php
					if ( ! $selected_term_id ) :
						?>
						<p><?php esc_html_e( 'Select a term from the "Top Terms" list to see the most viewed posts for that term.', 'wsh-views-counter-pro' ); ?></p>
					<?php elseif ( empty( $top_posts ) ) : ?>
						<p><?php esc_html_e( 'No post views recorded for this term in the selected period.', 'wsh-views-counter-pro' ); ?></p>
					<?php else : ?>
						<?php
						$term_label = isset( $term_labels[ $selected_term_id ] )
							? $term_labels[ $selected_term_id ]
							: sprintf( __( 'Term #%d', 'wsh-views-counter-pro' ), $selected_term_id );
						?>
						<p>
							<?php
							printf(
								/* translators: %s: term name */
								esc_html__( 'Most viewed posts in term: %s', 'wsh-views-counter-pro' ),
								'<strong>' . esc_html( $term_label ) . '</strong>'
							);
							?>
						</p>

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
			</div>

			<p style="margin-top:15px; text-align:center; font-size:11px; color:#777;">
				<?php esc_html_e( 'Taxonomy analytics are based on the daily views table (wsh_views_counter) joined with WordPress taxonomies.', 'wsh-views-counter-pro' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Get list of public taxonomies that are attached to supported post types.
	 *
	 * @param array $supported_post_types
	 * @return array slug => WP_Taxonomy
	 */
	protected static function get_available_taxonomies( $supported_post_types ) {

		$tax_objects = get_taxonomies(
			array(
				'public' => true,
			),
			'objects'
		);

		if ( ! is_array( $tax_objects ) ) {
			return array();
		}

		$result = array();

		foreach ( $tax_objects as $slug => $tax_obj ) {
			if ( empty( $tax_obj->object_type ) || ! is_array( $tax_obj->object_type ) ) {
				continue;
			}

			// At least one supported post type has to use this taxonomy.
			if ( array_intersect( $supported_post_types, $tax_obj->object_type ) ) {
				$result[ $slug ] = $tax_obj;
			}
		}

		return $result;
	}
}
