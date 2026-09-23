<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PRO+ module: Multi-Post Compare Analytics.
 *
 * - Up to 5 posts/products compared on one chart
 * - Daily views per post in selected period
 * - Comparison table per day
 */
class WSH_VC_Pro_Compare {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );

		// AJAX pretraga postova za Multi-Post Compare.
		add_action( 'wp_ajax_wsh_vc_pro_compare_search_posts', array( __CLASS__, 'ajax_search_posts' ) );
	}

	/**
	 * Register submenu under PRO menu.
	 */
	public static function register_menu() {

		$parent_slug = 'wsh_views_counter_pro';
		$capability  = 'manage_options';

		add_submenu_page(
			$parent_slug,
			__( 'Multi-Post Compare', 'wsh-views-counter-pro' ),
			__( 'Multi-Post Compare', 'wsh-views-counter-pro' ),
			$capability,
			'wsh-vc-pro-compare',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render page.
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

		$views_table = $wpdb->prefix . 'wsh_views_counter';

		// Safety: make sure table exists.
		$like        = $wpdb->esc_like( $views_table );
		$found_table = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$like
			)
		);

		if ( $found_table !== $views_table ) {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Views table (wsh_views_counter) not found. Please make sure the free WSH Views Counter plugin is active and its tables are created.', 'wsh-views-counter-pro' ) .
				'</p></div>';
			return;
		}

		// ===== Date range filter (default last 30 days) =====.
		$today        = current_time( 'Y-m-d' );
		$default_from = date( 'Y-m-d', strtotime( '-30 days', current_time( 'timestamp' ) ) );

		$from = isset( $_GET['wsh_cmp_from'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_cmp_from'] ) ) : $default_from; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$to   = isset( $_GET['wsh_cmp_to'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_cmp_to'] ) ) : $today;           // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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

		// ===== Post IDs input (comma separated) =====.
		$post_ids_raw = isset( $_GET['wsh_cmp_posts'] ) ? sanitize_text_field( wp_unslash( $_GET['wsh_cmp_posts'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$post_ids = array();
		if ( '' !== $post_ids_raw ) {
			$parts = array_map( 'trim', explode( ',', $post_ids_raw ) );
			foreach ( $parts as $part ) {
				$id = (int) $part;
				if ( $id > 0 ) {
					$post_ids[] = $id;
				}
			}
		}

		// Hard limit: max 5 posts to keep charts readable.
		$post_ids = array_slice( array_values( array_unique( $post_ids ) ), 0, 5 );

		// For UX, build "clean" value for the hidden field.
		$post_ids_field = '';
		if ( ! empty( $post_ids ) ) {
			$post_ids_field = implode( ', ', $post_ids );
		}

		// Lista selektovanih postova (za JS pre-populate).
		$selected_posts_js = array();
		if ( ! empty( $post_ids ) ) {
			foreach ( $post_ids as $pid ) {
				$title = get_the_title( $pid );
				if ( '' === $title ) {
					$title = sprintf( __( 'Post #%d', 'wsh-views-counter-pro' ), $pid );
				}
				$selected_posts_js[] = array(
					'id'    => $pid,
					'title' => $title,
				);
			}
		}

		// Data containers for chart/table.
		$date_labels  = array();
		$series       = array();
		$chart_series = array();
		$has_data     = false;

		// Build date range array (list of all dates from->to inclusive).
		$dates = array();
		$ts    = strtotime( $from );
		$end   = strtotime( $to );

		if ( $ts && $end && $ts <= $end ) {
			while ( $ts <= $end ) {
				$dates[] = date( 'Y-m-d', $ts );
				$ts     += DAY_IN_SECONDS;
			}
		}

		$date_labels = $dates;

		if ( ! empty( $post_ids ) && ! empty( $date_labels ) ) {

			// Query views per post per date.
			$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

			$params   = $post_ids;
			$params[] = $from;
			$params[] = $to;

			$sql = "
				SELECT post_id, view_date, SUM(view_count) AS views
				FROM {$views_table}
				WHERE post_id IN ($placeholders)
				  AND view_date BETWEEN %s AND %s
				GROUP BY post_id, view_date
				ORDER BY view_date ASC
			";

			$rows = $wpdb->get_results(
				$wpdb->prepare( $sql, $params ),
				ARRAY_A
			);

			if ( ! empty( $rows ) ) {
				$has_data = true;

				// Prepare empty series for each post.
				foreach ( $post_ids as $pid ) {
					$title = get_the_title( $pid );
					if ( '' === $title ) {
						$title = sprintf( __( 'Post #%d', 'wsh-views-counter-pro' ), $pid );
					}
					$series[ $pid ] = array(
						'title' => $title,
						'data'  => array(),
					);
				}

				// Fill actual values from DB.
				foreach ( $rows as $row ) {
					$pid  = (int) $row['post_id'];
					$date = $row['view_date'];
					$cnt  = (int) $row['views'];

					if ( isset( $series[ $pid ] ) && in_array( $date, $date_labels, true ) ) {
						$series[ $pid ]['data'][ $date ] = $cnt;
					}
				}

				// For each post, ensure every date in range exists (fill with 0).
				foreach ( $series as $pid => $info ) {
					$data_points = array();

					foreach ( $date_labels as $d ) {
						$data_points[] = isset( $info['data'][ $d ] ) ? (int) $info['data'][ $d ] : 0;
					}

					$chart_series[] = array(
						'id'    => $pid,
						'label' => $info['title'],
						'data'  => $data_points,
					);
				}
			}
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Multi-Post Compare (PRO+)', 'wsh-views-counter-pro' ); ?></h1>

			<form method="get" style="margin-bottom: 15px;">
				<input type="hidden" name="page" value="wsh-vc-pro-compare" />
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Date range', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<label>
								<?php esc_html_e( 'From:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_cmp_from" value="<?php echo esc_attr( $from ); ?>" />
							</label>
							&nbsp;&nbsp;
							<label>
								<?php esc_html_e( 'To:', 'wsh-views-counter-pro' ); ?>
								<input type="date" name="wsh_cmp_to" value="<?php echo esc_attr( $to ); ?>" />
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Posts to compare', 'wsh-views-counter-pro' ); ?>
						</th>
						<td>
							<input type="text"
								   id="wsh_cmp_search"
								   class="regular-text"
								   placeholder="<?php echo esc_attr_x( 'Start typing post/product title…', 'search placeholder', 'wsh-views-counter-pro' ); ?>" />
							<p class="description" style="margin-top:4px;">
								<?php esc_html_e( 'Type at least 3 characters, then click on a result to add it. Maximum 5 posts.', 'wsh-views-counter-pro' ); ?>
							</p>

							<div id="wsh-cmp-suggestions" class="wsh-cmp-suggestions" style="margin-top:5px;"></div>

							<div id="wsh-cmp-selected" class="wsh-cmp-selected" style="margin-top:8px;"></div>

							<input type="hidden"
								   id="wsh_cmp_posts_field"
								   name="wsh_cmp_posts"
								   value="<?php echo esc_attr( $post_ids_field ); ?>" />

							<p class="description" style="margin-top:4px;">
								<?php esc_html_e( 'Selected IDs are stored in this field. You can also manually edit the list if needed.', 'wsh-views-counter-pro' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">&nbsp;</th>
						<td>
							<button class="button button-primary">
								<?php esc_html_e( 'Compare', 'wsh-views-counter-pro' ); ?>
							</button>
						</td>
					</tr>
					</tbody>
				</table>
			</form>

			<script type="text/javascript">
			(function($) {
				var ajaxUrl      = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
				var selectedData = <?php echo wp_json_encode( $selected_posts_js ); ?> || [];

				var $search      = $('#wsh_cmp_search');
				var $suggestions = $('#wsh-cmp-suggestions');
				var $selected    = $('#wsh-cmp-selected');
				var $hidden      = $('#wsh_cmp_posts_field');

				function syncHiddenField() {
					var ids = [];
					for (var i = 0; i < selectedData.length; i++) {
						ids.push(selectedData[i].id);
					}
					$hidden.val(ids.join(', '));
				}

				function renderSelected() {
					$selected.empty();

					if (!selectedData.length) {
						$selected.append(
							$('<span/>', {
								text: '<?php echo esc_js( __( 'No posts selected yet.', 'wsh-views-counter-pro' ) ); ?>',
								css: { fontStyle: 'italic', color: '#666' }
							})
						);
						return;
					}

					for (var i = 0; i < selectedData.length; i++) {
						(function(item) {
							var $tag = $('<span/>', {
								'class': 'wsh-cmp-tag',
								css: {
									display: 'inline-flex',
									alignItems: 'center',
									padding: '2px 6px',
									margin: '0 6px 6px 0',
									background: '#f0f0f0',
									borderRadius: '3px',
									fontSize: '11px'
								}
							});

							$tag.append(
								$('<span/>', {
									text: item.title + ' (ID: ' + item.id + ')'
								})
							);

							$tag.append(
								$('<a/>', {
									href: '#',
									text: '×',
									css: {
										marginLeft: '6px',
										color: '#a00',
										textDecoration: 'none',
										fontWeight: 'bold'
									},
									click: function(e) {
										e.preventDefault();
										removeSelected(item.id);
									}
								})
							);

							$selected.append($tag);
						})(selectedData[i]);
					}
				}

				function removeSelected(id) {
					var newList = [];
					for (var i = 0; i < selectedData.length; i++) {
						if (selectedData[i].id !== id) {
							newList.push(selectedData[i]);
						}
					}
					selectedData = newList;
					syncHiddenField();
					renderSelected();
				}

				function addSelected(id, title) {
					// Već postoji?
					for (var i = 0; i < selectedData.length; i++) {
						if (selectedData[i].id === id) {
							return;
						}
					}

					if (selectedData.length >= 5) {
						alert('<?php echo esc_js( __( 'You can compare a maximum of 5 posts.', 'wsh-views-counter-pro' ) ); ?>');
						return;
					}

					selectedData.push({
						id: id,
						title: title
					});

					syncHiddenField();
					renderSelected();
				}

				// Pre-populate na osnovu PHP podataka.
				renderSelected();
				syncHiddenField();

				var searchTimer = null;

				$search.on('keyup', function() {
					var term = $.trim($search.val());

					if (searchTimer) {
						clearTimeout(searchTimer);
					}

					if (term.length < 3) {
						$suggestions.empty();
						return;
					}

					searchTimer = setTimeout(function() {
						$suggestions.empty().text('<?php echo esc_js( __( 'Searching…', 'wsh-views-counter-pro' ) ); ?>');

						$.get(
							ajaxUrl,
							{
								action: 'wsh_vc_pro_compare_search_posts',
								q: term
							},
							function(response) {
								$suggestions.empty();

								if (!response || !response.success || !response.data || !response.data.length) {
									$suggestions.text('<?php echo esc_js( __( 'No matching posts found.', 'wsh-views-counter-pro' ) ); ?>');
									return;
								}

								var $list = $('<ul/>', {
									css: {
										margin: 0,
										paddingLeft: '18px',
										maxHeight: '180px',
										overflowY: 'auto'
									}
								});

								for (var i = 0; i < response.data.length; i++) {
									(function(item) {
										var $li = $('<li/>', {
											css: { cursor: 'pointer' }
										});

										$li.text(item.text);
										$li.on('click', function() {
											// item.text je "Title (ID: 123)" – izvući title bez ID dela za tag.
											var titleOnly = item.text.replace(/\s*\(ID:\s*\d+\)\s*$/, '');
											addSelected(parseInt(item.id, 10), titleOnly);

											$suggestions.empty();
											$search.val('');
										});

										$list.append($li);
									})(response.data[i]);
								}

								$suggestions.append($list);
							}
						);
					}, 300); // debounce
				});
			})(jQuery);
			</script>

			<?php
			if ( empty( $post_ids ) ) {
				echo '<p>' . esc_html__( 'Select at least two posts/products to see the comparison chart.', 'wsh-views-counter-pro' ) . '</p>';
				echo '</div>';
				return;
			}

			if ( ! $has_data ) {
				echo '<p>' . esc_html__( 'No view data found for the selected posts and period.', 'wsh-views-counter-pro' ) . '</p>';
				echo '</div>';
				return;
			}
			?>

			<h2><?php esc_html_e( 'Views Comparison Chart', 'wsh-views-counter-pro' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Daily views per post for the selected period. Each post is shown as a separate line.', 'wsh-views-counter-pro' ); ?>
			</p>

			<canvas id="wsh-vc-pro-compare-chart" height="220" style="width:100%; max-width:100%; border:1px solid #ddd; background:#fff;"></canvas>

			<h2 style="margin-top:30px;"><?php esc_html_e( 'Daily Views Table', 'wsh-views-counter-pro' ); ?></h2>
			<?php
			echo self::render_comparison_table( $date_labels, $series ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>

			<p style="margin-top:15px; text-align:center; font-size:11px; color:#777;">
				<?php esc_html_e( 'Multi-Post Compare uses aggregated daily logs from the wsh_views_counter table.', 'wsh-views-counter-pro' ); ?>
			</p>
		</div>

		<script type="text/javascript">
		(function() {
			var canvas = document.getElementById('wsh-vc-pro-compare-chart');
			if (!canvas || !canvas.getContext) {
				return;
			}

			var labels = <?php echo wp_json_encode( $date_labels ); ?>;
			var series = <?php echo wp_json_encode( $chart_series ); ?>;

			if (!labels.length || !series.length) {
				return;
			}

			var ctx   = canvas.getContext('2d');
			var dpr   = window.devicePixelRatio || 1;
			var rect  = canvas.getBoundingClientRect();
			var cssHeight = 260;

			canvas.width  = rect.width * dpr;
			canvas.height = cssHeight * dpr;
			ctx.scale(dpr, dpr);

			var width  = rect.width;
			var height = cssHeight;

			var padding = { top: 20, right: 20, bottom: 40, left: 50 };
			var innerWidth  = width  - padding.left - padding.right;
			var innerHeight = height - padding.top  - padding.bottom;

			// Color palette for up to 5 lines.
			var colors = [
				'#2271b1',
				'#d63638',
				'#46b450',
				'#826eb4',
				'#ffb900'
			];

			// Compute global max.
			var maxVal = 0;
			for (var s = 0; s < series.length; s++) {
				var data = series[s].data || [];
				for (var i = 0; i < data.length; i++) {
					if (data[i] > maxVal) {
						maxVal = data[i];
					}
				}
			}
			if (maxVal === 0) {
				maxVal = 1;
			}

			function niceNumber(x) {
				var exp = Math.floor(Math.log10(x));
				var f   = x / Math.pow(10, exp);
				var nf;
				if      (f <= 1) nf = 1;
				else if (f <= 2) nf = 2;
				else if (f <= 5) nf = 5;
				else             nf = 10;
				return nf * Math.pow(10, exp);
			}
			var niceMax   = niceNumber(maxVal);
			var ySteps    = 4;
			var yStepSize = niceMax / ySteps;

			var stepX = (labels.length > 1) ? innerWidth / (labels.length - 1) : 0;

			function getX(i) {
				return padding.left + stepX * i;
			}
			function getY(v) {
				return padding.top + innerHeight - (v / niceMax) * innerHeight;
			}

			// Background
			ctx.clearRect(0, 0, width, height);
			ctx.fillStyle = '#ffffff';
			ctx.fillRect(0, 0, width, height);

			// Grid + Y axis labels.
			ctx.strokeStyle  = '#e5e5e5';
			ctx.fillStyle    = '#666';
			ctx.lineWidth    = 1;
			ctx.font         = '11px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
			ctx.textAlign    = 'right';
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

			// X axis line.
			var baseY = getY(0);
			ctx.strokeStyle = '#cccccc';
			ctx.beginPath();
			ctx.moveTo(padding.left, baseY);
			ctx.lineTo(width - padding.right, baseY);
			ctx.stroke();

			// X labels – max 7 ravnomerno raspoređenih.
			ctx.fillStyle    = '#666';
			ctx.textAlign    = 'center';
			ctx.textBaseline = 'top';

			var maxXLabels = 7;
			var stepLabel  = Math.max(1, Math.floor(labels.length / maxXLabels));

			for (var li = 0; li < labels.length; li += stepLabel) {
				var x = getX(li);
				var y = baseY + 4;
				ctx.fillText(labels[li], x, y);
			}

			// Draw each series.
			for (var sIdx = 0; sIdx < series.length; sIdx++) {
				var serie = series[sIdx];
				var data  = serie.data || [];

				var color = colors[sIdx % colors.length];

				// Area (light fill).
				ctx.beginPath();
				for (var j = 0; j < data.length; j++) {
					var x = getX(j);
					var y = getY(data[j]);
					if (j === 0) {
						ctx.moveTo(x, baseY);
						ctx.lineTo(x, y);
					} else {
						ctx.lineTo(x, y);
					}
				}
				var lastX = getX(data.length - 1);
				ctx.lineTo(lastX, baseY);
				ctx.closePath();
				ctx.fillStyle = color + '22'; // very light.
				ctx.fill();

				// Line.
				ctx.beginPath();
				ctx.strokeStyle = color;
				ctx.lineWidth   = 2;
				for (var k = 0; k < data.length; k++) {
					var lx = getX(k);
					var ly = getY(data[k]);
					if (k === 0) {
						ctx.moveTo(lx, ly);
					} else {
						ctx.lineTo(lx, ly);
					}
				}
				ctx.stroke();

				// Points.
				ctx.fillStyle = color;
				for (var m = 0; m < data.length; m++) {
					var px = getX(m);
					var py = getY(data[m]);
					ctx.beginPath();
					ctx.arc(px, py, 3, 0, Math.PI * 2, true);
					ctx.fill();
				}
			}

			// Simple legend (top-left).
			ctx.font         = '11px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
			ctx.textAlign    = 'left';
			ctx.textBaseline = 'middle';

			var legendX = padding.left;
			var legendY = padding.top - 10;
			if (legendY < 10) {
				legendY = 10;
			}

			for (var ls = 0; ls < series.length; ls++) {
				var c = colors[ls % colors.length];

				ctx.fillStyle = c;
				ctx.fillRect(legendX, legendY + ls * 16, 10, 10);

				ctx.fillStyle = '#222';
				ctx.fillText(series[ls].label, legendX + 14, legendY + ls * 16 + 5);
			}
		})();
		</script>
		<?php
	}

	/**
	 * AJAX: search posts/products by title.
	 * Returns JSON: [ { id: 123, text: "Post title (ID: 123)" }, ... ].
	 */
	public static function ajax_search_posts() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden.' ), 403 );
		}

		$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array() );
		}

		// Koje post type-ove tražimo: koristimo podešavanja iz free plugina ako postoje.
		$post_types = array( 'post', 'page', 'product' );
		if ( class_exists( 'WSH_Views_Counter' ) && method_exists( 'WSH_Views_Counter', 'get_supported_post_types' ) ) {
			$post_types = WSH_Views_Counter::get_supported_post_types();
		}

		$args = array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			's'              => $term,
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query   = new WP_Query( $args );
		$results = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$title = $post->post_title;
				if ( '' === $title ) {
					$title = sprintf( __( 'Post #%d', 'wsh-views-counter-pro' ), $post->ID );
				}

				$results[] = array(
					'id'   => (int) $post->ID,
					'text' => $title . ' (ID: ' . (int) $post->ID . ')',
				);
			}
		}

		wp_send_json_success( $results );
	}

	/**
	 * Render daily comparison table.
	 *
	 * @param array $date_labels List of dates (Y-m-d).
	 * @param array $series      Post series data.
	 * @return string
	 */
	protected static function render_comparison_table( $date_labels, $series ) {

		if ( empty( $date_labels ) || empty( $series ) ) {
			return '';
		}

		ob_start();
		?>
		<div style="max-width:100%; overflow:auto;">
		<table class="widefat striped" style="font-size:11px; min-width:650px;">
			<thead>
				<tr>
					<th style="width:110px;"><?php esc_html_e( 'Date', 'wsh-views-counter-pro' ); ?></th>
					<?php foreach ( $series as $pid => $info ) : ?>
						<th style="text-align:right;"><?php echo esc_html( $info['title'] ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $date_labels as $date ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $date ); ?></strong></td>
						<?php foreach ( $series as $pid => $info ) : ?>
							<?php
							$views = 0;
							if ( isset( $info['data'][ $date ] ) ) {
								$views = (int) $info['data'][ $date ];
							}
							?>
							<td style="text-align:right;"><?php echo esc_html( number_format_i18n( $views ) ); ?></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<?php

		return ob_get_clean();
	}
}
