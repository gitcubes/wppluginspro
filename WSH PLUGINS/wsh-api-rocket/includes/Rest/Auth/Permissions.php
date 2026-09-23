<?php
// File: includes/Rest/Auth/Permissions.php

namespace WSH\APIROCKET\Rest\Auth;

use WSH\APIROCKET\Licensing\Features;
use WSH\APIROCKET\Support\RateLimiter;
use WSH\APIROCKET\Support\Ip;

if ( ! defined('ABSPATH') ) exit;

final class Permissions {

    /**
     * FREE endpoints: public OR api_key. If api_key required, also rate-limit.
     *
     * @return true|\WP_Error
     */
    public static function public_or_api_key(\WP_REST_Request $request) {

        $mode = get_option('wsh_ar_public_mode', 'api_key'); // 'public' or 'api_key'
        $mode = ($mode === 'public') ? 'public' : 'api_key';

        // If public, still rate-limit by IP (optional). If you don't want it, remove this block.
        if ($mode === 'public') {
             $ip = Ip::get_client_ip();
            $rl = RateLimiter::check('free', 'anon', $ip, 60);
            if (is_wp_error($rl)) return $rl;
            return true;
        }

        // api_key mode
        if (!ApiKeyAuth::validate_request($request)) {
            return new \WP_Error('forbidden', 'Invalid API key.', ['status' => 403]);
        }

        $key = ApiKeyAuth::get_request_key($request);
        $ip = Ip::get_client_ip();

        $rl = RateLimiter::check('free', $key, $ip, 60);
        if (is_wp_error($rl)) return $rl;

        $request->set_attribute('wsh_ar_rate', RateLimiter::get_last_info());
        return true;
    }

    /**
     * PRO endpoints: feature enabled + api key + rate limit (separate bucket).
     *
     * @return true|\WP_Error
     */
    public static function pro_required(\WP_REST_Request $request, string $feature) {

        if (!Features::enabled($feature)) {
            return new \WP_Error('pro_required', 'PRO feature requires active license.', ['status' => 403]);
        }

        if (!ApiKeyAuth::validate_request($request)) {
            return new \WP_Error('forbidden', 'Invalid API key.', ['status' => 403]);
        }

        $key = ApiKeyAuth::get_request_key($request);
         $ip = Ip::get_client_ip();

        $rl = RateLimiter::check('pro', $key, $ip, 60);
        if (is_wp_error($rl)) return $rl;

        $request->set_attribute('wsh_ar_rate', RateLimiter::get_last_info());
        return true;
    }

    private static function get_ip(): string {
        // keep it simple + safe (avoid trusting forwarded headers by default)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return is_string($ip) ? $ip : '';
    }
}
