<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PRO module: Author Analytics (views per author).
 */
class WSH_VC_Pro_Authors {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Register submenu under the PRO menu.
	 */
	public static function register_menu() {

		$parent_slug = 'wsh_views_counter_pro'; // PRO main menu slug.
		$capability  = 'manage_options';

		add_submenu_page(
			$parent_slug,
			__( 'Author Analytics', 'wsh-views-counter-pro' ),
			__( 'Author Analytics', 'wsh-views-counter-pro' ),
			$capability,
			'wsh-vc-pro-authors',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render Author Analytics page.
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

		// Main daily views table from the free plugin.
		$table_views = $wpdb->prefix . 'wsh_views_counter';

		// Safety: check that the table exists.
		$like        = $wpdb->esc_like( $table_views );
		$found_table = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$like
			)
		);

		if ( $found_table !== $table_views ) {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Views table (wsh_views_counter) not found. Please make sure the free WSH Views Counter plugin is active and has created this table.', 'wsh-views-counter-pro' ) .
				'</p></div>';
			return;
		}

		// ===== Post type filter (post / page / product / ...) =====.
		$supported_types = array( 'post', 'page' );
		if ( class_exists( 'WSH_Views_Counter' ) && method_exists( 'WSH_Views_Counter', 'get_supported_post_types' ) ) {
			$supported_types = WSH_Views_Counter::get_supported_post_types();
		}

		$selected_post_type = isset( $_GET['wsh_auth_post_type'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( $_GET['wsh_auth_post_type'] ) )
			: '';

		if ( $selected_post_type && ! in_array( $selected_post_type, $supported_types, true ) ) {
			$selected_post_type = '';
		}

		// ===== Date range filter (last 30 days default) =====.
		$today        = current_time( 'Y-m-d' );
		$default_from = date( 'Y-m-d', strtotime( '-30 days', current_time( 'timestamp' ) ) );

		$from = isset( $_GET['wsh_auth_from'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_auth_from'] ) ) : $default_from; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to   = isset( $_GET['wsh_auth_to'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_auth_to'] ) ) : $today;           // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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

		// Selected author (for "Top posts by author" block).
		$selected_author_id = isset( $_GET['wsh_auth_author'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? (int) $_GET['wsh_auth_author']
			: 0;

		// ===== Query: author summary =====.
		$sql_auth  = "SELECT p.post_author AS author_id,
		                     SUM(v.view_count) AS views,
		                     COUNT(DISTINCT v.post_id) AS posts
		              FROM {$table_views} v
		              JOIN {$wpdb->posts} p ON p.ID = v.post_id
		              WHERE v.view_date BETWEEN %s AND %s
		                AND p.post_status = 'publish'";
		$params_auth = array( $from, $to );

		if ( $selected_post_type ) {
			$sql_auth      .= " AND v.post_type = %s";
			$params_auth[]  = $selected_post_type;
		}

		$sql_auth .= " GROUP BY p.post_author
		              ORDER BY views DESC
		              LIMIT 50";

		$authors = $wpdb->get_results(
			$wpdb->prepare( $sql_auth, $params_auth ),
			ARRAY_A
		);

		// Preload user display names.
		$authors_map = array();
		foreach ( $authors as $row ) {
			$aid              = isset( $row['author_id'] ) ? (int) $row['author_id'] : 0;
			$authors_map[ $aid ] = get_the_author_meta( 'display_name', $aid );
		}

		// ===== If an author is selected, load their top posts and daily stats =====.
		$author_posts  = array();
		$author_daily  = array();
		$author_name   = '';
		$has_author_id = ( $selected_author_id > 0 );

		if ( $has_author_id ) {

			$author_name = get_the_author_meta( 'display_name', $selected_author_id );
			if ( '' === $author_name ) {
				$author_name = sprintf( __( 'Author #%d', 'wsh-views-counter-pro' ), $selected_author_id );
			}

			// Top posts by this author.
			$sql_posts  = "SELECT v.post_id, SUM(v.view_count) AS views
			               FROM {$table_views} v
			               JOIN {$wpdb->posts} p ON p.ID = v.post_id
			               WHERE v.view_date BETWEEN %s AND %s
			                 AND p.post_status = 'publish'
			                 AND p.post_author = %d";
			$params_posts = array( $from, $to, $selected_author_id );

			if ( $selected_post_type ) {
				$sql_posts      .= " AND v.post_type = %s";
				$params_posts[]  = $selected_post_type;
			}

			$sql_posts .= " GROUP BY v.post_id
			                ORDER BY views DESC
			                LIMIT 20";

			$author_posts = $wpdb->get_results(
				$wpdb->prepare( $sql_posts, $params_posts ),
				ARRAY_A
			);

			// Daily views for this author.
			$sql_daily  = "SELECT v.view_date, SUM(v.view_count) AS views
			               FROM {$table_views} v
			               JOIN {$wpdb->posts} p ON p.ID = v.post_id
			               WHERE v.view_date BETWEEN %s AND %s
			                 AND p.post_status = 'publish'
			                 AND p.post_author = %d";
			$params_daily = array( $from, $to, $selected_author_id );

			if ( $selected_post_type ) {
				$sql_daily      .= " AND v.post_type = %s";
				$params_daily[]  = $selected_post_type;
			}

			$sql_daily .= " GROUP BY v.view_date
			                ORDER BY v.view_date ASC";

			$author_daily = $wpdb->get_results(
				$wpdb->prepare( $sql_daily, $params_daily ),
				ARRAY_A
			);
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Author Analytics (PRO)', 'wsh-views-counter-pro' ); ?></h1>

			<form method="get" style="margin-bottom: 15px;">
				<input type="hidden" name="page" value="wsh-vc-pro-authors" />
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Date range', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<label>
								<?php esc_html_e( 'From:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_auth_from" value="<?php echo esc_attr( $from ); ?>" />
							</label>
							&nbsp;&nbsp;
							<label>
								<?php esc_html_e( 'To:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_auth_to" value="<?php echo esc_attr( $to ); ?>" />
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Post type', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<select name="wsh_auth_post_type">
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

			<h2><?php esc_html_e( 'Top Authors', 'wsh-views-counter-pro' ); ?></h2>

			<?php
			if ( empty( $authors ) ) {
				echo '<p>' . esc_html__( 'No author views recorded for this period.', 'wsh-views-counter-pro' ) . '</p>';
			} else {
				self::render_authors_table( $authors, $authors_map, $from, $to, $selected_post_type, $selected_author_id );
			}
			?>

			<?php if ( $has_author_id ) : ?>
				<hr style="margin:30px 0;" />
				<h2>
					<?php
					printf(
						/* translators: %s: author name */
						esc_html__( 'Details for %s', 'wsh-views-counter-pro' ),
						esc_html( $author_name )
					);
					?>
				</h2>

				<div style="display:flex; gap:30px; flex-wrap:wrap;">
					<div style="flex:1; min-width:280px;">
						<h3><?php esc_html_e( 'Top Posts by this Author', 'wsh-views-counter-pro' ); ?></h3>
						<?php
						if ( empty( $author_posts ) ) {
							echo '<p>' . esc_html__( 'No posts found for this author in the selected period.', 'wsh-views-counter-pro' ) . '</p>';
						} else {
							self::render_author_posts_table( $author_posts );
						}
						?>
					</div>

					<div style="flex:1; min-width:280px;">
						<h3><?php esc_html_e( 'Daily Views (author total)', 'wsh-views-counter-pro' ); ?></h3>
						<?php
						if ( empty( $author_daily ) ) {
							echo '<p>' . esc_html__( 'No daily data for this author.', 'wsh-views-counter-pro' ) . '</p>';
						} else {
							self::render_author_daily_table( $author_daily );
						}
						?>
					</div>
				</div>
			<?php endif; ?>

			<p style="margin-top:15px; text-align:center; font-size:11px; color:#777;">
				<?php esc_html_e( 'Author analytics are based on aggregated daily logs stored in the wsh_views_counter table.', 'wsh-views-counter-pro' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render main authors table.
	 *
	 * @param array $authors      Summary rows.
	 * @param array $authors_map  [author_id => name].
	 * @param string $from        From date.
	 * @param string $to          To date.
	 * @param string $post_type   Selected post type.
	 * @param int    $selected_id Selected author ID.
	 */
	protected static function render_authors_table( $authors, $authors_map, $from, $to, $post_type, $selected_id ) {

		ob_start();
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:40px;">#</th>
					<th><?php esc_html_e( 'Author', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:100px; text-align:right;"><?php esc_html_e( 'Posts', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:120px; text-align:right;"><?php esc_html_e( 'Total views', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:120px; text-align:right;"><?php esc_html_e( 'Avg views / post', 'wsh-views-counter-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$i = 1;
				foreach ( $authors as $row ) :
					$author_id = isset( $row['author_id'] ) ? (int) $row['author_id'] : 0;
					$views     = isset( $row['views'] ) ? (int) $row['views'] : 0;
					$posts     = isset( $row['posts'] ) ? (int) $row['posts'] : 0;

					$name = isset( $authors_map[ $author_id ] ) ? $authors_map[ $author_id ] : '';
					if ( '' === $name ) {
						$name = $author_id > 0
							? sprintf( __( 'Author #%d', 'wsh-views-counter-pro' ), $author_id )
							: __( 'Unknown author', 'wsh-views-counter-pro' );
					}

					$avg = $posts > 0 ? round( $views / $posts ) : 0;

					$link_args = array(
						'page'             => 'wsh-vc-pro-authors',
						'wsh_auth_from'    => $from,
						'wsh_auth_to'      => $to,
						'wsh_auth_post_type' => $post_type,
						'wsh_auth_author'  => $author_id,
					);
					$detail_link = add_query_arg( $link_args, admin_url( 'admin.php' ) );
					?>
					<tr>
						<td><?php echo (int) $i; ?></td>
						<td>
							<a href="<?php echo esc_url( $detail_link ); ?>">
								<?php echo esc_html( $name ); ?>
							</a>
							<?php if ( $selected_id === $author_id ) : ?>
								<span style="color:#2271b1;">&larr; <?php esc_html_e( 'selected', 'wsh-views-counter-pro' ); ?></span>
							<?php endif; ?>
						</td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $posts ) ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $views ) ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $avg ) ); ?></td>
					</tr>
					<?php
					$i++;
				endforeach;
				?>
			</tbody>
		</table>
		<?php
		echo ob_get_clean();
	}

	/**
	 * Render "Top posts by this author" table.
	 *
	 * @param array $author_posts Rows with post_id, views.
	 */
	protected static function render_author_posts_table( $author_posts ) {

		ob_start();
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:40px;">#</th>
					<th><?php esc_html_e( 'Post', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:120px; text-align:right;"><?php esc_html_e( 'Views', 'wsh-views-counter-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$i = 1;
				foreach ( $author_posts as $row ) :
					$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
					$views   = isset( $row['views'] ) ? (int) $row['views'] : 0;

					if ( ! $post_id ) {
						continue;
					}

					$title = get_the_title( $post_id );
					if ( '' === $title ) {
						$title = sprintf( __( 'Post #%d', 'wsh-views-counter-pro' ), $post_id );
					}

					$link_front = get_permalink( $post_id );
					?>
					<tr>
						<td><?php echo (int) $i; ?></td>
						<td>
							<a href="<?php echo esc_url( $link_front ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $title ); ?>
							</a>
						</td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $views ) ); ?></td>
					</tr>
					<?php
					$i++;
				endforeach;
				?>
			</tbody>
		</table>
		<?php
		echo ob_get_clean();
	}

	/**
	 * Render "Daily views for this author" table.
	 *
	 * @param array $author_daily Rows with view_date, views.
	 */
	protected static function render_author_daily_table( $author_daily ) {

		ob_start();
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:120px; text-align:right;"><?php esc_html_e( 'Views', 'wsh-views-counter-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $author_daily as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['view_date'] ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( (int) $row['views'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		echo ob_get_clean();
	}
}
