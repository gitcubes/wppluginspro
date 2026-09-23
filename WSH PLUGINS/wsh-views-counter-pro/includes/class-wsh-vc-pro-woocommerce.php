<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PRO module: WooCommerce Analytics.
 *
 * - Top products by views
 * - Views → Orders + Add to cart funnel
 * - Category analytics
 * - Hourly heatmap for products
 * - Trending products (reuses trending engine)
 */
class WSH_VC_Pro_WooCommerce {

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
			__( 'WooCommerce Analytics', 'wsh-views-counter-pro' ),
			__( 'WooCommerce Analytics', 'wsh-views-counter-pro' ),
			$capability,
			'wsh-vc-pro-woocommerce',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render WooCommerce Analytics page.
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

		// WooCommerce mora da postoji.
		if ( ! class_exists( 'WooCommerce' ) ) {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'WooCommerce is not active. WooCommerce Analytics requires WooCommerce plugin.', 'wsh-views-counter-pro' ) .
				'</p></div>';
			return;
		}

		$views_table  = $wpdb->prefix . 'wsh_views_counter';
		$live_table   = $wpdb->prefix . 'wsh_views_live_hits';
		$funnel_table = $wpdb->prefix . 'wsh_views_woo_funnel';

		// Safety: proveri da li views tabela postoji.
		$like        = $wpdb->esc_like( $views_table );
		$found_table = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$like
			)
		);

		if ( $found_table !== $views_table ) {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Views table (wsh_views_counter) not found. Please make sure the free WSH Views Counter plugin is active and database tables are created.', 'wsh-views-counter-pro' ) .
				'</p></div>';
			return;
		}

		// Funnel tabela (add_to_cart log) – opciono.
		$like_funnel  = $wpdb->esc_like( $funnel_table );
		$found_funnel = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$like_funnel
			)
		);
		$funnel_enabled = ( $found_funnel === $funnel_table );

		if ( ! $funnel_enabled ) {
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'WooCommerce funnel table (wsh_views_woo_funnel) not found. Add-to-cart stats will be 0 until the free plugin creates and populates this table.', 'wsh-views-counter-pro' ) .
				'</p></div>';
		}

		// ===== Date range filter (default last 30 days) =====.
		$today        = current_time( 'Y-m-d' );
		$default_from = date( 'Y-m-d', strtotime( '-30 days', current_time( 'timestamp' ) ) );

		$from = isset( $_GET['wsh_woo_from'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_woo_from'] ) ) : $default_from; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to   = isset( $_GET['wsh_woo_to'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_woo_to'] ) ) : $today;           // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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

		$from_dt = $from . ' 00:00:00';
		$to_dt   = $to . ' 23:59:59';

		// 1) Top proizvodi po pogledima + orders + funnel (add_to_cart + stope).
		$top_products = self::get_top_products_stats( $from, $to, $from_dt, $to_dt, 20, $funnel_enabled ? $funnel_table : '' );

		// 2) Category analytics.
		$category_stats = self::get_category_stats( $from, $to, $from_dt, $to_dt );

		// 3) Hourly heatmap za proizvode.
		$hourly_data = self::get_product_hourly_heatmap( $from_dt, $to_dt, $live_table );

		// 4) Trending proizvodi.
		$trending = self::get_trending_products();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WooCommerce Analytics (PRO)', 'wsh-views-counter-pro' ); ?></h1>

			<form method="get" style="margin-bottom: 15px;">
				<input type="hidden" name="page" value="wsh-vc-pro-woocommerce" />
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Date range', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<label>
								<?php esc_html_e( 'From:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_woo_from" value="<?php echo esc_attr( $from ); ?>" />
							</label>
							&nbsp;&nbsp;
							<label>
								<?php esc_html_e( 'To:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_woo_to" value="<?php echo esc_attr( $to ); ?>" />
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

			<hr />

			<h2><?php esc_html_e( 'Top Products by Views & Funnel', 'wsh-views-counter-pro' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Funnel is calculated as product views → add to cart → completed/processing orders in the selected period.', 'wsh-views-counter-pro' ); ?>
			</p>
			<?php
			if ( empty( $top_products ) ) {
				echo '<p>' . esc_html__( 'No product data found for this period.', 'wsh-views-counter-pro' ) . '</p>';
			} else {
				self::render_top_products_table( $top_products );
			}
			?>

			<hr style="margin:30px 0;" />

			<h2><?php esc_html_e( 'Product Categories Analytics', 'wsh-views-counter-pro' ); ?></h2>
			<?php
			if ( empty( $category_stats ) ) {
				echo '<p>' . esc_html__( 'No category data found for this period.', 'wsh-views-counter-pro' ) . '</p>';
			} else {
				self::render_category_table( $category_stats );
			}
			?>

			<hr style="margin:30px 0;" />

			<h2><?php esc_html_e( 'Hourly Heatmap for Products', 'wsh-views-counter-pro' ); ?></h2>
			<?php
			if ( empty( $hourly_data ) ) {
				echo '<p>' . esc_html__( 'No live hits for products in the selected period.', 'wsh-views-counter-pro' ) . '</p>';
			} else {
				echo self::render_hourly_heatmap_table( $hourly_data );
			}
			?>

			<hr style="margin:30px 0;" />

			<h2><?php esc_html_e( 'Trending Products', 'wsh-views-counter-pro' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Trending products are calculated using the same engine as trending posts (last days boost).', 'wsh-views-counter-pro' ); ?>
			</p>
			<?php
			if ( empty( $trending ) ) {
				echo '<p>' . esc_html__( 'No trending products found.', 'wsh-views-counter-pro' ) . '</p>';
			} else {
				self::render_trending_products_list( $trending );
			}
			?>

			<p style="margin-top:15px; text-align:center; font-size:11px; color:#777;">
				<?php esc_html_e( 'WooCommerce analytics are based on the WSH Views Counter logs, WooCommerce order stats and Woo funnel events logged in the free plugin.', 'wsh-views-counter-pro' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Top products: views + add_to_cart + orders + conversion.
	 *
	 * @return array
	 */
	protected static function get_top_products_stats( $from, $to, $from_dt, $to_dt, $limit = 20, $funnel_table = '' ) {
		global $wpdb;

		$views_table = $wpdb->prefix . 'wsh_views_counter';
		$posts_table = $wpdb->posts;

		// 1) Views per product (product post_type).
		$sql = $wpdb->prepare(
			"SELECT v.post_id, SUM(v.view_count) AS views
			 FROM {$views_table} v
			 JOIN {$posts_table} p ON p.ID = v.post_id
			 WHERE v.post_type = 'product'
			   AND v.view_date BETWEEN %s AND %s
			   AND p.post_status = 'publish'
			 GROUP BY v.post_id
			 ORDER BY views DESC
			 LIMIT %d",
			$from,
			$to,
			$limit
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( empty( $rows ) ) {
			return array();
		}

		$product_ids = array_map(
			'intval',
			wp_list_pluck( $rows, 'post_id' )
		);

		// 2) Orders per product from wc_order_product_lookup (ako postoji).
		$order_counts = self::get_orders_per_product( $product_ids, $from_dt, $to_dt );

		// 3) Add to cart po proizvodu iz funnel tabele (ako je prosleđena).
		$add_to_cart_map = array();

		if ( ! empty( $funnel_table ) && ! empty( $product_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );

			$sql_funnel = $wpdb->prepare(
				"SELECT product_id,
				        SUM(qty) AS add_qty,
				        COUNT(*) AS add_events
				 FROM {$funnel_table}
				 WHERE event_type = 'add_to_cart'
				   AND event_time BETWEEN %s AND %s
				   AND product_id IN ({$placeholders})
				 GROUP BY product_id",
				array_merge(
					array( $from_dt, $to_dt ),
					$product_ids
				)
			);

			$f_rows = $wpdb->get_results( $sql_funnel, ARRAY_A );

			if ( ! empty( $f_rows ) ) {
				foreach ( $f_rows as $f_row ) {
					$pid = (int) $f_row['product_id'];
					$add_to_cart_map[ $pid ] = array(
						'qty'    => isset( $f_row['add_qty'] ) ? (int) $f_row['add_qty'] : 0,
						'events' => isset( $f_row['add_events'] ) ? (int) $f_row['add_events'] : 0,
					);
				}
			}
		}

		$stats = array();

		foreach ( $rows as $row ) {
			$post_id = (int) $row['post_id'];
			$views   = (int) $row['views'];

			$orders = isset( $order_counts[ $post_id ] ) ? (int) $order_counts[ $post_id ] : 0;

			$add_qty    = isset( $add_to_cart_map[ $post_id ] ) ? (int) $add_to_cart_map[ $post_id ]['qty'] : 0;
			$add_events = isset( $add_to_cart_map[ $post_id ] ) ? (int) $add_to_cart_map[ $post_id ]['events'] : 0;

			$cart_rate  = 0.0;
			$order_rate = 0.0;

			if ( $views > 0 ) {
				if ( $add_qty > 0 ) {
					$cart_rate = ( $add_qty / $views ) * 100;
				}
				if ( $orders > 0 ) {
					$order_rate = ( $orders / $views ) * 100;
				}
			}

			$stats[] = array(
				'post_id'     => $post_id,
				'views'       => $views,
				'add_qty'     => $add_qty,
				'add_events'  => $add_events,
				'orders'      => $orders,
				'cart_rate'   => $cart_rate,
				'order_rate'  => $order_rate,
			);
		}

		return $stats;
	}

	/**
	 * Get orders count per product using wc_order_product_lookup + wc_order_stats if available.
	 *
	 * @param array  $product_ids
	 * @param string $from_dt
	 * @param string $to_dt
	 * @return array product_id => orders_count
	 */
	protected static function get_orders_per_product( $product_ids, $from_dt, $to_dt ) {
		global $wpdb;

		$product_ids = array_filter( array_map( 'intval', (array) $product_ids ) );
		if ( empty( $product_ids ) ) {
			return array();
		}

		$table_opl = $wpdb->prefix . 'wc_order_product_lookup';
		$table_os  = $wpdb->prefix . 'wc_order_stats';

		// Provera da li lookup tabele postoje.
		$like_opl  = $wpdb->esc_like( $table_opl );
		$found_opl = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $like_opl ) );

		$like_os  = $wpdb->esc_like( $table_os );
		$found_os = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $like_os ) );

		if ( $found_opl !== $table_opl || $found_os !== $table_os ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );

		// Samo valjani statusi (completed / processing / on-hold itd.).
		$allowed_statuses   = array( 'wc-completed', 'wc-processing', 'wc-on-hold' );
		$status_placeholder = implode( ',', array_fill( 0, count( $allowed_statuses ), '%s' ) );

		$sql = "
			SELECT l.product_id, COUNT(DISTINCT l.order_id) AS orders_count
			FROM {$table_opl} l
			JOIN {$table_os} s ON s.order_id = l.order_id
			WHERE l.product_id IN ($placeholders)
			AND s.date_created BETWEEN %s AND %s
			AND s.status IN ($status_placeholder)
			GROUP BY l.product_id
		";

		$params = array_merge( $product_ids, array( $from_dt, $to_dt ), $allowed_statuses );

		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $params ),
			ARRAY_A
		);

		$result = array();

		foreach ( $rows as $row ) {
			$product_id = (int) $row['product_id'];
			$orders     = (int) $row['orders_count'];

			$result[ $product_id ] = $orders;
		}

		return $result;
	}


	/**
	 * Render table of top products.
	 *
	 * @param array $stats
	 */
	protected static function render_top_products_table( $stats ) {
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:40px;">#</th>
					<th><?php esc_html_e( 'Product', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:90px; text-align:right;"><?php esc_html_e( 'Views', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:110px; text-align:right;"><?php esc_html_e( 'Add to cart', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:90px; text-align:right;"><?php esc_html_e( 'Orders', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:90px; text-align:right;"><?php esc_html_e( 'Cart rate', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:110px; text-align:right;"><?php esc_html_e( 'Order rate', 'wsh-views-counter-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$i = 1;
				foreach ( $stats as $row ) :
					$post_id    = (int) $row['post_id'];
					$views      = (int) $row['views'];
					$orders     = (int) $row['orders'];
					$add_qty    = (int) $row['add_qty'];
					$cart_rate  = (float) $row['cart_rate'];
					$order_rate = (float) $row['order_rate'];

					$title = get_the_title( $post_id );
					if ( '' === $title ) {
						$title = sprintf( __( 'Product #%d', 'wsh-views-counter-pro' ), $post_id );
					}
					$link       = get_edit_post_link( $post_id );
					$front_link = get_permalink( $post_id );
					?>
					<tr>
						<td><?php echo (int) $i; ?></td>
						<td>
							<a href="<?php echo esc_url( $front_link ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $title ); ?>
							</a>
							<?php if ( $link ) : ?>
								<br /><a href="<?php echo esc_url( $link ); ?>" style="font-size:11px;"><?php esc_html_e( 'Edit product', 'wsh-views-counter-pro' ); ?></a>
							<?php endif; ?>
						</td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $views ) ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $add_qty ) ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $orders ) ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $cart_rate, 1 ) ); ?>%</td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $order_rate, 1 ) ); ?>%</td>
					</tr>
					<?php
					$i++;
				endforeach;
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Category analytics (product_cat): views + orders.
	 *
	 * @return array
	 */
	protected static function get_category_stats( $from, $to, $from_dt, $to_dt ) {
		global $wpdb;

		$views_table = $wpdb->prefix . 'wsh_views_counter';
		$posts_table = $wpdb->posts;
		$tr          = $wpdb->term_relationships;
		$tt          = $wpdb->term_taxonomy;
		$t           = $wpdb->terms;

		// Views po category.
		$sql_views = $wpdb->prepare(
			"SELECT t.term_id, t.name, SUM(v.view_count) AS views
			 FROM {$views_table} v
			 JOIN {$posts_table} p ON p.ID = v.post_id
			 JOIN {$tr} tr ON tr.object_id = p.ID
			 JOIN {$tt} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			 JOIN {$t} t ON t.term_id = tt.term_id
			 WHERE v.post_type = 'product'
			   AND v.view_date BETWEEN %s AND %s
			   AND p.post_status = 'publish'
			   AND tt.taxonomy = 'product_cat'
			 GROUP BY t.term_id, t.name
			 ORDER BY views DESC",
			$from,
			$to
		);

		$rows = $wpdb->get_results( $sql_views, ARRAY_A );

		if ( empty( $rows ) ) {
			return array();
		}

		$term_ids = array_map(
			'intval',
			wp_list_pluck( $rows, 'term_id' )
		);

		// Orders po category (preko wc_order_product_lookup + product_cat).
		$order_counts = self::get_orders_per_category( $term_ids, $from_dt, $to_dt );

		$stats = array();

		foreach ( $rows as $row ) {
			$term_id = (int) $row['term_id'];
			$views   = (int) $row['views'];
			$orders  = isset( $order_counts[ $term_id ] ) ? (int) $order_counts[ $term_id ] : 0;

			$conversion = 0.0;
			if ( $views > 0 && $orders > 0 ) {
				$conversion = ( $orders / $views ) * 100;
			}

			$stats[] = array(
				'term_id'    => $term_id,
				'name'       => $row['name'],
				'views'      => $views,
				'orders'     => $orders,
				'conversion' => $conversion,
			);
		}

		return $stats;
	}

	/**
	 * Orders per category using wc_order_product_lookup and product_cat relationship.
	 *
	 * @param array  $term_ids
	 * @param string $from_dt
	 * @param string $to_dt
	 * @return array term_id => orders_count
	 */
	protected static function get_orders_per_category( $term_ids, $from_dt, $to_dt ) {
		global $wpdb;

		$term_ids = array_filter( array_map( 'intval', (array) $term_ids ) );
		if ( empty( $term_ids ) ) {
			return array();
		}

		$table_opl = $wpdb->prefix . 'wc_order_product_lookup';
		$table_os  = $wpdb->prefix . 'wc_order_stats';

		$posts_table = $wpdb->posts;
		$tr          = $wpdb->term_relationships;
		$tt          = $wpdb->term_taxonomy;

		// Provera da li lookup tabele postoje.
		$like_opl  = $wpdb->esc_like( $table_opl );
		$found_opl = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $like_opl ) );

		$like_os  = $wpdb->esc_like( $table_os );
		$found_os = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $like_os ) );

		if ( $found_opl !== $table_opl || $found_os !== $table_os ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );

		$allowed_statuses   = array( 'wc-completed', 'wc-processing', 'wc-on-hold' );
		$status_placeholder = implode( ',', array_fill( 0, count( $allowed_statuses ), '%s' ) );

		$sql = "
			SELECT tt.term_id, COUNT(DISTINCT l.order_id) AS orders_count
			FROM {$table_opl} l
			JOIN {$posts_table} p ON p.ID = l.product_id
			JOIN {$tr} tr ON tr.object_id = p.ID
			JOIN {$tt} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			JOIN {$table_os} s ON s.order_id = l.order_id
			WHERE tt.taxonomy = 'product_cat'
			  AND tt.term_id IN ($placeholders)
			  AND s.date_created BETWEEN %s AND %s
			  AND s.status IN ($status_placeholder)
			GROUP BY tt.term_id
		";

		$params = array_merge( $term_ids, array( $from_dt, $to_dt ), $allowed_statuses );

		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $params ),
			ARRAY_A
		);

		$result = array();

		foreach ( $rows as $row ) {
			$term_id = (int) $row['term_id'];
			$orders  = (int) $row['orders_count'];

			$result[ $term_id ] = $orders;
		}

		return $result;
	}

	/**
	 * Render category table.
	 *
	 * @param array $stats
	 */
	protected static function render_category_table( $stats ) {
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:40px;">#</th>
					<th><?php esc_html_e( 'Category', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:100px; text-align:right;"><?php esc_html_e( 'Views', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:100px; text-align:right;"><?php esc_html_e( 'Orders', 'wsh-views-counter-pro' ); ?></th>
					<th style="width:120px; text-align:right;"><?php esc_html_e( 'Conversion', 'wsh-views-counter-pro' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$i = 1;
				foreach ( $stats as $row ) :
					$views      = (int) $row['views'];
					$orders     = (int) $row['orders'];
					$conversion = (float) $row['conversion'];

					$link = get_term_link( (int) $row['term_id'], 'product_cat' );
					?>
					<tr>
						<td><?php echo (int) $i; ?></td>
						<td>
							<?php if ( ! is_wp_error( $link ) ) : ?>
								<a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $row['name'] ); ?>
								</a>
							<?php else : ?>
								<?php echo esc_html( $row['name'] ); ?>
							<?php endif; ?>
						</td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $views ) ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $orders ) ); ?></td>
						<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $conversion, 2 ) ); ?>%</td>
					</tr>
					<?php
					$i++;
				endforeach;
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Hourly heatmap for products (0–23).
	 *
	 * @param string $from_dt
	 * @param string $to_dt
	 * @param string $live_table
	 * @return array [0..23] => count
	 */
	protected static function get_product_hourly_heatmap( $from_dt, $to_dt, $live_table ) {
		global $wpdb;

		// Proverimo da li live tabela postoji.
		$like        = $wpdb->esc_like( $live_table );
		$found_table = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$like
			)
		);

		if ( $found_table !== $live_table ) {
			return array();
		}

		$sql = $wpdb->prepare(
			"SELECT HOUR(view_time) AS h, COUNT(*) AS cnt
			 FROM {$live_table}
			 WHERE post_type = 'product'
			   AND view_time BETWEEN %s AND %s
			 GROUP BY h
			 ORDER BY h ASC",
			$from_dt,
			$to_dt
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( empty( $rows ) ) {
			return array();
		}

		$hours = array_fill( 0, 24, 0 );

		foreach ( $rows as $row ) {
			$h   = isset( $row['h'] ) ? (int) $row['h'] : 0;
			$cnt = isset( $row['cnt'] ) ? (int) $row['cnt'] : 0;

			if ( $h >= 0 && $h <= 23 ) {
				$hours[ $h ] = $cnt;
			}
		}

		return $hours;
	}

	/**
	 * Render simple hourly heatmap table for products.
	 *
	 * @param array $hours [0..23] => count
	 * @return string
	 */
	protected static function render_hourly_heatmap_table( $hours ) {

		if ( empty( $hours ) || ! is_array( $hours ) ) {
			return '';
		}

		$max = 0;
		foreach ( $hours as $cnt ) {
			$cnt = (int) $cnt;
			if ( $cnt > $max ) {
				$max = $cnt;
			}
		}

		ob_start();
		?>
		<table class="widefat striped" style="border-collapse:collapse; table-layout:fixed; font-size:11px;">
			<thead>
				<tr>
					<th style="width:120px;"><?php esc_html_e( 'Hour of day', 'wsh-views-counter-pro' ); ?></th>
					<?php for ( $h = 0; $h < 24; $h++ ) : ?>
						<th style="text-align:center;"><?php echo esc_html( $h ); ?></th>
					<?php endfor; ?>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><strong><?php esc_html_e( 'All products', 'wsh-views-counter-pro' ); ?></strong></td>
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
			</tbody>
		</table>
		<?php

		return ob_get_clean();
	}

	/**
	 * Trending products list via existing Trending engine.
	 *
	 * @return array
	 */
	protected static function get_trending_products() {
		if ( ! class_exists( 'WSH_VC_Pro_Trending' ) ) {
			return array();
		}

		$post_ids = WSH_VC_Pro_Trending::get_posts(
			array(
				'mode'      => 'trending',
				'limit'     => 10,
				'days'      => 7,
				'post_type' => 'product',
			)
		);

		$post_ids = array_filter(
			array_map( 'intval', is_array( $post_ids ) ? $post_ids : array() )
		);

		return $post_ids;
	}

	/**
	 * Render trending products list (simple).
	 *
	 * @param array $product_ids
	 */
	protected static function render_trending_products_list( $product_ids ) {

		if ( empty( $product_ids ) ) {
			return;
		}

		echo '<ul class="wsh-vc-pro-trending-products">';
		foreach ( $product_ids as $post_id ) {
			$post_id = (int) $post_id;

			$title = get_the_title( $post_id );
			if ( '' === $title ) {
				$title = sprintf( __( 'Product #%d', 'wsh-views-counter-pro' ), $post_id );
			}

			$link = get_permalink( $post_id );

			$views = 0;
			if ( class_exists( 'WSH_Views_Counter' ) && method_exists( 'WSH_Views_Counter', 'get_post_views' ) ) {
				$views = (int) WSH_Views_Counter::get_post_views( $post_id );
			}

			echo '<li>';
			echo '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer">';
			echo esc_html( $title );
			echo '</a>';
			echo ' <span style="font-size:11px; color:#555;">(' . esc_html( number_format_i18n( $views ) ) . ' ' . esc_html__( 'views', 'wsh-views-counter-pro' ) . ')</span>';
			echo '</li>';
		}
		echo '</ul>';
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
