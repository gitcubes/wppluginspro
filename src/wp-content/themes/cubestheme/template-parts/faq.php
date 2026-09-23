<?php
$homepage_id = get_option('page_on_front');

$faq_label = get_field('faq_label', $homepage_id);
$faq_title = get_field('faq_title', $homepage_id);
$faq_footer_button_text = get_field('faq_footer_button_text', $homepage_id);
$faq_footer_button_url = get_field('faq_footer_button_url', $homepage_id);
?>

<section class="faq">
    <div class="container">
        <div class="top-section animation" data-animation="slideUp" data-delay="0.08s">
            <?php if ($faq_label) : ?>
                <span class="label"><?php echo esc_html($faq_label); ?></span>
            <?php endif; ?>

            <?php if ($faq_title) : ?>
                <h2><?php echo esc_html($faq_title); ?></h2>
            <?php endif; ?>
        </div>

        <div class="accordion animation" data-animation="slideUp" data-delay="0.12s">
            <?php if (have_rows('faq_items', $homepage_id)) : ?>
                <?php $index = 1; ?>
                <?php while (have_rows('faq_items', $homepage_id)) : the_row(); ?>
                    <?php
                    $question = get_sub_field('question');
                    $answer = get_sub_field('answer');
                    $is_active = $index === 1;
                    $formatted_index = str_pad($index, 2, '0', STR_PAD_LEFT);
                    ?>

                    <div class="box accordion-item <?php echo $is_active ? 'is-active' : ''; ?>" data-accordion-item>
                        <div class="single-accordion">
                            <button class="accordion-trigger" type="button"
                                id="faq-trigger-<?php echo esc_attr($formatted_index); ?>"
                                aria-expanded="<?php echo $is_active ? 'true' : 'false'; ?>"
                                aria-controls="faq-panel-<?php echo esc_attr($formatted_index); ?>" data-accordion-trigger>
                                <div class="accordion-head d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center title-holder">
                                        <span class="border">
                                            <span><?php echo esc_html($formatted_index); ?></span>
                                        </span>
                                        <h4 class="accordion-title"><?php echo esc_html($question); ?></h4>
                                    </div>

                                    <span class="accordion-icon" aria-hidden="true">
                                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path d="M5 8L10 13L15 8" stroke="currentColor" stroke-width="1.8"
                                                stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </div>
                            </button>

                            <div class="accordion-panel" id="faq-panel-<?php echo esc_attr($formatted_index); ?>" role="region"
                                aria-labelledby="faq-trigger-<?php echo esc_attr($formatted_index); ?>" data-accordion-panel
                                <?php if (!$is_active) : ?>hidden<?php endif; ?>>
                                <p><?php echo esc_html($answer); ?></p>
                            </div>
                        </div>
                    </div>

                    <?php $index++; ?>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <?php if ($faq_footer_button_text && $faq_footer_button_url) : ?>
            <div class="faq-footer animation" data-animation="slideUp" data-delay="0.18s">
                <a href="<?php echo esc_url($faq_footer_button_url); ?>" class="btn btn-primary">
                    <?php echo esc_html($faq_footer_button_text); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>