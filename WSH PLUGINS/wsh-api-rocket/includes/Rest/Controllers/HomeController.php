<?php
namespace WSH\APIROCKET\Rest\Controllers;

use WSH\APIROCKET\Home\HomeLayoutService;

if ( ! defined('ABSPATH') ) exit;

final class HomeController {

    public function get_home(\WP_REST_Request $request) {
        $svc = new HomeLayoutService();
        $data = $svc->build($request);

        return new \WP_REST_Response([
            'status'  => 1,
            'message' => 'Success',
            'data'    => $data,
        ], 200);
    }
}
