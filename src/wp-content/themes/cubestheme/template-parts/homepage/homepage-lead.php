<?php
$hero_image = get_field('hero_image');
$hero_title = get_field('hero_title');
$hero_description = get_field('hero_description');

$hero_primary_button_text = get_field('hero_primary_button_text');
$hero_primary_button_url  = get_field('hero_primary_button_url');

$hero_secondary_button_text = get_field('hero_secondary_button_text');
$hero_secondary_button_url  = get_field('hero_secondary_button_url');

$hero_powered_by_text = get_field('hero_powered_by_text');
$hero_powered_by_logo = get_field('hero_powered_by_logo');
?>

<section class="homepage-hero">
    <?php if ($hero_image) : ?>
        <figure class="hero-visual position-relative animation" data-animation="fadeIn" data-delay="0.1s">
            <?php echo wp_get_attachment_image($hero_image, 'full'); ?>
        </figure>
    <?php endif; ?>

    <div class="container">
        <div class="hero-copy animation" data-animation="slideUp" data-delay="0.15s">

            <?php if ($hero_title) : ?>
                <h1 class="lead-title"><?php echo esc_html($hero_title); ?></h1>
            <?php endif; ?>

            <?php if ($hero_description) : ?>
                <p class="lead-description">
                    <?php echo esc_html($hero_description); ?>
                </p>
            <?php endif; ?>

            <div class="hero-actions">
                <?php if ($hero_primary_button_text && $hero_primary_button_url) : ?>
                    <div class="border">
                        <a href="<?php echo esc_url($hero_primary_button_url); ?>" class="btn btn-white cta-link">
                            <?php echo esc_html($hero_primary_button_text); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($hero_secondary_button_text && $hero_secondary_button_url) : ?>
                    <div class="border">
                        <a href="<?php echo esc_url($hero_secondary_button_url); ?>" class="btn btn-blue cta-link">
                            <?php echo esc_html($hero_secondary_button_text); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="hero-endorsement">
                <?php if ($hero_powered_by_text) : ?>
                    <span class="powered-by-text"><?php echo esc_html($hero_powered_by_text); ?></span>
                <?php endif; ?>

                <?php if ($hero_powered_by_logo) : ?>
                    <?php echo wp_get_attachment_image($hero_powered_by_logo, 'full'); ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</section>