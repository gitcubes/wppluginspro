<?php
$audience_fit_label = get_field('audience_fit_label');
$audience_fit_title = get_field('audience_fit_title');

$audience_fit_delays = ['0.12s', '0.2s', '0.28s', '0.36s'];
?>

<section class="audience-fit">
    <div class="container">
        <div class="top-section animation" data-animation="slideUp" data-delay="0.1s">
            <?php if ($audience_fit_label) : ?>
                <span class="label"><?php echo esc_html($audience_fit_label); ?></span>
            <?php endif; ?>

            <?php if ($audience_fit_title) : ?>
                <h2><?php echo esc_html($audience_fit_title); ?></h2>
            <?php endif; ?>
        </div>
        <?php if (have_rows('audience_fit_items')) : ?>
            <div class="audience-grid">

                <?php $index = 0; ?>
                <?php while (have_rows('audience_fit_items')) : the_row(); ?>
                    <?php
                    $title = get_sub_field('title');
                    $description = get_sub_field('description');
                    $button_text = get_sub_field('button_text');
                    $button_url = get_sub_field('button_url');
                    $delay = isset($audience_fit_delays[$index]) ? $audience_fit_delays[$index] : '0.12s';
                    ?>

                    <div class="single-box box animation" data-animation="slideUp" data-delay="<?php echo esc_attr($delay); ?>">
                        <div class="content">
                            <?php if ($title) : ?>
                                <h3><?php echo esc_html($title); ?></h3>
                            <?php endif; ?>

                            <?php if ($description) : ?>
                                <p><?php echo esc_html($description); ?></p>
                            <?php endif; ?>

                            <div class="box-info">
                                <?php if (have_rows('points')) : ?>
                                    <ul class="list-unstyled">
                                        <?php while (have_rows('points')) : the_row(); ?>
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

                            <?php if ($button_text && $button_url) : ?>
                                <a href="<?php echo esc_url($button_url); ?>" class="btn btn-primary">
                                    <?php echo esc_html($button_text); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php $index++; ?>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

    </div>
</section>