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

        <?php if (have_rows('suite_plan_items')) : ?>

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