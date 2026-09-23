<?php
$suite_benefits_label = get_field('suite_benefits_label');
$suite_benefits_title = get_field('suite_benefits_title');
$suite_benefits_description = get_field('suite_benefits_description');

$benefit_delays = ['0.12s', '0.2s', '0.28s', '0.36s'];
?>

<section class="suite-benefits">
    <div class="container">
        <div class="top-section animation" data-animation="slideUp" data-delay="0.1s">
            <?php if ($suite_benefits_label) : ?>
                <span class="label"><?php echo esc_html($suite_benefits_label); ?></span>
            <?php endif; ?>

            <?php if ($suite_benefits_title) : ?>
                <h2><?php echo esc_html($suite_benefits_title); ?></h2>
            <?php endif; ?>

            <?php if ($suite_benefits_description) : ?>
                <p><?php echo esc_html($suite_benefits_description); ?></p>
            <?php endif; ?>
        </div>

        <?php if (have_rows('suite_benefit_items')) : ?>

            <div class="benefit-grid info-card-grid">
                <?php $index = 0; ?>
                <?php while (have_rows('suite_benefit_items')) : the_row(); ?>
                    <?php
                    $icon = get_sub_field('icon');
                    $title = get_sub_field('title');
                    $description = get_sub_field('description');
                    $delay = isset($benefit_delays[$index]) ? $benefit_delays[$index] : '0.12s';
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

    </div>
</section>