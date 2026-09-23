<?php
$docs_contact_label = get_field('docs_contact_label');
$docs_contact_title = get_field('docs_contact_title');
$docs_contact_description = get_field('docs_contact_description');
$docs_contact_button_text = get_field('docs_contact_button_text');
$docs_contact_button_url = get_field('docs_contact_button_url');
?>

<section class="docs-contact" id="docs-contact">
    <div class="support-callout animation" data-animation="slideUp" data-delay="0.05s">
        <div class="container">
            <div class="top-section">
                <?php if ($docs_contact_label) : ?>
                    <span class="label"><?php echo esc_html($docs_contact_label); ?></span>
                <?php endif; ?>

                <?php if ($docs_contact_title) : ?>
                    <h2><?php echo esc_html($docs_contact_title); ?></h2>
                <?php endif; ?>

                <?php if ($docs_contact_description) : ?>
                    <p><?php echo esc_html($docs_contact_description); ?></p>
                <?php endif; ?>

                <?php if ($docs_contact_button_text && $docs_contact_button_url) : ?>
                    <div class="callout-actions">
                        <div class="border">
                            <a href="<?php echo esc_url($docs_contact_button_url); ?>" class="btn btn-white cta-link">
                                <?php echo esc_html($docs_contact_button_text); ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>