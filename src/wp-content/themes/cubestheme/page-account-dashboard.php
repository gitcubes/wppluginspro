<?php

wp_enqueue_style('cubestheme-dashboard', get_template_directory_uri() . '/frontend/css/dashboard.css', array(), themeVersion());

get_header(null, [
    'force_scrolled_header' => true,
]);

get_template_part('template-parts/dashboard/dashboard-page');

get_footer();
