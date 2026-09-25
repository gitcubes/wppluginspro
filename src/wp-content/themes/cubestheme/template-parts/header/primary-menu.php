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

$currentPath = cubestheme_nav_path(home_url($wp->request ?? ''));
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
    $subMenuItems = $subMenuItemsByParent[$primaryMenuItem->ID] ?? array();

    if (cubestheme_nav_is_current($primaryMenuURL, $currentPath)) {
        $activeClass = 'active';
    }

    foreach ($subMenuItems as $subMenuItem) {
        if (cubestheme_nav_is_current($subMenuItem->url, $currentPath)) {
            $activeClass = 'active';
            break;
        }
    }

    $desktopLinkClass = trim($linkClass . ' ' . $activeClass);
?>
    <a href="<?php echo esc_url($primaryMenuItem->url); ?>"
        <?php echo $desktopLinkClass ? ' class="' . esc_attr($desktopLinkClass) . '"' : ''; ?>
        <?php echo $activeClass ? ' aria-current="page"' : ''; ?>>
        <?php echo esc_html($primaryMenuItem->title); ?>
    </a>
    <?php

    foreach ($subMenuItems as $subMenuItem) {
        $submenuActiveClass = cubestheme_nav_is_current($subMenuItem->url, $currentPath) ? 'active' : '';
        $submenuClasses = trim($submenuLinkClass . ' is-submenu-link ' . $submenuActiveClass);
    ?>
        <a href="<?php echo esc_url($subMenuItem->url); ?>"
            <?php echo $submenuClasses ? ' class="' . esc_attr($submenuClasses) . '"' : ''; ?>
            <?php echo $submenuActiveClass ? ' aria-current="page"' : ''; ?>>
            <?php echo esc_html($subMenuItem->title); ?>
        </a>
<?php
    }
}
?>