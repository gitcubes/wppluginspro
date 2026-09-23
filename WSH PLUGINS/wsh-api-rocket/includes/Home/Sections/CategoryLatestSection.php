<?php
// File: includes/Home/Sections/CategoryLatestSection.php

namespace WSH\APIROCKET\Home\Sections;

use WSH\APIROCKET\Support\Helpers;

if ( ! defined('ABSPATH') ) exit;

final class CategoryLatestSection implements SectionInterface {

    public function build(array $cfg, array $ctx): array {

        $limit = max(1, min(50, (int)($cfg['limit'] ?? 4)));
        $cat   = (int)($cfg['category_id'] ?? 0);

        $exclude_dup = !empty($cfg['exclude_duplicates']);
        $excluded = $exclude_dup ? (array)($ctx['excluded'] ?? []) : [];

        if ($cat <= 0) {
            return ['items' => [], 'excluded_ids' => [], 'meta' => ['error' => 'category_id missing']];
        }

        $q = new \WP_Query([
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => $limit,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
            'tax_query' => [
                [
                    'taxonomy' => 'category',
                    'field'    => 'term_id',
                    'terms'    => [$cat],
                ],
            ],
            'post__not_in'        => array_values(array_unique(array_map('intval', $excluded))),
        ]);

        $items = [];
        $ids   = [];

        if ($q->have_posts()) {
            foreach ($q->posts as $post) {
                if (!$post instanceof \WP_Post) continue;
                $items[] = Helpers::parse_post($post);
                $ids[]   = (int)$post->ID;
            }
        }

        return [
            'items'        => $items,
            'excluded_ids' => $ids,
            'meta'         => [
                'category_id' => $cat,
                'category'    => get_cat_name($cat) ?: '',
            ],
        ];
    }
}
