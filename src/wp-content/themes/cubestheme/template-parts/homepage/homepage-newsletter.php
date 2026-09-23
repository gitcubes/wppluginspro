<?php
$newsletter_label = get_field('newsletter_label');
$newsletter_title = get_field('newsletter_title');
$newsletter_button_text = get_field('newsletter_button_text');
$newsletter_lottie_file = get_field('newsletter_lottie_file');
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

                    <form action="" method="post" aria-label="Newsletter signup form">
                        <div class="form-group">
                            <div class="border">
                                <input type="email" class="form-control" name="email" placeholder="you@example.com"
                                    aria-label="Email address" autocomplete="email" inputmode="email" required>
                            </div>

                            <div class="border">
                                <button type="submit" class="btn btn-white">
                                    <?php echo esc_html($newsletter_button_text); ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="lottie">
                    <?php if ($newsletter_lottie_file) : ?>
                        <lottie-player src="<?php echo esc_url($newsletter_lottie_file); ?>" background="transparent"
                            speed="1" preserveAspectRatio="xMaxYMid meet" loop autoplay>
                        </lottie-player>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>