<?php
namespace WSH\APIROCKET\Licensing;

if ( ! defined('ABSPATH') ) exit;

final class Features {

    private static array $features = [];

    public static function init(): void {
        // Free features always enabled
        self::$features = [
            'content'   => true,
            'menu'      => true,
            'home_free' => true,
            // PRO defaults
            'home_pro'  => false,
            'push'      => false,
            'woo'       => false,
            'paywall'   => false,
            'webhooks'  => false,
        ];

        // If license active -> enable PRO
        if (License::is_active()) {
            self::$features['home_pro'] = true;
            self::$features['push']     = true;
            self::$features['woo']      = true;
            self::$features['paywall']  = true;
            self::$features['webhooks'] = true;
        }

        /**
         * Allow override via filter (npr. server vrati features mapu)
         */
        self::$features = apply_filters('wsh_ar_features', self::$features);
    }

    public static function enabled(string $key): bool {
        return !empty(self::$features[$key]);
    }
}
