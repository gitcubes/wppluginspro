<?php // phpcs:ignore

namespace SEOPressPro\Actions\Api\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEOPress\Core\Hooks\ExecuteHooks;

/**
 * PRO Tools Settings REST API endpoints.
 */
class ToolsSettingsPro implements ExecuteHooks {
	/**
	 * Current user ID
	 *
	 * @var int
	 */
	private $current_user = '';

	/**
	 * @since 9.7.0
	 */
	public function hooks() {
		$this->current_user = wp_get_current_user()->ID;
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * Permission check.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return boolean
	 */
	public function permissionCheck( \WP_REST_Request $request ) {
		$current_user = $this->current_user ? $this->current_user : wp_get_current_user()->ID;
		if ( ! user_can( $current_user, seopress_capability( 'manage_options', 'cleaning' ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register() {
		register_rest_route(
			'seopress/v1',
			'/tools/clean-audit-scans',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'processCleanAuditScans' ),
				'permission_callback' => array( $this, 'permissionCheck' ),
			)
		);
	}

	/**
	 * Clean SEO audit scans.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function processCleanAuditScans( \WP_REST_Request $request ) {
		global $wpdb;

		// Clean custom table if it exists.
		$table_name = $wpdb->prefix . 'seopress_seo_issues';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			$wpdb->query( "DELETE FROM `{$table_name}`" );
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'SEO audit scans have been successfully deleted.', 'wp-seopress-pro' ),
			),
			200
		);
	}
}
