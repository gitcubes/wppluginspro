<?php
$plugin_families_label = get_field('plugin_families_label');
$plugin_families_title = get_field('plugin_families_title');
$plugin_families_description = get_field('plugin_families_description');
$plugin_families_summary_text = get_field('plugin_families_summary_text');

$family_delays = ['0.12s', '0.2s', '0.28s', '0.36s'];
?>

<section class="plugin-families">
    <div class="container position-relative">
        <div class="top-section animation" data-animation="slideUp" data-delay="0.1s">
            <?php if ($plugin_families_label) : ?>
                <span class="label"><?php echo esc_html($plugin_families_label); ?></span>
            <?php endif; ?>

            <?php if ($plugin_families_title) : ?>
                <h2><?php echo esc_html($plugin_families_title); ?></h2>
            <?php endif; ?>

            <?php if ($plugin_families_description) : ?>
                <p><?php echo esc_html($plugin_families_description); ?></p>
            <?php endif; ?>
        </div>

        <?php if (have_rows('plugin_families_items')) : ?>

            <div class="family-grid info-card-grid">
                <?php $index = 0; ?>
                <?php while (have_rows('plugin_families_items')) : the_row(); ?>
                    <?php
                    $icon = get_sub_field('icon');
                    $title = get_sub_field('title');
                    $description = get_sub_field('description');
                    $delay = isset($family_delays[$index]) ? $family_delays[$index] : '0.12s';
                    ?>

                    <div class="single-box box animation" data-animation="slideUp" data-delay="<?php echo esc_attr($delay); ?>">
                        <div class="content">
                            <div class="border">
                                <div class="image-holder">
                                    <?php if ($icon) : ?>
                                        <span><?php echo esc_html($icon); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($title) : ?>
                                <h4><?php echo esc_html($title); ?></h4>
                            <?php endif; ?>

                            <?php if ($description) : ?>
                                <p><?php echo esc_html($description); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php $index++; ?>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>


        <?php if ($plugin_families_summary_text) : ?>
            <div class="ecosystem-summary animation" data-animation="slideUp" data-delay="0.18s">
                <?php echo wp_kses_post($plugin_families_summary_text); ?>
            </div>
        <?php endif; ?>
    </div>
</section>