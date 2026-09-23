<?php
$error = $args['error'] ?? '';
$first_name = $args['first_name'] ?? '';
$last_name = $args['last_name'] ?? '';
$company = $args['company'] ?? '';
$email = $args['email'] ?? '';
$legal_url = cubestheme_legal_page_url();
?>
<section class="hero-section pricing">
    <div class="container">
        <div class="lead-content-holder">
            <h1 class="lead-title"><?php esc_html_e('Create your account', 'cubestheme'); ?></h1>
            <p class="lead-description">
                <?php esc_html_e('One account for all premium plugins, suites and support. Already have an account?', 'cubestheme'); ?>
                <a href="<?php echo esc_url(cubestheme_auth_page_url('login')); ?>"><?php esc_html_e('Sign in here.', 'cubestheme'); ?></a>
            </p>
        </div>

        <form method="post" action="<?php echo esc_url(cubestheme_auth_page_url('register')); ?>" class="box">
            <div class="content">
                <?php if ($error !== '') : ?>
                    <p class="auth-notice is-error" role="alert"><?php echo wp_kses_post($error); ?></p>
                <?php endif; ?>

                <?php wp_nonce_field('cubestheme_register', 'cubestheme_register_nonce'); ?>
                <input type="hidden" name="cubestheme_register" value="1">

                <div class="form-group flex-group">
                    <div>
                        <label for="first_name"><?php esc_html_e('First name', 'cubestheme'); ?></label>
                        <input type="text" name="first_name" id="first_name" placeholder="<?php esc_attr_e('First Name', 'cubestheme'); ?>" class="form-control" value="<?php echo esc_attr($first_name); ?>" autocomplete="given-name" required>
                    </div>
                    <div>
                        <label for="last_name"><?php esc_html_e('Last name', 'cubestheme'); ?></label>
                        <input type="text" name="last_name" id="last_name" placeholder="<?php esc_attr_e('Last Name', 'cubestheme'); ?>" class="form-control" value="<?php echo esc_attr($last_name); ?>" autocomplete="family-name" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="d-flex justify-content-between">
                        <label for="company"><?php esc_html_e('Company / Organization', 'cubestheme'); ?></label>
                        <span><?php esc_html_e('Optional', 'cubestheme'); ?></span>
                    </div>
                    <input type="text" name="company" id="company" placeholder="<?php esc_attr_e('Company', 'cubestheme'); ?>" class="form-control" value="<?php echo esc_attr($company); ?>" autocomplete="organization">
                </div>

                <div class="form-group">
                    <label for="email"><?php esc_html_e('Work email', 'cubestheme'); ?></label>
                    <input type="email" name="email" id="email" placeholder="<?php esc_attr_e('Email', 'cubestheme'); ?>" class="form-control" value="<?php echo esc_attr($email); ?>" autocomplete="email" required>
                </div>

                <div class="form-group flex-group">
                    <div>
                        <label for="password"><?php esc_html_e('Password', 'cubestheme'); ?></label>
                        <input type="password" name="password" id="password" placeholder="<?php esc_attr_e('Password', 'cubestheme'); ?>" class="form-control" autocomplete="new-password" required>
                    </div>
                    <div>
                        <label for="password_confirm"><?php esc_html_e('Confirm password', 'cubestheme'); ?></label>
                        <input type="password" name="password_confirm" id="password_confirm" placeholder="<?php esc_attr_e('Password', 'cubestheme'); ?>" class="form-control" autocomplete="new-password" required>
                    </div>
                </div>

                <div class="login-options">
                    <label class="accept-terms">
                        <input type="checkbox" name="accept_terms" value="1">
                        <span class="accept-terms-checkbox">
                            <svg width="10" height="8" viewBox="0 0 10 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M0.75 4.35L3.03571 6.75L8.75 0.75" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span class="accept-terms-text">
                            <?php esc_html_e('I agree that my data will be processed in order to answer this request. Read our', 'cubestheme'); ?>
                            <a href="<?php echo esc_url($legal_url); ?>"><?php esc_html_e('Privacy Policy', 'cubestheme'); ?></a>.
                        </span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary"><?php esc_html_e('Create account', 'cubestheme'); ?></button>

                <p>
                    <?php esc_html_e('You\'ll use this account to:', 'cubestheme'); ?><br>
                    <?php esc_html_e('manage licenses, download premium plugins and talk to support about your production sites.', 'cubestheme'); ?>
                </p>
            </div>
        </form>
    </div>
</section>
