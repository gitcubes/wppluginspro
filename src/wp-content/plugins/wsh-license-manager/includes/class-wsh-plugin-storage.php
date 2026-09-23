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

	const META_FILES = 'wsh_plugin_files';
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

	public static function packages($plugin_id)
	{
		self::migrate_legacy_file($plugin_id);

		$stored = get_post_meta($plugin_id, self::META_FILES, true);
		if (! is_array($stored)) {
			return array();
		}

		$packages = array();
		foreach ($stored as $package) {
			if (! is_array($package) || self::path_for_basename($package['file'] ?? '') === '') {
				continue;
			}
			$packages[] = array(
				'id'       => (string) $package['id'],
				'channel'  => ($package['channel'] ?? '') === 'free' ? 'free' : 'pro',
				'version'  => (string) ($package['version'] ?? ''),
				'file'     => (string) $package['file'],
				'original' => (string) ($package['original'] ?? 'plugin.zip'),
			);
		}

		usort($packages, function ($a, $b) {
			return version_compare($b['version'], $a['version']);
		});

		return $packages;
	}

	public static function packages_for_channel($plugin_id, $channel)
	{
		$channel = $channel === 'free' ? 'free' : 'pro';

		return array_values(array_filter(self::packages($plugin_id), function ($package) use ($channel) {
			return $package['channel'] === $channel;
		}));
	}

	public static function has_file($plugin_id)
	{
		return ! empty(self::packages($plugin_id));
	}

	public static function add_package($plugin_id, $channel, $version, $file)
	{
		$stored_file = self::receive_upload($file);
		if (is_wp_error($stored_file)) {
			return $stored_file;
		}

		$packages = get_post_meta($plugin_id, self::META_FILES, true);
		if (! is_array($packages)) {
			$packages = array();
		}

		$packages[] = array(
			'id'       => bin2hex(random_bytes(6)),
			'channel'  => $channel === 'free' ? 'free' : 'pro',
			'version'  => $version,
			'file'     => $stored_file['file'],
			'original' => $stored_file['original'],
		);

		update_post_meta($plugin_id, self::META_FILES, $packages);

		return true;
	}

	public static function remove_packages($plugin_id, $ids)
	{
		$ids = array_map('strval', (array) $ids);
		$packages = get_post_meta($plugin_id, self::META_FILES, true);
		if (! is_array($packages)) {
			return;
		}

		$kept = array();
		foreach ($packages as $package) {
			if (! is_array($package)) {
				continue;
			}
			if (in_array((string) ($package['id'] ?? ''), $ids, true)) {
				$path = self::path_for_basename($package['file'] ?? '');
				if ($path !== '') {
					wp_delete_file($path);
				}
				continue;
			}
			$kept[] = $package;
		}

		update_post_meta($plugin_id, self::META_FILES, $kept);
	}

	public static function delete_file($plugin_id)
	{
		foreach (self::packages($plugin_id) as $package) {
			$path = self::path_for_basename($package['file']);
			if ($path !== '') {
				wp_delete_file($path);
			}
		}

		delete_post_meta($plugin_id, self::META_FILES);
		delete_post_meta($plugin_id, self::META_FILE);
		delete_post_meta($plugin_id, self::META_NAME);
	}

	public static function find_package($plugin_id, $file_id)
	{
		foreach (self::packages($plugin_id) as $package) {
			if ($package['id'] === (string) $file_id) {
				return $package;
			}
		}

		return null;
	}

	public static function download_url($plugin_id, $file_id)
	{
		return wp_nonce_url(
			admin_url('admin-post.php?action=wsh_download_plugin&plugin=' . (int) $plugin_id . '&file=' . rawurlencode((string) $file_id)),
			'wsh_download_plugin_' . (int) $plugin_id . '_' . (string) $file_id
		);
	}

	public static function user_can_download($user_id, $plugin_id, $channel = 'pro')
	{
		$plugin_id = (int) $plugin_id;
		$channel = $channel === 'free' ? 'free' : 'pro';

		if (get_post_type($plugin_id) !== 'wsh_plugin') {
			return false;
		}

		if ($user_id > 0 && user_can($user_id, 'manage_options')) {
			return true;
		}

		if (get_post_status($plugin_id) !== 'publish' || empty(self::packages_for_channel($plugin_id, $channel))) {
			return false;
		}

		if ($channel === 'free') {
			return true;
		}

		$user = $user_id > 0 ? get_userdata($user_id) : false;
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
		$file_id = isset($_GET['file']) ? sanitize_text_field(wp_unslash($_GET['file'])) : '';
		$package = self::find_package($plugin_id, $file_id);

		if ($package === null) {
			status_header(404);
			exit(esc_html__('This plugin file is not available.', 'wsh-license-manager'));
		}

		if ($package['channel'] === 'pro' && ! is_user_logged_in()) {
			$return = function_exists('wc_get_account_endpoint_url')
				? wc_get_account_endpoint_url('plugin-files')
				: home_url('/my-account/');
			wp_safe_redirect(home_url('/sign-in/?redirect_to=' . rawurlencode($return)));
			exit;
		}

		$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
		if (! wp_verify_nonce($nonce, 'wsh_download_plugin_' . $plugin_id . '_' . $file_id)) {
			status_header(403);
			exit(esc_html__('This download link is invalid or has expired.', 'wsh-license-manager'));
		}

		if (! self::user_can_download(get_current_user_id(), $plugin_id, $package['channel'])) {
			status_header(403);
			exit(esc_html__('You do not have an active license for this plugin.', 'wsh-license-manager'));
		}

		$path = self::path_for_basename($package['file']);
		if ($path === '') {
			status_header(404);
			exit(esc_html__('This plugin file is not available.', 'wsh-license-manager'));
		}

		$filename = sanitize_file_name($package['original']);
		if ($filename === '') {
			$filename = 'plugin.zip';
		}
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

	private static function migrate_legacy_file($plugin_id)
	{
		$existing = get_post_meta($plugin_id, self::META_FILES, true);
		if (is_array($existing) && ! empty($existing)) {
			return;
		}

		$basename = basename((string) get_post_meta($plugin_id, self::META_FILE, true));
		if ($basename === '' || self::path_for_basename($basename) === '') {
			return;
		}

		update_post_meta($plugin_id, self::META_FILES, array(array(
			'id'       => bin2hex(random_bytes(6)),
			'channel'  => 'pro',
			'version'  => (string) get_post_meta($plugin_id, 'wsh_plugin_version', true),
			'file'     => $basename,
			'original' => (string) get_post_meta($plugin_id, self::META_NAME, true),
		)));
		delete_post_meta($plugin_id, self::META_FILE);
		delete_post_meta($plugin_id, self::META_NAME);
	}

	private static function path_for_basename($basename)
	{
		$basename = basename((string) $basename);
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

	private static function receive_upload($file)
	{
		self::ensure_directory();

		if (! isset($file['tmp_name'], $file['name'], $file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
			return new WP_Error('wsh_zip_missing', __('Choose a ZIP file.', 'wsh-license-manager'));
		}

		if ((int) $file['error'] !== UPLOAD_ERR_OK || ! is_uploaded_file($file['tmp_name'])) {
			return new WP_Error('wsh_zip_upload', __('The ZIP could not be uploaded.', 'wsh-license-manager'));
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

		return array(
			'file'     => $basename,
			'original' => $original,
		);
	}
}
