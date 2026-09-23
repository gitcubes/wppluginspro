<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu + license settings page for PRO.
 */
class WSH_VC_Pro_Admin_Menu {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Register PRO top-level menu with License page.
	 */
	public static function register_menu() {

		// Top-level menu for PRO.
		add_menu_page(
			__( 'WSH Views Counter PRO', 'wsh-views-counter-pro' ), // Page title.
			__( 'Views Counter PRO', 'wsh-views-counter-pro' ),     // Menu title.
			'manage_options',                                       // Capability.
			'wsh_views_counter_pro',                               // Menu slug (IMPORTANT).
			array( __CLASS__, 'render_page' ),                     // Callback.
			'dashicons-chart-line',
			180                                                      // Position (adjust if needed).
		);
	}

	/**
	 * Render license/settings page.
	 */
	public static function render_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice      = '';
		$notice_type = 'updated';

		// Handle form actions.
		if (
			isset( $_POST['wsh_vc_pro_license_action'], $_POST['wsh_vc_pro_license_nonce'] )
			&& check_admin_referer( 'wsh_vc_pro_license_save', 'wsh_vc_pro_license_nonce' )
		) {
			$action = sanitize_text_field( wp_unslash( $_POST['wsh_vc_pro_license_action'] ) );

			if ( 'activate' === $action ) {
				$key    = isset( $_POST['wsh_vc_pro_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['wsh_vc_pro_license_key'] ) ) : '';
				$result = WSH_VC_Pro_License::activate( $key );
				

				$notice      = isset( $result['message'] ) ? $result['message'] : '';
				$notice_type = ! empty( $result['success'] ) ? 'updated' : 'error';

			} elseif ( 'deactivate' === $action ) {
				$result      = WSH_VC_Pro_License::deactivate();
				$notice      = isset( $result['message'] ) ? $result['message'] : '';
				$notice_type = ! empty( $result['success'] ) ? 'updated' : 'error';
			}
		}

		$license_key    = WSH_VC_Pro_License::get_key();
		$license_status = WSH_VC_Pro_License::get_status();
		$license_expiry = get_option( WSH_VC_Pro_License::OPTION_EXPIRES, '' );
		$last_error     = get_option( WSH_VC_Pro_License::OPTION_LAST_ERROR, '' );

		$status_label = $license_status ? ucfirst( $license_status ) : __( 'Not set', 'wsh-views-counter-pro' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WSH Views Counter PRO – License', 'wsh-views-counter-pro' ); ?></h1>

			<?php if ( ! empty( $notice ) ) : ?>
				<div class="<?php echo ( 'error' === $notice_type ) ? 'notice notice-error' : 'notice notice-success'; ?>">
					<p><?php echo esc_html( $notice ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'wsh_vc_pro_license_save', 'wsh_vc_pro_license_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row">
							<label for="wsh_vc_pro_license_key"><?php esc_html_e( 'License Key', 'wsh-views-counter-pro' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="wsh_vc_pro_license_key"
								name="wsh_vc_pro_license_key"
								value="<?php echo esc_attr( $license_key ); ?>"
								class="regular-text"
								autocomplete="off"
							/>
							<p class="description">
								<?php esc_html_e( 'Enter your PRO license key provided after purchase.', 'wsh-views-counter-pro' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'wsh-views-counter-pro' ); ?></th>
						<td>
							<strong><?php echo esc_html( $status_label ); ?></strong>
							<?php if ( ! empty( $license_expiry ) ) : ?>
								<br />
								<small>
									<?php
									printf(
										/* translators: %s: expiry date */
										esc_html__( 'Expires on: %s', 'wsh-views-counter-pro' ),
										esc_html( $license_expiry )
									);
									?>
								</small>
							<?php endif; ?>
							<?php if ( ! empty( $last_error ) ) : ?>
								<br />
								<small style="color:#cc0000;">
									<?php
									printf(
										/* translators: %s: error message */
										esc_html__( 'Last error: %s', 'wsh-views-counter-pro' ),
										esc_html( $last_error )
									);
									?>
								</small>
							<?php endif; ?>
						</td>
					</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" name="wsh_vc_pro_license_action" value="activate" class="button button-primary">
						<?php esc_html_e( 'Activate License', 'wsh-views-counter-pro' ); ?>
					</button>
					<?php if ( $license_key ) : ?>
						<button type="submit" name="wsh_vc_pro_license_action" value="deactivate" class="button">
							<?php esc_html_e( 'Deactivate License', 'wsh-views-counter-pro' ); ?>
						</button>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<?php
	}
}
