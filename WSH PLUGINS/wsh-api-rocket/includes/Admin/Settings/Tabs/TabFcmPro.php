<?php
namespace WSH\APIROCKET\Admin\Settings\Tabs;

use WSH\APIROCKET\Licensing\License;

if ( ! defined('ABSPATH') ) exit;

final class TabFcmPro {
    public function render(): void {
        echo '<h2>' . esc_html__('Push / FCM (PRO)', 'wsh-api-rocket') . '</h2>';

        if (!License::is_active()) {
            echo '<div class="notice notice-warning"><p><strong>' .
                esc_html__('Locked (PRO).', 'wsh-api-rocket') .
                '</strong> ' . esc_html__('Upload Firebase service account JSON and send pushes from WP.', 'wsh-api-rocket') .
                '</p></div>';
            echo '<p>' . esc_html__('Activate a license to unlock this.', 'wsh-api-rocket') . '</p>';
            return;
        }

        echo '<div class="notice notice-info"><p>' .
            esc_html__('PRO is active. FCM module UI will be implemented here.', 'wsh-api-rocket') .
            '</p></div>';
    }
}
