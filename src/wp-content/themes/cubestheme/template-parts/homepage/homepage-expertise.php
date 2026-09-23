<?php
$expertise_label = get_field('expertise_label');
$expertise_title = get_field('expertise_title');
$expertise_description = get_field('expertise_description');
$expertise_button_text = get_field('expertise_button_text');
$expertise_button_url = get_field('expertise_button_url');
?>

<section class="expertise">
    <div class="container">
        <div class="expertise-copy animation" data-animation="slideRight" data-delay="0.1s">
            <?php if ($expertise_label) : ?>
                <span class="label"><?php echo esc_html($expertise_label); ?></span>
            <?php endif; ?>

            <?php if ($expertise_title) : ?>
                <h2><?php echo esc_html($expertise_title); ?></h2>
            <?php endif; ?>

            <?php if ($expertise_description) : ?>
                <p><?php echo esc_html($expertise_description); ?></p>
            <?php endif; ?>

            <?php if ($expertise_button_text && $expertise_button_url) : ?>
                <a href="<?php echo esc_url($expertise_button_url); ?>" class="btn btn-primary">
                    <?php echo esc_html($expertise_button_text); ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if (have_rows('expertise_items')) : ?>

            <div class="expertise-grid">
                <?php
                $delays = ['0.1s', '0.18s', '0.26s', '0.34s'];
                $index = 0;
                ?>

                <?php while (have_rows('expertise_items')) : the_row(); ?>
                    <?php
                    $number = get_sub_field('number');
                    $title = get_sub_field('title');
                    $description = get_sub_field('description');
                    $delay = isset($delays[$index]) ? $delays[$index] : '0.1s';
                    ?>

                    <div class="box animation" data-animation="slideUp" data-delay="<?php echo esc_attr($delay); ?>">
                        <div class="content">
                            <div class="d-flex align-items-center title-holder">
                                <div class="border">
                                    <span><?php echo esc_html($number); ?></span>
                                </div>

                                <?php if ($title) : ?>
                                    <h4><?php echo esc_html($title); ?></h4>
                                <?php endif; ?>
                            </div>

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