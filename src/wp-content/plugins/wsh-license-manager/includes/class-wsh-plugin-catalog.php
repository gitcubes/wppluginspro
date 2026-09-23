<?php
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Admin catalog of sellable plugins: landing page, private ZIP, and the
 * customer download list in My Account.
 */
class WSH_Plugin_Catalog
{

	public static function init()
	{
		add_action('init', array(__CLASS__, 'register_cpt'));
		add_action('init', array(__CLASS__, 'register_account_endpoint'));
		add_action('init', array(__CLASS__, 'maybe_flush_rewrites'), 99);
		add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
		add_action('save_post_wsh_plugin', array(__CLASS__, 'save_plugin'), 10, 2);
		add_action('before_delete_post', array(__CLASS__, 'delete_private_file'));
		add_action('admin_notices', array(__CLASS__, 'render_notice'));

		add_filter('manage_edit-wsh_plugin_columns', array(__CLASS__, 'admin_columns'));
		add_action('manage_wsh_plugin_posts_custom_column', array(__CLASS__, 'render_admin_column'), 10, 2);

		add_filter('woocommerce_account_menu_items', array(__CLASS__, 'account_menu_items'));
		add_action('woocommerce_account_plugin-files_endpoint', array(__CLASS__, 'render_account_downloads'));
	}

	public static function register_cpt()
	{
		register_post_type('wsh_plugin', array(
			'labels' => array(
				'name'          => __('Plugins', 'wsh-license-manager'),
				'singular_name' => __('Plugin', 'wsh-license-manager'),
				'add_new_item'  => __('Add plugin', 'wsh-license-manager'),
				'edit_item'     => __('Edit plugin', 'wsh-license-manager'),
				'menu_name'     => __('Plugins', 'wsh-license-manager'),
				'not_found'     => __('No plugins yet', 'wsh-license-manager'),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=wsh_license',
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'supports'            => array('title'),
			'map_meta_cap'        => false,
			'capabilities'        => array(
				'edit_post'          => 'manage_options',
				'read_post'          => 'manage_options',
				'delete_post'        => 'manage_options',
				'edit_posts'         => 'manage_options',
				'edit_others_posts'  => 'manage_options',
				'publish_posts'      => 'manage_options',
				'read_private_posts' => 'manage_options',
				'delete_posts'       => 'manage_options',
				'create_posts'       => 'manage_options',
			),
		));
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

	public static function add_meta_boxes()
	{
		add_meta_box(
			'wsh_plugin_details',
			__('Plugin setup', 'wsh-license-manager'),
			array(__CLASS__, 'render_metabox'),
			'wsh_plugin',
			'normal',
			'high'
		);
	}

	public static function render_metabox($post)
	{
		wp_nonce_field('wsh_plugin_details', 'wsh_plugin_details_nonce');

		$slug = (string) get_post_meta($post->ID, 'wsh_plugin_slug', true);
		$version = (string) get_post_meta($post->ID, 'wsh_plugin_version', true);
		$summary = (string) get_post_meta($post->ID, 'wsh_plugin_summary', true);
		$landing_id = (int) get_post_meta($post->ID, '_wsh_landing_page_id', true);
		$landing = $landing_id > 0 ? get_post($landing_id) : null;
		$has_zip = WSH_Plugin_Storage::has_file($post->ID);
		?>
		<p><?php esc_html_e('The ZIP is stored outside the public uploads folder. Customers never see a direct file address. My Account gives them a personal download link that works only while their license is active.', 'wsh-license-manager'); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wsh_plugin_slug"><?php esc_html_e('Plugin slug', 'wsh-license-manager'); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="wsh_plugin_slug" name="wsh_plugin_slug" value="<?php echo esc_attr($slug); ?>" placeholder="wsh-views-counter-pro" required>
					<p class="description"><?php esc_html_e('Must match the WooCommerce product meta wsh_plugin_slug. The license is issued for this slug.', 'wsh-license-manager'); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wsh_plugin_version"><?php esc_html_e('Version', 'wsh-license-manager'); ?></label></th>
				<td><input type="text" class="regular-text" id="wsh_plugin_version" name="wsh_plugin_version" value="<?php echo esc_attr($version); ?>" placeholder="1.0.0"></td>
			</tr>
			<tr>
				<th scope="row"><label for="wsh_plugin_summary"><?php esc_html_e('Landing summary', 'wsh-license-manager'); ?></label></th>
				<td><textarea class="large-text" rows="4" id="wsh_plugin_summary" name="wsh_plugin_summary"><?php echo esc_textarea($summary); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Landing page', 'wsh-license-manager'); ?></th>
				<td>
					<?php if ($landing instanceof WP_Post) : ?>
						<?php $landing_status = get_post_status_object($landing->post_status); ?>
						<p>
							<a href="<?php echo esc_url(get_edit_post_link($landing->ID)); ?>"><?php echo esc_html(get_the_title($landing)); ?></a>
							— <?php echo esc_html($landing_status ? $landing_status->label : $landing->post_status); ?>
						</p>
						<?php if ($landing->post_status === 'publish') : ?>
							<p><a href="<?php echo esc_url(get_permalink($landing)); ?>" target="_blank" rel="noopener"><?php esc_html_e('View landing page', 'wsh-license-manager'); ?></a></p>
						<?php else : ?>
							<p class="description"><?php esc_html_e('The landing page stays a draft until you publish it.', 'wsh-license-manager'); ?></p>
						<?php endif; ?>
					<?php else : ?>
						<p class="description"><?php esc_html_e('A draft landing page is created the first time you save this plugin.', 'wsh-license-manager'); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wsh_plugin_zip"><?php esc_html_e('Plugin ZIP', 'wsh-license-manager'); ?></label></th>
				<td>
					<?php if ($has_zip) : ?>
						<p>
							<strong><?php echo esc_html(WSH_Plugin_Storage::original_name($post->ID)); ?></strong>
							— <?php esc_html_e('stored privately', 'wsh-license-manager'); ?>
						</p>
						<p><a class="button" href="<?php echo esc_url(WSH_Plugin_Storage::download_url($post->ID)); ?>"><?php esc_html_e('Test download', 'wsh-license-manager'); ?></a></p>
						<label>
							<input type="checkbox" name="wsh_plugin_zip_remove" value="1">
							<?php esc_html_e('Remove the current ZIP', 'wsh-license-manager'); ?>
						</label>
					<?php else : ?>
						<p class="description"><?php esc_html_e('No ZIP stored yet.', 'wsh-license-manager'); ?></p>
					<?php endif; ?>
					<p><input type="file" id="wsh_plugin_zip" name="wsh_plugin_zip" accept=".zip,application/zip"></p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save_plugin($post_id, $post)
	{
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (! isset($_POST['wsh_plugin_details_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wsh_plugin_details_nonce'])), 'wsh_plugin_details')) {
			return;
		}

		if (! current_user_can('manage_options')) {
			return;
		}

		$slug = isset($_POST['wsh_plugin_slug']) ? sanitize_title(wp_unslash($_POST['wsh_plugin_slug'])) : '';
		if ($slug === '') {
			$slug = sanitize_title($post->post_title);
		}

		$duplicate = get_posts(array(
			'post_type'      => 'wsh_plugin',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'post__not_in'   => array($post_id),
			'fields'         => 'ids',
			'meta_key'       => 'wsh_plugin_slug',
			'meta_value'     => $slug,
		));

		if ($slug === '' || ! empty($duplicate)) {
			self::notice(__('Plugin slug is missing or already used by another plugin.', 'wsh-license-manager'));
		} else {
			update_post_meta($post_id, 'wsh_plugin_slug', $slug);
		}

		$version = isset($_POST['wsh_plugin_version']) ? sanitize_text_field(wp_unslash($_POST['wsh_plugin_version'])) : '';
		$summary = isset($_POST['wsh_plugin_summary']) ? sanitize_textarea_field(wp_unslash($_POST['wsh_plugin_summary'])) : '';
		update_post_meta($post_id, 'wsh_plugin_version', $version);
		update_post_meta($post_id, 'wsh_plugin_summary', $summary);

		if (! empty($_POST['wsh_plugin_zip_remove'])) {
			WSH_Plugin_Storage::delete_file($post_id);
		}

		if (! empty($_FILES['wsh_plugin_zip']['name'])) {
			$result = WSH_Plugin_Storage::store_upload($post_id, $_FILES['wsh_plugin_zip']);
			if (is_wp_error($result)) {
				self::notice($result->get_error_message());
			}
		}

		self::ensure_landing_page($post_id, $post->post_title);
	}

	private static function ensure_landing_page($plugin_id, $title)
	{
		$existing_id = (int) get_post_meta($plugin_id, '_wsh_landing_page_id', true);
		if ($existing_id > 0 && get_post($existing_id) instanceof WP_Post) {
			return;
		}

		$slug = (string) get_post_meta($plugin_id, 'wsh_plugin_slug', true);
		$page_id = wp_insert_post(array(
			'post_type'    => 'page',
			'post_status'  => 'draft',
			'post_title'   => $title,
			'post_name'    => $slug !== '' ? $slug : sanitize_title($title),
			'post_content' => '',
		));

		if (! $page_id || is_wp_error($page_id)) {
			return;
		}

		update_post_meta($page_id, '_wsh_plugin_id', $plugin_id);
		update_post_meta($plugin_id, '_wsh_landing_page_id', $page_id);
	}

	public static function template_include($template)
	{
		if (! is_page()) {
			return $template;
		}

		$plugin_id = (int) get_post_meta(get_queried_object_id(), '_wsh_plugin_id', true);
		if ($plugin_id <= 0 || get_post_type($plugin_id) !== 'wsh_plugin') {
			return $template;
		}

		$landing = WSH_LICENSE_MANAGER_PATH . 'templates/plugin-landing.php';

		return file_exists($landing) ? $landing : $template;
	}

	public static function delete_private_file($post_id)
	{
		if (get_post_type($post_id) !== 'wsh_plugin') {
			return;
		}

		WSH_Plugin_Storage::delete_file($post_id);
	}

	public static function admin_columns($columns)
	{
		$new = array();

		foreach ($columns as $key => $label) {
			$new[$key] = $label;
			if ($key === 'title') {
				$new['wsh_slug'] = __('Slug', 'wsh-license-manager');
				$new['wsh_landing'] = __('Landing', 'wsh-license-manager');
				$new['wsh_zip'] = __('ZIP', 'wsh-license-manager');
			}
		}

		return $new;
	}

	public static function render_admin_column($column, $post_id)
	{
		if ($column === 'wsh_slug') {
			echo esc_html((string) get_post_meta($post_id, 'wsh_plugin_slug', true));
			return;
		}

		if ($column === 'wsh_landing') {
			$landing_id = (int) get_post_meta($post_id, '_wsh_landing_page_id', true);
			$landing = $landing_id > 0 ? get_post($landing_id) : null;
			if ($landing instanceof WP_Post) {
				$landing_status = get_post_status_object($landing->post_status);
				echo esc_html($landing_status ? $landing_status->label : $landing->post_status);
			} else {
				esc_html_e('Missing', 'wsh-license-manager');
			}
			return;
		}

		if ($column === 'wsh_zip') {
			echo WSH_Plugin_Storage::has_file($post_id)
				? esc_html__('Protected', 'wsh-license-manager')
				: esc_html__('None', 'wsh-license-manager');
		}
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
			$updated['plugin-files'] = __('Plugins', 'wsh-license-manager');
		}

		return $updated;
	}

	public static function render_account_downloads()
	{
		$user = wp_get_current_user();
		$licenses = array();

		if ($user instanceof WP_User && $user->user_email !== '') {
			$licenses = get_posts(array(
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

		echo '<h2>' . esc_html__('Your plugins', 'wsh-license-manager') . '</h2>';

		if (empty($licenses)) {
			echo '<p>' . esc_html__('You do not have an active plugin license yet.', 'wsh-license-manager') . '</p>';
			return;
		}

		echo '<table class="shop_table shop_table_responsive"><thead><tr>';
		echo '<th>' . esc_html__('Plugin', 'wsh-license-manager') . '</th>';
		echo '<th>' . esc_html__('License key', 'wsh-license-manager') . '</th>';
		echo '<th>' . esc_html__('Download', 'wsh-license-manager') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($licenses as $license) {
			$slug = (string) get_post_meta($license->ID, 'wsh_product_slug', true);
			$key = (string) get_post_meta($license->ID, 'wsh_license_key', true);
			$plugin = self::find_by_slug($slug);
			$name = $plugin instanceof WP_Post ? get_the_title($plugin) : $slug;

			echo '<tr>';
			echo '<td>' . esc_html($name) . '</td>';
			echo '<td><code>' . esc_html($key) . '</code></td>';
			echo '<td>';
			if ($plugin instanceof WP_Post && WSH_Plugin_Storage::user_can_download(get_current_user_id(), $plugin->ID)) {
				echo '<a class="button" href="' . esc_url(WSH_Plugin_Storage::download_url($plugin->ID)) . '">' . esc_html__('Download', 'wsh-license-manager') . '</a>';
			} else {
				esc_html_e('Not available yet', 'wsh-license-manager');
			}
			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	public static function find_by_slug($slug)
	{
		if ($slug === '') {
			return null;
		}

		$posts = get_posts(array(
			'post_type'      => 'wsh_plugin',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => 'wsh_plugin_slug',
			'meta_value'     => $slug,
		));

		return ! empty($posts) ? $posts[0] : null;
	}

	private static function notice($message)
	{
		set_transient('wsh_plugin_notice_' . get_current_user_id(), $message, 60);
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

add_filter('template_include', array('WSH_Plugin_Catalog', 'template_include'));
