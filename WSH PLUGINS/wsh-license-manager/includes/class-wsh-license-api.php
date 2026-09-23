<?php
if (! defined('ABSPATH')) {
	exit;
}

if ( ! class_exists( 'WSH_License_Utils' ) ) {
	$utils_file = dirname( __FILE__ ) . '/class-wsh-license-utils.php';
	if ( file_exists( $utils_file ) ) {
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
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/verify',
			array(
				'methods'             => 'POST',
				'callback'            => array(__CLASS__, 'handle_verify'),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$namespace,
			'/deactivate',
			array(
				'methods'             => 'POST',
				'callback'            => array(__CLASS__, 'handle_deactivate'),
				'permission_callback' => '__return_true',
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

		$status     = get_post_meta($license_id, 'wsh_status', true);
		$expires_at = get_post_meta($license_id, 'wsh_expires_at', true);
		$product    = get_post_meta($license_id, 'wsh_product_slug', true);

		/*
		// Normalize requested site URL.
		$req_site = untrailingslashit(strtolower($site_url));

		// Site URL stored with the license (bound site).
		$license_site = get_post_meta($license_id, 'wsh_site_url', true);
		$license_site = $license_site ? untrailingslashit(strtolower($license_site)) : '';
		*/
		// Normalize requested site URL.
		$req_site = WSH_License_Utils::normalize_site($site_url);
		if ('' === $req_site) {
			return self::build_response(false, 'invalid', 'Invalid site URL.');
		}

		// Site URL stored with the license (bound site).
		$license_site = get_post_meta($license_id, 'wsh_site_url', true);
		$license_site = $license_site ? WSH_License_Utils::normalize_site($license_site) : '';


		// Ensure the license belongs to this product.
		if ( empty($product) || $product !== $plugin_slug ) {
			return self::build_response(false, 'invalid', 'License key does not match this product.');
		}

		// License disabled by admin.
		if ('disabled' === $status) {
			return self::build_response(false, 'disabled', 'License is disabled.');
		}

		// Check expiry (if expiry is set).
		if (! empty($expires_at)) {
			$now = current_time('Y-m-d');
			if ($now > $expires_at) {
				update_post_meta($license_id, 'wsh_status', 'expired');
				return self::build_response(false, 'expired', 'License has expired.', $expires_at);
			}
		}

		/**
		 * Single-site binding logic.
		 *
		 * Scenario A: license already has a bound site (normal case)
		 *   - if it matches requested site -> OK
		 *   - if it does not match -> error (wrong_site)
		 *
		 * Scenario B: license has no site stored (fallback)
		 *   - first activation will bind the license to requested site.
		 */

		if (! empty($license_site)) {
			// License is already bound to a specific site.
			if ($license_site !== $req_site) {
				return self::build_response(
					false,
					'wrong_site',
					'This license is bound to a different site URL.',
					$expires_at
				);
			}
		} else {
			// No site stored yet – bind license to this site on first successful activation.
			update_post_meta($license_id, 'wsh_site_url', $req_site);
			$license_site = $req_site;
		}

		// At this point, the license is valid for this site.
		// Ensure status is active.
		update_post_meta($license_id, 'wsh_status', 'active');

		// Optionally, you can store activation timestamp for stats/logging.
		update_post_meta($license_id, 'wsh_last_activation', current_time('mysql'));

		return self::build_response(
			true,
			'valid',
			'License activated successfully.',
			$expires_at,
			array(
				// For single-site model: 1 site, always used = 1.
				'license_limit' => 1,
				'license_used'  => 1,
				'bound_site'    => $license_site,
			)
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

		$status      = get_post_meta($license_id, 'wsh_status', true);
		$expires_at  = get_post_meta($license_id, 'wsh_expires_at', true);
		$product     = get_post_meta($license_id, 'wsh_product_slug', true);
		$license_site = get_post_meta($license_id, 'wsh_site_url', true);

		// Normalize URLs.
		//$req_site     = untrailingslashit(strtolower($site_url));
		//$license_site = $license_site ? untrailingslashit(strtolower($license_site)) : '';
		$req_site = WSH_License_Utils::normalize_site($site_url);
		if ('' === $req_site) {
			return self::build_response(false, 'invalid', 'Invalid site URL.');
		}

		$license_site = $license_site ? WSH_License_Utils::normalize_site($license_site) : '';

		// Ensure the license belongs to this product.
		if ( empty($product) || $product !== $plugin_slug ) {
			return self::build_response(false, 'invalid', 'License key does not match this product.');
		}

		// License disabled by admin.
		if ('disabled' === $status) {
			return self::build_response(false, 'disabled', 'License is disabled.', $expires_at);
		}

		// Check expiry (if expiry is set).
		if (! empty($expires_at)) {
			$now = current_time('Y-m-d');
			if ($now > $expires_at) {
				update_post_meta($license_id, 'wsh_status', 'expired');
				return self::build_response(false, 'expired', 'License has expired.', $expires_at);
			}
		}

		/**
		 * Single-site binding verification.
		 *
		 * If no site is stored, we consider that the license was never
		 * properly activated for any site.
		 */
		if (empty($license_site)) {
			return self::build_response(
				false,
				'not_activated',
				'License not activated for this site.',
				$expires_at,
				array(
					'license_limit' => 1,
					'license_used'  => 0,
				)
			);
		}

		// License is bound to a specific site - must match the requested one.
		if ($license_site !== $req_site) {
			return self::build_response(
				false,
				'wrong_site',
				'This license is bound to a different site URL.',
				$expires_at,
				array(
					'license_limit' => 1,
					'license_used'  => 1,
					'bound_site'    => $license_site,
				)
			);
		}

		// All good: license is valid for this site.
		return self::build_response(
			true,
			'valid',
			'License is valid.',
			$expires_at,
			array(
				'license_limit' => 1,
				'license_used'  => 1,
				'bound_site'    => $license_site,
			)
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

		if ('' === $license_key || '' === $site_url || '' === $plugin_slug){
			return self::build_response(false, 'invalid', 'Missing required parameters.');
		}

		$license_id = self::find_license_by_key($license_key);
		if (! $license_id) {
			return self::build_response(false, 'invalid', 'License key not found.');
		}

		$expires_at   = get_post_meta($license_id, 'wsh_expires_at', true);
		$product_slug = get_post_meta($license_id, 'wsh_product_slug', true);

		// Opcionalno: striktno proveri product.
		if ('' !== $plugin_slug && $product_slug && $product_slug !== $plugin_slug) {
			return self::build_response(false, 'invalid', 'License key does not match this product.');
		}

		$req_site = WSH_License_Utils::normalize_site($site_url);
		if ('' === $req_site) {
			return self::build_response(false, 'invalid', 'Invalid site URL.');
		}

		$license_site = get_post_meta($license_id, 'wsh_site_url', true);
		$license_site = $license_site ? WSH_License_Utils::normalize_site($license_site) : '';

		if (empty($license_site)) {
			return self::build_response(
				true,
				'deactivated',
				'License was not bound to any site (nothing to deactivate).',
				$expires_at,
				array(
					'license_limit' => 1,
					'license_used'  => 0,
				)
			);
		}

		if ($license_site !== $req_site) {
			return self::build_response(
				false,
				'wrong_site',
				'This license is bound to a different site URL.',
				$expires_at,
				array(
					'license_limit' => 1,
					'license_used'  => 1,
					'bound_site'    => $license_site,
				)
			);
		}

		// Unbind
		delete_post_meta($license_id, 'wsh_site_url');
		update_post_meta($license_id, 'wsh_last_deactivation', current_time('mysql'));

		return self::build_response(
			true,
			'deactivated',
			'License deactivated for this site.',
			$expires_at,
			array(
				'license_limit' => 1,
				'license_used'  => 0,
			)
		);
	}
}
