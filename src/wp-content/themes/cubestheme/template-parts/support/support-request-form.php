<?php
global $wpdb;

// Send
$user = "";
$email = "";
$support_name = "";
$support_type = "";
$support_plugin = "";
$support_site = "";
$support_priority = "";
$support_subject = "";
$support_issue = "";
$support_environment = "";
$message = "";
$error = "";
$request_message = "";
$website = get_site_url();

if (isset($_POST) && isset($_POST['send'])) {
    $error = "";
    $status = "failed";
    $message = "";

    $to = "office@cubes.rs";

    $subject = 'Support request from site ' . $website;

    $support_name        = sanitize_text_field($_POST['support_name']);
    $email               = sanitize_text_field($_POST['support_email']);
    $support_type        = sanitize_text_field($_POST['support_type']);
    $support_plugin      = sanitize_text_field($_POST['support_plugin']);
    $support_site        = sanitize_text_field($_POST['support_site']);
    $support_priority    = sanitize_text_field($_POST['support_priority']);
    $support_subject     = sanitize_text_field($_POST['support_subject']);
    $request_message     = sanitize_textarea_field($_POST['support_issue']);
    $support_environment = sanitize_textarea_field($_POST['support_environment']);
    $support_privacy     = isset($_POST['support_privacy']) ? sanitize_text_field($_POST['support_privacy']) : '';

    $created = date("Y-m-d H:i:s");

    // ReCaptcha Block
    $response = null;

    $reCaptcha = new Cubestheme_Recaptcha();

    if ($_POST["g-recaptcha-response"]) {
        $response = $reCaptcha->verifyResponse(
            $_SERVER["REMOTE_ADDR"],
            $_POST["g-recaptcha-response"]
        );
    }
    // End recaptcha

    if (($response != null && $response->success)) {

        $body = "New support request from site $website:<br /><br />";
        $body .= "Support Form:<br /><br />";
        $body .= "Name: $support_name<br />";
        $body .= "Email: $email<br />";
        $body .= "Support type: $support_type<br />";
        $body .= "Plugin / Suite: $support_plugin<br />";
        $body .= "Site URL / domain: " . (!empty($support_site) ? $support_site : 'Not provided') . "<br />";
        $body .= "Priority: $support_priority<br />";
        $body .= "Subject: $support_subject<br />";
        $body .= "Issue: " . nl2br(esc_html($request_message)) . "<br />";
        $body .= "Environment / logs: " . (!empty($support_environment) ? nl2br(esc_html($support_environment)) : 'Not provided') . "<br />";
        $body .= "Privacy consent: " . (!empty($support_privacy) ? 'Yes' : 'No') . "<br />";
        $body .= "Created: $created<br />";

        function wpse27856_set_content_type()
        {
            return "text/html";
        }

        add_filter('wp_mail_content_type', 'wpse27856_set_content_type');

        $headers = array(
            "Content-Type: text/html; charset=UTF-8",
            "From: $to",
            "Reply-To: $support_name <$email>"
        );

        wp_mail($to, $subject, $body, $headers);

        $status = "done";
        $message = __("Your message has been sent!", 'cubestheme');

        remove_filter('phpmailer_init', 'mailer_config');
    } else {
        $error = __("Please verify that you are not robot.", "cubestheme");
    }
}
?>

<?php
$support_hero_label = get_field('support_hero_label');
$support_hero_title = get_field('support_hero_title');
$support_hero_description = get_field('support_hero_description');

$support_form_title = get_field('support_form_title');
$support_form_description = get_field('support_form_description');
$support_form_submit_note = get_field('support_form_submit_note');

$support_guide_panel_title = get_field('support_guide_panel_title');
$support_guide_panel_description = get_field('support_guide_panel_description');

$support_info_card_delays = ['0.12s', '0.2s', '0.28s', '0.36s', '0.44s'];
?>

<section class="support-request">
    <div class="container">
        <div class="support-layout">
            <div class="support-request-panel box animation" data-animation="slideUp" data-delay="0.12s">
                <div class="content">
                    <div class="support-panel-intro">
                        <?php if ($support_form_title) : ?>
                            <h2><?php echo esc_html($support_form_title); ?></h2>
                        <?php endif; ?>

                        <?php if ($support_form_description) : ?>
                            <p><?php echo esc_html($support_form_description); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($message)) : ?>
                        <p class="form-success-message"><?php echo esc_html($message); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($error)) : ?>
                        <p class="form-error-message"><?php echo esc_html($error); ?></p>
                    <?php endif; ?>

                    <form action="" class="support-request-form" method="POST">
                        <div class="support-field-grid">
                            <div class="support-field">
                                <div class="support-field-head">
                                    <label
                                        for="support-name"><?php printf(esc_html__('Your name', 'cubestheme')); ?></label>
                                </div>
                                <input type="text" id="support-name" name="support_name"
                                    placeholder="<?php echo esc_attr__('Ana', 'cubestheme'); ?>" required>
                                <div class="error"></div>
                            </div>

                            <div class="support-field">
                                <div class="support-field-head">
                                    <label for="support-email">
                                        <?php printf(esc_html__('Email', 'cubestheme')); ?>
                                        <span class="support-label-note">
                                            <?php printf(esc_html__('(Use the email you purchased with)', 'cubestheme')); ?>
                                        </span>
                                    </label>
                                </div>
                                <input type="email" id="support-email" name="support_email"
                                    placeholder="<?php echo esc_attr__('Markovic', 'cubestheme'); ?>" required>
                                <div class="error"></div>
                            </div>

                            <div class="support-field">
                                <div class="support-field-head">
                                    <label
                                        for="support-type"><?php printf(esc_html__('What do you need help with?', 'cubestheme')); ?></label>
                                </div>
                                <div class="support-select-field">
                                    <select id="support-type" name="support_type" required>
                                        <option value="" selected>
                                            <?php printf(esc_html__('Select type...', 'cubestheme')); ?></option>
                                        <option value="technical">
                                            <?php printf(esc_html__('Technical issue', 'cubestheme')); ?></option>
                                        <option value="billing">
                                            <?php printf(esc_html__('Billing question', 'cubestheme')); ?></option>
                                        <option value="pre-sales">
                                            <?php printf(esc_html__('Pre-sales question', 'cubestheme')); ?></option>
                                    </select>
                                    <span class="support-select-icon" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12.6673 6L8.00065 10.6667L3.33398 6" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="error"></div>
                            </div>

                            <div class="support-field">
                                <div class="support-field-head">
                                    <label
                                        for="support-plugin"><?php printf(esc_html__('Which plugin or suite?', 'cubestheme')); ?></label>
                                </div>
                                <div class="support-select-field">
                                    <select id="support-plugin" name="support_plugin" required>
                                        <option value="" selected>
                                            <?php printf(esc_html__('Select...', 'cubestheme')); ?></option>
                                        <option value="views-counter-pro">
                                            <?php printf(esc_html__('WSH Views Counter PRO', 'cubestheme')); ?></option>
                                        <option value="editor-enhancer">
                                            <?php printf(esc_html__('WSH Editor Enhancer', 'cubestheme')); ?></option>
                                        <option value="news-suite">
                                            <?php printf(esc_html__('News Portal Suite', 'cubestheme')); ?></option>
                                        <option value="woo-suite">
                                            <?php printf(esc_html__('WooCommerce Growth Suite', 'cubestheme')); ?>
                                        </option>
                                    </select>
                                    <span class="support-select-icon" aria-hidden="true">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12.6673 6L8.00065 10.6667L3.33398 6" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="error"></div>
                            </div>

                            <div class="support-field">
                                <div class="support-field-head">
                                    <label for="support-site">
                                        <?php printf(esc_html__('Site URL / domain', 'cubestheme')); ?>
                                        <span class="support-label-note">
                                            <?php printf(esc_html__('(Optional, but very helpful)', 'cubestheme')); ?>
                                        </span>
                                    </label>
                                </div>
                                <input type="url" id="support-site" name="support_site"
                                    placeholder="<?php echo esc_attr__('https://www.yoursite.com', 'cubestheme'); ?>">
                                <div class="error"></div>
                            </div>

                            <div class="support-field">
                                <div class="support-field-head">
                                    <label>
                                        <?php printf(esc_html__('Priority', 'cubestheme')); ?>
                                        <span class="support-label-note">
                                            <?php printf(esc_html__('(Be honest - this helps everyone)', 'cubestheme')); ?>
                                        </span>
                                    </label>
                                </div>

                                <div class="support-priority-group">
                                    <label class="support-priority-option">
                                        <input type="radio" name="support_priority" value="normal" checked>
                                        <span class="support-priority-indicator">
                                            <svg width="10" height="10" viewBox="0 0 12 12" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path d="M2.5 6L4.8 8.3L9.5 3.5" stroke="white" stroke-width="1.6"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                        <span
                                            class="support-priority-text"><?php printf(esc_html__('Normal', 'cubestheme')); ?></span>
                                    </label>

                                    <label class="support-priority-option">
                                        <input type="radio" name="support_priority" value="high">
                                        <span class="support-priority-indicator">
                                            <svg width="10" height="10" viewBox="0 0 12 12" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path d="M2.5 6L4.8 8.3L9.5 3.5" stroke="white" stroke-width="1.6"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                        <span
                                            class="support-priority-text"><?php printf(esc_html__('High (site broken)', 'cubestheme')); ?></span>
                                    </label>
                                </div>

                                <div class="error"></div>
                            </div>

                            <div class="support-field support-field-wide">
                                <div class="support-field-head">
                                    <label
                                        for="support-subject"><?php printf(esc_html__('Subject', 'cubestheme')); ?></label>
                                </div>
                                <input type="text" id="support-subject" name="support_subject"
                                    placeholder="<?php echo esc_attr__('Example: Views not counting on WooCommerce product pages', 'cubestheme'); ?>"
                                    required>
                                <div class="error"></div>
                            </div>

                            <div class="support-field support-field-wide">
                                <div class="support-field-head support-field-head-split">
                                    <label
                                        for="support-issue"><?php printf(esc_html__('Describe the issue', 'cubestheme')); ?></label>
                                    <span class="support-field-hint">
                                        <?php printf(esc_html__('Steps to reproduce, what you expected, what you got', 'cubestheme')); ?>
                                    </span>
                                </div>
                                <textarea id="support-issue" name="support_issue" rows="5"
                                    placeholder="<?php echo esc_attr__("1) Go to ...\n2) Click on...\n3) Expected result:\n4) Actual result:", 'cubestheme'); ?>"
                                    required></textarea>
                                <div class="error"></div>
                            </div>

                            <div class="support-field support-field-wide">
                                <div class="support-field-head support-field-head-split">
                                    <label
                                        for="support-environment"><?php printf(esc_html__('Environment / logs', 'cubestheme')); ?></label>
                                    <span
                                        class="support-field-hint"><?php printf(esc_html__('Optional', 'cubestheme')); ?></span>
                                </div>
                                <textarea id="support-environment" name="support_environment" rows="6"
                                    placeholder="<?php echo esc_attr__('WordPress version, PHP version, plugin version, theme...', 'cubestheme'); ?>"></textarea>
                                <div class="error"></div>
                            </div>
                        </div>

                        <div class="support-consent-wrap">
                            <label class="support-consent">
                                <input type="checkbox" name="support_privacy" value="1" required>
                                <span class="support-consent-indicator">
                                    <svg width="10" height="10" viewBox="0 0 12 12" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path d="M2.5 6L4.8 8.3L9.5 3.5" stroke="white" stroke-width="1.6"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                                <span class="support-consent-text">
                                    <?php printf(esc_html__('I agree that my data will be processed in order to answer this request. Read our Privacy Policy.', 'cubestheme')); ?>
                                </span>
                            </label>
                            <div class="error"></div>
                        </div>

                        <input type="hidden" name="send" value="1">

                        <button type="submit" class="btn btn-primary">
                            <?php printf(esc_html__('Submit Request', 'cubestheme')); ?>
                        </button>

                        <?php if ($support_form_submit_note) : ?>
                            <p class="support-submit-note">
                                <?php echo esc_html($support_form_submit_note); ?>
                            </p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <aside class="support-guide-panel box animation" data-animation="slideUp" data-delay="0.18s">
                <div class="content">
                    <div class="support-panel-intro">
                        <?php if ($support_guide_panel_title) : ?>
                            <h3><?php echo esc_html($support_guide_panel_title); ?></h3>
                        <?php endif; ?>

                        <?php if ($support_guide_panel_description) : ?>
                            <p><?php echo esc_html($support_guide_panel_description); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if (have_rows('support_guide_cards')) : ?>

                        <div class="support-guide-list">
                            <?php while (have_rows('support_guide_cards')) : the_row(); ?>
                                <?php
                                $title = get_sub_field('title');
                                $content = get_sub_field('content');
                                ?>

                                <div class="support-guide-card">
                                    <?php if ($title) : ?>
                                        <h4><?php echo esc_html($title); ?></h4>
                                    <?php endif; ?>

                                    <?php if ($content) : ?>
                                        <?php echo wp_kses_post($content); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </aside>
        </div>

        <div class="card-grid">
            <?php if (have_rows('support_info_cards')) : ?>
                <?php $index = 0; ?>
                <?php while (have_rows('support_info_cards')) : the_row(); ?>
                    <?php
                    $icon = get_sub_field('icon');
                    $title = get_sub_field('title');
                    $content = get_sub_field('content');
                    $delay = isset($support_info_card_delays[$index]) ? $support_info_card_delays[$index] : '0.12s';
                    ?>

                    <div class="single-box box animation" data-animation="slideUp" data-delay="<?php echo esc_attr($delay); ?>">
                        <div class="content">
                            <div class="border">
                                <div class="image-holder">
                                    <?php if ($icon) : ?>
                                        <span><?php echo esc_html($icon); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($title) : ?>
                                <h4><?php echo esc_html($title); ?></h4>
                            <?php endif; ?>

                            <?php if ($content) : ?>
                                <?php echo wp_kses_post($content); ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php $index++; ?>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</section>