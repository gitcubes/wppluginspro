<?php
/**
 * Additional custom settings
 *
 * @package CubesShop
 * @subpackage CubesShop
 * @since 1.0
 */

/*
 * Custom Settings
 */

function theme_settings_page() {
    
}

function theme_front_page_settings() {
    $company_name_text = "";
    $company_address_text = "";
    $company_email_text = "";
    $company_phone1_text = "";
    $company_phone2_text = "";
    $company_working_time_text = "";
    $company_geo_text = "";
    $footer_text = "";
    $copyright_text = "";
    
    $message = "";
    if (isset($_POST["update_custom_settings"])) {
        
        // Do the saving
        $company_name_text = esc_attr($_POST["company_name_text"]);
        update_option("cubestheme_company_name_text", $company_name_text);

        $company_address_text = esc_attr($_POST["company_address_text"]);
        update_option("cubestheme_company_address_text", $company_address_text);
        
        $company_email_text = esc_attr($_POST["company_email_text"]);
        update_option("cubestheme_company_email_text", $company_email_text);
        
        $company_phone1_text = esc_attr($_POST["company_phone1_text"]);
        update_option("cubestheme_company_phone1_text", $company_phone1_text);
        
        $company_phone2_text = esc_attr($_POST["company_phone2_text"]);
        update_option("cubestheme_company_phone2_text", $company_phone2_text);
        
        $company_working_time_text = esc_attr($_POST["company_working_time_text"]);
        update_option("cubestheme_company_working_time_text", $company_working_time_text);
        
        $company_geo_text = esc_attr($_POST["company_geo_text"]);
        update_option("cubestheme_company_geo_text", $company_geo_text);
        
        $footer_text = esc_attr($_POST["footer_text"]);
        update_option("cubestheme_footer_text", $footer_text);

        $copyright_text = esc_attr($_POST["copyright_text"]);
        update_option("cubestheme_copyright_text", $copyright_text);
        
        $message = "Custom Settings have been updated successfully.";
    }
    
    $company_name_text = stripslashes(get_option("cubestheme_company_name_text"));
    $company_address_text = stripslashes(get_option("cubestheme_company_address_text"));
    $company_email_text = stripslashes(get_option("cubestheme_company_email_text"));
    $company_phone1_text = stripslashes(get_option("cubestheme_company_phone1_text"));
    $company_phone2_text = stripslashes(get_option("cubestheme_company_phone2_text"));
    $company_working_time_text = stripslashes(get_option("cubestheme_company_working_time_text"));
    $company_geo_text = stripslashes(get_option("cubestheme_company_geo_text"));
 
    $footer_text = stripslashes(get_option("cubestheme_footer_text"));
    $copyright_text = stripslashes(get_option("cubestheme_copyright_text"));
    ?>
    <div class="wrap">
    <h2>Company Info</h2>
        <form method="POST" action="">
            <?php
            if(!empty($message)){
                echo "<p style='color: green'>$message</p>";
            }
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="Company Name">
                            <?php printf(__('Company Name:', 'cubestheme')) ?>
                        </label> 
                    </th>
                    <td>
                        <input type="text" name="company_name_text" value="<?php echo $company_name_text; ?>" style="min-width: 430px" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="Company Address">
                            <?php printf(__('Company Address:', 'cubestheme')) ?>
                        </label> 
                    </th>
                    <td>
                        <input type="text" name="company_address_text" value="<?php echo $company_address_text; ?>" style="min-width: 430px" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="Company Phone1">
                            <?php printf(__('Company Phone1:', 'cubestheme')) ?>
                        </label> 
                    </th>
                    <td>
                        <input type="text" name="company_phone1_text" value="<?php echo $company_phone1_text; ?>" style="min-width: 430px" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="Company Phone2">
                        <?php printf(__('Company Phone2:', 'cubestheme')) ?>
                        </label> 
                    </th>
                    <td>
                        <input type="text" name="company_phone2_text" value="<?php echo $company_phone2_text; ?>" style="min-width: 430px" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="Company Email">
                        <?php printf(__('Company Email:', 'cubestheme')) ?>
                        </label> 
                    </th>
                    <td>
                        <input type="text" name="company_email_text" value="<?php echo $company_email_text; ?>" style="min-width: 430px" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="Company Working Time">
                            <?php printf(__('Company Working Time:', 'cubestheme')) ?>
                        </label> 
                    </th>
                    <td>
                        <input type="text" name="company_working_time_text" value="<?php echo $company_working_time_text; ?>" style="min-width: 430px" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="Company Geo">
                            <?php printf(__('Company Geo(long, lat):', 'cubestheme')) ?>
                        </label> 
                    </th>
                    <td>
                        <input type="text" name="company_geo_text" value="<?php echo $company_geo_text; ?>" style="min-width: 430px" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="Footer Text">
                        <?php printf(__('Footer Text:', 'cubestheme')) ?>
                        </label> 
                    </th>
                    <td>
                        <textarea name="footer_text" style="min-width: 430px; min-height: 140px;"><?php echo $footer_text; ?></textarea>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="Copyright Text">
                            <?php printf(__('Copyright text:', 'cubestheme')) ?> 
                        </label> 
                    </th>
                    <td>
                        <input type="text" name="copyright_text" value="<?php echo $copyright_text; ?>" style="min-width: 430px" />
                    </td>
                </tr>
            </table>
            <p>
                <input type="hidden" name="update_custom_settings" value="1" />
                <input type="submit" value="Save settings" class="button-primary"/>
            </p>
        </form>
    </div>
    <?php
}

function setup_theme_admin_menus() {
    add_menu_page('Theme settings', 'Company info', 'manage_options', 'tut_theme_settings', 'theme_settings_page', 'dashicons-hammer', 20);

    add_submenu_page('tut_theme_settings', 'Site Settings', 'Site', 'manage_options', 'tut_theme_settings', 'theme_front_page_settings');
}

// This tells WordPress to call the function named "setup_theme_admin_menus"
// when it's time to create the menu pages.
add_action("admin_menu", "setup_theme_admin_menus");
