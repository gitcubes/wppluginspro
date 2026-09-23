<?php
// PATCH: File: includes/Admin/Settings/Tabs/TabHome.php
// Add "Preview JSON" button + textarea output.

namespace WSH\APIROCKET\Admin\Settings\Tabs;

use WSH\APIROCKET\Home\LayoutRepository;

if ( ! defined('ABSPATH') ) exit;

final class TabHome {

    public function render(): void {

        $repo   = new LayoutRepository();
        $layout = $repo->get_layout(false);

        $sections = isset($layout['sections']) && is_array($layout['sections']) ? $layout['sections'] : [];

        $header = $this->find_section($sections, 'header', 'manual_posts');
        $main   = $this->find_section($sections, 'main', 'category_latest');
        $latest = $this->find_section($sections, 'latest', 'latest');
        $more   = $this->find_section($sections, 'more', 'more_news');

        $cats = get_categories(['hide_empty' => false]);

        echo '<h2>' . esc_html__('Home (FREE)', 'wsh-api-rocket') . '</h2>';
        echo '<p>' . esc_html__(
            'Configure a simple home layout: Header (manual posts), Main (category latest), Latest, and More News.',
            'wsh-api-rocket'
        ) . '</p>';

        echo '<p style="margin:12px 0;">';
        echo '<button type="button" class="button" id="wsh-ar-home-preview-btn">' . esc_html__('Preview JSON', 'wsh-api-rocket') . '</button>';
        echo '</p>';

        echo '<p><textarea id="wsh-ar-home-preview-output" class="large-text code" rows="12" readonly placeholder="Preview will appear here..."></textarea></p>';

        echo '<table class="form-table" role="presentation">';

        // HEADER
        echo '<tr><th colspan="2"><h3 style="margin:0;">' . esc_html__('Header: Manual posts', 'wsh-api-rocket') . '</h3></th></tr>';

        $header_title = (string)($header['title'] ?? 'Top');
        $header_limit = (int)($header['limit'] ?? 5);
        $header_ids   = (array)($header['post_ids'] ?? []);
        $header_ids_s = implode(',', array_map('intval', $header_ids));

        echo '<tr><th scope="row">' . esc_html__('Title', 'wsh-api-rocket') . '</th><td>';
        echo '<input type="text" class="regular-text" name="wsh_ar_home_free__raw[header][title]" value="' . esc_attr($header_title) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Limit', 'wsh-api-rocket') . '</th><td>';
        echo '<input type="number" min="1" max="50" name="wsh_ar_home_free__raw[header][limit]" value="' . esc_attr((string)$header_limit) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Post IDs', 'wsh-api-rocket') . '</th><td>';
        echo '<input type="text" class="large-text code" name="wsh_ar_home_free__raw[header][post_ids]" value="' . esc_attr($header_ids_s) . '">';
        echo '<p class="description">' . esc_html__('Comma-separated post IDs, e.g. 12,45,88', 'wsh-api-rocket') . '</p>';
        echo '</td></tr>';

        // MAIN
        echo '<tr><th colspan="2"><h3 style="margin:0;">' . esc_html__('Main: Category latest', 'wsh-api-rocket') . '</h3></th></tr>';

        $main_title = (string)($main['title'] ?? 'Main');
        $main_limit = (int)($main['limit'] ?? 4);
        $main_cat   = (int)($main['category_id'] ?? 0);

        echo '<tr><th scope="row">' . esc_html__('Title', 'wsh-api-rocket') . '</th><td>';
        echo '<input type="text" class="regular-text" name="wsh_ar_home_free__raw[main][title]" value="' . esc_attr($main_title) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Category', 'wsh-api-rocket') . '</th><td>';
        echo '<select name="wsh_ar_home_free__raw[main][category_id]">';
        echo '<option value="0">' . esc_html__('-- Select --', 'wsh-api-rocket') . '</option>';
        foreach ($cats as $c) {
            $sel = ((int)$c->term_id === $main_cat) ? ' selected' : '';
            echo '<option value="' . esc_attr((string)$c->term_id) . '"' . $sel . '>' . esc_html($c->name) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Limit', 'wsh-api-rocket') . '</th><td>';
        echo '<input type="number" min="1" max="50" name="wsh_ar_home_free__raw[main][limit]" value="' . esc_attr((string)$main_limit) . '">';
        echo '</td></tr>';

        // LATEST
        echo '<tr><th colspan="2"><h3 style="margin:0;">' . esc_html__('Latest', 'wsh-api-rocket') . '</h3></th></tr>';

        $latest_title = (string)($latest['title'] ?? 'Latest');
        $latest_limit = (int)($latest['limit'] ?? 5);
        $latest_excl  = !empty($latest['exclude_duplicates']);

        echo '<tr><th scope="row">' . esc_html__('Title', 'wsh-api-rocket') . '</th><td>';
        echo '<input type="text" class="regular-text" name="wsh_ar_home_free__raw[latest][title]" value="' . esc_attr($latest_title) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Limit', 'wsh-api-rocket') . '</th><td>';
        echo '<input type="number" min="1" max="50" name="wsh_ar_home_free__raw[latest][limit]" value="' . esc_attr((string)$latest_limit) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Exclude duplicates', 'wsh-api-rocket') . '</th><td>';
        echo '<label><input type="checkbox" name="wsh_ar_home_free__raw[latest][exclude_duplicates]" value="1"' . checked($latest_excl, true, false) . '> '
            . esc_html__('Do not repeat posts already used above', 'wsh-api-rocket') . '</label>';
        echo '</td></tr>';

        // MORE
        echo '<tr><th colspan="2"><h3 style="margin:0;">' . esc_html__('More news', 'wsh-api-rocket') . '</h3></th></tr>';

        $more_title = (string)($more['title'] ?? 'More');
        $more_limit = (int)($more['limit'] ?? 6);
        $more_excl  = !empty($more['exclude_duplicates']);
        $more_param = (string)($more['paged_param'] ?? 'more_page');

        echo '<tr><th scope="row">' . esc_html__('Title', 'wsh-api-rocket') . '</th><td>';
        echo '<input type="text" class="regular-text" name="wsh_ar_home_free__raw[more][title]" value="' . esc_attr($more_title) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Limit', 'wsh-api-rocket') . '</th><td>';
        echo '<input type="number" min="1" max="50" name="wsh_ar_home_free__raw[more][limit]" value="' . esc_attr((string)$more_limit) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Page param', 'wsh-api-rocket') . '</th><td>';
        echo '<input type="text" class="regular-text code" name="wsh_ar_home_free__raw[more][paged_param]" value="' . esc_attr($more_param) . '">';
        echo '<p class="description">' . esc_html__('Endpoint query param used for pagination, e.g. ?more_page=2', 'wsh-api-rocket') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Exclude duplicates', 'wsh-api-rocket') . '</th><td>';
        echo '<label><input type="checkbox" name="wsh_ar_home_free__raw[more][exclude_duplicates]" value="1"' . checked($more_excl, true, false) . '> '
            . esc_html__('Exclude posts already used in previous sections', 'wsh-api-rocket') . '</label>';
        echo '</td></tr>';

        echo '</table>';

        echo '<p class="description">' . esc_html__(
            'Preview uses the current saved layout. If you want live preview from unsaved form values, we can add serialization next.',
            'wsh-api-rocket'
        ) . '</p>';
    }

    private function find_section(array $sections, string $id, string $type): array {
        foreach ($sections as $s) {
            if (!is_array($s)) continue;
            if ((string)($s['id'] ?? '') === $id) return $s;
        }
        return ['id' => $id, 'type' => $type];
    }

    public static function sanitize_raw_to_layout($raw): array {
        if (!is_array($raw)) $raw = [];

        // HEADER
        $h_title = sanitize_text_field((string)($raw['header']['title'] ?? 'Top'));
        $h_limit = max(1, min(50, (int)($raw['header']['limit'] ?? 5)));
        $h_ids_s = (string)($raw['header']['post_ids'] ?? '');
        $h_ids   = array_values(array_unique(array_filter(array_map('intval', preg_split('/\s*,\s*/', $h_ids_s) ?: []))));

        // MAIN
        $m_title = sanitize_text_field((string)($raw['main']['title'] ?? 'Main'));
        $m_limit = max(1, min(50, (int)($raw['main']['limit'] ?? 4)));
        $m_cat   = (int)($raw['main']['category_id'] ?? 0);

        // LATEST
        $l_title = sanitize_text_field((string)($raw['latest']['title'] ?? 'Latest'));
        $l_limit = max(1, min(50, (int)($raw['latest']['limit'] ?? 5)));
        $l_excl  = !empty($raw['latest']['exclude_duplicates']);

        // MORE
        $mo_title = sanitize_text_field((string)($raw['more']['title'] ?? 'More'));
        $mo_limit = max(1, min(50, (int)($raw['more']['limit'] ?? 6)));
        $mo_excl  = !empty($raw['more']['exclude_duplicates']);
        $mo_param = sanitize_key((string)($raw['more']['paged_param'] ?? 'more_page'));
        if ($mo_param === '') $mo_param = 'more_page';

        return [
            'sections' => [
                [
                    'id'       => 'header',
                    'type'     => 'manual_posts',
                    'title'    => $h_title,
                    'limit'    => $h_limit,
                    'post_ids' => $h_ids,
                ],
                [
                    'id'          => 'main',
                    'type'        => 'category_latest',
                    'title'       => $m_title,
                    'limit'       => $m_limit,
                    'category_id' => $m_cat,
                    'exclude_duplicates' => true,
                ],
                [
                    'id'       => 'latest',
                    'type'     => 'latest',
                    'title'    => $l_title,
                    'limit'    => $l_limit,
                    'exclude_duplicates' => $l_excl,
                ],
                [
                    'id'       => 'more',
                    'type'     => 'more_news',
                    'title'    => $mo_title,
                    'limit'    => $mo_limit,
                    'exclude_duplicates' => $mo_excl,
                    'paged_param' => $mo_param,
                ],
            ],
        ];
    }
    
    // sanitize_raw_to_layout() remains same as before
}
