<?php

wp_enqueue_style('homepage', get_template_directory_uri() . '/frontend/css/homepage.css', array(), themeVersion());

get_header();

get_template_part('template-parts/homepage/homepage-lead');
get_template_part('template-parts/homepage/homepage-expertise');
get_template_part('template-parts/homepage/homepage-featured-plugins');
get_template_part('template-parts/homepage/homepage-suite-showcase');
get_template_part('template-parts/homepage/homepage-why-choose-us');
get_template_part('template-parts/pricing-preview');
get_template_part('template-parts/faq');
get_template_part('template-parts/newsletter');

get_footer();
