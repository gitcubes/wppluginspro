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

		// Try to read site URL from subscription meta.
		// First from our own key (if we ikada budemo upisivali), then from billing field.
		$site_url = $subscription->get_meta('_wsh_site_url', true);

		if (empty($site_url)) {
			$site_url = $subscription->get_meta('_billing_wsh_site_url', true);
		}

		// Normalize to canonical host (no www, lowercase, etc.)
		$site_url = WSH_License_Utils::normalize_site($site_url);

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

			// For now, treat every subscription product as a license product.
			$plugin_slug = get_post_meta($product_id, 'wsh_plugin_slug', true);
			if (empty($plugin_slug)) {
				$plugin_slug = 'wsh-views-counter-pro';
			}

			$max_sites = (int) get_post_meta($product_id, 'wsh_max_sites', true);
			if ($max_sites <= 0) {
				$max_sites = 1;
			}

			self::maybe_create_license(
				$subscription,
				$subscription_id,
				$product_id,
				$plugin_slug,
				$max_sites,
				$customer_email,
				$site_url
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
	protected static function maybe_create_license($subscription, $subscription_id, $product_id, $plugin_slug, $max_sites, $customer_email, $site_url)
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
			update_post_meta($license_id, 'wsh_max_sites', $max_sites);
			update_post_meta($license_id, 'wsh_customer_email', $customer_email);
			update_post_meta($license_id, 'wsh_status', 'active');

			// Store the site URL this license is bound to.
			if (! empty($site_url)) {
				update_post_meta($license_id, 'wsh_site_url', $site_url);
			}

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


// Copy site URL from order (billing field) to subscription meta.
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
