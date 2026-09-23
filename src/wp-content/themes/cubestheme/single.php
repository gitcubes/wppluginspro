<?php

wp_enqueue_style('fancy', get_template_directory_uri() . '/frontend/css/jquery.fancybox.min.css', array(), themeVersion());
wp_enqueue_style('blog-single', get_template_directory_uri() . '/frontend/css/blog-single.css', array(), themeVersion());

get_header();
?>

<?php if (have_posts()) : ?>
    <?php while (have_posts()) : the_post(); ?>
        <?php
        $post_id = get_the_ID();
        $default_category_id = (int) get_option('default_category');

        $post_categories = get_the_category($post_id);
        $primary_category = '';
        $listOfCategories = array();

        if (!empty($post_categories)) {
            foreach ($post_categories as $post_category) {
                if ((int) $post_category->term_id === $default_category_id || $post_category->slug === 'uncategorized') {
                    continue;
                }

                if (!$primary_category) {
                    $primary_category = $post_category->name;
                }

                $listOfCategories[] = (int) $post_category->term_id;
            }
        }

        $current_post_url = get_permalink();
        $current_post_title = get_the_title();

        $single_short_description = get_field('single_short_description');

        ?>

        <section class="hero-section single-blog">
            <div class="lead-bg">
                <div class="container">
                    <div class="lead-content-holder animation" data-animation="slideUp" data-delay="0.15s">
                        <div class="labels">
                            <span class="label"><?php printf(esc_html__('Blog', 'cubestheme')); ?></span>

                            <?php if ($primary_category) : ?>
                                <span class="category"><?php echo esc_html($primary_category); ?></span>
                            <?php endif; ?>
                        </div>

                        <h1 class="lead-title"><?php the_title(); ?></h1>

                        <?php if ($single_short_description) : ?>
                            <p class="lead-description">
                                <?php echo esc_html($single_short_description); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="blog-content">
                <div class="container">
                    <div class="content-container">
                        <?php if (has_post_thumbnail()) : ?>
                            <figure class="lead-img">
                                <div class="img-placeholder">
                                    <?php the_post_thumbnail('full'); ?>
                                </div>
                            </figure>
                        <?php endif; ?>

                        <div class="content">
                            <?php the_content(); ?>
                        </div>

                        <div class="share">
                            <span><?php printf(esc_html__('Share this article', 'cubestheme')); ?></span>

                            <div class="socials">
                                <a href="#" aria-label="<?php printf(esc_attr__('Copy link', 'cubestheme')); ?>"
                                    class="copy-link">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M12.7076 18.3639L11.2933 19.7781C9.34072 21.7308 6.1749 21.7308 4.22228 19.7781C2.26966 17.8255 2.26966 14.6597 4.22228 12.7071L5.63649 11.2929M18.3644 12.7071L19.7786 11.2929C21.7312 9.34024 21.7312 6.17441 19.7786 4.22179C17.826 2.26917 14.6602 2.26917 12.7076 4.22179L11.2933 5.636M8.50045 15.4999L15.5005 8.49994"
                                            stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>

                                    <div class="d-none">
                                        <input type="text" class="copy-url" name="copy_url"
                                            value="<?php echo esc_url($current_post_url); ?>">
                                    </div>

                                    <div class="tooltip">
                                        <?php printf(esc_html__('Copied', 'cubestheme')); ?>
                                    </div>
                                </a>

                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode($current_post_url); ?>"
                                    target="_blank" rel="noopener noreferrer"
                                    aria-label="<?php printf(esc_attr__('Share on Facebook', 'cubestheme')); ?>">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M22 12.0609C22 17.0913 18.3306 21.2698 13.5323 22V14.9817H15.871L16.3145 12.0609H13.5323V10.1947C13.5323 9.38337 13.9355 8.61258 15.1855 8.61258H16.4355V6.13793C16.4355 6.13793 15.3065 5.93509 14.1774 5.93509C11.9194 5.93509 10.4274 7.35497 10.4274 9.87018V12.0609H7.8871V14.9817H10.4274V22C5.62903 21.2698 2 17.0913 2 12.0609C2 6.50304 6.47581 2 12 2C17.5242 2 22 6.50304 22 12.0609Z"
                                            fill="white" />
                                    </svg>
                                </a>

                                <a href="https://twitter.com/intent/tweet?text=<?php echo rawurlencode($current_post_title); ?>&url=<?php echo rawurlencode($current_post_url); ?>"
                                    target="_blank" rel="noopener noreferrer"
                                    aria-label="<?php printf(esc_attr__('Share on X', 'cubestheme')); ?>">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M14.2182 10.4235L20.4353 3H18.0744L13.1948 8.82757L9.45769 3H3L9.53659 13.1939L3 21H5.36091L10.5608 14.7907L14.5423 21H21L14.2182 10.4244V10.4235ZM6.32709 4.90942H8.57815L17.6721 19.0906H15.4211L6.32709 4.90942Z"
                                            fill="white" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php
        if (!empty($listOfCategories)) :
            $args = array(
                'posts_per_page' => 3,
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'orderby'        => 'date',
                'order'          => 'DESC',
                'category__in'   => $listOfCategories,
                'post__not_in'   => array($post_id),
            );

            $relatedPosts = new WP_Query($args);

            if ($relatedPosts->have_posts()) :
        ?>
                <section class="related-posts">
                    <div class="container">
                        <h2><?php printf(esc_html__('You might also like', 'cubestheme')); ?></h2>

                        <div class="related-blog-items">
                            <?php
                            $delays = ['0.12s', '0.18s', '0.24s'];
                            $index = 0;

                            while ($relatedPosts->have_posts()) :
                                $relatedPosts->the_post();

                                $related_post_id = get_the_ID();
                                $related_categories = get_the_category($related_post_id);
                                $related_primary_category = '';

                                if (!empty($related_categories)) {
                                    foreach ($related_categories as $related_category) {
                                        if ((int) $related_category->term_id === $default_category_id || $related_category->slug === 'uncategorized') {
                                            continue;
                                        }

                                        $related_primary_category = $related_category->name;
                                        break;
                                    }
                                }

                                $reading_time = max(1, ceil(str_word_count(wp_strip_all_tags(get_the_content())) / 200));
                                $delay = isset($delays[$index]) ? $delays[$index] : '0.12s';
                            ?>
                                <article class="blog-item box animation" data-animation="slideUp"
                                    data-delay="<?php echo esc_attr($delay); ?>">
                                    <div class="content">
                                        <a href="<?php the_permalink(); ?>" class="blog-item-image img-placeholder">
                                            <?php if (has_post_thumbnail()) : ?>
                                                <?php the_post_thumbnail('full'); ?>
                                            <?php endif; ?>

                                            <?php if ($related_primary_category) : ?>
                                                <div class="caption">
                                                    <p><?php echo esc_html($related_primary_category); ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </a>

                                        <div class="blog-item-data">
                                            <h4>
                                                <a href="<?php the_permalink(); ?>">
                                                    <?php the_title(); ?>
                                                </a>
                                            </h4>

                                            <p>
                                                <?php echo esc_html(wp_trim_words(get_the_excerpt(), 28, '...')); ?>
                                            </p>

                                            <div class="cta d-flex align-items-center justify-content-between">
                                                <span><?php echo esc_html($reading_time); ?> min read</span>
                                                <a href="<?php the_permalink(); ?>" class="btn btn-primary">
                                                    <?php printf(esc_html__('Read article', 'cubestheme')); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php
                                $index++;
                            endwhile;
                            wp_reset_postdata();
                            ?>
                        </div>
                    </div>
                </section>
        <?php
            endif;
            wp_reset_postdata();
        endif;
        ?>

    <?php endwhile; ?>
<?php endif; ?>

<?php get_footer(); ?>