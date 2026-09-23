<?php
if (! defined('ABSPATH')) {
	exit;
}

/**
 * WooCommerce / Subscriptions integration for generating licenses.
 */
class WSH_License_WooCommerce
{

	public static function init()
	{
		// Trigger when a subscription status changes to "active".
		add_action('woocommerce_subscription_status_active', array(__CLASS__, 'handle_subscription_active'), 10, 1);
		add_action('woocommerce_subscription_status_updated', array(__CLASS__, 'handle_subscription_status_updated'), 10, 3);
	}

	/**
	 * Handle subscription activation.
	 *
	 * @param WC_Subscription $subscription Subscription object.
	 */
	public static function handle_subscription_active($subscription)
	{

		// Make sure we have a valid subscription object.
		if (! class_exists('WC_Subscription') || ! $subscription instanceof WC_Subscription) {
			return;
		}

		$subscription_id = $subscription->get_id();
		$customer_email  = $subscription->get_billing_email();

		// Sites are attached when the plugin is activated, not at checkout.
		// A license that already exists is left untouched, including its bound site.

		// Loop through subscription items and generate license for each item.
		foreach ($subscription->get_items('line_item') as $item_id => $item) {

			// In modern WooCommerce, this will be WC_Order_Item_Product.
			if (method_exists($item, 'get_product_id')) {
				$product_id = (int) $item->get_product_id();
			} else {
				// Fallback for older versions / unexpected item types.
				$product_id = is_array($item) && isset($item['product_id'])
					? (int) $item['product_id']
					: 0;
			}

			if ($product_id <= 0) {
				continue;
			}

			$variation_id = method_exists($item, 'get_variation_id') ? (int) $item->get_variation_id() : 0;
			$parent_id = $product_id;

			$plugin_slug = (string) get_post_meta($parent_id, 'wsh_plugin_slug', true);
			$license_group = (string) get_post_meta($parent_id, 'wsh_license_group', true);
			if ($plugin_slug === '' && $license_group === '') {
				$plugin_slug = 'wsh-views-counter-pro';
			}

			$max_sites = self::read_max_sites($variation_id > 0 ? $variation_id : $parent_id, $parent_id);

			self::maybe_create_license(
				$subscription,
				$subscription_id,
				$parent_id,
				$plugin_slug,
				$max_sites,
				$customer_email,
				$license_group
			);
		}
	}

	/**
	 * Sync license status with subscription status changes.
	 *
	 * @param WC_Subscription $subscription
	 * @param string $new_status
	 * @param string $old_status
	 */
	public static function handle_subscription_status_updated($subscription, $new_status, $old_status)
	{
		if (! class_exists('WC_Subscription') || ! $subscription instanceof WC_Subscription) {
			return;
		}

		$subscription_id = $subscription->get_id();

		// Find all licenses for this subscription
		$licenses = get_posts(array(
			'post_type'      => 'wsh_license',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => 'wsh_subscription_id',
			'meta_value'     => $subscription_id,
		));

		if (empty($licenses)) {
			return;
		}

		// Map subscription -> license status
		$map = array(
			'active'    => 'active',
			'on-hold'   => 'disabled',
			'cancelled' => 'disabled',
			'expired'   => 'expired',
		);

		if (! isset($map[$new_status])) {
			return;
		}

		$new_license_status = $map[$new_status];

		foreach ($licenses as $license_id) {
			update_post_meta($license_id, 'wsh_status', $new_license_status);
		}
	}


	/**
	 * Create license for subscription/product if not already created.
	 *
	 * @param WC_Subscription $subscription     Subscription object.
	 * @param int             $subscription_id  Subscription ID.
	 * @param int             $product_id       Product ID.
	 * @param string          $plugin_slug      Plugin slug (e.g. wsh-views-counter-pro).
	 * @param int             $max_sites       Max number of sites allowed.
	 * @param string          $customer_email   Customer email.
	 */
	protected static function read_max_sites($source_id, $parent_id)
	{
		$raw = get_post_meta($source_id, 'wsh_max_sites', true);
		if ($raw === '' && $source_id !== $parent_id) {
			$raw = get_post_meta($parent_id, 'wsh_max_sites', true);
		}

		if ($raw === '' || $raw === false) {
			return 1;
		}

		return (int) $raw;
	}

	protected static function maybe_create_license($subscription, $subscription_id, $product_id, $plugin_slug, $max_sites, $customer_email, $license_group = '')
	{

		// Check if license already exists for this subscription + product.
		$existing = get_posts(
			array(
				'post_type'      => 'wsh_license',
				'post_status'    => 'any',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => 'wsh_subscription_id',
						'value' => $subscription_id,
					),
					array(
						'key'   => 'wsh_product_id',
						'value' => $product_id,
					),
				),
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);

		if (! empty($existing)) {
			// License already exists, do nothing.
			return;
		}

		// Generate new license key.
		$license_key = WSH_License_CPT::generate_license_key();

		// Create license post.
		$license_id = wp_insert_post(
			array(
				'post_type'   => 'wsh_license',
				'post_status' => 'publish',
				'post_title'  => $license_key,
			)
		);

		if ($license_id && ! is_wp_error($license_id)) {

			update_post_meta($license_id, 'wsh_license_key', $license_key);
			update_post_meta($license_id, 'wsh_subscription_id', $subscription_id);
			update_post_meta($license_id, 'wsh_product_id', $product_id);
			update_post_meta($license_id, 'wsh_product_slug', $plugin_slug);
			update_post_meta($license_id, 'wsh_license_group', $license_group);
			update_post_meta($license_id, 'wsh_max_sites', $max_sites);
			update_post_meta($license_id, 'wsh_customer_email', $customer_email);
			update_post_meta($license_id, 'wsh_status', 'active');

			// Optional: set expiry date based on subscription end date (if available).
			if ($subscription instanceof WC_Subscription) {
				$end_date = $subscription->get_date('end');
				if ($end_date) {
					// Store only Y-m-d part.
					update_post_meta($license_id, 'wsh_expires_at', substr($end_date, 0, 10));
				}
			}
		}
	}
}


// Older orders may still carry a site URL. Keep it on the subscription for support.
// New licenses do not copy it; the customer activates sites from the plugin.
add_action('wcs_checkout_subscription_created', function ($subscription, $order) {

	if (! $subscription instanceof WC_Subscription || ! $order instanceof WC_Order) {
		return;
	}

	$site_url_normalized = '';

	// Try both keys (some WC setups store custom billing fields without underscore).
	$site_url = $order->get_meta('_billing_wsh_site_url', true);
	if (empty($site_url)) {
		$site_url = $order->get_meta('billing_wsh_site_url', true);
	}

	if (! empty($site_url)) {
		$site_url_normalized = WSH_License_Utils::normalize_site($site_url);
	}

	if ('' !== $site_url_normalized) {
		update_post_meta($subscription->get_id(), '_wsh_site_url', $site_url_normalized);
	}
}, 10, 2);
