<?php
namespace WSH\APIROCKET\Rest\Middleware;

if ( ! defined('ABSPATH') ) exit;

final class RateLimitHeaders {

    public function init(): void {
        add_filter('rest_post_dispatch', [$this, 'add_headers'], 20, 3);
    }

    public function add_headers($result, $server, $request) {

        if (!($result instanceof \WP_REST_Response)) {
            return $result;
        }

        $rate = $request->get_attribute('wsh_ar_rate');
        if (!is_array($rate) || empty($rate['limit'])) {
            return $result;
        }

        $result->header('X-RateLimit-Limit', (string) ((int)$rate['limit']));
        $result->header('X-RateLimit-Remaining', (string) ((int)($rate['remaining'] ?? 0)));
        $result->header('X-RateLimit-Reset', (string) ((int)($rate['reset'] ?? 0)));

        if (!empty($rate['retry_after'])) {
            $result->header('Retry-After', (string) ((int)$rate['retry_after']));
        }

        return $result;
    }
}
