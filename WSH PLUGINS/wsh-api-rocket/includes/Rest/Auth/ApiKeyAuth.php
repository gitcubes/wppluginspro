<?php
// File: includes/Rest/Auth/ApiKeyAuth.php

namespace WSH\APIROCKET\Rest\Auth;

if ( ! defined('ABSPATH') ) exit;

final class ApiKeyAuth {

    const OPTION_API_KEYS = 'wsh_ar_api_keys';

    public static function get_request_key(\WP_REST_Request $request): string {
        $key = $request->get_header('x-wsh-api-key');
        return is_string($key) ? trim($key) : '';
    }

    public static function validate_request(\WP_REST_Request $request): bool {
        $key = self::get_request_key($request);
        $keys = get_option(self::OPTION_API_KEYS, []);
        if (!is_array($keys)) $keys = [];

        return ($key !== '' && in_array($key, $keys, true));
    }
}
