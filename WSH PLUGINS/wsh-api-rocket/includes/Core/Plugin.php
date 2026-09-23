<?php
// PATCH: File: includes/Core/Plugin.php
// Add cache invalidation hooks

namespace WSH\APIROCKET\Core;

use WSH\APIROCKET\Licensing\License;
use WSH\APIROCKET\Licensing\Features;
use WSH\APIROCKET\Admin\Admin;
use WSH\APIROCKET\Rest\Rest;
use WSH\APIROCKET\Support\Cache;
use WSH\APIROCKET\Home\HomeLayoutService;

if ( ! defined('ABSPATH') ) exit;

final class Plugin {
    public function init(): void {

        License::init();
        Features::init();

        if (is_admin()) {
            (new Admin())->init();
        }

        (new Rest())->init();

        // Invalidate home cache on content changes
        add_action('save_post', function($post_id) {
            if (wp_is_post_revision($post_id)) return;
            Cache::delete_by_prefix(HomeLayoutService::CACHE_PREFIX);
        }, 10, 1);

        add_action('edited_terms', function() {
            Cache::delete_by_prefix(HomeLayoutService::CACHE_PREFIX);
        }, 10, 0);

        add_action('update_option_wsh_ar_home_layout_free', function() {
            Cache::delete_by_prefix(HomeLayoutService::CACHE_PREFIX);
        }, 10, 0);

        add_action('update_option_wsh_ar_home_layout_pro', function() {
            Cache::delete_by_prefix(HomeLayoutService::CACHE_PREFIX);
        }, 10, 0);
    }
}
