<?php

wp_enqueue_style('pricing', get_template_directory_uri() . '/frontend/css/pricing.css', array(), themeVersion());

get_header();

get_template_part('template-parts/pricing/pricing-page');

get_footer();
