<?php //phpcs:ignore
/**
 * SEOPress PRO License page.
 *
 * @package SEOPress PRO
 * @subpackage Admin_Pages
 */

defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

// Enqueue license page styles.
$prefix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
wp_enqueue_style( 'seopress-pro-license-page', SEOPRESS_PRO_PLUGIN_DIR_URL . 'assets/css/license-page' . $prefix . '.css', array(), SEOPRESS_PRO_VERSION );

$license  = defined( 'SEOPRESS_LICENSE_KEY' ) && ! empty( SEOPRESS_LICENSE_KEY ) && is_string( SEOPRESS_LICENSE_KEY ) ? SEOPRESS_LICENSE_KEY : get_option( 'seopress_pro_license_key' );
$selected = $license ? '********************************' : '';
$status   = get_option( 'seopress_pro_license_status' );
$docs     = function_exists( 'seopress_get_docs_links' ) ? seopress_get_docs_links() : '';

if ( is_plugin_active( 'wp-seopress/seopress.php' ) ) {
	if ( function_exists( 'seopress_admin_header' ) ) {
		echo seopress_admin_header();
	}
} ?>

<form class="seopress-option" method="post"
	action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
	<?php echo $this->feature_title( null ); ?>

	<div id="seopress-tabs" class="wrap full-width">
		<div class="seopress-tab active">
			<?php settings_fields( 'seopress_license' ); ?>

			<!-- License Status Banner -->
			<?php if ( false !== $status && 'valid' === $status ) { ?>
			<div class="seopress-notice is-success seopress-license-status-notice">
				<p>
					<strong><?php esc_html_e( 'License Active', 'wp-seopress-pro' ); ?></strong>
					<?php esc_html_e( 'Your license is active. You have access to automatic updates and support.', 'wp-seopress-pro' ); ?>
				</p>
				<a href="<?php echo esc_url( $docs['license']['account'] ); ?>" class="btn btnTertiary seopress-help" target="_blank">
					<?php esc_html_e( 'View my account', 'wp-seopress-pro' ); ?>
				</a>
			</div>
			<?php } else { ?>
			<div class="seopress-notice is-warning seopress-license-status-notice">
				<p>
					<strong><?php esc_html_e( 'License Not Active', 'wp-seopress-pro' ); ?></strong>
					<?php esc_html_e( 'Please activate your license to access automatic updates and support.', 'wp-seopress-pro' ); ?>
				</p>
				<a href="<?php echo esc_url( $docs['license']['account'] ); ?>" class="btn btnTertiary seopress-help" target="_blank">
					<?php esc_html_e( 'View my account', 'wp-seopress-pro' ); ?>
				</a>
			</div>
			<?php } ?>

			<!-- Two Column Layout -->
			<div class="seopress-license-layout">

				<!-- Left Column - Main Form -->
				<div class="seopress-license-card">
					<h3><?php esc_html_e( 'License Configuration', 'wp-seopress-pro' ); ?></h3>

					<?php if ( get_option( 'seopress_pro_license_key_error' ) ) { ?>
					<div class="seopress-license-error">
						<p>
							<span class="dashicons dashicons-warning"></span>
							<?php echo get_option( 'seopress_pro_license_key_error' ); ?>
						</p>
					</div>
					<?php } ?>

					<!-- License Key Field -->
					<div class="seopress-license-field">
						<label for="seopress_pro_license_key">
							<?php esc_html_e( 'License Key', 'wp-seopress-pro' ); ?>
						</label>
						<input id="seopress_pro_license_key" name="seopress_pro_license_key" type="text" autocomplete="off" spellcheck="false" autocorrect="off" autocapitalize="off" value="<?php echo esc_attr( $selected ); ?>" placeholder="<?php esc_html_e( 'Enter your license key', 'wp-seopress-pro' ); ?>" />
						<p class="description">
							<?php esc_html_e( 'Activating your license enables automatic updates and premium support. Your Pro features work without activation.', 'wp-seopress-pro' ); ?>
						</p>
						<?php if ( defined( 'SEOPRESS_LICENSE_KEY' ) && ! empty( SEOPRESS_LICENSE_KEY ) && is_string( SEOPRESS_LICENSE_KEY ) ) { ?>
						<div class="info-notice">
							<p>
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'Your license key is defined in wp-config.php.', 'wp-seopress-pro' ); ?>
							</p>
						</div>
						<?php } ?>
					</div>

					<?php wp_nonce_field( 'seopress_nonce', 'seopress_nonce' ); ?>

					<?php if ( false !== $license && ! empty( $license ) ) { ?>
						<!-- License Actions -->
						<div class="seopress-license-actions">
							<?php if ( false !== $status && 'valid' === $status ) { ?>
								<button type="button" id="seopress_pro_license_reset" class="btn btnTertiary">
									<?php esc_html_e( 'Reset your license', 'wp-seopress-pro' ); ?>
								</button>
								<input id="seopress-edd-license-btn" type="submit" class="btn btnSecondary" name="seopress_license_deactivate" value="<?php esc_html_e( 'Deactivate License', 'wp-seopress-pro' ); ?>" />
								<div class="spinner"></div>

							<?php } else { ?>
								<button type="button" id="seopress_pro_license_reset" class="btn btnTertiary">
									<?php esc_html_e( 'Reset your license', 'wp-seopress-pro' ); ?>
								</button>
								<input id="seopress-edd-license-btn" type="submit" class="btn btnPrimary" name="seopress_license_activate" value="<?php esc_html_e( 'Activate License', 'wp-seopress-pro' ); ?>" />
								<div class="spinner"></div>
							<?php } ?>
						</div>
					<?php } else { ?>
						<div class="seopress-license-actions">
							<input id="submit" name="submit" type="submit" class="btn btnPrimary" value="<?php esc_attr_e( 'Save changes', 'wp-seopress-pro' ); ?>" />
						</div>
					<?php } ?>

					<?php if ( isset( $_GET['sl_activation'] ) && ! empty( $_GET['message'] ) ) { ?>
						<?php
						switch ( sanitize_text_field( wp_unslash( $_GET['sl_activation'] ) ) ) {
							case 'false':
								$message = htmlspecialchars( urldecode( wp_unslash( $_GET['message'] ) ) );
								?>
						<div class="seopress-license-message is-error">
							<p>
								<span class="dashicons dashicons-dismiss"></span>
								<?php echo esc_html( $message ); ?>
							</p>
							<p class="description">
								<?php esc_html_e( 'Please try again or contact our support team if the problem persists.', 'wp-seopress-pro' ); ?>
							</p>
						</div>
								<?php
								break;
							case 'true':
							default:
								?>
						<div class="seopress-license-message is-success">
							<p>
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Your license has been successfully activated!', 'wp-seopress-pro' ); ?>
							</p>
						</div>
								<?php
								break;
						}
						?>
					<?php } ?>

				</div>
				<!-- End Left Column -->

				<!-- Right Sidebar - Help & Info -->
				<div class="seopress-license-sidebar">

					<!-- Quick Guide Card -->
					<div class="seopress-license-sidebar-card">
						<h4>
							<span class="dashicons dashicons-info-outline"></span>
							<?php esc_html_e( 'Quick Guide', 'wp-seopress-pro' ); ?>
						</h4>
						<ol>
							<li><?php esc_html_e( 'Enter your license key and click "Save changes"', 'wp-seopress-pro' ); ?></li>
							<li><?php esc_html_e( 'Click "Activate License"', 'wp-seopress-pro' ); ?></li>
							<li><?php esc_html_e( 'Get automatic updates and support', 'wp-seopress-pro' ); ?></li>
						</ol>
					</div>

					<!-- Alternative Method Card -->
					<div class="seopress-license-sidebar-card">
						<h4>
							<span class="dashicons dashicons-admin-settings"></span>
							<?php esc_html_e( 'Alternative Method', 'wp-seopress-pro' ); ?>
						</h4>
						<p>
							<?php esc_html_e( 'Add this constant to your', 'wp-seopress-pro' ); ?> <strong>wp-config.php</strong> <?php esc_html_e( 'file for automatic activation:', 'wp-seopress-pro' ); ?>
						</p>
						<div class="seopress-license-code-block">
							<pre><code id="seopress-license-code">define( 'SEOPRESS_LICENSE_KEY', 'your-license-key-here' );</code></pre>
							<button type="button" class="seopress-license-copy-btn" data-clipboard-target="#seopress-license-code">
								<span class="dashicons dashicons-clipboard"></span>
								<span class="copy-text"><?php esc_html_e( 'Copy', 'wp-seopress-pro' ); ?></span>
								<span class="copied-text" style="display:none;"><?php esc_html_e( 'Copied!', 'wp-seopress-pro' ); ?></span>
							</button>
						</div>
						<a href="<?php echo esc_url( $docs['license']['license_define'] ); ?>" target="_blank" class="seopress-help">
							<?php esc_html_e( 'Learn more', 'wp-seopress-pro' ); ?>
							<span class="dashicons dashicons-external"></span>
						</a>
					</div>

					<!-- Help Card -->
					<div class="seopress-license-sidebar-card seopress-help">
						<h4>
							<span class="dashicons dashicons-sos"></span>
							<?php esc_html_e( 'Need Help?', 'wp-seopress-pro' ); ?>
						</h4>
						<ul>
							<li>
								<a href="<?php echo esc_url( $docs['license']['license_errors'] ); ?>" target="_blank" class="seopress-help">
									<?php esc_html_e( 'Troubleshooting Documentation', 'wp-seopress-pro' ); ?>
									<span class="dashicons dashicons-external"></span>
								</a>
							</li>
							<li>
								<a href="https://www.seopress.org/support/" target="_blank" class="seopress-help">
									<?php esc_html_e( 'Contact Support', 'wp-seopress-pro' ); ?>
									<span class="dashicons dashicons-external"></span>
								</a>
							</li>
						</ul>
					</div>

				</div>
				<!-- End Right Sidebar -->

			</div>
			<!-- End Two Column Layout -->
		</div>
	</div>
</form>
<?php
/**
 * Sanitize license key.
 *
 * @param string $new The new license key.
 * @return string The sanitized license key.
 */
function seopress_sanitize_license( $new ) {
	$old = get_option( 'seopress_pro_license_key' );
	if ( $old && $old !== $new ) {
		delete_option( 'seopress_pro_license_status' ); // New license has been entered, so must reactivate.
	}
	if ( '********************************' === $new ) {
		return $old;
	}
	return $new;
}
