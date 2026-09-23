<?php
$story_overview_label = get_field('story_overview_label');
$story_overview_title = get_field('story_overview_title');
$story_overview_description = get_field('story_overview_description');
$story_overview_intro_text = get_field('story_overview_intro_text');
?>

<section class="story-overview">
    <div class="container">
        <div class="top-section animation" data-animation="slideUp" data-delay="0.1s">
            <?php if ($story_overview_label) : ?>
                <span class="label"><?php echo esc_html($story_overview_label); ?></span>
            <?php endif; ?>

            <?php if ($story_overview_title) : ?>
                <h2><?php echo esc_html($story_overview_title); ?></h2>
            <?php endif; ?>

            <?php if ($story_overview_description) : ?>
                <p><?php echo esc_html($story_overview_description); ?></p>
            <?php endif; ?>
        </div>

        <div class="story-summary">
            <div class="box animation" data-animation="slideUp" data-delay="0.1s">
                <div class="content">
                    <?php if ($story_overview_intro_text) : ?>
                        <p><?php echo esc_html($story_overview_intro_text); ?></p>
                    <?php endif; ?>

                    <?php if (have_rows('story_overview_highlights')) : ?>

                        <div class="story-highlights">
                            <ul class="list-unstyled">
                                <?php while (have_rows('story_overview_highlights')) : the_row(); ?>
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
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>