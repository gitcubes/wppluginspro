<?php
$menuLocations = get_nav_menu_locations();
$socialMenuID = $menuLocations['social-menu'];
$socialMenuItems = wp_get_nav_menu_items($socialMenuID);

if (!empty($socialMenuItems)) {
?>
    <div class="social">
        <?php
        foreach ($socialMenuItems as $socialMenuItem) {
            if ($socialMenuItem->menu_item_parent == 0) {
        ?>
                <a href="<?php echo $socialMenuItem->url; ?>" target="_blank"><?php echo strtolower($socialMenuItem->title); ?></a>
        <?php
            }
        }
        ?>
    </div>
<?php
}
