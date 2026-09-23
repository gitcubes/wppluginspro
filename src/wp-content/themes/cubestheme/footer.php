</main>

<?php
$menu_locations = get_nav_menu_locations();

$footer_menu_id = $menu_locations['footer-menu'] ?? 0;
$footer_menu_items = $footer_menu_id ? wp_get_nav_menu_items($footer_menu_id) : array();

$static_menu_id = $menu_locations['static-menu'] ?? 0;
$static_menu_items = $static_menu_id ? wp_get_nav_menu_items($static_menu_id) : array();

$footer_nav_delays = ['0.14s', '0.2s', '0.26s', '0.32s', '0.38s'];

$companyName = get_option('cubestheme_company_name_text');

global $wp;
$current_url = trailingslashit(home_url($wp->request));
?>

<footer>
    <div class="container">
        <div class="box animation" data-animation="slideUp" data-delay="0.05s">
            <div class="content">
                <div class="footer-top">
                    <div class="footer-callout animation" data-animation="slideRight" data-delay="0.1s">
                        <h2><?php printf(esc_html__('Ready to build on a solid plugin foundation?', 'cubestheme')); ?>
                        </h2>
                        <a href="/plugins"
                            class="btn btn-primary"><?php printf(esc_html__('Explore plugins', 'cubestheme')); ?></a>
                    </div>

                    <?php if (!empty($footer_menu_items)) : ?>
                        <div class="footer-nav">
                            <?php $index = 0; ?>

                            <?php foreach ($footer_menu_items as $footer_menu_item) : ?>
                                <?php if ((int) $footer_menu_item->menu_item_parent !== 0) continue; ?>

                                <?php
                                $footer_menu_item_id = $footer_menu_item->ID;
                                $sub_menu_items = array();

                                foreach ($footer_menu_items as $sub_item) {
                                    if ((int) $sub_item->menu_item_parent === (int) $footer_menu_item_id) {
                                        $sub_menu_items[] = $sub_item;
                                    }
                                }

                                $delay = isset($footer_nav_delays[$index]) ? $footer_nav_delays[$index] : '0.14s';
                                ?>

                                <div class="footer-nav-item animation" data-animation="slideUp"
                                    data-delay="<?php echo esc_attr($delay); ?>">
                                    <span class="label"><?php echo esc_html($footer_menu_item->title); ?></span>

                                    <?php if (!empty($sub_menu_items)) : ?>
                                        <ul class="list-unstyled">
                                            <?php foreach ($sub_menu_items as $sub_menu_item) : ?>
                                                <?php
                                                $item_url = trailingslashit($sub_menu_item->url);
                                                $active_class = $current_url === $item_url ? 'active' : '';
                                                ?>
                                                <li>
                                                    <a href="<?php echo esc_url($sub_menu_item->url); ?>"
                                                        class="<?php echo esc_attr($active_class); ?>">
                                                        <?php echo esc_html($sub_menu_item->title); ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>

                                <?php $index++; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="footer-bottom animation" data-animation="slideUp" data-delay="0.32s">
                    <div class="copyright">
                        <p>
                            <?php printf(esc_html__('© %s WP Plugins Pro. All rights reserved.', 'cubestheme'), date('Y')); ?>
                        </p>
                        <p>
                            <?php
                            printf(
                                wp_kses_post(__('Developed by %s', 'cubestheme')),
                                '<a href="https://cubes.rs" target="_blank" rel="noopener noreferrer">' . esc_html(!empty($companyName) ? $companyName : 'Cubes') . '</a>'
                            );
                            ?>
                        </p>
                    </div>

                    <?php if (!empty($static_menu_items)) : ?>
                        <div class="footer-links">
                            <?php foreach ($static_menu_items as $static_menu_item) : ?>
                                <?php if ((int) $static_menu_item->menu_item_parent !== 0) continue; ?>

                                <a href="<?php echo esc_url($static_menu_item->url); ?>">
                                    <?php echo esc_html($static_menu_item->title); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>

<?php
if (!empty(get_option('cubestheme_footer_code'))) {
    if (!isset($_GET['showbanners']) || $_GET['showbanners'] != 0) {
        echo get_option('cubestheme_footer_code');
    }
}
?>
</body>

</html>