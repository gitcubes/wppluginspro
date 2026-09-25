<?php

/**
 * news-theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package news-theme
 */
if (!defined('_S_VERSION')) {
    // Replace the version number of the theme on each release.
    define('_S_VERSION', '1.0.0');
}

// INCLUDE FILES

function themeVersion()
{
    return  '1.0.28';
}

// INCLUDE FILES

function cubestheme_scripts()
{

    wp_enqueue_script('jquerymin', get_template_directory_uri() . '/frontend/js/jquery.min.js', array(), '3.4.1', false);
    wp_enqueue_script('validate', get_template_directory_uri() . '/frontend/js/jquery.validate.min.js', array('jquerymin'), '1.19.1', true);
    wp_enqueue_script('lottie', get_template_directory_uri() . '/frontend/js/lottie-player.js', array(), '1.19.1', true);
    wp_enqueue_script('fancybox', get_template_directory_uri() . '/frontend/js/jquery.fancybox.min.js', array('jquerymin'), '3.3.5', true);
    wp_enqueue_script('main', get_template_directory_uri() . '/frontend/js/main.js', array('jquerymin'), themeVersion(), true);
}

add_action('wp_enqueue_scripts', 'cubestheme_header_cart_style', 200);

function cubestheme_header_cart_style()
{
    wp_enqueue_style('header-cart', get_template_directory_uri() . '/frontend/css/header-cart.css', array(), themeVersion());
}

add_action('wp_enqueue_scripts', 'cubestheme_scripts');

add_filter('upload_mimes', function ($mimes) {
    $mimes['json'] = 'application/json';
    return $mimes;
});

add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename, $mimes) {
    $filetype = wp_check_filetype($filename, $mimes);

    return [
        'ext'             => $filetype['ext'],
        'type'            => $filetype['type'],
        'proper_filename' => $data['proper_filename'],
    ];
}, 10, 4);

function cubestheme_support()
{
    // Title tag support
    add_theme_support('title-tag');

    // Custom Logo support
    add_theme_support('custom-logo', array(
        'height' => 48,
        'width' => 48,
        'flex-width' => true,
        'flex-height' => true
    ));

    //Feature Image support
    add_theme_support('post-thumbnails');
    add_theme_support('post-formats', array('standard', 'video', 'gallery'));
    remove_theme_support('widgets-block-editor');

    // AD image size
    add_image_size('lead-news', 601, 420, true);
    add_image_size('latest-news', 290, 188, true);



    load_theme_textdomain('cubestheme', get_template_directory() . '/languages');

    // Add default posts and comments RSS feed links to head.
    add_theme_support('automatic-feed-links');
}

add_action('after_setup_theme', 'cubestheme_support');

function cubestheme_filter_custom_logo_markup($html)
{
    if (empty($html) || strpos($html, 'logo-details') !== false) {
        return $html;
    }

    $logo_details = '<div class="logo-details"><span>WP Plugins Pro</span><span>By Web Solution Hub</span></div>';

    if (strpos($html, 'class="custom-logo-link"') !== false) {
        $html = preg_replace('/class="custom-logo-link"/', 'class="custom-logo-link brand"', $html, 1);
    }

    return preg_replace('/\s*<\/a>\s*$/', $logo_details . '</a>', $html, 1);
}

add_filter('get_custom_logo', 'cubestheme_filter_custom_logo_markup');

function cubestheme_menus()
{

    register_nav_menus(array(
        'primary-menu' => __('Primary Menu', 'cubestheme'),
        'footer-menu' => __('Footer Menu', 'cubestheme'),
        'static-menu' => __('Static Menu', 'cubestheme'),
    ));
}

add_action('init', 'cubestheme_menus');

function cubestheme_create_post_type()
{
    register_taxonomy('plugin_category', array('product'), array(
        'labels' => array(
            'name' => __('Plugin Categories', 'cubestheme'),
            'singular_name' => __('Plugin Category', 'cubestheme'),
        ),
        'public' => true,
        'hierarchical' => false,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => array(
            'slug' => 'plugin-category',
        ),
    ));
}


add_action('init', 'cubestheme_create_post_type');

function cubestheme_flush_removed_plugin_cpt()
{
    if (get_option('cubestheme_wp_plugins_pro_removed') === '1') {
        return;
    }

    flush_rewrite_rules(false);
    update_option('cubestheme_wp_plugins_pro_removed', '1', false);
}

add_action('init', 'cubestheme_flush_removed_plugin_cpt', 99);

function cubestheme_migrate_featured_plugins_to_products()
{
    if (get_option('cubestheme_featured_products_migrated') === '1' || !class_exists('WC_Product_Simple')) {
        return;
    }

    $front_id = (int) get_option('page_on_front');
    $selected = $front_id > 0 ? get_post_meta($front_id, 'featured_plugins', true) : array();
    if (!is_array($selected) || $selected === array()) {
        update_option('cubestheme_featured_products_migrated', '1');
        return;
    }

    $product_ids = array();

    foreach ($selected as $old_id) {
        $old_id = (int) $old_id;
        if (get_post_type($old_id) === 'product') {
            $product_ids[] = $old_id;
            continue;
        }

        if (get_post_type($old_id) !== 'wp_plugins_pro') {
            continue;
        }

        $product = new WC_Product_Simple();
        $product->set_name(get_the_title($old_id));
        $product->set_slug(get_post_field('post_name', $old_id));
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        $product_id = $product->save();

        if (!$product_id) {
            continue;
        }

        $meta = get_post_meta($old_id);
        foreach ($meta as $key => $values) {
            if (strpos($key, 'plugin_') !== 0 && strpos($key, '_plugin_') !== 0) {
                continue;
            }

            foreach ($values as $value) {
                add_post_meta($product_id, $key, maybe_unserialize($value));
            }
        }

        $thumbnail_id = (int) get_post_thumbnail_id($old_id);
        if ($thumbnail_id > 0) {
            set_post_thumbnail($product_id, $thumbnail_id);
        }

        $terms = wp_get_object_terms($old_id, 'plugin_category', array('fields' => 'ids'));
        if (!is_wp_error($terms) && $terms) {
            wp_set_object_terms($product_id, $terms, 'plugin_category');
        }

        $slug = (string) get_post_field('post_name', $old_id);
        update_post_meta($product_id, 'wsh_plugin_slug', $slug);
        update_post_meta($product_id, 'wsh_show_in_catalog', '1');

        $landing_slug = strpos($slug, 'wsh-') === 0 ? substr($slug, 4) : $slug;
        $landing = get_page_by_path($landing_slug);
        if (!$landing instanceof WP_Post) {
            $landing = get_page_by_path($slug);
        }
        if ($landing instanceof WP_Post) {
            update_post_meta($product_id, 'wsh_landing_page_id', $landing->ID);
        }

        $product_ids[] = $product_id;
    }

    if ($product_ids !== array()) {
        update_post_meta($front_id, 'featured_plugins', $product_ids);
    }

    update_option('cubestheme_featured_products_migrated', '1');
}

add_action('init', 'cubestheme_migrate_featured_plugins_to_products', 30);


/*
function cubestheme_init_sidebar()
{
    register_sidebar(array(
        'id' => 'sidebar_1',
        'name' => __('Homepage  Sidebar', 'cubestheme'),
        'description' => __('Sidebar for firstsection','cubestheme'),
        'before_widget' => '<div id="%1$s" class="widget  %2$s">',
        'after_widget' => '</div>',
        'before_title' => '<h3 class="widget-title">',
        'after_title' => '</h3>',
    ));

    register_sidebar(array(
        'id' => 'sidebar',
        'name' => __('Default Sidebar'),
        'description' => __('Default Sidebar'),
        'before_widget' => '<div id="%1$s" class="widget  %2$s">',
        'after_widget' => '</div>',
        'before_title' => '<h3 class="widget-title">',
        'after_title' => '</h3>',
    ));
}

add_action('widgets_init', 'cubestheme_init_sidebar');
*/



// Widgets


function cubestheme_add_editor_styles()
{
    add_editor_style('custom-editor-style.css');
}
add_action('init', 'cubestheme_add_editor_styles');



// Recaptcha
require get_template_directory() . '/inc/cubesRecaptcha.php';
/**
  Theme Option Page
 */
require get_template_directory() . '/inc/options.php';

/**
  Company Settings
 */
require get_template_directory() . '/inc/company-settings.php';
require get_template_directory() . '/inc/account-auth.php';
require get_template_directory() . '/inc/plugin-landing.php';
require get_template_directory() . '/inc/invoice.php';
require get_template_directory() . '/inc/newsletter.php';

/**
  Theme Widgets pagee
 */

//require get_template_directory() . '/inc/widgets/banner-widget.php';
//require get_template_directory() . '/inc/widgets/tab-news-widget.php';
//require get_template_directory() . '/inc/widgets/recommended-news-widget.php';
//require get_template_directory() . '/inc/widgets/premium-news-widget.php';
//require get_template_directory() . '/inc/widgets/printed-edition-widget.php';
//
//require get_template_directory() . '/inc/widgets/category-widget.php';
//require get_template_directory() . '/inc/widgets/current-post-related-news-widget.php';





/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
if (defined('JETPACK__VERSION')) {
    require get_template_directory() . '/inc/jetpack.php';
}

function displayPostFormatClass($format)
{
    $postClass = '';
    switch ($format) {
        case 'video':
            $postClass = 'video-item';
            break;
        case 'gallery':
            $postClass = 'gallery-item';
            break;
    }
    echo $postClass;
}

//Exclude pages from WordPress Search
if (!is_admin()) {

    function cubestheme_search_filter($query)
    {
        if ($query->is_search) {
            $query->set('post_type', 'post');
        }
        return $query;
    }

    add_filter('pre_get_posts', 'cubestheme_search_filter');
}

function units_time_ago()
{
    return str_replace(' ', '</span><span>', sprintf(esc_html__('%s', 'cubes_theme'), human_time_diff(get_the_time('U'), current_time('timestamp'))));
}

/**
 * Get most viewed posts.
 * 
 * @param array $args
 * @return array
 */
if (!function_exists('pvc_get_most_viewed_posts')) {

    function pvc_get_most_viewed_posts($args = array())
    {
        $args = array_merge(
            array(
                'posts_per_page' => 10,
                'order' => 'desc',
                'post_type' => 'post'
            ),
            $args
        );

        $args = apply_filters('pvc_get_most_viewed_posts_args', $args);

        // force to use filters
        $args['suppress_filters'] = false;

        // force to use post views as order
        $args['orderby'] = 'post_views';

        // force to get all fields
        $args['fields'] = '';

        return apply_filters('pvc_get_most_viewed_posts', get_posts($args), $args);
    }
}




//COMMENTS FORM ADD PLACEHOLDER

function my_update_comment_fields($fields)
{

    $commenter = wp_get_current_commenter();
    $req       = get_option('require_name_email');
    $label     = $req ? '*' : ' ' . __('(optional)', 'cubestheme');
    $aria_req  = $req ? "aria-required='true'" : '';

    $fields['author'] =
        '<div class="comment-form-author form-group">
			<label for="author" class="d-none">' . __("Your name*", "cubestheme") . $label . '</label>
			<input required id="author" class="form-control" name="author" type="text" placeholder="' . esc_attr__("Your Name*", "cubestheme") . '" value="' . esc_attr($commenter['comment_author']) .
        '" size="30" ' . $aria_req . ' />
		<div class="invalid-feedback"></div>
                </div>';

    $fields['email'] =
        '<div class="comment-form-email form-group">
			<label for="email" class="d-none">' . __("Your email*", "cubestheme") . $label . '</label>
			<input required id="email" class="form-control" name="email" type="email" placeholder="' . esc_attr__("Your Email*", "cubestheme") . '" value="' . esc_attr($commenter['comment_author_email']) .
        '" size="30" ' . $aria_req . ' />
                    <div class="invalid-feedback"></div>
		</div>';

    $fields['url'] =
        '<div class="comment-form-url form-group">
			<label for="url" class="d-none">' . __("Your website*", "cubestheme") . '</label>
			<input  id="url" class="form-control" name="url" type="url"  placeholder="' . esc_attr__("Your Website*", "cubestheme") . '" value="' . esc_attr($commenter['comment_author_url']) .
        '" size="30" />
                    <div class="invalid-feedback"></div>
			</div>';

    return $fields;
}
add_filter('comment_form_default_fields', 'my_update_comment_fields');



function my_update_comment_field($comment_field)
{

    $comment_field =
        '<div class="comment-form-comment form-group">
            <label for="comment" class="d-none">' . __("Your message*", "cubestheme") . '</label>
            <textarea required id="comment" class="form-control" name="comment" placeholder="' . esc_attr__("Your Message*", "cubestheme") . '" cols="45" rows="5" aria-required="true"></textarea>
        <div class="invalid-feedback"></div>
        <p style="margin-top: 10px;">' . __('Before posting comments, please visit', 'cubestheme') . ' <a target="_blank" href="/privacy-policy/"><u>' . __('and get acquainted with terms of service.', 'cubestheme') . '</u></a></p>
</div>';

    return $comment_field;
}
add_filter('comment_form_field_comment', 'my_update_comment_field');




add_filter('post_gallery', 'my_post_gallery', 10, 2);

function my_post_gallery($output, $attr)
{
    global $post;

    if (isset($attr['orderby'])) {
        $attr['orderby'] = sanitize_sql_orderby($attr['orderby']);
        if (!$attr['orderby'])
            unset($attr['orderby']);
    }

    extract(shortcode_atts(array(
        'order' => 'ASC',
        'orderby' => 'menu_order ID',
        'id' => $post->ID,
        'itemtag' => 'dl',
        'icontag' => 'dt',
        'captiontag' => 'dd',
        'columns' => 3,
        'size' => 'thumbnail',
        'include' => '',
        'exclude' => ''
    ), $attr));

    $id = intval($id);
    if ('RAND' == $order)
        $orderby = 'none';

    if (!empty($include)) {
        $include = preg_replace('/[^0-9,]+/', '', $include);
        $_attachments = get_posts(array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby));

        $attachments = array();
        foreach ($_attachments as $key => $val) {
            $attachments[$val->ID] = $_attachments[$key];
        }
    }

    if (empty($attachments))
        return '';

    // Here's your actual output, you may customize it to your needs


    $fancyBoxId = date('dmyHs');
    $output = "<div class=\"gallery-wrapper\">\n";


    // Now you loop through each attachment
    foreach ($attachments as $id => $attachment) {
        // Fetch the thumbnail (or full image, it's up to you)

        $img = wp_get_attachment_image_src($id, 'full');
        $imgCaption = wp_get_attachment_caption($id);
        $imgSource = get_field('image_source', $id);

        $siteURL = get_template_directory_uri();

        $output .= "<a href='{$img[0]}' class='d-block gallery-item' data-fancybox='{$fancyBoxId}'  data-caption='<div class=\"d-flex flex-wrap justify-content-between\"><p class=\"description\">{$imgCaption}</p> <p class=\"signature\">{$imgSource}</p></div>'>\n";
        $output .= "<figure class='position-relative mb-0'>\n";
        $output .= "<img src='{$img[0]}' alt=''>\n";
        $output .= "<p class='image-source'>{$imgSource}</p>\n";
        $output .= "</figure>\n";
        $output .= "<p class='figcaption'>{$imgCaption}</p>\n";
        $output .= "<p class='display-gallery d-flex align-items-center'>\n";
        $output .= "<span class='text-uppercase'>" . __('Gallery', 'cubestheme') . "</span>\n";
        $output .= "</p>\n";

        $output .= "</a>\n";
    }


    $output .= "</div>\n";
    return $output;
}



//function my_post_gallery_old($output, $attr) {
// global $post;

// if (isset($attr['orderby'])) {
//     $attr['orderby'] = sanitize_sql_orderby($attr['orderby']);
//     if (!$attr['orderby'])
//         unset($attr['orderby']);
// }

// extract(shortcode_atts(array(
//     'order' => 'ASC',
//     'orderby' => 'menu_order ID',
//     'id' => $post->ID,
//     'itemtag' => 'dl',
//     'icontag' => 'dt',
//     'captiontag' => 'dd',
//     'columns' => 3,
//     'size' => 'thumbnail',
//     'include' => '',
//     'exclude' => ''
//                 ), $attr));

// $id = intval($id);
// if ('RAND' == $order)
//     $orderby = 'none';

// if (!empty($include)) {
//     $include = preg_replace('/[^0-9,]+/', '', $include);
//     $_attachments = get_posts(array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby));

//     $attachments = array();
//     foreach ($_attachments as $key => $val) {
//         $attachments[$val->ID] = $_attachments[$key];
//     }
// }

// if (empty($attachments))
//     return '';

// // Here's your actual output, you may customize it to your needs
// $output = "<div class=\"gallery-slider mb-2\">\n";
// $output .= "<div class=\"swiper-container gallery-top gallery-top-1\">\n";
// $output .= "<div class=\"swiper-wrapper\">\n";


//   // Now you loop through each attachment
//   foreach ($attachments as $id => $attachment) {
// // Fetch the thumbnail (or full image, it's up to you)
////      $img = wp_get_attachment_image_src($id, 'medium');
////      $img = wp_get_attachment_image_src($id, 'my-custom-image-size');
//      $img = wp_get_attachment_image_src($id, 'full');

//    $output .= "<a data-fancybox=\"gallery\" href=\"{$img[0]}\" class=\"swiper-slide news-item-image\">\n";
//   $output .= "<img src=\"{$img[0]}\"  alt=\"\" />\n";
//   $output .= "</a>\n";
// }
// $output .= "</div>\n";
// $output .= "</div>\n";

// $output .= "<div class=\"swiper-button-next swiper-button-white\"></div>\n";
// $output .= "<div class=\"swiper-button-prev swiper-button-white\"></div>\n";

// $output .= "<div class=\"swiper-container gallery-thumbs gallery-thumbs-1\">\n";
// $output .= "<div class=\"swiper-wrapper\">\n";

//foreach ($attachments as $id => $attachment) {
// // Fetch the thumbnail (or full image, it's up to you)
// $img = wp_get_attachment_image_src($id, 'medium');
////          $img = wp_get_attachment_image_src($id, 'my-custom-image-size');
// //       $img = wp_get_attachment_image_src($id, 'full');

//     $output .= "<div class=\"swiper-slide\">\n";
//     $output .= "<figure class=\"news-item-image\">\n";
//     $output .= "<img src=\"{$img[0]}\" alt=\"\">\n";
//     $output .= "</figure>\n";
//     $output .= "</div>\n";
// }

// $output .= "</div>\n";
// $output .= "</div>\n";
// $output .= "</div>\n";

// return $output;
//}

//Login page start
function cubestheme_custom_login_logo()
{ ?>
    <style type="text/css">
        #login h1 a,
        .login h1 a {
            background-image: url('/wp-content/themes/cubestheme/frontend/img/login-logo.webp');
            background-repeat: no-repeat;
            background-position: center;
            padding: 0px 100px;
            border-radius: 16px;
            background-size: auto;
        }
    </style>
<?php }
add_action('login_enqueue_scripts', 'cubestheme_custom_login_logo');

function cubestheme_custom_login_url()
{
    return home_url();
}
add_filter('login_headerurl', 'cubestheme_custom_login_url');

function cubestheme_login_logo_url_redirect()
{
    return home_url();
}
add_filter('login_headertitle', 'cubestheme_login_logo_url_redirect');

function my_img_caption_shortcode($empty, $attr, $content)
{
    $attr = shortcode_atts(array(
        'id'      => '',
        'align'   => 'alignnone',
        'width'   => '',
        'caption' => ''
    ), $attr);

    if (1 > (int) $attr['width'] || empty($attr['caption'])) {
        return '';
    }

    $credit_id = $attr['id'];
    $credit_id = str_replace('attachment_', '', $credit_id);

    $photographer = get_field('image_source', $credit_id);
    $imageSource = '';
    if ($photographer) :
        $imageSource = '<span class="img-source">' . $photographer . '</span>';
    endif;

    return '<div class="image-wrapper ' . esc_attr($attr['align']) . ' "><div id="attachment_' . $credit_id . '"'
        . 'class="wp-caption ' . esc_attr($attr['align']) . '" >'
        . do_shortcode($content)
        . '<p class="wp-caption-text">' . $attr['caption'] . '' . $imageSource . '</p>'
        . '</div></div>';
}

add_filter('img_caption_shortcode', 'my_img_caption_shortcode', 10, 3);

//WOOCOMMERCE SUPPORT
function mytheme_add_woocommerce_support()
{
    add_theme_support('woocommerce', array(
        'thumbnail_image_width' => 640,
        'single_image_width'    => 800,
        'product_grid'          => array(
            'default_rows'    => 3,
            'min_rows'        => 1,
            'max_rows'        => 6,
            'default_columns' => 3,
            'min_columns'     => 1,
            'max_columns'     => 4,
        ),
    ));

    remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);
}

add_action('after_setup_theme', 'mytheme_add_woocommerce_support');

function cubestheme_is_commerce_view()
{
    if (!function_exists('is_woocommerce')) {
        return false;
    }

    return is_woocommerce() || is_cart() || is_checkout() || is_account_page();
}

function cubestheme_commerce_assets()
{
    if (!cubestheme_is_commerce_view()) {
        return;
    }

    wp_enqueue_style('static-page', get_template_directory_uri() . '/frontend/css/static-page.css', array(), themeVersion());
    wp_enqueue_style('commerce', get_template_directory_uri() . '/frontend/css/commerce.css', array('static-page'), themeVersion());

    if (function_exists('is_account_page') && is_account_page()) {
        wp_enqueue_style('account', get_template_directory_uri() . '/frontend/css/account.css', array('commerce'), themeVersion());
    }
}

add_action('wp_enqueue_scripts', 'cubestheme_commerce_assets', 100);

function cubestheme_plugins_page_url()
{
    $pages = get_posts(array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_key'       => '_wp_page_template',
        'meta_value'     => 'page-for-plugins.php',
    ));

    if ($pages) {
        return get_permalink($pages[0]);
    }

    return home_url('/plugins/');
}

function cubestheme_empty_cart_block($content, $block)
{
    if (is_admin() || ($block['blockName'] ?? '') !== 'woocommerce/empty-cart-block') {
        return $content;
    }

    $plugins_url = cubestheme_plugins_page_url();

    ob_start();
    ?>
    <div data-block-name="woocommerce/empty-cart-block" class="wp-block-woocommerce-empty-cart-block">
        <div class="cart-empty">
            <div class="cart-empty-mark" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                    <path d="M6.5 8h11l-.8 11.2a1 1 0 0 1-1 .8H8.3a1 1 0 0 1-1-.8L6.5 8z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="M9 8V7a3 3 0 0 1 6 0v1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </div>
            <h2><?php esc_html_e('Your cart is empty', 'cubestheme'); ?></h2>
            <p><?php esc_html_e('Choose a plugin and the license will be added here.', 'cubestheme'); ?></p>
            <a class="cart-empty-link" href="<?php echo esc_url($plugins_url); ?>">
                <?php esc_html_e('Explore plugins', 'cubestheme'); ?>
            </a>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

add_filter('render_block', 'cubestheme_empty_cart_block', 20, 2);

function cubestheme_checkout_order_open()
{
    echo '<div class="commerce-order-column">';
}

function cubestheme_checkout_order_close()
{
    echo '</div>';
}

add_action('woocommerce_checkout_before_order_review_heading', 'cubestheme_checkout_order_open', 1);
add_action('woocommerce_checkout_after_order_review', 'cubestheme_checkout_order_close', 99);

function cubestheme_checkout_without_shipping()
{
    return false;
}

add_filter('woocommerce_cart_needs_shipping', 'cubestheme_checkout_without_shipping', 100);
add_filter('woocommerce_cart_needs_shipping_address', 'cubestheme_checkout_without_shipping', 100);
add_filter('woocommerce_cart_contains_subscriptions_needing_shipping', 'cubestheme_checkout_without_shipping', 100);

function cubestheme_checkout_field_options()
{
    if (get_option('cubestheme_checkout_fields') === '1') {
        return;
    }

    update_option('woocommerce_checkout_company_field', 'optional');
    update_option('woocommerce_checkout_address_2_field', 'hidden');
    update_option('cubestheme_checkout_fields', '1');
}

add_action('init', 'cubestheme_checkout_field_options');

function cubestheme_hide_checkout_address_extras($fields)
{
    foreach (array('address_2', 'state') as $key) {
        if (!isset($fields[$key])) {
            continue;
        }
        $fields[$key]['required'] = false;
        $fields[$key]['hidden'] = true;
    }

    return $fields;
}

add_filter('woocommerce_default_address_fields', 'cubestheme_hide_checkout_address_extras');
add_filter('woocommerce_get_country_locale_default', 'cubestheme_hide_checkout_address_extras');

function cubestheme_hide_checkout_state($locale)
{
    foreach ($locale as $country => $fields) {
        $locale[$country]['state']['required'] = false;
        $locale[$country]['state']['hidden'] = true;
        $locale[$country]['address_2']['required'] = false;
        $locale[$country]['address_2']['hidden'] = true;
    }

    return $locale;
}

add_filter('woocommerce_get_country_locale', 'cubestheme_hide_checkout_state');

function cubestheme_checkout_fields($fields)
{
    unset($fields['billing']['billing_address_2'], $fields['billing']['billing_state']);
    unset($fields['shipping']['shipping_address_2'], $fields['shipping']['shipping_state']);

    if (isset($fields['billing']['billing_company'])) {
        $fields['billing']['billing_company']['label'] = __('Company name', 'cubestheme');
        $fields['billing']['billing_company']['required'] = false;
        $fields['billing']['billing_company']['priority'] = 30;
    }

    $fields['billing']['billing_vat'] = array(
        'type' => 'text',
        'label' => __('VAT ID', 'cubestheme'),
        'required' => false,
        'class' => array('form-row-wide'),
        'priority' => 35,
    );

    return $fields;
}

add_filter('woocommerce_checkout_fields', 'cubestheme_checkout_fields');

function cubestheme_save_checkout_vat($order_id)
{
    if (empty($_POST['billing_vat'])) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $order->update_meta_data('VAT ID', sanitize_text_field(wp_unslash($_POST['billing_vat'])));
    $order->save();
}

add_action('woocommerce_checkout_update_order_meta', 'cubestheme_save_checkout_vat');

function cubestheme_register_vat_field()
{
    if (!function_exists('woocommerce_register_additional_checkout_field')) {
        return;
    }

    woocommerce_register_additional_checkout_field(array(
        'id' => 'cubestheme/vat-id',
        'label' => __('VAT ID', 'cubestheme'),
        'location' => 'address',
        'type' => 'text',
        'required' => false,
    ));
}

add_action('woocommerce_init', 'cubestheme_register_vat_field');

function cubestheme_header_cart_count()
{
    if (!function_exists('WC') || !WC()->cart) {
        return 0;
    }

    return (int) WC()->cart->get_cart_contents_count();
}

function cubestheme_header_cart_link()
{
    $count = cubestheme_header_cart_count();
    $url = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/');
    $label = $count > 0
        ? sprintf(_n('Checkout, %d item', 'Checkout, %d items', $count, 'cubestheme'), $count)
        : __('Checkout', 'cubestheme');
    ?>
    <a href="<?php echo esc_url($url); ?>" class="icon-button header-cart" aria-label="<?php echo esc_attr($label); ?>">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M6.5 8h11l-.8 11.2a1 1 0 0 1-1 .8H8.3a1 1 0 0 1-1-.8L6.5 8z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
            <path d="M9 8V7a3 3 0 0 1 6 0v1" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
        <?php if ($count > 0) : ?>
            <span class="header-cart-count"><?php echo esc_html($count); ?></span>
        <?php endif; ?>
    </a>
    <?php
}

function cubestheme_header_cart_fragment($fragments)
{
    ob_start();
    cubestheme_header_cart_link();
    $fragments['a.header-cart'] = trim(ob_get_clean());

    return $fragments;
}

add_filter('woocommerce_add_to_cart_fragments', 'cubestheme_header_cart_fragment');

function cubestheme_variation_plan_label($variation)
{
    if (!$variation || !method_exists($variation, 'get_variation_attributes')) {
        return '';
    }

    $attributes = $variation->get_variation_attributes();
    if (!$attributes) {
        return '';
    }

    $value = (string) reset($attributes);
    $taxonomy = str_replace('attribute_', '', (string) key($attributes));
    if ($taxonomy !== '' && taxonomy_exists($taxonomy)) {
        $term = get_term_by('slug', $value, $taxonomy);
        if ($term instanceof WP_Term) {
            return $term->name;
        }
    }

    return $value;
}

function cubestheme_add_to_cart_message($message, $products)
{
    $product_id = (int) array_key_first((array) $products);
    $variation_id = isset($_REQUEST['variation_id']) ? absint(wp_unslash($_REQUEST['variation_id'])) : 0;
    $variation = $variation_id && function_exists('wc_get_product') ? wc_get_product($variation_id) : null;

    if ($variation && $variation->is_type(array('variation', 'subscription_variation'))) {
        $parent_id = (int) $variation->get_parent_id();
        if ($parent_id > 0) {
            $product_id = $parent_id;
        }
    }

    $name = wp_strip_all_tags(get_the_title($product_id));
    $plan = cubestheme_variation_plan_label($variation);
    $sentence = $plan !== ''
        ? sprintf(__('%1$s for %2$s has been added to your cart.', 'cubestheme'), $name, $plan)
        : sprintf(__('%s has been added to your cart.', 'cubestheme'), $name);

    return sprintf(
        '<span>%s</span> <a class="cart-added-toast-action" href="%s">%s</a>',
        esc_html($sentence),
        esc_url(function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/')),
        esc_html__('Checkout', 'cubestheme')
    );
}

add_filter('wc_add_to_cart_message_html', 'cubestheme_add_to_cart_message', 10, 2);

function cubestheme_cart_just_added_body_class($classes)
{
    if (function_exists('wc_notice_count') && !is_cart() && !is_checkout() && wc_notice_count('success') > 0) {
        $classes[] = 'cart-just-added';
    }

    return $classes;
}

add_filter('body_class', 'cubestheme_cart_just_added_body_class');

function cubestheme_cart_added_toast()
{
    if (!function_exists('wc_get_notices') || !function_exists('wc_set_notices') || is_cart() || is_checkout()) {
        return;
    }

    $success = wc_get_notices('success');
    if (!$success) {
        return;
    }

    $notices = wc_get_notices();
    unset($notices['success']);
    wc_set_notices($notices);
    ?>
    <div class="cart-added-toast" role="status">
        <?php foreach ($success as $notice) : ?>
            <?php $text = is_array($notice) ? (string) ($notice['notice'] ?? '') : (string) $notice; ?>
            <?php if ($text === '') continue; ?>
            <div class="cart-added-toast-row">
                <p><?php echo wp_kses($text, array('span' => array(), 'a' => array('class' => array(), 'href' => array()))); ?></p>
                <button class="cart-added-toast-close" type="button" aria-label="<?php esc_attr_e('Dismiss', 'cubestheme'); ?>">×</button>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
        document.querySelectorAll('.cart-added-toast-close').forEach(function (button) {
            button.addEventListener('click', function () {
                var toast = button.closest('.cart-added-toast');
                if (toast) {
                    toast.remove();
                }
                document.body.classList.remove('cart-just-added');
            });
        });
    </script>
    <?php
}

add_action('wp_body_open', 'cubestheme_cart_added_toast', 20);

function cubestheme_account_section_intro()
{
    if (!function_exists('is_wc_endpoint_url')) {
        return;
    }

    $sections = array(
        'orders' => array('Orders', 'Your orders', 'Each order keeps the plugin, the payment, and the license that was created from it.'),
        'payment-methods' => array('Payments', 'Saved cards', 'These cards are used for subscription renewals.'),
        'edit-account' => array('Account', 'Your details', 'Update the name, email, and password used for this account.'),
        'subscriptions' => array('Subscriptions', 'Your plans', 'Each plan renews yearly and keeps the license active.'),
    );

    foreach ($sections as $endpoint => $copy) {
        if (!is_wc_endpoint_url($endpoint)) {
            continue;
        }
        if ($endpoint === 'subscriptions' && is_wc_endpoint_url('view-subscription')) {
            continue;
        }

        echo '<div class="account-intro"><div>';
        echo '<span class="account-kicker">' . esc_html__($copy[0], 'cubestheme') . '</span>';
        echo '<h2>' . esc_html__($copy[1], 'cubestheme') . '</h2>';
        echo '<p>' . esc_html__($copy[2], 'cubestheme') . '</p>';
        echo '</div></div>';
        return;
    }
}

add_action('woocommerce_account_content', 'cubestheme_account_section_intro', 9);

function cubestheme_account_copy_script()
{
    if (!function_exists('is_account_page') || !is_account_page()) {
        return;
    }
    ?>
    <script>
        document.addEventListener('click', function (event) {
            var button = event.target.closest('.wsh-copy-key');
            if (!button) {
                return;
            }
            var key = button.getAttribute('data-key') || '';
            if (!key || !navigator.clipboard) {
                return;
            }
            navigator.clipboard.writeText(key).then(function () {
                var previous = button.textContent;
                button.textContent = 'Copied';
                window.setTimeout(function () {
                    button.textContent = previous;
                }, 1600);
            });
        });
    </script>
    <?php
}

add_action('wp_footer', 'cubestheme_account_copy_script');

function cubestheme_remove_injected_spam()
{
    if (get_option('cubestheme_spam_stripped') === '2') {
        return;
    }

    global $wpdb;
    $posts = $wpdb->get_results(
        "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_content LIKE '%aussieluckywins%' OR post_content LIKE '%lucky wins casino%'"
    );

    foreach ($posts as $post) {
        $clean = preg_replace('/<div style="position:\s*fixed;.*?<\/div>/s', '', $post->post_content);
        if ($clean !== $post->post_content) {
            wp_update_post(array(
                'ID' => $post->ID,
                'post_content' => $clean,
            ));
        }

        foreach (array('_yoast_wpseo_metadesc', 'rank_math_description', '_aioseo_description') as $meta_key) {
            $description = (string) get_post_meta($post->ID, $meta_key, true);
            if ($description !== '' && stripos($description, 'casino') !== false) {
                delete_post_meta($post->ID, $meta_key);
            }
        }
    }

    update_option('cubestheme_spam_stripped', '2');
}

add_action('init', 'cubestheme_remove_injected_spam');

function cubestheme_email_brand_options()
{
    if (get_option('cubestheme_email_brand') === '1') {
        return;
    }

    update_option('woocommerce_email_background_color', '#eef3fb');
    update_option('woocommerce_email_body_background_color', '#ffffff');
    update_option('woocommerce_email_base_color', '#034dd3');
    update_option('woocommerce_email_text_color', '#1b1b21');
    update_option('woocommerce_email_footer_text_color', '#5c6570');
    update_option('cubestheme_email_brand', '1');
}

add_action('init', 'cubestheme_email_brand_options');

function cubestheme_email_footer_text()
{
    return 'WP Plugins Pro · wppluginspro.io';
}

add_filter('woocommerce_email_footer_text', 'cubestheme_email_footer_text');

function cubestheme_email_styles($css)
{
    $css .= '
        #outer_wrapper { background-color: #eef3fb; }
        #wrapper { padding: 32px 0; }
        #template_header_image { background-color: #034dd3; padding: 18px 32px; border-radius: 16px 16px 0 0; }
        #template_header_image p, .email-logo-text { color: #ffffff !important; font-size: 18px; font-weight: 700; margin: 0; }
        #inner_wrapper, #template_container { border-radius: 0 0 16px 16px; }
        h1 { color: #1b1b21; font-size: 28px; line-height: 1.2; }
        h2 { color: #1b1b21; font-size: 18px; }
        a { color: #034dd3; }
        #addresses a, address a { color: #1b1b21; text-decoration: none; }
        .email-introduction a { display: inline-block; margin: 8px 0; padding: 12px 22px; background: #034dd3; color: #ffffff !important; text-decoration: none; border-radius: 999px; font-weight: 700; }
        table.td th, .order-item-data th { color: #5c6570; font-size: 12px; letter-spacing: 0.04em; text-transform: uppercase; }
        #template_footer { color: #5c6570; }
        #template_footer a { color: #034dd3; }
    ';

    return $css;
}

add_filter('woocommerce_email_styles', 'cubestheme_email_styles');

function cubestheme_brand_email_html($heading, $body_html)
{
    $heading = esc_html($heading);

    return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#eef3fb;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#eef3fb;"><tr><td align="center" style="padding:32px 16px;">'
        . '<table width="600" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;width:100%;">'
        . '<tr><td style="background:#034dd3;padding:18px 32px;border-radius:16px 16px 0 0;color:#ffffff;font-family:Helvetica,Arial,sans-serif;font-size:18px;font-weight:700;">WP Plugins Pro</td></tr>'
        . '<tr><td style="background:#ffffff;padding:32px;border-radius:0 0 16px 16px;font-family:Helvetica,Arial,sans-serif;color:#1b1b21;">'
        . '<h1 style="margin:0 0 16px;font-size:28px;line-height:1.2;font-weight:700;">' . $heading . '</h1>'
        . $body_html
        . '</td></tr>'
        . '<tr><td style="padding:16px 8px;color:#5c6570;font-family:Helvetica,Arial,sans-serif;font-size:13px;">WP Plugins Pro · wppluginspro.io</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function cubestheme_password_changed_email($email, $user)
{
    $login = $user instanceof WP_User ? $user->user_login : '';
    $email['message'] = cubestheme_brand_email_html(
        'Password changed',
        '<p style="margin:0;font-size:16px;line-height:1.5;">The password was changed for <strong>' . esc_html($login) . '</strong>.</p>'
    );
    $email['headers'] = array('Content-Type: text/html; charset=UTF-8');

    return $email;
}

add_filter('wp_password_change_notification_email', 'cubestheme_password_changed_email', 10, 2);

function cubestheme_boot_ticketing()
{
    if (get_option('wsh_ticketing_booted') === '1') {
        return;
    }

    $plugin = 'wsh-ticketing/wsh-ticketing.php';
    $plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
    if (!file_exists($plugin_file)) {
        return;
    }

    if (!function_exists('activate_plugin')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if (!is_plugin_active($plugin)) {
        activate_plugin($plugin);
    }

    if (class_exists('WSH_Tickets')) {
        WSH_Tickets::init();
    }

    update_option('wsh_ticketing_booted', '1');
}

add_action('init', 'cubestheme_boot_ticketing', 5);
