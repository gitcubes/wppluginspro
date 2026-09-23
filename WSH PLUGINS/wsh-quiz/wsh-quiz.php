<?php
/**
 * Plugin Name: WSH Quiz
 * Plugin URI: https://wppluginspro.io
 * Description: Create and embed quizzes on WordPress news sites. Manual editor is free; Pro features unlock with a license.
 * Version: 0.4.44
 * Author: Web Solutions Hub LLC
 * Author URI: https://wppluginspro.io
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wsh-quiz
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WSH_QUIZ_VERSION', '0.4.44' );
define( 'WSH_QUIZ_FILE', __FILE__ );
define( 'WSH_QUIZ_PATH', plugin_dir_path( __FILE__ ) );
define( 'WSH_QUIZ_URL', plugin_dir_url( __FILE__ ) );
define( 'WSH_QUIZ_BASENAME', plugin_basename( __FILE__ ) );
define( 'WSH_QUIZ_PLUGIN_SLUG', 'wsh-quiz' );

if ( ! defined( 'WSH_LICENSE_API_BASE' ) ) {
	define( 'WSH_LICENSE_API_BASE', 'https://wppluginspro.io/wp-json/wsh-license/v1' );
}

if ( ! defined( 'WSH_QUIZ_LICENSE_API_SECRET' ) ) {
	define( 'WSH_QUIZ_LICENSE_API_SECRET', 'wsh-quiz' );
}

require_once WSH_QUIZ_PATH . 'includes/helpers.php';
require_once WSH_QUIZ_PATH . 'includes/class-settings.php';
require_once WSH_QUIZ_PATH . 'includes/class-ai.php';
require_once WSH_QUIZ_PATH . 'includes/class-results.php';
require_once WSH_QUIZ_PATH . 'includes/class-install.php';
require_once WSH_QUIZ_PATH . 'includes/license/class-license.php';
require_once WSH_QUIZ_PATH . 'includes/class-access.php';
require_once WSH_QUIZ_PATH . 'includes/class-post-type.php';
require_once WSH_QUIZ_PATH . 'includes/class-admin.php';
require_once WSH_QUIZ_PATH . 'includes/class-metabox.php';
require_once WSH_QUIZ_PATH . 'includes/license/class-license-page.php';
require_once WSH_QUIZ_PATH . 'includes/class-assets.php';
require_once WSH_QUIZ_PATH . 'includes/class-ajax.php';
require_once WSH_QUIZ_PATH . 'includes/class-shortcode.php';
require_once WSH_QUIZ_PATH . 'includes/class-block.php';
require_once WSH_QUIZ_PATH . 'includes/class-frontend.php';
require_once WSH_QUIZ_PATH . 'includes/class-pages.php';
require_once WSH_QUIZ_PATH . 'includes/class-widget.php';
require_once WSH_QUIZ_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'WSH_Quiz_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WSH_Quiz_Install', 'deactivate' ) );

function wsh_quiz() {
	return WSH_Quiz_Plugin::instance();
}

wsh_quiz();
