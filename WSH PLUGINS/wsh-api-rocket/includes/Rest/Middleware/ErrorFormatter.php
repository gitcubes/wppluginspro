<?php
// File: includes/Rest/Middleware/ErrorFormatter.php

namespace WSH\APIROCKET\Rest\Middleware;

if ( ! defined('ABSPATH') ) exit;

final class ErrorFormatter {

    public function init(): void {
        add_filter('rest_post_dispatch', [$this, 'format'], 10, 3);
    }

    /**
     * @param \WP_HTTP_Response|\WP_REST_Response|mixed $result
     * @param \WP_REST_Server $server
     * @param \WP_REST_Request $request
     */
    public function format($result, $server, $request) {

        if (!is_wp_error($result)) {
            return $result;
        }

        $status = 400;
        $data = $result->get_error_data();
        if (is_array($data) && isset($data['status'])) {
            $status = (int)$data['status'];
        }

        $code    = (string) $result->get_error_code();
        $message = (string) $result->get_error_message();

        $payload = [
            'status'  => 0,
            'message' => $message !== '' ? $message : 'Error',
            'error'   => [
                'code' => $code,
                'data' => is_array($data) ? $data : new \stdClass(),
            ],
        ];

        // Useful hint for rate limit
        if (is_array($data) && isset($data['retry_after'])) {
            $payload['error']['retry_after'] = (int)$data['retry_after'];
        }

        return new \WP_REST_Response($payload, $status);
    }
}
