<?php
$terms_hero_label = get_field('terms_hero_label');
$terms_hero_description = get_field('terms_hero_description');
?>

<section class="hero-section">
    <div class="container">
        <div class="lead-content-holder animation" data-animation="slideUp" data-delay="0.15s">
            <?php if ($terms_hero_label) : ?>
                <span class="label"><?php echo esc_html($terms_hero_label); ?></span>
            <?php endif; ?>

            <h1 class="lead-title"><?php the_title(); ?></h1>

            <?php if ($terms_hero_description) : ?>
                <p class="lead-description">
                    <?php echo esc_html($terms_hero_description); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>