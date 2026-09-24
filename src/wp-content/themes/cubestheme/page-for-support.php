<?php

/*
    Template Name: Support
*/

wp_enqueue_style('support', get_template_directory_uri() . '/frontend/css/support.css', array(), themeVersion());

get_header();

get_template_part('template-parts/support/support-lead');
if (class_exists('WSH_Tickets') && WSH_Tickets::viewing_thread()) {
	get_template_part('template-parts/support/support-ticket');
} else {
	get_template_part('template-parts/support/support-request-form');
}
get_template_part('template-parts/faq');

get_footer();
