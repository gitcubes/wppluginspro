<?php
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Undo Manager (FREE)
 *
 * - Stores ONLY one last snapshot (overwrite behavior)
 * - Snapshot is created BEFORE the bulk update
 * - Allows rollback with a single click
 */
class WSH_WCBPM_Undo_Manager
{

	const OPTION_KEY = 'wsh_wcbpm_last_snapshot';

	/**
	 * Init (for compatibility with plugin boot)
	 */
	public static function init()
	{
		// FREE: nothing special to initialize for now.
	}

	protected static function is_pro_active(): bool
	{
		return class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active();
	}

	protected static function table_name(): string
	{
		global $wpdb;
		return $wpdb->prefix . 'wsh_wcbpm_undo';
	}

	/**
	 * Backward compatible alias (if UI calls has_undo()).
	 */
	public static function has_undo()
	{
		return self::has_snapshot();
	}

	/**
	 * Backward compatible alias (if UI calls undo_last()).
	 */
	public static function undo_last()
	{
		return self::restore();
	}

	/**
	 * Create snapshot before bulk update.
	 *
	 * @param array  $targets     Products + variations
	 * @param string $price_scope both|regular|sale
	 * @return void
	 */
	public static function create_snapshot($targets, $price_scope = 'both')
	{

		if (! function_exists('wc_get_product')) {
			return;
		}

		// PRO => DB history
		if (self::is_pro_active()) {
			self::create_snapshot_db($targets, $price_scope);
			return;
		}

		$data = array(
			'created_at' => current_time('mysql'),
			'price_scope' => $price_scope,
			'items'      => array(),
		);

		// Products
		if (! empty($targets['products']) && is_array($targets['products'])) {
			foreach ($targets['products'] as $pid) {
				$pid = (int) $pid;
				if ($pid <= 0) {
					continue;
				}
				$product = wc_get_product($pid);
				if (! $product) {
					continue;
				}
				$data['items'][$pid] = self::capture_prices($product, $price_scope);
			}
		}

		// Variations
		if (! empty($targets['variations']) && is_array($targets['variations'])) {
			foreach ($targets['variations'] as $v) {
				$vid = isset($v['variation_id']) ? (int) $v['variation_id'] : 0;
				if ($vid <= 0) {
					continue;
				}
				$product = wc_get_product($vid);
				if (! $product) {
					continue;
				}
				$data['items'][$vid] = self::capture_prices($product, $price_scope);
			}
		}

		update_option(self::OPTION_KEY, $data, false);
	}

	protected static function create_snapshot_db($targets, $price_scope = 'both')
	{
		global $wpdb;

		$table = self::table_name();

		$items = array();

		// Products
		if (! empty($targets['products']) && is_array($targets['products'])) {
			foreach ($targets['products'] as $pid) {
				$pid = (int) $pid;
				if ($pid <= 0) continue;

				$product = wc_get_product($pid);
				if (! $product) continue;

				$items[$pid] = self::capture_prices($product, $price_scope);
			}
		}

		// Variations
		if (! empty($targets['variations']) && is_array($targets['variations'])) {
			foreach ($targets['variations'] as $v) {
				$vid = isset($v['variation_id']) ? (int) $v['variation_id'] : 0;
				if ($vid <= 0) continue;

				$product = wc_get_product($vid);
				if (! $product) continue;

				$items[$vid] = self::capture_prices($product, $price_scope);
			}
		}

		if (empty($items)) return;

		$batch_id = wp_generate_password(16, false, false) . '-' . time();
		$created_at = current_time('mysql');
		$created_by = get_current_user_id();

		$wpdb->insert(
			$table,
			array(
				'batch_id'     => $batch_id,
				'created_at'   => $created_at,
				'created_by'   => (int) $created_by,
				'scope'        => (string) $price_scope,
				'items_count'  => (int) count($items),
				'items'        => wp_json_encode($items),
			),
			array('%s', '%s', '%d', '%s', '%d', '%s')
		);
	}

	public static function list_history(int $limit = 20): array
	{
		if (! self::is_pro_active()) return array();

		global $wpdb;
		$table = self::table_name();

		$limit = max(1, min(200, $limit));

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, batch_id, created_at, created_by, scope, items_count
             FROM {$table}
             ORDER BY created_at DESC
             LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	public static function restore_batch(string $batch_id): array
	{
		if (! function_exists('wc_get_product')) {
			return array('success' => false, 'message' => __('WooCommerce is not active.', 'wsh-wcbpm'));
		}
		if (! self::is_pro_active()) {
			return array('success' => false, 'message' => __('PRO feature. Activate license.', 'wsh-wcbpm'));
		}

		global $wpdb;
		$table = self::table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare("SELECT items FROM {$table} WHERE batch_id = %s LIMIT 1", $batch_id),
			ARRAY_A
		);

		if (! $row || empty($row['items'])) {
			return array('success' => false, 'message' => __('Snapshot not found.', 'wsh-wcbpm'));
		}

		$items = json_decode((string) $row['items'], true);
		if (! is_array($items)) {
			return array('success' => false, 'message' => __('Snapshot data is invalid.', 'wsh-wcbpm'));
		}

		$restored = 0;

		foreach ($items as $product_id => $prices) {
			$product_id = (int) $product_id;
			if ($product_id <= 0) continue;

			$product = wc_get_product($product_id);
			if (! $product) continue;

			if (is_array($prices) && array_key_exists('regular', $prices)) {
				$product->set_regular_price($prices['regular']);
			}
			if (is_array($prices) && array_key_exists('sale', $prices)) {
				$product->set_sale_price($prices['sale']);
			}

			$product->save();
			wc_delete_product_transients($product_id);
			$restored++;
		}

		return array(
			'success' => true,
			'message' => sprintf(__('Restore completed. Restored %d products.', 'wsh-wcbpm'), (int) $restored),
		);
	}

	public static function delete_batch(string $batch_id): array
	{
		if (! self::is_pro_active()) {
			return array('success' => false, 'message' => __('PRO feature. Activate license.', 'wsh-wcbpm'));
		}

		global $wpdb;
		$table = self::table_name();

		$deleted = $wpdb->delete($table, array('batch_id' => $batch_id), array('%s'));
		if (false === $deleted) {
			return array('success' => false, 'message' => __('Delete failed.', 'wsh-wcbpm'));
		}

		return array('success' => true, 'message' => __('Snapshot deleted.', 'wsh-wcbpm'));
	}


	/**
	 * Capture prices for a single product.
	 *
	 * @param WC_Product $product
	 * @param string     $scope
	 * @return array
	 */
	protected static function capture_prices($product, $scope)
	{

		$row = array();

		if ('both' === $scope || 'regular' === $scope) {
			$row['regular'] = $product->get_regular_price();
		}

		if ('both' === $scope || 'sale' === $scope) {
			$row['sale'] = $product->get_sale_price();
		}

		return $row;
	}

	/**
	 * Check if an undo snapshot is available.
	 *
	 * @return bool
	 */
	public static function has_snapshot()
	{
		$snap = get_option(self::OPTION_KEY);
		return is_array($snap) && ! empty($snap['items']) && is_array($snap['items']);
	}

	/**
	 * Restore last snapshot.
	 *
	 * @return array { success, message }
	 */
	public static function restore()
	{

		if (! function_exists('wc_get_product')) {
			return array(
				'success' => false,
				'message' => __('WooCommerce is not active.', 'wsh-wcbpm'),
			);
		}

		$snap = get_option(self::OPTION_KEY);
		if (! is_array($snap) || empty($snap['items']) || ! is_array($snap['items'])) {
			return array(
				'success' => false,
				'message' => __('No undo snapshot available.', 'wsh-wcbpm'),
			);
		}

		$restored = 0;

		foreach ($snap['items'] as $product_id => $prices) {
			$product_id = (int) $product_id;
			if ($product_id <= 0) {
				continue;
			}

			$product = wc_get_product($product_id);
			if (! $product) {
				continue;
			}

			if (is_array($prices) && array_key_exists('regular', $prices)) {
				$product->set_regular_price($prices['regular']);
			}

			if (is_array($prices) && array_key_exists('sale', $prices)) {
				$product->set_sale_price($prices['sale']);
			}

			$product->save();
			wc_delete_product_transients($product_id);
			$restored++;
		}

		// Clear snapshot after restore (FREE behavior).
		delete_option(self::OPTION_KEY);

		return array(
			'success' => true,
			'message' => sprintf(
				__('Undo completed successfully. Restored %d products.', 'wsh-wcbpm'),
				(int) $restored
			),
		);
	}

	/**
	 * Get snapshot metadata for UI.
	 *
	 * @return array|null
	 */
	public static function get_snapshot_info()
	{
		$snap = get_option(self::OPTION_KEY);
		if (! is_array($snap)) {
			return null;
		}

		return array(
			'created_at'  => isset($snap['created_at']) ? (string) $snap['created_at'] : '',
			'items_count' => (isset($snap['items']) && is_array($snap['items'])) ? count($snap['items']) : 0,
		);
	}
}
