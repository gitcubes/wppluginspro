<?php
namespace WSH\APIROCKET\Licensing;

if ( ! defined('ABSPATH') ) exit;

class License {
    // TODO: nalepi tvoju klasu ovde i prilagodi:
    // - OPTION_KEY = 'wsh_ar_license_key'
    // - TRANSIENT_LAST_VERIFY = 'wsh_ar_last_verify'
    // - plugin_slug = WSH_AR_SLUG
    // - version = WSH_AR_VERSION

    public static function init(): void {
        add_action('admin_init', [__CLASS__, 'maybe_auto_verify']);
    }

    public static function is_active(): bool {
        // TODO: tvoja logika valid
        return false;
    }

    public static function maybe_auto_verify(): void {
        // TODO
    }
}
