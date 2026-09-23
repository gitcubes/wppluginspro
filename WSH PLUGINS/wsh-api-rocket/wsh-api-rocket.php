
<?php
/**
 * Plugin Name: WSH API Rocket
 * Description: REST API services for mobile apps (content + home builder + optional PRO).
 * Version: 1.0.0
 * Author: WSH
 * Text Domain: wsh-api-rocket
 */
if ( ! defined('ABSPATH') ) exit;

define('WSH_AR_VERSION', '1.0.0');
define('WSH_AR_PLUGIN_FILE', __FILE__);
define('WSH_AR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WSH_AR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WSH_AR_SLUG', 'wsh-api-rocket');

require_once WSH_AR_PLUGIN_DIR . 'includes/Core/Autoloader.php';
WSH\APIROCKET\Core\Autoloader::register();

register_activation_hook(__FILE__, ['WSH\APIROCKET\Core\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['WSH\APIROCKET\Core\Deactivator', 'deactivate']);

add_action('plugins_loaded', function () {
    (new WSH\APIROCKET\Core\Plugin())->init();
});
