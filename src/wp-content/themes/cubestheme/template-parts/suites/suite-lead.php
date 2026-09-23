<?php
$suites_page_hero_label = get_field('suites_page_hero_label');
$suites_page_hero_title = get_field('suites_page_hero_title');
$suites_page_hero_description = get_field('suites_page_hero_description');
?>

<section class="hero-section">
    <div class="container">
        <div class="lead-content-holder animation" data-animation="slideUp" data-delay="0.15s">
            <?php if ($suites_page_hero_label) : ?>
                <span class="label"><?php echo esc_html($suites_page_hero_label); ?></span>
            <?php endif; ?>

            <?php if ($suites_page_hero_title) : ?>
                <h1 class="lead-title"><?php echo esc_html($suites_page_hero_title); ?></h1>
            <?php endif; ?>

            <?php if ($suites_page_hero_description) : ?>
                <p class="lead-description">
                    <?php echo esc_html($suites_page_hero_description); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>