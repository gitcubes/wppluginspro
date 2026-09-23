<?php
// File: includes/Core/Activator.php

namespace WSH\APIROCKET\Core;

use WSH\APIROCKET\Support\RateLimiter;

if ( ! defined('ABSPATH') ) exit;

final class Activator {

    public static function activate(): void {
        if (get_option('wsh_ar_home_cache_ttl', null) === null) {
            add_option('wsh_ar_home_cache_ttl', 120);
        }
        if (get_option('wsh_ar_public_mode', null) === null) {
            add_option('wsh_ar_public_mode', 'api_key');
        }
        if (get_option(RateLimiter::OPT_RPM_FREE, null) === null) {
            add_option(RateLimiter::OPT_RPM_FREE, 60);
        }
        if (get_option(RateLimiter::OPT_RPM_PRO, null) === null) {
            add_option(RateLimiter::OPT_RPM_PRO, 120);
        }

        if (get_option(\WSH\APIROCKET\Support\Ip::OPT_TRUST_PROXY, null) === null) {
            add_option(\WSH\APIROCKET\Support\Ip::OPT_TRUST_PROXY, 0);
        }
        
        if (get_option(\WSH\APIROCKET\Support\Ip::OPT_PROXY_MODE, null) === null) {
            add_option(\WSH\APIROCKET\Support\Ip::OPT_PROXY_MODE, 'auto');
        }

    }
}
