<?php
$plugin_catalog_label = get_field('plugin_catalog_label');
$plugin_catalog_title = get_field('plugin_catalog_title');

$paged = get_query_var('paged') ? get_query_var('paged') : 1;

$plugin_categories = get_terms([
    'taxonomy'   => 'plugin_category',
    'hide_empty' => true,
]);

$plugins_query = new WP_Query([
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'post_parent'    => 0,
    'posts_per_page' => 12,
    'paged'          => $paged,
    'meta_key'       => 'wsh_show_in_catalog',
    'meta_value'     => '1',
]);

$delays = ['0.12s', '0.18s', '0.24s', '0.3s', '0.36s', '0.42s'];
?>

<section class="plugin-catalog">
    <div class="container">
        <div class="top-section animation" data-animation="slideUp" data-delay="0.1s">
            <?php if ($plugin_catalog_label) : ?>
                <span class="label"><?php echo esc_html($plugin_catalog_label); ?></span>
            <?php endif; ?>

            <?php if ($plugin_catalog_title) : ?>
                <h2><?php echo esc_html($plugin_catalog_title); ?></h2>
            <?php endif; ?>
        </div>

        <div class="catalog-browser animation" data-animation="slideUp" data-delay="0.1s" data-catalog-tabs>
            <div class="catalog-switcher border pill-tabs" role="tablist" aria-label="Plugin catalog">
                <button class="catalog-tab is-active" type="button" role="tab" id="catalog-tab-all"
                    aria-controls="catalog-panels" aria-selected="true" tabindex="0" data-catalog-trigger="all">
                    <?php printf(esc_html__('All plugins', 'cubestheme')); ?>
                </button>

                <?php if ($plugin_categories && !is_wp_error($plugin_categories)) : ?>
                    <?php foreach ($plugin_categories as $category) : ?>
                        <button class="catalog-tab" type="button" role="tab"
                            id="catalog-tab-<?php echo esc_attr($category->slug); ?>" aria-controls="catalog-panels"
                            aria-selected="false" tabindex="-1" data-catalog-trigger="<?php echo esc_attr($category->slug); ?>">
                            <?php echo esc_html($category->name); ?>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="catalog-grid" id="catalog-panels">
                <?php if ($plugins_query->have_posts()) : ?>
                    <?php $index = 0; ?>
                    <?php while ($plugins_query->have_posts()) : $plugins_query->the_post(); ?>
                        <?php
                        $plugin_id = get_the_ID();

                        $plugin_icon = get_field('plugin_icon', $plugin_id);
                        $plugin_short_description = get_field('plugin_short_description', $plugin_id);
                        $plugin_badges = get_field('plugin_badges', $plugin_id);
                        $plugin_features = get_field('plugin_features', $plugin_id);
                        $plugin_ideal_for = get_field('plugin_ideal_for', $plugin_id);

                        $plugin_bottom_button_text = get_field('plugin_bottom_button_text', $plugin_id);
                        $plugin_bottom_button_url = get_field('plugin_bottom_button_url', $plugin_id);

                        $plugin_button_text = get_field('plugin_button_text', $plugin_id);
                        $landing_id = (int) get_post_meta($plugin_id, 'wsh_landing_page_id', true);
                        $plugin_single_url = $landing_id > 0 ? get_permalink($landing_id) : get_permalink($plugin_id);

                        if (!$plugin_button_text) {
                            $plugin_button_text = 'View plugin';
                        }

                        $terms = get_the_terms($plugin_id, 'plugin_category');
                        $term_slugs = [];

                        if ($terms && !is_wp_error($terms)) {
                            foreach ($terms as $term) {
                                $term_slugs[] = $term->slug;
                            }
                        }

                        $panel_value = !empty($term_slugs) ? implode(' ', $term_slugs) : 'uncategorized';
                        $delay = isset($delays[$index]) ? $delays[$index] : '0.12s';
                        ?>

                        <article class="plugin-card box is-active animation" data-animation="slideUp"
                            data-delay="<?php echo esc_attr($delay); ?>"
                            id="catalog-card-<?php echo esc_attr($post->post_name); ?>" aria-labelledby="catalog-tab-all"
                            data-catalog-panel="<?php echo esc_attr($panel_value); ?>">
                            <div class="content">
                                <div class="d-flex align-items-center plugin-card-header">
                                    <div class="border">
                                        <div class="image-holder">
                                            <?php if ($plugin_icon) : ?>
                                                <?php echo wp_get_attachment_image($plugin_icon, 'full'); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div>
                                        <?php if ($plugin_badges) : ?>
                                            <div class="d-flex align-items-center license-badges">
                                                <?php foreach ($plugin_badges as $badge) : ?>
                                                    <?php
                                                    $badge_text = $badge['badge_text'] ?? '';
                                                    $badge_class = sanitize_title($badge_text);
                                                    ?>
                                                    <?php if ($badge_text) : ?>
                                                        <strong class="<?php echo esc_attr($badge_class); ?>">
                                                            <?php echo esc_html($badge_text); ?>
                                                        </strong>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <h4><?php echo esc_html(get_the_title($plugin_id)); ?></h4>
                                    </div>
                                </div>

                                <?php if ($plugin_short_description) : ?>
                                    <p><?php echo esc_html($plugin_short_description); ?></p>
                                <?php endif; ?>

                                <?php if ($plugin_features) : ?>
                                    <div class="feature-list">
                                        <ul class="list-unstyled">
                                            <?php foreach ($plugin_features as $feature) : ?>
                                                <?php $feature_text = $feature['feature_text'] ?? ''; ?>

                                                <?php if ($feature_text) : ?>
                                                    <li>
                                                        <img src="<?php echo esc_url(get_template_directory_uri() . '/frontend/img/homepage/check-circle-blue.svg'); ?>"
                                                            alt="">
                                                        <span><?php echo esc_html($feature_text); ?></span>
                                                    </li>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if ($plugin_ideal_for) : ?>
                                    <div class="best-fit">
                                        <p>
                                            <span>Ideal for:</span>
                                            <?php echo esc_html($plugin_ideal_for); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <div class="cta d-flex align-items-center">
                                    <?php if ($plugin_bottom_button_text && $plugin_bottom_button_url) : ?>
                                        <a href="<?php echo esc_url($plugin_bottom_button_url); ?>" class="plugin-category">
                                            <?php echo esc_html($plugin_bottom_button_text); ?>
                                        </a>
                                    <?php endif; ?>

                                    <a href="<?php echo esc_url($plugin_single_url); ?>" class="btn btn-primary">
                                        <?php echo esc_html($plugin_button_text); ?>
                                    </a>
                                </div>
                            </div>
                        </article>

                        <?php $index++; ?>
                    <?php endwhile; ?>
                    <?php wp_reset_postdata(); ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($plugins_query->max_num_pages > 1) : ?>
            <nav class="navigation pagination animation" data-animation="slideUp" data-delay="0.2s" aria-label="Posts">
                <div class="nav-links">
                    <?php
                    echo paginate_links([
                        'base'      => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                        'format'    => '?paged=%#%',
                        'current'   => max(1, $paged),
                        'total'     => $plugins_query->max_num_pages,
                        'prev_text' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M15 18L9 12L15 6" stroke="#1E7BFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" /></svg>',
                        'next_text' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 18L15 12L9 6" stroke="#1E7BFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" /></svg>',
                    ]);
                    ?>
                </div>
            </nav>
        <?php endif; ?>
    </div>
</section>