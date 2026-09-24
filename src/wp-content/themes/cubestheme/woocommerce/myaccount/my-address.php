<?php
defined('ABSPATH') || exit;

$customer_id = get_current_user_id();

if (!wc_ship_to_billing_address_only() && wc_shipping_enabled()) {
	$get_addresses = apply_filters(
		'woocommerce_my_account_get_addresses',
		array(
			'billing'  => __('Billing address', 'woocommerce'),
			'shipping' => __('Shipping address', 'woocommerce'),
		),
		$customer_id
	);
} else {
	$get_addresses = apply_filters(
		'woocommerce_my_account_get_addresses',
		array(
			'billing' => __('Billing address', 'woocommerce'),
		),
		$customer_id
	);
}
?>

<section class="account-panel">
	<div class="account-intro">
		<div>
			<span class="account-kicker"><?php esc_html_e('Addresses', 'cubestheme'); ?></span>
			<h2><?php esc_html_e('Billing and shipping', 'cubestheme'); ?></h2>
			<p><?php echo esc_html(apply_filters('woocommerce_my_account_my_address_description', __('These addresses are used on checkout and on subscription renewals.', 'cubestheme'))); ?></p>
		</div>
	</div>

	<div class="account-address-grid">
		<?php foreach ($get_addresses as $name => $address_title) : ?>
			<?php $address = wc_get_account_formatted_address($name); ?>
			<article class="account-address">
				<header>
					<h3><?php echo esc_html($address_title); ?></h3>
					<a href="<?php echo esc_url(wc_get_endpoint_url('edit-address', $name)); ?>">
						<?php echo $address ? esc_html__('Edit', 'cubestheme') : esc_html__('Add', 'cubestheme'); ?>
					</a>
				</header>
				<address>
					<?php
					echo $address ? wp_kses_post($address) : esc_html__('You have not set up this type of address yet.', 'woocommerce');
					do_action('woocommerce_my_account_after_my_address', $name);
					?>
				</address>
			</article>
		<?php endforeach; ?>
	</div>
</section>
