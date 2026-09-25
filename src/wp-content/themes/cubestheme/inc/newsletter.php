<?php
if (!defined('ABSPATH')) {
	exit;
}

function cubestheme_brevo_api_key()
{
	$saved = get_option('cubestheme_brevo_api_key');
	if (is_string($saved) && trim($saved) !== '') {
		$key = trim($saved);
		$key = preg_replace('/^(api-key:|bearer)\s*/i', '', $key);

		return trim($key);
	}

	$smtp = get_option('wp_mail_smtp');
	if (is_array($smtp) && !empty($smtp['sendinblue']['api_key'])) {
		return (string) $smtp['sendinblue']['api_key'];
	}

	$key = get_option('sib_wc_api_key_v3');
	if (is_string($key) && $key !== '') {
		return $key;
	}

	$brevo = get_option('brevo_campaigns_settings');
	if (is_array($brevo) && !empty($brevo['api_key'])) {
		return (string) $brevo['api_key'];
	}

	return '';
}

function cubestheme_brevo_list_id($api_key)
{
	$list_id = absint(get_option('cubestheme_brevo_list_id'));
	if ($list_id > 0) {
		return $list_id;
	}

	$settings = get_option('sendinblue_woocommerce_user_connection_settings');
	if (is_array($settings) && !empty($settings['listId'])) {
		return (int) $settings['listId'];
	}

	if ($api_key === '') {
		return 0;
	}

	$response = wp_remote_get('https://api.brevo.com/v3/contacts/lists?limit=50&offset=0', array(
		'headers' => array(
			'api-key' => $api_key,
			'accept' => 'application/json',
		),
		'timeout' => 15,
	));
	if (is_wp_error($response)) {
		return 0;
	}

	$body = json_decode(wp_remote_retrieve_body($response), true);
	$lists = is_array($body['lists'] ?? null) ? $body['lists'] : array();
	$match = 0;
	foreach ($lists as $list) {
		$name = (string) ($list['name'] ?? '');
		if (preg_match('/newsletter|subscriber|wp plugins/i', $name)) {
			$match = (int) ($list['id'] ?? 0);
			break;
		}
	}
	if ($match <= 0 && count($lists) === 1) {
		$match = (int) ($lists[0]['id'] ?? 0);
	}
	if ($match > 0) {
		update_option('cubestheme_brevo_list_id', $match);
	}

	return $match;
}

function cubestheme_brevo_subscribe($email)
{
	$api_key = cubestheme_brevo_api_key();
	$list_id = cubestheme_brevo_list_id($api_key);
	if ($api_key === '' || $list_id <= 0) {
		update_option('cubestheme_brevo_last_error', $api_key === '' ? 'missing-key' : 'missing-list', false);

		return false;
	}

	$headers = array(
		'api-key' => $api_key,
		'content-type' => 'application/json',
		'accept' => 'application/json',
	);
	$response = wp_remote_post('https://api.brevo.com/v3/contacts', array(
		'headers' => $headers,
		'body' => wp_json_encode(array(
			'email' => $email,
			'listIds' => array($list_id),
			'updateEnabled' => true,
			'emailBlacklisted' => false,
		)),
		'timeout' => 15,
	));
	if (is_wp_error($response)) {
		update_option('cubestheme_brevo_last_error', 'request:' . $response->get_error_code(), false);

		return false;
	}

	$code = (int) wp_remote_retrieve_response_code($response);
	$body = wp_remote_retrieve_body($response);
	$saved = ($code >= 200 && $code < 300)
		|| strpos($body, 'duplicate_parameter') !== false
		|| strpos($body, 'already exist') !== false;
	if (!$saved) {
		$decoded = json_decode($body, true);
		$detail = is_array($decoded) ? sanitize_text_field(($decoded['code'] ?? '') . ' ' . ($decoded['message'] ?? '')) : 'http-' . $code;
		update_option('cubestheme_brevo_last_error', $code . ' ' . $detail, false);

		return false;
	}

	$unblock = wp_remote_request('https://api.brevo.com/v3/contacts/' . rawurlencode($email), array(
		'method' => 'PUT',
		'headers' => $headers,
		'body' => wp_json_encode(array(
			'emailBlacklisted' => false,
			'listIds' => array($list_id),
		)),
		'timeout' => 15,
	));
	if (is_wp_error($unblock)) {
		update_option('cubestheme_brevo_last_error', 'unblock:' . $unblock->get_error_code(), false);

		return false;
	}

	$unblock_code = (int) wp_remote_retrieve_response_code($unblock);
	if ($unblock_code >= 300) {
		$decoded = json_decode(wp_remote_retrieve_body($unblock), true);
		$detail = is_array($decoded) ? sanitize_text_field(($decoded['code'] ?? '') . ' ' . ($decoded['message'] ?? '')) : 'http-' . $unblock_code;
		update_option('cubestheme_brevo_last_error', $unblock_code . ' ' . $detail, false);

		return false;
	}

	delete_option('cubestheme_brevo_last_error');

	return true;
}

function cubestheme_newsletter_signup()
{
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['newsletter_signup'])) {
		return;
	}

	$email = sanitize_email(wp_unslash($_POST['newsletter_email'] ?? ''));
	$target = wp_get_referer();
	if (!$target) {
		$target = home_url('/');
	}
	$target = remove_query_arg('newsletter', $target);
	$status = is_email($email) && cubestheme_brevo_subscribe($email) ? 'ok' : 'error';

	wp_safe_redirect(add_query_arg('newsletter', $status, $target) . '#newsletter-signup');
	exit;
}

add_action('template_redirect', 'cubestheme_newsletter_signup');

function cubestheme_newsletter_notice()
{
	$status = sanitize_text_field(wp_unslash($_GET['newsletter'] ?? ''));
	if ($status === 'ok') {
		echo '<p class="newsletter-note is-success">' . esc_html__('You are on the list. Thank you.', 'cubestheme') . '</p>';
	} elseif ($status === 'error') {
		echo '<p class="newsletter-note">' . esc_html__('We could not add that email. Try again in a moment.', 'cubestheme') . '</p>';
	}
}
