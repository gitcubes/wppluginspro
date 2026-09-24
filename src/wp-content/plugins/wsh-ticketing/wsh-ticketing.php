<?php
/**
 * Plugin Name: WSH Ticketing
 * Description: Support tickets from the Get Support form, with an admin archive and email replies.
 * Author:      Web Solutions Hub LLC
 * Version:     1.0.0
 */

if (! defined('ABSPATH')) {
	exit;
}

define('WSH_TICKETING_PATH', plugin_dir_path(__FILE__));

require_once WSH_TICKETING_PATH . 'includes/class-wsh-tickets.php';

add_action('plugins_loaded', array('WSH_Tickets', 'init'));

register_activation_hook(__FILE__, array('WSH_Tickets', 'activate'));
