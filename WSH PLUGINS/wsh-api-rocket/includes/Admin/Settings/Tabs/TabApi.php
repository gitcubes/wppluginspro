<?php
// PATCH: File: includes/Admin/Settings/Tabs/TabApi.php
// Add UI for public/api-key mode + a button to generate a random API key.

namespace WSH\APIROCKET\Admin\Settings\Tabs;

use WSH\APIROCKET\Rest\Auth\ApiKeyAuth;

if ( ! defined('ABSPATH') ) exit;

final class TabApi {

    public function render(): void {

        $keys = get_option(ApiKeyAuth::OPTION_API_KEYS, []);
        if (!is_array($keys)) $keys = [];

        $keys = array_values(array_unique(array_filter(array_map('strval', $keys))));
        $textarea = implode("\n", $keys);

        $mode = get_option('wsh_ar_public_mode', 'api_key');
        $mode = ($mode === 'public') ? 'public' : 'api_key';

        if ($mode === 'api_key' && empty($keys)) {
            echo '<div class="notice notice-warning"><p><strong>' .
                esc_html__('No API keys set.', 'wsh-api-rocket') .
                '</strong> ' .
                esc_html__('API endpoints will return 403 until you add at least one key.', 'wsh-api-rocket') .
                '</p></div>';
        }


        echo '<h2>' . esc_html__('API', 'wsh-api-rocket') . '</h2>';

        echo '<h3>' . esc_html__('Access mode', 'wsh-api-rocket') . '</h3>';
        echo '<p>' . esc_html__(
            'Choose whether FREE endpoints are public or require an API key. Recommended: API key.',
            'wsh-api-rocket'
        ) . '</p>';

        echo '<fieldset>';
        echo '<label style="display:block;margin:6px 0;">';
        echo '<input type="radio" name="wsh_ar_public_mode" value="api_key" ' . checked($mode, 'api_key', false) . '> ';
        echo esc_html__('Require API key (recommended)', 'wsh-api-rocket');
        echo '</label>';

        echo '<label style="display:block;margin:6px 0;">';
        echo '<input type="radio" name="wsh_ar_public_mode" value="public" ' . checked($mode, 'public', false) . '> ';
        echo esc_html__('Public (no key required)', 'wsh-api-rocket');
        echo '</label>';
        echo '</fieldset>';

        echo '<hr>';

        echo '<h3>' . esc_html__('API Keys', 'wsh-api-rocket') . '</h3>';
        echo '<p>' . esc_html__(
            'Add one API key per line. Mobile apps should send it in the request header: X-WSH-API-KEY',
            'wsh-api-rocket'
        ) . '</p>';

        echo '<p><button type="button" class="button" id="wsh-ar-generate-key">' . esc_html__('Generate random key', 'wsh-api-rocket') . '</button></p>';

        echo '<table class="form-table" role="presentation">';
        echo '  <tr>';
        echo '    <th scope="row"><label for="wsh_ar_api_keys">' . esc_html__('Keys', 'wsh-api-rocket') . '</label></th>';
        echo '    <td>';
        echo '      <textarea id="wsh_ar_api_keys" class="large-text code" rows="10" name="' . esc_attr(ApiKeyAuth::OPTION_API_KEYS) . '__raw">' . esc_textarea($textarea) . '</textarea>';
        echo '      <p class="description">' . esc_html__('One per line. Use long random strings (32+ chars).', 'wsh-api-rocket') . '</p>';
        echo '    </td>';
        echo '  </tr>';
        echo '</table>';

        echo '<hr>';
        echo '<h3>' . esc_html__('Rate limit', 'wsh-api-rocket') . '</h3>';

        $free_rpm = (int) get_option(\WSH\APIROCKET\Support\RateLimiter::OPT_RPM_FREE, 60);
        $pro_rpm  = (int) get_option(\WSH\APIROCKET\Support\RateLimiter::OPT_RPM_PRO, 120);

        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="wsh_ar_rl_free">' . esc_html__('FREE RPM', 'wsh-api-rocket') . '</label></th>';
        echo '<td><input id="wsh_ar_rl_free" type="number" min="1" max="5000" name="' . esc_attr(\WSH\APIROCKET\Support\RateLimiter::OPT_RPM_FREE) . '" value="' . esc_attr((string)$free_rpm) . '">';
        echo '<p class="description">' . esc_html__('Requests per minute per API key + IP for FREE endpoints.', 'wsh-api-rocket') . '</p></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="wsh_ar_rl_pro">' . esc_html__('PRO RPM', 'wsh-api-rocket') . '</label></th>';
        echo '<td><input id="wsh_ar_rl_pro" type="number" min="1" max="5000" name="' . esc_attr(\WSH\APIROCKET\Support\RateLimiter::OPT_RPM_PRO) . '" value="' . esc_attr((string)$pro_rpm) . '">';
        echo '<p class="description">' . esc_html__('Requests per minute per API key + IP for PRO endpoints.', 'wsh-api-rocket') . '</p></td>';
        echo '</tr>';
        echo '</table>';

        echo '<hr>';
        echo '<h3>' . esc_html__('Proxy / Client IP', 'wsh-api-rocket') . '</h3>';

        $trust = (int) get_option(\WSH\APIROCKET\Support\Ip::OPT_TRUST_PROXY, 0);
        $mode  = get_option(\WSH\APIROCKET\Support\Ip::OPT_PROXY_MODE, 'auto');
        $mode  = is_string($mode) ? $mode : 'auto';
        if (!in_array($mode, ['auto','cloudflare','x_forwarded_for'], true)) $mode = 'auto';

        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row">' . esc_html__('Trust proxy headers', 'wsh-api-rocket') . '</th><td>';
        echo '<label><input type="checkbox" name="' . esc_attr(\WSH\APIROCKET\Support\Ip::OPT_TRUST_PROXY) . '" value="1" ' . checked($trust, 1, false) . '> ';
        echo esc_html__('Enable if your site is behind Cloudflare / reverse proxy (recommended in that case).', 'wsh-api-rocket');
        echo '</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Mode', 'wsh-api-rocket') . '</th><td>';
        echo '<select name="' . esc_attr(\WSH\APIROCKET\Support\Ip::OPT_PROXY_MODE) . '">';
        echo '<option value="auto" ' . selected($mode, 'auto', false) . '>' . esc_html__('Auto (CF + XFF + X-Real-IP)', 'wsh-api-rocket') . '</option>';
        echo '<option value="cloudflare" ' . selected($mode, 'cloudflare', false) . '>' . esc_html__('Cloudflare only (CF-Connecting-IP)', 'wsh-api-rocket') . '</option>';
        echo '<option value="x_forwarded_for" ' . selected($mode, 'x_forwarded_for', false) . '>' . esc_html__('X-Forwarded-For only', 'wsh-api-rocket') . '</option>';
        echo '</select>';
        echo '<p class="description">' . esc_html__('Leave Auto unless you know your proxy setup.', 'wsh-api-rocket') . '</p>';
        echo '</td></tr>';

        echo '</table>';

        echo '<hr>';
        echo '<h3>' . esc_html__('Uninstall', 'wsh-api-rocket') . '</h3>';
        $del = (int) get_option('wsh_ar_delete_on_uninstall', 0);
        echo '<label><input type="checkbox" name="wsh_ar_delete_on_uninstall" value="1" ' . checked($del, 1, false) . '> ';
        echo esc_html__('Delete plugin settings on uninstall', 'wsh-api-rocket');
        echo '</label>';


        echo '<script>
        (function(){
            var btn = document.getElementById("wsh-ar-generate-key");
            var ta  = document.getElementById("wsh_ar_api_keys");
            if(!btn || !ta) return;
            btn.addEventListener("click", function(){
                // simple random key (client-side); server-side generation can be added later
                var chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
                var key = "";
                for (var i=0; i<40; i++) key += chars.charAt(Math.floor(Math.random()*chars.length));
                if (ta.value.trim() !== "") ta.value = ta.value.trim() + "\\n" + key;
                else ta.value = key;
            });
        })();
        </script>';
    }

    public static function sanitize_raw_to_array($raw): array {
        if (!is_string($raw)) return [];
        $lines = preg_split('/\R/u', $raw) ?: [];
        $out = [];

        foreach ($lines as $line) {
            $k = trim((string)$line);
            if ($k === '') continue;

            $k = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $k);
            if ($k === '') continue;

            $out[] = $k;
        }

        return array_values(array_unique($out));
    }
}
