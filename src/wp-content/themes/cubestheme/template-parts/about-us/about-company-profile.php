<?php
$company_profile_image = get_field('company_profile_image');
$company_profile_label = get_field('company_profile_label');
$company_profile_title = get_field('company_profile_title');
$company_profile_description = get_field('company_profile_description');

$company_profile_card_caption = get_field('company_profile_card_caption');
$company_profile_card_title = get_field('company_profile_card_title');
$company_profile_card_text = get_field('company_profile_card_text');

$company_profile_button_text = get_field('company_profile_button_text');
$company_profile_button_url = get_field('company_profile_button_url');
?>

<section class="company-profile">
    <div class="container position-relative">
        <?php if ($company_profile_image) : ?>
            <figure class="animation" data-animation="fadeIn" data-delay="0.08s">
                <?php echo wp_get_attachment_image($company_profile_image, 'full'); ?>
            </figure>
        <?php endif; ?>


        <div class="top-section animation" data-animation="slideUp" data-delay="0.1s">
            <?php if ($company_profile_label) : ?>
                <span class="label"><?php echo esc_html($company_profile_label); ?></span>
            <?php endif; ?>

            <?php if ($company_profile_title) : ?>
                <h2><?php echo esc_html($company_profile_title); ?></h2>
            <?php endif; ?>

            <?php if ($company_profile_description) : ?>
                <p><?php echo esc_html($company_profile_description); ?></p>
            <?php endif; ?>
        </div>

        <div class="box animation" data-animation="slideUp" data-delay="0.16s">
            <div class="content">
                <?php if ($company_profile_card_caption) : ?>
                    <p class="company-caption"><?php echo esc_html($company_profile_card_caption); ?></p>
                <?php endif; ?>

                <?php if ($company_profile_card_title) : ?>
                    <h3><?php echo esc_html($company_profile_card_title); ?></h3>
                <?php endif; ?>

                <?php if ($company_profile_card_text) : ?>
                    <?php echo wp_kses_post($company_profile_card_text); ?>
                <?php endif; ?>

                <?php if ($company_profile_button_text && $company_profile_button_url) : ?>
                    <a href="<?php echo esc_url($company_profile_button_url); ?>" class="btn btn-primary">
                        <?php echo esc_html($company_profile_button_text); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>