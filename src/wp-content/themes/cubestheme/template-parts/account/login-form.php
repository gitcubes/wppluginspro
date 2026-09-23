<?php
$error = $args['error'] ?? '';
$email = $args['email'] ?? '';
$legal_url = cubestheme_legal_page_url();
?>
<section class="hero-section pricing">
    <div class="container">
        <div class="lead-content-holder">
            <h1 class="lead-title"><?php esc_html_e('Sign in to your account', 'cubestheme'); ?></h1>
            <p class="lead-description">
                <?php esc_html_e('Manage your licenses, downloads and support requests from a single place. Don\'t have an account yet?', 'cubestheme'); ?>
                <a href="<?php echo esc_url(cubestheme_auth_page_url('register')); ?>"><?php esc_html_e('Create one in a minute.', 'cubestheme'); ?></a>
            </p>
        </div>

        <form method="post" action="<?php echo esc_url(cubestheme_auth_page_url('login')); ?>" class="box">
            <div class="content">
                <?php if ($error !== '') : ?>
                    <p class="auth-notice is-error" role="alert"><?php echo esc_html($error); ?></p>
                <?php endif; ?>

                <?php wp_nonce_field('cubestheme_login', 'cubestheme_login_nonce'); ?>
                <input type="hidden" name="cubestheme_login" value="1">
                <?php if (!empty($_GET['redirect_to'])) : ?>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr(wp_unslash($_GET['redirect_to'])); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="email"><?php esc_html_e('Email address', 'cubestheme'); ?></label>
                    <input type="email" name="email" id="email" placeholder="<?php esc_attr_e('Email', 'cubestheme'); ?>" class="form-control" value="<?php echo esc_attr($email); ?>" autocomplete="username" required>
                </div>

                <div class="form-group">
                    <label for="password"><?php esc_html_e('Password', 'cubestheme'); ?></label>
                    <input type="password" name="password" id="password" placeholder="<?php esc_attr_e('Password', 'cubestheme'); ?>" class="form-control" autocomplete="current-password" required>
                </div>

                <div class="login-options">
                    <label class="remember">
                        <input type="checkbox" name="remember" value="1">
                        <span class="remember-checkbox">
                            <svg width="10" height="8" viewBox="0 0 10 8" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M0.75 4.35L3.03571 6.75L8.75 0.75" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span class="remember-text"><?php esc_html_e('Keep me signed in on this device', 'cubestheme'); ?></span>
                    </label>

                    <a href="<?php echo esc_url(cubestheme_lost_password_url()); ?>" class="forgot-password"><?php esc_html_e('Forgot password?', 'cubestheme'); ?></a>
                </div>

                <button type="submit" class="btn btn-primary"><?php esc_html_e('Sign in', 'cubestheme'); ?></button>

                <p>
                    <?php esc_html_e('Logging in means you accept our', 'cubestheme'); ?>
                    <a href="<?php echo esc_url($legal_url); ?>"><?php esc_html_e('Terms of Service', 'cubestheme'); ?></a>
                    <?php esc_html_e('and', 'cubestheme'); ?>
                    <a href="<?php echo esc_url($legal_url); ?>"><?php esc_html_e('Privacy Policy.', 'cubestheme'); ?></a>
                </p>
            </div>
        </form>
    </div>
</section>
