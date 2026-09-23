<?php
// File: includes/Home/Sections/MoreNewsSection.php

namespace WSH\APIROCKET\Home\Sections;

use WSH\APIROCKET\Support\Helpers;

if ( ! defined('ABSPATH') ) exit;

final class MoreNewsSection implements SectionInterface {

    public function build(array $cfg, array $ctx): array {

        /** @var \WP_REST_Request|null $request */
        $request = $ctx['request'] ?? null;

        $limit = max(1, min(50, (int)($cfg['limit'] ?? 6)));
        $exclude_dup = !empty($cfg['exclude_duplicates']);
        $excluded = $exclude_dup ? (array)($ctx['excluded'] ?? []) : [];

        $paged_param = sanitize_key((string)($cfg['paged_param'] ?? 'more_page'));
        if ($paged_param === '') $paged_param = 'more_page';

        $page = 1;
        if ($request instanceof \WP_REST_Request) {
            $page = (int)($request->get_param($paged_param) ?? 1);
        }
        $page = max(1, $page);

        $q = new \WP_Query([
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => $limit,
            'paged'               => $page,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
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
                'page' => $page,
                'paged_param' => $paged_param,
            ],
        ];
    }
}
