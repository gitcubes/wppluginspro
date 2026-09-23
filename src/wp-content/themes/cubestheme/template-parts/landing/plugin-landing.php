<?php
$page_id = get_queried_object_id();
$theme_img = get_template_directory_uri() . '/frontend/img';
$check_icon = $theme_img . '/homepage/check-circle-blue.svg';
$star_icon = $theme_img . '/homepage/star.svg';

$hero_title = cubestheme_landing_value($page_id, 'landing_hero_title');
$needs_title = cubestheme_landing_value($page_id, 'landing_needs_title');
$needs_items = cubestheme_landing_rows($page_id, 'landing_needs_items');
$features = cubestheme_landing_rows($page_id, 'landing_features');
$preview_title = cubestheme_landing_value($page_id, 'landing_preview_title');
$compare_rows = cubestheme_landing_rows($page_id, 'landing_compare_rows');
$market_rows = cubestheme_landing_rows($page_id, 'landing_market_rows');
$faq_rows = cubestheme_landing_rows($page_id, 'landing_faq');
$plans = cubestheme_landing_plan_copy($page_id);
$variations = cubestheme_landing_variations(cubestheme_landing_product_id($page_id));
$competitor_a = cubestheme_landing_value($page_id, 'landing_market_competitor_a');
$competitor_b = cubestheme_landing_value($page_id, 'landing_market_competitor_b');
?>

<?php if ($hero_title) : ?>
<section class="hero-section landing-hero">
    <figure class="landing-hero-visual position-relative animation" data-animation="fadeIn" data-delay="0.1s">
        <img src="<?php echo esc_url(cubestheme_landing_image_url($page_id, 'landing_hero_image', $theme_img . '/homepage/lead-img.png')); ?>" alt="" decoding="async" fetchpriority="high">
    </figure>
    <div class="container">
        <div class="lead-content-holder landing-hero-copy animation" data-animation="slideUp" data-delay="0.15s">
            <?php if (cubestheme_landing_value($page_id, 'landing_hero_label')) : ?>
                <span class="label"><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_hero_label')); ?></span>
            <?php endif; ?>
            <h1 class="lead-title"><?php echo esc_html($hero_title); ?></h1>
            <?php if (cubestheme_landing_value($page_id, 'landing_hero_text')) : ?>
                <p class="lead-description"><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_hero_text')); ?></p>
            <?php endif; ?>
            <div class="landing-hero-actions">
                <?php if (cubestheme_landing_value($page_id, 'landing_hero_primary')) : ?>
                    <div class="border">
                        <a href="#landing-pricing" class="btn btn-white cta-link"><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_hero_primary')); ?></a>
                    </div>
                <?php endif; ?>
                <?php if (cubestheme_landing_value($page_id, 'landing_hero_secondary')) : ?>
                    <div class="border">
                        <a href="#landing-features" class="btn btn-blue cta-link"><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_hero_secondary')); ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($needs_title || $needs_items) : ?>
<section class="landing-needs">
    <div class="container">
        <div class="landing-needs-layout">
            <div class="landing-needs-copy animation" data-animation="slideRight" data-delay="0.12s">
                <?php if (cubestheme_landing_value($page_id, 'landing_needs_label')) : ?>
                    <span class="label"><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_needs_label')); ?></span>
                <?php endif; ?>
                <?php if ($needs_title) : ?>
                    <h2><?php echo esc_html($needs_title); ?></h2>
                <?php endif; ?>
                <?php if (cubestheme_landing_value($page_id, 'landing_needs_text')) : ?>
                    <p><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_needs_text')); ?></p>
                <?php endif; ?>
                <?php if ($needs_items) : ?>
                    <div class="landing-needs-card box animation" data-animation="slideUp" data-delay="0.1s">
                        <div class="content">
                            <div class="landing-needs-list">
                                <ul class="list-unstyled">
                                    <?php foreach ($needs_items as $item) : ?>
                                        <?php if (empty($item['item_text'])) continue; ?>
                                        <li>
                                            <img src="<?php echo esc_url($check_icon); ?>" alt="">
                                            <span><?php echo esc_html($item['item_text']); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <figure class="landing-needs-visual box animation" data-animation="slideRight" data-delay="0.08s">
                <img src="<?php echo esc_url(cubestheme_landing_image_url($page_id, 'landing_needs_image', $theme_img . '/about-us/about-us-img.png')); ?>" alt="" loading="lazy" decoding="async">
            </figure>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($features) : ?>
<section class="landing-features" id="landing-features">
    <div class="container position-relative">
        <header class="top-section landing-features-header animation" data-animation="slideUp" data-delay="0.1s">
            <?php if (cubestheme_landing_value($page_id, 'landing_features_label')) : ?>
                <span class="label"><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_features_label')); ?></span>
            <?php endif; ?>
            <?php if (cubestheme_landing_value($page_id, 'landing_features_title')) : ?>
                <h2><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_features_title')); ?></h2>
            <?php endif; ?>
            <?php if (cubestheme_landing_value($page_id, 'landing_features_text')) : ?>
                <p><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_features_text')); ?></p>
            <?php endif; ?>
        </header>
        <div class="landing-features-grid">
            <?php foreach ($features as $feature) : ?>
                <article class="landing-feature-card box animation" data-animation="slideUp" data-delay="0.12s">
                    <div class="content">
                        <?php if (!empty($feature['tag'])) : ?>
                            <div class="landing-feature-tag"><span><?php echo esc_html($feature['tag']); ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($feature['title'])) : ?>
                            <h4><?php echo esc_html($feature['title']); ?></h4>
                        <?php endif; ?>
                        <?php if (!empty($feature['text'])) : ?>
                            <p><?php echo esc_html($feature['text']); ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($preview_title) : ?>
<section class="landing-preview">
    <div class="container position-relative">
        <header class="top-section landing-preview-header animation" data-animation="slideUp" data-delay="0.1s">
            <?php if (cubestheme_landing_value($page_id, 'landing_preview_label')) : ?>
                <span class="label"><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_preview_label')); ?></span>
            <?php endif; ?>
            <h2><?php echo esc_html($preview_title); ?></h2>
            <?php if (cubestheme_landing_value($page_id, 'landing_preview_text')) : ?>
                <p><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_preview_text')); ?></p>
            <?php endif; ?>
        </header>
        <figure class="landing-preview-frame box">
            <img src="<?php echo esc_url(cubestheme_landing_image_url($page_id, 'landing_preview_image', $theme_img . '/landing-page/preview.png')); ?>" alt="">
        </figure>
    </div>
</section>
<?php endif; ?>

<svg class="market-comparison-sprite" aria-hidden="true" width="0" height="0" focusable="false">
    <defs>
        <linearGradient id="market-comparison-check-gradient" x1="11.9983" y1="1.99976" x2="11.9983" y2="21.9963" gradientUnits="userSpaceOnUse">
            <stop stop-color="#034DD3" />
            <stop offset="1" stop-color="#3C8AFE" />
        </linearGradient>
    </defs>
    <symbol id="market-comparison-check-icon" viewBox="0 0 24 24">
        <path d="M11.9983 21.9963C17.5202 21.9963 21.9966 17.5199 21.9966 11.9981C21.9966 6.47614 17.5202 1.99976 11.9983 1.99976C6.47639 1.99976 2 6.47614 2 11.9981C2 17.5199 6.47639 21.9963 11.9983 21.9963Z" fill="url(#market-comparison-check-gradient)" />
        <path d="M7.5 11.998L10.4995 14.9975L16.4985 8.99854" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
    </symbol>
    <symbol id="market-comparison-cross-icon" viewBox="0 0 24 24">
        <path d="M11.9983 21.9961C17.5202 21.9961 21.9966 17.5197 21.9966 11.9978C21.9966 6.4759 17.5202 1.99951 11.9983 1.99951C6.47639 1.99951 2 6.4759 2 11.9978C2 17.5197 6.47639 21.9961 11.9983 21.9961Z" fill="#FF2727" />
        <path d="M8 7.99805L16 15.998M8 15.998L16 7.99805" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
    </symbol>
</svg>

<?php if ($compare_rows) : ?>
<section class="landing-comparison">
    <div class="container position-relative">
        <div class="top-section landing-comparison-header animation" data-animation="slideUp" data-delay="0.1s">
            <?php if (cubestheme_landing_value($page_id, 'landing_compare_label')) : ?>
                <span class="label"><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_compare_label')); ?></span>
            <?php endif; ?>
            <?php if (cubestheme_landing_value($page_id, 'landing_compare_title')) : ?>
                <h2><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_compare_title')); ?></h2>
            <?php endif; ?>
            <?php if (cubestheme_landing_value($page_id, 'landing_compare_text')) : ?>
                <p><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_compare_text')); ?></p>
            <?php endif; ?>
        </div>
        <div class="landing-comparison-table plan-table compare-table box animation" data-animation="slideUp" data-delay="0.16s">
            <div class="plan-table-scroll content">
                <table>
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Feature', 'cubestheme'); ?></th>
                            <th scope="col"><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_compare_free')); ?></th>
                            <th scope="col"><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_compare_pro')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($compare_rows as $row) : ?>
                            <tr>
                                <th scope="row"><?php echo esc_html($row['feature'] ?? ''); ?></th>
                                <?php foreach (array('free_text', 'pro_text') as $column) : ?>
                                    <?php $cell = (string) ($row[$column] ?? ''); ?>
                                    <td>
                                        <div class="landing-comparison-value">
                                            <?php echo cubestheme_landing_mark(cubestheme_landing_is_included($cell)); ?>
                                            <?php if ($cell !== '') : ?>
                                                <span><?php echo esc_html($cell); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($market_rows) : ?>
<section class="market-comparison">
    <div class="container position-relative">
        <div class="top-section market-comparison-header animation" data-animation="slideUp" data-delay="0.1s">
            <?php if (cubestheme_landing_value($page_id, 'landing_market_label')) : ?>
                <span class="label"><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_market_label')); ?></span>
            <?php endif; ?>
            <?php if (cubestheme_landing_value($page_id, 'landing_market_title')) : ?>
                <h2><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_market_title')); ?></h2>
            <?php endif; ?>
            <?php if (cubestheme_landing_value($page_id, 'landing_market_text')) : ?>
                <p><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_market_text')); ?></p>
            <?php endif; ?>
        </div>
        <div class="market-comparison-card box animation" data-animation="slideUp" data-delay="0.16s">
            <div class="market-comparison-scroll content">
                <table class="market-comparison-table">
                    <thead>
                        <tr>
                            <th scope="col" class="market-comparison-column market-comparison-column--feature"><?php esc_html_e('Feature', 'cubestheme'); ?></th>
                            <th scope="col" class="market-comparison-column market-comparison-column--featured">
                                <span class="market-comparison-product market-comparison-product--featured">
                                    <span><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_market_ours')); ?></span>
                                </span>
                            </th>
                            <?php if ($competitor_a) : ?>
                                <th scope="col" class="market-comparison-column market-comparison-column--brand"><?php echo esc_html($competitor_a); ?></th>
                            <?php endif; ?>
                            <?php if ($competitor_b) : ?>
                                <th scope="col" class="market-comparison-column market-comparison-column--brand"><?php echo esc_html($competitor_b); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($market_rows as $row) : ?>
                            <tr>
                                <th scope="row" class="market-comparison-feature"><?php echo esc_html($row['feature'] ?? ''); ?></th>
                                <td class="market-comparison-detail">
                                    <div class="market-comparison-benefit">
                                        <?php echo cubestheme_landing_mark(true); ?>
                                        <span><?php echo esc_html($row['ours'] ?? ''); ?></span>
                                    </div>
                                </td>
                                <?php if ($competitor_a) : ?>
                                    <td class="market-comparison-status"><?php echo cubestheme_landing_mark(!empty($row['competitor_a'])); ?></td>
                                <?php endif; ?>
                                <?php if ($competitor_b) : ?>
                                    <td class="market-comparison-status"><?php echo cubestheme_landing_mark(!empty($row['competitor_b'])); ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($variations) : ?>
<section class="pricing-preview" id="landing-pricing">
    <div class="pricing-shell animation" data-animation="slideUp" data-delay="0.05s">
        <div class="container">
            <div class="top-section">
                <span class="label"><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_pricing_label') ?: 'Pricing'); ?></span>
                <h2><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_pricing_title') ?: 'Choose your license'); ?></h2>
                <?php if (cubestheme_landing_value($page_id, 'landing_pricing_text')) : ?>
                    <p><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_pricing_text')); ?></p>
                <?php endif; ?>
            </div>
            <div class="pricing-grid">
                <?php foreach ($variations as $index => $item) : ?>
                    <?php
                    $plan = $plans[$item['slug']] ?? array();
                    $highlight = !empty($plan['plan_highlight']);
                    $description = isset($plan['plan_description']) ? (string) $plan['plan_description'] : '';
                    $button = !empty($plan['plan_button']) ? (string) $plan['plan_button'] : 'Buy ' . $item['label'];
                    $bullets = array();
                    if (!empty($plan['plan_bullets'])) {
                        $bullets = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $plan['plan_bullets'])));
                    }
                    $delay = $index === 1 ? '0.18s' : ($index === 2 ? '0.26s' : '0.1s');
                    ?>
                    <div class="pricing-card box animation" data-animation="slideUp" data-delay="<?php echo esc_attr($delay); ?>">
                        <div class="content">
                            <?php if ($highlight) : ?>
                                <strong><?php esc_html_e('MOST POPULAR', 'cubestheme'); ?></strong>
                            <?php endif; ?>
                            <div class="d-flex align-items-center title-holder">
                                <div class="border">
                                    <div class="image-holder">
                                        <img src="<?php echo esc_url($star_icon); ?>" alt="">
                                    </div>
                                </div>
                                <h4><?php echo esc_html($item['label']); ?></h4>
                            </div>
                            <h3><?php echo esc_html(cubestheme_landing_price_text($item['variation'])); ?></h3>
                            <?php if ($description) : ?>
                                <p><?php echo esc_html($description); ?></p>
                            <?php endif; ?>
                            <?php if ($bullets) : ?>
                                <div class="pricing-inclusions">
                                    <ul class="list-unstyled">
                                        <?php foreach ($bullets as $bullet) : ?>
                                            <li>
                                                <img src="<?php echo esc_url($check_icon); ?>" alt="">
                                                <span><?php echo esc_html($bullet); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <a href="<?php echo esc_url(cubestheme_landing_cart_url($page_id, $item)); ?>" class="btn <?php echo $highlight ? 'btn-primary' : 'btn-white'; ?>"><?php echo esc_html($button); ?></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($faq_rows) : ?>
<section class="faq">
    <div class="container">
        <div class="top-section animation" data-animation="slideUp" data-delay="0.08s">
            <?php if (cubestheme_landing_value($page_id, 'landing_faq_label')) : ?>
                <span class="label"><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_faq_label')); ?></span>
            <?php endif; ?>
            <?php if (cubestheme_landing_value($page_id, 'landing_faq_title')) : ?>
                <h2><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_faq_title')); ?></h2>
            <?php endif; ?>
        </div>
        <div class="accordion animation" data-animation="slideUp" data-delay="0.12s">
            <?php foreach ($faq_rows as $index => $faq) : ?>
                <?php
                $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
                $open = $index === 0;
                ?>
                <div class="box accordion-item<?php echo $open ? ' is-active' : ''; ?>" data-accordion-item>
                    <div class="single-accordion">
                        <button class="accordion-trigger" type="button" id="faq-trigger-<?php echo esc_attr($number); ?>" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="faq-panel-<?php echo esc_attr($number); ?>" data-accordion-trigger>
                            <div class="accordion-head d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center title-holder">
                                    <span class="border"><span><?php echo esc_html($number); ?></span></span>
                                    <h4 class="accordion-title"><?php echo esc_html($faq['question'] ?? ''); ?></h4>
                                </div>
                                <span class="accordion-icon" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M5 8L10 13L15 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>
                            </div>
                        </button>
                        <div class="accordion-panel" id="faq-panel-<?php echo esc_attr($number); ?>" role="region" aria-labelledby="faq-trigger-<?php echo esc_attr($number); ?>" data-accordion-panel>
                            <p><?php echo esc_html($faq['answer'] ?? ''); ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (cubestheme_landing_value($page_id, 'landing_faq_button') && cubestheme_landing_value($page_id, 'landing_faq_url')) : ?>
            <div class="faq-footer animation" data-animation="slideUp" data-delay="0.18s">
                <a href="<?php echo esc_url(cubestheme_landing_value($page_id, 'landing_faq_url')); ?>" class="btn btn-primary"><?php echo esc_html(cubestheme_landing_value($page_id, 'landing_faq_button')); ?></a>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
