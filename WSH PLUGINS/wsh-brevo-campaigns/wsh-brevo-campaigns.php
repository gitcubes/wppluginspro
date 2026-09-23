<?php
/**
 * Plugin Name: Brevo Campaigns (MVP)
 * Description: Send Brevo (Sendinblue) email campaigns directly from WordPress: manage campaigns, lists, settings, and build HTML templates with preview.
 * Author: Cubes / Miodrag + ChatGPT
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Text Domain: brevo-campaigns
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) { exit; }

if (!defined('WSH_BREVO_DBVER')) {
    define('WSH_BREVO_DBVER', '1'); // bumpaj kada menjaš šemu
}

if (!defined('BREVO_CAMPAIGNS_FILE')) {
    define('BREVO_CAMPAIGNS_FILE', __FILE__); // da bismo mogli plugins_url() iz drugih fajlova
}

if (!defined('BREVO_SMTP_DEBUG')) {
    define('BREVO_SMTP_DEBUG', true);
}


class BrevoCampaignsPlugin {
    const OPTION_KEY = 'brevo_campaigns_settings';
    const UPLOAD_SUBDIR = 'brevo-campaigns';
    const TEXT_DOMAIN = 'brevo-campaigns';

    /** @var BrevoApiClient */
    private $api;

    // na vrhu klase
    private $last_mail_error = '';

    public function __construct() {
        // i18n
        add_action('init', function () {
            load_plugin_textdomain(
                self::TEXT_DOMAIN,
                false,
                dirname(plugin_basename(BREVO_CAMPAIGNS_FILE)) . '/languages'
            );
        });

        require_once __DIR__ . '/includes/ApiClient.php';
        require_once __DIR__ . '/includes/Admin.php';
        require_once __DIR__ . '/includes/TemplateBuilder.php';

        $opts = get_option(self::OPTION_KEY, [
            'api_key' => '',
            'sender_email' => '',
            'sender_name' => '',
            'sender_slogan' => '',

            // NEW:
            'social_facebook' => '',
            'social_youtube' => '',
            'social_x' => '',
            'social_instagram' => '',
            'social_tiktok' => '',
            'social_linkedin' => '',

            // Send emails via Brevo:
            'wp_mail_enable'     => 0,
            'wp_mail_from_email' => '',
            'wp_mail_from_name'  => '',
            'wp_mail_reply_to'   => '',
            'wp_mail_host'       => 'smtp-relay.brevo.com',
            'wp_mail_port'       => 587,           // 587=tls, 465=ssl
            'wp_mail_encryption' => 'tls',
            'wp_mail_username'   => 'apikey',
        ]);
        $this->api = new BrevoApiClient($opts['api_key'] ?? '');

        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        add_action('wp_ajax_brevo_create_campaign', [$this, 'ajax_create_campaign']);
        add_action('wp_ajax_brevo_generate_preview', [$this, 'ajax_generate_preview']);
        add_action('wp_ajax_brevo_send_test', [$this, 'ajax_send_test']);
        add_action('wp_ajax_brevo_schedule_campaign', [$this, 'ajax_schedule_campaign']);
        add_action('wp_ajax_brevo_confirm_campaign', [$this, 'ajax_confirm_campaign']);
        add_action('wp_ajax_brevo_search_posts', [$this, 'ajax_search_posts']);
        add_action('wp_ajax_brevo_posts_by_ids', [$this, 'ajax_posts_by_ids']);
        add_action('wp_ajax_brevo_delete_campaign', [$this, 'ajax_delete_campaign']);
        add_action('wp_ajax_brevo_get_campaign_stats', [$this, 'ajax_get_campaign_stats']);
        add_action('wp_ajax_brevo_campaign_preview', [$this, 'ajax_campaign_preview']);
        add_action('wp_ajax_brevo_mail_test', [$this, 'ajax_brevo_mail_test']);

        //Send emails via Brevo
        add_action('phpmailer_init', [$this, 'maybe_route_wp_mail_via_brevo'], 999);
        add_filter('wp_mail_from',        [$this, 'filter_wp_from_email']);
        add_filter('wp_mail_from_name',   [$this, 'filter_wp_from_name']);
        add_action('wp_mail_failed',      [$this, 'on_wp_mail_failed'], 9999);
        add_filter('pre_wp_mail',         [$this, 'pre_wp_mail_via_brevo_api'], 10, 2);
        add_filter('wp_mail_content_type', function($t){ return 'text/html'; });
        add_action('rest_api_init', [$this, 'register_brevo_webhook_route']);

        //Brevo form shortcode
        add_action('wp_ajax_brevo_front_subscribe',      [$this, 'ajax_front_subscribe']);
        add_action('wp_ajax_nopriv_brevo_front_subscribe', [$this, 'ajax_front_subscribe']);
        add_shortcode('brevo_form', [$this, 'shortcode_brevo_form']);

        add_action('wp_ajax_brevo_form_subscribe',        [$this, 'ajax_form_subscribe']);
        add_action('wp_ajax_nopriv_brevo_form_subscribe', [$this, 'ajax_form_subscribe']);


        // Ensure uploads subdirectory exists
        add_action('admin_init', function(){
            // uploads subdir
            $upload = wp_upload_dir();
            $dir = trailingslashit($upload['basedir']) . self::UPLOAD_SUBDIR;
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }

            // DB schema check
            global $wpdb;
            $table = $wpdb->prefix . 'wsh_brevo_campaigns';

            $exists = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $table) );
            $ver_ok = (get_option('wsh_brevo_dbver') === WSH_BREVO_DBVER);

            if ($exists !== $table || !$ver_ok) {
                // ako si ostavio metodu kao non-static:
                $this->wsh_brevo_install_schema();
                // ako si prešao na static varijantu:
                // self::wsh_brevo_install_schema();
            }
        });
        
        // --- DB schema install/upgrade ---
        register_activation_hook(__FILE__, [$this, 'wsh_brevo_install_schema']);
    }

    public static function wsh_brevo_install_schema() {
        global $wpdb;
        $table   = $wpdb->prefix . 'wsh_brevo_campaigns';
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            brevo_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL DEFAULT '',
            subject VARCHAR(255) NOT NULL DEFAULT '',
            template VARCHAR(255) NOT NULL DEFAULT '',
            lists TEXT NULL,
            preview_file TEXT NULL,
            payload LONGTEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_brevo_id (brevo_id)
        ) $charset;";
        dbDelta($sql);
        update_option('wsh_brevo_dbver', WSH_BREVO_DBVER);
    }

    public function register_admin_menu() {
        add_menu_page(
            __('Brevo', self::TEXT_DOMAIN), __('Brevo', self::TEXT_DOMAIN), 'manage_options', 'brevo-panel', [$this, 'render_campaigns_page'], 'dashicons-email-alt2', 59
        );
        add_submenu_page('brevo-panel', __('Campaigns', self::TEXT_DOMAIN), __('Campaigns', self::TEXT_DOMAIN), 'manage_options', 'brevo-panel', [$this, 'render_campaigns_page']);
        add_submenu_page('brevo-panel', __('Lists', self::TEXT_DOMAIN), __('Lists', self::TEXT_DOMAIN), 'manage_options', 'brevo-lists', [$this, 'render_lists_page']);
        add_submenu_page('brevo-panel', __('Settings', self::TEXT_DOMAIN), __('Settings', self::TEXT_DOMAIN), 'manage_options', 'brevo-settings', [$this, 'render_settings_page']);
        add_submenu_page('brevo-panel', __('Create campaign', self::TEXT_DOMAIN), __('Create campaign', self::TEXT_DOMAIN), 'manage_options', 'brevo-create', [$this, 'render_create_page']);
        add_submenu_page('brevo-panel', __('Forms', self::TEXT_DOMAIN), __('Forms', self::TEXT_DOMAIN), 'manage_options', 'brevo-forms', [$this, 'render_forms_page']);
    }

    public function register_settings() {
        /*register_setting('brevo_settings_group', self::OPTION_KEY, [
            'type' => 'object', 'sanitize_callback' => [$this, 'sanitize_settings']
        ]);*/
        register_setting('brevo_settings_group', self::OPTION_KEY, [
            'type'              => 'array',        // ← mora array, ne object
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default'           => [],             // (opciono) bezbedan default
        ]);

        add_settings_section('brevo_settings_main', __('Brevo API Settings', self::TEXT_DOMAIN), function(){
            echo '<p>' . esc_html__('Enter the API key and default sender.', self::TEXT_DOMAIN) . '</p>';
        }, 'brevo-settings');

        add_settings_field('brevo_api_key', 'Brevo API Key', function(){
            $opts = get_option(self::OPTION_KEY);
            printf('<input type="password" name="%s[api_key]" value="%s" class="regular-text" />', esc_attr(self::OPTION_KEY), esc_attr($opts['api_key'] ?? ''));
            echo '<p class="description">' . esc_html__('Settings → SMTP & API u Brevo nalogu.', self::TEXT_DOMAIN) . '</p>';
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_sender_email', __('Sender Email', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            printf('<input type="email" name="%s[sender_email]" value="%s" class="regular-text" />', esc_attr(self::OPTION_KEY), esc_attr($opts['sender_email'] ?? ''));
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_sender_name', __('Sender Name', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            printf('<input type="text" name="%s[sender_name]" value="%s" class="regular-text" />', esc_attr(self::OPTION_KEY), esc_attr($opts['sender_name'] ?? ''));
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_preheader_text', __('Preheader text', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            printf('<input type="text" name="%s[preheader_text]" value="%s" class="regular-text" placeholder="' . esc_html__('Short summary shown in inbox', self::TEXT_DOMAIN) . '" />', esc_attr(self::OPTION_KEY), esc_attr($opts['preheader_text'] ?? ''));
        }, 'brevo-settings', 'brevo_settings_main');

        // Sender slogan
        add_settings_field('brevo_sender_slogan', __('Sender Slogan', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            printf(
                '<input type="text" name="%s[sender_slogan]" value="%s" class="regular-text" placeholder="' . esc_html__('Your weekly energy news', self::TEXT_DOMAIN) . '" />',
                esc_attr(self::OPTION_KEY),
                esc_attr($opts['sender_slogan'] ?? '')
            );
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_logo_url', __('Header logo URL', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            printf(
                '<input type="url" name="%s[logo_url]" value="%s" class="regular-text" placeholder="https://.../logo.png" />',
                esc_attr(self::OPTION_KEY),
                esc_attr($opts['logo_url'] ?? '')
            );
            echo '<p class="description">' . esc_html__('URL to the logo that will be displayed in the header of the newsletter.', self::TEXT_DOMAIN) . '</p>';
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_cta_color', __('CTA button color', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            $val = $opts['cta_color'] ?? '#34af0c';
            printf('<input type="color" name="%s[cta_color]" value="%s" /> <code>%s</code>', esc_attr(self::OPTION_KEY), esc_attr($val), esc_html($val));
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_cta_radius', __('CTA border radius (px)', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            $val = isset($opts['cta_radius']) ? intval($opts['cta_radius']) : 4;
            printf('<input type="number" name="%s[cta_radius]" value="%d" min="0" max="32" class="small-text" />', esc_attr(self::OPTION_KEY), $val);
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_test_list_id', __('Default Test List ID', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            $val = isset($opts['test_list_id']) ? intval($opts['test_list_id']) : 0;
            printf('<input type="number" name="%s[test_list_id]" value="%d" class="small-text" min="0" />', esc_attr(self::OPTION_KEY), $val);
            echo '<p class="description">' . esc_html__('Optional: List ID in Brevo to be used as fallback for test addresses.', self::TEXT_DOMAIN) . '</p>';
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_cta_text', __('CTA button text', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            $val = $opts['cta_text'] ?? __('Read more →', self::TEXT_DOMAIN);
            printf('<input type="text" name="%s[cta_text]" value="%s" class="regular-text" />',
                esc_attr(self::OPTION_KEY), esc_attr($val)
            );
        }, 'brevo-settings', 'brevo_settings_main');


        // --- Social links (pojedinačno) ---
        add_settings_field('brevo_social_facebook', __('Facebook URL', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            printf('<input type="url" name="%s[social_facebook]" value="%s" class="regular-text" placeholder="https://facebook.com/yourpage" />',
                esc_attr(self::OPTION_KEY), esc_attr($opts['social_facebook'] ?? '')
            );
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_social_youtube', __('YouTube URL', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            printf('<input type="url" name="%s[social_youtube]" value="%s" class="regular-text" placeholder="https://youtube.com/@yourchannel" />',
                esc_attr(self::OPTION_KEY), esc_attr($opts['social_youtube'] ?? '')
            );
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_social_x', __('X (Twitter) URL', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            printf('<input type="url" name="%s[social_x]" value="%s" class="regular-text" placeholder="https://x.com/yourhandle" />',
                esc_attr(self::OPTION_KEY), esc_attr($opts['social_x'] ?? '')
            );
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_social_instagram', __('Instagram URL', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            printf('<input type="url" name="%s[social_instagram]" value="%s" class="regular-text" placeholder="https://instagram.com/yourprofile" />',
                esc_attr(self::OPTION_KEY), esc_attr($opts['social_instagram'] ?? '')
            );
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_social_tiktok', __('TikTok URL', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            printf('<input type="url" name="%s[social_tiktok]" value="%s" class="regular-text" placeholder="https://tiktok.com/@yourprofile" />',
                esc_attr(self::OPTION_KEY), esc_attr($opts['social_tiktok'] ?? '')
            );
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_social_linkedin', __('LinkedIn URL', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            printf('<input type="url" name="%s[social_linkedin]" value="%s" class="regular-text" placeholder="https://linkedin.com/company/yourcompany" />',
                esc_attr(self::OPTION_KEY), esc_attr($opts['social_linkedin'] ?? '')
            );
        }, 'brevo-settings', 'brevo_settings_main');


        // UTM Builder (checkbox + polja)
        add_settings_field('brevo_utm_enable', __('Enable UTM builder', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            $checked = !empty($opts['utm_enable']) ? 'checked' : '';
            printf('<label><input type="checkbox" name="%s[utm_enable]" value="1" %s /> Turn on UTM appending to all links</label>', esc_attr(self::OPTION_KEY), $checked);
            echo '<p class="description">' . esc_html__('If enabled, UTM parameters will be added to all links in the email.', self::TEXT_DOMAIN) . '</p>';
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_utm_source', __('UTM Source', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            $val = $opts['utm_source'] ?? 'newsletter';
            printf('<input type="text" name="%s[utm_source]" value="%s" class="regular-text" />', esc_attr(self::OPTION_KEY), esc_attr($val));
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_utm_medium', __('UTM Medium', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            $val = $opts['utm_medium'] ?? 'email';
            printf('<input type="text" name="%s[utm_medium]" value="%s" class="regular-text" />', esc_attr(self::OPTION_KEY), esc_attr($val));
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_utm_campaign', __('UTM Campaign (optional)', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            $val = $opts['utm_campaign'] ?? '';
            printf('<input type="text" name="%s[utm_campaign]" value="%s" class="regular-text" placeholder="' . esc_html__('(blank => use Subject)', self::TEXT_DOMAIN) . '" />', esc_attr(self::OPTION_KEY), esc_attr($val));
            echo '<p class="description">' . esc_html__('If left blank, Subject is used as the campaign value.', self::TEXT_DOMAIN) . '</p>';
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_utm_term', __('UTM Term (optional)', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            $val = $opts['utm_term'] ?? '';
            printf('<input type="text" name="%s[utm_term]" value="%s" class="regular-text" />', esc_attr(self::OPTION_KEY), esc_attr($val));
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_utm_content', __('UTM Content (optional)', self::TEXT_DOMAIN), function(){
            $opts = get_option(self::OPTION_KEY);
            $val = $opts['utm_content'] ?? '';
            printf('<input type="text" name="%s[utm_content]" value="%s" class="regular-text" placeholder="(blank => use item/section title)" />', esc_attr(self::OPTION_KEY), esc_attr($val));
        }, 'brevo-settings', 'brevo_settings_main');

        // Send emails via Brevo
        add_settings_field('brevo_wp_mail_enable', __('Use Brevo for WP Mail', self::TEXT_DOMAIN), function () {
            $o = get_option(self::OPTION_KEY);
            printf(
                '<label><input type="checkbox" name="%s[wp_mail_enable]" value="1" %s> ' . esc_html__('Route all wp_mail() via Brevo SMTP', self::TEXT_DOMAIN) . '</label>',
                esc_attr(self::OPTION_KEY),
                !empty($o['wp_mail_enable']) ? 'checked' : ''
            );
            echo '<p class="description">' . esc_html__('Requires a Brevo API key (used as SMTP password) and a verified sending domain in Brevo.', self::TEXT_DOMAIN) . '</p>';
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_wp_from_email', __('From email', self::TEXT_DOMAIN), function () {
            $o = get_option(self::OPTION_KEY);
            printf('<input type="email" name="%s[wp_mail_from_email]" value="%s" class="regular-text" placeholder="no-reply@yourdomain.com" />',
                esc_attr(self::OPTION_KEY), esc_attr($o['wp_mail_from_email'] ?? ''));
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_wp_from_name', __('From name', self::TEXT_DOMAIN), function () {
            $o = get_option(self::OPTION_KEY);
            printf('<input type="text" name="%s[wp_mail_from_name]" value="%s" class="regular-text" placeholder="' . esc_html__('Your Site', self::TEXT_DOMAIN) . '" />',
                esc_attr(self::OPTION_KEY), esc_attr($o['wp_mail_from_name'] ?? ''));
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_wp_reply_to', __('Reply-To (optional)', self::TEXT_DOMAIN), function () {
            $o = get_option(self::OPTION_KEY);
            printf('<input type="email" name="%s[wp_mail_reply_to]" value="%s" class="regular-text" placeholder="support@yourdomain.com" />',
                esc_attr(self::OPTION_KEY), esc_attr($o['wp_mail_reply_to'] ?? ''));
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_wp_username', __('SMTP Username', self::TEXT_DOMAIN), function () {
            $o = get_option(self::OPTION_KEY);
            $val = $o['wp_mail_username'] ?? 'apikey';
            printf(
                '<input type="text" name="%s[wp_mail_username]" value="%s" class="regular-text" placeholder="apikey / your-account@email" />',
                esc_attr(self::OPTION_KEY),
                esc_attr($val)
            );
            echo '<p class="description">' . esc_html__('Brevo usually uses <code>apikey</code> as the username. If your account requires an e-mail as a username, enter it here.', self::TEXT_DOMAIN) . '</p>';
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_wp_transport', __('Transport', self::TEXT_DOMAIN), function () {
            $o = get_option(self::OPTION_KEY);
            $val = $o['wp_mail_transport'] ?? 'smtp';
            $name = esc_attr(self::OPTION_KEY) . '[wp_mail_transport]';
            ?>
            <label><input type="radio" name="<?php echo $name; ?>" value="smtp" <?php checked($val, 'smtp'); ?>> <?php echo esc_html__('SMTP (default)', self::TEXT_DOMAIN); ?></label><br>
            <label><input type="radio" name="<?php echo $name; ?>" value="api"  <?php checked($val, 'api');  ?>> <?php echo esc_html__('Brevo API (recommended)', self::TEXT_DOMAIN); ?></label>
            <p class="description"><?php echo esc_html__('If SMTP causes problems with authentication - use the API.', self::TEXT_DOMAIN); ?></p>
            <?php
        }, 'brevo-settings', 'brevo_settings_main');

        add_settings_field('brevo_wp_smtp', __('SMTP options', self::TEXT_DOMAIN), function () {
            $o = get_option(self::OPTION_KEY);
            $host = $o['wp_mail_host'] ?? 'smtp-relay.brevo.com';
            $port = (int)($o['wp_mail_port'] ?? 587);
            $enc  = $o['wp_mail_encryption'] ?? 'tls';
            printf('<input type="text" name="%s[wp_mail_host]" value="%s" class="regular-text" />', esc_attr(self::OPTION_KEY), esc_attr($host));
            echo '<p class="description" style="margin:4px 0 10px;">Default: smtp-relay.brevo.com</p>';
            echo '<label>Port: ';
            printf('<select name="%s[wp_mail_port]">', esc_attr(self::OPTION_KEY));
            printf('<option value="587" %s>587 (TLS)</option>', selected($port, 587, false));
            printf('<option value="465" %s>465 (SSL)</option>', selected($port, 465, false));
            echo '</select></label> ';
            echo '<label style="margin-left:10px;">' . esc_html__('Encryption', self::TEXT_DOMAIN) . ': ';
            printf('<select name="%s[wp_mail_encryption]">', esc_attr(self::OPTION_KEY));
            printf('<option value="tls" %s>TLS</option>', selected($enc, 'tls', false));
            printf('<option value="ssl" %s>SSL</option>', selected($enc, 'ssl', false));
            echo '</select></label>';

            // Test dugme
            echo '<p style="margin-top:10px;"><button type="button" class="button" id="brevo-mail-test">' . esc_html__('Send test email', self::TEXT_DOMAIN) . '</button></p>';
        }, 'brevo-settings', 'brevo_settings_main');

        // Webhook secret (skriveno polje ili ručni unos)
        add_settings_field('brevo_webhook_secret', __('Webhook secret', self::TEXT_DOMAIN), function () {
            $o = get_option(self::OPTION_KEY, []);
            if (empty($o['webhook_secret'])) {
                $o['webhook_secret'] = wp_generate_password(32, false, false);
                update_option(self::OPTION_KEY, $o);
            }
            printf('<input type="text" name="%s[webhook_secret]" value="%s" class="regular-text" />',
                esc_attr(self::OPTION_KEY), esc_attr($o['webhook_secret']));
            echo '<p class="description">' . esc_html__('Save, then copy the URL below.', self::TEXT_DOMAIN) . '</p>';
        }, 'brevo-settings', 'brevo_settings_main');

        // Read-only prikaz Webhook URL-a
        add_settings_field('brevo_webhook_url', __('Webhook URL', self::TEXT_DOMAIN), function () {
            $o = get_option(self::OPTION_KEY, []);
            $url = rest_url('brevo/v1/events');
            if (!empty($o['webhook_secret'])) {
                $url = add_query_arg('token', $o['webhook_secret'], $url);
            }
            printf('<code style="user-select:all">%s</code>', esc_html($url));
            echo '<p class="description">' . esc_html__('Enter this URL in Bravo &rarr; Webhooks → “URL to call“', self::TEXT_DOMAIN) . '”.</p>';
        }, 'brevo-settings', 'brevo_settings_main');

        //Brevo form confirmation
        // Forms (Signup behavior)
        add_settings_section('brevo_forms', __('Forms (Sign-up)', self::TEXT_DOMAIN), function(){
            echo '<p>' . esc_html__('Behavior options when logging in via the shortcode form.', self::TEXT_DOMAIN) . '</p>';
        }, 'brevo-settings');


        add_settings_field('brevo_forms_doi_enable', __('Enable Double Opt-In', self::TEXT_DOMAIN), function(){
            $o = get_option(self::OPTION_KEY);
            printf('<label><input type="checkbox" name="%s[forms_doi_enable]" value="1" %s /> ' . esc_html__('Send Brevo DOI confirmation email on signup', self::TEXT_DOMAIN) . '</label>',
                esc_attr(self::OPTION_KEY),
                !empty($o['forms_doi_enable']) ? 'checked' : ''
            );
            echo '<p class="description">' . esc_html__('The user receives a confirmation email from Brevo. The contact is entered in the list only after clicking on confirmation.', self::TEXT_DOMAIN) . '</p>';
        }, 'brevo-settings', 'brevo_forms');


        add_settings_field('brevo_forms_doi_template', __('DOI Template ID', self::TEXT_DOMAIN), function(){
            $o = get_option(self::OPTION_KEY);
            printf('<input type="number" name="%s[forms_doi_template]" value="%s" class="small-text" min="1" />',
                esc_attr(self::OPTION_KEY), esc_attr($o['forms_doi_template'] ?? '')
            );
        }, 'brevo-settings', 'brevo_forms');

        add_settings_field('brevo_forms_doi_redirect', __('DOI Redirect URL', self::TEXT_DOMAIN), function(){
            $o = get_option(self::OPTION_KEY);
            printf('<input type="url" name="%s[forms_doi_redirect]" value="%s" class="regular-text" placeholder="https://yoursite/thank-you/" />',
                esc_attr(self::OPTION_KEY), esc_attr($o['forms_doi_redirect'] ?? '')
            );
        }, 'brevo-settings', 'brevo_forms');

        add_settings_field('brevo_forms_welcome_enable', __('Send Welcome Email (if DOI is off)', self::TEXT_DOMAIN), function(){
            $o = get_option(self::OPTION_KEY);
            printf('<label><input type="checkbox" name="%s[forms_welcome_enable]" value="1" %s /> ' . esc_html__('Send immediate welcome email', self::TEXT_DOMAIN) . '</label>',
                esc_attr(self::OPTION_KEY),
                !empty($o['forms_welcome_enable']) ? 'checked' : ''
            );
            echo '<p class="description">' . esc_html__('If DOI is included, welcome is not sent immediately (waiting for confirmation).', self::TEXT_DOMAIN) . '</p>';
        }, 'brevo-settings', 'brevo_forms');

        add_settings_field('brevo_forms_welcome_subject', __('Welcome Subject', self::TEXT_DOMAIN), function(){
            $o = get_option(self::OPTION_KEY);
            printf('<input type="text" name="%s[forms_welcome_subject]" value="%s" class="regular-text" placeholder="Welcome to our newsletter!" />',
                esc_attr(self::OPTION_KEY), esc_attr($o['forms_welcome_subject'] ?? __('Welcome to our newsletter!', self::TEXT_DOMAIN))
            );
        }, 'brevo-settings', 'brevo_forms');

        add_settings_field('brevo_forms_welcome_html', __('Welcome HTML', self::TEXT_DOMAIN), function(){
            $o = get_option(self::OPTION_KEY);
            $val = $o['forms_welcome_html'] ?? '<p>' . esc_html__('Thanks for subscribing!', self::TEXT_DOMAIN) . '</p>';
            printf('<textarea name="%s[forms_welcome_html]" rows="5" class="large-text code">%s</textarea>',
                esc_attr(self::OPTION_KEY), esc_textarea($val)
            );
        }, 'brevo-settings', 'brevo_forms');

    
    }

    public function sanitize_settings($input) {
        // --- CTA stil ---
        $color = sanitize_hex_color($input['cta_color'] ?? '');
        if (!$color) { $color = '#34af0c'; }

        $radius = isset($input['cta_radius']) ? intval($input['cta_radius']) : 4;
        if ($radius < 0)  { $radius = 0; }
        if ($radius > 32) { $radius = 32; }

        // --- UTM ---
        $utm_enable   = !empty($input['utm_enable']) ? 1 : 0;
        $utm_source   = sanitize_text_field($input['utm_source']   ?? 'newsletter');
        $utm_medium   = sanitize_text_field($input['utm_medium']   ?? 'email');
        $utm_campaign = sanitize_text_field($input['utm_campaign'] ?? '');
        $utm_term     = sanitize_text_field($input['utm_term']     ?? '');
        $utm_content  = sanitize_text_field($input['utm_content']  ?? '');

        // --- WP mail preko Brevo SMTP ---
        $wp_mail_enable     = !empty($input['wp_mail_enable']) ? 1 : 0;
        $wp_mail_from_email = sanitize_email($input['wp_mail_from_email'] ?? '');
        $wp_mail_from_name  = sanitize_text_field($input['wp_mail_from_name'] ?? '');
        $wp_mail_reply_to   = sanitize_email($input['wp_mail_reply_to'] ?? '');
        $wp_mail_username = sanitize_text_field($input['wp_mail_username'] ?? 'apikey');

        // host dozvoli samo jednostavan tekst (bez URL protokola)
        $host = trim(sanitize_text_field($input['wp_mail_host'] ?? 'smtp-relay.brevo.com'));
        if ($host === '') $host = 'smtp-relay.brevo.com';

        // port ograniči na 587/465
        $port = isset($input['wp_mail_port']) ? intval($input['wp_mail_port']) : 587;
        if (!in_array($port, [587, 465], true)) $port = 587;

        // encryption ograniči na tls/ssl
        $enc = sanitize_key($input['wp_mail_encryption'] ?? 'tls');
        if (!in_array($enc, ['tls','ssl'], true)) $enc = 'tls';

        $transport = ($input['wp_mail_transport'] ?? 'smtp');
        if (!in_array($transport, ['smtp','api'], true)) { $transport = 'smtp'; }

        return [
            // osnovno
            'api_key'        => sanitize_text_field($input['api_key'] ?? ''),
            'sender_email'   => sanitize_email($input['sender_email'] ?? ''),
            'sender_name'    => sanitize_text_field($input['sender_name'] ?? ''),
            'sender_slogan'    => sanitize_text_field($input['sender_slogan'] ?? ''),
            'preheader_text' => sanitize_text_field($input['preheader_text'] ?? ''),
            'test_list_id'   => isset($input['test_list_id']) ? max(0, intval($input['test_list_id'])) : 0,

            // branding / CTA
            'cta_text'   => sanitize_text_field($input['cta_text'] ?? 'Read more →'),
            'logo_url'   => esc_url_raw($input['logo_url'] ?? ''),
            'cta_color'  => $color,
            'cta_radius' => $radius,

            // Social links (čuvamo samo validne URL-ove ili prazno)
            'social_facebook' => esc_url_raw($input['social_facebook'] ?? ''),
            'social_youtube'  => esc_url_raw($input['social_youtube'] ?? ''),
            'social_x'        => esc_url_raw($input['social_x'] ?? ''),
            'social_instagram'=> esc_url_raw($input['social_instagram'] ?? ''),
            'social_tiktok'   => esc_url_raw($input['social_tiktok'] ?? ''),
            'social_linkedin' => esc_url_raw($input['social_linkedin'] ?? ''),


            // UTM
            'utm_enable'   => $utm_enable,
            'utm_source'   => $utm_source,
            'utm_medium'   => $utm_medium,
            'utm_campaign' => $utm_campaign,
            'utm_term'     => $utm_term,
            'utm_content'  => $utm_content,
            

            // WP mail (SMTP preko Brevo)
            'wp_mail_enable'     => $wp_mail_enable,
            'wp_mail_from_email' => $wp_mail_from_email,
            'wp_mail_from_name'  => $wp_mail_from_name,
            'wp_mail_reply_to'   => $wp_mail_reply_to,
            'wp_mail_host'       => $host,
            'wp_mail_port'       => $port,
            'wp_mail_encryption' => $enc,
            'wp_mail_username'   => $wp_mail_username,
            'wp_mail_transport'  => $transport,
            'webhook_secret' => sanitize_text_field($input['webhook_secret'] ?? ''),

            // Forms (signup)
            'forms_doi_enable'     => !empty($input['forms_doi_enable']) ? 1 : 0,
            'forms_doi_template'   => isset($input['forms_doi_template']) ? max(0, (int)$input['forms_doi_template']) : 0,
            'forms_doi_redirect'   => esc_url_raw($input['forms_doi_redirect'] ?? ''),
            'forms_welcome_enable' => !empty($input['forms_welcome_enable']) ? 1 : 0,
            'forms_welcome_subject'=> sanitize_text_field($input['forms_welcome_subject'] ?? 'Welcome to our newsletter!'),
            'forms_welcome_html'   => wp_kses_post($input['forms_welcome_html'] ?? '<p>Thanks for subscribing!</p>'),

        ];
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'brevo') === false) return;
        wp_enqueue_media();
        wp_enqueue_style('brevo-admin', plugin_dir_url(__FILE__) . 'assets/admin.css?v=' . time(), [], '0.1.0');
        wp_enqueue_script('brevo-admin', plugin_dir_url(__FILE__) . 'assets/admin.js?v=' . time(), ['jquery'], '0.1.0', true);
        wp_localize_script('brevo-admin', 'BREVO_CAMPAIGNS', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('brevo_nonce')
        ]);
    }

    public function enqueue_public_assets() {
        wp_register_style('brevo-forms', plugin_dir_url(__FILE__).'assets/brevo-forms.css?v=' . time(), [], '1.0');
        wp_register_script('brevo-forms', plugin_dir_url(__FILE__).'assets/brevo-forms.js?v=' .time(), ['jquery'], '1.0', true);
        wp_localize_script('brevo-forms', 'BREVO_FORMS', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('brevo_nonce'),
        ]);
    }

    public function render_campaigns_page() {
        if (!current_user_can('manage_options')) return;

        $lists_error = '';
        $campaigns = [];
        try {
            $campaigns = $this->api->get_email_campaigns();
        } catch (Exception $e) {
            $lists_error = $e->getMessage();
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Brevo → Campaigns', self::TEXT_DOMAIN) . '</h1>';

        $create_url = admin_url('admin.php?page=brevo-create');
        echo '<p><a href="'.esc_url($create_url).'" class="button button-primary">' . esc_html__('Create campaign', self::TEXT_DOMAIN) . '</a></p>';

        if ($lists_error) {
            echo '<div class="notice notice-error"><p>' . esc_html($lists_error) . '</p></div>';
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr><th>ID</th><th>' . esc_html__('Name', self::TEXT_DOMAIN) . '</th><th>' . esc_html__('Subject', self::TEXT_DOMAIN) . '</th><th>' . esc_html__('Status', self::TEXT_DOMAIN) . '</th><th>' . esc_html__('Scheduled At', self::TEXT_DOMAIN) . '</th><th style="width:250px">' . esc_html__('Actions', self::TEXT_DOMAIN) . '</th></tr></thead>';
        echo '<tbody>';

        $rows = $campaigns['campaigns'] ?? [];
        if (empty($rows)) {
            echo '<tr><td colspan="6">' . esc_html__('No campaigns found.', self::TEXT_DOMAIN) . '</td></tr>';
        } else {
            foreach ($rows as $c) {
                $id      = isset($c['id']) ? intval($c['id']) : 0;
                $name    = $c['name'] ?? '';
                $subject = $c['subject'] ?? '';
                $status  = $c['status'] ?? '';
                $sched   = $c['scheduledAt'] ?? '-';

                // Actions: Edit (uvek), Remove (samo za draft)
                $edit_url = admin_url('admin.php?page=brevo-create&edit=' . $id);
                $actions  = '';
                $dup_url = admin_url('admin.php?page=brevo-create&duplicate=' . urlencode($id));

                if ($status === 'draft') {
                    $actions  .= '<a class="button" href="'.esc_url($edit_url).'">Edit</a>';
                    $actions .= ' <button type="button" class="button button-link-delete brevo-remove-campaign" data-id="'.esc_attr($id).'">' . esc_html__('Remove', self::TEXT_DOMAIN) . '</button>';
                }else{
                    $actions .= ' <button type="button" class="button button-secondary brevo-view-stats" data-id="'.esc_attr($id).'">' . esc_html__('View stats', self::TEXT_DOMAIN) . '</button>';
                    $actions .= ' <a class="button button-secondary brevo-view-stats" href="'. esc_attr($dup_url). '">' . esc_html__('Duplicate', self::TEXT_DOMAIN) . '</a>';
                }
                $actions .= ' <button type="button" class="button brevo-preview-campaign" data-status="'. ($c['status'] ?? '') . '" data-id="'. (int)($c['id'] ?? 0) . '">' . esc_html__('Preview', self::TEXT_DOMAIN) . '</button>';

                printf(
                    '<tr data-campaign-id="%1$d" data-status="%6$s"><td>#%1$d</td><td>%2$s</td><td>%3$s</td><td>%6$s</td><td>%5$s</td><td>%7$s</td></tr>',
                    $id,
                    esc_html($name),
                    esc_html($subject),
                    '',                         // (placeholder, unused)
                    esc_html($sched),
                    esc_html($status),
                    $actions
                );
            }
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function render_lists_page() {
        if (!current_user_can('manage_options')) return;

        echo '<div class="wrap"><h1>' . esc_html__('Brevo → Lists', self::TEXT_DOMAIN) . '</h1>';
        try {
            $lists = $this->api->get_lists();

            echo '<table class="widefat striped"><thead>
                    <tr>
                    <th style="width:80px">ID</th>
                    <th>' . esc_html__('Name', self::TEXT_DOMAIN) . '</th>
                    <th style="width:160px">' . esc_html__('Total Contacts', self::TEXT_DOMAIN) . '</th>
                    <th style="width:140px">' . esc_html__('Subscribers', self::TEXT_DOMAIN) . '</th>
                    <th style="width:140px">' . esc_html__('Blacklisted', self::TEXT_DOMAIN) . '</th>
                    </tr>
                </thead><tbody>';

            foreach ( ($lists['lists'] ?? []) as $l ) {
                $id    = isset($l['id']) ? (int)$l['id'] : 0;
                $name  = (string)($l['name'] ?? '');

                $uniq  = isset($l['uniqueSubscribers']) ? (int)$l['uniqueSubscribers'] : 0;      // total contacts
                $blk   = isset($l['totalBlacklisted'])  ? (int)$l['totalBlacklisted']  : 0;
                $subs0 = isset($l['totalSubscribers'])  ? (int)$l['totalSubscribers']  : 0;

                // Ako Brevo vraća 0 za totalSubscribers, prikaži realnije: uniq - blacklisted
                $subs  = $subs0 > 0 ? $subs0 : max(0, $uniq - $blk);

                printf(
                    '<tr><td>#%1$d</td><td>%2$s</td><td>%3$d</td><td>%4$d</td><td>%5$d</td></tr>',
                    $id,
                    esc_html($name),
                    $uniq, $subs, $blk
                );
            }

            echo '</tbody></table>';

        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>' . esc_html($e->getMessage()) . '</p></div>';
        }
        echo '</div>';
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap"><h1>' . esc_html__('Brevo → Settings', self::TEXT_DOMAIN) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('brevo_settings_group');
        do_settings_sections('brevo-settings');
        submit_button(__('Save Settings', self::TEXT_DOMAIN));
        echo '</form></div>';
    }

    public function render_create_page() {
        if (!current_user_can('manage_options')) return;

        $defaults = [
            'brevo_id'     => 0,        // NEW by default
            'name'         => '',
            'subject'      => '',
            'listIds'      => [],
            'sections'     => [],       // [{title, link, image, layout, postIds:[]}, ...]
            'preview_file' => '',       // NEW => prazan preview
            'template'     => 'template1.html', // ⬅️ default izabrani templejt
        ];

        $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $dup_id  = isset($_GET['duplicate']) ? intval($_GET['duplicate']) : 0;

        // --- EDIT tok (postojeći) ---
        if ($edit_id) {
            try {
                $info = $this->api->get_email_campaign($edit_id);
                $defaults['brevo_id'] = $edit_id;
                $defaults['name']     = $info['name']    ?? '';
                $defaults['subject']  = $info['subject'] ?? '';
                $defaults['template']  = $info['template'] ?? '';

                // Brevo može vratiti recipients.listIds ili recipients.lists
                $listsRaw = $info['recipients']['listIds'] ?? $info['recipients']['lists'] ?? [];
                if (!empty($listsRaw)) {
                    $defaults['listIds'] = array_map('intval', (array)$listsRaw);
                }
            } catch (Exception $e) {
                // nastavi sa lokalnim
            }

            $local = $this->get_local_campaign($edit_id);
            if ($local) {
                $payload = json_decode($local['payload'] ?? '[]', true);
                if (!empty($payload['sections'])) $defaults['sections'] = (array)$payload['sections'];

                if (empty($defaults['listIds']) && !empty($local['lists'])) {
                    $defaults['listIds'] = array_map('intval', json_decode($local['lists'], true) ?: []);
                }
                if (empty($defaults['name']) && !empty($local['name']))     $defaults['name']    = $local['name'];
                if (empty($defaults['subject']) && !empty($local['subject'])) $defaults['subject'] = $local['subject'];
                if (empty($defaults['template']) && !empty($local['template'])) $defaults['template'] = $local['template'];
                if (!empty($local['preview_file'])) $defaults['preview_file'] = $local['preview_file'];
            }
        }

        // --- DUPLICATE tok (novo) – prefill, ali ostaje NEW (brevo_id=0, preview_file='') ---
        if (!$edit_id && $dup_id) {
            try {
                $info = $this->api->get_email_campaign($dup_id);
                $defaults['name']    = rtrim(($info['name'] ?? ''), ' ') . ' (copy)';
                $defaults['subject'] = $info['subject'] ?? '';
                $defaults['template'] = $info['template'] ?? '';
                $listsRaw = $info['recipients']['listIds'] ?? $info['recipients']['lists'] ?? [];
                if (!empty($listsRaw)) {
                    $defaults['listIds'] = array_map('intval', (array)$listsRaw);
                }
            } catch (Exception $e) {
                // Ako Brevo API padne, nastavljamo bez meta
            }

            // Preuzmi naše sekcije iz lokalne tabele (ako postoje)
            $local = $this->get_local_campaign($dup_id);
            if ($local && !empty($local['payload'])) {
                $payload = json_decode($local['payload'], true);
                if (!empty($payload['sections'])) {
                    $defaults['sections'] = (array)$payload['sections'];
                }
                if (empty($defaults['template']) && !empty($local['template'])) $defaults['template'] = $local['template'];
            }
            // važno: ne diramo $defaults['brevo_id'] (ostaje 0) i $defaults['preview_file'] (ostaje '')
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Brevo → Create Campaign', self::TEXT_DOMAIN) . '</h1>';
        echo '<p class="description">' . esc_html__('Fill in the data and generate the HTML preview. Then save as a draft, send a test, schedule or submit.', self::TEXT_DOMAIN) . '</p>';
        echo '<p><a class="button" href="'.esc_url(admin_url('admin.php?page=brevo-panel')).'">' . esc_html__('← Back to Campaigns', self::TEXT_DOMAIN) . '</a></p>';

        // Info notice za duplicate
        if (!$edit_id && $dup_id) {
            echo '<div class="notice notice-info"><p>' . esc_html__('You are duplicating campaign #', self::TEXT_DOMAIN) . ''.esc_html($dup_id).'. ' . esc_html__('Fields are filled; be sure to generate the preview again.', self::TEXT_DOMAIN) . '</p></div>';
        }

        // prosledi default-e u formu (server-side prefill)
        AdminScreens::render_builder_form($defaults);

        echo '</div>';
    }

    public function render_forms_page() {
        if (!current_user_can('manage_options')) return;

        // (opciono) povuci liste iz Brevo API da pomogneš korisniku
        $lists = [];
        try { $resp = $this->api->get_lists(); $lists = $resp['lists'] ?? []; } catch (\Exception $e) {}

        echo '<div class="wrap"><h1>' . esc_html__('Brevo → Forms', self::TEXT_DOMAIN) . '</h1>';
        echo '<p class="description">' . esc_html__('Compile the subscribe form and click "Generate shortcode". Paste that code into a page/post, block/Widget, etc.', self::TEXT_DOMAIN) . '</p>';

        ?>
        <div class="brevo-form-builder">
        <table class="form-table">
            <tr>
            <th scope="row"><?php echo esc_html__('Form title', self::TEXT_DOMAIN); ?></th>
            <td><input type="text" id="bf-title" class="regular-text" placeholder="<?php echo esc_html__('eg. Newsletter subscription', self::TEXT_DOMAIN); ?>"></td>
            </tr>
            <tr>
            <th scope="row"><?php echo esc_html__('Intro text', self::TEXT_DOMAIN); ?></th>
            <td><textarea id="bf-blurb" class="large-text" rows="3" placeholder="<?php echo esc_html__('Short text above the form (optional)', self::TEXT_DOMAIN); ?>"></textarea></td>
            </tr>
            <tr>
            <th scope="row"><?php echo esc_html__('Fields', self::TEXT_DOMAIN); ?></th>
            <td>
                <label><input type="checkbox" class="bf-field" value="first"> <?php echo esc_html__('Name', self::TEXT_DOMAIN); ?></label>&nbsp;&nbsp;
                <label><input type="checkbox" class="bf-field" value="last"> <?php echo esc_html__('Last Name', self::TEXT_DOMAIN); ?></label>&nbsp;&nbsp;
                <label><input type="checkbox" class="bf-field" value="phone"> <?php echo esc_html__('Phone', self::TEXT_DOMAIN); ?></label>&nbsp;&nbsp;
                <label><input type="checkbox" class="bf-field" value="email" checked disabled> <?php echo esc_html__('Email (required)', self::TEXT_DOMAIN); ?></label>
                <p class="description"><?php echo esc_html__('Email is always on.', self::TEXT_DOMAIN); ?></p>
            </td>
            </tr>
            <tr>
            <th scope="row"><?php echo esc_html__('List options (select)', self::TEXT_DOMAIN); ?></th>
            <td>
                <table id="bf-lists" class="widefat" style="max-width:720px">
                <thead><tr><th style="width:55%"><?php echo esc_html__('Option label (visible in form)', self::TEXT_DOMAIN); ?></th><th><?php echo esc_html__('Brevo List ID (value)', self::TEXT_DOMAIN); ?></th><th style="width:70px"></th></tr></thead>
                <tbody></tbody>
                </table>
                <p><button type="button" class="button" id="bf-add-row"><?php echo esc_html__('+ Add option', self::TEXT_DOMAIN); ?></button></p>
                <?php if ($lists) {
                    echo '<p class="description">' . esc_html__('Help: your Brevo lists', self::TEXT_DOMAIN) . ': ';
                    $tmp = [];
                    foreach ($lists as $l) { $tmp[] = esc_html(($l['name']??'').' (#'.($l['id']??'').')'); }
                    echo implode(' · ', $tmp);
                    echo '</p>';
                } ?>
            </td>
            </tr>
            <tr>
            <th scope="row"><?php echo esc_html__('Button label', self::TEXT_DOMAIN); ?></th>
            <td><input type="text" id="bf-btn" class="regular-text" value="Subscribe"></td>
            </tr>
            <tr>
            <th scope="row"><?php echo esc_html__('Success message', self::TEXT_DOMAIN); ?></th>
            <td><input type="text" id="bf-success" class="regular-text" value="Hvala! Proverite inbox."></td>
            </tr>
            <tr>
            <th scope="row"><?php echo esc_html__('Error message', self::TEXT_DOMAIN); ?></th>
            <td><input type="text" id="bf-error" class="regular-text" value="Došlo je do greške. Pokušajte ponovo."></td>
            </tr>
        </table>

        <p><button type="button" class="button button-primary" id="bf-generate"><?php echo esc_html__('Generate shortcode', self::TEXT_DOMAIN); ?></button></p>
        <p><input type="text" id="bf-output" class="large-text code" readonly placeholder="<?php echo esc_html__('[shortcode will appear here]', self::TEXT_DOMAIN); ?>"></p>
        </div>

        <script>
        (function($){
        function addRow(label,val){
            const $tr = $('<tr/>');
            $tr.append('<td><input type="text" class="bf-opt-label widefat" value="'+(label||'')+'" placeholder="npr. Engleski newsletter"></td>');
            $tr.append('<td><input type="number" class="bf-opt-id small-text" value="'+(val||'')+'" placeholder="List ID"></td>');
            $tr.append('<td><button type="button" class="button-link-delete bf-del">Remove</button></td>');
            $('#bf-lists tbody').append($tr);
        }
        $('#bf-add-row').on('click', function(){ addRow(); });
        $(document).on('click','.bf-del', function(){ $(this).closest('tr').remove(); });

        $('#bf-generate').on('click', function(){
            const title  = $('#bf-title').val().trim();
            const blurb  = $('#bf-blurb').val().trim();
            const btn    = $('#bf-btn').val().trim() || 'Subscribe';
            const okMsg  = $('#bf-success').val().trim() || 'Thanks!';
            const errMsg = $('#bf-error').val().trim() || 'Error';

            const fields = [];
            $('.bf-field:checked').each(function(){ fields.push($(this).val()); });
            fields.push('email'); // uvek

            const opts = [];
            $('#bf-lists tbody tr').each(function(){
            const label = $(this).find('.bf-opt-label').val().trim();
            const id    = $(this).find('.bf-opt-id').val().trim();
            if(label && id) opts.push(label+':'+id);
            });

            if(!opts.length){ alert('Add at least one list (label + list ID).'); return; }

            // Sastavi shortcode
            function esc(s){ return s.replace(/"/g, '&quot;'); }
            const sc = '[brevo_form'
            + (title? ' title="'+esc(title)+'"':'')
            + (blurb? ' blurb="'+esc(blurb)+'"':'')
            + ' fields="'+esc(fields.join(','))+'"'
            + ' lists="'+esc(opts.join('|'))+'"'
            + ' button="'+esc(btn)+'"'
            + ' success="'+esc(okMsg)+'"'
            + ' error="'+esc(errMsg)+'"'
            + ']';

            $('#bf-output').val(sc).focus().select();
        });
        })(jQuery);
        </script>
        <?php
        echo '</div>';
    }

    // === AJAX Handlers ===
    private function verify_nonce_caps() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Forbidden'], 403);
        check_ajax_referer('brevo_nonce', 'nonce');
    }

    public function ajax_generate_preview() {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        $this->verify_nonce_caps();
        $payload = $_POST['payload'] ?? [];
        $builder = new BrevoTemplateBuilder();
        $html = $builder->build_html_from_payload($payload);
        $upload = wp_upload_dir();
        $unique = uniqid('brevo-campaign-');
        $file = trailingslashit($upload['basedir']) . self::UPLOAD_SUBDIR . "/{$unique}.html";
        file_put_contents($file, $html);
        $url = trailingslashit($upload['baseurl']) . self::UPLOAD_SUBDIR . "/{$unique}.html";
        wp_send_json_success(['url' => $url, 'file' => $file]);
    }

    public function ajax_create_campaign() {
        $this->verify_nonce_caps();

        $opts = get_option(self::OPTION_KEY, []);
        $in   = wp_unslash($_POST);

        $subject     = sanitize_text_field($in['subject'] ?? '');
        $template     = sanitize_text_field($in['template'] ?? '');
        $name        = sanitize_text_field($in['name'] ?? $subject);
        $list_ids    = array_map('intval', $in['listIds'] ?? []);
        $previewFile = sanitize_text_field($in['previewFile'] ?? '');
        $campaignId  = intval($in['campaignId'] ?? 0);
        $forceNew    = !empty($in['forceNew']);

        if (!$name) wp_send_json_error(['message' => 'Campaign name is required.'], 400);
        if (!$subject) wp_send_json_error(['message' => 'Subject is required.'], 400);
        if (empty($list_ids)) wp_send_json_error(['message' => 'Select at least one recipients list.'], 400);
        if (!$previewFile || !file_exists($previewFile)) wp_send_json_error(['message' => 'Generate preview before saving.'], 400);

        // ← builder payload (forme: sections, layout, postIds, link, image, titles…)
        $builderPayload = json_decode(stripslashes($in['builderPayload'] ?? ''), true);
        if (!is_array($builderPayload)) $builderPayload = [];

        $html = file_get_contents($previewFile) ?: '<html><body><p>No content</p></body></html>';

        $payload = [
            'name'        => $name,
            'subject'     => $subject,
            'template'     => $template,
            'sender'      => ['email' => $opts['sender_email'] ?? '', 'name' => $opts['sender_name'] ?? ''],
            'recipients'  => ['listIds' => $list_ids],
            'htmlContent' => $html,
            'inlineImageActivation' => false,
            'tags'        => ['wordpress'],
            'type'        => 'classic',
        ];

        try {
            $savedId = 0; $res = [];
            if ($campaignId && !$forceNew) {
                try {
                    $this->api->update_email_campaign($campaignId, $payload); // 204
                    $savedId = $campaignId;
                    $res = ['id' => $savedId, 'updated' => true];
                } catch (Exception $e) {
                    // fallback na create
                }
            }
            if (!$savedId) {
                $payload['status'] = 'draft';
                $createResp = $this->api->create_email_campaign($payload);
                $savedId = intval($createResp['id'] ?? 0);
                $res = ['id' => $savedId, 'created' => true, 'duplicated' => (bool)$campaignId];
            }

            // ↑ lokalno snimanje builder podataka
            if ($savedId) {
                $this->upsert_local_campaign($savedId, [
                    'name'         => $name,
                    'subject'      => $subject,
                    'template'      => $template,
                    'lists'        => $list_ids,
                    'preview_file' => $previewFile,
                    'payload'      => $builderPayload,
                    'status'       => 'draft',
                ]);
            }

            wp_send_json_success($res);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajax_send_test() {
        $this->verify_nonce_caps();
        $id = intval($_POST['campaignId'] ?? 0);
        $emails = array_map('sanitize_email', $_POST['emails'] ?? []);
        $emails = array_values(array_filter($emails));
        $list_ids = array_map('intval', $_POST['listIds'] ?? []);
        if (!$id) wp_send_json_error(['message' => 'Invalid campaign ID'], 400);
        if (empty($emails)) wp_send_json_error(['message' => 'Provide at least one test email'], 400);

        // Fallback na default test listu iz Settings
        $opts = get_option(self::OPTION_KEY, []);
        if (empty($list_ids) && !empty($opts['test_list_id'])) {
            $list_ids = [intval($opts['test_list_id'])];
        }

        try {
            // Kreiraj/azuriraj kontakt(e) i po potrebi ih upiši u listu(e)
            foreach ($emails as $em) {
                try { $this->api->create_or_update_contact($em, $list_ids); } catch (Exception $ie) {}
            }
            $resp = $this->api->send_test_email($id, $emails);
            wp_send_json_success($resp);
        } catch (Exception $e) {
            $hint = ' Make sure test emails exist as contacts in Brevo, are not blacklisted, and (if required) belong to a contact list.';
            wp_send_json_error(['message' => 'Brevo API error: ' . $e->getMessage() . $hint]);
        }
    }

    public function ajax_schedule_campaign() {
        $this->verify_nonce_caps();
        $id = intval($_POST['campaignId'] ?? 0);
        $schedule_at = sanitize_text_field($_POST['scheduleAt'] ?? ''); // ISO 8601
        try {
            $resp = $this->api->schedule_campaign($id, $schedule_at);
            wp_send_json_success($resp);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajax_confirm_campaign() {
        $this->verify_nonce_caps();
        $id = intval($_POST['campaignId'] ?? 0);
        try {
            $resp = $this->api->send_campaign_now($id);
            wp_send_json_success($resp);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajax_search_posts() {
        $this->verify_nonce_caps();
        $term = sanitize_text_field($_POST['term'] ?? '');
        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = 10;

        $q = new WP_Query([
            's' => $term,
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'ignore_sticky_posts' => true,
        ]);

        $items = [];
        while ($q->have_posts()) { $q->the_post();
            $items[] = [
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'excerpt' => wp_strip_all_tags(get_the_excerpt()),
                'thumb' => get_the_post_thumbnail_url(get_the_ID(), 'thumbnail') ?: '',
                'url' => get_permalink(),
            ];
        }
        wp_reset_postdata();

        wp_send_json_success([
            'items' => $items,
            'hasMore' => ($q->max_num_pages > $page),
        ]);
    }

    public function ajax_posts_by_ids() {
        $this->verify_nonce_caps();
        $ids_raw = $_POST['ids'] ?? [];
        if (is_string($ids_raw)) {
            $ids = array_map('intval', array_filter(array_map('trim', explode(',', $ids_raw))));
        } else {
            $ids = array_map('intval', (array)$ids_raw);
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) wp_send_json_success(['items' => []]);

        $q = new WP_Query([
            'post_type'      => 'post',
            'post__in'       => $ids,
            'orderby'        => 'post__in',
            'posts_per_page' => count($ids),
            'post_status'    => 'publish',
            'ignore_sticky_posts' => true,
        ]);

        $items = [];
        while ($q->have_posts()) { $q->the_post();
            $items[] = [
                'id'      => get_the_ID(),
                'title'   => get_the_title(),
                'excerpt' => wp_strip_all_tags(get_the_excerpt()),
                'thumb'   => get_the_post_thumbnail_url(get_the_ID(), 'thumbnail') ?: '',
                'url'     => get_permalink(),
            ];
        }
        wp_reset_postdata();

        wp_send_json_success(['items' => $items]);
    }

    public function ajax_delete_campaign() {
        $this->verify_nonce_caps();

        $id = intval($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error(['message' => 'Invalid campaign ID'], 400);

        try {
            // (opciono) proveri status i dozvoli brisanje samo za draft
            try {
                $info = $this->api->get_email_campaign($id);
                // U Brevo payloadu status za draft je npr. "draft"
                if (!empty($info['status']) && $info['status'] !== 'draft') {
                    wp_send_json_error(['message' => 'Only draft campaigns can be removed.'], 400);
                }
            } catch (Exception $e) {
                // Ako GET padne, ipak probaj DELETE (možda je dovoljan)
            }

            $this->api->delete_email_campaign($id); // 204 on success
            wp_send_json_success(['deleted' => true, 'id' => $id]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Brevo API error: ' . $e->getMessage()], 400);
        }

        $this->delete_local_campaign($id);
    }

    public function ajax_get_campaign_stats() {
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'Forbidden'], 403);
        check_admin_referer('brevo_nonce', 'nonce');

        $id = intval($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error(['message'=>'Missing id'], 400);

        try {
            $report = $this->api->get_campaign_report($id);

            $stats = $report['statistics'] ?? [];
            $global = $stats['globalStats'] ?? [];

            // helper za sabiranje
            $sum = [
                'delivered'      => 0,
                'sent'           => 0,
                'uniqueViews'    => 0,
                'uniqueClicks'   => 0,
                'unsubscriptions'=> 0,
                'softBounces'    => 0,
                'hardBounces'    => 0,
                'complaints'     => 0,
            ];

            // Ako globalStats ima nule (kao u tvom dumpu), saberemo campaignStats po listama
            if (!empty($stats['campaignStats']) && is_array($stats['campaignStats'])) {
                foreach ($stats['campaignStats'] as $row) {
                    foreach ($sum as $k => $_) {
                        $sum[$k] += (int)($row[$k] ?? 0);
                    }
                }
            }

            // Uzmi izračunate vrednosti ili fallback na global
            $delivered = $sum['delivered'] ?: (int)($global['delivered'] ?? $global['sent'] ?? 0);
            $sent      = $sum['sent']      ?: (int)($global['sent'] ?? $delivered);
            $opens     = $sum['uniqueViews']  ?: (int)($global['uniqueViews'] ?? $global['viewed'] ?? 0);
            $clicks    = $sum['uniqueClicks'] ?: (int)($global['uniqueClicks'] ?? $global['clickers'] ?? 0);
            $unsubs    = $sum['unsubscriptions'] ?: (int)($global['unsubscriptions'] ?? 0);
            $bounces   = ($sum['hardBounces'] + $sum['softBounces'])
                            ?: (int)($global['hardBounces'] ?? 0) + (int)($global['softBounces'] ?? 0);
            $spam      = $sum['complaints'] ?: (int)($global['complaints'] ?? 0);

            $deliveryRate = $sent ? round($delivered * 100 / $sent, 2) : 0;
            $openRate     = $delivered ? round($opens  * 100 / $delivered, 2) : 0;
            $ctr          = $delivered ? round($clicks * 100 / $delivered, 2) : 0;
            $ctor         = $opens     ? round($clicks * 100 / $opens,     2) : 0;

            // linksStats može stići kao mapa URL => count ili kao lista objekata
            $links = [];
            $linksNode = $stats['linksStats'] ?? [];
            if (is_array($linksNode)) {
                if (array_keys($linksNode) !== range(0, count($linksNode) - 1)) {
                    // asocijativna mapa
                    foreach ($linksNode as $url => $count) {
                        $links[] = ['url' => (string)$url, 'count' => (int)$count];
                    }
                } else {
                    // lista objekata {url,count}
                    foreach ($linksNode as $ls) {
                        $links[] = ['url' => (string)($ls['url'] ?? ''), 'count' => (int)($ls['count'] ?? 0)];
                    }
                }
                usort($links, fn($a,$b)=>$b['count'] <=> $a['count']);
                $links = array_slice($links, 0, 8);
            }

            wp_send_json_success([
                'metrics' => [
                    'delivered'    => $delivered,
                    'sent'         => $sent,
                    'opens'        => $opens,
                    'clicks'       => $clicks,
                    'unsubs'       => $unsubs,
                    'bounces'      => $bounces,
                    'spam'         => $spam,
                    'deliveryRate' => $deliveryRate,
                    'openRate'     => $openRate,
                    'ctr'          => $ctr,
                    'ctor'         => $ctor,
                ],
                'links' => $links,
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }

    }

    public function ajax_campaign_preview() {
        check_ajax_referer('brevo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied'], 403);
        }
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) wp_send_json_error(['message' => 'Missing id'], 400);

        // 1) Pokušaj preko Brevo API
        try {
            $info = $this->api->get_email_campaign($id); // GET /emailCampaigns/{id}
            $share_url = wp_http_validate_url( $info['shareLink'] ?? '' );

            if($share_url){
                wp_send_json_success(['type' => 'url', 'value' => $share_url]);
            }else{
                $html = $info['htmlContent'] ?? '';
                if ($html) {
                    wp_send_json_success(['type' => 'html', 'value' => $html]);
                }
            } 
        } catch (Exception $e) {
            // ignoriši, padamo na lokalni preview
        }

        // 2) Fallback: lokalni preview koji smo generisali
        $local = $this->get_local_campaign($id);
        if ($local && !empty($local['preview_file'])) {
            $url = $this->resolve_preview_url($local['preview_file']);
            if ($url) wp_send_json_success(['type' => 'url', 'value' => $url]);
        }

        wp_send_json_error(['message' => 'Preview is not available for this campaign yet.'], 404);
    }

    public function ajax_brevo_mail_test() {
        check_ajax_referer('brevo_nonce','nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message'=>'Permission denied'], 403);
        }

        $opts = get_option(self::OPTION_KEY, []);
        // Pre-checkovi da ne pokušavamo uz očigledno pogrešnu postavku
        if (empty($opts['wp_mail_enable'])) {
            wp_send_json_error(['message' => 'Brevo SMTP routing is OFF. Enable "Use Brevo for WP Mail" and save settings.'], 400);
        }
        if (empty($opts['api_key'])) {
            wp_send_json_error(['message' => 'Missing Brevo API key (used as SMTP password).'], 400);
        }
        if (empty($opts['wp_mail_from_email'])) {
            wp_send_json_error(['message' => 'From email is empty. Set a verified sender/domain in Settings.'], 400);
        }

        $to = isset($_POST['to']) ? sanitize_email(wp_unslash($_POST['to'])) : '';
        if (!$to || !is_email($to)) {
            // ako nije prosleđeno, pošalji sebi na From
            $to = sanitize_email($opts['wp_mail_from_email']);
        }

        // Resetuj poslednju grešku pre pokušaja
        $this->last_mail_error = '';

        $ok = wp_mail($to, 'Brevo SMTP test', "If you received this, WP is sending via Brevo SMTP.\nTime: " . date('c'));

        if ($ok) {
            wp_send_json_success();
        }

        // Ako je palo – probaj dati korisnu poruku
        $msg = $this->last_mail_error ?: 'wp_mail() returned false';
        // kratak hint
        $msg .= ' | Hints: check host/port (smtp-relay.brevo.com, 587 TLS or 465 SSL), API key, verified sending domain, and that your hosting allows outbound SMTP.';
        wp_send_json_error(['message' => $msg]);
    }

    public function ajax_front_subscribe() {
        check_ajax_referer('brevo_nonce','nonce');

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $list  = isset($_POST['list_id']) ? intval($_POST['list_id']) : 0;

        $first = isset($_POST['first']) ? sanitize_text_field(wp_unslash($_POST['first'])) : '';
        $last  = isset($_POST['last'])  ? sanitize_text_field(wp_unslash($_POST['last']))  : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';

        if (!$email || !is_email($email)) wp_send_json_error(['message'=>'Unesite ispravan email.'], 400);
        if (!$list) wp_send_json_error(['message'=>'Izaberite listu.'], 400);

        // pripremi atribute (Brevo tipično koristi ova imena; ako ih nema, ignorišu se)
        $attrs = [];
        if ($first) $attrs['FIRSTNAME'] = $first;
        if ($last)  $attrs['LASTNAME']  = $last;
        if ($phone) $attrs['SMS']       = $phone; // ili 'PHONE' u tvom nalogu

        try {
            // Pobrinuti se da ApiClient podrži $attributes kao 3. parametar (fallback: napravi if)
            if ( (new \ReflectionMethod($this->api, 'create_or_update_contact'))->getNumberOfParameters() >= 3 ) {
                $this->api->create_or_update_contact($email, [$list], $attrs);
            } else {
                // starija signatura
                $this->api->create_or_update_contact($email, [$list]);
                if (!empty($attrs) && method_exists($this->api, 'update_contact_attributes')) {
                    $this->api->update_contact_attributes($email, $attrs);
                }
            }
            wp_send_json_success(['ok'=>true]);
        } catch (\Exception $e) {
            wp_send_json_error(['message'=>'Greška: '.$e->getMessage()], 500);
        }
    }

    public function ajax_form_subscribe() {
        check_ajax_referer('brevo_nonce', 'nonce');

        // Polja iz forme
        $email   = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $fname   = sanitize_text_field($_POST['fname'] ?? '');
        $lname   = sanitize_text_field($_POST['lname'] ?? '');
        $phone   = sanitize_text_field($_POST['phone'] ?? '');
        // Dozvoli 1 ili više lista
        $list_id = isset($_POST['list_id']) ? (int)$_POST['list_id'] : 0;
        $listIds = [];
        if (!empty($_POST['list_ids'])) {
            $listIds = array_map('intval', (array)$_POST['list_ids']);
        } elseif ($list_id) {
            $listIds = [$list_id];
        }

        if (!$email || !is_email($email)) {
            wp_send_json_error(['message' => 'Please enter a valid email address.'], 400);
        }
        if (empty($listIds)) {
            wp_send_json_error(['message' => 'Please select at least one list.'], 400);
        }

        $opts = get_option(self::OPTION_KEY, []);
        $attrs = [];
        if ($fname) $attrs['FIRSTNAME'] = $fname;
        if ($lname) $attrs['LASTNAME']  = $lname;
        if ($phone) $attrs['SMS']       = $phone; // Brevo koristi SMS za phone broj

        try {
            // 1) Ako je uključen DOI => šalji DOI poziv i završavaš (kontakt se doda nakon potvrde)
            if (!empty($opts['forms_doi_enable']) && !empty($opts['forms_doi_template']) && !empty($opts['forms_doi_redirect'])) {
                // /contacts/doubleOptinConfirmation
                if (method_exists($this->api, 'send_double_optin')) {
                    $this->api->send_double_optin(
                        $email,
                        $attrs,
                        $listIds,
                        (int)$opts['forms_doi_template'],
                        $opts['forms_doi_redirect'],
                    );
                } else {
                    // Minimalna implementacija ako nema metoda u ApiClient:
                    $resp = wp_remote_post('https://api.brevo.com/v3/contacts/doubleOptinConfirmation', [
                        'headers' => [
                            'api-key'      => $opts['api_key'] ?? '',
                            'content-type' => 'application/json',
                            'accept'       => 'application/json',
                        ],
                        'body'    => wp_json_encode([
                            'templateId'     => (int)$opts['forms_doi_template'],
                            'email'        => $email,
                            'includeListIds' => array_values($listIds),
                            'redirectionUrl' => $opts['forms_doi_redirect'],
                            'attributes'     => (object)$attrs,
                        ]),
                        'timeout' => 15,
                    ]);
                    if (is_wp_error($resp) || (int)wp_remote_retrieve_response_code($resp) >= 300) {
                        $msg = is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_body($resp);
                        wp_send_json_error(['message' => 'Brevo DOI failed: ' . $msg], 500);
                    }
                }

                wp_send_json_success(['doi' => true, 'message' => 'Please check your inbox to confirm subscription.']);
            }

            // 2) Ako DOI nije uključen => upiši/azuriraj kontakt odmah
            if (method_exists($this->api, 'create_or_update_contact')) {
                $this->api->create_or_update_contact($email, $listIds, $attrs, true);
            } else {
                // fallback minimalni /contacts
                $resp = wp_remote_post('https://api.brevo.com/v3/contacts', [
                    'headers' => [
                        'api-key'      => $opts['api_key'] ?? '',
                        'content-type' => 'application/json',
                        'accept'       => 'application/json',
                    ],
                    'body' => wp_json_encode([
                        'email'          => $email,
                        'attributes'     => (object)$attrs,
                        'listIds'        => array_values($listIds),
                        'updateEnabled'  => true,
                    ]),
                    'timeout' => 15,
                ]);
                if (is_wp_error($resp)) {
                    wp_send_json_error(['message' => $resp->get_error_message()], 500);
                }
                $code = (int)wp_remote_retrieve_response_code($resp);
                if ($code >= 300) {
                    wp_send_json_error(['message' => 'Brevo error: ' . wp_remote_retrieve_body($resp)], 500);
                }
            }

            // 3) Welcome email ako je uključeno (i DOI nije uključen)
            if (!empty($opts['forms_welcome_enable'])) {
                $subj = $opts['forms_welcome_subject'] ?: 'Welcome!';
                $html = $opts['forms_welcome_html'] ?: '<p>Thanks for subscribing!</p>';

                // šaljemo preko wp_mail() (već routirano na Brevo)
                add_filter('wp_mail_content_type', function(){ return 'text/html; charset=UTF-8'; });
                $ok = wp_mail($email, $subj, $html);
                remove_filter('wp_mail_content_type', '__return_false'); // safety
                // ne dižemo grešku ako welcome padne — subscription je prošao
            }

            wp_send_json_success(['subscribed' => true, 'message' => 'You are subscribed.']);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }


    // === DB ===
    private function table_name() {
        global $wpdb; return $wpdb->prefix . 'wsh_brevo_campaigns';
    }

    private function get_local_campaign($brevo_id) {
        global $wpdb; $tbl = $this->table_name();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $tbl WHERE brevo_id=%d", $brevo_id), ARRAY_A);
    }

    private function upsert_local_campaign($brevo_id, $args) {
        global $wpdb; $tbl = $this->table_name();
        $now = current_time('mysql');
        $row = [
            'brevo_id'     => (int)$brevo_id,
            'name'         => (string)($args['name'] ?? ''),
            'subject'      => (string)($args['subject'] ?? ''),
            'template'      => (string)($args['template'] ?? ''),
            'lists'        => isset($args['lists']) ? wp_json_encode(array_map('intval', (array)$args['lists'])) : null,
            'preview_file' => (string)($args['preview_file'] ?? ''),
            'payload'      => isset($args['payload']) ? wp_json_encode($args['payload']) : null,
            'status'       => (string)($args['status'] ?? 'draft'),
            'updated_at'   => $now,
        ];
        $existing = $this->get_local_campaign($brevo_id);
        if ($existing) {
            return $wpdb->update($tbl, $row, ['brevo_id' => (int)$brevo_id]);
        } else {
            $row['created_at'] = $now;
            return $wpdb->insert($tbl, $row);
        }
    }

    private function delete_local_campaign($brevo_id) {
        global $wpdb; $tbl = $this->table_name();
        return $wpdb->delete($tbl, ['brevo_id' => (int)$brevo_id]);
    }

    private function resolve_preview_url($stored) {
        if (!$stored) return '';
        if (preg_match('#^https?://#i', $stored)) return $stored;
        $upload  = wp_upload_dir();
        $basedir = trailingslashit($upload['basedir']);
        $baseurl = trailingslashit($upload['baseurl']);
        if (stripos($stored, $basedir) === 0) {
            return $baseurl . ltrim(substr($stored, strlen($basedir)), '/');
        }
        $stored = ltrim($stored, '/');
        if (stripos($stored, 'uploads/') === 0) $stored = substr($stored, 8);
        return $baseurl . $stored;
    }

    // === Send emails via BREVO ===
    public function filter_wp_from_email($email) {
        $o = get_option(self::OPTION_KEY, []);
        if (!empty($o['wp_mail_enable']) && !empty($o['wp_mail_from_email'])) {
            return $o['wp_mail_from_email'];
        }
        return $email;
    }

    public function filter_wp_from_name($name) {
        $o = get_option(self::OPTION_KEY, []);
        if (!empty($o['wp_mail_enable']) && isset($o['wp_mail_from_name']) && $o['wp_mail_from_name'] !== '') {
            return $o['wp_mail_from_name'];
        }
        return $name;
    }

    public function on_wp_mail_failed( $wp_error ) {
        if ($wp_error instanceof WP_Error) {
            $msg = $wp_error->get_error_message();
            $data = $wp_error->get_error_data();
            // Povuci i SMTP debug ako ga WP/PHPMailer prosledi
            if (is_array($data)) {
                if (!empty($data['smtp_debug'])) {
                    $msg .= ' | ' . trim($data['smtp_debug']);
                } elseif (!empty($data['phpmailer_exception_code'])) {
                    $msg .= ' | code: ' . $data['phpmailer_exception_code'];
                }
            }
            $this->last_mail_error = $msg;
        } else {
            $this->last_mail_error = 'Unknown mail error';
        }
    }

    public function maybe_route_wp_mail_via_brevo($phpmailer) {
        $o = get_option(self::OPTION_KEY, []);
        if (empty($o['wp_mail_enable'])) return;

        $apiKey = trim($o['api_key'] ?? '');
        if (!$apiKey) return;

        $host = trim($o['wp_mail_host'] ?? 'smtp-relay.brevo.com');
        $port = (int)($o['wp_mail_port'] ?? 587);
        $enc  = in_array(($o['wp_mail_encryption'] ?? 'tls'), ['tls','ssl'], true) ? $o['wp_mail_encryption'] : 'tls';
        $user = trim($o['wp_mail_username'] ?? 'apikey');  // ← uzmi iz settings-a

        $phpmailer->isSMTP();
        $phpmailer->Host       = $host;
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Port       = $port;
        $phpmailer->SMTPSecure = $enc;                // 'tls' ili 'ssl'
        $phpmailer->Username   = $user;            // Brevo traži bukvalno 'apikey'
        $phpmailer->Password   = $apiKey;
        //$phpmailer->AuthType   = 'LOGIN';             // ← eksplicitno (nekad pomogne)
        $phpmailer->SMTPAutoTLS = ($enc === 'tls');   // osiguraj STARTTLS na 587
        $phpmailer->CharSet    = 'UTF-8';

        // (privremeni debug – uključi samo dok testiraš)
        if (1==1) {
            $phpmailer->SMTPDebug = 2;                // verbose
            $phpmailer->Debugoutput = function($str) {
                // upiši u error_log da vidiš kompletan 535 odgovor servera
                error_log('[BREVO_SMTP] ' . trim($str));
            };
        }

        // From / Reply-To
        $fromEmail = trim($o['wp_mail_from_email'] ?? '');
        $fromName  = trim($o['wp_mail_from_name']  ?? '');
        if ($fromEmail) {
            try { $phpmailer->setFrom($fromEmail, $fromName ?: $phpmailer->FromName, false); } catch (\Exception $e) {}
        }
        $reply = trim($o['wp_mail_reply_to'] ?? '');
        if ($reply) {
            try { $phpmailer->clearReplyTos(); $phpmailer->addReplyTo($reply); } catch (\Exception $e) {}
        }
    }

    public function pre_wp_mail_via_brevo_api($null, $atts) {
        $o = get_option(self::OPTION_KEY, []);
        if (empty($o['wp_mail_enable']) || ($o['wp_mail_transport'] ?? 'smtp') !== 'api') {
            return $null; // ne diramo; ide klasično (SMTP/PHPMailer)
        }
        if (empty($o['api_key'])) {
            return new WP_Error('brevo_api_key', 'Brevo API key is missing.');
        }

        // --- Normalizacija ulaza ---
        $to        = $atts['to'];
        $subject   = (string)($atts['subject'] ?? '');
        $message   = (string)($atts['message'] ?? '');
        $headersIn = $atts['headers'] ?? [];
        $filesIn   = $atts['attachments'] ?? [];

        // Headers mogu doći kao string sa \r\n ili kao array
        $headersArr = [];
        if (is_string($headersIn)) {
            $lines = preg_split('/\r\n|\r|\n/', $headersIn);
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    list($k,$v) = array_map('trim', explode(':', $line, 2));
                    if ($k !== '') $headersArr[] = [$k,$v];
                }
            }
        } else {
            foreach ((array)$headersIn as $h) {
                if (is_string($h) && strpos($h, ':') !== false) {
                    list($k,$v) = array_map('trim', explode(':', $h, 2));
                    if ($k !== '') $headersArr[] = [$k,$v];
                } elseif (is_array($h) && isset($h[0])) {
                    $headersArr[] = [$h[0], $h[1] ?? ''];
                }
            }
        }

        // --- To / Cc / Bcc / Reply-To ---
        $toList = [];
        $ccList = [];
        $bccList = [];
        $replyTo = '';

        $addrToArr = is_array($to) ? $to : array_map('trim', explode(',', $to));
        foreach ($addrToArr as $rcpt) {
            $rcpt = trim($rcpt);
            if ($rcpt === '') continue;
            if (preg_match('/(.*)<(.+@.+)>/', $rcpt, $m)) {
                $toList[] = ['email' => sanitize_email($m[2]), 'name' => trim($m[1])];
            } else {
                $toList[] = ['email' => sanitize_email($rcpt)];
            }
        }

        $contentType = 'text/plain';
        $customHeaders = [];
        foreach ($headersArr as $pair) {
            $name  = sanitize_text_field($pair[0]);
            $value = trim($pair[1] ?? '');
            if ($name === '') continue;
            $lk = strtolower($name);

            if ($lk === 'content-type') {
                $contentType = strtolower($value);
            } elseif ($lk === 'reply-to') {
                $replyTo = $value;
            } elseif ($lk === 'cc') {
                foreach (array_map('trim', explode(',', $value)) as $cc) {
                    if ($cc) $ccList[] = ['email' => sanitize_email($cc)];
                }
            } elseif ($lk === 'bcc') {
                foreach (array_map('trim', explode(',', $value)) as $bcc) {
                    if ($bcc) $bccList[] = ['email' => sanitize_email($bcc)];
                }
            } else {
                // Brevo dozvoljava "headers" u telu
                $customHeaders[$name] = $value;
            }
        }

        // --- Sadržaj: htmlContent / textContent ---
        $isHtml = (strpos($contentType, 'text/html') !== false)
                || stripos($message, '<html') !== false
                || stripos($message, '<p') !== false;

        $bodyField = $isHtml ? ['htmlContent' => $message] : ['textContent' => $message];

        // --- Attachments (base64) ---
        $attachments = [];
        foreach ((array)$filesIn as $file) {
            // WP ume da šalje kao string sa putanjom; uzmi samo čitljive fajlove
            $path = is_array($file) ? ($file[0] ?? $file[1] ?? '') : $file;
            $path = wp_normalize_path($path);
            if (!$path || !@is_file($path) || !@is_readable($path)) continue;

            $attachments[] = [
                'name'        => basename($path),
                'content'     => base64_encode(file_get_contents($path)),
                'contentType' => function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream',
            ];
        }

        // --- Sender (iz Settings; kao u WP SMTP) ---
        $senderEmail = $o['wp_mail_from_email'] ?: ($o['sender_email'] ?? '');
        $senderName  = $o['wp_mail_from_name']  ?: ($o['sender_name']  ?? '');

        $payload = array_merge([
            'sender'  => ['email' => $senderEmail, 'name' => $senderName],
            'to'      => $toList,
            'subject' => $subject,
        ], $bodyField);

        if ($replyTo && is_email($replyTo)) {
            $payload['replyTo'] = ['email' => $replyTo];
        }
        if (!empty($ccList))  { $payload['cc']  = $ccList; }
        if (!empty($bccList)) { $payload['bcc'] = $bccList; }
        if (!empty($attachments)) { $payload['attachment'] = $attachments; }
        if (!empty($customHeaders)) { $payload['headers'] = $customHeaders; }

        try {
            $resp = $this->api->send_transactional_email($payload);
            // ako želiš da “vučeš” Message-ID kao u WP SMTP:
            // $msgId = $resp['messageId'] ?? '';
            return true;
        } catch (\Exception $e) {
            return new WP_Error('brevo_api_send_failed', 'Brevo API send failed: ' . $e->getMessage());
        }
    }

    public function register_brevo_webhook_route() {
        register_rest_route('brevo/v1', '/events', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_brevo_webhook'],
            'permission_callback' => '__return_true', // validiraćemo token ručno
        ]);
    }

    public function handle_brevo_webhook(WP_REST_Request $req) {
        $o = get_option(self::OPTION_KEY, []);
        $token = $req->get_param('token');

        if (empty($o['webhook_secret']) || $token !== $o['webhook_secret']) {
            return new WP_REST_Response(['message' => 'Unauthorized'], 401);
        }

        $payload = json_decode($req->get_body(), true);
        if (!$payload) {
            return new WP_REST_Response(['message' => 'Bad payload'], 400);
        }

        // Brevo može slati jedan event ili niz eventova
        $events = isset($payload[0]) ? $payload : [$payload];

        foreach ($events as $e) {
            // Primer polja: event, email, ts, message-id, campaign_id, link, reason...
            // Uradi šta ti treba: upiši u log/tabelu ili agregiraj po kampanji
            // error_log('BREVO EVENT: ' . wp_json_encode($e));
            // npr. $this->store_brevo_event($e);
        }

        return new WP_REST_Response(['ok' => true], 200);
    }

    // === Form shotcode BREVO ===
    public function shortcode_brevo_form($atts = []) {
        // defaults
        $a = shortcode_atts([
            'title'   => '',
            'blurb'   => '',
            'fields'  => 'email', // CSV: first,last,phone,email
            'lists'   => '',      // "Label 1:14|Label 2:13"
            'button'  => 'Subscribe',
            'success' => 'Thanks for subscribing!',
            'error'   => 'Something went wrong. Please try again.',
            'class'   => '',
        ], $atts, 'brevo_form');

        // Parse fields
        $want = array_filter(array_map('trim', explode(',', strtolower($a['fields']))));
        if (!in_array('email', $want, true)) $want[] = 'email'; // email je obavezan

        // Parse lists => [ ['label'=>..,'id'=>..], ... ]
        $choices = [];
        foreach (array_filter(array_map('trim', explode('|', $a['lists']))) as $pair) {
            if (strpos($pair, ':') === false) continue;
            [$label, $id] = array_map('trim', explode(':', $pair, 2));
            if ($label !== '' && ctype_digit($id)) $choices[] = ['label'=>$label, 'id'=>(int)$id];
        }
        if (empty($choices)) return '<div class="brevo-form-error">No lists configured.</div>';

        // unique id
        $uid = 'brevof_'.wp_generate_password(6,false,false);

        // enqueue assets once
        wp_enqueue_style('brevo-forms');
        wp_enqueue_script('brevo-forms');

        ob_start(); ?>
        <div class="brevo-form <?php echo esc_attr($a['class']); ?>" id="<?php echo esc_attr($uid); ?>">
        <?php if ($a['title'])  echo '<h3 class="brevo-form-title">'.esc_html($a['title']).'</h3>'; ?>
        <?php if ($a['blurb'])  echo '<div class="brevo-form-blurb">'.wp_kses_post(wpautop($a['blurb'])).'</div>'; ?>

        <form class="brevo-form-inner" method="post" novalidate>
            <input type="hidden" name="action" value="brevo_front_subscribe">
            <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce('brevo_nonce') ); ?>">

            <?php if (count($choices) === 1): ?>
            <input type="hidden" name="list_id" value="<?php echo (int)$choices[0]['id']; ?>">
            <?php else: ?>
            <div class="bf-row">
                <label>* List</label>
                <select name="list_id" required>
                <option value="">— choose —</option>
                <?php foreach ($choices as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>"><?php echo esc_html($c['label']); ?></option>
                <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if (in_array('first',$want,true)): ?>
            <div class="bf-row"><label>* First Name</label><input type="text" name="first" required autocomplete="given-name"></div>
            <?php endif; ?>

            <?php if (in_array('last',$want,true)): ?>
            <div class="bf-row"><label>* Last Name</label><input type="text" name="last" required autocomplete="family-name"></div>
            <?php endif; ?>

            <?php if (in_array('phone',$want,true)): ?>
            <div class="bf-row"><label>Contact Phone (Optional)</label><input type="tel" name="phone" autocomplete="tel"></div>
            <?php endif; ?>

            <div class="bf-row"><label>* Email</label><input type="email" name="email" required autocomplete="email"></div>

            <button type="submit" class="bf-submit"><?php echo esc_html($a['button']); ?></button>
            <div class="bf-msg" data-ok="<?php echo esc_attr($a['success']); ?>" data-err="<?php echo esc_attr($a['error']); ?>"></div>
        </form>
        </div>
        <?php
        return ob_get_clean();
    }


}

new BrevoCampaignsPlugin();


