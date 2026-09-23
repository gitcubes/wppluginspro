<!-- blog item start -->
<article class="blog-item">
    <?php
    $categoryList = get_the_category(get_the_ID());
    foreach ($categoryList as $category) {
    ?>
        <a href="<?php echo get_category_link($category->term_id); ?>" class="category">
            <?php echo get_cat_name($category->term_id); ?>
        </a>
    <?php
    }
    ?>

    <a href="<?php the_permalink(); ?>" class="blog-item-picture img-placeholder">
        <?php the_post_thumbnail($imageSize); ?>

    </a>
    <h2 class="blog-item-title">
        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
    </h2>
    <p class="short-description">
        <?php the_field('short_description'); ?>
    </p>
    <p class="full-date"><?php echo get_the_date(); ?></p>
</article><!-- blog item end -->