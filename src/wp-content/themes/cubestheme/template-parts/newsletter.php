<?php
$homepage_id = get_option('page_on_front');

$newsletter_label = get_field('newsletter_label', $homepage_id);
$newsletter_title = get_field('newsletter_title', $homepage_id);
$newsletter_button_text = get_field('newsletter_button_text', $homepage_id);
$newsletter_lottie_file = get_field('newsletter_lottie_file', $homepage_id);
?>

<section class="newsletter" id="newsletter-signup">
    <div class="container">
        <div class="box animation" data-animation="slideUp" data-delay="0.08s">
            <div class="content">
                <div>
                    <?php if ($newsletter_label) : ?>
                        <span class="label">
                            <?php echo esc_html($newsletter_label); ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($newsletter_title) : ?>
                        <h2><?php echo esc_html($newsletter_title); ?></h2>
                    <?php endif; ?>

                    <form action="#newsletter-signup" method="post" class="newsletter-form" aria-label="Newsletter signup form">
                        <input type="hidden" name="newsletter_signup" value="1">
                        <div class="form-group">
                            <div class="newsletter-field">
                                <div class="border">
                                    <input type="email" class="form-control" name="newsletter_email" placeholder="you@example.com"
                                        aria-label="Email address" autocomplete="email" inputmode="email" required>
                                </div>
                                <div class="error"></div>
                            </div>

                            <div class="border">
                                <button type="submit" class="btn btn-white">
                                    <?php echo esc_html($newsletter_button_text); ?>
                                </button>
                            </div>
                        </div>
                        <?php if (function_exists('cubestheme_newsletter_notice')) { cubestheme_newsletter_notice(); } ?>
                    </form>
                </div>

                <?php if ($newsletter_lottie_file) : ?>
                    <div class="lottie d-none d-xl-block">
                        <lottie-player src="<?php echo esc_url($newsletter_lottie_file); ?>" background="transparent"
                            speed="1" preserveAspectRatio="xMaxYMid meet" loop autoplay>
                        </lottie-player>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</section>