<?php

wp_enqueue_style('licences', get_template_directory_uri() . '/frontend/css/licences.css', array(), themeVersion());

get_header(null, [
    'force_scrolled_header' => true,
]);

get_template_part('template-parts/licences/licences-page');

get_footer();
