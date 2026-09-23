<?php

function cubestheme_account_url()
{
    if (function_exists('wc_get_page_permalink')) {
        $url = wc_get_page_permalink('myaccount');
        if ($url) {
            return $url;
        }
    }

    return home_url('/my-account/');
}

function cubestheme_auth_page_url($slug)
{
    $page = get_page_by_path($slug);
    if ($page instanceof WP_Post) {
        return get_permalink($page);
    }

    return home_url('/' . $slug . '/');
}

function cubestheme_legal_page_url()
{
    $page = get_page_by_path('terms-privacy-cookies');
    if ($page instanceof WP_Post) {
        return get_permalink($page);
    }

    return home_url('/terms-privacy-cookies/');
}

function cubestheme_lost_password_url()
{
    if (function_exists('wc_lostpassword_url')) {
        return wc_lostpassword_url();
    }

    return wp_lostpassword_url();
}

function cubestheme_ensure_auth_pages()
{
    $pages = array(
        'login' => 'Login',
        'register' => 'Register',
    );

    foreach ($pages as $slug => $title) {
        $existing = get_page_by_path($slug);
        if ($existing instanceof WP_Post) {
            continue;
        }

        wp_insert_post(array(
            'post_title' => $title,
            'post_name' => $slug,
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => '',
        ));
    }
}

add_action('init', 'cubestheme_ensure_auth_pages');

function cubestheme_redirect_auth_pages()
{
    if (is_user_logged_in() && (is_page('login') || is_page('register'))) {
        wp_safe_redirect(cubestheme_account_url());
        exit;
    }

    if (is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()) {
        return;
    }

    if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('lost-password')) {
        return;
    }

    wp_safe_redirect(cubestheme_auth_page_url('login'));
    exit;
}

add_action('template_redirect', 'cubestheme_redirect_auth_pages');

function cubestheme_auth_redirect_target()
{
    $requested = isset($_REQUEST['redirect_to']) ? wp_unslash($_REQUEST['redirect_to']) : '';
    $fallback = cubestheme_account_url();

    return $requested ? wp_validate_redirect($requested, $fallback) : $fallback;
}

function cubestheme_process_login()
{
    $state = array(
        'error' => '',
        'email' => '',
    );

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['cubestheme_login'])) {
        return $state;
    }

    $state['email'] = sanitize_text_field(wp_unslash($_POST['email'] ?? ''));
    $password = wp_unslash($_POST['password'] ?? '');

    if (!isset($_POST['cubestheme_login_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cubestheme_login_nonce'])), 'cubestheme_login')) {
        $state['error'] = __('The form expired. Refresh the page and try again.', 'cubestheme');
        return $state;
    }

    if ($state['email'] === '' || $password === '') {
        $state['error'] = __('Enter your email and password.', 'cubestheme');
        return $state;
    }

    if (!is_email($state['email'])) {
        $state['error'] = __('Enter a valid email address.', 'cubestheme');
        return $state;
    }

    $login = sanitize_email($state['email']);
    if (is_email($login)) {
        $user = get_user_by('email', $login);
        if ($user instanceof WP_User) {
            $login = $user->user_login;
        }
    }

    $signed_in = wp_signon(array(
        'user_login' => $login,
        'user_password' => $password,
        'remember' => !empty($_POST['remember']),
    ), is_ssl());

    if (is_wp_error($signed_in)) {
        $state['error'] = __('That email or password is not right.', 'cubestheme');
        return $state;
    }

    wp_safe_redirect(cubestheme_auth_redirect_target());
    exit;
}

function cubestheme_process_register()
{
    $state = array(
        'error' => '',
        'first_name' => '',
        'last_name' => '',
        'company' => '',
        'email' => '',
    );

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['cubestheme_register'])) {
        return $state;
    }

    $state['first_name'] = sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''));
    $state['last_name'] = sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''));
    $state['company'] = sanitize_text_field(wp_unslash($_POST['company'] ?? ''));
    $state['email'] = sanitize_text_field(wp_unslash($_POST['email'] ?? ''));
    $password = wp_unslash($_POST['password'] ?? '');
    $password_confirm = wp_unslash($_POST['password_confirm'] ?? '');

    if (!isset($_POST['cubestheme_register_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cubestheme_register_nonce'])), 'cubestheme_register')) {
        $state['error'] = __('The form expired. Refresh the page and try again.', 'cubestheme');
        return $state;
    }

    if ($state['first_name'] === '' || $state['last_name'] === '' || $state['email'] === '' || $password === '') {
        $state['error'] = __('Fill in your name, email and password.', 'cubestheme');
        return $state;
    }

    if (!is_email($state['email'])) {
        $state['error'] = __('Enter a valid email address.', 'cubestheme');
        return $state;
    }

    if (strlen($password) < 8) {
        $state['error'] = __('Use a password with at least 8 characters.', 'cubestheme');
        return $state;
    }

    if ($password !== $password_confirm) {
        $state['error'] = __('The two passwords do not match.', 'cubestheme');
        return $state;
    }

    if (empty($_POST['accept_terms'])) {
        $state['error'] = __('Accept the privacy terms to create an account.', 'cubestheme');
        return $state;
    }

    if (!function_exists('wc_create_new_customer')) {
        $state['error'] = __('Registration is unavailable right now.', 'cubestheme');
        return $state;
    }

    $customer_id = wc_create_new_customer(sanitize_email($state['email']), '', $password, array(
        'first_name' => $state['first_name'],
        'last_name' => $state['last_name'],
    ));

    if (is_wp_error($customer_id)) {
        $state['error'] = $customer_id->get_error_message();
        return $state;
    }

    if (class_exists('WC_Customer')) {
        $customer = new WC_Customer($customer_id);
        $customer->set_first_name($state['first_name']);
        $customer->set_last_name($state['last_name']);
        $customer->set_billing_first_name($state['first_name']);
        $customer->set_billing_last_name($state['last_name']);
        $customer->set_billing_email(sanitize_email($state['email']));
        if ($state['company'] !== '') {
            $customer->set_billing_company($state['company']);
        }
        $customer->save();
    }

    if (function_exists('wc_set_customer_auth_cookie')) {
        wc_set_customer_auth_cookie($customer_id);
    } else {
        wp_set_auth_cookie($customer_id, true);
    }

    wp_safe_redirect(cubestheme_account_url());
    exit;
}
