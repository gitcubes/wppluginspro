<?php
// File: includes/Support/RateLimiter.php

namespace WSH\APIROCKET\Support;

if ( ! defined('ABSPATH') ) exit;

final class RateLimiter {

    const OPT_RPM_FREE = 'wsh_ar_rate_limit_rpm_free'; // requests per minute
    const OPT_RPM_PRO  = 'wsh_ar_rate_limit_rpm_pro';  // requests per minute

    // add near top of class
    private static array $last_info = [];

    public static function get_last_info(): array {
        return self::$last_info;
    }

    /**
     * @param string $bucket "free"|"pro"
     * @param string $api_key
     * @param string $ip
     * @param int $window_seconds
     * @return true|\WP_Error
     */
    public static function check(string $bucket, string $api_key, string $ip, int $window_seconds = 60) {

        $bucket = ($bucket === 'pro') ? 'pro' : 'free';

        $limit = ($bucket === 'pro')
            ? (int) get_option(self::OPT_RPM_PRO, 120)
            : (int) get_option(self::OPT_RPM_FREE, 60);

        // clamp
        $limit = max(1, min(5000, $limit));

        // Identify client (key + ip). If no key, treat as "anon".
        $api_key = trim((string)$api_key);
        if ($api_key === '') $api_key = 'anon';

        $ip = trim((string)$ip);
        if ($ip === '') $ip = '0.0.0.0';

        $fingerprint = substr(hash('sha256', $bucket . '|' . $api_key . '|' . $ip), 0, 32);
        $tkey = 'wsh_ar_rl_' . $bucket . '_' . $fingerprint;

        $state = get_transient($tkey);
        $now   = time();

        if (!is_array($state)) {
            $state = [
                'count' => 0,
                'start' => $now,
            ];
        }

        $start = isset($state['start']) ? (int)$state['start'] : $now;
        $count = isset($state['count']) ? (int)$state['count'] : 0;

        // reset window if expired
        if ($now - $start >= $window_seconds) {
            $start = $now;
            $count = 0;
        }

        $count++;

        $state['start'] = $start;
        $state['count'] = $count;

        // Keep transient until end of window (+ small buffer)
        $ttl = max(1, ($window_seconds - ($now - $start)) + 2);
        set_transient($tkey, $state, $ttl);

        $reset_in     = max(0, $window_seconds - ($now - $start));
        $reset_epoch  = $now + $reset_in;
        $remaining    = max(0, $limit - $count);

        self::$last_info = [
            'bucket'        => $bucket,
            'limit'         => $limit,
            'remaining'     => $remaining,
            'reset'         => $reset_epoch,
            'reset_in'      => $reset_in,
            'window_seconds'=> $window_seconds,
        ];


        if ($count > $limit) {
            $retry_after = max(1, $window_seconds - ($now - $start));

            $err = new \WP_Error(
                'rate_limited',
                'Too many requests.',
                [
                    'status' => 429,
                    'limit' => $limit,
                    'window_seconds' => $window_seconds,
                    'retry_after' => $retry_after,
                ]
            );

            self::$last_info['retry_after'] = $retry_after;
            
            return $err;
        }

        return true;
    }
}
