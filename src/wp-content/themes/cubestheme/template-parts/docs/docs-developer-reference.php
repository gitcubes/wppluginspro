<?php
$developer_reference_label = get_field('developer_reference_label');
$developer_reference_title = get_field('developer_reference_title');
$developer_reference_description = get_field('developer_reference_description');

$developer_reference_delays = ['0.12s', '0.2s', '0.28s', '0.36s', '0.44s'];
?>

<section class="developer-reference" id="docs-developer">
    <div class="container">
        <div class="top-section animation" data-animation="slideUp" data-delay="0.1s">
            <?php if ($developer_reference_label) : ?>
                <span class="label"><?php echo esc_html($developer_reference_label); ?></span>
            <?php endif; ?>

            <?php if ($developer_reference_title) : ?>
                <h2><?php echo esc_html($developer_reference_title); ?></h2>
            <?php endif; ?>

            <?php if ($developer_reference_description) : ?>
                <p><?php echo esc_html($developer_reference_description); ?></p>
            <?php endif; ?>
        </div>

        <?php if (have_rows('developer_reference_items')) : ?>

            <div class="guide-grid">
                <?php $index = 0; ?>
                <?php while (have_rows('developer_reference_items')) : the_row(); ?>
                    <?php
                    $label = get_sub_field('label');
                    $title = get_sub_field('title');
                    $description = get_sub_field('description');
                    $button_text = get_sub_field('button_text');
                    $button_url = get_sub_field('button_url');
                    $delay = isset($developer_reference_delays[$index]) ? $developer_reference_delays[$index] : '0.12s';
                    ?>

                    <div class="single-box box animation" data-animation="slideUp" data-delay="<?php echo esc_attr($delay); ?>">
                        <div class="content">
                            <?php if ($label) : ?>
                                <span class="label"><?php echo esc_html($label); ?></span>
                            <?php endif; ?>

                            <?php if ($title) : ?>
                                <h3><?php echo esc_html($title); ?></h3>
                            <?php endif; ?>

                            <?php if ($description) : ?>
                                <p><?php echo esc_html($description); ?></p>
                            <?php endif; ?>

                            <div class="doc-links">
                                <?php if (have_rows('links')) : ?>
                                    <ul class="list-unstyled">
                                        <?php while (have_rows('links')) : the_row(); ?>
                                            <?php
                                            $text = get_sub_field('text');
                                            $file = get_sub_field('file');
                                            ?>

                                            <?php if ($text && $file) : ?>
                                                <li>
                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none"
                                                        xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M6 12L10 8L6 4" stroke="#034DD3" stroke-width="2" stroke-linecap="round"
                                                            stroke-linejoin="round" />
                                                    </svg>
                                                    <a href="<?php echo esc_url($file); ?>" target="_blank" rel="noopener noreferrer">
                                                        <?php echo esc_html($text); ?>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        <?php endwhile; ?>
                                    </ul>
                                <?php endif; ?>

                                <?php if ($button_text && $button_url) : ?>
                                    <a href="<?php echo esc_url($button_url); ?>" class="btn btn-primary">
                                        <?php echo esc_html($button_text); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php $index++; ?>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

    </div>
</section>