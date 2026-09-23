<?php

wp_enqueue_style('landing-page', get_template_directory_uri() . '/frontend/css/landing-page.css', array(), themeVersion());

get_header();

get_template_part('template-parts/landing/views-counter-pro');

get_footer();
