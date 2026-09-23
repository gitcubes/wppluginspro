<?php
wp_enqueue_style('error', get_template_directory_uri() . '/frontend/css/error.css', array(), themeVersion());

get_header(null, [
    'force_scrolled_header' => true,
]);
?>

<section class="error-page">
    <div class="container">
        <div class="error-item">
            <h1>404</h1>
            <h2><?php printf(__('Stranica nepostoji', 'cubestheme')); ?></h2>
            <div class="btn">
                <a class="btn btn-primary"
                    href=<?php echo get_home_url(); ?>><?php printf(__('Nazad na početnu stranicu', 'cubestheme')); ?></a>
            </div>
        </div>
    </div>
</section>

<?php
get_footer();
?>