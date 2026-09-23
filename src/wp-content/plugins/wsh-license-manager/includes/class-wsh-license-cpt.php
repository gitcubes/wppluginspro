<?php
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Registers the wsh_license custom post type used for storing license keys.
 */
class WSH_License_CPT
{

	public static function init()
	{
		add_action('init', array(__CLASS__, 'register_cpt'));
		add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));

		// Admin list columns
		add_filter('manage_edit-wsh_license_columns', array(__CLASS__, 'admin_columns'));
		add_action('manage_wsh_license_posts_custom_column', array(__CLASS__, 'render_admin_column'), 10, 2);

		// Sortable columns
		add_filter('manage_edit-wsh_license_sortable_columns', array(__CLASS__, 'sortable_columns'));
		add_action('pre_get_posts', array(__CLASS__, 'handle_sorting'));

		// Small admin CSS for badges
		add_action('admin_head', array(__CLASS__, 'admin_badge_css'));

		// RESET SITE
		add_action( 'admin_post_wsh_reset_site', array( __CLASS__, 'handle_reset_site_action' ) );
	}

	/**
	 * Register custom post type "wsh_license".
	 */
	public static function register_cpt()
	{

		$labels = array(
			'name'               => __('Licenses', 'wsh-license-manager'),
			'singular_name'      => __('License', 'wsh-license-manager'),
			'add_new'            => __('Add New', 'wsh-license-manager'),
			'add_new_item'       => __('Add New License', 'wsh-license-manager'),
			'edit_item'          => __('Edit License', 'wsh-license-manager'),
			'new_item'           => __('New License', 'wsh-license-manager'),
			'all_items'          => __('Licenses', 'wsh-license-manager'),
			'view_item'          => __('View License', 'wsh-license-manager'),
			'search_items'       => __('Search Licenses', 'wsh-license-manager'),
			'not_found'          => __('No licenses found', 'wsh-license-manager'),
			'not_found_in_trash' => __('No licenses found in Trash', 'wsh-license-manager'),
			'menu_name'          => __('WSH Licenses', 'wsh-license-manager'),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'supports'           => array('title'),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'show_in_rest'       => false,
		);

		register_post_type('wsh_license', $args);
	}

	/**
	 * Add meta boxes for License edit screen.
	 */
	public static function add_meta_boxes()
	{
		add_meta_box(
			'wsh_license_details',
			__('License Details', 'wsh-license-manager'),
			array(__CLASS__, 'render_license_details_metabox'),
			'wsh_license',
			'normal',
			'high'
		);
	}

	/**
	 * Define admin columns for wsh_license list table.
	 */
	public static function admin_columns($columns)
	{

		// Keep checkbox + title, but rename title label a bit.
		$new = array();

		foreach ($columns as $key => $label) {
			if ('title' === $key) {
				$new['title'] = __('License Key', 'wsh-license-manager');
				$new['wsh_plugin'] = __('Plugin', 'wsh-license-manager');
				$new['wsh_site']   = __('Site', 'wsh-license-manager');
				$new['wsh_status'] = __('Status', 'wsh-license-manager');
				$new['wsh_expires'] = __('Subscription', 'wsh-license-manager');
				$new['wsh_email']  = __('Customer email', 'wsh-license-manager');
			} else {
				$new[$key] = $label;
			}
		}

		// If for some reason title wasn't present, ensure our columns exist.
		if (! isset($new['wsh_site'])) {
			$new['wsh_plugin']  = __('Plugin', 'wsh-license-manager');
			$new['wsh_site']    = __('Site', 'wsh-license-manager');
			$new['wsh_status']  = __('Status', 'wsh-license-manager');
			$new['wsh_expires'] = __('Subscription', 'wsh-license-manager');
			$new['wsh_email']   = __('Customer email', 'wsh-license-manager');
		}

		return $new;
	}

	/**
	 * Render admin column values.
	 */
	public static function render_admin_column($column, $post_id)
	{

		switch ($column) {

			case 'wsh_plugin':
				$product_id = (int) get_post_meta($post_id, 'wsh_product_id', true);
				$label = self::plugin_label($post_id);
				$edit_url = $product_id > 0 ? get_edit_post_link($product_id) : '';
				if ($edit_url) {
					echo '<a href="' . esc_url($edit_url) . '">' . esc_html($label) . '</a>';
				} else {
					echo esc_html($label);
				}
				break;

			case 'wsh_site':
				$sites = WSH_License_Utils::activated_sites($post_id);
				$limit = WSH_License_Utils::max_sites($post_id);
				$limit_label = 0 === $limit ? __('unlimited', 'wsh-license-manager') : (string) $limit;
				if (! $sites) {
					echo '—';
				} else {
					echo '<code>' . esc_html($sites[0]) . '</code>';
					if (count($sites) > 1) {
						echo ' +' . (int) (count($sites) - 1);
					}
				}
				echo '<br><span class="wsh-lic-muted">' . esc_html(count($sites) . ' / ' . $limit_label) . '</span>';
				break;

			case 'wsh_status':
				$status = (string) get_post_meta($post_id, 'wsh_status', true);
				$status = $status !== '' ? $status : '—';

				$cls = 'wsh-badge wsh-badge--default';
				if ('active' === $status || 'valid' === $status) {
					$cls = 'wsh-badge wsh-badge--green';
				} elseif ('expired' === $status) {
					$cls = 'wsh-badge wsh-badge--red';
				} elseif ('disabled' === $status) {
					$cls = 'wsh-badge wsh-badge--gray';
				} elseif ('on_hold' === $status || 'on-hold' === $status) {
					$cls = 'wsh-badge wsh-badge--yellow';
				}

				echo '<span class="' . esc_attr($cls) . '">' . esc_html($status) . '</span>';
				break;

			case 'wsh_expires':
				echo esc_html(self::subscription_expires_label($post_id));
				break;

			case 'wsh_email':
				$email = (string) get_post_meta($post_id, 'wsh_customer_email', true);
				if ($email !== '') {
					echo '<a href="' . esc_url('mailto:' . $email) . '">' . esc_html($email) . '</a>';
				} else {
					echo '—';
				}
				break;
		}
	}

	/**
	 * Make some columns sortable.
	 */
	public static function sortable_columns($columns)
	{
		$columns['wsh_status']  = 'wsh_status';
		$columns['wsh_expires'] = 'wsh_expires_at';
		$columns['wsh_email']   = 'wsh_customer_email';
		$columns['wsh_site']    = 'wsh_site_url';
		return $columns;
	}

	private static function plugin_label($post_id)
	{
		$group = (string) get_post_meta($post_id, 'wsh_license_group', true);
		$groups = array(
			'news'      => __('News Suite', 'wsh-license-manager'),
			'ecommerce' => __('Ecommerce Suite', 'wsh-license-manager'),
			'all'       => __('All-Access', 'wsh-license-manager'),
		);

		if (isset($groups[$group])) {
			return $groups[$group];
		}

		$product_id = (int) get_post_meta($post_id, 'wsh_product_id', true);
		if ($product_id > 0) {
			$title = get_the_title($product_id);
			if ($title !== '') {
				return $title;
			}
		}

		$slug = (string) get_post_meta($post_id, 'wsh_product_slug', true);

		return $slug !== '' ? $slug : '—';
	}

	private static function subscription_expires_label($post_id)
	{
		$subscription_id = (int) get_post_meta($post_id, 'wsh_subscription_id', true);

		if ($subscription_id > 0 && function_exists('wcs_get_subscription')) {
			$subscription = wcs_get_subscription($subscription_id);

			if ($subscription instanceof WC_Subscription) {
				$end = (string) $subscription->get_date('end');
				if ($end !== '' && $end !== '0') {
					return date_i18n(get_option('date_format'), strtotime($end));
				}

				$next = (string) $subscription->get_date('next_payment');
				if ($next !== '' && $next !== '0') {
					return sprintf(
						__('Renews %s', 'wsh-license-manager'),
						date_i18n(get_option('date_format'), strtotime($next))
					);
				}
			}
		}

		$stored = (string) get_post_meta($post_id, 'wsh_expires_at', true);
		if ($stored !== '') {
			$timestamp = strtotime($stored);

			return $timestamp ? date_i18n(get_option('date_format'), $timestamp) : $stored;
		}

		return '—';
	}

	/**
	 * Apply sorting by meta values.
	 */
	public static function handle_sorting($query)
	{

		if (! is_admin() || ! $query->is_main_query()) {
			return;
		}

		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (! $screen || 'edit-wsh_license' !== $screen->id) {
			return;
		}

		$orderby = (string) $query->get('orderby');

		$meta_map = array(
			'wsh_status'         => array('key' => 'wsh_status', 'type' => 'CHAR'),
			'wsh_expires_at'     => array('key' => 'wsh_expires_at', 'type' => 'CHAR'), // Y-m-d sorts fine as string
			'wsh_customer_email' => array('key' => 'wsh_customer_email', 'type' => 'CHAR'),
			'wsh_site_url'       => array('key' => 'wsh_site_url', 'type' => 'CHAR'),
		);

		if (isset($meta_map[$orderby])) {
			$query->set('meta_key', $meta_map[$orderby]['key']);
			$query->set('orderby', 'meta_value');

			// If you ever store numeric meta and need numeric sort:
			// $query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Minimal CSS for status badges in admin list.
	 */
	public static function admin_badge_css()
	{
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (! $screen || 'edit-wsh_license' !== $screen->id) {
			return;
		}
	?>
		<style>
			.wsh-badge {
				display: inline-block;
				padding: 2px 8px;
				border-radius: 999px;
				font-size: 12px;
				line-height: 18px;
				border: 1px solid rgba(0, 0, 0, .08);
				background: #f6f7f7;
				color: #1d2327;
				text-transform: capitalize;
			}

			.wsh-badge--green {
				background: #edfaef;
				border-color: #b7e3c0;
				color: #0a5d1a;
			}

			.wsh-badge--red {
				background: #ffecec;
				border-color: #f5b5b5;
				color: #8a1f1f;
			}

			.wsh-badge--gray {
				background: #f1f1f1;
				border-color: #d5d5d5;
				color: #555;
			}

			.wsh-badge--yellow {
				background: #fff7e6;
				border-color: #f2d49b;
				color: #6b4e00;
			}
		</style>
	<?php
	}


	/**
	 * Render License Details metabox.
	 *
	 * @param WP_Post $post
	 */
	public static function render_license_details_metabox($post)
	{

		$license_id = $post->ID;

		$license_key      = (string) get_post_meta($license_id, 'wsh_license_key', true);
		$status           = (string) get_post_meta($license_id, 'wsh_status', true);
		$expires_at       = (string) get_post_meta($license_id, 'wsh_expires_at', true);
		$sites            = WSH_License_Utils::activated_sites($license_id);
		$max_sites        = WSH_License_Utils::max_sites($license_id);
		$customer_email   = (string) get_post_meta($license_id, 'wsh_customer_email', true);
		$product_id       = (int)    get_post_meta($license_id, 'wsh_product_id', true);
		$product_slug     = (string) get_post_meta($license_id, 'wsh_product_slug', true);
		$subscription_id  = (int)    get_post_meta($license_id, 'wsh_subscription_id', true);
		$last_activation  = (string) get_post_meta($license_id, 'wsh_last_activation', true);
		$last_deactivation = (string) get_post_meta($license_id, 'wsh_last_deactivation', true);

		// Purchased date (best effort):
		// 1) From subscription start date if Subscriptions exists
		// 2) Fallback to post publish date
		$purchased = '';

		if ($subscription_id > 0 && function_exists('wcs_get_subscription')) {
			$sub = wcs_get_subscription($subscription_id);
			if ($sub && is_object($sub) && method_exists($sub, 'get_date')) {
				$start = $sub->get_date('start');
				if ($start) {
					$purchased = $start; // usually MySQL datetime string
				}
			}
		}

		if ($purchased === '') {
			$purchased = get_the_date('Y-m-d H:i:s', $license_id);
		}

		// Pretty formatting
		$status     = $status !== '' ? $status : '—';
		$expires_at = $expires_at !== '' ? $expires_at : '—';
		$site_limit_label = 0 === $max_sites ? __('unlimited', 'wsh-license-manager') : (string) $max_sites;
		$customer_email = $customer_email !== '' ? $customer_email : '—';
		$product_slug   = $product_slug !== '' ? $product_slug : '—';
		$license_key    = $license_key !== '' ? $license_key : $post->post_title;

	?>
		<style>
			.wsh-lic-grid {
				display: grid;
				grid-template-columns: 220px 1fr;
				gap: 10px 16px;
			}

			.wsh-lic-grid code {
				font-size: 12px;
			}

			.wsh-lic-muted {
				color: #666;
			}

			.wsh-lic-actions {
				margin-top: 12px;
			}
		</style>

		<div class="wsh-lic-grid">
			<div><strong><?php esc_html_e('License Key', 'wsh-license-manager'); ?></strong></div>
			<?php
			if ( isset($_GET['wsh_site_reset']) ) {
				echo '<div class="notice notice-success is-dismissible"><p>License site binding has been reset.</p></div>';
			}
			?>
			<div>
				<input type="text" class="widefat" readonly value="<?php echo esc_attr($license_key); ?>" />
				<div class="wsh-lic-muted"><?php esc_html_e('Readonly. This is the key customers use in the plugin.', 'wsh-license-manager'); ?></div>
			</div>

			<div><strong><?php esc_html_e('Status', 'wsh-license-manager'); ?></strong></div>
			<div><code><?php echo esc_html($status); ?></code></div>

			<div><strong><?php esc_html_e('Activated sites', 'wsh-license-manager'); ?></strong></div>
			<div>
				<?php if ($sites) : ?>
					<?php foreach ($sites as $site) : ?>
						<code><?php echo esc_html($site); ?></code><br>
					<?php endforeach; ?>
				<?php else : ?>
					—
				<?php endif; ?>
				<div class="wsh-lic-muted">
					<?php echo esc_html(sprintf(__('%1$d of %2$s slots used. Sites are added when the plugin is activated.', 'wsh-license-manager'), count($sites), $site_limit_label)); ?>
				</div>
			</div>

			<div><strong><?php esc_html_e('Customer Email', 'wsh-license-manager'); ?></strong></div>
			<div>
				<?php if ($customer_email !== '—') : ?>
					<a href="<?php echo esc_url('mailto:' . $customer_email); ?>"><?php echo esc_html($customer_email); ?></a>
				<?php else : ?>
					<?php echo esc_html($customer_email); ?>
				<?php endif; ?>
			</div>

			<div><strong><?php esc_html_e('Product', 'wsh-license-manager'); ?></strong></div>
			<div>
				<code><?php echo esc_html($product_slug); ?></code>
				<?php if ($product_id > 0) : ?>
					<span class="wsh-lic-muted">(#<?php echo (int) $product_id; ?>)</span>
					<?php
					$edit_link = get_edit_post_link($product_id);
					if ($edit_link) {
						echo ' <a href="' . esc_url($edit_link) . '">' . esc_html__('Edit product', 'wsh-license-manager') . '</a>';
					}
					?>
				<?php endif; ?>
			</div>

			<div><strong><?php esc_html_e('Subscription ID', 'wsh-license-manager'); ?></strong></div>
			<div>
				<?php if ($subscription_id > 0) : ?>
					<code>#<?php echo (int) $subscription_id; ?></code>
					<?php
					$sub_link = get_edit_post_link($subscription_id);
					if ($sub_link) {
						echo ' <a href="' . esc_url($sub_link) . '">' . esc_html__('Open subscription', 'wsh-license-manager') . '</a>';
					}
					?>
				<?php else : ?>
					—
				<?php endif; ?>
			</div>

			<div><strong><?php esc_html_e('Purchased', 'wsh-license-manager'); ?></strong></div>
			<div><code><?php echo esc_html($purchased); ?></code></div>

			<div><strong><?php esc_html_e('Expires', 'wsh-license-manager'); ?></strong></div>
			<div><code><?php echo esc_html($expires_at); ?></code></div>

			<div><strong><?php esc_html_e('Last activation', 'wsh-license-manager'); ?></strong></div>
			<div><code><?php echo esc_html($last_activation !== '' ? $last_activation : '—'); ?></code></div>

			<div><strong><?php esc_html_e('Last deactivation', 'wsh-license-manager'); ?></strong></div>
			<div><code><?php echo esc_html($last_deactivation !== '' ? $last_deactivation : '—'); ?></code></div>

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<hr />

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				onsubmit="return confirm('Clear every activated site on this license? The customer can activate again, up to the slot limit.');">

				<input type="hidden" name="action" value="wsh_reset_site" />
				<input type="hidden" name="wsh_reset_site" value="1" />
				<input type="hidden" name="post_ID" value="<?php echo (int) $license_id; ?>" />
				<?php wp_nonce_field( 'wsh_reset_site_' . $license_id, '_wsh_reset_nonce' ); ?>

				<p>
					<button type="submit" class="button button-secondary">
						<?php esc_html_e( 'Clear activated sites', 'wsh-license-manager' ); ?>
					</button>
					<span class="description">
						<?php esc_html_e( 'Frees every slot. The license key and the slot limit stay.', 'wsh-license-manager' ); ?>
					</span>
				</p>
			</form>
			<?php endif; ?>
		</div>

	<?php
	}


	/**
	 * Generate a random license key string.
	 *
	 * @return string
	 */
	public static function generate_license_key()
	{
		return strtoupper(wp_generate_password(24, false, false));
	}

	/**
	 * Handle admin "Reset Site" action for license.
	 */
	public static function handle_reset_site_action()
	{

		if (! is_admin() || ! current_user_can('manage_options')) {
			return;
		}

		if (
			! isset($_POST['wsh_reset_site'], $_POST['post_ID'], $_POST['_wsh_reset_nonce'])
		) {
			return;
		}

		$license_id = (int) $_POST['post_ID'];

		if (! wp_verify_nonce($_POST['_wsh_reset_nonce'], 'wsh_reset_site_' . $license_id)) {
			wp_die('Security check failed.');
		}

		// Make sure this is a license post.
		if (get_post_type($license_id) !== 'wsh_license') {
			return;
		}

		delete_post_meta($license_id, 'wsh_site_url');
		delete_post_meta($license_id, 'wsh_sites');
		delete_post_meta($license_id, 'wsh_last_activation');

		// Optional: log reset time
		update_post_meta($license_id, 'wsh_last_reset', current_time('mysql'));

		// Redirect back with notice
		wp_safe_redirect(
			add_query_arg(
				array(
					'post'            => $license_id,
					'action'          => 'edit',
					'wsh_site_reset'  => 1,
				),
				admin_url('post.php')
			)
		);
		exit;
	}
}
