<?php
$focus_areas_label = get_field('focus_areas_label');
$focus_area_delays = ['0.12s', '0.18s', '0.24s', '0.3s', '0.36s', '0.42s', '0.48s', '0.54s'];
?>

<section class="focus-areas">
    <div class="container">
        <?php if ($focus_areas_label) : ?>
            <span class="label animation" data-animation="slideUp" data-delay="0.08s">
                <?php echo esc_html($focus_areas_label); ?>
            </span>
        <?php endif; ?>

        <?php if (have_rows('focus_areas_items')) : ?>

            <div class="focus-area-grid">
                <?php $index = 0; ?>
                <?php while (have_rows('focus_areas_items')) : the_row(); ?>
                    <?php
                    $text = get_sub_field('text');
                    $delay = isset($focus_area_delays[$index]) ? $focus_area_delays[$index] : '0.12s';
                    ?>

                    <div class="box animation" data-animation="slideUp" data-delay="<?php echo esc_attr($delay); ?>">
                        <div class="content">
                            <?php echo esc_html($text); ?>
                        </div>
                    </div>

                    <?php $index++; ?>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</section>