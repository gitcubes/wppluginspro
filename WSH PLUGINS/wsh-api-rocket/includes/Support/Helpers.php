<?php
// File: includes/Support/Helpers.php

namespace WSH\APIROCKET\Support;

if ( ! defined('ABSPATH') ) exit;

final class Helpers {

    public static function parse_post(\WP_Post $post): array {

        $id = (int)$post->ID;

        $image = get_the_post_thumbnail_url($id, 'large');
        if (!$image) $image = '';

        $excerpt = has_excerpt($id)
            ? wp_strip_all_tags(get_the_excerpt($id))
            : wp_trim_words(wp_strip_all_tags($post->post_content), 30, '...');

        $cats = [];
        $terms = get_the_terms($id, 'category');
        if (is_array($terms)) {
            foreach ($terms as $t) {
                $cats[] = [
                    'id'   => (int)$t->term_id,
                    'name' => (string)$t->name,
                    'slug' => (string)$t->slug,
                ];
            }
        }

        $data = [
            'id'        => $id,
            'title'     => get_the_title($id),
            'date_gmt'  => get_post_time('c', true, $post),
            'date'      => get_post_time('c', false, $post),
            'excerpt'   => $excerpt,
            'url'       => get_permalink($id),
            'image'     => $image,
            'categories'=> $cats,
        ];

        return apply_filters('wsh_ar_parse_post', $data, $post);
    }
}
