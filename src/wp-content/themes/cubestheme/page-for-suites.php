<?php

/*
    Template Name: Suites
*/

wp_enqueue_style('suites', get_template_directory_uri() . '/frontend/css/suites.css', array(), themeVersion());

get_header();

get_template_part('template-parts/suites/suite-lead');
get_template_part('template-parts/suites/suite-benefits');
get_template_part('template-parts/suites/suite-plans');
get_template_part('template-parts/suites/suite-comparison');
get_template_part('template-parts/suites/suite-licensing');
get_template_part('template-parts/faq');

get_footer();
