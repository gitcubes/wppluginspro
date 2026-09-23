<?php
if ( ! defined('WP_UNINSTALL_PLUGIN') ) exit;

$delete = (int) get_option('wsh_ar_delete_on_uninstall', 0);
if ($delete !== 1) {
    return;
}

// options to remove
$opts = [
    'wsh_ar_public_mode',
    'wsh_ar_home_cache_ttl',
    'wsh_ar_api_keys',
    'wsh_ar_home_layout_free',
    'wsh_ar_home_layout_pro',
    'wsh_ar_rate_limit_rpm_free',
    'wsh_ar_rate_limit_rpm_pro',
    'wsh_ar_trust_proxy',
    'wsh_ar_proxy_mode',
    'wsh_ar_delete_on_uninstall',
];

// license options (if you keep them in free plugin)
$opts = array_merge($opts, [
    'wsh_ar_license_key',
    'wsh_ar_license_status',
    'wsh_ar_license_expires',
    'wsh_ar_license_last_error',
]);

foreach ($opts as $k) {
    delete_option($k);
}

// delete transients (prefix)
global $wpdb;
$prefix = $wpdb->esc_like('_transient_wsh_ar_') . '%';
$prefix_timeout = $wpdb->esc_like('_transient_timeout_wsh_ar_') . '%';

$wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix) );
$wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix_timeout) );
