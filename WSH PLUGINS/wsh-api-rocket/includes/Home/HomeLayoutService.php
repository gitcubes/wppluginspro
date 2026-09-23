<?php
// PATCH: File: includes/Home/HomeLayoutService.php
// Add transient caching. Cache key includes:
// - pro/free mode
// - layout hash
// - pagination params relevant for MoreNews section

namespace WSH\APIROCKET\Home;

use WSH\APIROCKET\Licensing\Features;
use WSH\APIROCKET\Support\Cache;

if ( ! defined('ABSPATH') ) exit;

final class HomeLayoutService {

    const CACHE_PREFIX = 'wsh_ar_home_';

    public function build(\WP_REST_Request $request): array {

        $is_pro = Features::enabled('home_pro');

        $repo   = new LayoutRepository();
        $layout = $repo->get_layout($is_pro);

        // include page params (more_page etc.) in cache key
        $cache_context = $this->extract_cache_context($layout, $request);

        $hash = md5(wp_json_encode([$layout, $cache_context, $is_pro]));
        $ttl  = (int) get_option('wsh_ar_home_cache_ttl', 120); // seconds
        $ttl  = max(10, min(3600, $ttl));

        $cache_key = self::CACHE_PREFIX . ($is_pro ? 'pro_' : 'free_') . $hash;

        $cached = Cache::get($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        // global excluded across sections
        $ctx = [
            'excluded' => [],
            'request'  => $request,
        ];

        $sections_out = [];
        foreach (($layout['sections'] ?? []) as $section_cfg) {
            // Sanity: if category_latest has no category_id, skip
            if (is_array($section_cfg) && ($section_cfg['type'] ?? '') === 'category_latest') {
                $cat = (int)($section_cfg['category_id'] ?? 0);
                if ($cat <= 0) {
                    continue;
                }
            }

            $handler = SectionFactory::make($section_cfg);
            if (!$handler) continue;

            $result = $handler->build($section_cfg, $ctx);

            // update excluded
            if (!empty($result['excluded_ids']) && is_array($result['excluded_ids'])) {
                $ctx['excluded'] = array_values(array_unique(array_merge(
                    $ctx['excluded'],
                    array_map('intval', $result['excluded_ids'])
                )));
            }

            $sections_out[] = [
                'id'    => (string)($section_cfg['id'] ?? ''),
                'title' => (string)($section_cfg['title'] ?? ''),
                'type'  => (string)($section_cfg['type'] ?? ''),
                'items' => $result['items'] ?? [],
                'meta'  => $result['meta'] ?? new \stdClass(),
            ];
        }

        $out = [
            'sections' => $sections_out,
        ];

        Cache::set($cache_key, $out, $ttl);

        return $out;
    }

    private function extract_cache_context(array $layout, \WP_REST_Request $request): array {
        $ctx = [];

        // detect more_news paged_param(s) to vary cache per page
        foreach (($layout['sections'] ?? []) as $s) {
            if (!is_array($s)) continue;
            if (($s['type'] ?? '') !== 'more_news') continue;

            $param = sanitize_key((string)($s['paged_param'] ?? 'more_page'));
            if ($param === '') $param = 'more_page';

            $ctx[$param] = max(1, (int)($request->get_param($param) ?? 1));
        }

        return $ctx;
    }
}
