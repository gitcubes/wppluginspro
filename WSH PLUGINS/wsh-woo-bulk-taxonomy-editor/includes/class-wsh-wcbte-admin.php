<?php
if (! defined('ABSPATH')) exit;

class WSH_WCBTE_Admin {

	const NONCE_ACTION = 'wsh_wcbte_nonce_action';
	const NONCE_NAME   = 'wsh_wcbte_nonce';

	// FREE: samo product_cat
	public static function allowed_taxonomies() {

		$allowed = array('product_cat');

		// PRO unlock
		if ( class_exists('WSH_WCBTE_License') && WSH_WCBTE_License::is_active() ) {

			$tax_objects = get_object_taxonomies('product', 'objects');

			foreach ($tax_objects as $tax => $obj) {
				if ( empty($obj->public) && empty($obj->show_ui) ) continue;
				$allowed[] = $tax;
			}

			$allowed = array_values(array_unique($allowed));
		}

		return $allowed;
	}

	private static function is_pro() : bool {
		return ( class_exists('WSH_WCBTE_License') && WSH_WCBTE_License::is_active() );
	}

	public static function init() {
		add_filter('bulk_actions-edit-product', array(__CLASS__, 'add_bulk_action'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
		add_action('admin_footer-edit.php', array(__CLASS__, 'render_modal'));

		add_action('wp_ajax_wsh_wcbte_search_terms', array(__CLASS__, 'ajax_search_terms'));
		add_action('wp_ajax_wsh_wcbte_apply', array(__CLASS__, 'ajax_apply'));
		add_action('wp_ajax_wsh_wcbte_preview', array(__CLASS__, 'ajax_preview'));
		add_action('wp_ajax_wsh_wcbte_tax_ui', array(__CLASS__, 'ajax_tax_ui'));
	}

	public static function add_bulk_action($actions) {
		$actions['wsh_wcbte_bulk_taxonomy_edit'] = __('WSH: Bulk Taxonomy Edit', 'wsh-wcbte');
		return $actions;
	}

	public static function enqueue_assets($hook) {
		if ($hook !== 'edit.php') return;

		$screen = get_current_screen();
		if (! $screen || $screen->id !== 'edit-product') return;

		wp_enqueue_script(
			'wsh-wcbte-admin',
			WSH_WCBTE_URL . 'assets/admin.js?v=' . time(),
			array('jquery'),
			WSH_WCBTE_VERSION,
			true
		);

		wp_enqueue_style(
			'wsh-wcbte-admin',
			WSH_WCBTE_URL . 'assets/admin.css?v=' . time(),
			array(),
			WSH_WCBTE_VERSION
		);

		wp_localize_script('wsh-wcbte-admin', 'WSH_WCBTE', array(
			'ajaxUrl'  => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce(self::NONCE_ACTION),
			'allowed'  => self::allowed_taxonomies(),
			'isPro'    => self::is_pro(),
			'chunk'    => 50,
			'i18n'     => array(
				'noSelection' => __('Select at least one product.', 'wsh-wcbte'),
				'working'     => __('Working...', 'wsh-wcbte'),
				'done'        => __('Done.', 'wsh-wcbte'),
				'error'       => __('Error:', 'wsh-wcbte'),
			),
		));
	}

	public static function render_modal() {
		$screen = get_current_screen();
		if (! $screen || $screen->id !== 'edit-product') return;

		if (! current_user_can('edit_products') && ! current_user_can('manage_woocommerce')) {
			return;
		}

		$is_pro   = self::is_pro();
		$allowed  = self::allowed_taxonomies();
		$tax_objs = get_object_taxonomies('product', 'objects');

		$options = array();
		foreach ($allowed as $t) {
			$obj = isset($tax_objs[$t]) ? $tax_objs[$t] : get_taxonomy($t);
			if ( ! $obj ) continue;
			$label = !empty($obj->labels->singular_name) ? $obj->labels->singular_name : $t;
			$options[$t] = $label;
		}

		$default_tax = 'product_cat';
		if ( ! isset($options[$default_tax]) ) {
			$default_tax = key($options);
		}
		?>
		<div id="wsh-wcbte-backdrop" style="display:none;"></div>

		<div id="wsh-wcbte-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="wsh-wcbte-title">
			<div class="wsh-wcbte-modal__header">
				<h2 id="wsh-wcbte-title">WSH WooCommerce Bulk Taxonomy Editor</h2>
				<button type="button" class="button wsh-wcbte-close" aria-label="<?php esc_attr_e('Close', 'wsh-wcbte'); ?>">×</button>
			</div>

			<div class="wsh-wcbte-modal__body">
				<p class="wsh-wcbte-muted">
					<?php esc_html_e('Edit taxonomy terms for selected products.', 'wsh-wcbte'); ?>
				</p>

				<div class="wsh-wcbte-row" style="align-items:center;">
					<div class="wsh-wcbte-col">
						<label for="wsh-wcbte-taxonomy"><strong><?php esc_html_e('Select taxonomy', 'wsh-wcbte'); ?></strong></label>
						<select id="wsh-wcbte-taxonomy" style="min-width:320px;">
							<?php foreach ($options as $tax => $label) : ?>
								<option value="<?php echo esc_attr($tax); ?>" <?php selected($tax, $default_tax); ?>>
									<?php echo esc_html($label); ?>
								</option>
							<?php endforeach; ?>
						</select>

						<?php if ( ! $is_pro ) : ?>
							<p class="description" style="margin-top:6px;">
								<?php esc_html_e('FREE version supports product categories only.', 'wsh-wcbte'); ?>
							</p>
						<?php endif; ?>
					</div>

					<div class="wsh-wcbte-col wsh-wcbte-col--sm">
						<label><strong><?php esc_html_e('Mode', 'wsh-wcbte'); ?></strong></label>
						<div class="wsh-wcbte-radio">
							<label><input type="radio" name="wsh_wcbte_mode" value="add" checked> <?php esc_html_e('Add (keep old + add new)', 'wsh-wcbte'); ?></label><br>
							<label><input type="radio" name="wsh_wcbte_mode" value="replace"> <?php esc_html_e('Replace (remove old, set new)', 'wsh-wcbte'); ?></label><br>
							<?php if ($is_pro) : ?>
								<label><input type="radio" name="wsh_wcbte_mode" value="remove"> <?php esc_html_e('Remove (remove selected)', 'wsh-wcbte'); ?></label>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<div id="wsh-wcbte-taxonomy-ui-wrap" style="margin-top:12px;">
					<!-- AJAX will render UI for selected taxonomy here -->
				</div>

				<?php if ( ! $is_pro ) : ?>
					<hr>
					<div class="wsh-wcbte-locked">
						<div class="wsh-wcbte-locked__badge">PRO</div>
						<h3><?php esc_html_e('More taxonomies in PRO', 'wsh-wcbte'); ?></h3>
						<ul>
							<li><?php esc_html_e('Product tags (product_tag)', 'wsh-wcbte'); ?></li>
							<li><?php esc_html_e('Brands (custom taxonomy)', 'wsh-wcbte'); ?></li>
							<li><?php esc_html_e('Attributes (pa_*)', 'wsh-wcbte'); ?></li>
							<li><?php esc_html_e('Any custom product taxonomies', 'wsh-wcbte'); ?></li>
						</ul>
						<p class="wsh-wcbte-muted"><?php esc_html_e('Upgrade to PRO to unlock these.', 'wsh-wcbte'); ?></p>
					</div>
				<?php endif; ?>

				<div class="wsh-wcbte-preview" style="display:none;">
					<h3 style="margin-top:14px;"><?php esc_html_e('Preview (Dry Run)', 'wsh-wcbte'); ?></h3>
					<p class="wsh-wcbte-muted"><?php esc_html_e('Shows how terms will look after applying changes (no data is saved).', 'wsh-wcbte'); ?></p>
					<div class="wsh-wcbte-preview__tablewrap">
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e('Product', 'wsh-wcbte'); ?></th>
									<th id="wsh-wcbte-preview-current-label"><?php esc_html_e('Current', 'wsh-wcbte'); ?></th>
									<th id="wsh-wcbte-preview-final-label"><?php esc_html_e('Final', 'wsh-wcbte'); ?></th>
								</tr>
							</thead>
							<tbody id="wsh-wcbte-preview-body"></tbody>
						</table>
					</div>
				</div>

				<div class="wsh-wcbte-status" style="display:none;">
					<div class="wsh-wcbte-progress">
						<div class="wsh-wcbte-progress__bar" style="width:0%"></div>
					</div>
					<pre class="wsh-wcbte-log" aria-live="polite"></pre>
				</div>
			</div>

			<div class="wsh-wcbte-modal__footer">
				<div class="wsh-wcbte-left">
					<span class="wsh-wcbte-selected">
						<?php esc_html_e('Selected products:', 'wsh-wcbte'); ?> <strong id="wsh-wcbte-selected-count">0</strong>
					</span>
				</div>
				<div class="wsh-wcbte-right">
					<button type="button" class="button button-secondary wsh-wcbte-close"><?php esc_html_e('Cancel', 'wsh-wcbte'); ?></button>
					<button type="button" class="button" id="wsh-wcbte-preview"><?php esc_html_e('Preview', 'wsh-wcbte'); ?></button>
					<button type="button" class="button button-primary" id="wsh-wcbte-apply"><?php esc_html_e('Apply Changes', 'wsh-wcbte'); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	private static function must_allow_tax($tax) {
		return in_array($tax, self::allowed_taxonomies(), true);
	}

	private static function check_access_or_die() {
		if (! (current_user_can('edit_products') || current_user_can('manage_woocommerce'))) {
			wp_send_json_error(array('message' => 'Forbidden'), 403);
		}
		check_ajax_referer(self::NONCE_ACTION, 'nonce');
	}

	private static function sanitize_mode_or_error(string $mode) : string {
		$mode = sanitize_key($mode);
		$allowed = array('add','replace');
		if ( self::is_pro() ) $allowed[] = 'remove';

		if ( ! in_array($mode, $allowed, true) ) {
			wp_send_json_error(array('message' => 'Invalid mode'), 400);
		}
		return $mode;
	}

	public static function ajax_search_terms() {
		self::check_access_or_die();

		$tax = isset($_GET['taxonomy']) ? sanitize_key($_GET['taxonomy']) : '';
		if (! self::must_allow_tax($tax)) {
			wp_send_json_error(array('message' => 'Taxonomy not allowed.'), 400);
		}

		$q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';

		$terms = get_terms(array(
			'taxonomy'   => $tax,
			'hide_empty' => false,
			'number'     => 20,
			'search'     => $q,
		));

		if (is_wp_error($terms)) {
			wp_send_json_error(array('message' => $terms->get_error_message()), 500);
		}

		$out = array();
		foreach ($terms as $t) {
			$out[] = array(
				'id'   => (int) $t->term_id,
				'text' => $t->name,
			);
		}

		wp_send_json_success(array('results' => $out));
	}

	public static function ajax_apply() {
		self::check_access_or_die();

		$tax = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : '';
		if (! self::must_allow_tax($tax)) {
			wp_send_json_error(array('message' => 'Taxonomy not allowed.'), 400);
		}

		$mode = isset($_POST['mode']) ? (string) $_POST['mode'] : 'add';
		$mode = self::sanitize_mode_or_error($mode);

		$product_ids = isset($_POST['product_ids']) ? (array) $_POST['product_ids'] : array();
		$product_ids = array_values(array_filter(array_map('absint', $product_ids)));

		$term_ids = isset($_POST['term_ids']) ? (array) $_POST['term_ids'] : array();
		$term_ids = array_values(array_filter(array_map('absint', $term_ids)));

		$offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
		$chunk  = isset($_POST['chunk']) ? absint($_POST['chunk']) : 50;
		if ($chunk < 1) $chunk = 50;
		if ($chunk > 200) $chunk = 200;

		$total = count($product_ids);
		if ($total === 0) {
			wp_send_json_error(array('message' => 'No products selected'), 400);
		}

		// Remove mode requires term selection
		if ( $mode === 'remove' && empty($term_ids) ) {
			wp_send_json_error(array('message' => 'Select at least one term to remove.'), 400);
		}

		$batch = array_slice($product_ids, $offset, $chunk);
		$updated = 0;
		$errors = array();

		foreach ($batch as $pid) {
			if (get_post_type($pid) !== 'product') continue;

			$current = wp_get_object_terms($pid, $tax, array('fields' => 'ids'));
			if (is_wp_error($current)) {
				$errors[] = "ID {$pid}: " . $current->get_error_message();
				continue;
			}

			$current = array_values(array_map('absint', (array) $current));

			if ($mode === 'replace') {
				$final = $term_ids; // može biti prazno => clear all
			} elseif ($mode === 'remove') {
				$final = array_values(array_diff($current, $term_ids));
			} else { // add
				$final = array_values(array_unique(array_merge($current, $term_ids)));
			}

			$res = wp_set_object_terms($pid, $final, $tax, false);
			if (is_wp_error($res)) {
				$errors[] = "ID {$pid}: " . $res->get_error_message();
				continue;
			}

			$updated++;

			if (function_exists('wc_delete_product_transients')) {
				wc_delete_product_transients($pid);
			}
		}

		$new_offset = $offset + count($batch);
		$done = ($new_offset >= $total);

		wp_send_json_success(array(
			'total'      => $total,
			'offset'     => $new_offset,
			'processed'  => count($batch),
			'updated'    => $updated,
			'done'       => $done,
			'errors'     => $errors,
		));
	}

	public static function ajax_preview() {
		self::check_access_or_die();

		$tax = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : '';
		if (! self::must_allow_tax($tax)) {
			wp_send_json_error(array('message' => 'Taxonomy not allowed.'), 400);
		}

		$mode = isset($_POST['mode']) ? (string) $_POST['mode'] : 'add';
		$mode = self::sanitize_mode_or_error($mode);

		$product_ids = isset($_POST['product_ids']) ? (array) $_POST['product_ids'] : array();
		$product_ids = array_values(array_filter(array_map('absint', $product_ids)));

		$term_ids = isset($_POST['term_ids']) ? (array) $_POST['term_ids'] : array();
		$term_ids = array_values(array_filter(array_map('absint', $term_ids)));

		if (empty($product_ids)) {
			wp_send_json_error(array('message' => 'No products selected'), 400);
		}

		// Remove mode requires term selection
		if ( $mode === 'remove' && empty($term_ids) ) {
			wp_send_json_error(array('message' => 'Select at least one term to remove.'), 400);
		}

		$limit = isset($_POST['limit']) ? absint($_POST['limit']) : 50;
		if ($limit < 1) $limit = 50;
		if ($limit > 200) $limit = 200;

		// Helper: map term_id => name
		$get_names = function(array $ids) use ($tax) : array {
			$ids = array_values(array_filter(array_map('absint', $ids)));
			if (empty($ids)) return array();

			$terms = get_terms(array(
				'taxonomy'   => $tax,
				'hide_empty' => false,
				'include'    => $ids,
			));

			$map = array();
			if (!is_wp_error($terms)) {
				foreach ($terms as $t) {
					$map[(int)$t->term_id] = $t->name;
				}
			}
			return $map;
		};

		$selected_terms = $get_names($term_ids);

		$rows = array();
		$subset = array_slice($product_ids, 0, $limit);

		foreach ($subset as $pid) {
			if (get_post_type($pid) !== 'product') continue;

			$title = get_the_title($pid);
			if ($title === '') $title = "(#{$pid})";

			$current_ids = wp_get_object_terms($pid, $tax, array('fields' => 'ids'));
			if (is_wp_error($current_ids)) $current_ids = array();
			$current_ids = array_values(array_map('absint', (array) $current_ids));

			$current_terms = $get_names($current_ids);

			if ($mode === 'replace') {
				$final_ids = $term_ids;
			} elseif ($mode === 'remove') {
				$final_ids = array_values(array_diff($current_ids, $term_ids));
			} else {
				$final_ids = array_values(array_unique(array_merge($current_ids, $term_ids)));
				sort($final_ids);
			}

			$known = $current_terms + $selected_terms;
			$missing = array_diff($final_ids, array_keys($known));
			if (!empty($missing)) {
				$known += $get_names($missing);
			}

			$final_terms = array();
			foreach ($final_ids as $tid) {
				$final_terms[] = isset($known[(int)$tid]) ? $known[(int)$tid] : "#{$tid}";
			}

			$rows[] = array(
				'product_id' => $pid,
				'title'      => $title,
				'current'    => array_values($current_terms),
				'final'      => $final_terms,
			);
		}

		$obj = get_taxonomy($tax);
		$label = $obj && !empty($obj->labels->name) ? $obj->labels->name : $tax;

		wp_send_json_success(array(
			'limit'      => $limit,
			'taxonomy'   => $tax,
			'tax_label'  => $label,
			'rows'       => $rows,
		));
	}

	public static function ajax_tax_ui() {
		self::check_access_or_die();

		$tax = isset($_GET['taxonomy']) ? sanitize_key($_GET['taxonomy']) : 'product_cat';
		if ( ! self::must_allow_tax($tax) ) {
			wp_send_json_error(array('message' => 'Taxonomy not allowed.'), 400);
		}

		$obj = get_taxonomy($tax);
		if ( ! $obj ) {
			wp_send_json_error(array('message' => 'Invalid taxonomy.'), 400);
		}

		$label  = !empty($obj->labels->singular_name) ? $obj->labels->singular_name : $tax;
		$is_hier = !empty($obj->hierarchical);

		ob_start();
		?>
		<div class="wsh-wcbte-tax-ui" data-taxonomy="<?php echo esc_attr($tax); ?>" data-hier="<?php echo $is_hier ? '1' : '0'; ?>">
			<label><strong><?php echo esc_html($label); ?></strong> <code><?php echo esc_html($tax); ?></code></label>

			<?php if ( $is_hier ) : ?>
				<div class="wsh-wcbte-cat-search" style="margin-top:8px;">
					<input type="text" class="regular-text" id="wsh-wcbte-tax-search" placeholder="<?php esc_attr_e('Search...', 'wsh-wcbte'); ?>">
					<button type="button" class="button" id="wsh-wcbte-tax-search-clear"><?php esc_html_e('Clear', 'wsh-wcbte'); ?></button>
				</div>

				<div class="wsh-wcbte-catbox__inner" style="margin-top:8px;">
					<ul class="categorychecklist" id="wsh-wcbte-tax-checklist">
						<?php
						wp_terms_checklist(0, array(
							'taxonomy'      => $tax,
							'checked_ontop' => false,
							'walker'        => new Walker_Category_Checklist(),
						));
						?>
					</ul>
				</div>

				<p class="description"><?php esc_html_e('Select terms (hierarchical).', 'wsh-wcbte'); ?></p>

			<?php else : ?>
				<select id="wsh-wcbte-term-select" multiple="multiple" style="width:100%" data-taxonomy="<?php echo esc_attr($tax); ?>"></select>
				<p class="description"><?php esc_html_e('Search and select terms.', 'wsh-wcbte'); ?></p>
			<?php endif; ?>
		</div>
		<?php
		$html = ob_get_clean();

		wp_send_json_success(array(
			'html'  => $html,
			'label' => $label,
			'hier'  => $is_hier ? 1 : 0,
		));
	}
}
