<?php
namespace WSH\APIROCKET\Admin\Settings;

use WSH\APIROCKET\Licensing\Features;

if ( ! defined('ABSPATH') ) exit;

final class Page {

    public static function render(): void {
        if (!current_user_can('manage_options')) return;

        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'home';

        $tabs = [
            'home'    => __('Home', 'wsh-api-rocket'),
            'api'     => __('API', 'wsh-api-rocket'),
            'license' => __('License', 'wsh-api-rocket'),
        ];

        if (Features::enabled('home_pro')) {
            $tabs['home_pro'] = __('Home PRO', 'wsh-api-rocket');
        }

        echo '<div class="wrap"><h1>WSH API Rocket</h1>';
        echo '<nav class="nav-tab-wrapper">';
        foreach ($tabs as $k => $label) {
            $active = ($k === $tab) ? ' nav-tab-active' : '';
            echo '<a class="nav-tab'.$active.'" href="'.esc_url(admin_url('admin.php?page=wsh-api-rocket&tab='.$k)).'">'.esc_html($label).'</a>';
        }
        echo '</nav>';

        echo '<form method="post" action="options.php">';
        settings_fields('wsh_ar');

        switch ($tab) {
            case 'api':
                (new Tabs\TabApi())->render();
                break;
            case 'license':
                (new Tabs\TabLicense())->render();
                break;
            case 'home_pro':
                (new Tabs\TabHomePro())->render();
                break;
            case 'home':
            default:
                (new Tabs\TabHome())->render();
                break;
        }

        submit_button();
        echo '</form></div>';
    }
}
