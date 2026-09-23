<?php
$blog_page_id = get_option('page_for_posts');

$blog_hero_label = get_field('blog_hero_label', $blog_page_id);
$blog_hero_title = get_field('blog_hero_title', $blog_page_id);
$blog_hero_description = get_field('blog_hero_description', $blog_page_id);
?>

<section class="hero-section">
    <div class="container">
        <div class="lead-content-holder animation" data-animation="slideUp" data-delay="0.15s">
            <?php if ($blog_hero_label) : ?>
                <span class="label"><?php echo esc_html($blog_hero_label); ?></span>
            <?php endif; ?>

            <?php if ($blog_hero_title) : ?>
                <h1 class="lead-title"><?php echo esc_html($blog_hero_title); ?></h1>
            <?php endif; ?>

            <?php if ($blog_hero_description) : ?>
                <p class="lead-description">
                    <?php echo esc_html($blog_hero_description); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>