<?php

wp_enqueue_style('blog', get_template_directory_uri() . '/frontend/css/blog.css', array(), themeVersion());

get_header();

get_template_part('template-parts/blog/blog-lead');
get_template_part('template-parts/blog/blog');

get_footer();
