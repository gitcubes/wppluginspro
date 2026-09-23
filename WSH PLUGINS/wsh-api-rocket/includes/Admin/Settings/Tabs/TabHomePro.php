<?php
namespace WSH\APIROCKET\Admin\Settings\Tabs;

use WSH\APIROCKET\Licensing\License;

if ( ! defined('ABSPATH') ) exit;

final class TabHomePro {
    public function render(): void {
        echo '<h2>' . esc_html__('Home PRO', 'wsh-api-rocket') . '</h2>';

        if (!License::is_active()) {
            echo '<div class="notice notice-warning"><p><strong>' .
                esc_html__('Locked (PRO).', 'wsh-api-rocket') .
                '</strong> ' . esc_html__('Activate a license to unlock the advanced Home Builder (drag & drop, tags, CPT, most popular...).', 'wsh-api-rocket') .
                '</p></div>';
            echo '<p>' . esc_html__('Go to the License tab and enter your key.', 'wsh-api-rocket') . '</p>';
            return;
        }

        echo '<div class="notice notice-info"><p>' .
            esc_html__('PRO is active. Home PRO builder will be implemented here.', 'wsh-api-rocket') .
            '</p></div>';
    }
}
