<?php
$about_contact_label = get_field('about_contact_label');
$about_contact_title = get_field('about_contact_title');
$about_contact_description = get_field('about_contact_description');
$about_contact_note = get_field('about_contact_note');

$about_contact_delays = ['0.12s', '0.2s', '0.28s', '0.36s'];
?>

<section class="about-contact">
    <div class="contact-shell animation" data-animation="slideUp" data-delay="0.05s">
        <div class="container">
            <div class="top-section">
                <?php if ($about_contact_label) : ?>
                    <span class="label"><?php echo esc_html($about_contact_label); ?></span>
                <?php endif; ?>

                <?php if ($about_contact_title) : ?>
                    <h2><?php echo esc_html($about_contact_title); ?></h2>
                <?php endif; ?>

                <?php if ($about_contact_description) : ?>
                    <p><?php echo esc_html($about_contact_description); ?></p>
                <?php endif; ?>
            </div>
            <?php if (have_rows('about_contact_items')) : ?>

                <div class="contact-grid">
                    <?php $index = 0; ?>
                    <?php while (have_rows('about_contact_items')) : the_row(); ?>
                        <?php
                        $icon = get_sub_field('icon');
                        $title = get_sub_field('title');
                        $description = get_sub_field('description');
                        $button_text = get_sub_field('button_text');
                        $button_url = get_sub_field('button_url');
                        $delay = isset($about_contact_delays[$index]) ? $about_contact_delays[$index] : '0.12s';
                        ?>

                        <div class="single-box box animation" data-animation="slideUp"
                            data-delay="<?php echo esc_attr($delay); ?>">
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

            <?php if ($about_contact_note) : ?>
                <p class="contact-note"><?php echo esc_html($about_contact_note); ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>