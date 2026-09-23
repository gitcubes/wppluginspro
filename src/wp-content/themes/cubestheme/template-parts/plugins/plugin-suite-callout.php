<?php
$suite_callout_label = get_field('suite_callout_label');
$suite_callout_title = get_field('suite_callout_title');
$suite_callout_description = get_field('suite_callout_description');
$suite_callout_primary_button_text = get_field('suite_callout_primary_button_text');
$suite_callout_primary_button_url = get_field('suite_callout_primary_button_url');
$suite_callout_secondary_button_text = get_field('suite_callout_secondary_button_text');
$suite_callout_secondary_button_url = get_field('suite_callout_secondary_button_url');
?>

<section class="suite-callout">
    <div class="suite-callout-shell animation" data-animation="slideUp" data-delay="0.05s">
        <div class="container">
            <div class="top-section">
                <?php if ($suite_callout_label) : ?>
                    <span class="label"><?php echo esc_html($suite_callout_label); ?></span>
                <?php endif; ?>

                <?php if ($suite_callout_title) : ?>
                    <h2><?php echo esc_html($suite_callout_title); ?></h2>
                <?php endif; ?>

                <?php if ($suite_callout_description) : ?>
                    <p><?php echo esc_html($suite_callout_description); ?></p>
                <?php endif; ?>

                <?php if (
                    ($suite_callout_primary_button_text && $suite_callout_primary_button_url) ||
                    ($suite_callout_secondary_button_text && $suite_callout_secondary_button_url)
                ) : ?>
                    <div class="callout-actions">
                        <?php if ($suite_callout_primary_button_text && $suite_callout_primary_button_url) : ?>
                            <div class="border">
                                <a href="<?php echo esc_url($suite_callout_primary_button_url); ?>"
                                    class="btn btn-white cta-link">
                                    <?php echo esc_html($suite_callout_primary_button_text); ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if ($suite_callout_secondary_button_text && $suite_callout_secondary_button_url) : ?>
                            <div class="border">
                                <a href="<?php echo esc_url($suite_callout_secondary_button_url); ?>"
                                    class="btn btn-blue cta-link">
                                    <?php echo esc_html($suite_callout_secondary_button_text); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>