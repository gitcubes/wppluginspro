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
    return  '1.0.5';
}

// INCLUDE FILES

function cubestheme_scripts()
{

    wp_enqueue_script('jquerymin', get_template_directory_uri() . '/frontend/js/jquery.min.js', array(), '3.4.1', false);
    wp_enqueue_script('validate', get_template_directory_uri() . '/frontend/js/jquery.validate.min.js', array('jquerymin'), '1.19.1', true);
    wp_enqueue_script('lottie', get_template_directory_uri() . '/frontend/js/lottie-player.js', array(), '1.19.1', true);
    wp_enqueue_script('fancybox', get_template_directory_uri() . '/frontend/js/jquery.fancybox.min.js', array('jquerymin'), '3.3.5', true);
    wp_enqueue_script('main', get_template_directory_uri() . '/frontend/js/main.js', array('jquerymin'), '1.0', true);
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
    register_post_type('wp_plugins_pro', array(
        'labels' => array(
            'name' => __('WP Plugins Pro', 'cubestheme'),
            'singular_name' => __('WP Plugin Pro', 'cubestheme'),
            'plural_name' => __('WP Plugins Pro', 'cubestheme'),
            'all_items' => __('All WP Plugins Pro', 'cubestheme'),
            'add_new' => __('Add new Plugin', 'cubestheme'),
            'add_new_item' => __('Add new Plugin', 'cubestheme'),
            'new_item' => __('New Plugin', 'cubestheme'),
            'edit' => __('Edit', 'cubestheme'),
            'edit_item' => __('Edit Plugin', 'cubestheme'),
            'view' => __('View Plugin', 'cubestheme'),
            'view_item' => __('View Plugin', 'cubestheme'),
            'featured_image' => __('Featured image for Plugin', 'cubestheme'),
        ),
        'public' => true,
        'hierarchical' => false,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-admin-plugins',
        'menu_position' => 10,
        'exclude_from_search' => false,
        'show_in_rest' => true,
        'supports' => array(
            'title',
            'editor',
            'thumbnail',
        )
    ));

    register_taxonomy('plugin_category', array('wp_plugins_pro'), array(
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
}

add_action('wp_enqueue_scripts', 'cubestheme_commerce_assets', 100);

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
