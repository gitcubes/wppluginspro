<?php
wp_enqueue_style('static-page', get_template_directory_uri() . '/frontend/css/static-page.css', array(), themeVersion());

get_header(null, [
    'force_scrolled_header' => true,
]);

$commerce_page = function_exists('cubestheme_is_commerce_view') && cubestheme_is_commerce_view();

?>
<section class="static-page<?php echo $commerce_page ? ' commerce-page' : ''; ?>">
    <div class="container">
        <h1 class="page-title">
            <?php the_title() ?>
        </h1>
        <div class="content">
            <?php the_content() ?>
        </div>
    </div>
</section>
<?php


get_footer();
