<?php
$suite_licensing_image = get_field('suite_licensing_image');
$suite_licensing_label = get_field('suite_licensing_label');
$suite_licensing_title = get_field('suite_licensing_title');
$suite_licensing_description = get_field('suite_licensing_description');
$suite_licensing_card_title = get_field('suite_licensing_card_title');
$suite_licensing_card_description = get_field('suite_licensing_card_description');
?>

<section class="suite-licensing">
    <div class="container">
        <div class="licensing-layout">
            <div class="box animation" data-animation="slideRight" data-delay="0.08s">
                <?php if ($suite_licensing_image) : ?>
                    <?php echo wp_get_attachment_image($suite_licensing_image, 'full'); ?>
                <?php endif; ?>
            </div>

            <div class="licensing-copy animation" data-animation="slideLeft" data-delay="0.12s">
                <?php if ($suite_licensing_label) : ?>
                    <span class="label">
                        <?php echo esc_html($suite_licensing_label); ?>
                    </span>
                <?php endif; ?>

                <?php if ($suite_licensing_title) : ?>
                    <h2><?php echo esc_html($suite_licensing_title); ?></h2>
                <?php endif; ?>

                <?php if ($suite_licensing_description) : ?>
                    <p><?php echo esc_html($suite_licensing_description); ?></p>
                <?php endif; ?>

                <div class="single-box box animation" data-animation="slideUp" data-delay="0.1s">
                    <div class="content">
                        <div class="licensing-details">
                            <?php if ($suite_licensing_card_title) : ?>
                                <h4><?php echo esc_html($suite_licensing_card_title); ?></h4>
                            <?php endif; ?>

                            <?php if ($suite_licensing_card_description) : ?>
                                <p><?php echo esc_html($suite_licensing_card_description); ?></p>
                            <?php endif; ?>

                            <?php if (have_rows('suite_licensing_items')) : ?>
                                <ul class="list-unstyled">
                                    <?php while (have_rows('suite_licensing_items')) : the_row(); ?>
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