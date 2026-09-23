<?php

/*
    Template Name: Docs
*/

wp_enqueue_style('docs', get_template_directory_uri() . '/frontend/css/docs.css', array(), themeVersion());

get_header();

get_template_part('template-parts/docs/docs-hero-section');
get_template_part('template-parts/docs/docs-overview');
get_template_part('template-parts/docs/docs-start');
get_template_part('template-parts/docs/docs-guide');
get_template_part('template-parts/docs/docs-developer-reference');
get_template_part('template-parts/docs/docs-contact');

get_footer();
