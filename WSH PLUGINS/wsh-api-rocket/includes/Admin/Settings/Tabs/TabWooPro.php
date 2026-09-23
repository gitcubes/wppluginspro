<?php
namespace WSH\APIROCKET\Admin\Settings\Tabs;

use WSH\APIROCKET\Licensing\License;

if ( ! defined('ABSPATH') ) exit;

final class TabWooPro {
    public function render(): void {
        echo '<h2>' . esc_html__('WooCommerce (PRO)', 'wsh-api-rocket') . '</h2>';

        if (!License::is_active()) {
            echo '<div class="notice notice-warning"><p><strong>' .
                esc_html__('Locked (PRO).', 'wsh-api-rocket') .
                '</strong> ' . esc_html__('Products list/single, create order, orders by customer, webhooks → push.', 'wsh-api-rocket') .
                '</p></div>';
            echo '<p>' . esc_html__('Activate a license to unlock this.', 'wsh-api-rocket') . '</p>';
            return;
        }

        echo '<div class="notice notice-info"><p>' .
            esc_html__('PRO is active. Woo endpoints/settings will be implemented here.', 'wsh-api-rocket') .
            '</p></div>';
    }
}
