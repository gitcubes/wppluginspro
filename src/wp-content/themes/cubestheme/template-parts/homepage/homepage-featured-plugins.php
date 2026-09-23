<?php
$featured_plugins_label = get_field('featured_plugins_label');
$featured_plugins_title = get_field('featured_plugins_title');
$featured_plugins_description = get_field('featured_plugins_description');
$featured_plugins = get_field('featured_plugins');

$featured_plugins_footer_button_text = get_field('featured_plugins_footer_button_text');
$featured_plugins_footer_button_url = get_field('featured_plugins_footer_button_url');

$delays = ['0.1s', '0.18s', '0.26s', '0.34s'];
?>

<section class="featured-plugins">
    <div class="featured-shell animation" data-animation="slideUp" data-delay="0.05s">
        <div class="container">
            <div class="top-section">
                <?php if ($featured_plugins_label) : ?>
                    <span class="label"><?php echo esc_html($featured_plugins_label); ?></span>
                <?php endif; ?>

                <?php if ($featured_plugins_title) : ?>
                    <h2><?php echo esc_html($featured_plugins_title); ?></h2>
                <?php endif; ?>

                <?php if ($featured_plugins_description) : ?>
                    <p><?php echo esc_html($featured_plugins_description); ?></p>
                <?php endif; ?>
            </div>

            <?php if ($featured_plugins) : ?>

                <div class="featured-grid">
                    <?php foreach ($featured_plugins as $index => $plugin_id) : ?>
                        <?php
                        $plugin_icon = get_field('plugin_icon', $plugin_id);
                        $plugin_short_description = get_field('plugin_short_description', $plugin_id);
                        $plugin_bottom_button_text = get_field('plugin_bottom_button_text', $plugin_id);
                        $plugin_bottom_button_url = get_field('plugin_bottom_button_url', $plugin_id);
                        $plugin_button_text = get_field('plugin_button_text', $plugin_id);
                        $plugin_badges = get_field('plugin_badges', $plugin_id);
                        $landing_id = (int) get_post_meta($plugin_id, 'wsh_landing_page_id', true);
                        $plugin_single_url = $landing_id > 0 ? get_permalink($landing_id) : get_permalink($plugin_id);

                        if (!$plugin_button_text) {
                            $plugin_button_text = 'View plugin';
                        }

                        $delay = isset($delays[$index]) ? $delays[$index] : '0.1s';
                        ?>

                        <div class="featured-card box animation" data-animation="slideUp"
                            data-delay="<?php echo esc_attr($delay); ?>">
                            <div class="content">
                                <div class="d-flex align-items-center title-holder">
                                    <div class="border">
                                        <div class="image-holder">
                                            <?php if ($plugin_icon) : ?>
                                                <?php echo wp_get_attachment_image($plugin_icon, 'full'); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div>
                                        <?php if ($plugin_badges) : ?>
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
                                        <?php endif; ?>

                                        <h4><?php echo esc_html(get_the_title($plugin_id)); ?></h4>
                                    </div>
                                </div>

                                <?php if ($plugin_short_description) : ?>
                                    <p><?php echo esc_html($plugin_short_description); ?></p>
                                <?php endif; ?>

                                <div class="cta d-flex align-items-center">
                                    <?php if ($plugin_bottom_button_text && $plugin_bottom_button_url) : ?>
                                        <a href="<?php echo esc_url($plugin_bottom_button_url); ?>" class="analytics">
                                            <?php echo esc_html($plugin_bottom_button_text); ?>
                                        </a>
                                    <?php endif; ?>

                                    <a href="<?php echo esc_url($plugin_single_url); ?>" class="btn btn-primary">
                                        <?php echo esc_html($plugin_button_text); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($featured_plugins_footer_button_text && $featured_plugins_footer_button_url) : ?>
                <div class="featured-footer animation" data-animation="slideUp" data-delay="0.32s">
                    <div class="border">
                        <a href="<?php echo esc_url($featured_plugins_footer_button_url); ?>" class="btn cta-link btn-blue">
                            <?php echo esc_html($featured_plugins_footer_button_text); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>