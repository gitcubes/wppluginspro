<!DOCTYPE html>

<html <?php language_attributes(); ?>>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="cubes d.o.o">
    <meta name="keywords" content="">
    <meta name="theme-color" content="#fff">


    <!--ios compatibility-->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content='<?php bloginfo('name'); ?>'>
    <link rel="apple-touch-icon" href="/apple-icon-144x144.png">


    <!--Android compatibility-->

    <meta name=" mobile-web-app-capable" content="yes">
    <meta name="application-name" content='<?php bloginfo('name'); ?>'>
    <link rel="icon" type="image/png" href="/android-icon-192x192.png">


    <link rel=" icon" type="image/x-icon" href="/favicon.ico">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Funnel+Display:wght@300..800&family=Host+Grotesk:ital,wght@0,300..800;1,300..800&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Roboto+Mono:ital,wght@0,100..700;1,100..700&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">

    <!--CSS FILES-->

    <?php wp_head(); ?>

    <?php
    if (!empty(get_option('cubestheme_head_code'))) {
        if (!isset($_GET['showbanners']) || $_GET['showbanners'] != 0) {
            echo get_option('cubestheme_head_code');
        }
    }
    ?>

</head>

<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>

    <header class="fixed-top <?php echo !empty($args['force_scrolled_header']) ? 'is-scrolled' : ''; ?>" role="banner">

        <div class="container">
            <div class="header-content">

                <?php if (has_custom_logo()) :
                    the_custom_logo();
                else :
                ?>
                    <a class="brand" href="<?php echo esc_url(home_url('/')); ?>"
                        aria-label="<?php echo esc_attr(get_bloginfo('name') . ' home'); ?>">
                        <img src="<?php echo get_template_directory_uri() ?>/frontend/img/logo.png" alt="logo" width="48"
                            height="48" />
                        <?php
                        ?>
                        <div class="logo-details">
                            <span>WP Plugins Pro</span>
                            <span>By Web Solution Hub</span>
                        </div>
                    </a>
                <?php
                endif;
                ?>


                <nav class="nav" aria-label="Primary navigation">
                    <?php get_template_part('template-parts/header/primary-menu', null, array(
                        'link_class' => '',
                    )); ?>
                </nav>

                <?php
                $cubestheme_logged_in = is_user_logged_in();
                $cubestheme_account_url = $cubestheme_logged_in ? cubestheme_account_url() : cubestheme_auth_page_url('sign-in');
                $cubestheme_cta_url = $cubestheme_logged_in ? cubestheme_account_url() : cubestheme_auth_page_url('register');
                $cubestheme_cta_label = $cubestheme_logged_in ? __('Account', 'cubestheme') : __('Register', 'cubestheme');
                ?>
                <div class="header-actions">
                    <a href="<?php echo esc_url($cubestheme_account_url); ?>" class="icon-button" aria-label="<?php echo esc_attr($cubestheme_logged_in ? __('Your account', 'cubestheme') : __('Sign in', 'cubestheme')); ?>">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M19 20a7 7 0 0 0-14 0" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" />
                            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2" />
                        </svg>
                    </a>
                    <a href="<?php echo esc_url($cubestheme_cta_url); ?>" class="cta-button"><?php echo esc_html($cubestheme_cta_label); ?></a>
                </div>

                <button class="menu-toggle" id="menuToggle" type="button" aria-expanded="false"
                    aria-controls="mobileMenu" aria-label="Open navigation">
                    <span class="menu-toggle-box" aria-hidden="true">
                        <span class="menu-toggle-line"></span>
                        <span class="menu-toggle-line"></span>
                        <span class="menu-toggle-line"></span>
                    </span>
                </button>

                <div class="mobile-menu" id="mobileMenu">
                    <nav class="mobile-panel" aria-label="Mobile navigation">
                        <?php get_template_part('template-parts/header/primary-menu', null, array(
                            'link_class' => 'mobile-link',
                            'submenu_link_class' => 'mobile-link',
                        )); ?>
                        <div class="mobile-actions">
                            <a href="<?php echo esc_url($cubestheme_account_url); ?>" class="icon-button" aria-label="<?php echo esc_attr($cubestheme_logged_in ? __('Your account', 'cubestheme') : __('Sign in', 'cubestheme')); ?>">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M19 20a7 7 0 0 0-14 0" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" />
                                    <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2" />
                                </svg>
                            </a>
                            <a href="<?php echo esc_url($cubestheme_cta_url); ?>" class="cta-button"><?php echo esc_html($cubestheme_cta_label); ?></a>
                        </div>
                    </nav>
                </div>
            </div>
        </div>
    </header>

    <main id="main-content" class="site-main">