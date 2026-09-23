<?php
// File: includes/Home/Sections/LatestSection.php

namespace WSH\APIROCKET\Home\Sections;

use WSH\APIROCKET\Support\Helpers;

if ( ! defined('ABSPATH') ) exit;

final class LatestSection implements SectionInterface {

    public function build(array $cfg, array $ctx): array {

        $limit = max(1, min(50, (int)($cfg['limit'] ?? 5)));
        $exclude_dup = !empty($cfg['exclude_duplicates']);
        $excluded = $exclude_dup ? (array)($ctx['excluded'] ?? []) : [];

        $q = new \WP_Query([
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => $limit,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
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
        ];
    }
}
