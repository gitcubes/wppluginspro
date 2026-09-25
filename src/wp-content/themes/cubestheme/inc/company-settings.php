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

        update_option("cubestheme_company_logo_id", absint($_POST["company_logo_id"] ?? 0));
        update_option("cubestheme_brevo_list_id", absint($_POST["brevo_list_id"] ?? 0));
        $brevo_api_key = sanitize_text_field(wp_unslash($_POST["brevo_api_key"] ?? ""));
        if ($brevo_api_key !== "") {
            update_option("cubestheme_brevo_api_key", $brevo_api_key);
        }
        
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
    $company_logo_id = absint(get_option("cubestheme_company_logo_id"));
    $brevo_list_id = absint(get_option("cubestheme_brevo_list_id"));
    $saved_brevo_key = get_option("cubestheme_brevo_api_key");
    $brevo_api_key_set = is_string($saved_brevo_key) && $saved_brevo_key !== "";
    $company_logo_url = $company_logo_id ? wp_get_attachment_image_url($company_logo_id, 'medium') : '';
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
                        <label><?php esc_html_e('Company logo', 'cubestheme'); ?></label>
                    </th>
                    <td>
                        <input type="hidden" id="company_logo_id" name="company_logo_id" value="<?php echo esc_attr($company_logo_id); ?>">
                        <img id="company_logo_preview" src="<?php echo esc_url($company_logo_url); ?>" alt="" style="display:<?php echo $company_logo_url ? 'block' : 'none'; ?>;max-width:180px;height:auto;margin:0 0 12px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:8px;">
                        <button type="button" class="button" id="company_logo_select"><?php esc_html_e('Upload logo', 'cubestheme'); ?></button>
                        <button type="button" class="button" id="company_logo_remove" style="display:<?php echo $company_logo_url ? 'inline-block' : 'none'; ?>;"><?php esc_html_e('Remove logo', 'cubestheme'); ?></button>
                        <p class="description"><?php esc_html_e('Shown at the top of customer invoices. Use a PNG with a transparent background.', 'cubestheme'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="brevo_list_id"><?php esc_html_e('Brevo list ID', 'cubestheme'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="brevo_list_id" name="brevo_list_id" value="<?php echo esc_attr($brevo_list_id); ?>" min="0" class="small-text">
                        <p class="description"><?php esc_html_e('Homepage newsletter signups are added to this Brevo list. Find the ID in Brevo under Contacts, Lists.', 'cubestheme'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="brevo_api_key"><?php esc_html_e('Brevo API key', 'cubestheme'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="brevo_api_key" name="brevo_api_key" value="" class="regular-text" autocomplete="off" placeholder="<?php echo $brevo_api_key_set ? esc_attr__('Saved. Paste a new key to replace it.', 'cubestheme') : ''; ?>">
                        <p class="description"><?php esc_html_e('This is the contacts API key, not the SMTP key. In Brevo open SMTP & API, then API keys, and create a key. Leave this blank to keep the saved key.', 'cubestheme'); ?></p>
                    </td>
                </tr>
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

function cubestheme_company_logo_media($hook)
{
    if ($hook !== 'toplevel_page_tut_theme_settings') {
        return;
    }

    wp_enqueue_media();
    wp_add_inline_script('jquery', "
        jQuery(function ($) {
            var frame;
            $('#company_logo_select').on('click', function (event) {
                event.preventDefault();
                if (frame) {
                    frame.open();
                    return;
                }
                frame = wp.media({
                    title: 'Company logo',
                    button: { text: 'Use this logo' },
                    multiple: false
                });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#company_logo_id').val(attachment.id);
                    $('#company_logo_preview').attr('src', attachment.url).show();
                    $('#company_logo_remove').show();
                });
                frame.open();
            });
            $('#company_logo_remove').on('click', function (event) {
                event.preventDefault();
                $('#company_logo_id').val('0');
                $('#company_logo_preview').attr('src', '').hide();
                $(this).hide();
            });
        });
    ");
}

add_action('admin_enqueue_scripts', 'cubestheme_company_logo_media');
