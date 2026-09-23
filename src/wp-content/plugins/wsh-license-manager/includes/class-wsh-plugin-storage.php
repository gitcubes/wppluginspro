<?php
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Private ZIP storage and gated downloads.
 *
 * Files live outside the public uploads tree. The browser never receives
 * a direct file URL. A download is a short-lived, user-bound nonce that
 * is checked against an active license before the file is streamed.
 */
class WSH_Plugin_Storage
{

	const META_FILE = 'wsh_zip_file';
	const META_NAME = 'wsh_zip_original_name';

	public static function directory()
	{
		return WP_CONTENT_DIR . '/wsh-private/plugins';
	}

	public static function ensure_directory()
	{
		$dir = self::directory();

		if (! is_dir($dir)) {
			wp_mkdir_p($dir);
		}

		$htaccess = $dir . '/.htaccess';
		if (! file_exists($htaccess)) {
			file_put_contents($htaccess, "Require all denied\nOptions -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
		}

		$index = $dir . '/index.php';
		if (! file_exists($index)) {
			file_put_contents($index, "<?php\n// Silence is golden.\n");
		}

		$parent = dirname($dir);
		if (! file_exists($parent . '/index.php')) {
			file_put_contents($parent . '/index.php', "<?php\n// Silence is golden.\n");
		}
		if (! file_exists($parent . '/.htaccess')) {
			file_put_contents($parent . '/.htaccess', "Require all denied\nOptions -Indexes\n");
		}
	}

	public static function init()
	{
		add_action('init', array(__CLASS__, 'ensure_directory'));
		add_action('admin_post_wsh_download_plugin', array(__CLASS__, 'handle_download'));
		add_action('admin_post_nopriv_wsh_download_plugin', array(__CLASS__, 'handle_download'));
	}

	public static function has_file($plugin_id)
	{
		return self::path_for($plugin_id) !== '';
	}

	public static function original_name($plugin_id)
	{
		$name = (string) get_post_meta($plugin_id, self::META_NAME, true);
		$name = sanitize_file_name($name);

		return $name !== '' ? $name : 'plugin.zip';
	}

	public static function path_for($plugin_id)
	{
		$basename = (string) get_post_meta($plugin_id, self::META_FILE, true);
		$basename = basename($basename);

		if ($basename === '' || ! preg_match('/^[a-f0-9]{32}\.zip$/', $basename)) {
			return '';
		}

		$dir = realpath(self::directory());
		if ($dir === false) {
			return '';
		}

		$path = $dir . DIRECTORY_SEPARATOR . $basename;
		if (! is_file($path)) {
			return '';
		}

		$real = realpath($path);
		if ($real === false || strpos($real, $dir . DIRECTORY_SEPARATOR) !== 0) {
			return '';
		}

		return $real;
	}

	public static function store_upload($plugin_id, $file)
	{
		self::ensure_directory();

		if (! isset($file['tmp_name'], $file['name'], $file['error'])) {
			return new WP_Error('wsh_zip_missing', __('Choose a ZIP file.', 'wsh-license-manager'));
		}

		if ((int) $file['error'] !== UPLOAD_ERR_OK) {
			return new WP_Error('wsh_zip_upload', __('The ZIP could not be uploaded.', 'wsh-license-manager'));
		}

		if (! is_uploaded_file($file['tmp_name'])) {
			return new WP_Error('wsh_zip_upload', __('The ZIP upload was rejected.', 'wsh-license-manager'));
		}

		$original = sanitize_file_name($file['name']);
		if (strtolower(pathinfo($original, PATHINFO_EXTENSION)) !== 'zip') {
			return new WP_Error('wsh_zip_type', __('Only .zip files are allowed.', 'wsh-license-manager'));
		}

		$handle = fopen($file['tmp_name'], 'rb');
		$magic = $handle ? fread($handle, 4) : '';
		if ($handle) {
			fclose($handle);
		}

		if ($magic !== "PK\x03\x04" && $magic !== "PK\x05\x06" && $magic !== "PK\x07\x08") {
			return new WP_Error('wsh_zip_type', __('That file is not a valid ZIP archive.', 'wsh-license-manager'));
		}

		$basename = bin2hex(random_bytes(16)) . '.zip';
		$destination = self::directory() . '/' . $basename;

		if (! move_uploaded_file($file['tmp_name'], $destination)) {
			return new WP_Error('wsh_zip_move', __('The ZIP could not be stored.', 'wsh-license-manager'));
		}

		chmod($destination, 0640);

		$previous = self::path_for($plugin_id);
		update_post_meta($plugin_id, self::META_FILE, $basename);
		update_post_meta($plugin_id, self::META_NAME, $original);

		if ($previous !== '' && $previous !== realpath($destination)) {
			wp_delete_file($previous);
		}

		return true;
	}

	public static function delete_file($plugin_id)
	{
		$path = self::path_for($plugin_id);
		delete_post_meta($plugin_id, self::META_FILE);
		delete_post_meta($plugin_id, self::META_NAME);

		if ($path !== '') {
			wp_delete_file($path);
		}
	}

	public static function download_url($plugin_id)
	{
		return wp_nonce_url(
			admin_url('admin-post.php?action=wsh_download_plugin&plugin=' . (int) $plugin_id),
			'wsh_download_plugin_' . (int) $plugin_id
		);
	}

	public static function user_can_download($user_id, $plugin_id)
	{
		$plugin_id = (int) $plugin_id;
		if ($user_id <= 0 || get_post_type($plugin_id) !== 'wsh_plugin') {
			return false;
		}

		if (user_can($user_id, 'manage_options')) {
			return true;
		}

		if (get_post_status($plugin_id) !== 'publish') {
			return false;
		}

		if (! self::has_file($plugin_id)) {
			return false;
		}

		$user = get_userdata($user_id);
		if (! $user instanceof WP_User) {
			return false;
		}

		$slug = (string) get_post_meta($plugin_id, 'wsh_plugin_slug', true);
		if ($slug === '') {
			return false;
		}

		$licenses = get_posts(array(
			'post_type'      => 'wsh_license',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => 'wsh_product_slug',
					'value' => $slug,
				),
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

		return ! empty($licenses);
	}

	public static function handle_download()
	{
		$plugin_id = isset($_GET['plugin']) ? (int) $_GET['plugin'] : 0;

		if (! is_user_logged_in()) {
			$return = function_exists('wc_get_account_endpoint_url')
				? wc_get_account_endpoint_url('plugin-files')
				: home_url('/my-account/');
			wp_safe_redirect(home_url('/sign-in/?redirect_to=' . rawurlencode($return)));
			exit;
		}

		$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
		if (! wp_verify_nonce($nonce, 'wsh_download_plugin_' . $plugin_id)) {
			status_header(403);
			exit(esc_html__('This download link is invalid or has expired.', 'wsh-license-manager'));
		}

		if (! self::user_can_download(get_current_user_id(), $plugin_id)) {
			status_header(403);
			exit(esc_html__('You do not have an active license for this plugin.', 'wsh-license-manager'));
		}

		$path = self::path_for($plugin_id);
		if ($path === '') {
			status_header(404);
			exit(esc_html__('This plugin file is not available.', 'wsh-license-manager'));
		}

		$filename = self::original_name($plugin_id);
		$filename = str_replace(array('"', "\r", "\n"), '', $filename);

		nocache_headers();
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . (string) filesize($path));
		header('X-Content-Type-Options: nosniff');
		header('X-Robots-Tag: noindex, nofollow', true);
		header('Content-Transfer-Encoding: binary');

		while (ob_get_level() > 0) {
			ob_end_clean();
		}

		readfile($path);
		exit;
	}
}
