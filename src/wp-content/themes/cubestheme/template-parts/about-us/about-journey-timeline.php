<?php
$journey_timeline_label = get_field('journey_timeline_label');
$journey_timeline_title = get_field('journey_timeline_title');

$timeline_delays = ['0.1s', '0.18s', '0.26s', '0.34s', '0.42s', '0.5s'];
?>

<section class="journey-timeline">
    <div class="timeline-shell animation" data-animation="slideUp" data-delay="0.05s">
        <div class="container">
            <div class="top-section">
                <?php if ($journey_timeline_label) : ?>
                    <span class="label"><?php echo esc_html($journey_timeline_label); ?></span>
                <?php endif; ?>

                <?php if ($journey_timeline_title) : ?>
                    <h2><?php echo esc_html($journey_timeline_title); ?></h2>
                <?php endif; ?>
            </div>
            <?php if (have_rows('journey_timeline_items')) : ?>

                <div class="milestone-grid">
                    <?php $index = 0; ?>
                    <?php while (have_rows('journey_timeline_items')) : the_row(); ?>
                        <?php
                        $year_label = get_sub_field('year_label');
                        $text = get_sub_field('text');
                        $delay = isset($timeline_delays[$index]) ? $timeline_delays[$index] : '0.1s';
                        ?>

                        <div class="milestone-card animation" data-animation="slideUp"
                            data-delay="<?php echo esc_attr($delay); ?>">
                            <div class="box">
                                <div class="content">
                                    <?php if ($year_label) : ?>
                                        <span><?php echo esc_html($year_label); ?></span>
                                    <?php endif; ?>

                                    <?php if ($text) : ?>
                                        <p><?php echo esc_html($text); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php $index++; ?>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</section>