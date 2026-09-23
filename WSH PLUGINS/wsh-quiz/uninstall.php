<?php
/**
 * Remove plugin options on uninstall. Quiz posts are left in the database.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = array(
	'wsh_quiz_license_key',
	'wsh_quiz_license_status',
	'wsh_quiz_license_expires',
	'wsh_quiz_license_last_error',
	'wsh_quiz_license_plan',
	'wsh_quiz_license_last_verified',
	'wsh_quiz_license_grace_until',
	'wsh_quiz_version',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

delete_transient( 'wsh_quiz_last_verify' );
