<?php
// File: includes/Rest/Controllers/WooControllerPro.php
namespace WSH\APIROCKET\Rest\Controllers;
if ( ! defined('ABSPATH') ) exit;

final class WooControllerPro {
    public function list_products(\WP_REST_Request $request) {
        return new \WP_REST_Response([
            'status' => 0,
            'message' => 'PRO feature (woo) not implemented yet.',
            'data' => [],
        ], 501);
    }
}
