<?php

/*
    Template Name: About Us
*/

wp_enqueue_style('about-us', get_template_directory_uri() . '/frontend/css/about-us.css', array(), themeVersion());

get_header();

get_template_part('template-parts/about-us/about-hero');
get_template_part('template-parts/about-us/about-company-profile');
get_template_part('template-parts/about-us/about-focus-areas');
get_template_part('template-parts/about-us/about-story-overview');
get_template_part('template-parts/about-us/about-journey-timeline');
get_template_part('template-parts/about-us/about-audience-fit');
get_template_part('template-parts/about-us/about-production-standards');
get_template_part('template-parts/about-us/about-contact');
get_template_part('template-parts/faq');

get_footer();
