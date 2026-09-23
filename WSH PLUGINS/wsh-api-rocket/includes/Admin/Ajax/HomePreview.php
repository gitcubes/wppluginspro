<?php
// File: includes/Admin/Ajax/HomePreview.php

namespace WSH\APIROCKET\Admin\Ajax;

use WSH\APIROCKET\Home\HomeLayoutService;
use WSH\APIROCKET\Home\LayoutRepository;

if ( ! defined('ABSPATH') ) exit;

final class HomePreview {

    public function init(): void {
        add_action('wp_ajax_wsh_ar_home_preview', [$this, 'handle']);
    }

    public function handle(): void {
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        check_ajax_referer('wsh_ar_home_preview', 'nonce');

        // Optional: allow previewing POSTed layout without saving
        $raw_layout = isset($_POST['layout']) ? wp_unslash($_POST['layout']) : '';
        $layout = null;

        if (is_string($raw_layout) && $raw_layout !== '') {
            $decoded = json_decode($raw_layout, true);
            if (is_array($decoded)) {
                $layout = $decoded;
            }
        }

        // Build home output using service; if layout provided, temporarily filter repository
        if ($layout) {
            add_filter('wsh_ar_home_layout_override', function() use ($layout) {
                return $layout;
            }, 10, 0);
        }

        $svc = new HomeLayoutService();
        // We don't have a REST request here; pass null-friendly ctx by calling build with fake request
        $request = new \WP_REST_Request('GET', '/wsh/v1/home');
        $data = $svc->build($request);

        if ($layout) {
            remove_all_filters('wsh_ar_home_layout_override');
        }

        wp_send_json_success([
            'message' => 'OK',
            'data'    => $data,
        ]);
    }
}
