<?php
$current_user = wp_get_current_user();
$support_name = '';
$email = '';
if ($current_user instanceof WP_User && $current_user->ID) {
    $support_name = trim($current_user->first_name . ' ' . $current_user->last_name);
    if ($support_name === '') {
        $support_name = $current_user->display_name;
    }
    $email = $current_user->user_email;
}

$message = '';
$error = '';
if (!empty($_GET['ticket'])) {
    $message = sprintf(__('Your request was received. The ticket number is #%s. A confirmation was sent to your email.', 'cubestheme'), sanitize_text_field(wp_unslash($_GET['ticket'])));
} elseif (!empty($_GET['support_error'])) {
    $error = __('The request could not be sent. Check the required fields and try again.', 'cubestheme');
}

$support_products = class_exists('WSH_Tickets') ? WSH_Tickets::products() : array();
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
                                    value="<?php echo esc_attr($support_name); ?>"
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
                                    value="<?php echo esc_attr($email); ?>"
                                    placeholder="<?php echo esc_attr__('you@example.com', 'cubestheme'); ?>" required>
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
                                        <?php foreach ($support_products as $support_product) : ?>
                                            <option value="<?php echo esc_attr($support_product->ID); ?>">
                                                <?php echo esc_html(get_the_title($support_product)); ?>
                                            </option>
                                        <?php endforeach; ?>
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

                        <?php wp_nonce_field('wsh_support_request', 'wsh_support_nonce'); ?>
                        <input type="hidden" name="wsh_support_request" value="1">

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