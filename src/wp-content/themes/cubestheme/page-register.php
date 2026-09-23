<?php

wp_enqueue_style('account-forms', get_template_directory_uri() . '/frontend/css/account-forms.css', array(), themeVersion());
wp_enqueue_style('account-auth', get_template_directory_uri() . '/frontend/css/account-auth.css', array('account-forms'), themeVersion());

$register_state = cubestheme_process_register();

get_header();

get_template_part('template-parts/account/register-form', null, $register_state);

get_footer();
