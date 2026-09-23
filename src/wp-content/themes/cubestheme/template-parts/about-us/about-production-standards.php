<?php
$production_standards_image = get_field('production_standards_image');
$production_standards_label = get_field('production_standards_label');
$production_standards_title = get_field('production_standards_title');
$production_standards_description = get_field('production_standards_description');
?>

<section class="production-standards">
    <div class="container">
        <div class="standards-layout">
            <div class="box animation" data-animation="slideRight" data-delay="0.08s">
                <?php if ($production_standards_image) : ?>
                    <?php echo wp_get_attachment_image($production_standards_image, 'full'); ?>
                <?php endif; ?>
            </div>

            <div class="standards-copy animation" data-animation="slideLeft" data-delay="0.12s">
                <?php if ($production_standards_label) : ?>
                    <span class="label">
                        <?php echo esc_html($production_standards_label); ?>
                    </span>
                <?php endif; ?>

                <?php if ($production_standards_title) : ?>
                    <h2><?php echo esc_html($production_standards_title); ?></h2>
                <?php endif; ?>

                <?php if ($production_standards_description) : ?>
                    <p><?php echo esc_html($production_standards_description); ?></p>
                <?php endif; ?>

                <div class="single-box box animation" data-animation="slideUp" data-delay="0.1s">
                    <div class="content">
                        <div class="box-info">
                            <?php if (have_rows('production_standards_items')) : ?>
                                <ul class="list-unstyled">
                                    <?php while (have_rows('production_standards_items')) : the_row(); ?>
                                        <?php $text = get_sub_field('text'); ?>

                                        <?php if ($text) : ?>
                                            <li>
                                                <img src="<?php echo esc_url(get_template_directory_uri() . '/frontend/img/homepage/check-circle-blue.svg'); ?>"
                                                    alt="">
                                                <span><?php echo esc_html($text); ?></span>
                                            </li>
                                        <?php endif; ?>
                                    <?php endwhile; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>