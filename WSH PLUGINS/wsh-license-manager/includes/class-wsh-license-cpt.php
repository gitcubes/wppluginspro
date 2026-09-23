<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the wsh_license custom post type used for storing license keys.
 */
class WSH_License_CPT {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
	}

	/**
	 * Register custom post type "wsh_license".
	 */
	public static function register_cpt() {

		$labels = array(
			'name'               => __( 'Licenses', 'wsh-license-manager' ),
			'singular_name'      => __( 'License', 'wsh-license-manager' ),
			'add_new'            => __( 'Add New', 'wsh-license-manager' ),
			'add_new_item'       => __( 'Add New License', 'wsh-license-manager' ),
			'edit_item'          => __( 'Edit License', 'wsh-license-manager' ),
			'new_item'           => __( 'New License', 'wsh-license-manager' ),
			'all_items'          => __( 'Licenses', 'wsh-license-manager' ),
			'view_item'          => __( 'View License', 'wsh-license-manager' ),
			'search_items'       => __( 'Search Licenses', 'wsh-license-manager' ),
			'not_found'          => __( 'No licenses found', 'wsh-license-manager' ),
			'not_found_in_trash' => __( 'No licenses found in Trash', 'wsh-license-manager' ),
			'menu_name'          => __( 'WSH Licenses', 'wsh-license-manager' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'supports'           => array( 'title' ),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'show_in_rest'       => false,
		);

		register_post_type( 'wsh_license', $args );
	}

	/**
	 * Generate a random license key string.
	 *
	 * @return string
	 */
	public static function generate_license_key() {
		return strtoupper( wp_generate_password( 24, false, false ) );
	}
}
