<?php
$why_choose_us_image = get_field('why_choose_us_image');
$why_choose_us_label = get_field('why_choose_us_label');
$why_choose_us_title = get_field('why_choose_us_title');
?>

<section class="why-choose-us">
    <div class="container">
        <div class="why-choose-layout">
            <div class="box animation" data-animation="slideRight" data-delay="0.08s">
                <?php if ($why_choose_us_image) : ?>
                    <?php echo wp_get_attachment_image($why_choose_us_image, 'full'); ?>
                <?php endif; ?>
            </div>

            <div class="content animation" data-animation="slideLeft" data-delay="0.12s">
                <?php if ($why_choose_us_label) : ?>
                    <span class="label">
                        <?php echo esc_html($why_choose_us_label); ?>
                    </span>
                <?php endif; ?>

                <?php if ($why_choose_us_title) : ?>
                    <h2><?php echo esc_html($why_choose_us_title); ?></h2>
                <?php endif; ?>

                <?php if (have_rows('why_choose_us_items')) : ?>

                    <div class="accordion">
                        <?php $index = 1; ?>
                        <?php while (have_rows('why_choose_us_items')) : the_row(); ?>
                            <?php
                            $number = get_sub_field('number');
                            $title = get_sub_field('title');
                            $description = get_sub_field('description');
                            $is_active = $index === 1;
                            ?>

                            <div class="box accordion-item <?php echo $is_active ? 'is-active' : ''; ?>" data-accordion-item>
                                <div class="single-accordion">
                                    <button class="accordion-trigger" type="button"
                                        id="why-us-trigger-<?php echo str_pad($index, 2, '0', STR_PAD_LEFT); ?>"
                                        aria-expanded="<?php echo $is_active ? 'true' : 'false'; ?>"
                                        aria-controls="why-us-panel-<?php echo str_pad($index, 2, '0', STR_PAD_LEFT); ?>"
                                        data-accordion-trigger>
                                        <span class="d-flex align-items-center title-holder">
                                            <span class="border">
                                                <span><?php echo esc_html($number); ?></span>
                                            </span>
                                            <span class="accordion-title"><?php echo esc_html($title); ?></span>
                                        </span>
                                    </button>

                                    <div class="accordion-panel"
                                        id="why-us-panel-<?php echo str_pad($index, 2, '0', STR_PAD_LEFT); ?>" role="region"
                                        aria-labelledby="why-us-trigger-<?php echo str_pad($index, 2, '0', STR_PAD_LEFT); ?>"
                                        data-accordion-panel <?php if (!$is_active) : ?>hidden<?php endif; ?>>
                                        <p><?php echo esc_html($description); ?></p>
                                    </div>
                                </div>
                            </div>

                            <?php $index++; ?>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</section>