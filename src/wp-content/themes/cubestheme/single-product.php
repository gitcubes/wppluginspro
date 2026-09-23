<?php

get_header(null, [
    'force_scrolled_header' => true,
]);

?>
<section class="static-page commerce-page">
    <div class="container">
        <?php woocommerce_breadcrumb(); ?>
        <div class="content">
            <?php while (have_posts()) : ?>
                <?php the_post(); ?>
                <?php wc_get_template_part('content', 'single-product'); ?>
            <?php endwhile; ?>
        </div>
    </div>
</section>
<?php

get_footer();
