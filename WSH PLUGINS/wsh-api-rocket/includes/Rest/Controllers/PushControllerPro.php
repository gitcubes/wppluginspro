<?php
// File: includes/Rest/Controllers/PushControllerPro.php
namespace WSH\APIROCKET\Rest\Controllers;
if ( ! defined('ABSPATH') ) exit;

final class PushControllerPro {
    public function send(\WP_REST_Request $request) {
        return new \WP_REST_Response([
            'status' => 0,
            'message' => 'PRO feature (push) not implemented yet.',
            'data' => [],
        ], 501);
    }
}
