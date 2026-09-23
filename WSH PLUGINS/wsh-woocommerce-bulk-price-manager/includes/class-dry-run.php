<?php
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Dry Run + Apply (FREE)
 *
 * - preview(): računa nove cene, vraća prvih N redova za prikaz.
 * - apply(): snima UNDO snapshot (jedan poslednji), pa upisuje cene u Woo meta.
 *
 * Zavisnosti:
 * - WSH_WCBPM_Filter_Engine
 * - WSH_WCBPM_Price_Calculator
 * - WSH_WCBPM_Undo_Manager
 */
class WSH_WCBPM_Dry_Run
{

	/**
	 * Koliko redova vraćamo u preview tabeli (da ne ubijemo admin).
	 */
	const PREVIEW_LIMIT = 200;

	//PRO
	protected static function job_option_key($job_id) {
	return 'wsh_wcbpm_job_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $job_id);
	}

	//PRO
	protected static function job_is_pro_active() : bool {
		return class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active();
	}

	//PRO
	public static function job_start_apply($filters, $update, $batch_size = 200)
	{
		if (! function_exists('wc_get_product')) {
			return array('success' => false, 'message' => __('WooCommerce is not active.', 'wsh-wcbpm'));
		}

		if ( ! self::job_is_pro_active() ) {
			return array('success' => false, 'message' => __('PRO feature. Activate your license.', 'wsh-wcbpm'));
		}

		$validation = self::validate_update($update);
		if (! $validation['ok']) {
			return array('success' => false, 'message' => $validation['message']);
		}

		$filters     = is_array($filters) ? $filters : array();
		$price_scope = isset($filters['price_scope']) ? sanitize_text_field((string) $filters['price_scope']) : 'both';

		$allowed_scopes = array('both', 'regular', 'sale', 'sale_from_regular');
		if (! in_array($price_scope, $allowed_scopes, true)) $price_scope = 'both';

		// Guard: sale_from_regular only makes sense for decrease
		$action = isset($update['action']) ? (string) $update['action'] : 'decrease';
		if ('sale_from_regular' === $price_scope && 'decrease' !== $action) {
			return array('success' => false, 'message' => __('Sale-from-regular mode only supports Decrease.', 'wsh-wcbpm'));
		}

		if (! class_exists('WSH_WCBPM_Filter_Engine')) {
			return array('success' => false, 'message' => __('Filter engine is not loaded.', 'wsh-wcbpm'));
		}

		$targets = WSH_WCBPM_Filter_Engine::find_targets($filters);
		$total   = self::count_targets($targets);

		if ($total <= 0) {
			return array('success' => false, 'message' => __('There are no products matching the filters.', 'wsh-wcbpm'));
		}

		// Undo snapshot scope: sale_from_regular => snapshot captures sale (since we write sale)
		$snapshot_scope = ('sale_from_regular' === $price_scope) ? 'sale' : $price_scope;

		if (class_exists('WSH_WCBPM_Undo_Manager')) {
			WSH_WCBPM_Undo_Manager::create_snapshot($targets, $snapshot_scope);
		}

		// Flatten IDs to process
		$ids = array();

		if (! empty($targets['products'])) {
			foreach ((array)$targets['products'] as $pid) {
				$pid = (int) $pid;
				if ($pid > 0) $ids[] = $pid;
			}
		}

		$parent_ids = array();
		if (! empty($targets['variations'])) {
			foreach ((array)$targets['variations'] as $v) {
				$vid = isset($v['variation_id']) ? (int) $v['variation_id'] : 0;
				if ($vid > 0) $ids[] = $vid;

				$par = isset($v['parent_id']) ? (int) $v['parent_id'] : 0;
				if ($par > 0) $parent_ids[] = $par;
			}
		}
		$parent_ids = array_values(array_unique($parent_ids));

		$job_id = wp_generate_password(18, false, false) . '_' . time();
		$key    = self::job_option_key($job_id);

		$job = array(
			'type'       => 'apply',
			'created_at' => time(),
			'status'     => 'running',
			'cursor'     => 0,
			'total'      => count($ids),
			'ids'        => $ids,
			'parent_ids' => $parent_ids,
			'filters'    => $filters,
			'update'     => $update,
			'price_scope'=> $price_scope,
			'batch_size' => (int) $batch_size,
			'applied'    => 0,
			'errors'     => 0,
		);

		update_option($key, $job, false);

		return array(
			'success' => true,
			'job_id'  => $job_id,
			'total'   => (int) $job['total'],
			'message' => __('Performance job started.', 'wsh-wcbpm'),
		);
	}

	//PRO
	public static function job_step_apply($job_id)
	{
		if (! function_exists('wc_get_product')) {
			return array('success' => false, 'message' => __('WooCommerce is not active.', 'wsh-wcbpm'));
		}

		if ( ! self::job_is_pro_active() ) {
			return array('success' => false, 'message' => __('PRO feature. Activate your license.', 'wsh-wcbpm'));
		}

		$key = self::job_option_key($job_id);
		$job = get_option($key);

		if (! is_array($job) || empty($job['ids']) || 'running' !== ($job['status'] ?? '')) {
			return array('success' => false, 'message' => __('Job not found or not running.', 'wsh-wcbpm'));
		}

		$ids        = (array) $job['ids'];
		$total      = (int) ($job['total'] ?? count($ids));
		$cursor     = (int) ($job['cursor'] ?? 0);
		$batch_size = (int) ($job['batch_size'] ?? 200);
		$update     = (array) ($job['update'] ?? array());
		$scope      = (string) ($job['price_scope'] ?? 'both');

		$applied = (int) ($job['applied'] ?? 0);
		$errors  = (int) ($job['errors'] ?? 0);

		$end = min($cursor + $batch_size, $total);

		for ($i = $cursor; $i < $end; $i++) {
			$pid = isset($ids[$i]) ? (int) $ids[$i] : 0;
			if ($pid <= 0) { $errors++; continue; }

			$res = self::apply_to_product_id($pid, $update, $scope);
			if ($res) $applied++;
			else $errors++;
		}

		$cursor = $end;

		$done = ($cursor >= $total);

		// If done: sync parents once
		if ($done && ! empty($job['parent_ids']) && is_array($job['parent_ids'])) {
			$parent_ids = array_values(array_unique(array_map('intval', $job['parent_ids'])));
			foreach ($parent_ids as $par_id) {
				if ($par_id > 0) {
					$parent = wc_get_product($par_id);
					if ($parent) $parent->save();
					wc_delete_product_transients($par_id);
				}
			}
		}

		$job['cursor']  = $cursor;
		$job['applied'] = $applied;
		$job['errors']  = $errors;

		if ($done) {
			$job['status'] = 'done';
			// cleanup: delete job option (ili ostavi 10 min, ali najčistije je delete)
			delete_option($key);
		} else {
			update_option($key, $job, false);
		}

		return array(
			'success'   => true,
			'done'      => $done,
			'processed' => $cursor,
			'total'     => $total,
			'applied'   => $applied,
			'errors'    => $errors,
			'message'   => $done ? __('Performance apply completed.', 'wsh-wcbpm') : __('Batch processed.', 'wsh-wcbpm'),
		);
	}

	//PRO
	public static function job_start_restore($batch_id, $batch_size = 200)
	{
		if (! function_exists('wc_get_product')) {
			return array('success' => false, 'message' => __('WooCommerce is not active.', 'wsh-wcbpm'));
		}

		if ( ! self::job_is_pro_active() ) {
			return array('success' => false, 'message' => __('PRO feature. Activate your license.', 'wsh-wcbpm'));
		}

		$batch_id = sanitize_text_field((string) $batch_id);
		if ('' === $batch_id) {
			return array('success' => false, 'message' => __('Missing batch_id.', 'wsh-wcbpm'));
		}

		// Load snapshot items from DB (history)
		if ( ! class_exists('WSH_WCBPM_Undo_Manager') ) {
			return array('success' => false, 'message' => __('Undo module is not loaded.', 'wsh-wcbpm'));
		}

		$items = self::load_snapshot_items_by_batch_id($batch_id);
		if ( ! is_array($items) || empty($items) ) {
			return array('success' => false, 'message' => __('Snapshot not found or empty.', 'wsh-wcbpm'));
		}

		$ids = array_map('intval', array_keys($items));
		$ids = array_values(array_filter($ids, function($n){ return $n > 0; }));

		if (empty($ids)) {
			return array('success' => false, 'message' => __('Snapshot has no valid items.', 'wsh-wcbpm'));
		}

		$job_id = wp_generate_password(18, false, false) . '_' . time();
		$key    = self::job_option_key($job_id);

		$job = array(
			'type'       => 'restore',
			'created_at' => time(),
			'status'     => 'running',
			'cursor'     => 0,
			'total'      => count($ids),
			'batch_id'   => $batch_id,
			'batch_size' => (int) max(50, min(2000, (int)$batch_size)),
			'ids'        => $ids,
			'items'      => $items, // map product_id => prices
			'restored'   => 0,
			'errors'     => 0,
		);

		update_option($key, $job, false);

		return array(
			'success' => true,
			'job_id'  => $job_id,
			'total'   => (int) $job['total'],
			'message' => __('Performance restore started.', 'wsh-wcbpm'),
		);
	}

	//PRO
	public static function job_step_restore($job_id)
	{
		if (! function_exists('wc_get_product')) {
			return array('success' => false, 'message' => __('WooCommerce is not active.', 'wsh-wcbpm'));
		}

		if ( ! self::job_is_pro_active() ) {
			return array('success' => false, 'message' => __('PRO feature. Activate your license.', 'wsh-wcbpm'));
		}

		$key = self::job_option_key($job_id);
		$job = get_option($key);

		if (! is_array($job) || 'running' !== ($job['status'] ?? '') || empty($job['ids']) || empty($job['items'])) {
			return array('success' => false, 'message' => __('Job not found or not running.', 'wsh-wcbpm'));
		}

		$ids        = (array) $job['ids'];
		$items      = (array) $job['items'];
		$total      = (int) ($job['total'] ?? count($ids));
		$cursor     = (int) ($job['cursor'] ?? 0);
		$batch_size = (int) ($job['batch_size'] ?? 200);

		$restored = (int) ($job['restored'] ?? 0);
		$errors   = (int) ($job['errors'] ?? 0);

		$end = min($cursor + $batch_size, $total);

		for ($i = $cursor; $i < $end; $i++) {
			$pid = isset($ids[$i]) ? (int) $ids[$i] : 0;
			if ($pid <= 0) { $errors++; continue; }

			$product = wc_get_product($pid);
			if (! $product) { $errors++; continue; }

			$prices = isset($items[$pid]) && is_array($items[$pid]) ? $items[$pid] : array();

			if (array_key_exists('regular', $prices)) {
				$product->set_regular_price($prices['regular']);
			}
			if (array_key_exists('sale', $prices)) {
				$product->set_sale_price($prices['sale']);
			}

			$product->save();
			wc_delete_product_transients($pid);

			$restored++;
		}

		$cursor = $end;
		$done   = ($cursor >= $total);

		$job['cursor']   = $cursor;
		$job['restored'] = $restored;
		$job['errors']   = $errors;

		if ($done) {
			$job['status'] = 'done';
			delete_option($key);
		} else {
			update_option($key, $job, false);
		}

		return array(
			'success'   => true,
			'done'      => $done,
			'processed' => $cursor,
			'total'     => $total,
			'restored'  => $restored,
			'errors'    => $errors,
			'message'   => $done ? __('Performance restore completed.', 'wsh-wcbpm') : __('Restore batch processed.', 'wsh-wcbpm'),
		);
	}

	//PRO
	protected static function load_snapshot_items_by_batch_id($batch_id)
	{
		global $wpdb;

		$table = $wpdb->prefix . 'wsh_wcbpm_undo';

		$row = $wpdb->get_row(
			$wpdb->prepare("SELECT items FROM {$table} WHERE batch_id = %s LIMIT 1", (string) $batch_id),
			ARRAY_A
		);

		if (! $row || empty($row['items'])) {
			return null;
		}

		$data = json_decode((string)$row['items'], true);
		return is_array($data) ? $data : null;
	}


	/**
	 * Preview: calculates new prices without saving.
	 *
	 * @param array $filters
	 * @param array $update
	 * @return array
	 */
	public static function preview($filters, $update)
	{

		if (! function_exists('wc_get_product')) {
			return array(
				'success' => false,
				'message' => __('WooCommerce is not active.', 'wsh-wcbpm'),
			);
		}

		$validation = self::validate_update($update);
		if (! $validation['ok']) {
			return array(
				'success' => false,
				'message' => $validation['message'],
			);
		}

		if (! class_exists('WSH_WCBPM_Filter_Engine')) {
			return array(
				'success' => false,
				'message' => __('Filter engine is not loaded.', 'wsh-wcbpm'),
			);
		}

		$targets = WSH_WCBPM_Filter_Engine::find_targets($filters);

		$price_scope = isset($filters['price_scope'])
			? sanitize_text_field((string) $filters['price_scope'])
			: 'both';

		if ('sale_from_regular' === $price_scope && 'decrease' !== (string) ($update['action'] ?? 'decrease')) {
			return array(
				'success' => false,
				'message' => __('Sale-from-regular mode only supports Decrease.', 'wsh-wcbpm'),
			);
		}

		$rows = self::build_rows_for_preview($targets, $update, $price_scope, self::PREVIEW_LIMIT);

		return array(
			'success' => true,
			'rows'    => $rows,
			'summary' => array(
				'count' => self::count_targets($targets),
			),
		);
	}


	/**
	 * Apply: saves prices + creates undo snapshot.
	 *
	 * @param array $filters
	 * @param array $update
	 * @return array
	 */
	public static function apply($filters, $update)
	{

		if (! function_exists('wc_get_product')) {
			return array(
				'success' => false,
				'message' => __('WooCommerce is not active.', 'wsh-wcbpm'),
			);
		}

		$validation = self::validate_update($update);
		if (! $validation['ok']) {
			return array(
				'success' => false,
				'message' => $validation['message'],
			);
		}

		$filters     = is_array($filters) ? $filters : array();
		$price_scope = isset($filters['price_scope']) ? sanitize_text_field((string) $filters['price_scope']) : 'both';

		if ('sale_from_regular' === $price_scope && 'decrease' !== (string) ($update['action'] ?? 'decrease')) {
			return array(
				'success' => false,
				'message' => __('Sale-from-regular mode only supports Decrease.', 'wsh-wcbpm'),
			);
		}

		$allowed_scopes = array('both', 'regular', 'sale', 'sale_from_regular');
		if (! in_array($price_scope, $allowed_scopes, true)) {
			$price_scope = 'both';
		}

		$targets = class_exists('WSH_WCBPM_Filter_Engine')
			? WSH_WCBPM_Filter_Engine::find_targets($filters)
			: array('products' => array(), 'variations' => array());

		$total = self::count_targets($targets);
		if ($total <= 0) {
			return array(
				'success' => false,
				'message' => __('There are no products matching the filters.', 'wsh-wcbpm'),
			);
		}

		// Undo Manager currently supports only: both|regular|sale.
		// For "sale_from_regular" we update ONLY sale, so snapshot should capture sale.
		$snapshot_scope = ('sale_from_regular' === $price_scope) ? 'sale' : $price_scope;

		// 1) Create UNDO snapshot before saving.
		if (class_exists('WSH_WCBPM_Undo_Manager')) {
			WSH_WCBPM_Undo_Manager::create_snapshot($targets, $snapshot_scope);
		}

		// 2) Apply update.
		$applied = 0;
		$errors  = 0;

		// Parent products (simple + variable parents).
		if (! empty($targets['products'])) {
			foreach ($targets['products'] as $pid) {
				$pid = (int) $pid;
				if ($pid <= 0) {
					continue;
				}

				// apply_to_product_id() MUST support $price_scope = sale_from_regular
				$res = self::apply_to_product_id($pid, $update, $price_scope);
				if ($res) {
					$applied++;
				} else {
					$errors++;
				}
			}
		}

		// Variations (direct).
		if (! empty($targets['variations'])) {
			foreach ($targets['variations'] as $v) {
				$vid = isset($v['variation_id']) ? (int) $v['variation_id'] : 0;
				if ($vid <= 0) {
					continue;
				}

				$res = self::apply_to_product_id($vid, $update, $price_scope);
				if ($res) {
					$applied++;
				} else {
					$errors++;
				}
			}

			// Re-sync parent variable products after variation updates.
			self::maybe_sync_parents($targets['variations']);
		}

		// Return preview rows again (limit).
		$rows = self::build_rows_for_preview($targets, $update, $price_scope, self::PREVIEW_LIMIT);

		$msg = sprintf(
			/* translators: 1: applied count, 2: errors count */
			__('Updated: %1$d. Errors: %2$d.', 'wsh-wcbpm'),
			(int) $applied,
			(int) $errors
		);

		return array(
			'success' => true,
			'message' => $msg,
			'rows'    => $rows,
			'summary' => array(
				'count'   => $total,
				'applied' => $applied,
				'errors'  => $errors,
			),
		);
	}

	/**
	 * Apply update to a product (or variation) ID.
	 *
	 * @param int    $product_id
	 * @param array  $update
	 * @param string $price_scope both|regular|sale|sale_from_regular
	 * @return bool
	 */
	protected static function apply_to_product_id($product_id, $update, $price_scope)
	{

		$product = wc_get_product((int) $product_id);
		if (! $product) {
			return false;
		}

		$price_scope = (string) $price_scope;

		$old_regular = $product->get_regular_price();
		$old_sale    = $product->get_sale_price();

		$old_regular = ('' === $old_regular || null === $old_regular) ? null : (float) $old_regular;
		$old_sale    = ('' === $old_sale || null === $old_sale) ? null : (float) $old_sale;

		$changed = false;

		// 1) Regular update
		if ('both' === $price_scope || 'regular' === $price_scope) {
			$new_regular = WSH_WCBPM_Price_Calculator::calculate($old_regular, $update);
			if (null !== $new_regular) {
				$product->set_regular_price($new_regular);
				$changed = true;
			}
		}

		// 2) Sale update (existing sale only, FREE behavior)
		if ('both' === $price_scope || 'sale' === $price_scope) {
			$new_sale = WSH_WCBPM_Price_Calculator::calculate($old_sale, $update);

			// FREE behavior: do not create sale if it doesn't exist.
			if (null !== $new_sale && null !== $old_sale) {
				$product->set_sale_price($new_sale);
				$changed = true;
			}
		}

		// 3) PRO: Sale from Regular (true discount)
		// - Regular stays unchanged.
		// - Sale is created/updated only if computed sale is < regular.
		if ('sale_from_regular' === $price_scope) {

			if (null !== $old_regular && $old_regular > 0) {

				$candidate_sale = WSH_WCBPM_Price_Calculator::calculate($old_regular, $update);

				// Set sale only if valid and lower than regular.
				if (null !== $candidate_sale && (float) $candidate_sale < (float) $old_regular) {
					$product->set_sale_price($candidate_sale);
					$changed = true;
				}

				// IMPORTANT: If candidate_sale >= regular, do nothing (do NOT remove existing sale).
			}
		}

		// Not an error: simply nothing to change.
		if (! $changed) {
			return true;
		}

		$product->save();
		wc_delete_product_transients((int) $product_id);

		return true;
	}

	/**
	 * Build rows for preview table.
	 *
	 * @param array  $targets
	 * @param array  $update
	 * @param string $price_scope both|regular|sale|sale_from_regular
	 * @param int    $limit
	 * @return array
	 */
	protected static function build_rows_for_preview($targets, $update, $price_scope, $limit = 200)
	{

		$rows  = array();
		$count = 0;

		$product_ids = isset($targets['products']) ? (array) $targets['products'] : array();
		foreach ($product_ids as $pid) {
			$pid = (int) $pid;
			if ($pid <= 0) continue;

			$r = self::preview_row_for_id($pid, $update, $price_scope, false);
			if ($r) {
				$rows[] = $r;
				$count++;
				if ($count >= $limit) return $rows;
			}
		}

		$vars = isset($targets['variations']) ? (array) $targets['variations'] : array();
		foreach ($vars as $v) {
			$vid = isset($v['variation_id']) ? (int) $v['variation_id'] : 0;
			if ($vid <= 0) continue;

			$r = self::preview_row_for_id($vid, $update, $price_scope, true);
			if ($r) {
				$rows[] = $r;
				$count++;
				if ($count >= $limit) return $rows;
			}
		}

		return $rows;
	}

	/**
	 * Build a single preview row.
	 *
	 * @param int    $product_id
	 * @param array  $update
	 * @param bool   $is_variation
	 * @param string $price_scope both|regular|sale|sale_from_regular
	 * @return array|null
	 */
	protected static function preview_row_for_id($product_id, $update, $price_scope, $is_variation = false)
	{

		$p = wc_get_product((int) $product_id);
		if (! $p) {
			return null;
		}

		$price_scope = (string) $price_scope;

		$name = $p->get_name();
		if ('' === $name) {
			$name = sprintf(__('Product #%d', 'wsh-wcbpm'), (int) $product_id);
		}

		$variation_label = '';
		if ($is_variation && method_exists($p, 'get_formatted_variation_attributes')) {
			$variation_label = (string) $p->get_formatted_variation_attributes(true);
		}

		$old_regular = $p->get_regular_price();
		$old_sale    = $p->get_sale_price();

		$old_regular = ('' === $old_regular || null === $old_regular) ? null : (float) $old_regular;
		$old_sale    = ('' === $old_sale || null === $old_sale) ? null : (float) $old_sale;

		$new_regular = null;
		$new_sale    = null;

		// REGULAR preview
		if ('both' === $price_scope || 'regular' === $price_scope) {
			$new_regular = WSH_WCBPM_Price_Calculator::calculate($old_regular, $update);
		} else {
			// unchanged
			$new_regular = $old_regular;
		}

		// SALE preview
		if ('both' === $price_scope || 'sale' === $price_scope) {

			// FREE behavior: calculate sale only if it exists
			if (null !== $old_sale) {
				$new_sale = WSH_WCBPM_Price_Calculator::calculate($old_sale, $update);
			} else {
				$new_sale = null;
			}
		} elseif ('sale_from_regular' === $price_scope) {

			// Compute sale from REGULAR (discount mode).
			// IMPORTANT: preview must match apply() behavior:
			// - If candidate sale is a real discount (< regular) => show candidate
			// - Otherwise => keep existing sale unchanged (do NOT show null unless there was no sale)
			if (null !== $old_regular && $old_regular > 0) {

				$candidate_sale = WSH_WCBPM_Price_Calculator::calculate($old_regular, $update);

				if (null !== $candidate_sale && (float) $candidate_sale < (float) $old_regular) {
					$new_sale = $candidate_sale; // would be set/updated
				} else {
					$new_sale = $old_sale; // unchanged (matches apply: do nothing)
				}
			} else {
				$new_sale = $old_sale; // unchanged (regular missing => apply would do nothing)
			}
		}

		$fmt = function ($v) {
			if (null === $v) return null;
			return wc_format_localized_price((float) $v);
		};

		return array(
			'product_id'      => (int) $product_id,
			'name'            => (string) $name,
			'variation_label' => (string) $variation_label,
			'old_regular'     => $fmt($old_regular),
			'old_sale'        => $fmt($old_sale),
			'new_regular'     => $fmt($new_regular),
			'new_sale'        => $fmt($new_sale),
		);
	}



	/**
	 * Count total targets.
	 *
	 * @param array $targets
	 * @return int
	 */
	protected static function count_targets($targets)
	{
		$p = isset($targets['products']) ? (array) $targets['products'] : array();
		$v = isset($targets['variations']) ? (array) $targets['variations'] : array();
		return (int) (count($p) + count($v));
	}

	/**
	 * Validate update payload.
	 *
	 * @param array $update
	 * @return array { ok: bool, message: string }
	 */
	protected static function validate_update($update)
	{
		$method = isset($update['method']) ? (string) $update['method'] : 'percent';
		$action = isset($update['action']) ? (string) $update['action'] : 'decrease';
		$value  = isset($update['value']) ? trim((string) $update['value']) : '';

		if ('' === $value) {
			return array('ok' => false, 'message' => __('Please enter a value (percentage or amount).', 'wsh-wcbpm'));
		}

		if (! is_numeric($value)) {
			return array('ok' => false, 'message' => __('The value must be a number.', 'wsh-wcbpm'));
		}

		$val = (float) $value;
		if ($val <= 0) {
			return array('ok' => false, 'message' => __('The value must be greater than 0.', 'wsh-wcbpm'));
		}

		if ('percent' === $method && $val > 100000) {
			return array('ok' => false, 'message' => __('The percentage is too large.', 'wsh-wcbpm'));
		}

		if ('fixed' !== $method && 'percent' !== $method) {
			return array('ok' => false, 'message' => __('Unknown method.', 'wsh-wcbpm'));
		}

		if ('increase' !== $action && 'decrease' !== $action) {
			return array('ok' => false, 'message' => __('Unknown action.', 'wsh-wcbpm'));
		}

		return array('ok' => true, 'message' => '');
	}



	/**
	 * After updating variations, sync parent variable products.
	 *
	 * @param array $variations list of [variation_id,parent_id]
	 * @return void
	 */
	protected static function maybe_sync_parents($variations)
	{

		if (empty($variations) || ! function_exists('wc_get_product')) {
			return;
		}

		$parent_ids = array();
		foreach ($variations as $v) {
			$pid = isset($v['parent_id']) ? (int) $v['parent_id'] : 0;
			if ($pid > 0) {
				$parent_ids[] = $pid;
			}
		}

		$parent_ids = array_values(array_unique($parent_ids));

		foreach ($parent_ids as $pid) {
			$parent = wc_get_product($pid);
			if ($parent && method_exists($parent, 'sync')) {
				// In some WC versions, WC_Product_Variable::sync is static, but instance->save usually refreshes _price range.
				$parent->save();
			}
			wc_delete_product_transients($pid);
		}
	}
}
