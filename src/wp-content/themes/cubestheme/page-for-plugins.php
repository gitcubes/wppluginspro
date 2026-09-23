<?php

/*
    Template Name: Plugins
*/

wp_enqueue_style('plugins', get_template_directory_uri() . '/frontend/css/plugins.css', array(), themeVersion());

get_header();

get_template_part('template-parts/plugins/plugin-lead');
get_template_part('template-parts/plugins/plugin-families');
get_template_part('template-parts/plugins/plugin-catalog');
get_template_part('template-parts/plugins/plugin-suite-callout');

get_footer();
