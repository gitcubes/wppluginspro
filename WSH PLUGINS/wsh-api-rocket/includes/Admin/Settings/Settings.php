<?php
// PATCH: File: includes/Admin/Settings/Settings.php
// Save the "public mode" radio option too.

namespace WSH\APIROCKET\Admin\Settings;

use WSH\APIROCKET\Rest\Auth\ApiKeyAuth;
use WSH\APIROCKET\Admin\Settings\Tabs\TabApi;
use WSH\APIROCKET\Admin\Settings\Tabs\TabHome;
use WSH\APIROCKET\Home\LayoutRepository;

if ( ! defined('ABSPATH') ) exit;

final class Settings {

    public function init(): void {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function menu(): void {
        add_menu_page(
            __('WSH API Rocket', 'wsh-api-rocket'),
            __('WSH API Rocket', 'wsh-api-rocket'),
            'manage_options',
            'wsh-api-rocket',
            [Page::class, 'render'],
            'dashicons-rest-api'
        );
    }

    public function register_settings(): void {

        register_setting('wsh_ar', ApiKeyAuth::OPTION_API_KEYS);
        register_setting('wsh_ar', LayoutRepository::OPT_LAYOUT_FREE);
        register_setting('wsh_ar', LayoutRepository::OPT_LAYOUT_PRO);
        register_setting('wsh_ar', 'wsh_ar_public_mode');

        register_setting('wsh_ar', \WSH\APIROCKET\Support\RateLimiter::OPT_RPM_FREE);
        register_setting('wsh_ar', \WSH\APIROCKET\Support\RateLimiter::OPT_RPM_PRO);

        register_setting('wsh_ar', \WSH\APIROCKET\Support\Ip::OPT_TRUST_PROXY);
        register_setting('wsh_ar', \WSH\APIROCKET\Support\Ip::OPT_PROXY_MODE);
        register_setting('wsh_ar', 'wsh_ar_delete_on_uninstall');

        add_action('admin_init', function () {
            if (!is_admin() || !current_user_can('manage_options')) return;
            if (!isset($_POST['option_page']) || $_POST['option_page'] !== 'wsh_ar') return;

            // access mode
            if (isset($_POST['wsh_ar_public_mode'])) {
                $mode = sanitize_key((string)wp_unslash($_POST['wsh_ar_public_mode']));
                $mode = ($mode === 'public') ? 'public' : 'api_key';
                update_option('wsh_ar_public_mode', $mode);
            }

            // API keys raw textarea
            if (isset($_POST[ApiKeyAuth::OPTION_API_KEYS . '__raw'])) {
                $raw = wp_unslash($_POST[ApiKeyAuth::OPTION_API_KEYS . '__raw']);
                $arr = TabApi::sanitize_raw_to_array($raw);
                update_option(ApiKeyAuth::OPTION_API_KEYS, $arr);
            }

            // Home FREE builder raw fields -> layout array
            if (isset($_POST['wsh_ar_home_free__raw']) && is_array($_POST['wsh_ar_home_free__raw'])) {
                $raw = wp_unslash($_POST['wsh_ar_home_free__raw']);
                $layout = TabHome::sanitize_raw_to_layout($raw);
                update_option(LayoutRepository::OPT_LAYOUT_FREE, $layout);
            }

            if (isset($_POST[\WSH\APIROCKET\Support\RateLimiter::OPT_RPM_FREE])) {
                $v = (int) wp_unslash($_POST[\WSH\APIROCKET\Support\RateLimiter::OPT_RPM_FREE]);
                $v = max(1, min(5000, $v));
                update_option(\WSH\APIROCKET\Support\RateLimiter::OPT_RPM_FREE, $v);
            }

            if (isset($_POST[\WSH\APIROCKET\Support\RateLimiter::OPT_RPM_PRO])) {
                $v = (int) wp_unslash($_POST[\WSH\APIROCKET\Support\RateLimiter::OPT_RPM_PRO]);
                $v = max(1, min(5000, $v));
                update_option(\WSH\APIROCKET\Support\RateLimiter::OPT_RPM_PRO, $v);
            }

            if (isset($_POST[\WSH\APIROCKET\Support\Ip::OPT_TRUST_PROXY])) {
                $v = (int) wp_unslash($_POST[\WSH\APIROCKET\Support\Ip::OPT_TRUST_PROXY]);
                update_option(\WSH\APIROCKET\Support\Ip::OPT_TRUST_PROXY, $v === 1 ? 1 : 0);
            } else {
                // unchecked checkbox
                update_option(\WSH\APIROCKET\Support\Ip::OPT_TRUST_PROXY, 0);
            }

            if (isset($_POST[\WSH\APIROCKET\Support\Ip::OPT_PROXY_MODE])) {
                $m = sanitize_key((string) wp_unslash($_POST[\WSH\APIROCKET\Support\Ip::OPT_PROXY_MODE]));
                if (!in_array($m, ['auto','cloudflare','x_forwarded_for'], true)) $m = 'auto';
                update_option(\WSH\APIROCKET\Support\Ip::OPT_PROXY_MODE, $m);
            }

            if (isset($_POST['wsh_ar_delete_on_uninstall'])) {
                update_option('wsh_ar_delete_on_uninstall', 1);
            } else {
                update_option('wsh_ar_delete_on_uninstall', 0);
            }

        });
    }
}


// Quick test guide (copy into your Postman as new requests)
// These are not files, just ready-to-use samples:

/*
1) HOME
GET {{base_url}}/wp-json/wsh/v1/home
Header:
X-WSH-API-KEY: {{api_key}}

2) MENU
GET {{base_url}}/wp-json/wsh/v1/menu?location=primary
Header:
X-WSH-API-KEY: {{api_key}}

3) CATEGORIES
GET {{base_url}}/wp-json/wsh/v1/categories?per_page=100
Header:
X-WSH-API-KEY: {{api_key}}

4) POSTS LIST
GET {{base_url}}/wp-json/wsh/v1/posts?page=1&per_page=20&category_id=3
Header:
X-WSH-API-KEY: {{api_key}}

5) SINGLE POST
GET {{base_url}}/wp-json/wsh/v1/posts/123
Header:
X-WSH-API-KEY: {{api_key}}
*/
