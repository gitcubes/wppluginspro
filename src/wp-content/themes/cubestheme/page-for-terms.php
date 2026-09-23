<?php

/*
    Template Name: Terms
*/

wp_enqueue_style('privacy', get_template_directory_uri() . '/frontend/css/privacy.css', array(), themeVersion());

get_header();

get_template_part('template-parts/terms/terms-lead');
get_template_part('template-parts/terms/terms-content');

get_footer();
