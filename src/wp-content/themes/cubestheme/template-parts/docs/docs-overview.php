<?php
$docs_overview_label = get_field('docs_overview_label');
$docs_overview_title = get_field('docs_overview_title');

$docs_overview_delays = ['0.12s', '0.2s', '0.28s', '0.36s', '0.44s', '0.52s'];
?>

<section class="docs-overview">
    <div class="container">
        <div class="top-section animation" data-animation="slideUp" data-delay="0.1s">
            <?php if ($docs_overview_label) : ?>
                <span class="label"><?php echo esc_html($docs_overview_label); ?></span>
            <?php endif; ?>

            <?php if ($docs_overview_title) : ?>
                <h2><?php echo esc_html($docs_overview_title); ?></h2>
            <?php endif; ?>
        </div>

        <?php if (have_rows('docs_overview_items')) : ?>

            <div class="docs-areas info-card-grid">
                <?php $index = 0; ?>
                <?php while (have_rows('docs_overview_items')) : the_row(); ?>
                    <?php
                    $icon = get_sub_field('icon');
                    $title = get_sub_field('title');
                    $description = get_sub_field('description');
                    $delay = isset($docs_overview_delays[$index]) ? $docs_overview_delays[$index] : '0.12s';
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