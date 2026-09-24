<?php

function cubestheme_landing_field($key, $label, $name, $type, $extra = array())
{
    return array_merge(array(
        'key' => $key,
        'label' => $label,
        'name' => $name,
        'type' => $type,
    ), $extra);
}

function cubestheme_register_plugin_landing_fields()
{
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    $text = function ($key, $label, $name) {
        return cubestheme_landing_field($key, $label, $name, 'text');
    };
    $area = function ($key, $label, $name) {
        return cubestheme_landing_field($key, $label, $name, 'textarea', array('rows' => 3, 'new_lines' => ''));
    };

    acf_add_local_field_group(array(
        'key' => 'group_plugin_landing',
        'title' => 'Plugin landing',
        'fields' => array(
            cubestheme_landing_field('field_landing_product_note', '', '', 'message', array(
                'message' => 'Buy buttons use the product that lists this page as its Landing page. You can also pick that product below. Prices come from its 1 site, 5 sites, and unlimited variations.',
            )),
            cubestheme_landing_field('field_landing_product', 'Product', 'landing_product', 'post_object', array(
                'post_type' => array('product'),
                'return_format' => 'id',
                'ui' => 1,
            )),
            cubestheme_landing_field('field_tab_landing_hero', 'Hero', '', 'tab'),
            $text('field_landing_hero_label', 'Label', 'landing_hero_label'),
            $text('field_landing_hero_title', 'Title', 'landing_hero_title'),
            $area('field_landing_hero_text', 'Text', 'landing_hero_text'),
            $text('field_landing_hero_primary', 'Primary button', 'landing_hero_primary'),
            $text('field_landing_hero_secondary', 'Secondary button', 'landing_hero_secondary'),
            cubestheme_landing_field('field_landing_hero_image', 'Image', 'landing_hero_image', 'image', array('return_format' => 'array', 'preview_size' => 'medium')),
            cubestheme_landing_field('field_tab_landing_needs', 'Needs', '', 'tab'),
            $text('field_landing_needs_label', 'Label', 'landing_needs_label'),
            $text('field_landing_needs_title', 'Title', 'landing_needs_title'),
            $area('field_landing_needs_text', 'Text', 'landing_needs_text'),
            cubestheme_landing_field('field_landing_needs_items', 'Points', 'landing_needs_items', 'repeater', array(
                'layout' => 'table',
                'button_label' => 'Add point',
                'sub_fields' => array(
                    $area('field_landing_needs_item_text', 'Text', 'item_text'),
                ),
            )),
            cubestheme_landing_field('field_landing_needs_image', 'Image', 'landing_needs_image', 'image', array('return_format' => 'array', 'preview_size' => 'medium')),
            cubestheme_landing_field('field_tab_landing_features', 'Features', '', 'tab'),
            $text('field_landing_features_label', 'Label', 'landing_features_label'),
            $text('field_landing_features_title', 'Title', 'landing_features_title'),
            $area('field_landing_features_text', 'Text', 'landing_features_text'),
            cubestheme_landing_field('field_landing_features', 'Feature cards', 'landing_features', 'repeater', array(
                'layout' => 'block',
                'button_label' => 'Add feature',
                'sub_fields' => array(
                    $text('field_landing_feature_tag', 'Tag', 'tag'),
                    $text('field_landing_feature_title', 'Title', 'title'),
                    $area('field_landing_feature_text', 'Text', 'text'),
                ),
            )),
            cubestheme_landing_field('field_tab_landing_preview', 'Preview', '', 'tab'),
            $text('field_landing_preview_label', 'Label', 'landing_preview_label'),
            $text('field_landing_preview_title', 'Title', 'landing_preview_title'),
            $area('field_landing_preview_text', 'Text', 'landing_preview_text'),
            cubestheme_landing_field('field_landing_preview_image', 'Image', 'landing_preview_image', 'image', array('return_format' => 'array', 'preview_size' => 'medium')),
            cubestheme_landing_field('field_tab_landing_compare', 'Free vs PRO', '', 'tab'),
            $text('field_landing_compare_label', 'Label', 'landing_compare_label'),
            $text('field_landing_compare_title', 'Title', 'landing_compare_title'),
            $area('field_landing_compare_text', 'Text', 'landing_compare_text'),
            $text('field_landing_compare_free', 'Free column', 'landing_compare_free'),
            $text('field_landing_compare_pro', 'PRO column', 'landing_compare_pro'),
            cubestheme_landing_field('field_landing_compare_rows', 'Rows', 'landing_compare_rows', 'repeater', array(
                'layout' => 'table',
                'button_label' => 'Add row',
                'sub_fields' => array(
                    $text('field_landing_compare_feature', 'Feature', 'feature'),
                    $text('field_landing_compare_free_text', 'Free', 'free_text'),
                    $text('field_landing_compare_pro_text', 'PRO', 'pro_text'),
                ),
            )),
            cubestheme_landing_field('field_tab_landing_market', 'Compare', '', 'tab'),
            $text('field_landing_market_label', 'Label', 'landing_market_label'),
            $text('field_landing_market_title', 'Title', 'landing_market_title'),
            $area('field_landing_market_text', 'Text', 'landing_market_text'),
            $text('field_landing_market_ours', 'Our column', 'landing_market_ours'),
            $text('field_landing_market_competitor_a', 'Competitor 1', 'landing_market_competitor_a'),
            $text('field_landing_market_competitor_b', 'Competitor 2', 'landing_market_competitor_b'),
            cubestheme_landing_field('field_landing_market_rows', 'Rows', 'landing_market_rows', 'repeater', array(
                'layout' => 'block',
                'button_label' => 'Add row',
                'sub_fields' => array(
                    $text('field_landing_market_feature', 'Feature', 'feature'),
                    $text('field_landing_market_ours_text', 'Our note', 'ours'),
                    cubestheme_landing_field('field_landing_market_a', 'Competitor 1 included', 'competitor_a', 'true_false', array('ui' => 1)),
                    cubestheme_landing_field('field_landing_market_b', 'Competitor 2 included', 'competitor_b', 'true_false', array('ui' => 1)),
                ),
            )),
            cubestheme_landing_field('field_tab_landing_pricing', 'Pricing', '', 'tab'),
            $text('field_landing_pricing_label', 'Label', 'landing_pricing_label'),
            $text('field_landing_pricing_title', 'Title', 'landing_pricing_title'),
            $area('field_landing_pricing_text', 'Text', 'landing_pricing_text'),
            cubestheme_landing_field('field_landing_plans', 'Plan copy', 'landing_plans', 'repeater', array(
                'layout' => 'block',
                'button_label' => 'Add plan copy',
                'instructions' => 'Match the variation slug: 1-site, 5-sites, or unlimited. The price and the cart button come from the product.',
                'sub_fields' => array(
                    $text('field_landing_plan_slug', 'Variation slug', 'plan_slug'),
                    $area('field_landing_plan_description', 'Description', 'plan_description'),
                    cubestheme_landing_field('field_landing_plan_bullets', 'Bullets', 'plan_bullets', 'textarea', array(
                        'rows' => 4,
                        'new_lines' => '',
                        'instructions' => 'One line per bullet.',
                    )),
                    $text('field_landing_plan_button', 'Button text', 'plan_button'),
                    cubestheme_landing_field('field_landing_plan_highlight', 'Most popular', 'plan_highlight', 'true_false', array('ui' => 1)),
                ),
            )),
            cubestheme_landing_field('field_tab_landing_faq', 'FAQ', '', 'tab'),
            $text('field_landing_faq_label', 'Label', 'landing_faq_label'),
            $text('field_landing_faq_title', 'Title', 'landing_faq_title'),
            cubestheme_landing_field('field_landing_faq', 'Questions', 'landing_faq', 'repeater', array(
                'layout' => 'block',
                'button_label' => 'Add question',
                'sub_fields' => array(
                    $text('field_landing_faq_question', 'Question', 'question'),
                    $area('field_landing_faq_answer', 'Answer', 'answer'),
                ),
            )),
            $text('field_landing_faq_button', 'Footer button', 'landing_faq_button'),
            cubestheme_landing_field('field_landing_faq_url', 'Footer button URL', 'landing_faq_url', 'url'),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'page_template',
                    'operator' => '==',
                    'value' => 'page-for-plugin-landing.php',
                ),
            ),
        ),
        'position' => 'normal',
        'style' => 'default',
        'active' => true,
    ));
}

function cubestheme_landing_value($page_id, $name)
{
    if (!function_exists('get_field')) {
        return '';
    }

    $value = get_field($name, $page_id);

    return is_string($value) ? $value : '';
}

function cubestheme_landing_rows($page_id, $name)
{
    if (!function_exists('get_field')) {
        return array();
    }

    $rows = get_field($name, $page_id);

    return is_array($rows) ? $rows : array();
}

function cubestheme_landing_image_url($page_id, $name, $fallback)
{
    if (!function_exists('get_field')) {
        return $fallback;
    }

    $image = get_field($name, $page_id);
    if (is_array($image) && !empty($image['url'])) {
        return $image['url'];
    }

    if (is_numeric($image)) {
        $url = wp_get_attachment_image_url((int) $image, 'full');
        if ($url) {
            return $url;
        }
    }

    return $fallback;
}

function cubestheme_landing_product_id($page_id)
{
    if (function_exists('get_field')) {
        $picked = (int) get_field('landing_product', $page_id);
        if ($picked > 0 && get_post_type($picked) === 'product') {
            return $picked;
        }
    }

    $ids = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'post_parent' => 0,
        'posts_per_page' => 10,
        'fields' => 'ids',
        'meta_key' => 'wsh_landing_page_id',
        'meta_value' => (string) $page_id,
    ));

    $page_slug = (string) get_post_field('post_name', $page_id);
    $best = 0;
    $best_score = -1;

    foreach ($ids as $id) {
        $product = function_exists('wc_get_product') ? wc_get_product($id) : null;
        if (!$product) {
            continue;
        }

        $score = 0;
        if ($product->is_type(array('variable', 'variable-subscription'))) {
            $score += 2;
        }

        $plugin_slug = (string) get_post_meta($id, 'wsh_plugin_slug', true);
        $plugin_slug = str_replace('wsh-', '', $plugin_slug);
        $product_slug = str_replace('wsh-', '', (string) $product->get_slug());
        if ($page_slug !== '' && ($plugin_slug === $page_slug || strpos($product_slug, $page_slug) === 0)) {
            $score += 10;
        }

        if ($score > $best_score) {
            $best_score = $score;
            $best = (int) $id;
        }
    }

    return $best;
}

function cubestheme_landing_variations($product_id)
{
    if ($product_id <= 0 || !function_exists('wc_get_product')) {
        return array();
    }

    $product = wc_get_product($product_id);
    if (!$product || !method_exists($product, 'get_children')) {
        return array();
    }

    $order = array('1-site' => 1, '5-sites' => 2, 'unlimited' => 3);
    $items = array();

    foreach ($product->get_children() as $child_id) {
        $variation = wc_get_product($child_id);
        if (!$variation || !$variation->is_type(array('variation', 'subscription_variation')) || !$variation->is_purchasable() || !$variation->variation_is_visible()) {
            continue;
        }

        $attributes = $variation->get_variation_attributes();
        $attribute_key = $attributes ? (string) array_key_first($attributes) : '';
        $slug = $attribute_key !== '' ? (string) $attributes[$attribute_key] : '';
        $taxonomy = str_replace('attribute_', '', $attribute_key);
        $label = $slug;

        if ($taxonomy !== '' && taxonomy_exists($taxonomy)) {
            $term = get_term_by('slug', $slug, $taxonomy);
            if ($term instanceof WP_Term) {
                $label = $term->name;
            }
        }

        $items[] = array(
            'variation' => $variation,
            'parent_id' => $product_id,
            'slug' => $slug,
            'label' => $label,
            'attributes' => $attributes,
            'sort' => $order[$slug] ?? 50,
        );
    }

    usort($items, function ($a, $b) {
        return $a['sort'] <=> $b['sort'];
    });

    return $items;
}

function cubestheme_landing_cart_url($page_id, $item)
{
    $args = array(
        'add-to-cart' => $item['parent_id'],
        'variation_id' => $item['variation']->get_id(),
        'quantity' => 1,
    );

    foreach ($item['attributes'] as $key => $value) {
        $args[$key] = $value;
    }

    return add_query_arg($args, get_permalink($page_id));
}

function cubestheme_landing_price_text($variation)
{
    $amount = html_entity_decode(wp_strip_all_tags(wc_price($variation->get_price())), ENT_QUOTES, get_bloginfo('charset'));
    $period = '';

    if (class_exists('WC_Subscriptions_Product')) {
        $interval = (int) WC_Subscriptions_Product::get_interval($variation);
        $period_slug = (string) WC_Subscriptions_Product::get_period($variation);
        if ($period_slug !== '') {
            $period = $interval > 1 ? $interval . ' ' . $period_slug . 's' : $period_slug;
        }
    }

    return $period !== '' ? $amount . ' / ' . $period : $amount;
}

function cubestheme_landing_plan_copy($page_id)
{
    $copy = array();

    foreach (cubestheme_landing_rows($page_id, 'landing_plans') as $plan) {
        $slug = isset($plan['plan_slug']) ? (string) $plan['plan_slug'] : '';
        if ($slug !== '') {
            $copy[$slug] = $plan;
        }
    }

    return $copy;
}

function cubestheme_seed_plugin_landing()
{
    if (get_option('cubestheme_plugin_landing_seeded') === '1' || !function_exists('update_field')) {
        return;
    }

    $page = get_page_by_path('views-counter-pro');
    if (!$page instanceof WP_Post) {
        return;
    }

    update_post_meta($page->ID, '_wp_page_template', 'page-for-plugin-landing.php');

    if (get_field('landing_hero_title', $page->ID)) {
        update_option('cubestheme_plugin_landing_seeded', '1', false);
        return;
    }

    $id = $page->ID;
    $fields = array(
        'landing_hero_label' => 'WSH Views Counter PRO',
        'landing_hero_title' => 'Advanced WordPress views analytics for news portals & serious content sites',
        'landing_hero_text' => 'Upgrade the free WSH Views Counter plugin with geo statistics, referrer analytics, real-time panels, WooCommerce funnels, exports and anti-bot protection - all engineered for high-traffic WordPress installations.',
        'landing_hero_primary' => 'Get PRO license',
        'landing_hero_secondary' => 'See all features',
        'landing_needs_label' => 'Why you need more than raw view counts',
        'landing_needs_title' => 'Google Analytics is overkill. Basic counters are not enough.',
        'landing_needs_text' => 'You already track views with the free WSH Views Counter plugin and now want the next level: proper analytics dashboards, geo and referrer insights, real-time overview and export tools.',
        'landing_features_label' => 'Features',
        'landing_features_title' => 'What WSH Views Counter PRO adds on top of the free plugin',
        'landing_features_text' => 'The free version already gives you a reliable tracking engine. PRO turns that data into a full analytics suite with interactive charts, geo breakdowns, referrer insights, real-time panels and exports.',
        'landing_preview_label' => 'Preview',
        'landing_preview_title' => 'A clean dashboard your editors will actually use',
        'landing_preview_text' => 'WSH Views Counter PRO is designed for editorial and marketing teams - fast to load, easy to understand, and focused on the numbers that matter.',
        'landing_compare_label' => 'Free vs PRO',
        'landing_compare_title' => 'Already using the free plugin? Here\'s what PRO unlocks.',
        'landing_compare_text' => 'The free WSH Views Counter plugin gives you a rock-solid tracking foundation. WSH Views Counter PRO builds on top of it - no data loss, no reconfiguration required.',
        'landing_compare_free' => 'WSH Views Counter (Free)',
        'landing_compare_pro' => 'WSH Views Counter PRO',
        'landing_market_label' => 'Compare plugins',
        'landing_market_title' => 'How WSH Views Counter PRO stacks up against popular alternatives.',
        'landing_market_text' => 'Built for sites that need accurate views analytics, lightweight performance and native WordPress-ready reporting instead of generic traffic summaries.',
        'landing_market_ours' => 'WSH Views Counter PRO',
        'landing_market_competitor_a' => 'Post Views Counter',
        'landing_market_competitor_b' => 'MonsterInsights',
        'landing_pricing_label' => 'Pricing',
        'landing_pricing_title' => 'Choose your WSH Views Counter PRO license',
        'landing_pricing_text' => 'Simple annual licenses with updates and premium support included. Upgrade between plans at any time — just pay the difference.',
        'landing_faq_label' => 'Frequently Answer Questions',
        'landing_faq_title' => 'Getting Started Your Essential Questions Answered',
        'landing_faq_button' => 'See more',
        'landing_faq_url' => home_url('/docs/'),
    );

    foreach ($fields as $name => $value) {
        update_field($name, $value, $id);
    }

    update_field('landing_needs_items', array(
        array('item_text' => 'You want to know which posts are trending today - not just "all time most viewed".'),
        array('item_text' => 'You need to see how specific authors, categories or pages perform over the last 7 / 30 / 90 days.'),
        array('item_text' => 'You care about where traffic comes from: search, social, newsletters, internal links, direct.'),
        array('item_text' => 'You run a news portal or magazine and must detect viral content as it happens.'),
        array('item_text' => 'You manage WooCommerce products and want to connect views with add-to-cart and order events.'),
        array('item_text' => 'You want all this inside WordPress - without the complexity of Google Analytics dashboards.'),
    ), $id);

    update_field('landing_features', array(
        array('tag' => 'Analytics', 'title' => 'Interactive charts & time ranges', 'text' => 'Daily, weekly and monthly charts with quick presets (1 / 7 / 30 / 90 days) and comparison views. Instantly see how your traffic evolves over time without leaving WordPress.'),
        array('tag' => 'Geo', 'title' => 'Geo-location statistics', 'text' => 'See top countries and cities per post, author or site-wide. Understand where your audience actually comes from and which regions react to which content.'),
        array('tag' => 'Referrers', 'title' => 'Referrer analytics', 'text' => 'Break down traffic by source: Google, social networks, newsletters, direct, internal links and external referrers. Identify which channels drive engaged readership.'),
        array('tag' => 'Real-time', 'title' => 'Lightweight real-time panel', 'text' => 'Monitor current active visitors, latest views in a live feed and which posts are being read right now - without the heavy overhead of external trackers.'),
        array('tag' => 'WooCommerce', 'title' => 'Basic funnels for WooCommerce', 'text' => 'Connect product views with add-to-cart and order events. Spot products that people see but don\'t buy, and optimize titles, images or pricing.'),
        array('tag' => 'Security', 'title' => 'Anti-bot & anti-fraud tools', 'text' => 'Filter out known bots, crawlers and suspicious patterns. Keep your stats as close to reality as possible, especially when you sell ads based on impressions.'),
    ), $id);

    update_field('landing_compare_rows', array(
        array('feature' => 'Core post views tracking (any post type)', 'free_text' => 'Yes', 'pro_text' => 'Yes'),
        array('feature' => 'AJAX / JavaScript tracking (cache-friendly)', 'free_text' => 'Default method, works great with caching plugins', 'pro_text' => 'Same engine, plus additional analytics on top'),
        array('feature' => 'Daily stats tables & basic dashboard widget', 'free_text' => 'Basic overview', 'pro_text' => 'Enhanced with additional panels and filters'),
        array('feature' => 'Admin reports (by post, author, date)', 'free_text' => 'Standard filters', 'pro_text' => 'Advanced filters & AJAX tables for large datasets'),
        array('feature' => 'Shortcodes & automatic view display', 'free_text' => 'Yes', 'pro_text' => 'Yes'),
        array('feature' => 'Geo analytics (countries / cities)', 'free_text' => 'Yes', 'pro_text' => 'Full Geo Analytics module'),
        array('feature' => 'Referrer analytics (domains, search, social)', 'free_text' => 'Yes', 'pro_text' => 'Per-day, per-post and site-wide referrer breakdowns'),
        array('feature' => 'Real-time analytics (live visitors & hits)', 'free_text' => 'Yes', 'pro_text' => 'Lightweight live panel with active visitors'),
        array('feature' => 'WooCommerce product analytics & funnels', 'free_text' => 'Yes', 'pro_text' => 'Views to add-to-cart to orders (basic funnels)'),
        array('feature' => 'Export views data (CSV / Excel / JSON)', 'free_text' => 'Yes', 'pro_text' => 'Export PRO module for deeper analysis'),
        array('feature' => 'Popular & trending widgets / blocks', 'free_text' => 'Yes', 'pro_text' => 'PRO widgets & Gutenberg blocks'),
        array('feature' => 'Viral detection & trending alerts', 'free_text' => 'Yes', 'pro_text' => 'Detect sudden spikes in views and mark posts as trending'),
        array('feature' => 'Developer tools (REST API, JS events, webhooks)', 'free_text' => 'Yes', 'pro_text' => 'Built-in integration points for custom workflows'),
        array('feature' => 'Anti-bot and anti-fraud filters', 'free_text' => 'Yes', 'pro_text' => 'Extended bot detection & rate limiting options'),
    ), $id);

    $market = array();
    foreach (array(
        array('Lightweight post views tracking', 'Native tables, optimized for views data'),
        array('AJAX / JavaScript tracking (cache-friendly)', 'Enabled by default, no extra setup'),
        array('Geo analytics (country / city)', 'Built-in Geo Analytics module'),
        array('Referrer analytics (domains, search, social)', 'Native referrer breakdown by date & post'),
        array('Real-time analytics (active visitors & live feed)', 'Lightweight, self-hosted live panel'),
        array('WooCommerce product funnels', 'Views -> add to cart -> orders (basic)'),
        array('Export views data (CSV / Excel / JSON)', 'Direct export from WordPress'),
        array('Designed for high-traffic sites', 'Custom tables & background processing'),
    ) as $row) {
        $market[] = array(
            'feature' => $row[0],
            'ours' => $row[1],
            'competitor_a' => 0,
            'competitor_b' => 0,
        );
    }
    update_field('landing_market_rows', $market, $id);

    update_field('landing_plans', array(
        array(
            'plan_slug' => '1-site',
            'plan_description' => 'Perfect for a single blog, portal or WooCommerce store.',
            'plan_bullets' => "Use on 1 WordPress site\nAll PRO features included\n1 year of updates & support",
            'plan_button' => 'Buy single site',
            'plan_highlight' => 0,
        ),
        array(
            'plan_slug' => '5-sites',
            'plan_description' => 'Best choice for agencies, publishers or multi-site owners.',
            'plan_bullets' => "Use on up to 5 WordPress sites\nAll PRO features included\nPriority support for editors & admins",
            'plan_button' => 'Buy 5 sites',
            'plan_highlight' => 1,
        ),
        array(
            'plan_slug' => 'unlimited',
            'plan_description' => 'For serious agencies, networks and hosting partners.',
            'plan_bullets' => "Use on unlimited sites\nAll PRO features included\nBest price per site as you scale",
            'plan_button' => 'Buy unlimited',
            'plan_highlight' => 0,
        ),
    ), $id);

    update_field('landing_faq', array(
        array('question' => 'Why choose a suite instead of single plugins?', 'answer' => 'Suites are curated combinations of plugins that work together out of the box – with a lower price than buying each license separately.'),
        array('question' => 'Why choose a suite instead of single plugins?', 'answer' => 'Suites are curated combinations of plugins that work together out of the box – with a lower price than buying each license separately.'),
        array('question' => 'How can I access the My Dashboard?', 'answer' => 'Suites are curated combinations of plugins that work together out of the box – with a lower price than buying each license separately.'),
        array('question' => 'One ecosystem, many plugins', 'answer' => 'Suites are curated combinations of plugins that work together out of the box – with a lower price than buying each license separately.'),
    ), $id);

    update_option('cubestheme_plugin_landing_seeded', '1', false);
}

function cubestheme_landing_mark($positive)
{
    $icon = $positive ? 'market-comparison-check-icon' : 'market-comparison-cross-icon';
    $class = $positive ? 'market-comparison-icon--positive' : 'market-comparison-icon--negative';

    return '<svg class="market-comparison-icon ' . esc_attr($class) . '" width="24" height="24" aria-hidden="true" focusable="false"><use href="#' . esc_attr($icon) . '"></use></svg>';
}

function cubestheme_landing_is_included($text)
{
    $text = strtolower(trim((string) $text));

    return $text !== '' && $text !== 'no' && $text !== '—' && $text !== '-';
}

function cubestheme_find_landing_product($needles)
{
    $products = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'post_parent' => 0,
        'posts_per_page' => -1,
    ));

    $best = 0;
    $best_score = -1;

    foreach ($products as $product) {
        $title = (string) $product->post_title;
        $matched = false;

        foreach ($needles as $needle) {
            if (stripos($title, $needle) !== false) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            continue;
        }

        $score = 1;
        $wc_product = function_exists('wc_get_product') ? wc_get_product($product->ID) : null;

        if ($wc_product && $wc_product->is_type(array('variable', 'variable-subscription'))) {
            $score += 10;
        } elseif ($wc_product && $wc_product->is_type(array('subscription', 'simple'))) {
            $score += 4;
        }

        if (stripos($title, '1 year') !== false) {
            $score -= 2;
        }

        $score += max(0, 80 - strlen($title));

        if ($score > $best_score) {
            $best_score = $score;
            $best = (int) $product->ID;
        }
    }

    return $best;
}

function cubestheme_seed_remaining_landings()
{
    if (!function_exists('update_field')) {
        return;
    }

    $files = glob(get_template_directory() . '/inc/landings/*.php');
    if (!$files) {
        return;
    }

    sort($files);

    foreach ($files as $file) {
        $landing = include $file;
        if (!is_array($landing) || empty($landing['slug']) || empty($landing['fields']['landing_hero_title'])) {
            continue;
        }

        $slug = sanitize_title($landing['slug']);
        $seed_key = 'cubestheme_landing_seeded_' . $slug;
        if (get_option($seed_key) === '1') {
            continue;
        }

        $page = get_page_by_path($slug);

        if (!$page instanceof WP_Post) {
            $page_id = wp_insert_post(array(
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => $landing['title'],
                'post_name' => $slug,
            ), true);

            if (is_wp_error($page_id) || !$page_id) {
                continue;
            }

            $page = get_post($page_id);
        }

        if (!$page instanceof WP_Post) {
            continue;
        }

        update_post_meta($page->ID, '_wp_page_template', 'page-for-plugin-landing.php');

        if (!get_field('landing_hero_title', $page->ID)) {

        $id = $page->ID;

        foreach ($landing['fields'] as $name => $value) {
            update_field($name, $value, $id);
        }

        $needs = array();
        foreach ($landing['needs'] as $text) {
            $needs[] = array('item_text' => $text);
        }
        update_field('landing_needs_items', $needs, $id);

        update_field('landing_features', $landing['features'], $id);

        $compare = array();
        foreach ($landing['compare'] as $row) {
            $compare[] = array(
                'feature' => $row[0],
                'free_text' => $row[1],
                'pro_text' => $row[2],
            );
        }
        update_field('landing_compare_rows', $compare, $id);

        $market = array();
        foreach ($landing['market'] as $row) {
            $market[] = array(
                'feature' => $row[0],
                'ours' => $row[1],
                'competitor_a' => (int) $row[2],
                'competitor_b' => (int) $row[3],
            );
        }
        update_field('landing_market_rows', $market, $id);

        $plan_bullets = array(
            '1-site' => "Use on 1 WordPress site\nAll PRO features included\n1 year of updates and support",
            '5-sites' => "Use on up to 5 WordPress sites\nAll PRO features included\n1 year of updates and support",
            'unlimited' => "Use on unlimited WordPress sites\nAll PRO features included\n1 year of updates and support",
        );
        $plan_buttons = array(
            '1-site' => 'Buy 1 site',
            '5-sites' => 'Buy 5 sites',
            'unlimited' => 'Buy unlimited',
        );
        $plans = array();
        foreach (array('1-site', '5-sites', 'unlimited') as $plan_slug) {
            $plans[] = array(
                'plan_slug' => $plan_slug,
                'plan_description' => isset($landing['plans'][$plan_slug]) ? $landing['plans'][$plan_slug] : '',
                'plan_bullets' => $plan_bullets[$plan_slug],
                'plan_button' => $plan_buttons[$plan_slug],
                'plan_highlight' => $plan_slug === '5-sites' ? 1 : 0,
            );
        }
        update_field('landing_plans', $plans, $id);

        $faq = array();
        foreach ($landing['faq'] as $item) {
            $faq[] = array(
                'question' => $item[0],
                'answer' => $item[1],
            );
        }
        update_field('landing_faq', $faq, $id);

        }

        cubestheme_link_landing_product($page->ID, $landing);

        if (get_field('landing_hero_title', $page->ID)) {
            update_option($seed_key, '1', false);
        }
    }
}

function cubestheme_link_landing_product($page_id, $landing)
{
    $product_id = cubestheme_find_landing_product(isset($landing['needles']) ? $landing['needles'] : array());
    if ($product_id <= 0) {
        return;
    }

    update_field('landing_product', $product_id, $page_id);
    update_post_meta($product_id, 'wsh_landing_page_id', $page_id);
}

function cubestheme_fix_catalog_competitor_labels()
{
    if (get_option('cubestheme_catalog_market_labels') === '1' || !function_exists('update_field')) {
        return;
    }

    $page = get_page_by_path('product-catalog');
    if (!$page instanceof WP_Post) {
        return;
    }

    update_field('landing_market_competitor_a', 'Basic feeds', $page->ID);
    update_field('landing_market_competitor_b', 'Enterprise platforms', $page->ID);
    update_option('cubestheme_catalog_market_labels', '1', false);
}

add_action('acf/init', function () {
    cubestheme_register_plugin_landing_fields();
    cubestheme_seed_plugin_landing();
    cubestheme_seed_remaining_landings();
    cubestheme_fix_catalog_competitor_labels();
});
