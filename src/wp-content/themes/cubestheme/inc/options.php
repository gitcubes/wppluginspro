<?php

function cubestheme_option_page()
{

    add_menu_page(
        'Option Page',
        'Header Footer Code',
        'administrator',
        'option_page',
        'cubestheme_banners',
        'dashicons-id',
        24
    );
}
add_action('admin_menu', 'cubestheme_option_page');

function cubestheme_banner_setings()
{
    register_setting('cubestheme_banners', 'cubestheme_head_code');
    register_setting('cubestheme_banners', 'cubestheme_footer_code');
}
add_action('init', 'cubestheme_banner_setings');

function cubestheme_banners()
{
?>
    <h1><?php printf(__('Banner Positions', 'cubes_theme')); ?></h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('cubestheme_banners');
        do_settings_sections('cubestheme_banners')
        ?>

        <table class="form-table">
            <tr>
                <th><?php printf(__('Head Code', 'cubestheme')); ?></th>
                <td>
                    <label style="display: block"><?php printf('Head Scrips or Style', 'cubestheme') ?></label>
                    <textarea cols="150" rows="15" name="cubestheme_head_code"><?php echo get_option('cubestheme_head_code')  ?></textarea>
                </td>
            </tr>
            
            <tr>
                <th><?php printf(__('Footer Code', 'cubes_theme')); ?></th>
                <td>
                    <label style="display: block"><?php printf(__('Footer Scripts', 'cubestheme')) ?></label>
                    <textarea cols="150" rows="15" name="cubestheme_footer_code"><?php echo get_option('cubestheme_footer_code')  ?></textarea>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
<?php

}
