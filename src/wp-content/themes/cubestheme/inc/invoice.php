<?php
if (!defined('ABSPATH')) {
	exit;
}

function cubestheme_invoice_url($order)
{
	return add_query_arg('wsh_invoice', $order->get_id(), wc_get_account_endpoint_url('orders'));
}

function cubestheme_order_invoice_action($actions, $order)
{
	if (!$order instanceof WC_Order || !$order->has_status(array('processing', 'completed', 'on-hold'))) {
		return $actions;
	}

	$actions['invoice'] = array(
		'url' => cubestheme_invoice_url($order),
		'name' => __('Invoice', 'cubestheme'),
	);

	return $actions;
}

add_filter('woocommerce_my_account_my_orders_actions', 'cubestheme_order_invoice_action', 20, 2);

function cubestheme_invoice_request()
{
	if (empty($_GET['wsh_invoice']) || !function_exists('wc_get_order')) {
		return;
	}

	if (!is_user_logged_in()) {
		auth_redirect();
	}

	$order = wc_get_order(absint($_GET['wsh_invoice']));
	$allowed = $order instanceof WC_Order && (
		(int) $order->get_user_id() === get_current_user_id()
		|| current_user_can('manage_woocommerce')
	);
	if (!$allowed || !$order->has_status(array('processing', 'completed', 'on-hold'))) {
		wp_die(esc_html__('This invoice is not available.', 'cubestheme'), esc_html__('Invoice', 'cubestheme'), array('response' => 403));
	}

	cubestheme_render_invoice($order);
	exit;
}

add_action('template_redirect', 'cubestheme_invoice_request');

function cubestheme_invoice_number($order)
{
	$number = (string) $order->get_meta('_wsh_invoice_number');
	if ($number === '') {
		$number = 'INV-' . $order->get_order_number();
		$order->update_meta_data('_wsh_invoice_number', $number);
		$order->update_meta_data('_wsh_invoice_issued', current_time('mysql'));
		$order->save();
	}

	return $number;
}

function cubestheme_invoice_vat($order)
{
	$vat = (string) $order->get_meta('VAT ID');
	if ($vat === '') {
		$vat = (string) $order->get_meta('_wc_billing/cubestheme/vat-id');
	}

	return $vat;
}

function cubestheme_invoice_seller()
{
	$lines = array();
	$name = trim((string) get_option('cubestheme_company_name_text'));
	$lines[] = $name !== '' ? $name : get_bloginfo('name');

	$address = trim((string) get_option('cubestheme_company_address_text'));
	if ($address !== '') {
		$lines[] = $address;
	} else {
		$store = array_filter(array(
			get_option('woocommerce_store_address'),
			get_option('woocommerce_store_address_2'),
			trim(get_option('woocommerce_store_postcode') . ' ' . get_option('woocommerce_store_city')),
		));
		$lines = array_merge($lines, $store);
	}

	$email = trim((string) get_option('cubestheme_company_email_text'));
	$lines[] = $email !== '' ? $email : 'info@wppluginspro.io';
	$lines[] = 'wppluginspro.io';

	return $lines;
}

function cubestheme_render_invoice($order)
{
	$number = cubestheme_invoice_number($order);
	$issued = (string) $order->get_meta('_wsh_invoice_issued');
	$issued_label = $issued !== '' ? mysql2date('F j, Y', $issued) : $order->get_date_created()->date_i18n('F j, Y');
	$vat = cubestheme_invoice_vat($order);
	$seller = cubestheme_invoice_seller();
	$customer = array_filter(array(
		trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
		$order->get_billing_company(),
		$vat !== '' ? sprintf(__('VAT ID: %s', 'cubestheme'), $vat) : '',
		$order->get_billing_address_1(),
		trim($order->get_billing_postcode() . ' ' . $order->get_billing_city()),
		$order->get_billing_country() ? WC()->countries->countries[$order->get_billing_country()] ?? $order->get_billing_country() : '',
		$order->get_billing_email(),
	));

	nocache_headers();
	header('Content-Type: text/html; charset=UTF-8');
	?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?php echo esc_html($number); ?></title>
	<style>
		body { margin: 0; background: #eef3fb; color: #1b1b21; font-family: Helvetica, Arial, sans-serif; }
		.toolbar { max-width: 800px; margin: 24px auto 0; padding: 0 16px; text-align: right; }
		.toolbar button { border: 0; border-radius: 999px; background: #034dd3; color: #fff; font: 600 14px Helvetica, Arial, sans-serif; padding: 12px 18px; cursor: pointer; }
		.sheet { max-width: 800px; margin: 16px auto 40px; background: #fff; border-radius: 16px; overflow: hidden; }
		.bar { background: #034dd3; color: #fff; padding: 18px 32px; font-size: 18px; font-weight: 700; }
		.pad { padding: 32px; }
		h1 { margin: 0 0 8px; font-size: 28px; }
		.meta { margin: 0 0 28px; color: #5c6570; }
		.columns { display: flex; gap: 32px; margin-bottom: 28px; }
		.columns div { flex: 1; }
		h2 { margin: 0 0 8px; font-size: 12px; letter-spacing: 0.04em; text-transform: uppercase; color: #5c6570; }
		p { margin: 0; line-height: 1.5; }
		table { width: 100%; border-collapse: collapse; }
		th { text-align: left; font-size: 12px; letter-spacing: 0.04em; text-transform: uppercase; color: #5c6570; padding: 10px 0; border-bottom: 1px solid #e4e7ee; }
		td { padding: 12px 0; border-bottom: 1px solid #eef1f6; vertical-align: top; }
		.num { text-align: right; }
		.totals { margin-top: 16px; margin-left: auto; width: 260px; }
		.totals td { border: 0; padding: 4px 0; }
		.totals .grand td { font-size: 18px; font-weight: 700; padding-top: 10px; }
		@media print {
			body { background: #fff; }
			.toolbar { display: none; }
			.sheet { margin: 0; border-radius: 0; }
		}
	</style>
</head>
<body>
	<div class="toolbar">
		<button type="button" onclick="window.print()"><?php esc_html_e('Download PDF', 'cubestheme'); ?></button>
	</div>
	<article class="sheet">
		<div class="bar">WP Plugins Pro</div>
		<div class="pad">
			<h1><?php esc_html_e('Invoice', 'cubestheme'); ?> <?php echo esc_html($number); ?></h1>
			<p class="meta"><?php echo esc_html(sprintf(__('Issued %1$s · Order #%2$s · %3$s', 'cubestheme'), $issued_label, $order->get_order_number(), wc_get_order_status_name($order->get_status()))); ?></p>
			<div class="columns">
				<div>
					<h2><?php esc_html_e('From', 'cubestheme'); ?></h2>
					<p><?php echo wp_kses_post(implode('<br>', array_map('esc_html', $seller))); ?></p>
				</div>
				<div>
					<h2><?php esc_html_e('Bill to', 'cubestheme'); ?></h2>
					<p><?php echo wp_kses_post(implode('<br>', array_map('esc_html', $customer))); ?></p>
				</div>
			</div>
			<table>
				<thead>
					<tr>
						<th><?php esc_html_e('Description', 'cubestheme'); ?></th>
						<th class="num"><?php esc_html_e('Qty', 'cubestheme'); ?></th>
						<th class="num"><?php esc_html_e('Amount', 'cubestheme'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($order->get_items() as $item) : ?>
						<tr>
							<td><?php echo esc_html($item->get_name()); ?></td>
							<td class="num"><?php echo esc_html($item->get_quantity()); ?></td>
							<td class="num"><?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<table class="totals">
				<tr>
					<td><?php esc_html_e('Subtotal', 'cubestheme'); ?></td>
					<td class="num"><?php echo wp_kses_post($order->get_subtotal_to_display()); ?></td>
				</tr>
				<?php if ((float) $order->get_total_tax() > 0) : ?>
					<tr>
						<td><?php esc_html_e('Tax', 'cubestheme'); ?></td>
						<td class="num"><?php echo wp_kses_post(wc_price($order->get_total_tax(), array('currency' => $order->get_currency()))); ?></td>
					</tr>
				<?php endif; ?>
				<tr class="grand">
					<td><?php esc_html_e('Total', 'cubestheme'); ?></td>
					<td class="num"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
				</tr>
			</table>
		</div>
	</article>
</body>
</html>
	<?php
}
