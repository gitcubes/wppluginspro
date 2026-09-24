<?php
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Plugin package fields on the WooCommerce product, plus customer downloads.
 */
class WSH_Plugin_Catalog
{

	public static function init()
	{
		add_action('init', array(__CLASS__, 'register_account_endpoint'));
		add_action('init', array(__CLASS__, 'maybe_flush_rewrites'), 99);
		add_action('admin_notices', array(__CLASS__, 'render_notice'));
		add_action('before_delete_post', array(__CLASS__, 'delete_private_file'));

		add_filter('woocommerce_product_data_tabs', array(__CLASS__, 'product_tab'));
		add_action('woocommerce_product_data_panels', array(__CLASS__, 'product_panel'));
		add_action('add_meta_boxes', array(__CLASS__, 'files_meta_box'));
		add_action('post_edit_form_tag', array(__CLASS__, 'product_form_enctype'));
		add_action('woocommerce_process_product_meta', array(__CLASS__, 'save_product'));
		add_action('woocommerce_product_after_variable_attributes', array(__CLASS__, 'variation_fields'), 10, 3);
		add_action('woocommerce_save_product_variation', array(__CLASS__, 'save_variation'), 10, 2);
		add_action('admin_post_wsh_create_site_variations', array(__CLASS__, 'create_site_variations'));

		add_filter('woocommerce_account_menu_items', array(__CLASS__, 'account_menu_items'));
		add_action('woocommerce_account_plugin-files_endpoint', array(__CLASS__, 'render_account_downloads'));
		add_action('woocommerce_thankyou', array(__CLASS__, 'render_thankyou_access'), 5);
		add_action('woocommerce_account_dashboard', array(__CLASS__, 'render_dashboard_access'), 5);
		add_action('woocommerce_order_details_after_order_table', array(__CLASS__, 'render_order_access'), 5);
		add_action('woocommerce_after_register_post_type', array(__CLASS__, 'seed_suite_products'));
	}

	public static function register_account_endpoint()
	{
		add_rewrite_endpoint('plugin-files', EP_ROOT | EP_PAGES);
	}

	public static function maybe_flush_rewrites()
	{
		if (get_option('wsh_plugin_files_endpoint') === '1') {
			return;
		}

		flush_rewrite_rules(false);
		update_option('wsh_plugin_files_endpoint', '1', false);
	}

	public static function product_tab($tabs)
	{
		$tabs['wsh_plugin'] = array(
			'label'    => __('Plugin license', 'wsh-license-manager'),
			'target'   => 'wsh_plugin_product_data',
			'class'    => array(),
			'priority' => 75,
		);

		return $tabs;
	}

	public static function product_panel()
	{
		global $post;

		$product_id = $post instanceof WP_Post ? $post->ID : 0;
		$slug = (string) get_post_meta($product_id, 'wsh_plugin_slug', true);
		$family = (string) get_post_meta($product_id, 'wsh_plugin_family', true);
		$group = (string) get_post_meta($product_id, 'wsh_license_group', true);
		$landing_id = (int) get_post_meta($product_id, 'wsh_landing_page_id', true);
		$show = get_post_meta($product_id, 'wsh_show_in_catalog', true) === '1';
		$pages = get_pages(array('post_status' => array('publish', 'draft')));
		?>
		<div id="wsh_plugin_product_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<?php
				woocommerce_wp_text_input(array(
					'id'          => 'wsh_plugin_slug',
					'label'       => __('Plugin slug', 'wsh-license-manager'),
					'description' => __('Used on a single plugin. Example: wsh-views-counter-pro. Leave empty on a suite.', 'wsh-license-manager'),
					'value'       => $slug,
				));
				woocommerce_wp_select(array(
					'id'          => 'wsh_plugin_family',
					'label'       => __('Suite family', 'wsh-license-manager'),
					'description' => __('Which suite key also unlocks this plugin.', 'wsh-license-manager'),
					'value'       => $family,
					'options'     => array(
						''            => __('None', 'wsh-license-manager'),
						'news'        => __('News', 'wsh-license-manager'),
						'ecommerce'   => __('Ecommerce', 'wsh-license-manager'),
					),
				));
				woocommerce_wp_select(array(
					'id'          => 'wsh_license_group',
					'label'       => __('This product sells', 'wsh-license-manager'),
					'description' => __('Choose a group only for a News Suite, Ecommerce Suite, or All-Access product.', 'wsh-license-manager'),
					'value'       => $group,
					'options'     => array(
						''            => __('One plugin', 'wsh-license-manager'),
						'news'        => __('News suite', 'wsh-license-manager'),
						'ecommerce'   => __('Ecommerce suite', 'wsh-license-manager'),
						'all'         => __('All-Access', 'wsh-license-manager'),
					),
				));
				woocommerce_wp_select(array(
					'id'      => 'wsh_landing_page_id',
					'label'   => __('Landing page', 'wsh-license-manager'),
					'value'   => (string) $landing_id,
					'options' => self::page_options($pages, $landing_id),
				));
				woocommerce_wp_checkbox(array(
					'id'          => 'wsh_show_in_catalog',
					'label'       => __('Show in plugin catalog', 'wsh-license-manager'),
					'description' => __('Public plugins page. Leave off for the old test products.', 'wsh-license-manager'),
					'value'       => $show ? 'yes' : 'no',
				));
				?>
				<p class="form-field">
					<label><?php esc_html_e('Site variations', 'wsh-license-manager'); ?></label>
					<a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wsh_create_site_variations&product_id=' . $product_id), 'wsh_create_site_variations_' . $product_id)); ?>">
						<?php esc_html_e('Add 1 site, 5 sites, and unlimited', 'wsh-license-manager'); ?>
					</a>
					<span class="description"><?php esc_html_e('Turns this into a variable subscription. Existing test products stay as they are until you click this.', 'wsh-license-manager'); ?></span>
				</p>
			</div>
		</div>
		<?php
	}

	public static function files_meta_box()
	{
		add_meta_box(
			'wsh_plugin_zips',
			__('Plugin ZIP files', 'wsh-license-manager'),
			array(__CLASS__, 'render_files_meta_box'),
			'product',
			'normal',
			'high'
		);
	}

	public static function product_form_enctype()
	{
		global $post;

		if ($post instanceof WP_Post && $post->post_type === 'product') {
			echo ' enctype="multipart/form-data"';
		}
	}

	public static function render_files_meta_box($post)
	{
		$product_id = $post instanceof WP_Post ? $post->ID : 0;
		$packages = WSH_Plugin_Storage::packages($product_id);
		?>
		<p><?php esc_html_e('One row per version. A plugin can have a Free row and a PRO row. The file address stays private.', 'wsh-license-manager'); ?></p>
		<?php if (! empty($packages)) : ?>
			<table class="widefat striped" style="margin-bottom:12px;">
				<thead>
					<tr>
						<th><?php esc_html_e('Package', 'wsh-license-manager'); ?></th>
						<th><?php esc_html_e('Version', 'wsh-license-manager'); ?></th>
						<th><?php esc_html_e('File', 'wsh-license-manager'); ?></th>
						<th><?php esc_html_e('Remove', 'wsh-license-manager'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($packages as $package) : ?>
						<tr>
							<td><?php echo esc_html($package['channel'] === 'free' ? 'Free' : 'PRO'); ?></td>
							<td><?php echo esc_html($package['version']); ?></td>
							<td>
								<?php echo esc_html($package['original']); ?>
								<a href="<?php echo esc_url(WSH_Plugin_Storage::download_url($product_id, $package['id'])); ?>"><?php esc_html_e('Test', 'wsh-license-manager'); ?></a>
							</td>
							<td><input type="checkbox" name="wsh_remove_file[]" value="<?php echo esc_attr($package['id']); ?>"></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<div id="wsh-file-rows">
			<div class="wsh-file-row" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
				<select name="wsh_new_channel[]">
					<option value="free"><?php esc_html_e('Free', 'wsh-license-manager'); ?></option>
					<option value="pro" selected><?php esc_html_e('PRO', 'wsh-license-manager'); ?></option>
				</select>
				<input type="text" name="wsh_new_version[]" placeholder="1.0.0" style="width:120px;">
				<input type="file" name="wsh_new_zip[]" accept=".zip,application/zip">
			</div>
		</div>
		<p>
			<button type="button" class="button" id="wsh-add-file-row"><?php esc_html_e('Add version', 'wsh-license-manager'); ?></button>
		</p>
		<script>
			document.getElementById('wsh-add-file-row').addEventListener('click', function () {
				var rows = document.getElementById('wsh-file-rows');
				var copy = rows.querySelector('.wsh-file-row').cloneNode(true);
				copy.querySelectorAll('input').forEach(function (input) { input.value = ''; });
				rows.appendChild(copy);
			});
		</script>
		<?php
	}

	public static function variation_fields($loop, $variation_data, $variation)
	{
		woocommerce_wp_text_input(array(
			'id'            => 'wsh_max_sites_' . $loop,
			'name'          => 'wsh_max_sites[' . $loop . ']',
			'value'         => get_post_meta($variation->ID, 'wsh_max_sites', true),
			'label'         => __('Max sites', 'wsh-license-manager'),
			'description'   => __('1, 5, or 0 for unlimited.', 'wsh-license-manager'),
			'type'          => 'number',
			'wrapper_class' => 'form-row form-row-full',
			'custom_attributes' => array('min' => '0', 'step' => '1'),
		));
	}

	public static function save_variation($variation_id, $loop)
	{
		if (! isset($_POST['wsh_max_sites'][$loop])) {
			return;
		}

		update_post_meta($variation_id, 'wsh_max_sites', max(0, (int) wp_unslash($_POST['wsh_max_sites'][$loop])));
	}

	public static function save_product($product_id)
	{
		if (! current_user_can('manage_options')) {
			return;
		}

		$slug = isset($_POST['wsh_plugin_slug']) ? sanitize_title(wp_unslash($_POST['wsh_plugin_slug'])) : '';
		$family = isset($_POST['wsh_plugin_family']) ? sanitize_key(wp_unslash($_POST['wsh_plugin_family'])) : '';
		$group = isset($_POST['wsh_license_group']) ? sanitize_key(wp_unslash($_POST['wsh_license_group'])) : '';
		$landing_id = isset($_POST['wsh_landing_page_id']) ? (int) $_POST['wsh_landing_page_id'] : 0;

		if (! in_array($family, array('news', 'ecommerce'), true)) {
			$family = '';
		}
		if (! in_array($group, array('news', 'ecommerce', 'all'), true)) {
			$group = '';
		}

		update_post_meta($product_id, 'wsh_plugin_slug', $slug);
		update_post_meta($product_id, 'wsh_plugin_family', $family);
		update_post_meta($product_id, 'wsh_license_group', $group);
		update_post_meta($product_id, 'wsh_landing_page_id', $landing_id);
		update_post_meta($product_id, 'wsh_show_in_catalog', empty($_POST['wsh_show_in_catalog']) ? '0' : '1');

		if (! empty($_POST['wsh_remove_file']) && is_array($_POST['wsh_remove_file'])) {
			WSH_Plugin_Storage::remove_packages($product_id, array_map('sanitize_text_field', wp_unslash($_POST['wsh_remove_file'])));
		}

		self::save_new_packages($product_id);
	}

	public static function create_site_variations()
	{
		$product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;
		check_admin_referer('wsh_create_site_variations_' . $product_id);

		if (! current_user_can('manage_options') || get_post_type($product_id) !== 'product') {
			wp_die(esc_html__('You cannot edit this product.', 'wsh-license-manager'));
		}

		self::ensure_sites_attribute();
		wp_set_object_terms($product_id, 'variable-subscription', 'product_type');
		clean_post_cache($product_id);

		$terms = array();
		foreach (array('1-site' => '1 site', '5-sites' => '5 sites', 'unlimited' => 'Unlimited') as $slug => $name) {
			$term = get_term_by('slug', $slug, 'pa_sites');
			if ($term instanceof WP_Term) {
				$terms[$slug] = (int) $term->term_id;
			}
		}

		wp_set_object_terms($product_id, array_values($terms), 'pa_sites');

		$product = new WC_Product_Variable_Subscription($product_id);
		$attribute = new WC_Product_Attribute();
		$attribute->set_id((int) wc_attribute_taxonomy_id_by_name('sites'));
		$attribute->set_name('pa_sites');
		$attribute->set_options(array_values($terms));
		$attribute->set_visible(true);
		$attribute->set_variation(true);
		$product->set_attributes(array($attribute));
		$product->save();

		$limits = array('1-site' => 1, '5-sites' => 5, 'unlimited' => 0);
		$price = get_post_meta($product_id, '_subscription_price', true);
		if ($price === '') {
			$price = get_post_meta($product_id, '_regular_price', true);
		}
		$period = get_post_meta($product_id, '_subscription_period', true);
		$interval = get_post_meta($product_id, '_subscription_period_interval', true);

		foreach ($limits as $slug => $max_sites) {
			if (self::variation_exists($product_id, $slug)) {
				continue;
			}

			$variation = new WC_Product_Subscription_Variation();
			$variation->set_parent_id($product_id);
			$variation->set_attributes(array('pa_sites' => $slug));
			$variation->set_regular_price($price !== '' ? $price : '0');
			$variation->set_status('publish');
			$variation->update_meta_data('_subscription_period', $period !== '' ? $period : 'year');
			$variation->update_meta_data('_subscription_period_interval', $interval !== '' ? $interval : 1);
			$variation->update_meta_data('_subscription_length', 0);
			$variation->update_meta_data('wsh_max_sites', $max_sites);
			$variation->save();
		}

		WC_Product_Variable::sync($product_id);
		wp_safe_redirect(get_edit_post_link($product_id, 'raw'));
		exit;
	}

	public static function account_menu_items($items)
	{
		$updated = array();

		foreach ($items as $key => $label) {
			$updated[$key] = $label;
			if ($key === 'orders') {
				$updated['plugin-files'] = __('Plugins', 'wsh-license-manager');
			}
		}

		if (! isset($updated['plugin-files'])) {
			$updated['plugin-files'] = __('Licenses & downloads', 'wsh-license-manager');
		} else {
			$updated['plugin-files'] = __('Licenses & downloads', 'wsh-license-manager');
		}

		unset($updated['downloads']);

		return $updated;
	}

	public static function render_thankyou_access($order_id)
	{
		$order = wc_get_order($order_id);
		if (! $order instanceof WC_Order) {
			return;
		}

		self::render_purchase_access(
			self::licenses_for_order($order),
			__('Your license and download', 'wsh-license-manager'),
			__('Copy the license key into the plugin on your site. Download the PRO file here. The site address is saved when you activate the plugin.', 'wsh-license-manager'),
			true
		);
	}

	public static function render_dashboard_access()
	{
		$licenses = self::licenses_for_user();
		$plugins_url = function_exists('cubestheme_plugins_page_url') ? cubestheme_plugins_page_url() : home_url('/plugins/');
		$licenses_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('plugin-files') : home_url('/my-account/plugin-files/');
		$site_count = 0;
		$nearest_label = __('No renewal date yet', 'wsh-license-manager');
		$nearest_detail = __('A renewal date appears after the first subscription payment.', 'wsh-license-manager');
		$nearest_time = null;

		foreach ($licenses as $license) {
			$site_count += count(WSH_License_Utils::activated_sites($license->ID));
			$facts = self::license_facts($license->ID);
			if ($facts['renews_time'] && ($nearest_time === null || $facts['renews_time'] < $nearest_time)) {
				$nearest_time = $facts['renews_time'];
				$days = (int) max(0, floor(($facts['renews_time'] - time()) / DAY_IN_SECONDS));
				$nearest_label = sprintf(_n('%d day', '%d days', $days, 'wsh-license-manager'), $days);
				$nearest_detail = $facts['name'] . ' · ' . $facts['plan'];
			}
		}

		echo '<section class="account-panel">';
		echo '<div class="account-intro">';
		echo '<div><span class="account-kicker">' . esc_html__('Account', 'wsh-license-manager') . '</span>';
		echo '<h2>' . esc_html__('Your licenses and sites', 'wsh-license-manager') . '</h2>';
		echo '<p>' . esc_html__('Copy a license key into the plugin, then download the PRO file. The site is saved when you activate the plugin.', 'wsh-license-manager') . '</p></div>';
		echo '<div class="account-intro__actions">';
		echo '<a class="button button-secondary" href="' . esc_url($licenses_url) . '">' . esc_html__('All licenses', 'wsh-license-manager') . '</a>';
		echo '<a class="button" href="' . esc_url($plugins_url) . '">' . esc_html__('Browse plugins', 'wsh-license-manager') . '</a>';
		echo '</div></div>';

		echo '<div class="account-metrics">';
		self::metric_card(__('Total licenses', 'wsh-license-manager'), (string) count($licenses), __('Active plugin and suite keys on this account.', 'wsh-license-manager'));
		self::metric_card(__('Active sites', 'wsh-license-manager'), (string) $site_count, __('Domains currently using your licenses. Staging does not count.', 'wsh-license-manager'));
		self::metric_card(__('Nearest renewal', 'wsh-license-manager'), $nearest_label, $nearest_detail);
		self::metric_card(__('Support', 'wsh-license-manager'), empty($licenses) ? __('Inactive', 'wsh-license-manager') : __('Priority', 'wsh-license-manager'), empty($licenses) ? __('Support starts with an active PRO license.', 'wsh-license-manager') : __('Active while at least one PRO license is valid.', 'wsh-license-manager'));
		echo '</div>';

		echo '<div class="account-section-head"><div><h3>' . esc_html__('Your licenses', 'wsh-license-manager') . '</h3>';
		echo '<p>' . esc_html__('Download the latest PRO file and paste the key in the plugin settings.', 'wsh-license-manager') . '</p></div></div>';
		self::render_account_license_table($licenses);
		echo '</section>';
	}

	public static function render_order_access($order)
	{
		if (! $order instanceof WC_Order || (function_exists('is_order_received_page') && is_order_received_page())) {
			return;
		}

		$licenses = self::licenses_for_order($order);
		if (empty($licenses)) {
			return;
		}

		self::render_purchase_access(
			$licenses,
			__('License and download for this order', 'wsh-license-manager'),
			__('Use this key in the plugin. Download the PRO file from the list.', 'wsh-license-manager')
		);
	}

	private static function render_purchase_access($licenses, $title, $text, $prominent = false)
	{
		$account_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('plugin-files') : home_url('/my-account/plugin-files/');
		$button = '<p class="wsh-purchase-access__action"><a class="button" href="' . esc_url($account_url) . '">' . esc_html__('Open licenses and downloads', 'wsh-license-manager') . '</a></p>';

		echo '<section class="wsh-purchase-access' . ($prominent ? ' is-prominent' : '') . '">';
		echo '<h2>' . esc_html($title) . '</h2>';
		echo '<p>' . esc_html($text) . '</p>';
		if ($prominent) {
			echo $button;
		}

		if (empty($licenses)) {
			echo '<p>' . esc_html__('Your license is still being prepared. Open Licenses & downloads in a moment.', 'wsh-license-manager') . '</p>';
		} else {
			self::render_license_table($licenses);
		}

		if (! $prominent) {
			echo $button;
		}
		echo '</section>';
	}

	public static function render_account_downloads()
	{
		$licenses = self::licenses_for_user();
		$plugins_url = function_exists('cubestheme_plugins_page_url') ? cubestheme_plugins_page_url() : home_url('/plugins/');

		echo '<section class="account-panel">';
		echo '<div class="account-intro">';
		echo '<div><span class="account-kicker">' . esc_html__('Licenses', 'wsh-license-manager') . '</span>';
		echo '<h2>' . esc_html__('Licenses and downloads', 'wsh-license-manager') . '</h2>';
		echo '<p>' . esc_html__('Copy the license key into the plugin. Download the latest PRO file. Older files can be less secure.', 'wsh-license-manager') . '</p></div>';
		echo '<div class="account-intro__actions"><a class="button" href="' . esc_url($plugins_url) . '">' . esc_html__('Browse plugins', 'wsh-license-manager') . '</a></div>';
		echo '</div>';
		self::render_account_license_table($licenses);
		echo '</section>';
	}

	private static function metric_card($label, $value, $text)
	{
		echo '<article class="account-metric"><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong><p>' . esc_html($text) . '</p></article>';
	}

	private static function render_account_license_table($licenses)
	{
		if (empty($licenses)) {
			echo '<p class="account-empty">' . esc_html__('You do not have an active plugin license yet.', 'wsh-license-manager') . '</p>';
			return;
		}

		echo '<div class="account-table-wrap"><table class="account-license-table"><thead><tr>';
		echo '<th>' . esc_html__('Plugin / Suite', 'wsh-license-manager') . '</th>';
		echo '<th>' . esc_html__('Plan', 'wsh-license-manager') . '</th>';
		echo '<th>' . esc_html__('License key', 'wsh-license-manager') . '</th>';
		echo '<th>' . esc_html__('Status', 'wsh-license-manager') . '</th>';
		echo '<th>' . esc_html__('Renews', 'wsh-license-manager') . '</th>';
		echo '<th>' . esc_html__('Sites', 'wsh-license-manager') . '</th>';
		echo '<th>' . esc_html__('Download', 'wsh-license-manager') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($licenses as $license) {
			$facts = self::license_facts($license->ID);
			echo '<tr>';
			echo '<td><div class="account-product"><strong>' . esc_html($facts['name']) . '</strong>';
			if ($facts['summary'] !== '') {
				echo '<p>' . esc_html($facts['summary']) . '</p>';
			}
			echo '</div></td>';
			echo '<td><div class="account-plan"><span class="account-pill">' . esc_html($facts['kind']) . '</span><p>' . esc_html($facts['plan']) . '</p></div></td>';
			echo '<td><div class="account-key"><code>' . esc_html($facts['key']) . '</code>';
			echo '<button type="button" class="wsh-copy-key" data-key="' . esc_attr($facts['key']) . '">' . esc_html__('Copy key', 'wsh-license-manager') . '</button></div></td>';
			echo '<td><span class="account-badge is-' . esc_attr($facts['status_class']) . '">' . esc_html($facts['status']) . '</span></td>';
			echo '<td>' . esc_html($facts['renews']) . '</td>';
			echo '<td><div class="account-sites">';
			if (empty($facts['sites'])) {
				echo '<span>' . esc_html__('Not activated yet', 'wsh-license-manager') . '</span>';
			} else {
				foreach ($facts['sites'] as $site) {
					echo '<span class="account-site">' . esc_html($site) . '</span>';
				}
			}
			echo '</div></td>';
			echo '<td><div class="account-downloads">' . $facts['downloads'] . '</div></td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	private static function license_facts($license_id)
	{
		$products = self::products_for_license($license_id);
		$group = (string) get_post_meta($license_id, 'wsh_license_group', true);
		$max_sites = WSH_License_Utils::max_sites($license_id);
		$status = (string) get_post_meta($license_id, 'wsh_status', true);
		$plan = $max_sites === 0 ? __('Unlimited sites', 'wsh-license-manager') : sprintf(_n('%d site', '%d sites', $max_sites, 'wsh-license-manager'), $max_sites);
		$renews = __('Not scheduled', 'wsh-license-manager');
		$renews_time = null;
		$subscription_id = (int) get_post_meta($license_id, 'wsh_subscription_id', true);

		if ($subscription_id && function_exists('wcs_get_subscription')) {
			$subscription = wcs_get_subscription($subscription_id);
			if ($subscription) {
				$next = $subscription->get_time('next_payment');
				if ($next) {
					$renews_time = $next;
					$renews = $subscription->get_date_to_display('next_payment');
				}
				if ($status === '') {
					$status = $subscription->get_status();
				}
			}
		}

		$downloads = '';
		foreach ($products as $product) {
			$downloads .= self::download_links($product);
		}
		if ($downloads === '') {
			$downloads = '<span>' . esc_html__('Not available yet', 'wsh-license-manager') . '</span>';
		}

		$kinds = array(
			'news' => __('Suite', 'wsh-license-manager'),
			'ecommerce' => __('Suite', 'wsh-license-manager'),
			'all' => __('All-Access', 'wsh-license-manager'),
		);

		return array(
			'name' => self::license_label($license_id, $products),
			'summary' => $plan . ' · ' . __('Yearly', 'wsh-license-manager'),
			'kind' => isset($kinds[$group]) ? $kinds[$group] : __('Single plugin', 'wsh-license-manager'),
			'plan' => $plan,
			'key' => (string) get_post_meta($license_id, 'wsh_license_key', true),
			'status' => $status !== '' ? ucfirst($status) : __('Active', 'wsh-license-manager'),
			'status_class' => in_array($status, array('active', ''), true) ? 'active' : ($status === 'expired' ? 'expired' : 'expiring'),
			'renews' => $renews,
			'renews_time' => $renews_time,
			'sites' => WSH_License_Utils::activated_sites($license_id),
			'downloads' => $downloads,
		);
	}

	private static function download_links($product)
	{
		$html = '';
		foreach (array('pro', 'free') as $channel) {
			$packages = WSH_Plugin_Storage::packages_for_channel($product->ID, $channel);
			if (empty($packages) || ! WSH_Plugin_Storage::user_can_download(get_current_user_id(), $product->ID, $channel)) {
				continue;
			}

			foreach ($packages as $index => $package) {
				$label = trim(get_the_title($product) . ' ' . ($channel === 'free' ? 'Free' : 'PRO') . ' ' . $package['version']);
				$class = $index === 0 ? 'wsh-download' : 'wsh-download is-older';
				$html .= '<a class="' . esc_attr($class) . '" href="' . esc_url(WSH_Plugin_Storage::download_url($product->ID, $package['id'])) . '">' . esc_html($label) . '</a>';
			}
		}

		return $html;
	}

	private static function render_license_table($licenses)
	{
		echo '<table class="shop_table shop_table_responsive wsh-license-table"><thead><tr>';
		echo '<th>' . esc_html__('Plugin', 'wsh-license-manager') . '</th>';
		echo '<th>' . esc_html__('License key', 'wsh-license-manager') . '</th>';
		echo '<th>' . esc_html__('Download', 'wsh-license-manager') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($licenses as $license) {
			$products = self::products_for_license($license->ID);
			$name = self::license_label($license->ID, $products);
			$key = (string) get_post_meta($license->ID, 'wsh_license_key', true);
			$max_sites = (int) get_post_meta($license->ID, 'wsh_max_sites', true);
			$sites = $max_sites === 0 ? __('Unlimited sites', 'wsh-license-manager') : sprintf(_n('%d site', '%d sites', $max_sites, 'wsh-license-manager'), $max_sites);

			echo '<tr><td>' . esc_html($name) . '<br><span class="wsh-license-sites">' . esc_html($sites) . '</span></td>';
			echo '<td><code class="wsh-license-key">' . esc_html($key) . '</code></td><td>';
			if (empty($products)) {
				esc_html_e('Not available yet', 'wsh-license-manager');
			} else {
				foreach ($products as $product) {
					self::render_version_links($product, 'pro');
					self::render_version_links($product, 'free');
				}
			}
			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	private static function licenses_for_user()
	{
		$user = wp_get_current_user();
		if (! $user instanceof WP_User || $user->user_email === '') {
			return array();
		}

		return get_posts(array(
			'post_type'      => 'wsh_license',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => 'wsh_customer_email',
					'value' => $user->user_email,
				),
				array(
					'key'   => 'wsh_status',
					'value' => 'active',
				),
			),
		));
	}

	private static function licenses_for_order($order)
	{
		$licenses = array();
		$seen = array();

		if (function_exists('wcs_get_subscriptions_for_order')) {
			$subscriptions = wcs_get_subscriptions_for_order($order, array('order_type' => 'any'));
			foreach ($subscriptions as $subscription) {
				$found = get_posts(array(
					'post_type'      => 'wsh_license',
					'post_status'    => 'publish',
					'posts_per_page' => 20,
					'meta_key'       => 'wsh_subscription_id',
					'meta_value'     => (string) $subscription->get_id(),
				));
				foreach ($found as $license) {
					if (! isset($seen[$license->ID])) {
						$seen[$license->ID] = true;
						$licenses[] = $license;
					}
				}
			}
		}

		if (! empty($licenses)) {
			return $licenses;
		}

		$email = $order->get_billing_email();
		$product_ids = array();
		foreach ($order->get_items() as $item) {
			if (method_exists($item, 'get_product_id')) {
				$product_ids[] = (int) $item->get_product_id();
			}
		}

		if ($email === '' || empty($product_ids)) {
			return array();
		}

		return get_posts(array(
			'post_type'      => 'wsh_license',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => 'wsh_customer_email',
					'value' => $email,
				),
				array(
					'key'     => 'wsh_product_id',
					'value'   => $product_ids,
					'compare' => 'IN',
				),
				array(
					'key'   => 'wsh_status',
					'value' => 'active',
				),
			),
		));
	}

	public static function delete_private_file($post_id)
	{
		if (get_post_type($post_id) !== 'product') {
			return;
		}

		WSH_Plugin_Storage::delete_file($post_id);
	}

	private static function save_new_packages($product_id)
	{
		$channels = isset($_POST['wsh_new_channel']) ? (array) wp_unslash($_POST['wsh_new_channel']) : array();
		$versions = isset($_POST['wsh_new_version']) ? (array) wp_unslash($_POST['wsh_new_version']) : array();
		$uploads = isset($_FILES['wsh_new_zip']) && is_array($_FILES['wsh_new_zip']) ? $_FILES['wsh_new_zip'] : array();

		if (empty($uploads['name']) || ! is_array($uploads['name'])) {
			return;
		}

		foreach ($uploads['name'] as $index => $name) {
			if ($name === '') {
				continue;
			}

			$version = sanitize_text_field($versions[$index] ?? '');
			if ($version === '') {
				self::notice(__('Each ZIP needs a version number.', 'wsh-license-manager'));
				continue;
			}

			$result = WSH_Plugin_Storage::add_package($product_id, sanitize_key($channels[$index] ?? 'pro'), $version, array(
				'name'     => $uploads['name'][$index] ?? '',
				'type'     => $uploads['type'][$index] ?? '',
				'tmp_name' => $uploads['tmp_name'][$index] ?? '',
				'error'    => $uploads['error'][$index] ?? UPLOAD_ERR_NO_FILE,
				'size'     => $uploads['size'][$index] ?? 0,
			));

			if (is_wp_error($result)) {
				self::notice($result->get_error_message());
			}
		}
	}

	private static function ensure_sites_attribute()
	{
		if (! wc_attribute_taxonomy_id_by_name('sites')) {
			wc_create_attribute(array(
				'name'         => 'Sites',
				'slug'         => 'sites',
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			));
			delete_transient('wc_attribute_taxonomies');
		}

		if (! taxonomy_exists('pa_sites')) {
			register_taxonomy('pa_sites', array('product'), array(
				'label'        => 'Sites',
				'public'       => false,
				'hierarchical' => false,
				'show_ui'      => false,
			));
		}

		foreach (array('1-site' => '1 site', '5-sites' => '5 sites', 'unlimited' => 'Unlimited') as $slug => $name) {
			if (! term_exists($slug, 'pa_sites')) {
				wp_insert_term($name, 'pa_sites', array('slug' => $slug));
			}
		}
	}

	private static function variation_exists($product_id, $slug)
	{
		$children = get_posts(array(
			'post_type'      => 'product_variation',
			'post_parent'    => $product_id,
			'post_status'    => array('publish', 'private'),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		));

		foreach ($children as $child_id) {
			if ((string) get_post_meta($child_id, 'attribute_pa_sites', true) === $slug) {
				return true;
			}
		}

		return false;
	}

	private static function products_for_license($license_id)
	{
		$group = (string) get_post_meta($license_id, 'wsh_license_group', true);
		$slug = (string) get_post_meta($license_id, 'wsh_product_slug', true);

		if (in_array($group, array('news', 'ecommerce', 'all'), true)) {
			$query = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'post_parent'    => 0,
				'posts_per_page' => 50,
			);
			if ($group !== 'all') {
				$query['meta_key'] = 'wsh_plugin_family';
				$query['meta_value'] = $group;
			} else {
				$query['meta_query'] = array(array(
					'key'     => 'wsh_plugin_family',
					'value'   => array('news', 'ecommerce'),
					'compare' => 'IN',
				));
			}

			return get_posts($query);
		}

		$product = self::find_by_slug($slug);

		return $product instanceof WP_Post ? array($product) : array();
	}

	private static function license_label($license_id, $products)
	{
		$group = (string) get_post_meta($license_id, 'wsh_license_group', true);
		$labels = array(
			'news'      => __('News Suite', 'wsh-license-manager'),
			'ecommerce' => __('Ecommerce Suite', 'wsh-license-manager'),
			'all'       => __('All-Access', 'wsh-license-manager'),
		);

		if (isset($labels[$group])) {
			return $labels[$group];
		}

		$product_id = (int) get_post_meta($license_id, 'wsh_product_id', true);
		if ($product_id > 0) {
			return get_the_title($product_id);
		}

		return ! empty($products) ? get_the_title($products[0]) : (string) get_post_meta($license_id, 'wsh_product_slug', true);
	}

	private static function render_version_links($product, $channel)
	{
		$packages = WSH_Plugin_Storage::packages_for_channel($product->ID, $channel);
		if (empty($packages) || ! WSH_Plugin_Storage::user_can_download(get_current_user_id(), $product->ID, $channel)) {
			return;
		}

		echo '<p style="margin:0 0 8px;"><strong>' . esc_html(get_the_title($product) . ' ' . ($channel === 'free' ? 'Free' : 'PRO')) . '</strong></p>';
		echo '<ul style="margin:0 0 16px;padding-left:18px;">';
		foreach ($packages as $package) {
			$label = trim(get_the_title($product) . ' ' . $package['version']);
			echo '<li><a href="' . esc_url(WSH_Plugin_Storage::download_url($product->ID, $package['id'])) . '">' . esc_html($label) . '</a></li>';
		}
		echo '</ul>';
	}

	public static function find_by_slug($slug)
	{
		if ($slug === '') {
			return null;
		}

		$posts = get_posts(array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'post_parent'    => 0,
			'posts_per_page' => 1,
			'meta_key'       => 'wsh_plugin_slug',
			'meta_value'     => $slug,
		));

		return ! empty($posts) ? $posts[0] : null;
	}

	private static function page_options($pages, $selected)
	{
		$options = array('0' => __('No landing page', 'wsh-license-manager'));
		foreach ($pages as $page) {
			$options[(string) $page->ID] = $page->post_title . ($page->post_status === 'draft' ? ' (draft)' : '');
		}
		if ($selected > 0 && ! isset($options[(string) $selected])) {
			$options[(string) $selected] = get_the_title($selected);
		}

		return $options;
	}

	private static function notice($message)
	{
		set_transient('wsh_plugin_notice_' . get_current_user_id(), $message, 60);
	}

	public static function seed_suite_products()
	{
		if (get_option('wsh_suite_products_seeded') === '4' || ! class_exists('WC_Product_Variable_Subscription')) {
			return;
		}

		$suites = array(
			array(
				'title' => 'WSH News Suite',
				'slug' => 'news-suite',
				'group' => 'news',
				'excerpt' => 'One yearly license for every News plugin. The site limit is on this key.',
				'prices' => array('1-site' => '149', '5-sites' => '249', 'unlimited' => '349'),
			),
			array(
				'title' => 'WSH Ecommerce Suite',
				'slug' => 'ecommerce-suite',
				'group' => 'ecommerce',
				'excerpt' => 'One yearly license for every Ecommerce plugin. The site limit is on this key.',
				'prices' => array('1-site' => '149', '5-sites' => '249', 'unlimited' => '349'),
			),
			array(
				'title' => 'WSH All-Access',
				'slug' => 'all-access',
				'group' => 'all',
				'excerpt' => 'One yearly license for every PRO plugin, in both News and Ecommerce. Less than buying both suites.',
				'prices' => array('1-site' => '199', '5-sites' => '299', 'unlimited' => '399'),
			),
		);

		$category = term_exists('suites', 'product_cat');
		if (! $category) {
			$category = wp_insert_term('Suites', 'product_cat', array('slug' => 'suites'));
		}
		$category_id = 0;
		if (is_array($category) && isset($category['term_id'])) {
			$category_id = (int) $category['term_id'];
		}

		foreach ($suites as $suite) {
			$existing = get_page_by_path($suite['slug'], OBJECT, 'product');
			if ($existing instanceof WP_Post) {
				$product_id = (int) $existing->ID;
				$product = new WC_Product_Variable_Subscription($product_id);
			} else {
				$product = new WC_Product_Variable_Subscription();
				$product->set_name($suite['title']);
				$product->set_slug($suite['slug']);
				$product->set_status('publish');
				$product->set_catalog_visibility('visible');
				$product->set_virtual(true);
				$product->set_short_description($suite['excerpt']);
				$product->set_description($suite['excerpt']);
				if ($category_id > 0) {
					$product->set_category_ids(array($category_id));
				}
				$product_id = $product->save();
			}

			if (! $product_id) {
				return;
			}

			update_post_meta($product_id, 'wsh_plugin_slug', '');
			update_post_meta($product_id, 'wsh_plugin_family', '');
			update_post_meta($product_id, 'wsh_license_group', $suite['group']);
			update_post_meta($product_id, 'wsh_show_in_catalog', '1');

			self::ensure_sites_attribute();
			$terms = array();
			foreach (array('1-site' => '1 site', '5-sites' => '5 sites', 'unlimited' => 'Unlimited') as $slug => $name) {
				$term = get_term_by('slug', $slug, 'pa_sites');
				if ($term instanceof WP_Term) {
					$terms[$slug] = (int) $term->term_id;
				}
			}

			wp_set_object_terms($product_id, array_values($terms), 'pa_sites');
			$attribute = new WC_Product_Attribute();
			$attribute->set_id((int) wc_attribute_taxonomy_id_by_name('sites'));
			$attribute->set_name('pa_sites');
			$attribute->set_options(array_values($terms));
			$attribute->set_visible(true);
			$attribute->set_variation(true);
			$product->set_attributes(array($attribute));
			$product->save();

			$limits = array('1-site' => 1, '5-sites' => 5, 'unlimited' => 0);
			foreach ($limits as $slug => $max_sites) {
				if (self::variation_exists($product_id, $slug)) {
					continue;
				}

				$variation = new WC_Product_Subscription_Variation();
				$variation->set_parent_id($product_id);
				$variation->set_attributes(array('pa_sites' => $slug));
				$variation->set_regular_price($suite['prices'][$slug]);
				$variation->set_status('publish');
				$variation->set_virtual(true);
				$variation->update_meta_data('_subscription_period', 'year');
				$variation->update_meta_data('_subscription_period_interval', 1);
				$variation->update_meta_data('_subscription_length', 0);
				$variation->update_meta_data('wsh_max_sites', $max_sites);
				$variation->save();
			}

			WC_Product_Variable::sync($product_id);
			wp_set_object_terms($product_id, 'variable-subscription', 'product_type');
		}

		update_option('wsh_suite_products_seeded', '4', false);
	}

	public static function render_notice()
	{
		$message = get_transient('wsh_plugin_notice_' . get_current_user_id());
		if (! $message) {
			return;
		}

		delete_transient('wsh_plugin_notice_' . get_current_user_id());
		echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
	}
}
