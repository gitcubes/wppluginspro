<?php
// File: includes/Home/Sections/ManualPostsSection.php

namespace WSH\APIROCKET\Home\Sections;

use WSH\APIROCKET\Support\Helpers;

if ( ! defined('ABSPATH') ) exit;

final class ManualPostsSection implements SectionInterface {

    public function build(array $cfg, array $ctx): array {

        $limit = max(1, min(50, (int)($cfg['limit'] ?? 5)));
        $ids   = isset($cfg['post_ids']) && is_array($cfg['post_ids']) ? $cfg['post_ids'] : [];
        $ids   = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($limit > 0) {
            $ids = array_slice($ids, 0, $limit);
        }

        if (empty($ids)) {
            return ['items' => [], 'excluded_ids' => []];
        }

        $q = new \WP_Query([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => count($ids),
            'post__in'       => $ids,
            'orderby'        => 'post__in',
            'no_found_rows'  => true,
        ]);

        $items = [];
        $used  = [];

        if ($q->have_posts()) {
            foreach ($q->posts as $post) {
                if (!$post instanceof \WP_Post) continue;
                $items[] = Helpers::parse_post($post);
                $used[]  = (int)$post->ID;
            }
        }

        return [
            'items'        => $items,
            'excluded_ids' => $used,
        ];
    }
}
