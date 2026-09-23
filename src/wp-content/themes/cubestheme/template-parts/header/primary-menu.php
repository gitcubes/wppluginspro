<?php
global $wp;

$args = isset($args) && is_array($args) ? $args : array();
$linkClass = $args['link_class'] ?? '';
$submenuLinkClass = $args['submenu_link_class'] ?? $linkClass;
$menuLocations = get_nav_menu_locations();
$primaryMenuID = $menuLocations['primary-menu'] ?? 0;
$primaryMenuItems = $primaryMenuID ? wp_get_nav_menu_items($primaryMenuID) : array();

if (empty($primaryMenuItems)) {
    return;
}

$currentURL = trailingslashit(home_url($wp->request ?? ''));
$topLevelItems = array();
$subMenuItemsByParent = array();

foreach ($primaryMenuItems as $primaryMenuItem) {
    $parentID = (int) $primaryMenuItem->menu_item_parent;

    if ($parentID === 0) {
        $topLevelItems[] = $primaryMenuItem;
        continue;
    }

    if (!isset($subMenuItemsByParent[$parentID])) {
        $subMenuItemsByParent[$parentID] = array();
    }

    $subMenuItemsByParent[$parentID][] = $primaryMenuItem;
}

foreach ($topLevelItems as $primaryMenuItem) {
    $activeClass = '';
    $primaryMenuURL = untrailingslashit($primaryMenuItem->url);
    $subMenuItems = $subMenuItemsByParent[$primaryMenuItem->ID] ?? array();

    if (untrailingslashit($currentURL) === $primaryMenuURL) {
        $activeClass = 'active';
    }

    foreach ($subMenuItems as $subMenuItem) {
        if (untrailingslashit($currentURL) === untrailingslashit($subMenuItem->url)) {
            $activeClass = 'active';
            break;
        }
    }

    $desktopLinkClass = trim($linkClass . ' ' . $activeClass);
?>
    <a href="<?php echo esc_url($primaryMenuItem->url); ?>"
        <?php echo $desktopLinkClass ? ' class="' . esc_attr($desktopLinkClass) . '"' : ''; ?>>
        <?php echo esc_html($primaryMenuItem->title); ?>
    </a>
    <?php

    foreach ($subMenuItems as $subMenuItem) {
        $submenuActiveClass = untrailingslashit($currentURL) === untrailingslashit($subMenuItem->url) ? 'active' : '';
        $submenuClasses = trim($submenuLinkClass . ' is-submenu-link ' . $submenuActiveClass);
    ?>
        <a href="<?php echo esc_url($subMenuItem->url); ?>"
            <?php echo $submenuClasses ? ' class="' . esc_attr($submenuClasses) . '"' : ''; ?>>
            <?php echo esc_html($subMenuItem->title); ?>
        </a>
<?php
    }
}
?>