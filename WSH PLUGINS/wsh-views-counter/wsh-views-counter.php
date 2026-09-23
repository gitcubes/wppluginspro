<?php
/**
 * WSH Views Counter
 *
 * Plugin Name: WSH Views Counter
 * Plugin URI:  https://wordpress.org/plugins/wsh-views-counter/
 * Description: Enables WordPress views counter functions (based on and extended from WSH Views Counter).
 * Version:     1.0.1
 * Author: Web Solutions Hub LLC
 * Author URI: https://websolutions.online
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain: wsh-views-counter
 * Domain Path: /languages
 * Requires at least: 4.9
 * Requires PHP: 5.2.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Load the WP_List_Table class used for displaying views in the admin area.
 */
require_once plugin_dir_path( __FILE__ ) . 'inc/views-counter-table.php';

if ( ! class_exists( 'WSH_Views_Counter' ) ) :

class WSH_Views_Counter {

	private static $settings              = array();
	private static $supported_post_types = array( 'post', 'page', 'product' );

	private function __construct() {}

	/**
	 * Initialize all plugin hooks.
	 */
	public static function init_actions() {

		// Define plugin constants.
		self::define_constants();

		// Admin hooks.
		add_action( 'admin_init', array( __CLASS__, 'on_admin_init' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_items' ) );

		// "Views" column in the admin lists for all supported post types.
		$post_types = self::get_supported_post_types();

		foreach ( $post_types as $post_type ) {

			// Add the column.
			add_filter(
				"manage_{$post_type}_posts_columns",
				array( __CLASS__, 'add_views_column' )
			);

			// Fill the column with values.
			add_action(
				"manage_{$post_type}_posts_custom_column",
				array( __CLASS__, 'render_views_column' ),
				10,
				2
			);

			// Make the "Views" column sortable.
			add_filter(
				"manage_edit-{$post_type}_sortable_columns",
				array( __CLASS__, 'make_post_columns_sortable' )
			);
		}

		//add_filter( 'manage_post_posts_columns', array( __CLASS__, 'add_views_column' ) );
		//add_action( 'manage_post_posts_custom_column', array( __CLASS__, 'render_views_column' ), 10, 2 );
		//add_filter( 'manage_edit-post_sortable_columns', array( __CLASS__, 'make_post_columns_sortable' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'orderby_views_column' ) );
		

		// Frontend JS (global variable + script enqueue).
		add_action( 'wp_head', array( __CLASS__, 'frontend_js_bootstrap' ) );

		// Display: shortcodes & automatic output.
		add_shortcode( 'wsh_views', array( __CLASS__, 'shortcode_views' ) );
		add_filter( 'the_content', array( __CLASS__, 'maybe_append_views_to_content' ) );

		// ➕ NEW: display views in the submit box on the post edit screen.
		add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'render_post_views_in_submitbox' ) );

		// Meta box for manually editing the number of views.
    	//add_action( 'add_meta_boxes', array( __CLASS__, 'register_views_meta_box' ) );
    	add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_post_scripts' ) );

		// Admin dashboard widget.
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_dashboard_widget' ) );

		// WooCommerce funnel tracking (add to cart, orders).
		if ( class_exists( 'WooCommerce' ) ) {
			// Log "Add to cart" event per product.
			add_action(
				'woocommerce_add_to_cart',
				array( __CLASS__, 'on_woocommerce_add_to_cart' ),
				10,
				6
			);

			// Log "Order" events per product (when checkout is processed).
			add_action(
				'woocommerce_checkout_order_processed',
				array( __CLASS__, 'on_woocommerce_checkout_order_processed' ),
				10,
				3
			);
		}

		// PRO upsell notice.
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_pro_admin_notice' ) );

	}

	/**
	 * Show a dismissible admin notice suggesting PRO, only for admins.
	 */
	public static function maybe_show_pro_admin_notice() {

		if ( wsh_views_counter_pro_is_active() ) {
			return; // Ima PRO → ne gnjavi ga upsellom.
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// If user dismissed.
		if ( (int) get_option( 'wsh_views_counter_pro_notice_dismissed', 0 ) === 1 ) {
			return;
		}

		// Restrict to certain screens (Dashboard + plugin pages).
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! in_array( $screen->id, array( 'dashboard', 'toplevel_page_wsh_views_counter_reports', 'views-counter_page_wsh_views_counter_settings' ), true ) ) {
			return;
		}

		$pro_url    = 'https://websolutions.online/wshplugins/wsh-views-counter-pro';
		$dismiss_url = add_query_arg(
			array(
				'wsh_vc_dismiss_pro_notice' => '1',
			)
		);
		?>
		<div class="notice notice-info is-dismissible wsh-vc-pro-notice">
			<p>
				<strong><?php esc_html_e( 'Need deeper traffic analytics?', 'wsh-views-counter' ); ?></strong>
				<?php esc_html_e( 'Upgrade to WSH Views Counter PRO for geo, referrer, real-time and WooCommerce analytics on top of your existing data.', 'wsh-views-counter' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $pro_url ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'View PRO features', 'wsh-views-counter' ); ?>
				</a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button-link" style="margin-left:10px;">
					<?php esc_html_e( 'Dismiss', 'wsh-views-counter' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Returns the list of post types for which views are counted,
	 * with the ability to modify it via a filter hook.
	 */
	public static function get_supported_post_types() {
		$default = self::$supported_post_types;

		$stored = get_option( 'wsh_views_counter-post-types', $default );

		if ( is_array( $stored ) ) {
			$types = array_map( 'sanitize_key', $stored );
		} elseif ( is_string( $stored ) && '' !== $stored ) {
			$types = array_map( 'sanitize_key', array_map( 'trim', explode( ',', $stored ) ) );
		} else {
			$types = $default;
		}

		$types = array_values( array_unique( $types ) );

		/**
		 * Filter: allow developers to add/remove post types.
		 */
		return apply_filters( 'wsh_views_counter_supported_post_types', $types );
	}

	/**
	 * Setup plugin constants.
	 *
	 * @return void
	 */
	private static function define_constants() {

		if ( ! defined( 'WSH_VIEWS_COUNTER_VERSION' ) ) {
			define( 'WSH_VIEWS_COUNTER_VERSION', '1.3.31' );
		}

		if ( ! defined( 'WSH_VIEWS_COUNTER_URL' ) ) {
			define( 'WSH_VIEWS_COUNTER_URL', plugins_url( '', __FILE__ ) );
		}

		if ( ! defined( 'WSH_VIEWS_COUNTER_PATH' ) ) {
			define( 'WSH_VIEWS_COUNTER_PATH', plugin_dir_path( __FILE__ ) );
		}

		if ( ! defined( 'WSH_VIEWS_COUNTER_BASENAME' ) ) {
			define( 'WSH_VIEWS_COUNTER_BASENAME', plugin_basename( __FILE__ ) );
		}

		// Back-compat constant.
		if ( ! defined( 'WSH_VIEWS_COUNTER_PLUGIN_PATH' ) ) {
			define( 'WSH_VIEWS_COUNTER_PLUGIN_PATH', WSH_VIEWS_COUNTER_URL );
		}
	}

	/**
	 * Register dashboard widget on the main WordPress Dashboard screen.
	 */
	public static function register_dashboard_widget() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'wsh_views_counter_dashboard_widget',
			__( 'Post Views', 'wsh-views-counter' ),
			array( __CLASS__, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render the dashboard widget: simple line chart + top 10 posts for selected month.
	*/
	public static function render_dashboard_widget() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wsh_views_counter';

		// Safety: make sure table exists.
		$like        = $wpdb->esc_like( $table_name );
		$found_table = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$like
			)
		);

		if ( $found_table !== $table_name ) {
			echo '<p>' . esc_html__( 'Views table not found. Please visit any post on the frontend to initialize the plugin.', 'wsh-views-counter' ) . '</p>';
			return;
		}

		// ===== Month selection via GET param =====.
		$param_month = isset( $_GET['wsh_views_month'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_views_month'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( preg_match( '/^\d{4}-\d{2}$/', $param_month ) ) {
			// Format YYYY-MM.
			$month_ts = strtotime( $param_month . '-01 00:00:00' );
			if ( ! $month_ts ) {
				$month_ts = current_time( 'timestamp' );
				$param_month = date( 'Y-m', $month_ts );
			}
		} else {
			$month_ts    = current_time( 'timestamp' );
			$param_month = date( 'Y-m', $month_ts );
		}

		// Start/end of selected month (MySQL date format).
		$month_start = date( 'Y-m-01', $month_ts );
		$month_end   = date( 'Y-m-t', $month_ts );
		$month_label = date_i18n( 'F Y', $month_ts );

		// Previous / next month timestamps.
		$prev_month_ts = strtotime( '-1 month', $month_ts );
		$next_month_ts = strtotime( '+1 month', $month_ts );

		// URLs for navigation in Dashboard (/wp-admin/index.php).
		$prev_month_param = date( 'Y-m', $prev_month_ts );
		$next_month_param = date( 'Y-m', $next_month_ts );

		$prev_url = add_query_arg(
			array( 'wsh_views_month' => $prev_month_param ),
			admin_url( 'index.php' )
		);

		// Current month URL – možemo ili bez parametra ili sa parametrom; ovde ostavljamo sa parametrom.
		$current_url = add_query_arg(
			array( 'wsh_views_month' => $param_month ),
			admin_url( 'index.php' )
		);

		$next_url = add_query_arg(
			array( 'wsh_views_month' => $next_month_param ),
			admin_url( 'index.php' )
		);

		// Supported post types from plugin settings.
		$post_types = self::get_supported_post_types();
		$post_types_sql = '';

		if ( ! empty( $post_types ) && is_array( $post_types ) ) {
			$escaped_types  = array_map( 'esc_sql', $post_types );
			$post_types_sql = " AND p.post_type IN ('" . implode( "','", $escaped_types ) . "')";
		}

		// ===== Daily totals for the chart (selected month) =====.
		$daily_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT wv.view_date, SUM(wv.view_count) AS views
				FROM {$table_name} wv
				LEFT JOIN {$wpdb->posts} p ON p.ID = wv.post_id
				WHERE wv.view_date BETWEEN %s AND %s
				{$post_types_sql}
				AND p.post_status = 'publish'
				GROUP BY wv.view_date
				ORDER BY wv.view_date ASC",
				$month_start,
				$month_end
			),
			ARRAY_A
		);

		$chart_labels      = array();
		$chart_values      = array();
		$total_views_month = 0;

		// 1) mapiraj date => views iz baze
		$views_by_date = array();

		if ( ! empty( $daily_rows ) ) {
			foreach ( $daily_rows as $row ) {
				$date  = $row['view_date'];          // "YYYY-MM-DD"
				$views = (int) $row['views'];
				$views_by_date[ $date ] = $views;
				$total_views_month     += $views;
			}
		}

		// 2) Popuni baš SVE dane u odabranom mesecu
		$year          = (int) date( 'Y', $month_ts );
		$month_numeric = (int) date( 'm', $month_ts );
		$days_in_month = (int) date( 't', $month_ts ); // 28–31

		for ( $day = 1; $day <= $days_in_month; $day++ ) {
			// Labela za X osu (prikaz u grafu).
			$chart_labels[] = (string) $day; // "1", "2", ...

			// Ključ u formatu YYYY-MM-DD – da se slaže sa view_date iz baze.
			$date_key = sprintf( '%04d-%02d-%02d', $year, $month_numeric, $day );

			// Ako postoji u mapi, uzmi broj pregleda, ako ne – 0.
			$chart_values[] = isset( $views_by_date[ $date_key ] )
				? (int) $views_by_date[ $date_key ]
				: 0;
		}



		// ===== Top 10 posts for selected month =====.
		$top_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT wv.post_id, SUM(wv.view_count) AS views
				FROM {$table_name} wv
				LEFT JOIN {$wpdb->posts} p ON p.ID = wv.post_id
				WHERE wv.view_date BETWEEN %s AND %s
				{$post_types_sql}
				AND p.post_status = 'publish'
				GROUP BY wv.post_id
				ORDER BY views DESC
				LIMIT 10",
				$month_start,
				$month_end
			),
			ARRAY_A
		);

		?>
		<div class="wsh-views-counter-dashboard-widget">
			<p class="wsh-views-counter-month-nav" style="margin-bottom:10px; text-align: center;">
				<a href="<?php echo esc_url( $prev_url ); ?>">&laquo; <?php echo esc_html( date_i18n( 'F Y', $prev_month_ts ) ); ?></a>
				&nbsp;&nbsp;
				<a href="<?php echo esc_url( $current_url ); ?>"><strong><?php echo esc_html( $month_label ); ?></strong></a>
				&nbsp;&nbsp;
				<a href="<?php echo esc_url( $next_url ); ?>"><?php echo esc_html( date_i18n( 'F Y', $next_month_ts ) ); ?> &raquo;</a>
			</p>

			<div style="margin-bottom:15px;">
				<canvas id="wsh-views-counter-dashboard-chart" height="160" style="width:100%; max-width:100%;"></canvas>
				<p class="description" style="margin-top:6px;">
					<?php esc_html_e( 'Total views in this month:', 'wsh-views-counter' ); ?>
					<strong><?php echo esc_html( number_format_i18n( $total_views_month ) ); ?></strong>
				</p>
			</div>

			<h4 style="margin-top:15px;"><?php esc_html_e( 'Top Posts', 'wsh-views-counter' ); ?></h4>

			<?php if ( empty( $top_rows ) ) : ?>
				<p><?php esc_html_e( 'No data available for this month yet.', 'wsh-views-counter' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th style="width:40px;">#</th>
							<th><?php esc_html_e( 'Post', 'wsh-views-counter' ); ?></th>
							<th style="width:80px; text-align:right;"><?php esc_html_e( 'Views', 'wsh-views-counter' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$rank = 1;
						foreach ( $top_rows as $row ) :
							$post_id = (int) $row['post_id'];
							$views   = (int) $row['views'];
							if ( ! $post_id ) {
								continue;
							}
							$title = get_the_title( $post_id );
							if ( '' === $title ) {
								$title = sprintf( __( 'Post #%d', 'wsh-views-counter' ), $post_id );
							}
							$link = get_permalink( $post_id );
							?>
							<tr>
								<td><?php echo (int) $rank; ?></td>
								<td>
									<a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( $title ); ?>
									</a>
								</td>
								<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $views ) ); ?></td>
							</tr>
							<?php
							$rank++;
						endforeach;
						?>
						<?php if ( ! wsh_views_counter_pro_is_active() ) { ?>
						<tr>
							<td colspan="3" style="text-align:center; font-size:12px; color:#666;">
								<i><?php esc_html_e( 'Powered by', 'wsh-views-counter' ); ?></i>
								<a href="https://websolutions.online/wshplugins/wsh-views-counter" target="_blank" rel="noopener noreferrer">
									<?php esc_html_e( 'WSH Views Counter', 'wsh-views-counter' ); ?>
								</a>
								&nbsp;·&nbsp;
								<a href="https://websolutions.online/wshplugins/wsh-views-counter-pro" target="_blank" rel="noopener noreferrer">
									<strong><?php esc_html_e( 'Upgrade to PRO', 'wsh-views-counter' ); ?></strong>
								</a>
							</td>
						</tr>
						<?php }else{ ?>
							<tr>
								<td colspan='3' style='text-align: center'><i>Powered by</i> <a href='https://websolutions.online/wshplugins/wsh-views-counter' target='_blank'><i><u>WSH Views Counter</a></u></i></td>
							</tr>							
						<?php } ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<script type="text/javascript">
		(function() {
			var canvas = document.getElementById('wsh-views-counter-dashboard-chart');
			if (!canvas || !canvas.getContext) {
				return;
			}

			var labels = <?php echo wp_json_encode( $chart_labels ); ?>;
			var values = <?php echo wp_json_encode( array_map( 'intval', $chart_values ) ); ?>;

			if (!values.length) {
				return;
			}

			var ctx = canvas.getContext('2d');

			// Retina support
			var dpr  = window.devicePixelRatio || 1;
			var rect = canvas.getBoundingClientRect();
			var cssHeight = 180; // vizuelna visina u CSS pikselima

			canvas.width  = rect.width * dpr;
			canvas.height = cssHeight * dpr;
			ctx.scale(dpr, dpr);

			var width  = rect.width;
			var height = cssHeight;

			var padding = { top: 10, right: 10, bottom: 28, left: 42 };
			var innerWidth  = width  - padding.left - padding.right;
			var innerHeight = height - padding.top  - padding.bottom;

			// max vrednost
			var maxVal = 0;
			for (var i = 0; i < values.length; i++) {
				if (values[i] > maxVal) {
					maxVal = values[i];
				}
			}
			if (maxVal === 0) {
				maxVal = 1;
			}

			// "lepa" max vrednost (za y ose)
			function niceNumber(x) {
				var exp = Math.floor(Math.log10(x));
				var f   = x / Math.pow(10, exp);
				var nf;
				if (f <= 1)      nf = 1;
				else if (f <= 2) nf = 2;
				else if (f <= 5) nf = 5;
				else             nf = 10;
				return nf * Math.pow(10, exp);
			}
			var niceMax   = niceNumber(maxVal);
			var ySteps    = 4;
			var yStepSize = niceMax / ySteps;

			// helperi za koordinate
			var stepX = (labels.length > 1) ? innerWidth / (labels.length - 1) : 0;

			function getX(i) {
				return padding.left + stepX * i;
			}
			function getY(v) {
				return padding.top + innerHeight - (v / niceMax) * innerHeight;
			}

			// pozadina
			ctx.clearRect(0, 0, width, height);
			ctx.fillStyle = '#ffffff';
			ctx.fillRect(0, 0, width, height);

			// horizontalne grid linije + y tick labels
			ctx.strokeStyle = '#e5e5e5';
			ctx.fillStyle   = '#666';
			ctx.lineWidth   = 1;
			ctx.font        = '11px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
			ctx.textAlign   = 'right';
			ctx.textBaseline = 'middle';

			for (var g = 0; g <= ySteps; g++) {
				var val = yStepSize * g;
				var y   = getY(val);

				ctx.beginPath();
				ctx.moveTo(padding.left, y);
				ctx.lineTo(width - padding.right, y);
				ctx.stroke();

				var label = val >= 1000 ? (Math.round(val / 100) / 10) + 'k' : Math.round(val).toString();
				ctx.fillText(label, padding.left - 6, y);
			}

			// x axis linija
			var baseY = getY(0);
			ctx.strokeStyle = '#cccccc';
			ctx.beginPath();
			ctx.moveTo(padding.left, baseY);
			ctx.lineTo(width - padding.right, baseY);
			ctx.stroke();

			// x labels (ne crtamo svaku da ne bude gužva – samo 1., srednji i poslednji dan)
			ctx.fillStyle   = '#666';
			ctx.textAlign   = 'center';
			ctx.textBaseline = 'top';

			if (labels.length) {
				var firstIdx = 0;
				var lastIdx  = labels.length - 1;
				var midIdx   = Math.round(labels.length / 2);

				var xFirst = getX(firstIdx);
				var xMid   = getX(midIdx);
				var xLast  = getX(lastIdx);

				var yLabel = baseY + 4;

				ctx.fillText(labels[firstIdx], xFirst, yLabel);
				if (midIdx !== firstIdx && midIdx !== lastIdx) {
					ctx.fillText(labels[midIdx], xMid, yLabel);
				}
				if (lastIdx !== firstIdx) {
					ctx.fillText(labels[lastIdx], xLast, yLabel);
				}
			}

			// area (ispod linije)
			ctx.beginPath();
			for (var j = 0; j < values.length; j++) {
				var x = getX(j);
				var y = getY(values[j]);
				if (j === 0) {
					ctx.moveTo(x, baseY);
					ctx.lineTo(x, y);
				} else {
					ctx.lineTo(x, y);
				}
			}
			var lastX = getX(values.length - 1);
			ctx.lineTo(lastX, baseY);
			ctx.closePath();
			ctx.fillStyle = 'rgba(0, 115, 170, 0.16)';
			ctx.fill();

			// linija
			ctx.beginPath();
			ctx.strokeStyle = '#0073aa';
			ctx.lineWidth   = 2;
			for (var k = 0; k < values.length; k++) {
				var lx = getX(k);
				var ly = getY(values[k]);
				if (k === 0) {
					ctx.moveTo(lx, ly);
				} else {
					ctx.lineTo(lx, ly);
				}
			}
			ctx.stroke();

			// tačkice
			ctx.fillStyle = '#0073aa';
			for (var m = 0; m < values.length; m++) {
				var px = getX(m);
				var py = getY(values[m]);
				ctx.beginPath();
				ctx.arc(px, py, 3, 0, Math.PI * 2, true);
				ctx.fill();
			}
		})();
		</script>

		<?php
	}



	/**
	 * Add the "Views" column to the posts list.
	 */
	public static function add_views_column( $columns ) {
		$columns['wsh_views_counter'] = __( 'Views', 'wsh-views-counter' );
		return $columns;
	}

	/**
	 * Fill the "Views" column with values.
	 */
	public static function render_views_column( $column_key, $post_id ) {
		if ( 'wsh_views_counter' !== $column_key ) {
			return;
		}

		$views = get_post_meta( $post_id, 'wsh_views_count', true );
		if ( empty( $views ) ) {
			$views = '0';
		}

		echo '<span>' . esc_html( $views ) . '</span>';
	}

	/**
	 * Display "Post Views: X" in the Publish / Status & visibility box
	 * on the post edit screen.
	 */
	public static function render_post_views_in_submitbox() {
		global $post;

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		// Only for supported post types.
		$supported = self::get_supported_post_types();
		if ( ! in_array( $post->post_type, $supported, true ) ) {
			return;
		}

		// Get the raw number of views (integer).
		$views = (int) self::get_post_views( $post->ID );
		if ( $views < 0 ) {
			$views = 0;
		}

		?>
		<span class="misc-pub-section wsh-views-counter-misc wsh-views-counter-meta-field">
			<span class="dashicons dashicons-welcome-view-site" style="margin-right: 4px;"></span>
			<?php esc_html_e( 'Views Counter', 'wsh-views-counter' ); ?>:
			<input
				type="number"
				min="0"
				step="1"
				id="wsh_views_counter_manual_input"
				value="<?php echo esc_attr( $views ); ?>"
				style="width:60px; padding:2px; margin-right:4px; height: 10px !important; margin-bottom: 7px; border-top: 0px; border-left: 0px; border-right: 0px; text-align: center;"
			/>
			<a href="#" data-post-id="<?php echo (int) $post->ID; ?>" class="button button-small wsh-views-counter-save-manual">
				<?php esc_html_e( 'Save', 'wsh-views-counter' ); ?>
			</a>
			<span class="wsh-views-counter-status" style="margin-top:3px; margin-left: 10px; margin-bottom: 10px; display: inline-block; font-size:12px;"></span>
		</span>
		<?php
	}

	/**
	 * Register a meta box for displaying and manually editing the views count.
	 */
	public static function register_views_meta_box() {

		// Post types where we show the meta box.
		if ( class_exists( 'WSH_Views_Counter' ) ) {
			$post_types = self::get_supported_post_types();
		} else {
			$post_types = array( 'post', 'page', 'product' );
		}

		foreach ( $post_types as $pt ) {
			add_meta_box(
				'wsh_views_counter_meta_box',
				__( 'Views Counter', 'wsh-views-counter' ),
				array( __CLASS__, 'render_post_views_in_submitbox' ),
				$pt,
				'side',
				'default'
			);
		}
	}


	/**
	 * Enqueue admin scripts on post edit screens.
	 */
	public static function enqueue_admin_post_scripts( $hook ) {

		// Only load on post.php and post-new.php.
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		// Try to determine the current post type.
		global $typenow;

		if ( empty( $typenow ) ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && ! empty( $screen->post_type ) ) {
				$typenow = $screen->post_type;
			}
		}

		if ( empty( $typenow ) ) {
			return;
		}

		// Allowed post types configured in this plugin.
		if ( class_exists( 'WSH_Views_Counter' ) ) {
			$post_types = self::get_supported_post_types();
		} else {
			$post_types = array( 'post', 'page', 'product' );
		}

		if ( ! in_array( $typenow, $post_types, true ) ) {
			return;
		}

		// Enqueue JS.
		wp_enqueue_script(
			'wsh-views-counter-admin-post-meta',
			WSH_VIEWS_COUNTER_URL . '/assets/js/admin-post-meta.js',
			array( 'jquery' ),
			WSH_VIEWS_COUNTER_VERSION,
			true
		);

		wp_localize_script(
			'wsh-views-counter-admin-post-meta',
			'wshViewsCounterMeta',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wsh_views_counter_meta_ajax' ),
				'success'  => __( 'Views updated successfully.', 'wsh-views-counter' ),
				'error'    => __( 'Error while updating views.', 'wsh-views-counter' ),
			)
		);
	}


	/**
	 * Make the "Views" column sortable.
	 */
	public static function make_post_columns_sortable( $columns ) {
		$columns['wsh_views_counter'] = 'wsh_views_counter';
		return $columns;
	}

	/**
	 * Sorting by views count.
	 */
	public static function orderby_views_column( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'wsh_views_counter' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', 'wsh_views_count' );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Admin init – currently without heavy operations.
	 */
	// Handle dismissal of the PRO admin notice.
	public static function on_admin_init() {
		if ( isset( $_GET['wsh_vc_dismiss_pro_notice'] ) && '1' === $_GET['wsh_vc_dismiss_pro_notice'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( current_user_can( 'manage_options' ) ) {
				update_option( 'wsh_views_counter_pro_notice_dismissed', 1 );
			}
		}
	}

	/**
	 * Add admin menu items:
	 * - main item: "Views Counter"
	 * - subitems: "Reports" and "Settings".
	 */
	public static function add_menu_items() {

		$capability_reports  = 'edit_posts';
		$capability_settings = 'manage_options';
		$parent_slug         = 'wsh_views_counter_reports';

		// Main menu (opens Reports).
		add_menu_page(
			__( 'WSH Views Counter', 'wsh-views-counter' ),
			__( 'Views Counter', 'wsh-views-counter' ),
			$capability_reports,
			$parent_slug,
			array( __CLASS__, 'render_reports_page' ),
			'dashicons-welcome-view-site',
			181
		);

		// Submenu: Reports (same slug as parent).
		add_submenu_page(
			$parent_slug,
			__( 'Reports', 'wsh-views-counter' ),
			__( 'Reports', 'wsh-views-counter' ),
			$capability_reports,
			'wsh_views_counter_reports',
			array( __CLASS__, 'render_reports_page' )
		);

		// Submenu: Settings.
		add_submenu_page(
			$parent_slug,
			__( 'Settings', 'wsh-views-counter' ),
			__( 'Settings', 'wsh-views-counter' ),
			$capability_settings,
			'wsh_views_counter_settings',
			array( __CLASS__, 'render_settings_page' )
		);

		// New: Submenu – Upgrade to PRO.
		if ( ! wsh_views_counter_pro_is_active() ) {
			add_submenu_page(
				$parent_slug,
				__( 'Upgrade to PRO', 'wsh-views-counter' ),
				__( 'Upgrade to PRO', 'wsh-views-counter' ),
				$capability_settings,
				'wsh_views_counter_go_pro',
				array( __CLASS__, 'render_pro_upgrade_page' )
			);
		}
	}

	/**
	 * Reports page – table with data and filters.
	 */
	public static function render_reports_page() {

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'wsh-views-counter' ) );
		}

		// Create a list table instance.
		$wp_list_table = new WSH_Views_Counter_List_Table();
		$wp_list_table->prepare_items();
		?>
		<link rel="stylesheet" href="<?php echo esc_url( WSH_VIEWS_COUNTER_URL . '/assets/css/admin-style.css?v=' . WSH_VIEWS_COUNTER_VERSION ); ?>" />

		<div class="wrap">

			<div id="icon-users" class="icon32"><br /></div>
			<h2><?php esc_html_e( 'WSH Views Counter – Reports', 'wsh-views-counter' ); ?></h2>

			<?php if ( ! wsh_views_counter_pro_is_active() ) { ?>
			<div style="float:right; margin-top:-40px;">
				<div style="background:#fff; border:1px solid #ccd0d4; padding:8px 12px; border-radius:3px; text-align:right;">
					<strong><?php esc_html_e( 'Need geo, referrers & real-time?', 'wsh-views-counter' ); ?></strong><br>
					<span style="font-size:12px; color:#555;">
						<?php esc_html_e( 'Upgrade to PRO and unlock advanced analytics dashboards.', 'wsh-views-counter' ); ?>
					</span><br>
					<a href="<?php echo esc_url( 'https://websolutions.online/wshplugins/wsh-views-counter-pro' ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary button-small" style="margin-top:4px;">
						<?php esc_html_e( 'Upgrade to PRO', 'wsh-views-counter' ); ?>
					</a>
				</div>
			</div>
			<?php } ?>

			<form id="items-filter" method="get">
				<input type="hidden" name="page" value="wsh_views_counter_reports" />
				<?php $wp_list_table->display(); ?>
			</form>

		</div>
		<?php
	}

	/**
	 * Settings page – plugin configuration.
	 */
	public static function render_settings_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'wsh-views-counter' ) );
		}

		// Options.
		$exclude_admin_visits = get_option( 'wsh_views_counter-exclude-admin-visits' );
		$count_interval       = get_option( 'wsh_views_counter-count-interval' );
		$exclude_ips          = get_option( 'wsh_views_counter-exclude-ips' );
		$exclude_bots = (int) get_option( 'wsh_views_counter-exclude-bots', 0 );

		if ( empty( $count_interval ) ) {
			$count_interval = '1h';
		}
		?>
		<link rel="stylesheet" href="<?php echo esc_url( WSH_VIEWS_COUNTER_URL . '/assets/css/admin-style.css?v=' . WSH_VIEWS_COUNTER_VERSION ); ?>" />

		<div class="wrap">

			<div id="icon-users" class="icon32"><br /></div>
			<h2><?php esc_html_e( 'WSH Views Counter – Settings', 'wsh-views-counter' ); ?></h2>

			<?php if ( ! wsh_views_counter_pro_is_active() ) { ?>
			<div style="float:right; margin-top:-40px;">
				<div style="background:#fff; border:1px solid #ccd0d4; padding:8px 12px; border-radius:3px; text-align:right;">
					<strong><?php esc_html_e( 'Need geo, referrers & real-time?', 'wsh-views-counter' ); ?></strong><br>
					<span style="font-size:12px; color:#555;">
						<?php esc_html_e( 'Upgrade to PRO and unlock advanced analytics dashboards.', 'wsh-views-counter' ); ?>
					</span><br>
					<a href="<?php echo esc_url( 'https://websolutions.online/wshplugins/wsh-views-counter-pro' ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary button-small" style="margin-top:4px;">
						<?php esc_html_e( 'Upgrade to PRO', 'wsh-views-counter' ); ?>
					</a>
				</div>
			</div>
			<?php } ?>

			<div class="total-info">
				<!--<p id="wsh_views_counter-message"></p> -->

				<h2><?php esc_html_e( 'Default Options', 'wsh-views-counter' ); ?></h2>

				<label style="width: 300px; display: inline-block;"><?php esc_html_e( 'Count Interval:', 'wsh-views-counter' ); ?></label>
				<select name="wsh_views_counter-count-interval" id="wsh_views_counter-count-interval">
					<option value="30m" <?php selected( $count_interval, '30m' ); ?>>30 minutes</option>
					<option value="1h"  <?php selected( $count_interval, '1h' ); ?>>1 hour</option>
					<option value="3h"  <?php selected( $count_interval, '3h' ); ?>>3 hours</option>
					<option value="6h"  <?php selected( $count_interval, '6h' ); ?>>6 hours</option>
					<option value="12h" <?php selected( $count_interval, '12h' ); ?>>12 hours</option>
					<option value="1d"  <?php selected( $count_interval, '1d' ); ?>>1 day</option>
				</select>
				<br><br>

				<br><br>

				<p><?php esc_html_e( 'Post Types Count', 'wsh-views-counter' ); ?>:</p>

				<?php
				// Default post types.
				$default_post_types = array( 'post', 'page', 'product' );

				// Currently enabled post types (from options).
				$enabled_post_types = get_option( 'wsh_views_counter-post-types', $default_post_types );
				if ( ! is_array( $enabled_post_types ) ) {
					$enabled_post_types = array_filter( array_map( 'trim', explode( ',', (string) $enabled_post_types ) ) );
				}

				// All public post types.
				$all_post_types = get_post_types(
					array(
						'public' => true,
					),
					'objects'
				);
				?>

				<div style="margin-left: 0;">
					<?php foreach ( $all_post_types as $pt_slug => $pt_obj ) : ?>
						<label style="margin-right: 12px;">
							<input type="checkbox"
								   name="wsh_views_counter-post-types[]"
								   value="<?php echo esc_attr( $pt_slug ); ?>"
								   <?php checked( in_array( $pt_slug, $enabled_post_types, true ) ); ?>
							/>
							<?php echo esc_html( $pt_obj->labels->name ); ?>
						</label>
					<?php endforeach; ?>
				</div>
				<p class="description">
					<?php esc_html_e( 'Select post types for which views will be counted.', 'wsh-views-counter' ); ?>
				</p>

				<br><br>

				<hr />

				<h2><?php esc_html_e( 'Display Options', 'wsh-views-counter' ); ?></h2>

				<label style="width: 300px; display: inline-block;">
					<?php esc_html_e( 'Enable automatic display:', 'wsh-views-counter' ); ?>
				</label>
				<?php
				$display_enable = get_option( 'wsh_views_counter-display-enable', 0 );
				?>
				<input type="checkbox"
					value="1"
					name="wsh_views_counter-display-enable"
					id="wsh_views_counter-display-enable"
					<?php checked( (int) $display_enable, 1 ); ?>
				/>
				<br><br>

				<label style="width: 300px; display: inline-block;">
					<?php esc_html_e( 'Display position:', 'wsh-views-counter' ); ?>
				</label>
				<?php
				$display_position = get_option( 'wsh_views_counter-display-position', 'after' );
				?>
				<select name="wsh_views_counter-display-position" id="wsh_views_counter-display-position">
					<option value="before" <?php selected( $display_position, 'before' ); ?>>
						<?php esc_html_e( 'Before content', 'wsh-views-counter' ); ?>
					</option>
					<option value="after" <?php selected( $display_position, 'after' ); ?>>
						<?php esc_html_e( 'After content', 'wsh-views-counter' ); ?>
					</option>
				</select>
				<br><br>

				<label style="width: 300px; display: inline-block;">
					<?php esc_html_e( 'Views label:', 'wsh-views-counter' ); ?>
				</label>
				<?php
				$display_label = get_option( 'wsh_views_counter-display-label', 'Views:' );
				?>
				<input type="text"
					name="wsh_views_counter-display-label"
					id="wsh_views_counter-display-label"
					value="<?php echo esc_attr( $display_label ); ?>"
				/>
				<br><br>

				<label style="width: 300px; display: inline-block;">
					<?php esc_html_e( 'Format numbers (1,234):', 'wsh-views-counter' ); ?>
				</label>
				<?php
				$display_format_number = get_option( 'wsh_views_counter-display-format-number', 1 );
				?>
				<input type="checkbox"
					value="1"
					name="wsh_views_counter-display-format-number"
					id="wsh_views_counter-display-format-number"
					<?php checked( (int) $display_format_number, 1 ); ?>
				/>
				<br><br>

				<hr />

				<h2><?php esc_html_e( 'Visitor Filters', 'wsh-views-counter' ); ?></h2>

				<label style="width: 300px; display: inline-block;"><?php esc_html_e( 'Exclude Administrator Visits:', 'wsh-views-counter' ); ?></label>
				<input type="checkbox"
					   value="1"
					   name="wsh_views_counter-exclude-admin-visits"
					   id="wsh_views_counter-exclude-admin-visits"
					   <?php checked( (int) $exclude_admin_visits, 1 ); ?>
				/>
				<br><br>

				<label style="width: 300px; display: inline-block;">
					<?php esc_html_e( 'Exclude logged-in users:', 'wsh-views-counter' ); ?>
				</label>
				<?php
				$exclude_logged_in = (int) get_option( 'wsh_views_counter-exclude-logged-in', 0 );
				?>
				<input type="checkbox"
					value="1"
					name="wsh_views_counter-exclude-logged-in"
					id="wsh_views_counter-exclude-logged-in"
					<?php checked( $exclude_logged_in, 1 ); ?>
				/>
				<br><br>

				<label style="width: 300px; display: inline-block;">
					<?php esc_html_e( 'Exclude guests (not logged-in users):', 'wsh-views-counter' ); ?>
				</label>
				<?php
				$exclude_guests = (int) get_option( 'wsh_views_counter-exclude-guests', 0 );
				?>
				<input type="checkbox"
					value="1"
					name="wsh_views_counter-exclude-guests"
					id="wsh_views_counter-exclude-guests"
					<?php checked( $exclude_guests, 1 ); ?>
				/>
				<br><br>

				<label style="width: 300px; display: inline-block; vertical-align: top;">
					<?php esc_html_e( 'Exclude user roles:', 'wsh-views-counter' ); ?>
				</label>
				<?php
				$all_roles          = wp_roles()->roles;
				$excluded_roles_str = get_option( 'wsh_views_counter-exclude-roles', '' );
				$excluded_roles     = array_filter( array_map( 'trim', explode( ',', (string) $excluded_roles_str ) ) );
				?>
				<select name="wsh_views_counter-exclude-roles[]" id="wsh_views_counter-exclude-roles" multiple style="min-width: 250px; height: 120px;">
					<?php foreach ( $all_roles as $role_slug => $role_data ) : ?>
						<option value="<?php echo esc_attr( $role_slug ); ?>" <?php selected( in_array( $role_slug, $excluded_roles, true ) ); ?>>
							<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description" style="margin-left: 300px;">
					<?php esc_html_e( 'Hold Ctrl (Windows) or Cmd (Mac) to select multiple roles.', 'wsh-views-counter' ); ?>
				</p>

				<br><br>
			
				<label style="width: 300px; display: inline-block;"><?php esc_html_e( 'Exclude IPs (separated with ;):', 'wsh-views-counter' ); ?></label>
				<input type="text"
					   name="wsh_views_counter-exclude-ips"
					   id="wsh_views_counter-exclude-ips"
					   style="width: 50%" 
					   value="<?php echo esc_attr( $exclude_ips ); ?>"
				/>
				<br><br>

				<hr />
		
				<label style="width: 300px; display: inline-block;"><?php esc_html_e( 'Exclude bots/crawlers:', 'wsh-views-counter' ); ?></label>
				<input type="checkbox"
			       id="wsh_views_counter-exclude-bots"
			       name="wsh_views_counter-exclude-bots"
			       value="1"
			       <?php checked( 1, $exclude_bots ); ?> />
				<?php esc_html_e( 'Do not count visits from known bots and crawlers (Googlebot, Bingbot, Ahrefs, etc.)', 'wsh-views-counter' ); ?>
				<br><br>

				<hr />

				<h2><?php esc_html_e( 'Data Retention', 'wsh-views-counter' ); ?></h2>

				<label style="width: 300px; display: inline-block;">
					<?php esc_html_e( 'Automatically delete old daily view records:', 'wsh-views-counter' ); ?>
				</label>
				<?php
				$cleanup_period = get_option( 'wsh_views_counter-cleanup-period', '0' );
				?>
				<select name="wsh_views_counter-cleanup-period" id="wsh_views_counter-cleanup-period">
					<option value="0"   <?php selected( $cleanup_period, '0' ); ?>>
						<?php esc_html_e( 'Never (keep all data)', 'wsh-views-counter' ); ?>
					</option>
					<option value="30"  <?php selected( $cleanup_period, '30' ); ?>>
						<?php esc_html_e( 'Delete data older than 30 days', 'wsh-views-counter' ); ?>
					</option>
					<option value="90"  <?php selected( $cleanup_period, '90' ); ?>>
						<?php esc_html_e( 'Delete data older than 90 days', 'wsh-views-counter' ); ?>
					</option>
					<option value="180" <?php selected( $cleanup_period, '180' ); ?>>
						<?php esc_html_e( 'Delete data older than 6 months', 'wsh-views-counter' ); ?>
					</option>
					<option value="365" <?php selected( $cleanup_period, '365' ); ?>>
						<?php esc_html_e( 'Delete data older than 1 year', 'wsh-views-counter' ); ?>
					</option>
					<option value="730" <?php selected( $cleanup_period, '730' ); ?>>
						<?php esc_html_e( 'Delete data older than 2 years', 'wsh-views-counter' ); ?>
					</option>
				</select>
				<p class="description" style="margin-left: 300px;">
					<?php esc_html_e( 'This affects only the daily log table (wsh_views_counter). The total post view count remains intact.', 'wsh-views-counter' ); ?>
				</p>

				<br><br>
				<p id="wsh_views_counter-message"></p>

				<a href="javascript:void(0);"
				   class="wsh_views_counter-settings button button-primary button-large"
				   onclick="wsh_views_counter_save_settings();">
					<?php esc_html_e( 'Save', 'wsh-views-counter' ); ?>
				</a>
			</div>

			<!-- Import Start -->
			<?php
			// Check whether the "Post Views Counter" plugin table exists.
			global $wpdb;
			$pvc_table       = $wpdb->prefix . 'post_views';
			$pvc_table_exist = ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $pvc_table ) ) === $pvc_table );
			$import_done     = get_option( 'wsh_views_counter_pvc_import_done' );

			if ( $pvc_table_exist ) :
				?>
				<hr style="margin: 30px 0;">

				<h2><?php esc_html_e( 'Import data from Post Views Counter', 'wsh-views-counter' ); ?></h2>
				<p id="wsh_views_import-message"></p>

				<?php if ( $import_done ) : ?>
					<p>
						<?php esc_html_e( 'Import from Post Views Counter has already been completed.', 'wsh-views-counter' ); ?>
					</p>
					<p>
						<?php esc_html_e( 'If you really need to run the import again, you can manually reset the flag wsh_views_counter_pvc_import_done in the options table.', 'wsh-views-counter' ); ?>
					</p>
				<?php else : ?>
					<p>
						<?php esc_html_e( 'The plugin "Post Views Counter" table was detected (wp_post_views). You can import all its data into WSH Views Counter.', 'wsh-views-counter' ); ?>
					</p>
					<p>
						<?php esc_html_e( 'This will read all rows from wp_post_views and insert/merge them into the wsh_views_counter table and update the wsh_views_count post meta values.', 'wsh-views-counter' ); ?>
					</p>
					<button type="button"
							class="button button-secondary"
							onclick="wsh_views_counter_import_from_pvc();">
						<?php esc_html_e( 'Import all data', 'wsh-views-counter' ); ?>
					</button>
				<?php endif; ?>
			<?php endif; ?>
			<!-- Import End -->

			<hr />

			<!-- Delete start -->
			<h2><?php esc_html_e( 'Danger Zone', 'wsh-views-counter' ); ?></h2>
			<p>
				<?php esc_html_e( 'This will permanently delete all collected view logs (wsh_views_counter table) and all post meta related to view counts (wsh_views_count).', 'wsh-views-counter' ); ?>
				<br />
				<strong><?php esc_html_e( 'This action cannot be undone. Use with caution.', 'wsh-views-counter' ); ?></strong>
			</p>

			<p id="wsh_views_counter-delete-message"></p>

			<a href="javascript:void(0);"
			   class="button button-secondary button-large"
			   onclick="wsh_views_counter_delete_all_data();">
				<?php esc_html_e( 'Delete All Data', 'wsh-views-counter' ); ?>
			</a>
			<!-- Delete End -->


			<!-- Example start -->
			<hr style="margin: 30px 0;">

			<h2><?php esc_html_e( 'Developer usage: get most viewed posts', 'wsh-views-counter' ); ?></h2>

			<p>
				<?php esc_html_e( 'You can use the helper function wsh_views_counter_get_posts() to retrieve the IDs of your most viewed posts and then query them via WP_Query or get_posts().', 'wsh-views-counter' ); ?>
			</p>

			<ul style="list-style: disc; margin-left: 20px;">
				<li><code>$number_of_posts</code> – <?php esc_html_e( 'how many post IDs you want to get (e.g. 20)', 'wsh-views-counter' ); ?></li>
				<li><code>$post_type</code> – <?php esc_html_e( 'post type, e.g. \'post\', \'page\' or \'product\'', 'wsh-views-counter' ); ?></li>
				<li><code>$number_of_days</code> – <?php esc_html_e( 'how many days back to look for views (e.g. 7)', 'wsh-views-counter' ); ?></li>
				<li><code>$view_type</code> – <?php esc_html_e( 'optional: \'popular\' (default, based on total views in the selected period) or \'newest\' (newest posts with the highest number of views in the selected period)', 'wsh-views-counter' ); ?></li>
			</ul>

			<p><strong><?php esc_html_e( 'Example:', 'wsh-views-counter' ); ?></strong></p>

			<pre><code><?php echo esc_html(
			'$ids = wsh_views_counter_get_posts( 20, \'post\', 7 );

			$args = array(
				\'post__in\'       => $ids,
				\'orderby\'        => \'post__in\',
				\'posts_per_page\' => 20,
			);

			$posts = get_posts( $args );

			if ( $posts ) {
				foreach ( $posts as $p ) {
					echo esc_html( $p->ID ) . \': \' . esc_html( get_the_title( $p ) ) . \'<br>\';
				}
			}'
			); ?></code></pre>
			<!-- Example End -->

			<!-- Shortcode -->
			<hr style="margin: 30px 0;">
			<p><strong><?php esc_html_e( 'Shortcode usage:', 'wsh-views-counter' ); ?></strong></p>

			<p>
				<?php esc_html_e( 'You can display the view count anywhere using the [wsh_views] shortcode.', 'wsh-views-counter' ); ?>
			</p>

			<ul style="list-style: disc; margin-left: 20px;">
				<li><code>[wsh_views]</code> – <?php esc_html_e( 'prints views for the current post/page/product.', 'wsh-views-counter' ); ?></li>
				<li><code>[wsh_views label="Views:"]</code> – <?php esc_html_e( 'prints views for the current post with a custom label.', 'wsh-views-counter' ); ?></li>
				<li><code>[wsh_views id="123"]</code> – <?php esc_html_e( 'prints views for the given post ID.', 'wsh-views-counter' ); ?></li>
				<li><code>[wsh_views id="123" label="Reads:"]</code> – <?php esc_html_e( 'prints views for a specific post ID with a custom label.', 'wsh-views-counter' ); ?></li>
			</ul>

			<p>
				<?php esc_html_e( 'In PHP templates you can also use:', 'wsh-views-counter' ); ?>
			</p>

			<pre><code><?php echo esc_html(
			"echo do_shortcode( '[wsh_views]' );

			// Or with parameters:
			echo do_shortcode( '[wsh_views id=\"123\" label=\"Reads:\"]' );"
			); ?></code></pre>


			<!-- GO TO PRO -->
			<?php if ( ! wsh_views_counter_pro_is_active() ) { ?>
			<hr style="margin: 40px 0 20px;">

			<div style="background:#f8fafb; border:1px solid #dcdfe5; padding:15px 20px; border-radius:4px; max-width:900px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Go further with WSH Views Counter PRO', 'wsh-views-counter' ); ?></h2>
				<p>
					<?php esc_html_e( 'You are using the free version with core tracking and reports. PRO adds geo analytics, referrers, real-time, WooCommerce reports, exports and more – on top of the data you already collect.', 'wsh-views-counter' ); ?>
				</p>
				<ul style="margin-left:20px; list-style:disc;">
					<li><?php esc_html_e( 'See where your traffic comes from (countries, cities, referrers).', 'wsh-views-counter' ); ?></li>
					<li><?php esc_html_e( 'Monitor active visitors in real time.', 'wsh-views-counter' ); ?></li>
					<li><?php esc_html_e( 'Analyze WooCommerce products and funnels.', 'wsh-views-counter' ); ?></li>
					<li><?php esc_html_e( 'Export data to CSV / Excel / JSON.', 'wsh-views-counter' ); ?></li>
				</ul>
				<p style="margin-top:12px;">
					<a href="<?php echo esc_url( 'https://websolutions.online/wshplugins/wsh-views-counter-pro' ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
						<?php esc_html_e( 'View PRO features & pricing', 'wsh-views-counter' ); ?>
					</a>
					&nbsp;
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsh_views_counter_go_pro' ) ); ?>">
						<?php esc_html_e( 'Learn more inside WordPress', 'wsh-views-counter' ); ?>
					</a>
				</p>
			</div>

			<hr style="margin: 40px 0 20px;">

			<h2><?php esc_html_e( 'Free vs PRO', 'wsh-views-counter' ); ?></h2>
			<p>
				<?php esc_html_e( 'The free version gives you a solid, production-ready views tracking engine. PRO adds a full analytics suite on top of the same data.', 'wsh-views-counter' ); ?>
			</p>

			<table class="widefat striped" style="max-width: 800px; margin-top: 15px;">
				<thead>
					<tr>
						<th style="width: 40%;"><?php esc_html_e( 'Feature', 'wsh-views-counter' ); ?></th>
						<th style="width: 30%;"><?php esc_html_e( 'WSH Views Counter (Free)', 'wsh-views-counter' ); ?></th>
						<th style="width: 30%;"><?php esc_html_e( 'WSH Views Counter PRO', 'wsh-views-counter' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Core post views tracking (any post type)', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Yes', 'wsh-views-counter' ); ?></td>
						<td>✔ <?php esc_html_e( 'Yes', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'AJAX / JavaScript tracking (cache-friendly)', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Default method', 'wsh-views-counter' ); ?></td>
						<td>✔ <?php esc_html_e( 'Default method', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Daily stats table & dashboard widget', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Basic overview', 'wsh-views-counter' ); ?></td>
						<td>✔ <?php esc_html_e( 'Enhanced with additional panels', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Admin reports (by post, author, date)', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Standard filters', 'wsh-views-counter' ); ?></td>
						<td>✔ <?php esc_html_e( 'Advanced filters & AJAX tables', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Shortcodes & automatic view display', 'wsh-views-counter' ); ?></strong></td>
						<td>✔</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Geo analytics (countries / cities)', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Referrer analytics (domains, search, social)', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Real-time analytics (live visitors & hits)', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'WooCommerce product analytics & funnels', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Export views data (CSV / Excel / JSON)', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Popular & Trending posts widgets / blocks', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Viral detection & trending alerts', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Developer tools (REST API, JS events, webhooks)', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Anti-bot and anti-fraud filters', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top: 18px;">
				<strong><?php esc_html_e( 'Bottom line:', 'wsh-views-counter' ); ?></strong>
				<?php esc_html_e( 'The free version is perfect for counting views. PRO is for when views become a business-critical metric and you need real analytics, exports and automation.', 'wsh-views-counter' ); ?>
			</p>

			<p style="margin-top: 10px;">
				<a href="<?php echo esc_url( 'https://websolutions.online/wshplugins/wsh-views-counter-pro' ); ?>" class="button button-primary button-hero" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Upgrade to WSH Views Counter PRO', 'wsh-views-counter' ); ?>
				</a>
			</p>
			<?php } ?>

			<script type="text/javascript" src="<?php echo esc_url( WSH_VIEWS_COUNTER_URL . '/assets/js/admin-script.js?v=' . WSH_VIEWS_COUNTER_VERSION ); ?>"></script>

		</div>
		<?php
	}

	/**
	 * "Upgrade to PRO" page – marketing / feature overview.
	 */
	public static function render_pro_upgrade_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'wsh-views-counter' ) );
		}

		$pro_url = 'https://websolutions.online/wshplugins/wsh-views-counter-pro';
		?>
		<div class="wrap wsh-vc-go-pro">
			<h1><?php esc_html_e( 'WSH Views Counter PRO', 'wsh-views-counter' ); ?></h1>

			<p class="description" style="max-width: 800px;">
				<?php esc_html_e( 'Unlock a full analytics suite on top of the free WSH Views Counter tracking engine.', 'wsh-views-counter' ); ?>
			</p>

			<hr>

			<div style="display:flex; gap:30px; align-items:flex-start; max-width:1100px;">
				<div style="flex:2;">
					<h2><?php esc_html_e( 'What you get in PRO', 'wsh-views-counter' ); ?></h2>
					<ul style="list-style:disc; margin-left:20px;">
						<li><?php esc_html_e( 'Geo Analytics – views by country, city and region with an interactive world map.', 'wsh-views-counter' ); ?></li>
						<li><?php esc_html_e( 'Referrer Analytics – see which domains, search engines and social networks send you traffic.', 'wsh-views-counter' ); ?></li>
						<li><?php esc_html_e( 'Real-time Analytics – live dashboard of active visitors and last hits.', 'wsh-views-counter' ); ?></li>
						<li><?php esc_html_e( 'Advanced charts & heatmaps – daily and hourly traffic patterns, device breakdowns.', 'wsh-views-counter' ); ?></li>
						<li><?php esc_html_e( 'Author & taxonomy analytics – best authors, categories, tags and custom taxonomies.', 'wsh-views-counter' ); ?></li>
						<li><?php esc_html_e( 'WooCommerce analytics – product views, funnels (view → cart → checkout → order), trending products.', 'wsh-views-counter' ); ?></li>
						<li><?php esc_html_e( 'Export PRO – CSV, JSON and Excel exports with filters for large sites.', 'wsh-views-counter' ); ?></li>
						<li><?php esc_html_e( 'Popular & Trending Posts – widgets, blocks and shortcodes with a smart trending engine.', 'wsh-views-counter' ); ?></li>
						<li><?php esc_html_e( 'Viral detection & anti-bot protection – see what is going viral and filter bots / crawlers.', 'wsh-views-counter' ); ?></li>
					</ul>

					<p style="margin-top:20px;">
						<a href="<?php echo esc_url( $pro_url ); ?>" class="button button-primary button-hero" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Get WSH Views Counter PRO', 'wsh-views-counter' ); ?>
						</a>
						&nbsp;
						<a href="<?php echo esc_url( $pro_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'View full feature list & pricing', 'wsh-views-counter' ); ?>
						</a>
					</p>
				</div>

				<div style="flex:1; background:#fff; border:1px solid #ccd0d4; padding:15px 20px; border-radius:4px;">
					<h3><?php esc_html_e( 'Perfect for:', 'wsh-views-counter' ); ?></h3>
					<ul style="margin-left:18px; list-style:disc;">
						<li><?php esc_html_e( 'News & magazine websites', 'wsh-views-counter' ); ?></li>
						<li><?php esc_html_e( 'High-traffic blogs', 'wsh-views-counter' ); ?></li>
						<li><?php esc_html_e( 'WooCommerce stores', 'wsh-views-counter' ); ?></li>
						<li><?php esc_html_e( 'Agencies and client sites', 'wsh-views-counter' ); ?></li>
					</ul>

					<h3 style="margin-top:20px;"><?php esc_html_e( 'Works on top of this plugin', 'wsh-views-counter' ); ?></h3>
					<p>
						<?php esc_html_e( 'PRO uses the same tracking tables and data you already collect with the free version.', 'wsh-views-counter' ); ?>
					</p>
					<p>
						<?php esc_html_e( 'Install the PRO add-on, activate your license, and all additional analytics dashboards will appear in the admin menu.', 'wsh-views-counter' ); ?>
					</p>
				</div>
			</div>

			<hr style="margin: 30px 0;">

			<h2><?php esc_html_e( 'How does WSH Views Counter compare to other plugins?', 'wsh-views-counter' ); ?></h2>
			<p>
				<?php esc_html_e( 'Here is a direct feature comparison so you can choose the best analytics solution for your site.', 'wsh-views-counter' ); ?>
			</p>

			<table class="widefat striped" style="max-width: 1000px; margin-top: 15px;">
				<thead>
					<tr>
						<th style="width: 28%;"><?php esc_html_e( 'Feature', 'wsh-views-counter' ); ?></th>
						<th style="width: 24%;"><?php esc_html_e( 'WSH Views Counter PRO', 'wsh-views-counter' ); ?></th>
						<th style="width: 24%;"><?php esc_html_e( 'Post Views Counter', 'wsh-views-counter' ); ?></th>
						<th style="width: 24%;"><?php esc_html_e( 'MonsterInsights', 'wsh-views-counter' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'AJAX / JavaScript tracking (cache-friendly)', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Enabled by default. Fully automatic and optimized for caching plugins.', 'wsh-views-counter' ); ?></td>
						<td>⚠ <?php esc_html_e( 'JavaScript mode available, but must be manually selected. Fast AJAX is PRO-only.', 'wsh-views-counter' ); ?></td>
						<td>⚠ <?php esc_html_e( 'Uses Google Analytics scripts instead of a lightweight native AJAX tracker.', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Lightweight post views tracking', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Native table, optimized for views only', 'wsh-views-counter' ); ?></td>
						<td>✔ <?php esc_html_e( 'Post views only', 'wsh-views-counter' ); ?></td>
						<td>⚠ <?php esc_html_e( 'Google Analytics focused', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Real-time analytics (active visitors, live hits)', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Built-in real-time panel', 'wsh-views-counter' ); ?></td>
						<td>✖</td>
						<td>⚠ <?php esc_html_e( 'Limited, depends on GA view', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Geo analytics (country / city)', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Dedicated Geo Analytics module', 'wsh-views-counter' ); ?></td>
						<td>✖</td>
						<td>⚠ <?php esc_html_e( 'Requires Google Analytics geo', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Referrer analytics (domains, search, social)', 'wsh-views-counter' ); ?></strong></td>
						<td>✔</td>
						<td>✖</td>
						<td>⚠ <?php esc_html_e( 'Via GA reports, not per-post views table', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'WooCommerce product analytics & funnels', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Views → cart → checkout → order', 'wsh-views-counter' ); ?></td>
						<td>✖</td>
						<td>⚠ <?php esc_html_e( 'General eCommerce stats, not per-view funnels', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Trending & viral detection engine', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Detects sudden spikes in views', 'wsh-views-counter' ); ?></td>
						<td>✖</td>
						<td>✖</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Heatmaps (views by hour / day)', 'wsh-views-counter' ); ?></strong></td>
						<td>✔</td>
						<td>✖</td>
						<td>✖</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Export views data (CSV / Excel / JSON)', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Export PRO module', 'wsh-views-counter' ); ?></td>
						<td>✖</td>
						<td>⚠ <?php esc_html_e( 'Exports via GA, not raw views table', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Popular / Trending posts widgets & blocks', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Widgets, shortcodes, Gutenberg blocks', 'wsh-views-counter' ); ?></td>
						<td>⚠ <?php esc_html_e( 'Basic display only', 'wsh-views-counter' ); ?></td>
						<td>⚠ <?php esc_html_e( 'Depends on GA events / goals', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'REST API & JS event hooks', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Designed for developers', 'wsh-views-counter' ); ?></td>
						<td>✖</td>
						<td>⚠ <?php esc_html_e( 'Mostly GA-based, not per-view', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Anti-bot & anti-fraud filters', 'wsh-views-counter' ); ?></strong></td>
						<td>✔</td>
						<td>✖</td>
						<td>⚠ <?php esc_html_e( 'Relies on GA filters', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Designed for high-traffic sites (100k+ views/day)', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Custom tables & background processing', 'wsh-views-counter' ); ?></td>
						<td>⚠ <?php esc_html_e( 'OK for smaller sites', 'wsh-views-counter' ); ?></td>
						<td>⚠ <?php esc_html_e( 'Depends on GA limits', 'wsh-views-counter' ); ?></td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top: 8px; background:#f5faff; padding:10px 14px; border-left:4px solid #0073aa;">
				<strong><?php esc_html_e( 'Did you know?', 'wsh-views-counter' ); ?></strong>
				<?php esc_html_e( 'WSH Views Counter uses JavaScript/AJAX tracking automatically — no setup required. This ensures accurate counting even on heavily cached pages, something many plugins struggle with.', 'wsh-views-counter' ); ?>
			</p>
			<p style="margin-top: 18px;">
				<strong><?php esc_html_e( 'Summary:', 'wsh-views-counter' ); ?></strong>
				<?php esc_html_e( 'Post Views Counter is a good basic counter, MonsterInsights is great for Google Analytics – but WSH Views Counter PRO is built specifically for fast, deep per-post view analytics inside WordPress.', 'wsh-views-counter' ); ?>
			</p>

			<hr style="margin: 40px 0 20px;">

			<h2><?php esc_html_e( 'Free vs PRO', 'wsh-views-counter' ); ?></h2>
			<p>
				<?php esc_html_e( 'The free version gives you a solid, production-ready views tracking engine. PRO adds a full analytics suite on top of the same data.', 'wsh-views-counter' ); ?>
			</p>

			<table class="widefat striped" style="max-width: 800px; margin-top: 15px;">
				<thead>
					<tr>
						<th style="width: 40%;"><?php esc_html_e( 'Feature', 'wsh-views-counter' ); ?></th>
						<th style="width: 30%;"><?php esc_html_e( 'WSH Views Counter (Free)', 'wsh-views-counter' ); ?></th>
						<th style="width: 30%;"><?php esc_html_e( 'WSH Views Counter PRO', 'wsh-views-counter' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Core post views tracking (any post type)', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Yes', 'wsh-views-counter' ); ?></td>
						<td>✔ <?php esc_html_e( 'Yes', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'AJAX / JavaScript tracking (cache-friendly)', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Default method', 'wsh-views-counter' ); ?></td>
						<td>✔ <?php esc_html_e( 'Default method', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Daily stats table & dashboard widget', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Basic overview', 'wsh-views-counter' ); ?></td>
						<td>✔ <?php esc_html_e( 'Enhanced with additional panels', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Admin reports (by post, author, date)', 'wsh-views-counter' ); ?></strong></td>
						<td>✔ <?php esc_html_e( 'Standard filters', 'wsh-views-counter' ); ?></td>
						<td>✔ <?php esc_html_e( 'Advanced filters & AJAX tables', 'wsh-views-counter' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Shortcodes & automatic view display', 'wsh-views-counter' ); ?></strong></td>
						<td>✔</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Geo analytics (countries / cities)', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Referrer analytics (domains, search, social)', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Real-time analytics (live visitors & hits)', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'WooCommerce product analytics & funnels', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Export views data (CSV / Excel / JSON)', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Popular & Trending posts widgets / blocks', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Viral detection & trending alerts', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Developer tools (REST API, JS events, webhooks)', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Anti-bot and anti-fraud filters', 'wsh-views-counter' ); ?></strong></td>
						<td>✖</td>
						<td>✔</td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top: 18px;">
				<strong><?php esc_html_e( 'Bottom line:', 'wsh-views-counter' ); ?></strong>
				<?php esc_html_e( 'The free version is perfect for counting views. PRO is for when views become a business-critical metric and you need real analytics, exports and automation.', 'wsh-views-counter' ); ?>
			</p>

			<p style="margin-top: 10px;">
				<a href="<?php echo esc_url( 'https://websolutions.online/wshplugins/wsh-views-counter-pro' ); ?>" class="button button-primary button-hero" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Upgrade to WSH Views Counter PRO', 'wsh-views-counter' ); ?>
				</a>
			</p>


		</div>
		<?php
	}

	/**
	 * Return the number of views for a given post.
	 */
	public static function get_post_views( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return 0;
		}

		// 1) Read from post meta.
		$views = get_post_meta( $post_id, 'wsh_views_count', true );
		$views = (int) $views;

		// 2) If it is 0, try to calculate it from the table (imported / legacy data).
		if ( $views <= 0 ) {
			global $wpdb;

			$table_name = $wpdb->prefix . 'wsh_views_counter';

			$sum = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(view_count) FROM {$table_name} WHERE post_id = %d",
					$post_id
				)
			);

			if ( $sum > 0 ) {
				$views = $sum;
				// 3) Store the value in post meta so future reads use meta
				//    and sorting by the column in admin remains fast.
				update_post_meta( $post_id, 'wsh_views_count', $views );
			}
		}

		return max( 0, $views );
	}


	/**
	 * Format the views number (with or without thousands separator).
	 */
	public static function format_views_number( $views ) {
		$views = (int) $views;

		$format = get_option( 'wsh_views_counter-display-format-number', 1 );
		if ( (int) $format === 1 ) {
			return number_format_i18n( $views );
		}

		return (string) $views;
	}

	/**
	 * Generate HTML that displays the views count for a post.
	 */
	public static function get_views_html( $post_id ) {
		$views = self::get_post_views( $post_id );
		$views = self::format_views_number( $views );

		$label = get_option( 'wsh_views_counter-display-label', 'Views:' );
		$label = trim( $label );

		if ( '' !== $label ) {
			$label = esc_html( $label ) . ' ';
		}

		$html  = '<span class="wsh-views-counter" style="display: inline-block; margin: 10px 0px;">';
		$html .= '<span class="wsh-views-counter-label">' . $label . '</span>';
		$html .= '<span class="wsh-views-counter-count">' . esc_html( $views ) . '</span>';
		$html .= '</span>';

		return $html;
	}

	/**
	 * Shortcode [wsh_views id=""].
	 *
	 * - if no id is provided, use the global $post.
	 */
	public static function shortcode_views( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'     => 0,
				'label'  => '',   // allow overriding the label
				'format' => '',   // reserved for PRO version ('compact', 'short', ...)
			),
			$atts,
			'wsh_views'
		);

		$post_id = (int) $atts['id'];
		if ( $post_id <= 0 ) {
			$post = get_post();
			if ( ! $post ) {
				return '';
			}
			$post_id = $post->ID;
		}

		// Temporarily override the label if it is provided.
		if ( '' !== $atts['label'] ) {
			$orig_label = get_option( 'wsh_views_counter-display-label', 'Views:' );
			update_option( 'wsh_views_counter-display-label-tmp', $orig_label );
			update_option( 'wsh_views_counter-display-label', $atts['label'] );
		}

		$html = self::get_views_html( $post_id );

		// Restore the original label if we overrode it.
		if ( '' !== $atts['label'] ) {
			$orig_label = get_option( 'wsh_views_counter-display-label-tmp', 'Views:' );
			update_option( 'wsh_views_counter-display-label', $orig_label );
			delete_option( 'wsh_views_counter-display-label-tmp' );
		}

		return $html;
	}

	/**
	 * Automatically append the views HTML to the content (before/after content).
	 */
	public static function maybe_append_views_to_content( $content ) {
		// Run only on the frontend.
		if ( is_admin() ) {
			return $content;
		}

		// Must be a single view (post/page/product) and the main query.
		$supported = self::get_supported_post_types();
		if ( ! is_singular( $supported ) ) {
			return $content;
		}

		global $post;
		if ( ! in_array( $post->post_type, $supported, true ) ) {
			return $content;
		}

		$enabled = (int) get_option( 'wsh_views_counter-display-enable', 0 );
		if ( 1 !== $enabled ) {
			return $content;
		}

		$position = get_option( 'wsh_views_counter-display-position', 'after' );
		$html     = self::get_views_html( $post->ID );

		if ( 'before' === $position ) {
			return $html . "\n" . $content;
		}

		// Default: after content.
		return $content . "\n" . $html;
	}


	/**
	 * Frontend JS – define a global variable and enqueue the script.
	 */
	public static function frontend_js_bootstrap() {

		$count_interval = get_option( 'wsh_views_counter-count-interval' );
		if ( empty( $count_interval ) ) {
			$count_interval = '1h';
		}

		wp_enqueue_script(
			'wsh-views-counter-cvc-js',
			WSH_VIEWS_COUNTER_URL . '/assets/js/cvc.js?v=' . WSH_VIEWS_COUNTER_VERSION,
			array( 'jquery' ),
			WSH_VIEWS_COUNTER_VERSION,
			true
		);

		// Prosleđujemo podatke u JS (uključujući post_id).
		wp_localize_script(
			'wsh-views-counter-cvc-js',
			'wshViewsCounter',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'count_interval' => $count_interval,
				'post_id'        => is_singular() ? get_the_ID() : 0,
			)
		);
	}

		/**
	 * Internal helper: get / create session key for funnel tracking.
	 *
	 * We try to reuse existing cookies if present, otherwise generate one.
	 */
	protected static function get_session_key() {
		$candidates = array();

		if ( isset( $_COOKIE['wsh_vc_session'] ) ) {
			$candidates[] = (string) $_COOKIE['wsh_vc_session'];
		}
		if ( isset( $_COOKIE['wsh_views_session'] ) ) {
			$candidates[] = (string) $_COOKIE['wsh_views_session'];
		}

		foreach ( $candidates as $key ) {
			$key = trim( $key );
			if ( '' !== $key && preg_match( '/^[a-f0-9]{16,64}$/', $key ) ) {
				return $key;
			}
		}

		// Generate new session key.
		$key = wp_generate_password( 32, false, false );

		if ( ! headers_sent() ) {
			$path = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
			setcookie( 'wsh_vc_session', $key, time() + 30 * DAY_IN_SECONDS, $path );
		}

		$_COOKIE['wsh_vc_session'] = $key;

		return $key;
	}

	/**
	 * Internal helper: log WooCommerce funnel event.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $event_type add_to_cart|order|checkout...
	 * @param int    $qty        Quantity.
	 * @param int    $order_id   Optional order ID.
	 */
	protected static function log_woo_event( $product_id, $event_type, $qty = 1, $order_id = 0 ) {
		global $wpdb;

		$product_id = (int) $product_id;
		if ( $product_id <= 0 ) {
			return;
		}

		$event_type = trim( (string) $event_type );
		if ( '' === $event_type ) {
			return;
		}

		$qty      = max( 1, (int) $qty );
		$order_id = max( 0, (int) $order_id );

		$session_key = self::get_session_key();
		if ( '' === $session_key ) {
			return;
		}

		$table_woo = $wpdb->prefix . 'wsh_views_woo_funnel';

		$wpdb->insert(
			$table_woo,
			array(
				'session_key' => $session_key,
				'product_id'  => $product_id,
				'event_type'  => $event_type,
				'event_time'  => current_time( 'mysql' ),
				'order_id'    => $order_id,
				'qty'         => $qty,
			),
			array(
				'%s',
				'%d',
				'%s',
				'%s',
				'%d',
				'%d',
			)
		);
	}

	/**
	 * WooCommerce hook: log add to cart event.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id    Product ID.
	 * @param int    $quantity      Quantity.
	 * @param int    $variation_id  Variation ID.
	 * @param array  $variation     Variation data.
	 * @param array  $cart_item_data Cart item data.
	 */
	public static function on_woocommerce_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		$product_id = (int) $product_id;
		if ( $product_id <= 0 ) {
			return;
		}

		$qty = (int) $quantity;
		if ( $qty <= 0 ) {
			$qty = 1;
		}

		self::log_woo_event( $product_id, 'add_to_cart', $qty, 0 );
	}

	/**
	 * WooCommerce hook: log order events per product.
	 *
	 * Fired when checkout is processed.
	 *
	 * @param int   $order_id    Order ID.
	 * @param array $posted_data Posted checkout data.
	 * @param mixed $order       WC_Order instance (or null on some versions).
	 */
	public static function on_woocommerce_checkout_order_processed( $order_id, $posted_data, $order ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order_id = (int) $order_id;
		if ( $order_id <= 0 ) {
			return;
		}

		if ( ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$items = $order->get_items();
		if ( empty( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
				continue;
			}

			$product_id = (int) $item->get_product_id();
			$qty        = (int) $item->get_quantity();

			if ( $product_id <= 0 ) {
				continue;
			}

			if ( $qty <= 0 ) {
				$qty = 1;
			}

			self::log_woo_event( $product_id, 'order', $qty, $order_id );
		}
	}


	private static function get_settings( $refresh = 'no' ) {}

	public static function register_settings() {}

	public static function enqueue_block_editor_scripts() {}

	/**
	 * Set defaults on activation (called from the global function).
	 */
	public static function activate() {
		register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall' ) );
	}

	/**
	 * Delete the options on uninstall.
	 */
	public static function uninstall() {
		// Here we can delete options/tables if needed on uninstall.
	}
}

endif;

// Bootstrap the plugin.
add_action( 'plugins_loaded', array( 'WSH_Views_Counter', 'init_actions' ) );

/**
 * Activate the plugin.
 */
function wsh_views_counter_activate() {

	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	/*
	 * 1) Main daily views table
	 *    - agregacija po postu i danu
	 */
	$table_main = $wpdb->prefix . 'wsh_views_counter';

	$sql_main = "CREATE TABLE $table_main (
	  ID bigint(20) UNSIGNED NOT NULL auto_increment,
	  post_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
	  post_type varchar(20) NOT NULL DEFAULT 'post',
	  view_date date NOT NULL DEFAULT '0000-00-00',
	  view_count bigint(20) UNSIGNED NOT NULL DEFAULT 0,
	  is_mobile bigint(20) UNSIGNED NOT NULL DEFAULT 0,
	  is_desktop bigint(20) UNSIGNED NOT NULL DEFAULT 0,
	  is_logged bigint(20) UNSIGNED NOT NULL DEFAULT 0,
	  created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	  PRIMARY KEY  (ID),
	  KEY post_id (post_id),
	  KEY post_id_view_date (post_id, view_date),
	  KEY view_date (view_date),
	  KEY post_type (post_type)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_main );

	/*
	 * 2) Geo analytics table
	 *    - agregacija po postu, datumu i geo lokaciji (zemlja + grad)
	 */
	$table_geo = $wpdb->prefix . 'wsh_views_counter_geo';

	$sql_geo = "CREATE TABLE $table_geo (
	  ID bigint(20) UNSIGNED NOT NULL auto_increment,
	  post_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
	  post_type varchar(20) NOT NULL DEFAULT 'post',
	  view_date date NOT NULL DEFAULT '0000-00-00',
	  country_code varchar(5) NOT NULL DEFAULT '',
	  country_name varchar(100) NOT NULL DEFAULT '',
	  city varchar(100) NOT NULL DEFAULT '',
	  view_count int(11) NOT NULL DEFAULT 0,
	  PRIMARY KEY  (ID),
	  KEY post_id (post_id),
	  KEY view_date (view_date),
	  KEY post_date (post_id, view_date),
	  KEY date_country (view_date, country_code),
	  KEY date_city (view_date, city)
	) $charset_collate;";

	dbDelta( $sql_geo );

	/*
	 * 3) Referrer analytics table
	 *    - agregacija po postu, datumu, tipu izvora i domenu
	 */
	$table_ref = $wpdb->prefix . 'wsh_views_counter_ref';

	$sql_ref = "CREATE TABLE $table_ref (
	  ID bigint(20) UNSIGNED NOT NULL auto_increment,
	  post_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
	  post_type varchar(20) NOT NULL DEFAULT 'post',
	  view_date date NOT NULL DEFAULT '0000-00-00',
	  ref_type varchar(20) NOT NULL DEFAULT '',
	  ref_domain varchar(191) NOT NULL DEFAULT '',
	  view_count int(11) NOT NULL DEFAULT 0,
	  PRIMARY KEY  (ID),
	  KEY post_id (post_id),
	  KEY view_date (view_date),
	  KEY post_date (post_id, view_date),
	  KEY date_type (view_date, ref_type),
	  KEY date_domain (view_date, ref_domain)
	) $charset_collate;";

	dbDelta( $sql_ref );

	/*
	 * 4) Sessions table
	 *    - po korisničkoj sesiji: landing/exit stranice, trajanje itd.
	 */
	$table_sessions = $wpdb->prefix . 'wsh_views_sessions';

	$sql_sessions = "CREATE TABLE $table_sessions (
	  ID bigint(20) UNSIGNED NOT NULL auto_increment,
	  session_key varchar(64) NOT NULL,
	  first_seen datetime NOT NULL,
	  last_seen datetime NOT NULL,
	  first_post_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
	  last_post_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
	  hit_count int(11) NOT NULL DEFAULT 0,
	  country_code varchar(5) NOT NULL DEFAULT '',
	  PRIMARY KEY  (ID),
	  UNIQUE KEY session_key (session_key),
	  KEY first_post_id (first_post_id),
	  KEY last_post_id (last_post_id),
	  KEY last_seen (last_seen)
	) $charset_collate;";

	dbDelta( $sql_sessions );

	/*
	 * 5) Live hits (real-time) table
	 *    - poslednji hitovi, aktivni korisnici, live feed
	 */
	$table_live = $wpdb->prefix . 'wsh_views_live_hits';

	$sql_live = "CREATE TABLE $table_live (
	  ID bigint(20) UNSIGNED NOT NULL auto_increment,
	  session_key varchar(64) NOT NULL,
	  post_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
	  post_type varchar(20) NOT NULL DEFAULT 'post',
	  view_time datetime NOT NULL,
	  country_code varchar(5) NOT NULL DEFAULT '',
	  ref_type varchar(20) NOT NULL DEFAULT '',
	  ref_domain varchar(191) NOT NULL DEFAULT '',
	  is_mobile tinyint(1) NOT NULL DEFAULT 0,
	  is_logged tinyint(1) NOT NULL DEFAULT 0,
	  PRIMARY KEY  (ID),
	  KEY view_time (view_time),
	  KEY session_key (session_key),
	  KEY post_id (post_id)
	) $charset_collate;";

	dbDelta( $sql_live );
	
	/*
	 * 6) WooCommerce funnel events
	 *    - add to cart / order events per product
	 */
	$table_woo = $wpdb->prefix . 'wsh_views_woo_funnel';

	$sql_woo = "CREATE TABLE $table_woo (
	  ID bigint(20) UNSIGNED NOT NULL auto_increment,
	  session_key varchar(64) NOT NULL,
	  product_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
	  event_type varchar(20) NOT NULL DEFAULT '',
	  event_time datetime NOT NULL,
	  order_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
	  qty int(11) NOT NULL DEFAULT 0,
	  PRIMARY KEY  (ID),
	  KEY product_id (product_id),
	  KEY event_time (event_time),
	  KEY event_type (event_type),
	  KEY session_key (session_key),
	  KEY order_id (order_id)
	) $charset_collate;";

	dbDelta( $sql_woo );

	// Pozovi dodatni activate kod ako postoji klasa.
	if ( class_exists( 'WSH_Views_Counter' ) ) {
		WSH_Views_Counter::activate();
	}

	// Schedule the daily cleanup event (if not already scheduled).
	if ( ! wp_next_scheduled( 'wsh_views_counter_cleanup_event' ) ) {
		wp_schedule_event( time() + 3600, 'daily', 'wsh_views_counter_cleanup_event' );
	}
}
register_activation_hook( __FILE__, 'wsh_views_counter_activate' );

/**
 * Cron callback – cleans old records from the wsh_views_counter table
 * based on the "Data Retention" setting.
 */
function wsh_views_counter_cleanup_cron() {
	global $wpdb;

	$cleanup_period = get_option( 'wsh_views_counter-cleanup-period', '0' );
	$days           = (int) $cleanup_period;

	if ( $days <= 0 ) {
		// 0 = never delete.
		return;
	}

	$table_name = $wpdb->prefix . 'wsh_views_counter';

	// Date X days ago.
	$threshold_date = gmdate( 'Y-m-d', strtotime( '-' . $days . ' days' ) );

	// Delete all rows where view_date is older than the threshold date.
	// (use prepare for safety)
	$sql = $wpdb->prepare(
		"DELETE FROM {$table_name} WHERE view_date < %s",
		$threshold_date
	);

	$wpdb->query( $sql );
}
add_action( 'wsh_views_counter_cleanup_event', 'wsh_views_counter_cleanup_cron' );


/**
 * Custom function to get most popular posts.
 */
if ( ! function_exists( 'wsh_views_counter_get_posts' ) ) :
	function wsh_views_counter_get_posts( $num_of_posts = 10, $post_type = 'post', $num_of_days = 7, $view_type = 'popular' ) {
		global $wpdb;

		$ids    = array();
		$posts  = array();

		$num_of_posts = intval( $num_of_posts );
		if ( empty( $num_of_posts ) ) {
			$num_of_posts = 10;
		}

		$num_of_days = intval( $num_of_days );
		if ( empty( $num_of_days ) ) {
			$num_of_days = 7;
		}

		//Post types
		$default = array( 'post', 'page', 'product' );
		$stored = get_option( 'wsh_views_counter-post-types', $default );

		if ( is_array( $stored ) ) {
			$types = array_map( 'sanitize_key', $stored );
		} elseif ( is_string( $stored ) && '' !== $stored ) {
			$types = array_map( 'sanitize_key', array_map( 'trim', explode( ',', $stored ) ) );
		} else {
			$types = $default;
		}

		if ( ! in_array( $post_type, $types, true ) ) {
			$post_type = 'post';
		}

		$view_type = (string) $view_type;
		if ( empty( $view_type ) || ! in_array( $view_type, array( 'popular', 'newest' ), true ) ) {
			$view_type = 'popular';
		}

		$end_date   = date( 'Y-m-d', strtotime( '+1 day' ) );
		$start_date = date( 'Y-m-d', strtotime( '-' . $num_of_days . ' day' ) );

		if ( 'newest' === $view_type ) {
			$sql = $wpdb->prepare(
				"SELECT wp.ID as post_id, wm.meta_value as counter
				 FROM {$wpdb->posts} wp
				 JOIN {$wpdb->postmeta} wm ON wp.ID = wm.post_id
				 WHERE wm.meta_key = 'wsh_views_count'
				   AND wp.post_type = %s
				   AND (DATE(wp.post_date) BETWEEN %s AND %s)
				 ORDER BY (wm.meta_value * 1) DESC
				 LIMIT %d",
				$post_type,
				$start_date,
				$end_date,
				$num_of_posts
			);
		} else {
			$table_name = $wpdb->prefix . 'wsh_views_counter';

			$sql = $wpdb->prepare(
				"SELECT post_id, SUM(view_count) as counter
				 FROM $table_name
				 WHERE post_type = %s
				   AND (view_date BETWEEN %s AND %s)
				 GROUP BY post_id
				 ORDER BY counter DESC
				 LIMIT %d",
				$post_type,
				$start_date,
				$end_date,
				$num_of_posts
			);
		}

		$results = $wpdb->get_results( $sql );

		if ( ! empty( $results ) ) {
			foreach ( $results as $res ) {
				$ids[] = (int) $res->post_id;
			}
		}

		return $ids;
	}
endif;


/**
 * Custom function to check is PRO plugin is active
 */
if ( ! function_exists( 'wsh_views_counter_pro_is_active' ) ) {
	/**
	 * Check if the PRO add-on is installed AND license is active.
	 */
	function wsh_views_counter_pro_is_active() {
		if ( ! class_exists( 'WSH_VC_Pro_License' ) ) {
			return false;
		}

		if ( ! method_exists( 'WSH_VC_Pro_License', 'is_active' ) ) {
			return false;
		}

		return (bool) WSH_VC_Pro_License::is_active();
	}
}