<?php
if (! defined('ABSPATH')) {
	exit;
}

if (! class_exists('WSH_License_Utils')) {
	$utils_file = dirname(__FILE__) . '/class-wsh-license-utils.php';
	if (file_exists($utils_file)) {
		require_once $utils_file;
	}
}

/**
 * REST API endpoints for license activation, verification and deactivation.
 */
class WSH_License_API
{

	public static function init()
	{
		add_action('rest_api_init', array(__CLASS__, 'register_routes'));
	}

	/**
	 * Register REST API routes.
	 */
	public static function register_routes()
	{

		$namespace = WSH_LICENSE_API_NAMESPACE; // e.g. "wsh-vc-license/v1"

		register_rest_route(
			$namespace,
			'/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array(__CLASS__, 'handle_activate'),
				//'permission_callback' => '__return_true',
				'permission_callback' => array(__CLASS__, 'permission_check'),
			)
		);

		register_rest_route(
			$namespace,
			'/verify',
			array(
				'methods'             => 'POST',
				'callback'            => array(__CLASS__, 'handle_verify'),
				//'permission_callback' => '__return_true',
				'permission_callback' => array(__CLASS__, 'permission_check'),
			)
		);

		register_rest_route(
			$namespace,
			'/deactivate',
			array(
				'methods'             => 'POST',
				'callback'            => array(__CLASS__, 'handle_deactivate'),
				//'permission_callback' => '__return_true',
				'permission_callback' => array(__CLASS__, 'permission_check'),
			)
		);
	}

	/**
	 * Find license post by license key.
	 *
	 * @param string $license_key License key string.
	 * @return int|false Post ID or false if not found.
	 */
	protected static function find_license_by_key($license_key)
	{
		$query = new WP_Query(
			array(
				'post_type'      => 'wsh_license',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => 'wsh_license_key',
				'meta_value'     => $license_key,
				'fields'         => 'ids',
			)
		);

		if (! empty($query->posts)) {
			return (int) $query->posts[0];
		}

		return false;
	}

	/**
	 * Common helper to build standard JSON response.
	 *
	 * @param bool   $success Success flag.
	 * @param string $status  License status.
	 * @param string $message Human readable message.
	 * @param string $expires Expiry date (Y-m-d) or empty.
	 * @param array  $extra   Extra data.
	 * @return WP_REST_Response
	 */
	protected static function build_response($success, $status, $message, $expires = '', $extra = array())
	{
		$base = array(
			'success' => (bool) $success,
			'status'  => (string) $status,
			'expires' => (string) $expires,
			'error'   => $success ? '' : $message,
			'message' => $message,
		);

		return new WP_REST_Response(array_merge($base, $extra));
	}

	/**
	 * Handle /activate endpoint.
	 */

	public static function handle_activate(WP_REST_Request $request)
	{

		$license_key = trim((string) $request->get_param('license_key'));
		$site_url    = trim((string) $request->get_param('site_url'));
		$plugin_slug = trim((string) $request->get_param('plugin_slug'));

		if ('' === $license_key || '' === $site_url || '' === $plugin_slug) {
			return self::build_response(false, 'invalid', 'Missing required parameters.');
		}

		$license_id = self::find_license_by_key($license_key);

		if (! $license_id) {
			return self::build_response(false, 'invalid', 'License key not found.');
		}

		$req_site = WSH_License_Utils::normalize_site($site_url);
		if ('' === $req_site) {
			return self::build_response(false, 'invalid', 'Invalid site URL.');
		}

		$blocked = self::guard_license($license_id, $plugin_slug);
		if ($blocked instanceof WP_REST_Response) {
			return $blocked;
		}

		$expires_at = (string) get_post_meta($license_id, 'wsh_expires_at', true);
		$sites      = WSH_License_Utils::activated_sites($license_id);

		if (in_array($req_site, $sites, true) || WSH_License_Utils::is_staging_site($req_site)) {
			self::mark_activated($license_id);
			$extra = self::usage_extra($license_id, $req_site);
			if (! in_array($req_site, $sites, true)) {
				$extra['staging'] = true;
			}

			return self::build_response(true, 'valid', 'License activated successfully.', $expires_at, $extra);
		}

		$limit = WSH_License_Utils::max_sites($license_id);
		if (0 !== $limit && count($sites) >= $limit) {
			if (1 === $limit && 1 === count($sites)) {
				return self::build_response(
					false,
					'wrong_site',
					'This license is bound to a different site URL.',
					$expires_at,
					self::usage_extra($license_id, $sites[0])
				);
			}

			return self::build_response(
				false,
				'site_limit',
				'This license has no free site slots left.',
				$expires_at,
				self::usage_extra($license_id)
			);
		}

		$sites[] = $req_site;
		WSH_License_Utils::save_activated_sites($license_id, $sites);
		self::mark_activated($license_id);

		return self::build_response(
			true,
			'valid',
			'License activated successfully.',
			$expires_at,
			self::usage_extra($license_id, $req_site)
		);
	}


	/**
	 * Handle /verify endpoint.
	 */
	public static function handle_verify(WP_REST_Request $request)
	{

		$license_key = trim((string) $request->get_param('license_key'));
		$site_url    = trim((string) $request->get_param('site_url'));
		$plugin_slug = trim((string) $request->get_param('plugin_slug'));

		if ('' === $license_key || '' === $site_url || '' === $plugin_slug) {
			return self::build_response(false, 'invalid', 'Missing required parameters.');
		}

		$license_id = self::find_license_by_key($license_key);

		if (! $license_id) {
			return self::build_response(false, 'invalid', 'License key not found.');
		}

		$req_site = WSH_License_Utils::normalize_site($site_url);
		if ('' === $req_site) {
			return self::build_response(false, 'invalid', 'Invalid site URL.');
		}

		$blocked = self::guard_license($license_id, $plugin_slug);
		if ($blocked instanceof WP_REST_Response) {
			return $blocked;
		}

		$expires_at = (string) get_post_meta($license_id, 'wsh_expires_at', true);
		$sites      = WSH_License_Utils::activated_sites($license_id);

		if (in_array($req_site, $sites, true)) {
			return self::build_response(
				true,
				'valid',
				'License is valid.',
				$expires_at,
				self::usage_extra($license_id, $req_site)
			);
		}

		$staging_ready = $sites || (string) get_post_meta($license_id, 'wsh_last_activation', true) !== '';
		if (WSH_License_Utils::is_staging_site($req_site) && $staging_ready) {
			$extra = self::usage_extra($license_id, $req_site);
			$extra['staging'] = true;

			return self::build_response(true, 'valid', 'License is valid.', $expires_at, $extra);
		}

		if ($sites && 1 === WSH_License_Utils::max_sites($license_id) && 1 === count($sites)) {
			return self::build_response(
				false,
				'wrong_site',
				'This license is bound to a different site URL.',
				$expires_at,
				self::usage_extra($license_id, $sites[0])
			);
		}

		return self::build_response(
			false,
			'not_activated',
			'License not activated for this site.',
			$expires_at,
			self::usage_extra($license_id)
		);
	}


	/**
	 * Handle /deactivate endpoint.
	 */
	public static function handle_deactivate_bkp(WP_REST_Request $request)
	{

		$license_key = trim((string) $request->get_param('license_key'));
		$site_url    = trim((string) $request->get_param('site_url'));

		if ('' === $license_key || '' === $site_url) {
			return self::build_response(false, 'invalid', 'Missing required parameters.');
		}

		$license_id = self::find_license_by_key($license_key);

		if (! $license_id) {
			return self::build_response(false, 'invalid', 'License key not found.');
		}

		$expires_at = get_post_meta($license_id, 'wsh_expires_at', true);
		$max_sites  = (int) get_post_meta($license_id, 'wsh_max_sites', true);
		$activations = get_post_meta($license_id, 'wsh_activations', true);
		if (! is_array($activations)) {
			$activations = array();
		}

		$site_url_normalized = untrailingslashit(strtolower($site_url));

		if (isset($activations[$site_url_normalized])) {
			unset($activations[$site_url_normalized]);
			update_post_meta($license_id, 'wsh_activations', $activations);
		}

		return self::build_response(
			true,
			'deactivated',
			'License deactivated for this site.',
			$expires_at,
			array(
				'license_limit' => $max_sites,
				'license_used'  => count($activations),
			)
		);
	}

	public static function handle_deactivate(WP_REST_Request $request)
	{

		$license_key = trim((string) $request->get_param('license_key'));
		$site_url    = trim((string) $request->get_param('site_url'));
		$plugin_slug = trim((string) $request->get_param('plugin_slug')); // preporuka

		if ('' === $license_key || '' === $site_url || '' === $plugin_slug) {
			return self::build_response(false, 'invalid', 'Missing required parameters.');
		}

		$license_id = self::find_license_by_key($license_key);
		if (! $license_id) {
			return self::build_response(false, 'invalid', 'License key not found.');
		}

		if (! self::license_covers_plugin($license_id, $plugin_slug)) {
			return self::build_response(false, 'invalid', 'License key does not match this product.');
		}

		$req_site = WSH_License_Utils::normalize_site($site_url);
		if ('' === $req_site) {
			return self::build_response(false, 'invalid', 'Invalid site URL.');
		}

		$expires_at = (string) get_post_meta($license_id, 'wsh_expires_at', true);
		$sites      = WSH_License_Utils::activated_sites($license_id);

		if (! in_array($req_site, $sites, true)) {
			return self::build_response(
				true,
				'deactivated',
				'License was not bound to this site.',
				$expires_at,
				self::usage_extra($license_id)
			);
		}

		$remaining = array();
		foreach ($sites as $site) {
			if ($site !== $req_site) {
				$remaining[] = $site;
			}
		}

		WSH_License_Utils::save_activated_sites($license_id, $remaining);
		update_post_meta($license_id, 'wsh_last_deactivation', current_time('mysql'));

		return self::build_response(
			true,
			'deactivated',
			'License deactivated for this site.',
			$expires_at,
			self::usage_extra($license_id)
		);
	}

	/**
	 * REST permission check (HMAC signature + nonce + timestamp + simple rate limit).
	 *
	 * Client must send:
	 * - ts (unix timestamp)
	 * - nonce (random string)
	 * - sig (HMAC SHA256)
	 *
	 * sig = hash_hmac('sha256', ts|nonce|license_key|site_url|plugin_slug, SECRET)
	 */
	public static function permission_check(WP_REST_Request $request)
	{

		// Basic rate limit per IP (very simple, but effective)
		$ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
		$rl_key = 'wsh_rl_' . md5($ip);
		$hits = (int) get_transient($rl_key);
		if ($hits > 120) { // 120 requests / 5 min
			return new WP_Error('wsh_rate_limited', 'Too many requests.', array('status' => 429));
		}
		set_transient($rl_key, $hits + 1, 5 * MINUTE_IN_SECONDS);

		$ts    = (string) $request->get_param('ts');
		$nonce = (string) $request->get_param('nonce');
		$sig   = (string) $request->get_param('sig');

		if ($ts === '' || $nonce === '' || $sig === '') {
			return new WP_Error('wsh_auth_missing', 'Missing auth parameters.', array('status' => 401));
		}

		$ts_int = (int) $ts;

		// 5 minute window
		if (abs(time() - $ts_int) > 300) {
			return new WP_Error('wsh_auth_ts', 'Request expired.', array('status' => 401));
		}

		// Nonce replay protection
		$nonce_key = 'wsh_nonce_' . md5($nonce);
		if (get_transient($nonce_key)) {
			return new WP_Error('wsh_auth_replay', 'Replay detected.', array('status' => 401));
		}
		set_transient($nonce_key, 1, 10 * MINUTE_IN_SECONDS);

		$license_key = (string) $request->get_param('license_key');
		$site_url    = (string) $request->get_param('site_url');
		$plugin_slug = (string) $request->get_param('plugin_slug');

		// If missing core fields, fail here (prevents signature check on empty payload)
		if ($license_key === '' || $site_url === '' || $plugin_slug === '') {
			return new WP_Error('wsh_auth_payload', 'Missing required payload parameters.', array('status' => 401));
		}

		$payload = implode('|', array($ts, $nonce, $license_key, $site_url, $plugin_slug));

		$secret = (string) get_option('wsh_license_api_secret', '');
		if ($secret === '') {
			return new WP_Error('wsh_auth_server', 'Server secret not configured.', array('status' => 500));
		}

		$expected = hash_hmac('sha256', $payload, $secret);

		if (! hash_equals($expected, $sig)) {
			return new WP_Error('wsh_auth_invalid', 'Invalid signature.', array('status' => 401));
		}

		return true;
	}

	/**
	 * Reject a license that is disabled, expired, or for another plugin.
	 *
	 * @param int    $license_id  License post ID.
	 * @param string $plugin_slug Plugin slug sent by the client.
	 * @return WP_REST_Response|null
	 */
	protected static function guard_license($license_id, $plugin_slug)
	{
		if (! self::license_covers_plugin($license_id, $plugin_slug)) {
			return self::build_response(false, 'invalid', 'License key does not match this product.');
		}

		$status     = (string) get_post_meta($license_id, 'wsh_status', true);
		$expires_at = (string) get_post_meta($license_id, 'wsh_expires_at', true);

		if ('disabled' === $status) {
			return self::build_response(false, 'disabled', 'License is disabled.', $expires_at);
		}

		if ('' !== $expires_at && current_time('Y-m-d') > $expires_at) {
			update_post_meta($license_id, 'wsh_status', 'expired');
			return self::build_response(false, 'expired', 'License has expired.', $expires_at);
		}

		return null;
	}

	/**
	 * A single-plugin key matches its slug. A suite key matches every plugin in that family.
	 *
	 * @param int    $license_id  License post ID.
	 * @param string $plugin_slug Plugin slug sent by the client.
	 * @return bool
	 */
	protected static function license_covers_plugin($license_id, $plugin_slug)
	{
		$product_slug = (string) get_post_meta($license_id, 'wsh_product_slug', true);
		$group        = (string) get_post_meta($license_id, 'wsh_license_group', true);

		if ('' === $group) {
			return '' !== $product_slug && $product_slug === $plugin_slug;
		}

		if (! class_exists('WSH_Plugin_Catalog')) {
			return '' !== $product_slug && $product_slug === $plugin_slug;
		}

		$product = WSH_Plugin_Catalog::find_by_slug($plugin_slug);
		if (! $product instanceof WP_Post) {
			return false;
		}

		if ('all' === $group) {
			return true;
		}

		$family = (string) get_post_meta($product->ID, 'wsh_plugin_family', true);

		return '' !== $family && $family === $group;
	}

	protected static function mark_activated($license_id)
	{
		update_post_meta($license_id, 'wsh_status', 'active');
		update_post_meta($license_id, 'wsh_last_activation', current_time('mysql'));
	}

	/**
	 * @param int    $license_id License post ID.
	 * @param string $bound_site Site this response is about.
	 * @return array
	 */
	protected static function usage_extra($license_id, $bound_site = '')
	{
		$sites = WSH_License_Utils::activated_sites($license_id);
		$extra = array(
			'license_limit' => WSH_License_Utils::max_sites($license_id),
			'license_used'  => count($sites),
			'sites'         => $sites,
		);

		if ('' !== $bound_site) {
			$extra['bound_site'] = $bound_site;
		} elseif ($sites) {
			$extra['bound_site'] = $sites[0];
		}

		return $extra;
	}
}
