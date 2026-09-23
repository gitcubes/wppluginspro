<?php
// File: includes/Rest/Controllers/ContentController.php

namespace WSH\APIROCKET\Rest\Controllers;
use WSH\APIROCKET\Support\Response;

if (! defined('ABSPATH')) exit;

final class ContentController
{

    /**
     * GET /menu?location=primary OR /menu?menu_id=123
     */
    public function get_menu(\WP_REST_Request $request)
    {

        $location = sanitize_key((string)($request->get_param('location') ?? ''));
        $menu_id  = (int)($request->get_param('menu_id') ?? 0);

        if ($menu_id <= 0 && $location !== '') {
            $locations = get_nav_menu_locations();
            if (is_array($locations) && !empty($locations[$location])) {
                $menu_id = (int)$locations[$location];
            }
        }

        if ($menu_id <= 0) {
            // fallback: first menu
            $menus = wp_get_nav_menus();
            if (!empty($menus) && isset($menus[0]->term_id)) {
                $menu_id = (int)$menus[0]->term_id;
            }
        }

        if ($menu_id <= 0) {
            return new \WP_REST_Response([
                'status' => 0,
                'message' => 'No menu found.',
                'data' => [],
            ], 200);
        }

        $items = wp_get_nav_menu_items($menu_id);
        if (!is_array($items)) $items = [];

        // Build tree by menu_item_parent
        $by_parent = [];
        foreach ($items as $it) {
            $parent = (int)$it->menu_item_parent;
            $by_parent[$parent][] = $it;
        }

        $build = function($parent_id) use (&$build, $by_parent) {
            $out = [];
            foreach (($by_parent[$parent_id] ?? []) as $it) {
                $id = (int)$it->ID;

                $node = [
                    'id'        => $id,
                    'title'     => html_entity_decode((string)$it->title),
                    'url'       => (string)$it->url,
                    'type'      => (string)$it->type,   // taxonomy|post_type|custom
                    'object'    => (string)$it->object, // category|page|post...
                    'object_id' => (int)$it->object_id,
                ];

                $node['target'] = $this->normalize_menu_target($node);

                $node['children'] = $build($id);
                $out[] = $node;
            }
            return $out;
        };

        /*return Response::ok([
            'menu_id' => $menu_id,
            'items'   => $build(0),
        ]);*/

        return new \WP_REST_Response([
            'status'  => 1,
            'message' => 'Success',
            'data'    => [
                'menu_id' => $menu_id,
                'items'   => $build(0),
            ],
        ], 200);
    }

    /**
     * GET /categories?per_page=100
     */
    public function get_categories(\WP_REST_Request $request)
    {

        $per_page = max(1, min(200, (int)($request->get_param('per_page') ?? 100)));

        $terms = get_terms([
            'taxonomy'   => 'category',
            'hide_empty' => false,
            'number'     => $per_page,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        $data = [];
        if (is_array($terms)) {
            foreach ($terms as $t) {
                if (!($t instanceof \WP_Term)) continue;
                $data[] = [
                    'id'    => (int)$t->term_id,
                    'name'  => (string)$t->name,
                    'slug'  => (string)$t->slug,
                    'count' => (int)$t->count,
                ];
            }
        }

        return new \WP_REST_Response(['status' => 1, 'message' => 'Success', 'data' => $data], 200);
    }

    /**
     * GET /posts?category_id=3&tag=politika&search=abc&page=1&per_page=20
     */
    public function list_posts(\WP_REST_Request $request)
    {

        $page     = max(1, (int)($request->get_param('page') ?? 1));
        $per_page = max(1, min(50, (int)($request->get_param('per_page') ?? 20)));

        $category_id = (int)($request->get_param('category_id') ?? 0);
        $tag         = sanitize_text_field((string)($request->get_param('tag') ?? ''));
        $search      = sanitize_text_field((string)($request->get_param('search') ?? ''));

        $args = [
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'paged'               => $page,
            'posts_per_page'      => $per_page,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
        ];

        if ($search !== '') $args['s'] = $search;

        if ($category_id > 0) {
            $args['tax_query'][] = [
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => [$category_id],
            ];
        }

        if ($tag !== '') {
            // accepts slug or name; WP uses slug normally
            $args['tag'] = $tag;
        }

        $q = new \WP_Query($args);

        $items = [];
        if ($q->have_posts()) {
            foreach ($q->posts as $post) {
                if (!$post instanceof \WP_Post) continue;
                $items[] = \WSH\APIROCKET\Support\Helpers::parse_post($post);
            }
        }

        return new \WP_REST_Response([
            'status'  => 1,
            'message' => 'Success',
            'data'    => [
                'items' => $items,
                'page'  => $page,
                'per_page' => $per_page,
                'total' => (int)$q->found_posts,
                'total_pages' => (int)$q->max_num_pages,
            ],
        ], 200);
    }

    /**
     * GET /posts/{id}
     */
    public function get_post(\WP_REST_Request $request)
    {

        $id = (int)$request['id'];
        $post = get_post($id);

        if (!$post || $post->post_status !== 'publish') {
            return new \WP_Error('not_found', 'Post not found', ['status' => 404]);
        }

        $data = \WSH\APIROCKET\Support\Helpers::parse_post($post);

        // Optional: full content (safe)
        $data['content'] = apply_filters('the_content', $post->post_content);

        return new \WP_REST_Response(['status' => 1, 'message' => 'Success', 'data' => $data], 200);
    }

    /**
     * GET /search?q=...
     */
    public function search(\WP_REST_Request $request)
    {

        // map q -> search param used by list_posts()
        $q = sanitize_text_field((string)($request->get_param('q') ?? ''));

        // Create a shallow copy request-like params by setting into request
        $request->set_param('search', $q);

        // Optionally allow category/tag filters later, for now keep it simple
        return $this->list_posts($request);
    }

    private function normalize_menu_target(array $node): array
    {

        $url       = (string)($node['url'] ?? '');
        $type      = (string)($node['type'] ?? '');
        $object    = (string)($node['object'] ?? '');
        $object_id = (int)($node['object_id'] ?? 0);

        // 1) Taxonomy: category (najčešće)
        if ($type === 'taxonomy' && $object === 'category' && $object_id > 0) {
            return [
                'type'     => 'category',
                'id'       => $object_id,
                'endpoint' => '/wp-json/wsh/v1/posts?category_id=' . $object_id,
            ];
        }

        // 2) Post types: post/page
        if ($type === 'post_type' && $object_id > 0) {

            if ($object === 'post') {
                return [
                    'type'     => 'post',
                    'id'       => $object_id,
                    'endpoint' => '/wp-json/wsh/v1/posts/' . $object_id,
                ];
            }

            if ($object === 'page') {
                // trenutno nemamo /pages endpoint (može kasnije)
                return [
                    'type'     => 'page',
                    'id'       => $object_id,
                    'endpoint' => null,
                ];
            }

            // any custom post type
            return [
                'type'     => $object !== '' ? $object : 'post_type',
                'id'       => $object_id,
                'endpoint' => null,
            ];
        }

        // 3) Custom link: pokušaj da mapiraš internal WP URL na post ili term
        // (radi kad korisnik nalepi link na kategoriju ili post)
        if ($url !== '') {
            $home = home_url();
            if (strpos($url, $home) === 0) {

                // try post id
                $pid = url_to_postid($url);
                if ($pid > 0) {
                    return [
                        'type'     => 'post',
                        'id'       => (int)$pid,
                        'endpoint' => '/wp-json/wsh/v1/posts/' . (int)$pid,
                    ];
                }

                // try category by URL
                $term = $this->term_from_url($url, 'category');
                if ($term && !is_wp_error($term)) {
                    return [
                        'type'     => 'category',
                        'id'       => (int)$term->term_id,
                        'endpoint' => '/wp-json/wsh/v1/posts?category_id=' . (int)$term->term_id,
                    ];
                }
            }
        }

        // 4) External
        return [
            'type'     => 'external',
            'id'       => 0,
            'endpoint' => null,
        ];
    }

    private function term_from_url(string $url, string $taxonomy): ?\WP_Term
    {
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') return null;

        $path = trim($path, '/');

        // naive: slug is last segment
        $parts = explode('/', $path);
        $slug  = end($parts);
        if (!$slug) return null;

        $term = get_term_by('slug', $slug, $taxonomy);
        if ($term instanceof \WP_Term) return $term;

        return null;
    }
}
