<?php
$suites_label = get_field('suites_label');
$suites_title = get_field('suites_title');
$suites_description = get_field('suites_description');
$suites = get_field('suites');
?>

<section class="suite-showcase">
    <div class="container">
        <div class="top-section animation" data-animation="slideUp" data-delay="0.1s">
            <?php if ($suites_label) : ?>
                <span class="label"><?php echo esc_html($suites_label); ?></span>
            <?php endif; ?>

            <?php if ($suites_title) : ?>
                <h2><?php echo esc_html($suites_title); ?></h2>
            <?php endif; ?>

            <?php if ($suites_description) : ?>
                <p><?php echo esc_html($suites_description); ?></p>
            <?php endif; ?>
        </div>

        <div class="suite-browser animation" data-animation="slideUp" data-delay="0.1s" data-suite-tabs>
            <div class="suite-switcher border pill-tabs" role="tablist" aria-label="Plugin suites">
                <?php if ($suites) : ?>
                    <?php foreach ($suites as $index => $suite) : ?>
                        <?php
                        $tab_label = $suite['tab_label'] ?? '';
                        $tab_slug = sanitize_title($tab_label);
                        $is_active = $index === 0;
                        ?>

                        <button class="suites-tab <?php echo $is_active ? 'is-active' : ''; ?>" type="button" role="tab"
                            id="suite-tab-<?php echo esc_attr($tab_slug); ?>"
                            aria-controls="suite-panel-<?php echo esc_attr($tab_slug); ?>"
                            aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                            tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
                            data-suite-trigger="<?php echo esc_attr($tab_slug); ?>">
                            <?php echo esc_html($tab_label); ?>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="suite-stage">
                <?php if ($suites) : ?>
                    <?php foreach ($suites as $index => $suite) : ?>
                        <?php
                        $tab_label = $suite['tab_label'] ?? '';
                        $tab_slug = sanitize_title($tab_label);
                        $title = $suite['title'] ?? '';
                        $description = $suite['description'] ?? '';
                        $items = $suite['items'] ?? [];
                        $button_text = $suite['button_text'] ?? '';
                        $button_url = $suite['button_url'] ?? '';
                        $image = $suite['image'] ?? '';
                        $is_active = $index === 0;
                        ?>

                        <article class="suite-panel box <?php echo $is_active ? 'is-active' : ''; ?>" role="tabpanel"
                            id="suite-panel-<?php echo esc_attr($tab_slug); ?>"
                            aria-labelledby="suite-tab-<?php echo esc_attr($tab_slug); ?>"
                            data-suite-panel="<?php echo esc_attr($tab_slug); ?>"
                            <?php if (!$is_active) : ?>hidden<?php endif; ?>>
                            <div class="content">
                                <div class="suite-details">
                                    <?php if ($title) : ?>
                                        <h3><?php echo esc_html($title); ?></h3>
                                    <?php endif; ?>

                                    <?php if ($description) : ?>
                                        <p><?php echo esc_html($description); ?></p>
                                    <?php endif; ?>

                                    <?php if ($items) : ?>
                                        <ul class="suite-includes list-unstyled">
                                            <?php foreach ($items as $item) : ?>
                                                <?php
                                                $item_title = $item['title'] ?? '';
                                                $item_text = $item['text'] ?? '';
                                                ?>

                                                <li>
                                                    <img src="<?php echo get_template_directory_uri(); ?>/frontend/img/homepage/check-circle.svg"
                                                        alt="">
                                                    <div>
                                                        <?php if ($item_title) : ?>
                                                            <strong><?php echo esc_html($item_title); ?></strong>
                                                        <?php endif; ?>

                                                        <?php if ($item_text) : ?>
                                                            <span><?php echo esc_html($item_text); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>

                                    <?php if ($button_text && $button_url) : ?>
                                        <a href="<?php echo esc_url($button_url); ?>" class="btn btn-primary">
                                            <?php echo esc_html($button_text); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div class="suite-visual">
                                    <?php if ($image) : ?>
                                        <?php echo wp_get_attachment_image($image, 'full'); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>