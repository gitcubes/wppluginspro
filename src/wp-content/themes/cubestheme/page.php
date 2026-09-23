<?php
wp_enqueue_style('static-page', get_template_directory_uri() . '/frontend/css/static-page.css', array(), themeVersion());

get_header(null, [
    'force_scrolled_header' => true,
]);

?>
<section class="static-page">
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
