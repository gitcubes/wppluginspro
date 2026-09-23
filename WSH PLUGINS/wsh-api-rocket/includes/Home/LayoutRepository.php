<?php
// PATCH: File: includes/Home/LayoutRepository.php
// Allow admin preview override via filter.

namespace WSH\APIROCKET\Home;

if ( ! defined('ABSPATH') ) exit;

final class LayoutRepository {

    const OPT_LAYOUT_FREE = 'wsh_ar_home_layout_free';
    const OPT_LAYOUT_PRO  = 'wsh_ar_home_layout_pro';

    public function get_layout(bool $pro): array {

        // Preview override (admin AJAX)
        $override = apply_filters('wsh_ar_home_layout_override', null);
        if (is_array($override)) {
            return $override;
        }

        $opt = $pro ? self::OPT_LAYOUT_PRO : self::OPT_LAYOUT_FREE;
        $raw = get_option($opt, []);

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return $decoded;
        }
        if (is_array($raw)) return $raw;

        return [
            'sections' => [
                ['id'=>'header', 'type'=>'manual_posts', 'title'=>'Top', 'limit'=>5, 'post_ids'=>[]],
                ['id'=>'latest', 'type'=>'latest', 'title'=>'Latest', 'limit'=>5, 'exclude_duplicates'=>true],
                ['id'=>'more',   'type'=>'more_news', 'title'=>'More', 'limit'=>6, 'exclude_duplicates'=>true, 'paged_param'=>'more_page'],
            ]
        ];
    }
}
