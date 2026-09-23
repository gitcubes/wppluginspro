<?php
// File: includes/Rest/Rest.php

namespace WSH\APIROCKET\Rest;

use WSH\APIROCKET\Rest\Middleware\ErrorFormatter;
use WSH\APIROCKET\Rest\Middleware\RateLimitHeaders;

if ( ! defined('ABSPATH') ) exit;

final class Rest {
    public function init(): void {
        // Middleware: unify WP_Error format
        (new ErrorFormatter())->init();

        add_action('rest_api_init', function () {
            (new Routes())->register();
        });
    }
}
