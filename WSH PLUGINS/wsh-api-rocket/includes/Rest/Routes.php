<?php
// File: includes/Rest/Routes.php

namespace WSH\APIROCKET\Rest;

use WSH\APIROCKET\Rest\Controllers\HomeController;
use WSH\APIROCKET\Rest\Controllers\ContentController;
use WSH\APIROCKET\Rest\Controllers\PushControllerPro;
use WSH\APIROCKET\Rest\Controllers\WooControllerPro;
use WSH\APIROCKET\Rest\Auth\Permissions;

if ( ! defined('ABSPATH') ) exit;

final class Routes {

    public function register(): void {

        $ns = 'wsh/v1';

        $home    = new HomeController();
        $content = new ContentController();

        /**
         * HOME
         * GET /wp-json/wsh/v1/home
         */
        register_rest_route($ns, '/home', [
            'methods'             => 'GET',
            'callback'            => [$home, 'get_home'],
            'permission_callback' => [Permissions::class, 'public_or_api_key'],
        ]);

        /**
         * MENU
         * GET /wp-json/wsh/v1/menu?location=primary OR ?menu_id=123
         */
        register_rest_route($ns, '/menu', [
            'methods'             => 'GET',
            'callback'            => [$content, 'get_menu'],
            'permission_callback' => [Permissions::class, 'public_or_api_key'],
            'args' => [
                'location' => [
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_key',
                ],
                'menu_id' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        /**
         * CATEGORIES
         * GET /wp-json/wsh/v1/categories?per_page=100
         */
        register_rest_route($ns, '/categories', [
            'methods'             => 'GET',
            'callback'            => [$content, 'get_categories'],
            'permission_callback' => [Permissions::class, 'public_or_api_key'],
            'args' => [
                'per_page' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 100,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        $v = (int)$value;
                        return $v >= 1 && $v <= 200;
                    },
                ],
            ],
        ]);

        /**
         * POSTS LIST
         * GET /wp-json/wsh/v1/posts?page=1&per_page=20&category_id=3&tag=politika&search=abc
         */
        register_rest_route($ns, '/posts', [
            'methods'             => 'GET',
            'callback'            => [$content, 'list_posts'],
            'permission_callback' => [Permissions::class, 'public_or_api_key'],
            'args' => [
                'page' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        $v = (int)$value;
                        return $v >= 1 && $v <= 50;
                    },
                ],
                'category_id' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                ],
                'tag' => [
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'search' => [
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        /**
         * SINGLE POST
         * GET /wp-json/wsh/v1/posts/123
         */
        register_rest_route($ns, '/posts/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$content, 'get_post'],
            'permission_callback' => [Permissions::class, 'public_or_api_key'],
            'args' => [
                'id' => [
                    'type'              => 'integer',
                    'required'          => true,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return ((int)$value > 0);
                    },
                ],
            ],
        ]);


        /**
         * SEARCH (alias)
         * GET /wp-json/wsh/v1/search?q=abc&page=1&per_page=20
         */
        register_rest_route($ns, '/search', [
            'methods'             => 'GET',
            'callback'            => [$content, 'search'],
            'permission_callback' => [Permissions::class, 'public_or_api_key'],
            'args' => [
                'q' => [
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'page' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'type'              => 'integer',
                    'required'          => false,
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        $v = (int)$value;
                        return $v >= 1 && $v <= 50;
                    },
                ],
            ],
        ]);


        /**
         * PRO: PUSH
         * POST /wp-json/wsh/v1/push/send
         */
        register_rest_route($ns, '/push/send', [
            'methods'             => 'POST',
            'callback'            => [new PushControllerPro(), 'send'],
            'permission_callback' => fn($req) => Permissions::pro_required($req, 'push'),
        ]);

        /**
         * PRO: WOO (placeholder)
         * GET /wp-json/wsh/v1/woo/products
         */
        register_rest_route($ns, '/woo/products', [
            'methods'             => 'GET',
            'callback'            => [new WooControllerPro(), 'list_products'],
            'permission_callback' => fn($req) => Permissions::pro_required($req, 'woo'),
        ]);
    }
}
