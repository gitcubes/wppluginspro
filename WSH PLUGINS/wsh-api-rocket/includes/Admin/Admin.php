<?php
// PATCH: File: includes/Admin/Admin.php
// Register AJAX preview handler + enqueue admin assets.

namespace WSH\APIROCKET\Admin;

use WSH\APIROCKET\Admin\Settings\Settings;
use WSH\APIROCKET\Admin\Ajax\HomePreview;

if ( ! defined('ABSPATH') ) exit;

final class Admin {

    public function init(): void {
        (new Settings())->init();
        (new HomePreview())->init();

        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hook): void {
        // Only load on our plugin page
        if (strpos($hook, 'wsh-api-rocket') === false) return;

        wp_enqueue_script(
            'wsh-ar-admin',
            WSH_AR_PLUGIN_URL . 'assets/admin/admin.js',
            ['jquery'],
            WSH_AR_VERSION,
            true
        );

        wp_localize_script('wsh-ar-admin', 'WSH_AR_ADMIN', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wsh_ar_home_preview'),
        ]);
    }
}
