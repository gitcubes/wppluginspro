<?php

get_header(null, [
    'force_scrolled_header' => true,
]);

?>
<section class="static-page commerce-page">
    <div class="container">
        <?php woocommerce_breadcrumb(); ?>
        <h1 class="page-title"><?php woocommerce_page_title(); ?></h1>
        <div class="content">
            <?php if (woocommerce_product_loop()) : ?>
                <?php do_action('woocommerce_before_shop_loop'); ?>
                <?php woocommerce_product_loop_start(); ?>
                <?php while (have_posts()) : ?>
                    <?php the_post(); ?>
                    <?php do_action('woocommerce_shop_loop'); ?>
                    <?php wc_get_template_part('content', 'product'); ?>
                <?php endwhile; ?>
                <?php woocommerce_product_loop_end(); ?>
                <?php do_action('woocommerce_after_shop_loop'); ?>
            <?php else : ?>
                <?php do_action('woocommerce_no_products_found'); ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php

get_footer();
