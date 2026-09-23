<?php
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Admin UI (FREE):
 * - Wizard-like flow (Filters -> Update method -> Preview -> Apply)
 * - Undo last update
 * - CSV template export (link/button)
 * - CSV export trenutnih cena za trenutno izabrane filtere (bonus FREE)
 *
 * Dependencies:
 * - WSH_WCBPM_Filter_Engine
 * - WSH_WCBPM_Dry_Run
 * - WSH_WCBPM_Undo_Manager
 * - WSH_WCBPM_CSV_Export
 */
class WSH_WCBPM_Admin_UI
{

	const MENU_SLUG = 'wsh-wcbpm';

	const LICENSE_NONCE_ACTION = 'wsh_wcbpm_license';
	const LICENSE_ACTIVATE_ACTION = 'wsh_wcbpm_license_activate';
	const LICENSE_DEACTIVATE_ACTION = 'wsh_wcbpm_license_deactivate';
	const LICENSE_VERIFY_ACTION = 'wsh_wcbpm_license_verify';

	// Admin-post actions for CSV
	const EXPORT_NONCE_ACTION     = 'wsh_wcbpm_export';
	const EXPORT_TEMPLATE_ACTION  = 'wsh_wcbpm_export_template';
	const EXPORT_CURRENT_ACTION   = 'wsh_wcbpm_export_current';

	public static function init()
	{
		add_action('admin_menu', array(__CLASS__, 'register_menu'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));

		// AJAX.
		add_action('wp_ajax_wsh_wcbpm_dry_run', array(__CLASS__, 'ajax_dry_run'));
		add_action('wp_ajax_wsh_wcbpm_apply', array(__CLASS__, 'ajax_apply'));
		add_action('wp_ajax_wsh_wcbpm_undo_last', array(__CLASS__, 'ajax_undo_last'));

		// admin-post CSV export handlers
		add_action('admin_post_' . self::EXPORT_TEMPLATE_ACTION, array(__CLASS__, 'handle_export_template'));
		add_action('admin_post_' . self::EXPORT_CURRENT_ACTION, array(__CLASS__, 'handle_export_current'));

		add_action('admin_post_' . self::LICENSE_ACTIVATE_ACTION, array(__CLASS__, 'handle_license_activate'));
		add_action('admin_post_' . self::LICENSE_DEACTIVATE_ACTION, array(__CLASS__, 'handle_license_deactivate'));
		add_action('admin_post_' . self::LICENSE_VERIFY_ACTION, array(__CLASS__, 'handle_license_verify'));

		//PRO history restore
		add_action('wp_ajax_wsh_wcbpm_restore_batch', array(__CLASS__, 'ajax_restore_batch'));
		add_action('wp_ajax_wsh_wcbpm_delete_batch', array(__CLASS__, 'ajax_delete_batch'));

		//PRO Batch update
		add_action('wp_ajax_wsh_wcbpm_job_start_apply', array(__CLASS__, 'ajax_job_start_apply'));
		add_action('wp_ajax_wsh_wcbpm_job_step_apply', array(__CLASS__, 'ajax_job_step_apply'));

		//PRO Batch respore
		add_action('wp_ajax_wsh_wcbpm_job_start_restore', array(__CLASS__, 'ajax_job_start_restore'));
		add_action('wp_ajax_wsh_wcbpm_job_step_restore', array(__CLASS__, 'ajax_job_step_restore'));

		//PRO presets functions
		add_action('wp_ajax_wsh_wcbpm_preset_save', array(__CLASS__, 'ajax_preset_save'));
		add_action('wp_ajax_wsh_wcbpm_preset_get', array(__CLASS__, 'ajax_preset_get'));
		add_action('wp_ajax_wsh_wcbpm_preset_delete', array(__CLASS__, 'ajax_preset_delete'));

		//PRO Atts select
		add_action('wp_ajax_wsh_wcbpm_attr_terms', array(__CLASS__, 'ajax_attr_terms'));
	}

	public static function register_menu()
	{
		add_submenu_page(
			'woocommerce',
			__('Bulk Price Manager', 'wsh-wcbpm'),
			__('Bulk Price Manager', 'wsh-wcbpm'),
			'manage_woocommerce',
			self::MENU_SLUG,
			array(__CLASS__, 'render_page')
		);
	}

	public static function enqueue_assets($hook)
	{

		// Only our page.
		if (false === strpos((string) $hook, self::MENU_SLUG)) {
			return;
		}

		$ver = defined('WSH_WCBPM_VERSION') ? WSH_WCBPM_VERSION : '1.0.0';

		// WP base styles (cards/tables)
		wp_enqueue_style('wp-components');

		/**
		 * ✅ WooCommerce enhanced selects (SelectWoo + styling)
		 * Ovo daje moderni multi-select sa search kao na slici.
		 */
		if (function_exists('WC')) {
			wp_enqueue_script('wc-enhanced-select');
			wp_enqueue_style('woocommerce_admin_styles');
		} else {
			// Fallback (ako neko otvori bez WC, iako plugin realno zavisi od WC)
			wp_enqueue_script('selectWoo');
			wp_enqueue_style('selectWoo');
		}

		/**
		 * ✅ Load our real JS file (NE inline mega string)
		 * Path: /assets/admin-ui.js
		 */
		$js_url = plugins_url('assets/admin-ui.js?v=' . time(), dirname(__FILE__) . '/../wsh-woocommerce-bulk-price-manager.php');

		wp_enqueue_script(
			'wsh-wcbpm-admin',
			$js_url,
			array('jquery', function_exists('WC') ? 'wc-enhanced-select' : 'selectWoo'),
			$ver,
			true
		);

		$nonce_ajax   = wp_create_nonce('wsh_wcbpm_admin');
		$nonce_export = wp_create_nonce(self::EXPORT_NONCE_ACTION);

		// Export URLs (admin-post).
		$template_url = add_query_arg(
			array(
				'action'   => self::EXPORT_TEMPLATE_ACTION,
				'_wpnonce' => $nonce_export,
			),
			admin_url('admin-post.php')
		);

		$current_base_url = add_query_arg(
			array(
				'action'   => self::EXPORT_CURRENT_ACTION,
				'_wpnonce' => $nonce_export,
			),
			admin_url('admin-post.php')
		);

		// ✅ Only small config inline (safe)
		$config = array(
			'ajaxurl'              => admin_url('admin-ajax.php'),
			'nonce'                => $nonce_ajax,
			'exportTemplateUrl'    => $template_url,
			'exportCurrentBaseUrl' => $current_base_url,
			'i18n'                 => array(
				'no_products' => __('There are no products matching the filters.', 'wsh-wcbpm'),
				'ajax_error'  => __('Server error (AJAX).', 'wsh-wcbpm'),
			),
			'pro_active' => class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active(),
		);

		wp_add_inline_script(
			'wsh-wcbpm-admin',
			'window.WSH_WCBPM = ' . wp_json_encode($config) . ';',
			'before'
		);

		// (Optional) tiny CSS tweak: make selects wider + nicer spacing
		$css = '
			.wsh-wcbpm .select2-container { min-width: 420px; }
			.wsh-wcbpm .select2-selection--multiple { min-height: 36px; padding-top: 2px; }
		';
		wp_add_inline_style('woocommerce_admin_styles', $css);
	}

	public static function handle_license_activate()
	{
		if (! current_user_can('manage_woocommerce')) wp_die(esc_html__('You do not have permission.', 'wsh-wcbpm'));
		check_admin_referer(self::LICENSE_NONCE_ACTION);

		$key = isset($_POST['wsh_wcbpm_license_key']) ? sanitize_text_field(wp_unslash($_POST['wsh_wcbpm_license_key'])) : '';

		if (! class_exists('WSH_WCBPM_License')) {
			wp_die(esc_html__('License module is not loaded.', 'wsh-wcbpm'));
		}

		$result = WSH_WCBPM_License::activate($key);
		$msg    = isset($result['message']) ? (string) $result['message'] : '';

		$redirect = add_query_arg(
			array(
				'page' => self::MENU_SLUG,
				'wsh_license_notice' => rawurlencode($msg),
				'wsh_license_success' => ! empty($result['success']) ? '1' : '0',
			),
			admin_url('admin.php')
		);

		wp_safe_redirect($redirect . '#wsh-wcbpm-license');
		exit;
	}

	public static function handle_license_deactivate()
	{
		if (! current_user_can('manage_woocommerce')) wp_die(esc_html__('You do not have permission.', 'wsh-wcbpm'));
		check_admin_referer(self::LICENSE_NONCE_ACTION);

		if (! class_exists('WSH_WCBPM_License')) {
			wp_die(esc_html__('License module is not loaded.', 'wsh-wcbpm'));
		}

		$result = WSH_WCBPM_License::deactivate();
		$msg    = isset($result['message']) ? (string) $result['message'] : '';

		$redirect = add_query_arg(
			array(
				'page' => self::MENU_SLUG,
				'wsh_license_notice' => rawurlencode($msg),
				'wsh_license_success' => ! empty($result['success']) ? '1' : '0',
			),
			admin_url('admin.php')
		);

		wp_safe_redirect($redirect . '#wsh-wcbpm-license');
		exit;
	}

	public static function handle_license_verify()
	{
		if (! current_user_can('manage_woocommerce')) wp_die(esc_html__('You do not have permission.', 'wsh-wcbpm'));
		check_admin_referer(self::LICENSE_NONCE_ACTION);

		if (! class_exists('WSH_WCBPM_License')) {
			wp_die(esc_html__('License module is not loaded.', 'wsh-wcbpm'));
		}

		$result = WSH_WCBPM_License::verify(true);
		$msg    = isset($result['message']) ? (string) $result['message'] : '';

		$redirect = add_query_arg(
			array(
				'page' => self::MENU_SLUG,
				'wsh_license_notice' => rawurlencode($msg),
				'wsh_license_success' => ! empty($result['success']) ? '1' : '0',
			),
			admin_url('admin.php')
		);

		wp_safe_redirect($redirect . '#wsh-wcbpm-license');
		exit;
	}

	public static function render_page()
	{
		if (! current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'wsh-wcbpm'));
		}

		if (isset($_GET['wsh_license_notice'])) {
			$success = isset($_GET['wsh_license_success']) && '1' === (string) $_GET['wsh_license_success'];
			$cls = $success ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr($cls) . ' is-dismissible"><p>' .
				esc_html(wp_unslash($_GET['wsh_license_notice'])) .
				'</p></div>';
		}

		$cats = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
		$tags = get_terms(array('taxonomy' => 'product_tag', 'hide_empty' => false));

		$attr_taxonomies = function_exists('wc_get_attribute_taxonomies') ? wc_get_attribute_taxonomies() : array();

		$undo_available = class_exists('WSH_WCBPM_Undo_Manager') ? WSH_WCBPM_Undo_Manager::has_snapshot() : false;
		$csv_available  = class_exists('WSH_WCBPM_CSV_Export');
	?>
		<div class="wrap wsh-wcbpm">
			<h1><?php esc_html_e('WSH Bulk Price Manager', 'wsh-wcbpm'); ?></h1>

			<div id="wsh-wcbpm-notices"></div>

			<div class="notice notice-info">
				<p>
					<strong><?php esc_html_e('FREE version:', 'wsh-wcbpm'); ?></strong>
					<?php esc_html_e('Preview/Dry Run + Undo last update. Filters by category, tag and attributes.', 'wsh-wcbpm'); ?>
				</p>
			</div>

			<!-- Main params -->
			<div class="card" style="max-width: 1100px; padding: 16px;">
				<h2 style="margin-top:0;"><?php esc_html_e('1) Select products (filters)', 'wsh-wcbpm'); ?></h2>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e('Categories', 'wsh-wcbpm'); ?></th>
							<td>
								<select
									id="wsh_filter_categories"
									class="wc-enhanced-select"
									multiple="multiple"
									style="min-width:420px;"
									data-placeholder="<?php esc_attr_e('Search categories…', 'wsh-wcbpm'); ?>">
									<option></option>
									<?php if (! empty($cats) && ! is_wp_error($cats)) : ?>
										<?php foreach ($cats as $c) : ?>
											<option value="<?php echo esc_attr((string) $c->term_id); ?>"><?php echo esc_html($c->name); ?></option>
										<?php endforeach; ?>
									<?php endif; ?>
								</select>
								<p class="description"><?php esc_html_e('If you select nothing, all products will be included.', 'wsh-wcbpm'); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e('Tags', 'wsh-wcbpm'); ?></th>
							<td>
								<select
									id="wsh_filter_tags"
									class="wc-enhanced-select"
									multiple="multiple"
									style="min-width:420px;"
									data-placeholder="<?php esc_attr_e('Search tags…', 'wsh-wcbpm'); ?>">
									<option></option>
									<?php if (! empty($tags) && ! is_wp_error($tags)) : ?>
										<?php foreach ($tags as $t) : ?>
											<option value="<?php echo esc_attr((string) $t->term_id); ?>"><?php echo esc_html($t->name); ?></option>
										<?php endforeach; ?>
									<?php endif; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e('Attribute', 'wsh-wcbpm'); ?></th>
							<td style="display:flex; gap:10px; align-items:flex-start; flex-wrap:wrap;">

								<select id="wsh_attr_tax" style="min-width:220px;">
									<option value=""><?php esc_html_e('— Select attribute —', 'wsh-wcbpm'); ?></option>
									<?php
									if (! empty($attr_taxonomies)) :
										foreach ($attr_taxonomies as $a) :
											$tax = wc_attribute_taxonomy_name($a->attribute_name);
									?>
											<option value="<?php echo esc_attr($tax); ?>">
												<?php echo esc_html($a->attribute_label ? $a->attribute_label : $a->attribute_name); ?>
											</option>
									<?php
										endforeach;
									endif;
									?>
								</select>

								<?php if ( class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active() ) : ?>
									<select
										id="wsh_attr_terms"
										class="wc-enhanced-select"
										multiple="multiple"
										style="min-width:420px;"
										data-placeholder="<?php esc_attr_e('Select values…', 'wsh-wcbpm'); ?>"
									></select>

									<p class="description" style="flex-basis:100%; margin-top:0;">
										<?php esc_html_e('Select one or more attribute values.', 'wsh-wcbpm'); ?>
									</p>
								<?php else : ?>
									<input
										id="wsh_attr_term"
										type="text"
										class="regular-text"
										placeholder="<?php esc_attr_e('Value (e.g. red or 123 term_id)', 'wsh-wcbpm'); ?>"
									/>
									<p class="description" style="flex-basis:100%; margin-top:0;">
										<?php esc_html_e('Enter a slug or term_id (e.g. red / large).', 'wsh-wcbpm'); ?>
									</p>
								<?php endif; ?>

							</td>
						</tr>

						<?php if ( class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active() ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e('Filter logic', 'wsh-wcbpm'); ?></th>
							<td>
								<label>
									<input type="checkbox" id="wsh_filters_or" />
									<?php esc_html_e('Match ANY selected filters (OR). If unchecked, plugin matches ALL (AND).', 'wsh-wcbpm'); ?>
								</label>
							</td>
						</tr>
						<?php endif; ?>
						
						<tr>
							<th scope="row"><?php esc_html_e('Variations', 'wsh-wcbpm'); ?></th>
							<td>
								<label>
									<input type="checkbox" id="wsh_include_variations" checked />
									<?php esc_html_e('Include variations (variable products)', 'wsh-wcbpm'); ?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e('Which prices to update', 'wsh-wcbpm'); ?></th>
							<td>
								<select id="wsh_price_scope">
									<option value="both"><?php esc_html_e('Regular + Sale', 'wsh-wcbpm'); ?></option>
									<option value="regular"><?php esc_html_e('Regular only', 'wsh-wcbpm'); ?></option>
									<option value="sale"><?php esc_html_e('Sale only', 'wsh-wcbpm'); ?></option>
									<option value="sale_from_regular"><?php esc_html_e('Sale from Regular (discount) PRO', 'wsh-wcbpm'); ?></option>
								</select>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Buttons -->
			<div class="card" style="max-width: 1100px; padding: 16px; margin-top:16px;">
				<h2 style="margin-top:0;"><?php esc_html_e('2) Price change', 'wsh-wcbpm'); ?></h2>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e('Method', 'wsh-wcbpm'); ?></th>
							<td>
								<select id="wsh_update_method">
									<option value="percent"><?php esc_html_e('Percentage (%)', 'wsh-wcbpm'); ?></option>
									<option value="fixed"><?php esc_html_e('Fixed amount', 'wsh-wcbpm'); ?></option>
								</select>
								&nbsp;&nbsp;
								<select id="wsh_update_action">
									<option value="decrease"><?php esc_html_e('Decrease', 'wsh-wcbpm'); ?></option>
									<option value="increase"><?php esc_html_e('Increase', 'wsh-wcbpm'); ?></option>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e('Value', 'wsh-wcbpm'); ?></th>
							<td>
								<input type="number" id="wsh_update_value" step="0.01" class="regular-text" placeholder="<?php esc_attr_e('e.g. 10 or 5.99', 'wsh-wcbpm'); ?>" />
								<p class="description"><?php esc_html_e('For percentage enter 10 for 10%. For fixed amount enter 5.99.', 'wsh-wcbpm'); ?></p>
							</td>
						</tr>

						<?php if (class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active()) : ?>
							<tr>
								<th scope="row"><?php esc_html_e('PRO: Performance mode', 'wsh-wcbpm'); ?></th>
								<td>
									<label style="display:flex; align-items:center; gap:8px;">
										<input type="checkbox" id="wsh_perf_mode" />
										<?php esc_html_e('Process in batches (recommended for large catalogs)', 'wsh-wcbpm'); ?>
									</label>

									<div style="margin-top:8px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
										<label>
											<?php esc_html_e('Batch size:', 'wsh-wcbpm'); ?>
											<input type="number" id="wsh_batch_size" value="200" min="50" max="2000" step="50" style="width:120px;" />
										</label>
										<p class="description" style="margin:0;">
											<?php esc_html_e('Start with 200. Increase if server is fast.', 'wsh-wcbpm'); ?>
										</p>
									</div>
								</td>
							</tr>
						<?php endif; ?>


						<tr>
							<th scope="row"><?php esc_html_e('Rounding', 'wsh-wcbpm'); ?></th>
							<td>
								<select id="wsh_rounding">
									<option value="none"><?php esc_html_e('None', 'wsh-wcbpm'); ?></option>
									<option value="int"><?php esc_html_e('Whole number', 'wsh-wcbpm'); ?></option>
									<option value="99"><?php esc_html_e('To .99', 'wsh-wcbpm'); ?></option>
								</select>
							</td>
						</tr>
					</tbody>
				</table>

				<p>
					<button class="button button-primary" id="wsh-btn-dry-run"><?php esc_html_e('Preview / Dry run', 'wsh-wcbpm'); ?></button>

					<button class="button button-secondary" id="wsh-btn-undo" <?php disabled(! $undo_available); ?>>
						<?php esc_html_e('Undo last update', 'wsh-wcbpm'); ?>
					</button>

					<?php if ($csv_available) : ?>
						<button class="button" id="wsh-btn-export-template"><?php esc_html_e('CSV Template (for import)', 'wsh-wcbpm'); ?></button>
						<button class="button" id="wsh-btn-export-current"><?php esc_html_e('Export current targets (CSV)', 'wsh-wcbpm'); ?></button>
					<?php endif; ?>
				</p>

				<div id="wsv-wcbpm-apply-confirm" style="display:none; margin-top:10px; padding:10px; border:1px solid #ccd0d4; background:#fff;">
					<p style="margin:0 0 10px 0;"><strong><?php esc_html_e('Are you sure?', 'wsh-wcbpm'); ?></strong> <?php esc_html_e('This will update prices in the database.', 'wsh-wcbpm'); ?></p>
					<button class="button button-primary" id="wsh-btn-apply-confirm-yes"><?php esc_html_e('Yes, apply', 'wsh-wcbpm'); ?></button>
					<button class="button" id="wsh-btn-apply-confirm-no"><?php esc_html_e('No', 'wsh-wcbpm'); ?></button>
				</div>

				<!-- PRO performance progress bar -->
				<div id="wsh-perf-progress" style="display:none; margin-top:12px; padding:12px; border:1px solid #ccd0d4; background:#fff;">
					<div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
						<strong><?php esc_html_e('Processing…', 'wsh-wcbpm'); ?></strong>
						<span id="wsh-perf-progress-text">0%</span>
					</div>
					<div style="height:10px; background:#e5e5e5; border-radius:999px; overflow:hidden; margin-top:8px;">
						<div id="wsh-perf-progress-bar" style="height:10px; width:0%; background:#2271b1;"></div>
					</div>
					<p class="description" id="wsh-perf-progress-meta" style="margin:8px 0 0 0;">
						<?php esc_html_e('Preparing…', 'wsh-wcbpm'); ?>
					</p>
				</div>

				<!-- PRO Scheduling -->
				<?php $is_pro = class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active(); ?>
				<?php if ( $is_pro ) : ?>
				<hr style="margin:12px 0;" />
				<h3 style="margin:0 0 6px 0;"><?php esc_html_e('Scheduling (PRO)', 'wsh-wcbpm'); ?></h3>

				<!--
				<table class="form-table" role="presentation" style="display: none;">
					<tbody>
						<tr>
						<th scope="row"><?php esc_html_e('Run', 'wsh-wcbpm'); ?></th>
						<td>
							<select id="wsh_schedule_mode">
							<option value="now"><?php esc_html_e('Now', 'wsh-wcbpm'); ?></option>
							<option value="later"><?php esc_html_e('Schedule for later', 'wsh-wcbpm'); ?></option>
							</select>
						</td>
						</tr>

						<tr id="wsh_schedule_datetime_row" style="display:none;">
						<th scope="row"><?php esc_html_e('Date & time', 'wsh-wcbpm'); ?></th>
						<td>
							<input type="datetime-local" id="wsh_schedule_datetime" />
							<p class="description" style="margin-top:6px;">
							<?php esc_html_e('Uses your WordPress site timezone.', 'wsh-wcbpm'); ?>
							</p>
						</td>
						</tr>

						<tr>
						<th scope="row"><?php esc_html_e('Batch size', 'wsh-wcbpm'); ?></th>
						<td>
							<input type="number" id="wsh_schedule_batch_size" value="200" min="50" max="2000" step="10" />
							<p class="description" style="margin-top:6px;">
							<?php esc_html_e('Recommended for large catalogs. Start with 200. Increase if server is fast.', 'wsh-wcbpm'); ?>
							</p>
						</td>
						</tr>
					</tbody>
				</table>

				<p style="display: none;">
					<button class="button button-secondary" id="wsh-btn-schedule">
						<?php // esc_html_e('Schedule job', 'wsh-wcbpm'); ?>
					</button>
				</p> -->

				<div id="wsh-scheduled-jobs" style="margin-top:14px;">
					<h3 style="margin:0 0 10px 0; font-size:14px;"><?php esc_html_e('Scheduled jobs', 'wsh-wcbpm'); ?></h3>

					<div id="wsh-jobs-empty" class="notice notice-info inline" style="display:none; padding:10px 12px; margin:0 0 12px 0;">
						<p style="margin:0;"><?php esc_html_e('No scheduled jobs yet.', 'wsh-wcbpm'); ?></p>
					</div>

					<div id="wsh-jobs-list" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(360px, 1fr)); gap:12px;"></div>

					<p class="description" style="margin-top:10px;">
						<?php esc_html_e('Tip: jobs are stored in wp_options (wsh_wcbpm_sched_job_*). You can cancel scheduled jobs anytime.', 'wsh-wcbpm'); ?>
					</p>
				</div>

				<!-- Details Modal -->
				<div id="wsh-job-modal" style="display:none; position:fixed; inset:0; z-index:100000; background:rgba(0,0,0,.55);">
					<div style="background:#fff; max-width:860px; width:calc(100% - 40px); margin:40px auto; border-radius:10px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,.25);">
						<div style="display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid #e5e5e5;">
							<div>
								<div id="wsh-job-modal-title" style="font-size:14px; font-weight:700; margin:0;"></div>
								<div id="wsh-job-modal-sub" class="description" style="margin-top:4px;"></div>
							</div>
							<button type="button" class="button" id="wsh-job-modal-close"><?php esc_html_e('Close', 'wsh-wcbpm'); ?></button>
						</div>

						<div style="padding:16px;">
							<div id="wsh-job-modal-body"></div>
						</div>

						<div style="padding:14px 16px; border-top:1px solid #e5e5e5; display:flex; gap:10px; justify-content:flex-end;">
							<button type="button" class="button" id="wsh-job-modal-run"><?php esc_html_e('Run now', 'wsh-wcbpm'); ?></button>
							<button type="button" class="button button-link-delete" id="wsh-job-modal-cancel"><?php esc_html_e('Cancel job', 'wsh-wcbpm'); ?></button>
						</div>
					</div>
				</div>

				<!-- Schedule Modal -->
				<div id="wsh-schedule-modal" style="display:none; position:fixed; inset:0; z-index:100000; background:rgba(0,0,0,.55);">
					<div style="background:#fff; max-width:520px; width:calc(100% - 40px); margin:70px auto; border-radius:12px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,.25);">
						<div style="display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid #e5e5e5;">
							<div>
								<div style="font-size:14px; font-weight:700; margin:0;"><?php esc_html_e('Schedule price update', 'wsh-wcbpm'); ?></div>
								<div class="description" style="margin-top:4px;">
									<?php esc_html_e('Choose when to run this exact Preview update.', 'wsh-wcbpm'); ?>
								</div>
							</div>
							<button type="button" class="button" id="wsh-schedule-close"><?php esc_html_e('Close', 'wsh-wcbpm'); ?></button>
						</div>

						<div style="padding:16px;">
							<table class="form-table" role="presentation" style="margin:0;">
								<tbody>
									<tr>
										<th scope="row" style="padding-left:0;"><?php esc_html_e('Run at', 'wsh-wcbpm'); ?></th>
										<td style="padding-left:0;">
											<input type="datetime-local" id="wsh-schedule-datetime" class="regular-text" />
											<p class="description" style="margin:6px 0 0 0;">
												<?php esc_html_e('Uses your WordPress site timezone.', 'wsh-wcbpm'); ?>
											</p>
										</td>
									</tr>

									<tr>
										<th scope="row" style="padding-left:0;"><?php esc_html_e('Batch size', 'wsh-wcbpm'); ?></th>
										<td style="padding-left:0;">
											<input type="number" id="wsh-schedule-batch" value="200" min="50" max="2000" step="50" />
											<p class="description" style="margin:6px 0 0 0;">
												<?php esc_html_e('Recommended: 200. Increase if server is fast.', 'wsh-wcbpm'); ?>
											</p>
										</td>
									</tr>

									<tr>
										<th scope="row" style="padding-left:0;"><?php esc_html_e('Summary', 'wsh-wcbpm'); ?></th>
										<td style="padding-left:0;">
											<div id="wsh-schedule-summary" class="notice notice-info inline" style="margin:0; padding:10px 12px;">
												<p style="margin:0;"><?php esc_html_e('Preview must be generated first.', 'wsh-wcbpm'); ?></p>
											</div>
										</td>
									</tr>

								</tbody>
							</table>
						</div>

						<div style="padding:14px 16px; border-top:1px solid #e5e5e5; display:flex; gap:10px; justify-content:flex-end;">
							<button type="button" class="button" id="wsh-schedule-cancel"><?php esc_html_e('Cancel', 'wsh-wcbpm'); ?></button>
							<button type="button" class="button button-primary" id="wsh-schedule-confirm"><?php esc_html_e('Schedule job', 'wsh-wcbpm'); ?></button>
						</div>
					</div>
				</div>
				<?php endif; ?>
			</div>

			<!-- PREVIEW results -->
			<div class="card" id="wsh-wcbpm-preview-wrap" style="max-width: 1100px; padding: 16px; margin-top:16px; display:none;">
				<h2 style="margin-top:0;"><?php esc_html_e('3) Preview results', 'wsh-wcbpm'); ?></h2>
				<p class="description">
					<?php esc_html_e('Matched products count:', 'wsh-wcbpm'); ?>
					<strong id="wsh-wcbpm-preview-count">0</strong>
				</p>

				<table class="widefat striped">
					<thead>
						<tr>
							<th style="width:90px;"><?php esc_html_e('ID', 'wsh-wcbpm'); ?></th>
							<th><?php esc_html_e('Product', 'wsh-wcbpm'); ?></th>
							<th style="width:110px; text-align:right;"><?php esc_html_e('Old Reg', 'wsh-wcbpm'); ?></th>
							<th style="width:110px; text-align:right;"><?php esc_html_e('Old Sale', 'wsh-wcbpm'); ?></th>
							<th style="width:110px; text-align:right;"><?php esc_html_e('New Reg', 'wsh-wcbpm'); ?></th>
							<th style="width:110px; text-align:right;"><?php esc_html_e('New Sale', 'wsh-wcbpm'); ?></th>
						</tr>
					</thead>
					<tbody id="wsh-wcbpm-preview-rows"></tbody>
				</table>

				<p style="margin-top:12px;">
					<button class="button button-primary" id="wsh-btn-apply"><?php esc_html_e('Apply (update prices)', 'wsh-wcbpm'); ?></button>
					<?php if ( class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active() ) : ?>
						<button type="button" class="button button-secondary" id="wsh-btn-schedule">
							<?php esc_html_e('Schedule', 'wsh-wcbpm'); ?>
						</button>
					<?php else : ?>
						<button type="button" class="button" id="wsh-btn-schedule" disabled title="<?php esc_attr_e('PRO feature - activate license to schedule.', 'wsh-wcbpm'); ?>">
							<?php esc_html_e('Schedule (PRO)', 'wsh-wcbpm'); ?>
						</button>
					<?php endif; ?>
				</p>
			</div>

			<!-- PRO version info -->
			<div class="notice notice-warning" style="max-width:1100px; margin-top:16px;">
				<p style="margin: 8px 0;">
					<strong><?php esc_html_e('PRO ideas:', 'wsh-wcbpm'); ?></strong>
					<?php esc_html_e('scheduling, full backup history, CSV/Excel import, brand filter, advanced rules engine.', 'wsh-wcbpm'); ?>
				</p>
			</div>

			<?php
			//PRO Import function
			$is_pro = class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active();
			if ( class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active() && class_exists('WSH_WCBPM_Preset_Manager') ) :
			?>
			<div class="card" id="wsh-wcbpm-import" style="max-width:1100px; padding:16px; margin-top:16px;">
				<h2 style="margin-top:0;"><?php esc_html_e('PRO: CSV/Excel Import', 'wsh-wcbpm'); ?></h2>

				<?php if ( ! $is_pro ) : ?>
					<div class="notice notice-warning" style="margin:10px 0 0 0;">
						<p style="margin:6px 0;"><?php esc_html_e('Activate your license to unlock CSV/Excel import.', 'wsh-wcbpm'); ?></p>
					</div>
				<?php else : ?>
					<p class="description">
						<?php esc_html_e(
							'Upload a CSV file exported from Excel. You must provide at least one product identifier per row: Product ID or SKU.',
							'wsh-wcbpm'
						); ?>
						<br />
						<?php esc_html_e(
							'Columns:',
							'wsh-wcbpm'
						); ?>
					</p>
					<ul class="description" style="margin-top:4px; padding-left:18px;">
						<li><strong>product_id</strong> — <?php esc_html_e('Optional if SKU is provided.', 'wsh-wcbpm'); ?></li>
						<li><strong>sku</strong> — <?php esc_html_e('Optional if Product ID is provided.', 'wsh-wcbpm'); ?></li>
						<li><strong>regular_price</strong> — <?php esc_html_e('Optional. Leave empty if you do not want to change the Regular price.', 'wsh-wcbpm'); ?></li>
						<li><strong>sale_price</strong> — <?php esc_html_e('Optional. Leave empty if you do not want to change the Sale price.', 'wsh-wcbpm'); ?></li>
					</ul>
					<p class="description" style="margin-top:6px;">
						<?php esc_html_e(
							'If both Product ID and SKU are provided, they must match the same product. Invalid rows will be skipped during import.',
							'wsh-wcbpm'
						); ?>
					</p>
					<p class="description" style="margin-top:8px;">
						<a
							href="<?php echo esc_url( add_query_arg(
								array(
									'action'   => 'wsh_wcbpm_export_template',
									'_wpnonce' => wp_create_nonce( WSH_WCBPM_Admin_UI::EXPORT_NONCE_ACTION ),
								),
								admin_url( 'admin-post.php' )
							) ); ?>"
							class="button button-secondary"
						>
							<?php esc_html_e( 'Download CSV template', 'wsh-wcbpm' ); ?>
						</a>
						<span style="margin-left:6px; color:#646970;">
							<?php esc_html_e( 'Use this template as a starting point for your import.', 'wsh-wcbpm' ); ?>
						</span>
					</p>

					<div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
						<input type="file" id="wsh_import_file" accept=".csv,text/csv" />
						<label style="display:flex; align-items:center; gap:8px;">
							<input type="checkbox" id="wsh_import_clear_sale_empty" />
							<?php esc_html_e('Clear sale if sale_price is empty', 'wsh-wcbpm'); ?>
						</label>

						<button class="button" id="wsh_import_preview_btn"><?php esc_html_e('Preview Import', 'wsh-wcbpm'); ?></button>
					</div>

					<div id="wsh-import-summary" class="description" style="margin-top:10px;"></div>

					<div id="wsh-import-preview-wrap" style="display:none; margin-top:12px;">
						<table class="widefat striped">
							<thead>
								<tr>
									<th style="width:90px;"><?php esc_html_e('ID', 'wsh-wcbpm'); ?></th>
									<th style="width:140px;"><?php esc_html_e('SKU', 'wsh-wcbpm'); ?></th>
									<th><?php esc_html_e('Product', 'wsh-wcbpm'); ?></th>
									<th style="width:110px; text-align:right;"><?php esc_html_e('Old Reg', 'wsh-wcbpm'); ?></th>
									<th style="width:110px; text-align:right;"><?php esc_html_e('Old Sale', 'wsh-wcbpm'); ?></th>
									<th style="width:110px; text-align:right;"><?php esc_html_e('New Reg', 'wsh-wcbpm'); ?></th>
									<th style="width:110px; text-align:right;"><?php esc_html_e('New Sale', 'wsh-wcbpm'); ?></th>
									<th style="width:160px;"><?php esc_html_e('Status', 'wsh-wcbpm'); ?></th>
								</tr>
							</thead>
							<tbody id="wsh-import-preview-rows"></tbody>
						</table>

						<p style="margin-top:12px;">
							<button class="button button-primary" id="wsh_import_apply_btn" disabled>
								<?php esc_html_e('Apply Import (batch)', 'wsh-wcbpm'); ?>
							</button>
						</p>
					</div>

					<input type="hidden" id="wsh_import_id" value="" />
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<!-- Presets data -->
			<?php if ( class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active() && class_exists('WSH_WCBPM_Preset_Manager') ) : ?>
			<?php $presets = WSH_WCBPM_Preset_Manager::list_presets(200); ?>
			<div class="card" id="wsh-wcbpm-presets" style="max-width:1100px; padding:16px; margin-top:16px;">
				<h2 style="margin-top:0;"><?php esc_html_e('PRO: Rule presets', 'wsh-wcbpm'); ?></h2>

				<div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
					<select id="wsh_preset_select" style="min-width:320px;">
						<option value=""><?php esc_html_e('— Select preset —', 'wsh-wcbpm'); ?></option>
						<?php foreach ($presets as $p) : ?>
							<option value="<?php echo esc_attr((string)$p['id']); ?>">
								<?php echo esc_html((string)$p['name']); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<button class="button" id="wsh_preset_load"><?php esc_html_e('Load', 'wsh-wcbpm'); ?></button>
					<button class="button button-primary" id="wsh_preset_run"><?php esc_html_e('Run (Preview)', 'wsh-wcbpm'); ?></button>
					<button class="button" id="wsh_preset_save"><?php esc_html_e('Save current as preset', 'wsh-wcbpm'); ?></button>
					<button class="button" id="wsh_preset_delete"><?php esc_html_e('Delete', 'wsh-wcbpm'); ?></button>
				</div>

				<p class="description" style="margin-top:10px;">
					<?php esc_html_e('Presets save filters + pricing rule + performance settings. Load to reuse, or Run to preview instantly.', 'wsh-wcbpm'); ?>
				</p>
			</div>
			<?php endif; ?>

			<!-- PRO version - restore -->
			<?php if (class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active()) : ?>
				<?php $history = class_exists('WSH_WCBPM_Undo_Manager') ? WSH_WCBPM_Undo_Manager::list_history(20) : array(); ?>
				<div class="card" style="max-width:1100px; padding:16px; margin-top:16px;">
					<h2 style="margin-top:0;"><?php esc_html_e('PRO: Restore history', 'wsh-wcbpm'); ?></h2>

					<?php if (empty($history)) : ?>
						<p class="description"><?php esc_html_e('No snapshots yet. Run an update to create restore points.', 'wsh-wcbpm'); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e('Date', 'wsh-wcbpm'); ?></th>
									<th><?php esc_html_e('Scope', 'wsh-wcbpm'); ?></th>
									<th><?php esc_html_e('Items', 'wsh-wcbpm'); ?></th>
									<th><?php esc_html_e('User', 'wsh-wcbpm'); ?></th>
									<th style="width:220px;"><?php esc_html_e('Actions', 'wsh-wcbpm'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($history as $h) : ?>
									<?php
									$user = !empty($h['created_by']) ? get_user_by('id', (int)$h['created_by']) : null;
									$user_label = $user ? $user->user_login : '—';
									?>
									<tr>
										<td><?php echo esc_html($h['created_at'] ?? ''); ?></td>
										<td><?php echo esc_html($h['scope'] ?? ''); ?></td>
										<td><?php echo esc_html((string)($h['items_count'] ?? '0')); ?></td>
										<td><?php echo esc_html($user_label); ?></td>
										<td>
											<button class="button wsh-restore-batch" data-batch="<?php echo esc_attr($h['batch_id']); ?>">
												<?php esc_html_e('Restore', 'wsh-wcbpm'); ?>
											</button>
											<button class="button wsh-delete-batch" data-batch="<?php echo esc_attr($h['batch_id']); ?>">
												<?php esc_html_e('Delete', 'wsh-wcbpm'); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p class="description" style="margin-top:10px;">
							<?php esc_html_e('Tip: For very large catalogs we will add batch restore (Performance mode).', 'wsh-wcbpm'); ?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

		</div>

		<?php
		$license_key    = class_exists('WSH_WCBPM_License') ? WSH_WCBPM_License::get_key() : '';
		$license_status = class_exists('WSH_WCBPM_License') ? WSH_WCBPM_License::get_status() : '';
		$license_exp    = class_exists('WSH_WCBPM_License') ? WSH_WCBPM_License::get_expires() : '';
		$is_active      = class_exists('WSH_WCBPM_License') ? WSH_WCBPM_License::is_active() : false;

		$nonce_license = wp_create_nonce(self::LICENSE_NONCE_ACTION);

		$activate_url = admin_url('admin-post.php');
		$deact_url    = admin_url('admin-post.php');
		$verify_url   = admin_url('admin-post.php');
		?>

		<div class="card" id="wsh-wcbpm-license" style="max-width:1100px; padding:16px; margin-top:16px;">
			<h2 style="margin-top:0;"><?php esc_html_e('License', 'wsh-wcbpm'); ?></h2>
			<?php
			if (isset($_GET['wsh_license_notice'])) {
				$success = isset($_GET['wsh_license_success']) && '1' === (string) $_GET['wsh_license_success'];
				$cls = $success ? 'notice-success' : 'notice-error';
				echo '<div class="notice ' . esc_attr($cls) . ' is-dismissible" style="margin:10px 0 0 0;"><p>' .
					esc_html(wp_unslash($_GET['wsh_license_notice'])) .
					'</p></div>';
			}
			?>

			<p class="description" style="margin-top:0;">
				<?php esc_html_e('Activate your license key to unlock PRO features.', 'wsh-wcbpm'); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e('License key', 'wsh-wcbpm'); ?></th>
						<td>
							<form method="post" action="<?php echo esc_url($activate_url); ?>" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
								<input type="hidden" name="action" value="<?php echo esc_attr(self::LICENSE_ACTIVATE_ACTION); ?>">
								<input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce_license); ?>">
								<input
									type="password"
									name="wsh_wcbpm_license_key"
									class="regular-text"
									style="min-width:420px;"
									value="<?php echo esc_attr($license_key); ?>"
									placeholder="<?php esc_attr_e('Enter your license key', 'wsh-wcbpm'); ?>" />
								<button type="submit" class="button button-primary"><?php esc_html_e('Activate', 'wsh-wcbpm'); ?></button>
							</form>

							<div style="margin-top:10px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
								<form method="post" action="<?php echo esc_url($verify_url); ?>">
									<input type="hidden" name="action" value="<?php echo esc_attr(self::LICENSE_VERIFY_ACTION); ?>">
									<input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce_license); ?>">
									<button type="submit" class="button"><?php esc_html_e('Verify', 'wsh-wcbpm'); ?></button>
								</form>

								<?php if ('' !== $license_key) : ?>
									<form method="post" action="<?php echo esc_url($deact_url); ?>">
										<input type="hidden" name="action" value="<?php echo esc_attr(self::LICENSE_DEACTIVATE_ACTION); ?>">
										<input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce_license); ?>">
										<button type="submit" class="button"><?php esc_html_e('Deactivate', 'wsh-wcbpm'); ?></button>
									</form>
								<?php endif; ?>

							</div>

							<p class="description" style="margin-top:10px;">
								<?php
								echo esc_html__('Status:', 'wsh-wcbpm') . ' ';
								if ($is_active) {
									echo '<strong style="color:#1d7f2f;">' . esc_html__('Active', 'wsh-wcbpm') . '</strong>';
								} else {
									echo '<strong style="color:#b32d2e;">' . esc_html__('Inactive', 'wsh-wcbpm') . '</strong>';
								}

								if ($license_status) {
									echo ' <span class="description">(' . esc_html($license_status) . ')</span>';
								}

								if ($license_exp) {
									echo ' <span class="description">— ' . esc_html__('Expires:', 'wsh-wcbpm') . ' ' . esc_html($license_exp) . '</span>';
								}
								?>
							</p>

							<?php if (! $is_active) : ?>
								<div class="notice notice-warning" style="margin:10px 0 0 0;">
									<p style="margin:6px 0;">
										<?php esc_html_e('PRO features are locked. Activate your license to unlock PRO.', 'wsh-wcbpm'); ?>
									</p>
								</div>
							<?php endif; ?>

						</td>
					</tr>
				</tbody>
			</table>
		</div> <!-- end #wsh-wcbpm-license card -->

		<!-- Licence form -->
		<div class="card" id="wsh-wcbpm-pro" style="max-width:1100px; padding:16px; margin-top:16px;">
			<div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap;">
				<div>
					<h2 style="margin-top:0;">
						<?php esc_html_e('Unlock PRO features', 'wsh-wcbpm'); ?>
						<span style="display:inline-block; margin-left:8px; padding:2px 8px; border-radius:999px; background:#fff5d6; color:#7a4b00; font-size:12px; font-weight:600;">
							<?php esc_html_e('PRO', 'wsh-wcbpm'); ?>
						</span>
					</h2>

					<p class="description" style="margin-top:-6px;">
						<?php esc_html_e('Upgrade to PRO to automate complex pricing changes and save hours on large catalogs.', 'wsh-wcbpm'); ?>
					</p>
				</div>

				<div style="text-align:right; min-width:260px;">
					<a
						class="button button-primary"
						href="<?php echo esc_url('https://wppluginspro.io/wsh-woocommerce-bulk-price-manager'); ?>"
						target="_blank"
						rel="noopener noreferrer"
						style="padding:6px 14px;">
						<?php esc_html_e('Buy license', 'wsh-wcbpm'); ?>
					</a>

					<p class="description" style="margin:8px 0 0 0;">
						<?php esc_html_e('After purchase, paste your license key above and click Activate.', 'wsh-wcbpm'); ?>
					</p>
				</div>
			</div>

			<hr style="margin:12px 0;" />

			<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:10px;">
				<div>
					<h3 style="margin:0 0 6px 0; font-size:14px;"><?php esc_html_e('What you get in PRO', 'wsh-wcbpm'); ?></h3>
					<ul style="margin:0; padding-left:18px;">
						<li><strong><?php esc_html_e('Smart Sale Price', 'wsh-wcbpm'); ?></strong> — <?php esc_html_e('Create/update Sale price based on Regular price (true discount logic).', 'wsh-wcbpm'); ?></li>
						<li><strong><?php esc_html_e('Advanced filters', 'wsh-wcbpm'); ?></strong> — <?php esc_html_e('Attribute term dropdowns, OR logic, and more targeting options.', 'wsh-wcbpm'); ?></li>
						<li><strong><?php esc_html_e('Scheduling', 'wsh-wcbpm'); ?></strong> — <?php esc_html_e('Run price updates now or schedule them for later.', 'wsh-wcbpm'); ?></li>
						<li><strong><?php esc_html_e('CSV/Excel import', 'wsh-wcbpm'); ?></strong> — <?php esc_html_e('Bulk import prices with validation and safe preview.', 'wsh-wcbpm'); ?></li>
					</ul>
				</div>

				<div>
					<h3 style="margin:0 0 6px 0; font-size:14px;"><?php esc_html_e('Safety & workflow', 'wsh-wcbpm'); ?></h3>
					<ul style="margin:0; padding-left:18px;">
						<li><strong><?php esc_html_e('Multiple restore points', 'wsh-wcbpm'); ?></strong> — <?php esc_html_e('Keep history, not just the last update.', 'wsh-wcbpm'); ?></li>
						<li><strong><?php esc_html_e('Rule presets', 'wsh-wcbpm'); ?></strong> — <?php esc_html_e('Save pricing rules and reuse them anytime.', 'wsh-wcbpm'); ?></li>
						<li><strong><?php esc_html_e('Performance mode', 'wsh-wcbpm'); ?></strong> — <?php esc_html_e('Batch processing for large catalogs.', 'wsh-wcbpm'); ?></li>
						<li><strong><?php esc_html_e('Priority support', 'wsh-wcbpm'); ?></strong> — <?php esc_html_e('Faster responses and help with setup.', 'wsh-wcbpm'); ?></li>
					</ul>

					<?php
					$is_pro = class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active();
					if ( $is_pro ) :
					?>
						<div style="margin-top:12px; padding:12px; border:1px solid #dcdcde; background:#f6f7f7; border-radius:8px;">
							<h4 style="margin:0 0 6px 0; font-size:13px;"><?php esc_html_e('PRO Support', 'wsh-wcbpm'); ?></h4>
							<p class="description" style="margin:0 0 10px 0;">
								<?php esc_html_e('Need help with setup or bulk pricing rules? PRO customers get priority support. Open a ticket and we’ll respond as fast as possible (business days).', 'wsh-wcbpm'); ?>
							</p>
							<a
								class="button button-primary"
								href="<?php echo esc_url('https://wppluginspro.io/ticketing'); ?>"
								target="_blank"
								rel="noopener noreferrer"
							>
								<?php esc_html_e('Open Support Ticket', 'wsh-wcbpm'); ?>
							</a>
						</div>
					<?php endif; ?>

				</div>
			</div>
		</div>
	<?php
	}

	public static function handle_export_template()
	{
		if (! current_user_can('manage_woocommerce')) wp_die(esc_html__("You don't have permission.", 'wsh-wcbpm'));
		check_admin_referer(self::EXPORT_NONCE_ACTION);
		if (! class_exists('WSH_WCBPM_CSV_Export')) wp_die(esc_html__('The CSV export module is not loaded.', 'wsh-wcbpm'));
		WSH_WCBPM_CSV_Export::export_template();
		exit;
	}

	public static function handle_export_current()
	{
		if (! current_user_can('manage_woocommerce')) wp_die(esc_html__("You don't have permission.", 'wsh-wcbpm'));
		check_admin_referer(self::EXPORT_NONCE_ACTION);
		if (! class_exists('WSH_WCBPM_CSV_Export')) wp_die(esc_html__('The CSV export module is not loaded.', 'wsh-wcbpm'));

		$filters = array();
		if (isset($_GET['filters'])) {
			$json = wp_unslash((string) $_GET['filters']);
			$decoded = json_decode($json, true);
			if (is_array($decoded)) $filters = $decoded;
		}

		WSH_WCBPM_CSV_Export::export_current_for_targets($filters);
		exit;
	}

	/**
	 * Send AJAX error in a UI-friendly way.
	 * - For validation / expected user errors -> HTTP 200 (so JS .done() can show message).
	 * - For real server errors -> use 4xx/5xx.
	 *
	 * @param string $message
	 * @param int    $http_status
	 * @return void
	 */
	protected static function send_ajax_error($message, $http_status = 200)
	{
		$payload = array('message' => (string) $message);

		// Validation / expected errors: keep 200 so frontend can show message via .done()
		if ((int) $http_status === 200) {
			wp_send_json_error($payload);
		}

		wp_send_json_error($payload, (int) $http_status);
	}

	public static function ajax_dry_run()
	{

		if (! current_user_can('manage_woocommerce')) {
			self::send_ajax_error(__("You don't have permission.", 'wsh-wcbpm'), 403);
		}

		check_ajax_referer('wsh_wcbpm_admin');

		$filters = (isset($_POST['filters']) && is_array($_POST['filters'])) ? (array) $_POST['filters'] : array();
		$update  = (isset($_POST['update']) && is_array($_POST['update'])) ? (array) $_POST['update'] : array();

		if (! class_exists('WSH_WCBPM_Dry_Run')) {
			self::send_ajax_error(__('Dry run module is not loaded.', 'wsh-wcbpm'), 500);
		}

		$result = WSH_WCBPM_Dry_Run::preview($filters, $update);

		if (! is_array($result) || empty($result['success'])) {
			$msg = (is_array($result) && ! empty($result['message']))
				? $result['message']
				: __('Error in preview.', 'wsh-wcbpm');

			// IMPORTANT: validation/user error => 200
			self::send_ajax_error($msg, 200);
		}

		wp_send_json_success(array(
			'rows'    => isset($result['rows']) ? (array) $result['rows'] : array(),
			'summary' => isset($result['summary']) ? (array) $result['summary'] : array(),
		));
	}

	public static function ajax_apply()
	{

		if (! current_user_can('manage_woocommerce')) {
			self::send_ajax_error(__("You don't have permission.", 'wsh-wcbpm'), 403);
		}

		check_ajax_referer('wsh_wcbpm_admin');

		$filters = (isset($_POST['filters']) && is_array($_POST['filters'])) ? (array) $_POST['filters'] : array();
		$update  = (isset($_POST['update']) && is_array($_POST['update'])) ? (array) $_POST['update'] : array();

		if (! class_exists('WSH_WCBPM_Dry_Run')) {
			self::send_ajax_error(__('Apply module is not loaded.', 'wsh-wcbpm'), 500);
		}

		$result = WSH_WCBPM_Dry_Run::apply($filters, $update);

		if (! is_array($result) || empty($result['success'])) {
			$msg = (is_array($result) && ! empty($result['message']))
				? $result['message']
				: __('Error in apply.', 'wsh-wcbpm');

			// IMPORTANT: validation/user error => 200
			self::send_ajax_error($msg, 200);
		}

		wp_send_json_success(array(
			'message' => isset($result['message']) ? (string) $result['message'] : __('Prices have been updated.', 'wsh-wcbpm'),
			'rows'    => isset($result['rows']) ? (array) $result['rows'] : array(),
			'summary' => isset($result['summary']) ? (array) $result['summary'] : array(),
		));
	}

	public static function ajax_undo_last()
	{

		if (! current_user_can('manage_woocommerce')) {
			self::send_ajax_error(__("You don't have permission.", 'wsh-wcbpm'), 403);
		}

		check_ajax_referer('wsh_wcbpm_admin');

		if (! class_exists('WSH_WCBPM_Undo_Manager')) {
			self::send_ajax_error(__('Undo module is not loaded.', 'wsh-wcbpm'), 500);
		}

		$result = WSH_WCBPM_Undo_Manager::restore();

		if (! is_array($result) || empty($result['success'])) {
			$msg = (is_array($result) && ! empty($result['message']))
				? $result['message']
				: __('Error in undo.', 'wsh-wcbpm');

			// Undo fail is also a "user-facing" error -> keep 200 so UI shows it
			self::send_ajax_error($msg, 200);
		}

		wp_send_json_success(array(
			'message' => isset($result['message']) ? (string) $result['message'] : __('Returned to previous prices.', 'wsh-wcbpm'),
		));
	}

	public static function ajax_restore_batch()
	{
		if (! current_user_can('manage_woocommerce')) {
			self::send_ajax_error(__("You don't have permission.", 'wsh-wcbpm'), 403);
		}
		check_ajax_referer('wsh_wcbpm_admin');

		$batch_id = isset($_POST['batch_id']) ? sanitize_text_field(wp_unslash($_POST['batch_id'])) : '';
		if ('' === $batch_id) {
			self::send_ajax_error(__('Missing batch_id.', 'wsh-wcbpm'), 200);
		}

		$res = WSH_WCBPM_Undo_Manager::restore_batch($batch_id);
		if (empty($res['success'])) {
			self::send_ajax_error($res['message'] ?? __('Restore failed.', 'wsh-wcbpm'), 200);
		}

		wp_send_json_success(array('message' => $res['message'] ?? __('Restore completed.', 'wsh-wcbpm')));
	}

	public static function ajax_delete_batch()
	{
		if (! current_user_can('manage_woocommerce')) {
			self::send_ajax_error(__("You don't have permission.", 'wsh-wcbpm'), 403);
		}
		check_ajax_referer('wsh_wcbpm_admin');

		$batch_id = isset($_POST['batch_id']) ? sanitize_text_field(wp_unslash($_POST['batch_id'])) : '';
		if ('' === $batch_id) {
			self::send_ajax_error(__('Missing batch_id.', 'wsh-wcbpm'), 200);
		}

		$res = WSH_WCBPM_Undo_Manager::delete_batch($batch_id);
		if (empty($res['success'])) {
			self::send_ajax_error($res['message'] ?? __('Delete failed.', 'wsh-wcbpm'), 200);
		}

		wp_send_json_success(array('message' => $res['message'] ?? __('Deleted.', 'wsh-wcbpm')));
	}

	public static function ajax_job_start_apply()
	{
		if (! current_user_can('manage_woocommerce')) {
			self::send_ajax_error(__("You don't have permission.", 'wsh-wcbpm'), 403);
		}
		check_ajax_referer('wsh_wcbpm_admin');

		if (! (class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active())) {
			self::send_ajax_error(__('This is a PRO feature. Activate your license.', 'wsh-wcbpm'), 200);
		}

		$filters = (isset($_POST['filters']) && is_array($_POST['filters'])) ? (array) $_POST['filters'] : array();
		$update  = (isset($_POST['update']) && is_array($_POST['update'])) ? (array) $_POST['update'] : array();

		$batch_size = isset($_POST['batch_size']) ? (int) $_POST['batch_size'] : 200;
		$batch_size = max(50, min(2000, $batch_size));

		if (! class_exists('WSH_WCBPM_Dry_Run')) {
			self::send_ajax_error(__('Apply module is not loaded.', 'wsh-wcbpm'), 500);
		}

		$res = WSH_WCBPM_Dry_Run::job_start_apply($filters, $update, $batch_size);

		if (empty($res['success'])) {
			self::send_ajax_error($res['message'] ?? __('Unable to start job.', 'wsh-wcbpm'), 200);
		}

		wp_send_json_success(array(
			'job_id'     => $res['job_id'],
			'total'      => $res['total'],
			'batch_size' => $batch_size,
			'message'    => $res['message'] ?? __('Job started.', 'wsh-wcbpm'),
		));
	}

	public static function ajax_job_step_apply()
	{
		if (! current_user_can('manage_woocommerce')) {
			self::send_ajax_error(__("You don't have permission.", 'wsh-wcbpm'), 403);
		}
		check_ajax_referer('wsh_wcbpm_admin');

		if (! (class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active())) {
			self::send_ajax_error(__('This is a PRO feature. Activate your license.', 'wsh-wcbpm'), 200);
		}

		$job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
		if ('' === $job_id) {
			self::send_ajax_error(__('Missing job_id.', 'wsh-wcbpm'), 200);
		}

		if (! class_exists('WSH_WCBPM_Dry_Run')) {
			self::send_ajax_error(__('Apply module is not loaded.', 'wsh-wcbpm'), 500);
		}

		$res = WSH_WCBPM_Dry_Run::job_step_apply($job_id);

		if (empty($res['success'])) {
			self::send_ajax_error($res['message'] ?? __('Job step failed.', 'wsh-wcbpm'), 200);
		}

		wp_send_json_success(array(
			'done'      => !empty($res['done']),
			'processed' => (int) ($res['processed'] ?? 0),
			'total'     => (int) ($res['total'] ?? 0),
			'applied'   => (int) ($res['applied'] ?? 0),
			'errors'    => (int) ($res['errors'] ?? 0),
			'message'   => $res['message'] ?? '',
		));
	}

	public static function ajax_job_start_restore()
	{
		if (! current_user_can('manage_woocommerce')) {
			self::send_ajax_error(__("You don't have permission.", 'wsh-wcbpm'), 403);
		}
		check_ajax_referer('wsh_wcbpm_admin');

		if (! (class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active())) {
			self::send_ajax_error(__('This is a PRO feature. Activate your license.', 'wsh-wcbpm'), 200);
		}

		$batch_id = isset($_POST['batch_id']) ? sanitize_text_field(wp_unslash($_POST['batch_id'])) : '';
		if ('' === $batch_id) {
			self::send_ajax_error(__('Missing batch_id.', 'wsh-wcbpm'), 200);
		}

		$batch_size = isset($_POST['batch_size']) ? (int) $_POST['batch_size'] : 200;
		$batch_size = max(50, min(2000, $batch_size));

		if (! class_exists('WSH_WCBPM_Dry_Run')) {
			self::send_ajax_error(__('Restore module is not loaded.', 'wsh-wcbpm'), 500);
		}

		$res = WSH_WCBPM_Dry_Run::job_start_restore($batch_id, $batch_size);

		if (empty($res['success'])) {
			self::send_ajax_error($res['message'] ?? __('Unable to start restore job.', 'wsh-wcbpm'), 200);
		}

		wp_send_json_success(array(
			'job_id'     => $res['job_id'],
			'total'      => $res['total'],
			'batch_size' => $batch_size,
			'message'    => $res['message'] ?? __('Restore job started.', 'wsh-wcbpm'),
		));
	}

	public static function ajax_job_step_restore()
	{
		if (! current_user_can('manage_woocommerce')) {
			self::send_ajax_error(__("You don't have permission.", 'wsh-wcbpm'), 403);
		}
		check_ajax_referer('wsh_wcbpm_admin');

		if (! (class_exists('WSH_WCBPM_License') && WSH_WCBPM_License::is_active())) {
			self::send_ajax_error(__('This is a PRO feature. Activate your license.', 'wsh-wcbpm'), 200);
		}

		$job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
		if ('' === $job_id) {
			self::send_ajax_error(__('Missing job_id.', 'wsh-wcbpm'), 200);
		}

		if (! class_exists('WSH_WCBPM_Dry_Run')) {
			self::send_ajax_error(__('Restore module is not loaded.', 'wsh-wcbpm'), 500);
		}

		$res = WSH_WCBPM_Dry_Run::job_step_restore($job_id);

		if (empty($res['success'])) {
			self::send_ajax_error($res['message'] ?? __('Restore step failed.', 'wsh-wcbpm'), 200);
		}

		wp_send_json_success(array(
			'done'      => !empty($res['done']),
			'processed' => (int) ($res['processed'] ?? 0),
			'total'     => (int) ($res['total'] ?? 0),
			'restored'  => (int) ($res['restored'] ?? 0),
			'errors'    => (int) ($res['errors'] ?? 0),
			'message'   => $res['message'] ?? '',
		));
	}

	public static function ajax_preset_save()
	{
		if (! current_user_can('manage_woocommerce')) self::send_ajax_error(__("You don't have permission.", 'wsh-wcbpm'), 403);
		check_ajax_referer('wsh_wcbpm_admin');

		if (! class_exists('WSH_WCBPM_Preset_Manager')) self::send_ajax_error(__('Preset module not loaded.', 'wsh-wcbpm'), 500);

		$name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
		$desc = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';

		$payload = array();
		if (isset($_POST['payload'])) {
			$raw = wp_unslash((string) $_POST['payload']);
			$decoded = json_decode($raw, true);
			if (is_array($decoded)) $payload = $decoded;
		}

		$res = WSH_WCBPM_Preset_Manager::save_preset($name, $desc, $payload, 0);
		if (empty($res['success'])) self::send_ajax_error($res['message'] ?? __('Save failed.', 'wsh-wcbpm'), 200);

		wp_send_json_success(array('message' => $res['message'], 'id' => (int)($res['id'] ?? 0)));
	}

	public static function ajax_preset_get()
	{
		if (! current_user_can('manage_woocommerce')) self::send_ajax_error(__("You don't have permission.", 'wsh-wcbpm'), 403);
		check_ajax_referer('wsh_wcbpm_admin');

		if (! class_exists('WSH_WCBPM_Preset_Manager')) self::send_ajax_error(__('Preset module not loaded.', 'wsh-wcbpm'), 500);

		$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
		$preset = WSH_WCBPM_Preset_Manager::get_preset($id);
		if (! $preset) self::send_ajax_error(__('Preset not found.', 'wsh-wcbpm'), 200);

		wp_send_json_success(array(
			'preset' => array(
				'id'          => (int)$preset['id'],
				'name'        => (string)$preset['name'],
				'description' => (string)($preset['description'] ?? ''),
				'payload'     => (array)($preset['payload'] ?? array()),
			)
		));
	}

	public static function ajax_preset_delete()
	{
		if (! current_user_can('manage_woocommerce')) self::send_ajax_error(__("You don't have permission.", 'wsh-wcbpm'), 403);
		check_ajax_referer('wsh_wcbpm_admin');

		if (! class_exists('WSH_WCBPM_Preset_Manager')) self::send_ajax_error(__('Preset module not loaded.', 'wsh-wcbpm'), 500);

		$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
		$res = WSH_WCBPM_Preset_Manager::delete_preset($id);
		if (empty($res['success'])) self::send_ajax_error($res['message'] ?? __('Delete failed.', 'wsh-wcbpm'), 200);

		wp_send_json_success(array('message' => $res['message']));
	}

	public static function ajax_attr_terms() {
		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => __("You don't have permission.", 'wsh-wcbpm')), 403);
		}
		check_ajax_referer('wsh_wcbpm_admin');

		$tax = isset($_POST['taxonomy']) ? sanitize_text_field(wp_unslash($_POST['taxonomy'])) : '';
		if ($tax === '' || ! taxonomy_exists($tax)) {
			wp_send_json_error(array('message' => __('Invalid taxonomy.', 'wsh-wcbpm')), 200);
		}

		$terms = get_terms(array(
			'taxonomy'   => $tax,
			'hide_empty' => false,
		));

		if (is_wp_error($terms)) {
			wp_send_json_error(array('message' => __('Unable to load terms.', 'wsh-wcbpm')), 200);
		}

		$out = array();
		foreach ($terms as $t) {
			$out[] = array(
				'id'   => (int) $t->term_id,
				'text' => (string) $t->name,
			);
		}

		wp_send_json_success(array('terms' => $out));
	}

}
