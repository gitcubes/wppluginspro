<?php
wp_enqueue_style('blog', get_template_directory_uri() . '/frontend/css/blog.css', array(), themeVersion());

get_header();
?>

<?php
$blog_page_id = get_option('page_for_posts');
$blog_page_url = $blog_page_id ? get_permalink($blog_page_id) : home_url('/blog/');

$blog_hero_label = get_field('blog_hero_label', $blog_page_id);
$blog_hero_title = single_tag_title('', false);
$blog_hero_description = tag_description();

$blog_tags = get_tags([
    'hide_empty' => true,
]);

$default_category_id = (int) get_option('default_category');
$current_tag = get_queried_object();
$paged = get_query_var('paged') ? get_query_var('paged') : 1;

$postArgs = array(
    'posts_per_page' => get_option('posts_per_page'),
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
    'paged'          => $paged,
    'tag_id'         => $current_tag->term_id,
);

$queryResults = new WP_Query($postArgs);

$delays = ['0.12s', '0.18s', '0.24s', '0.3s', '0.36s', '0.42s', '0.48s', '0.54s'];
?>

<section class="hero-section">
    <div class="container">
        <div class="lead-content-holder animation" data-animation="slideUp" data-delay="0.15s">
            <span class="label"><?php printf(esc_html__('Tag', 'cubestheme')); ?></span>

            <h1 class="lead-title"><?php echo esc_html($blog_hero_title); ?></h1>

            <?php if ($blog_hero_description) : ?>
                <div class="lead-description">
                    <?php echo wp_kses_post($blog_hero_description); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="all-blog-posts">
    <div class="container">
        <div class="blog-categories text-center animation" data-animation="slideUp" data-delay="0.08s">
            <div class="categories-tab-nav border pill-tabs">
                <a href="<?php echo esc_url($blog_page_url); ?>" class="categories-tab">
                    <?php printf(esc_html__('All', 'cubestheme')); ?>
                </a>

                <?php if ($blog_tags) : ?>
                    <?php foreach ($blog_tags as $tag) : ?>
                        <a href="<?php echo esc_url(get_tag_link($tag->term_id)); ?>"
                            class="categories-tab <?php echo ((int) $current_tag->term_id === (int) $tag->term_id) ? 'is-active' : ''; ?>">
                            <?php echo esc_html($tag->name); ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="blog-posts">
            <?php if ($queryResults->have_posts()) : ?>
                <?php $index = 0; ?>
                <?php while ($queryResults->have_posts()) : $queryResults->the_post(); ?>
                    <?php
                    $post_id = get_the_ID();
                    $post_categories = get_the_category($post_id);
                    $primary_category = '';

                    if (!empty($post_categories)) {
                        foreach ($post_categories as $post_category) {
                            if ((int) $post_category->term_id === $default_category_id || $post_category->slug === 'uncategorized') {
                                continue;
                            }

                            $primary_category = $post_category->name;
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

                                <?php if ($primary_category) : ?>
                                    <div class="caption">
                                        <p><?php echo esc_html($primary_category); ?></p>
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

                    <?php $index++; ?>
                <?php endwhile; ?>
            <?php else : ?>
                <p><?php printf(esc_html__('No posts found.', 'cubestheme')); ?></p>
            <?php endif; ?>

            <?php wp_reset_postdata(); ?>
        </div>

        <?php
        $pagination_base = trailingslashit(get_tag_link($current_tag->term_id)) . user_trailingslashit('page/%#%/', 'paged');
        ?>

        <?php if ($queryResults->max_num_pages > 1) : ?>
            <nav class="navigation pagination animation" data-animation="slideUp" data-delay="0.18s" aria-label="Posts">
                <div class="nav-links">
                    <?php
                    echo paginate_links([
                        'base'      => $pagination_base,
                        'format'    => '',
                        'current'   => max(1, $paged),
                        'total'     => $queryResults->max_num_pages,
                        'prev_text' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="#1E7BFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" /></svg>',
                        'next_text' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 18L15 12L9 6" stroke="#1E7BFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" /></svg>',
                    ]);
                    ?>
                </div>
            </nav>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>