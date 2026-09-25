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

	$logo_id = absint(get_option('cubestheme_company_logo_id'));
	$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
	$status = wc_get_order_status_name($order->get_status());

	nocache_headers();
	header('Content-Type: text/html; charset=UTF-8');
	?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?php echo esc_html($number); ?></title>
	<style>
		* { box-sizing: border-box; }
		body { margin: 0; background: #eef3fb; color: #1b1b21; font-family: Helvetica, Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
		.toolbar { max-width: 820px; margin: 24px auto 0; padding: 0 16px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
		.toolbar a, .toolbar button { border: 0; border-radius: 999px; font: 600 14px Helvetica, Arial, sans-serif; padding: 12px 18px; cursor: pointer; text-decoration: none; }
		.toolbar a { background: #fff; color: #034dd3; border: 1px solid rgba(3, 77, 211, 0.25); }
		.toolbar button { background: #034dd3; color: #fff; }
		.sheet { max-width: 820px; margin: 16px auto 40px; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 12px 40px rgba(3, 77, 211, 0.08); }
		.pad { padding: 36px 40px 32px; }
		.head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; padding-bottom: 22px; border-bottom: 3px solid #034dd3; }
		.logo { display: block; max-width: 210px; max-height: 64px; width: auto; height: auto; }
		.wordmark { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.3px; }
		.doc { text-align: right; }
		.doc h1 { margin: 0; font-size: 13px; letter-spacing: 0.16em; text-transform: uppercase; color: #034dd3; }
		.doc strong { display: block; margin-top: 4px; font-size: 26px; letter-spacing: -0.4px; }
		.doc p { margin: 8px 0 0; color: #5c6570; font-size: 13px; line-height: 1.5; }
		.parties { display: flex; gap: 24px; margin: 28px 0; }
		.card { flex: 1; padding: 16px 18px; border: 1px solid #d9e2f2; border-radius: 12px; }
		h2 { margin: 0 0 8px; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: #034dd3; }
		.card p { margin: 0; line-height: 1.55; font-size: 14px; }
		table.items { width: 100%; border-collapse: collapse; }
		table.items th { text-align: left; font-size: 11px; letter-spacing: 0.06em; text-transform: uppercase; color: #5c6570; padding: 10px 8px; border-bottom: 2px solid #034dd3; }
		table.items td { padding: 14px 8px; border-bottom: 1px solid #e6ebf5; vertical-align: top; font-size: 14px; }
		.num { text-align: right; }
		.totals { margin: 18px 0 0 auto; width: 280px; border-collapse: collapse; }
		.totals td { padding: 6px 8px; font-size: 14px; }
		.totals .grand td { padding-top: 12px; border-top: 2px solid #034dd3; font-size: 18px; font-weight: 700; }
		.foot { margin-top: 36px; padding-top: 16px; border-top: 1px solid #e6ebf5; color: #5c6570; font-size: 12px; }
		@page { margin: 14mm; }
		@media print {
			body { background: #fff; }
			.toolbar { display: none; }
			.sheet { margin: 0; max-width: none; border-radius: 0; box-shadow: none; }
		}
	</style>
</head>
<body>
	<div class="toolbar">
		<a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>"><?php esc_html_e('Back to orders', 'cubestheme'); ?></a>
		<button type="button" onclick="window.print()"><?php esc_html_e('Download PDF', 'cubestheme'); ?></button>
	</div>
	<article class="sheet">
		<div class="pad">
			<header class="head">
				<div>
					<?php if ($logo_url) : ?>
						<img class="logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
					<?php else : ?>
						<p class="wordmark"><?php echo esc_html(get_bloginfo('name')); ?></p>
					<?php endif; ?>
				</div>
				<div class="doc">
					<h1><?php esc_html_e('Invoice', 'cubestheme'); ?></h1>
					<strong><?php echo esc_html($number); ?></strong>
					<p>
						<?php echo esc_html($issued_label); ?><br>
						<?php echo esc_html(sprintf(__('Order #%s', 'cubestheme'), $order->get_order_number())); ?><br>
						<?php echo esc_html($status); ?>
					</p>
				</div>
			</header>
			<div class="parties">
				<div class="card">
					<h2><?php esc_html_e('From', 'cubestheme'); ?></h2>
					<p><?php echo wp_kses_post(implode('<br>', array_map('esc_html', $seller))); ?></p>
				</div>
				<div class="card">
					<h2><?php esc_html_e('Bill to', 'cubestheme'); ?></h2>
					<p><?php echo wp_kses_post(implode('<br>', array_map('esc_html', $customer))); ?></p>
				</div>
			</div>
			<table class="items">
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
			<p class="foot"><?php esc_html_e('Thank you for your business.', 'cubestheme'); ?> WP Plugins Pro · wppluginspro.io</p>
		</div>
	</article>
</body>
</html>
	<?php
}
