<?php
// File: includes/Support/Response.php

namespace WSH\APIROCKET\Support;

if ( ! defined('ABSPATH') ) exit;

final class Response {

    public static function ok($data = [], string $message = 'Success', int $http = 200): \WP_REST_Response {
        return new \WP_REST_Response([
            'status'  => 1,
            'message' => $message,
            'data'    => $data,
        ], $http);
    }

    /**
     * @param string $code machine code
     * @param string $message human message
     * @param int $http http status
     * @param array $data extra payload
     */
    public static function error(string $code, string $message, int $http = 400, array $data = []): \WP_Error {
        return new \WP_Error($code, $message, array_merge(['status' => $http], $data));
    }
}
