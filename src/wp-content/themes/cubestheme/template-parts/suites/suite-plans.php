<?php
$suite_plans_label = get_field('suite_plans_label');
$suite_plans_title = get_field('suite_plans_title');
$suite_plans_description = get_field('suite_plans_description');

$plan_delays = ['0.1s', '0.18s', '0.26s', '0.34s'];
?>

<section class="suite-plans">
    <div class="container">
        <div class="top-section animation" data-animation="slideUp" data-delay="0.1s">
            <?php if ($suite_plans_label) : ?>
                <span class="label"><?php echo esc_html($suite_plans_label); ?></span>
            <?php endif; ?>

            <?php if ($suite_plans_title) : ?>
                <h2><?php echo esc_html($suite_plans_title); ?></h2>
            <?php endif; ?>

            <?php if ($suite_plans_description) : ?>
                <p><?php echo esc_html($suite_plans_description); ?></p>
            <?php endif; ?>
        </div>

        <?php
        $suite_products = new WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'post_parent'    => 0,
            'posts_per_page' => 12,
            'tax_query'      => [[
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => ['suites'],
            ]],
            'meta_key'       => 'wsh_license_group',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        ]);
        $suite_order = ['news' => 1, 'ecommerce' => 2, 'all' => 3];
        $suite_cards = [];
        if ($suite_products->have_posts()) {
            while ($suite_products->have_posts()) {
                $suite_products->the_post();
                $group = (string) get_post_meta(get_the_ID(), 'wsh_license_group', true);
                $suite_cards[] = [
                    'id' => get_the_ID(),
                    'sort' => $suite_order[$group] ?? 50,
                ];
            }
            wp_reset_postdata();
            usort($suite_cards, function ($a, $b) {
                return $a['sort'] <=> $b['sort'];
            });
        }
        ?>

        <?php if ($suite_cards) : ?>
            <div class="suite-plan-grid">
                <?php $index = 0; ?>
                <?php foreach ($suite_cards as $card) : ?>
                    <?php
                    $suite_id = $card['id'];
                    $suite_product = function_exists('wc_get_product') ? wc_get_product($suite_id) : null;
                    $group = (string) get_post_meta($suite_id, 'wsh_license_group', true);
                    $audiences = [
                        'news' => __('For publishers and media', 'cubestheme'),
                        'ecommerce' => __('For WooCommerce stores', 'cubestheme'),
                        'all' => __('For agencies and every site', 'cubestheme'),
                    ];
                    $family = $group === 'all' ? '' : $group;
                    $included = get_posts([
                        'post_type'      => 'product',
                        'post_status'    => 'publish',
                        'post_parent'    => 0,
                        'posts_per_page' => 20,
                        'meta_query'     => $group === 'all' ? [[
                            'key'     => 'wsh_plugin_family',
                            'value'   => ['news', 'ecommerce'],
                            'compare' => 'IN',
                        ]] : [[
                            'key'   => 'wsh_plugin_family',
                            'value' => $family,
                        ]],
                    ]);
                    $delay = isset($plan_delays[$index]) ? $plan_delays[$index] : '0.1s';
                    ?>
                    <div class="suite-plan-card box animation" data-animation="slideUp" data-delay="<?php echo esc_attr($delay); ?>">
                        <div class="content">
                            <h4><?php echo esc_html(get_the_title($suite_id)); ?></h4>
                            <?php if (isset($audiences[$group])) : ?>
                                <p class="plan-audience"><?php echo esc_html($audiences[$group]); ?></p>
                            <?php endif; ?>
                            <?php if ($suite_product) : ?>
                                <h3><?php echo wp_kses_post($suite_product->get_price_html()); ?></h3>
                            <?php endif; ?>
                            <p><?php echo esc_html(wp_strip_all_tags(get_the_excerpt($suite_id))); ?></p>
                            <?php if ($included) : ?>
                                <div class="included-plugins">
                                    <p><?php esc_html_e('Includes plugins like:', 'cubestheme'); ?></p>
                                    <ul class="list-unstyled">
                                        <?php foreach ($included as $plugin) : ?>
                                            <li>
                                                <img src="<?php echo esc_url(get_template_directory_uri() . '/frontend/img/homepage/check-circle-blue.svg'); ?>" alt="">
                                                <span><?php echo esc_html(get_the_title($plugin)); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <div class="cta">
                                <a href="<?php echo esc_url(get_permalink($suite_id)); ?>" class="btn btn-primary">
                                    <?php esc_html_e('View suite', 'cubestheme'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php $index++; ?>
                <?php endforeach; ?>
            </div>
        <?php elseif (have_rows('suite_plan_items')) : ?>

            <div class="suite-plan-grid">
                <?php $index = 0; ?>
                <?php while (have_rows('suite_plan_items')) : the_row(); ?>
                    <?php
                    $title = get_sub_field('title');
                    $audience = get_sub_field('audience');
                    $description = get_sub_field('description');
                    $guidance = get_sub_field('guidance');
                    $includes_label = get_sub_field('includes_label');

                    $primary_button_text = get_sub_field('primary_button_text');
                    $primary_button_url = get_sub_field('primary_button_url');

                    $secondary_button_text = get_sub_field('secondary_button_text');
                    $secondary_button_url = get_sub_field('secondary_button_url');

                    $delay = isset($plan_delays[$index]) ? $plan_delays[$index] : '0.1s';
                    ?>

                    <div class="suite-plan-card box animation" data-animation="slideUp"
                        data-delay="<?php echo esc_attr($delay); ?>">
                        <div class="content">
                            <?php if ($title) : ?>
                                <h4><?php echo esc_html($title); ?></h4>
                            <?php endif; ?>

                            <?php if ($audience) : ?>
                                <p class="plan-audience"><?php echo esc_html($audience); ?></p>
                            <?php endif; ?>

                            <?php if ($description) : ?>
                                <p><?php echo esc_html($description); ?></p>
                            <?php endif; ?>

                            <?php if ($guidance) : ?>
                                <p class="plan-guidance"><?php echo esc_html($guidance); ?></p>
                            <?php endif; ?>

                            <div class="included-plugins">
                                <?php if ($includes_label) : ?>
                                    <p><?php echo esc_html($includes_label); ?></p>
                                <?php endif; ?>

                                <?php if (have_rows('included_plugins')) : ?>
                                    <ul class="list-unstyled">
                                        <?php while (have_rows('included_plugins')) : the_row(); ?>
                                            <?php $plugin_name = get_sub_field('plugin_name'); ?>

                                            <?php if ($plugin_name) : ?>
                                                <li>
                                                    <img src="<?php echo esc_url(get_template_directory_uri() . '/frontend/img/homepage/check-circle-blue.svg'); ?>"
                                                        alt="">
                                                    <span><?php echo esc_html($plugin_name); ?></span>
                                                </li>
                                            <?php endif; ?>
                                        <?php endwhile; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>

                            <div class="cta">
                                <?php if ($primary_button_text && $primary_button_url) : ?>
                                    <a href="<?php echo esc_url($primary_button_url); ?>" class="btn btn-white">
                                        <?php echo esc_html($primary_button_text); ?>
                                    </a>
                                <?php endif; ?>

                                <?php if ($secondary_button_text && $secondary_button_url) : ?>
                                    <a href="<?php echo esc_url($secondary_button_url); ?>" class="btn btn-primary">
                                        <?php echo esc_html($secondary_button_text); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php $index++; ?>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</section>