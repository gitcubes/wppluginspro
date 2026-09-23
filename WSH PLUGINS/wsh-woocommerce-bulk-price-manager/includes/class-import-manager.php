<?php
if (!defined('ABSPATH')) exit;

class WSH_WCBPM_Import_Manager
{
	const PREVIEW_LIMIT = 200;

	// where we store parsed import (1h)
	const IMPORT_OPTION_PREFIX = 'wsh_wcbpm_import_';
	const IMPORT_TTL_SECONDS   = 3600;

	public static function init()
	{
		add_action('wp_ajax_wsh_wcbpm_import_preview', array(__CLASS__, 'ajax_import_preview'));
		add_action('wp_ajax_wsh_wcbpm_job_start_import', array(__CLASS__, 'ajax_job_start_import'));
		add_action('wp_ajax_wsh_wcbpm_job_step_import', array(__CLASS__, 'ajax_job_step_import'));
	}

	protected static function is_pro_active(): bool
	{
		return class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active();
	}

	protected static function require_caps_or_die()
	{
		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => __("You don't have permission.", 'wsh-wcbpm')), 403);
		}
		check_ajax_referer('wsh_wcbpm_admin');

		if (!self::is_pro_active()) {
			wp_send_json_error(array('message' => __('This is a PRO feature. Activate your license.', 'wsh-wcbpm')), 200);
		}

		if (!function_exists('wc_get_product')) {
			wp_send_json_error(array('message' => __('WooCommerce is not active.', 'wsh-wcbpm')), 200);
		}
	}

	/**
	 * Resolve product ID by:
	 * - product_id if provided
	 * - else SKU lookup
	 *
	 * @param array $it
	 * @return int
	 */
	protected static function resolve_product_id(array $it): int
	{
		$pid = isset($it['product_id']) ? (int)$it['product_id'] : 0;
		if ($pid > 0) return $pid;

		$sku = isset($it['sku']) ? trim((string)$it['sku']) : '';
		if ($sku === '') return 0;

		if (function_exists('wc_get_product_id_by_sku')) {
			$found = (int) wc_get_product_id_by_sku($sku);
			if ($found > 0) return $found;
		}

		return 0;
	}

	/**
	 * If both product_id and sku are provided, check if SKU matches that product.
	 *
	 * @param array      $it
	 * @param WC_Product $p
	 * @return bool
	 */
	protected static function product_id_and_sku_match(array $it, $p): bool
	{
		$input_pid = isset($it['product_id']) ? (int)$it['product_id'] : 0;
		$input_sku = isset($it['sku']) ? trim((string)$it['sku']) : '';

		if ($input_pid <= 0 || $input_sku === '') {
			return true; // nothing to compare
		}

		if (!is_object($p) || !method_exists($p, 'get_sku')) {
			return true; // can't check
		}

		$real_sku = (string) $p->get_sku();
		if ($real_sku === '') {
			// product has no SKU, but CSV provided one -> mismatch
			return false;
		}

		return (strcasecmp($real_sku, $input_sku) === 0);
	}

	/* --------------------------
	 * AJAX: Preview (upload CSV)
	 * -------------------------- */
	public static function ajax_import_preview()
	{
		self::require_caps_or_die();

		if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
			wp_send_json_error(array('message' => __('No file uploaded.', 'wsh-wcbpm')), 200);
		}

		$clear_sale_empty = !empty($_POST['clear_sale_empty']) ? 1 : 0;

		$tmp = $_FILES['file']['tmp_name'] ?? '';
		$err = $_FILES['file']['error'] ?? UPLOAD_ERR_OK;

		if ($err !== UPLOAD_ERR_OK || !$tmp || !file_exists($tmp)) {
			wp_send_json_error(array('message' => __('Upload failed.', 'wsh-wcbpm')), 200);
		}

		$parsed = self::parse_csv_file($tmp);

		if (empty($parsed['success'])) {
			wp_send_json_error(array('message' => $parsed['message'] ?? __('CSV parse error.', 'wsh-wcbpm')), 200);
		}

		$items = (array)($parsed['items'] ?? array());
		$stats = (array)($parsed['stats'] ?? array());
		$rows  = array();

		$errors_count = 0;
		$valid_count  = 0;

		foreach ($items as $it) {
			if (count($rows) >= self::PREVIEW_LIMIT) break;

			$preview = self::preview_row_from_item($it, (bool)$clear_sale_empty);
			if (!empty($preview['error'])) {
				$errors_count++;
			} else {
				$valid_count++;
			}

			$rows[] = $preview;
		}

		// store full import for apply job
		$import_id = self::store_import_items($items, array(
			'clear_sale_empty'   => (int)$clear_sale_empty,
			'detected_delimiter' => (string)($parsed['delimiter'] ?? ''),
		));

		wp_send_json_success(array(
			'import_id' => $import_id,
			'rows'      => $rows,
			'summary'   => array(
				'total_rows'   => (int)($stats['total_rows'] ?? count($items)),
				'valid_rows'   => (int)$valid_count,
				'error_rows'   => (int)$errors_count,
				'preview_rows' => count($rows),
			),
			'message'   => __('Import preview is ready. Review errors before applying.', 'wsh-wcbpm'),
		));
	}

	protected static function preview_row_from_item(array $it, bool $clear_sale_empty): array
	{
		$input_pid = (int)($it['product_id'] ?? 0);
		$input_sku = trim((string)($it['sku'] ?? ''));

		$product_id = self::resolve_product_id($it);

		if ($product_id <= 0) {
			return array(
				'product_id'   => 0,
				'sku'          => $input_sku,
				'name'         => '',
				'old_regular'  => null,
				'old_sale'     => null,
				'new_regular'  => null,
				'new_sale'     => null,
				'status'       => 'error',
				'error'        => __('Missing product_id and/or invalid SKU.', 'wsh-wcbpm'),
			);
		}

		$p = wc_get_product($product_id);
		if (!$p) {
			return array(
				'product_id'   => $product_id,
				'sku'          => $input_sku,
				'name'         => '',
				'old_regular'  => null,
				'old_sale'     => null,
				'new_regular'  => null,
				'new_sale'     => null,
				'status'       => 'error',
				'error'        => __('Product not found.', 'wsh-wcbpm'),
			);
		}

		// mismatch check only when BOTH provided
		if ($input_pid > 0 && $input_sku !== '' && !self::product_id_and_sku_match($it, $p)) {
			return array(
				'product_id'   => $product_id,
				'sku'          => $input_sku,
				'name'         => $p->get_name(),
				'old_regular'  => null,
				'old_sale'     => null,
				'new_regular'  => null,
				'new_sale'     => null,
				'status'       => 'error',
				'error'        => __('product_id and SKU do not match the same product.', 'wsh-wcbpm'),
			);
		}

		$name = $p->get_name();
		if ($name === '') $name = sprintf(__('Product #%d', 'wsh-wcbpm'), $product_id);

		// If CSV sku empty, show actual sku for clarity
		$real_sku = method_exists($p, 'get_sku') ? (string)$p->get_sku() : '';
		$display_sku = ($input_sku !== '') ? $input_sku : $real_sku;

		$old_regular = $p->get_regular_price();
		$old_sale    = $p->get_sale_price();

		$old_regular_f = ('' === $old_regular || null === $old_regular) ? null : (float)$old_regular;
		$old_sale_f    = ('' === $old_sale || null === $old_sale) ? null : (float)$old_sale;

		$has_regular_col = array_key_exists('regular_price', $it);
		$has_sale_col    = array_key_exists('sale_price', $it);

		$new_regular = $old_regular_f;
		$new_sale    = $old_sale_f;

		// regular: null => no change
		if ($has_regular_col && $it['regular_price'] !== null) {
			$new_regular = (float)$it['regular_price'];
		}

		// sale: null => no change OR clear
		$sale_will_clear = false;
		if ($has_sale_col) {
			if ($it['sale_price'] === null) {
				if ($clear_sale_empty) {
					$new_sale = null; // means clear
					$sale_will_clear = true;
				}
			} else {
				$new_sale = (float)$it['sale_price'];
			}
		}

		// validate sale <= regular when both are numeric and sale isn't being cleared
		if ($new_regular !== null && $new_sale !== null && !$sale_will_clear) {
			if ((float)$new_sale > (float)$new_regular) {
				return array(
					'product_id'   => $product_id,
					'sku'          => $display_sku,
					'name'         => $name,
					'old_regular'  => self::fmt($old_regular_f),
					'old_sale'     => self::fmt($old_sale_f),
					'new_regular'  => self::fmt($new_regular),
					'new_sale'     => self::fmt($new_sale),
					'status'       => 'error',
					'error'        => __('Sale price cannot be greater than Regular price.', 'wsh-wcbpm'),
				);
			}
		}

		// what to display as "new" in preview
		$preview_new_regular = self::fmt($old_regular_f);
		if ($has_regular_col) {
			$preview_new_regular = ($it['regular_price'] === null) ? self::fmt($old_regular_f) : self::fmt($new_regular);
		}

		$preview_new_sale = self::fmt($old_sale_f);
		if ($has_sale_col) {
			if ($it['sale_price'] === null) {
				$preview_new_sale = $clear_sale_empty ? null : self::fmt($old_sale_f);
			} else {
				$preview_new_sale = self::fmt($new_sale);
			}
		}

		return array(
			'product_id'  => $product_id,
			'sku'         => $display_sku,
			'name'        => $name,
			'old_regular' => self::fmt($old_regular_f),
			'old_sale'    => self::fmt($old_sale_f),
			'new_regular' => $preview_new_regular,
			'new_sale'    => $preview_new_sale,
			'status'      => 'ok',
		);
	}

	protected static function fmt($v)
	{
		if ($v === null) return null;
		return wc_format_localized_price((float)$v);
	}

	/* --------------------------
	 * JOB: start import apply
	 * -------------------------- */
	public static function ajax_job_start_import()
	{

		// Povecas memoriju za izvrsavanja skripti
		ini_set('memory_limit', '11024M');      // ili šta realno treba
		ini_set('max_execution_time', 1800);   // 30 min

		self::require_caps_or_die();

		$import_id = isset($_POST['import_id']) ? sanitize_text_field(wp_unslash($_POST['import_id'])) : '';
		if ($import_id === '') {
			wp_send_json_error(array('message' => __('Missing import_id.', 'wsh-wcbpm')), 200);
		}

		$batch_size = isset($_POST['batch_size']) ? (int)$_POST['batch_size'] : 200;
		$batch_size = max(50, min(2000, $batch_size));

		$data = self::load_import_items($import_id);
		if (!$data) {
			wp_send_json_error(array('message' => __('Import data expired or not found. Please upload again.', 'wsh-wcbpm')), 200);
		}

		$items = (array)($data['items'] ?? array());
		$opts  = (array)($data['options'] ?? array());
		$clear_sale_empty = !empty($opts['clear_sale_empty']);

		$valid  = array();
		$errors = 0;

		$parent_ids = array();

		foreach ($items as $it) {
			$pid = self::resolve_product_id($it);
			if ($pid <= 0) { $errors++; continue; }

			$p = wc_get_product($pid);
			if (!$p) { $errors++; continue; }

			// mismatch check if both provided
			$input_pid = (int)($it['product_id'] ?? 0);
			$input_sku = trim((string)($it['sku'] ?? ''));
			if ($input_pid > 0 && $input_sku !== '' && !self::product_id_and_sku_match($it, $p)) {
				$errors++;
				continue;
			}

			// validate numeric values if set (parse already returns float|null, but keep defensive)
			$reg  = $it['regular_price'] ?? null;
			$sale = $it['sale_price'] ?? null;

			if ($reg !== null && !is_numeric($reg)) { $errors++; continue; }
			if ($sale !== null && !is_numeric($sale)) { $errors++; continue; }

			// sale <= regular if both provided and not clearing
			if ($reg !== null && $sale !== null) {
				if ((float)$sale > (float)$reg) { $errors++; continue; }
			}

			// track parent for variation sync
			if ($p->is_type('variation') && method_exists($p, 'get_parent_id')) {
				$par = (int)$p->get_parent_id();
				if ($par > 0) $parent_ids[] = $par;
			}

			$valid[] = array(
				'product_id'    => (int)$pid,
				'sku'           => $input_sku,
				'regular_price' => array_key_exists('regular_price', $it) ? ($reg === null ? null : (float)$reg) : '__nochange__',
				'sale_price'    => array_key_exists('sale_price', $it) ? ($sale === null ? null : (float)$sale) : '__nochange__',
			);
		}

		$parent_ids = array_values(array_unique(array_map('intval', $parent_ids)));

		if (empty($valid)) {
			wp_send_json_error(array('message' => __('No valid rows to import.', 'wsh-wcbpm')), 200);
		}

		// snapshot before apply — capture BOTH reg+sale for all involved ids
		if (class_exists('WSH_WCBPM_Undo_Manager')) {
			$targets = array(
				'products'   => array_map(function ($x) { return (int)$x['product_id']; }, $valid),
				'variations' => array(),
			);
			WSH_WCBPM_Undo_Manager::create_snapshot($targets, 'both');
		}

		$job_id = wp_generate_password(18, false, false) . '_' . time();
		$key    = 'wsh_wcbpm_job_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$job_id);

		$job = array(
			'type'             => 'import',
			'status'           => 'running',
			'created_at'       => time(),
			'cursor'           => 0,
			'total'            => count($valid),
			'batch_size'       => $batch_size,
			'items'            => $valid,
			'clear_sale_empty' => (int)$clear_sale_empty,
			'parent_ids'       => $parent_ids,
			'applied'          => 0,
			'errors'           => 0,
		);

		update_option($key, $job, false);

		wp_send_json_success(array(
			'job_id'  => $job_id,
			'total'   => (int)$job['total'],
			'message' => sprintf(
				/* translators: 1: valid rows, 2: skipped errors */
				__('Import started. Rows: %1$d. Skipped: %2$d.', 'wsh-wcbpm'),
				(int)$job['total'],
				(int)$errors
			),
		));
	}

	/* --------------------------
	 * JOB: step import apply
	 * -------------------------- */
	public static function ajax_job_step_import()
	{

		// Povecas memoriju za izvrsavanja skripti
		ini_set('memory_limit', '11024M');      // ili šta realno treba
		ini_set('max_execution_time', 1800);   // 30 min

		//self::require_caps_or_die();

		$job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
		if ($job_id === '') {
			wp_send_json_error(array('message' => __('Missing job_id.', 'wsh-wcbpm')), 200);
		}

		$key = 'wsh_wcbpm_job_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$job_id);
		$job = get_option($key);

		if (!is_array($job) || ($job['status'] ?? '') !== 'running' || empty($job['items'])) {
			wp_send_json_error(array('message' => __('Job not found or not running.', 'wsh-wcbpm')), 200);
		}

		$items      = (array)$job['items'];
		$total      = (int)($job['total'] ?? count($items));
		$cursor     = (int)($job['cursor'] ?? 0);
		$batch_size = (int)($job['batch_size'] ?? 200);

		$applied = (int)($job['applied'] ?? 0);
		$errors  = (int)($job['errors'] ?? 0);
		$clear_sale_empty = !empty($job['clear_sale_empty']);

		$end = min($cursor + $batch_size, $total);

		for ($i = $cursor; $i < $end; $i++) {
			$it = $items[$i] ?? null;
			if (!is_array($it)) { $errors++; continue; }

			$pid = (int)($it['product_id'] ?? 0);
			if ($pid <= 0) { $errors++; continue; }

			$p = wc_get_product($pid);
			if (!$p) { $errors++; continue; }

			$reg  = $it['regular_price'] ?? '__nochange__';
			$sale = $it['sale_price'] ?? '__nochange__';

			$changed = false;

			// regular: null => no change
			if ($reg !== '__nochange__') {
				if ($reg === null) {
					// no change
				} else {
					$p->set_regular_price((float)$reg);
					$changed = true;
				}
			}

			// sale: null => no change OR clear
			if ($sale !== '__nochange__') {
				if ($sale === null) {
					if ($clear_sale_empty) {
						$p->set_sale_price('');
						$changed = true;
					}
				} else {
					$p->set_sale_price((float)$sale);
					$changed = true;
				}
			}

			if ($changed) {
				$p->save();
				wc_delete_product_transients($pid);
			}

			$applied++;
		}

		$cursor = $end;
		$done = ($cursor >= $total);

		// parent sync when done (variations)
		if ($done && !empty($job['parent_ids']) && is_array($job['parent_ids'])) {
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
			delete_option($key);
		} else {
			update_option($key, $job, false);
		}

		wp_send_json_success(array(
			'done'      => $done,
			'processed' => $cursor,
			'total'     => $total,
			'applied'   => $applied,
			'errors'    => $errors,
			'message'   => $done ? __('Import completed.', 'wsh-wcbpm') : __('Import batch processed.', 'wsh-wcbpm'),
		));
	}

	/* --------------------------
	 * Storage for import items
	 * -------------------------- */
	protected static function store_import_items(array $items, array $options): string
	{
		$import_id = wp_generate_password(20, false, false) . '_' . time();
		$key = self::IMPORT_OPTION_PREFIX . preg_replace('/[^a-zA-Z0-9_\-]/', '', $import_id);

		$payload = array(
			'created_at' => time(),
			'items'      => $items,
			'options'    => $options,
		);

		update_option($key, $payload, false);

		return $import_id;
	}

	protected static function load_import_items(string $import_id)
	{
		$key = self::IMPORT_OPTION_PREFIX . preg_replace('/[^a-zA-Z0-9_\-]/', '', $import_id);
		$data = get_option($key);

		if (!is_array($data) || empty($data['created_at'])) return null;

		if (time() - (int)$data['created_at'] > self::IMPORT_TTL_SECONDS) {
			delete_option($key);
			return null;
		}

		return $data;
	}

	/* --------------------------
	 * CSV parsing + normalization
	 * -------------------------- */
	protected static function parse_csv_file(string $path): array
	{
		$fh = fopen($path, 'r');
		if (!$fh) {
			return array('success' => false, 'message' => __('Unable to read file.', 'wsh-wcbpm'));
		}

		// Read first non-empty line for delimiter detection
		$firstLine = '';

		while (!feof($fh)) {
			$line = fgets($fh);
			if ($line === false) break;
			$trim = trim((string)$line);
			if ($trim !== '') { $firstLine = $trim; break; }
		}

		if ($firstLine === '') {
			fclose($fh);
			return array('success' => false, 'message' => __('CSV is empty.', 'wsh-wcbpm'));
		}

		$delimiter = self::detect_delimiter($firstLine);

		// rewind
		fseek($fh, 0);

		$header = null;
		$items  = array();
		$total_rows = 0;

		while (!feof($fh)) {
			$row = fgetcsv($fh, 0, $delimiter);
			if ($row === false) break;

			// remove BOM on first field (header row)
			if ($header === null && isset($row[0])) {
				$row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$row[0]);
			}

			// skip empty row
			$allEmpty = true;
			foreach ($row as $c) {
				if (trim((string)$c) !== '') { $allEmpty = false; break; }
			}
			if ($allEmpty) continue;

			$total_rows++;

			if ($header === null) {
				$header = array_map(function ($h) {
					return strtolower(trim((string)$h));
				}, $row);

				$has_pid = in_array('product_id', $header, true);
				$has_sku = in_array('sku', $header, true);

				if (!$has_pid && !$has_sku) {
					fclose($fh);
					return array('success' => false, 'message' => __('CSV header must include "product_id" or "sku".', 'wsh-wcbpm'));
				}

				// pricing columns are optional; if missing, they won't be changed.
				continue;
			}

			$assoc = array();
			foreach ($header as $idx => $key) {
				if ($key === '') continue;
				$assoc[$key] = isset($row[$idx]) ? trim((string)$row[$idx]) : '';
			}

			$item = array();

			// product_id / sku (either may exist)
			if (array_key_exists('product_id', $assoc)) {
				$item['product_id'] = (int)trim((string)$assoc['product_id']);
			} else {
				$item['product_id'] = 0;
			}

			if (array_key_exists('sku', $assoc)) {
				$item['sku'] = sanitize_text_field((string)$assoc['sku']);
			} else {
				$item['sku'] = '';
			}

			// only set keys if present in header
			if (array_key_exists('regular_price', $assoc)) {
				$item['regular_price'] = self::normalize_price_or_null($assoc['regular_price']);
			}
			if (array_key_exists('sale_price', $assoc)) {
				$item['sale_price'] = self::normalize_price_or_null($assoc['sale_price']);
			}

			$items[] = $item;
		}

		fclose($fh);

		return array(
			'success'   => true,
			'items'     => $items,
			'delimiter' => $delimiter,
			'stats'     => array(
				'total_rows' => $total_rows,
			),
		);
	}

	protected static function detect_delimiter(string $line): string
	{
		$sc = substr_count($line, ';');
		$cm = substr_count($line, ',');
		$tb = substr_count($line, "\t");

		if ($tb > $sc && $tb > $cm) return "\t";
		if ($sc >= $cm) return ';';
		return ',';
	}

	protected static function normalize_price_or_null(string $raw)
	{
		$raw = trim((string)$raw);
		if ($raw === '') return null;

		// allow "12,34" -> "12.34"
		$raw = str_replace(array(' ', "\xC2\xA0"), '', $raw);
		$raw = str_replace(',', '.', $raw);

		// keep only digits/dot/minus
		$raw = preg_replace('/[^0-9\.\-]/', '', $raw);

		if ($raw === '' || !is_numeric($raw)) return null;

		return (float)$raw;
	}
}
