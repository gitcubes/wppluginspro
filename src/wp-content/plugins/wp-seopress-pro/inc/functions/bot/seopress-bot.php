<?php // phpcs:ignore
/**
 * SEOPress PRO Bot class.
 *
 * @package SEOPress PRO
 * @subpackage Bot
 */

namespace SEOPressPRO;

defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;

/**
 * Bot class.
 */
class Bot {
	/**
	 * Holds the class object.
	 *
	 * @since 1.0.0
	 *
	 * @var object
	 */
	public static $instance;
	/**
	 * Unique plugin slug identifier.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $plugin_slug = 'seopress-bot-batch';
	/**
	 * Plugin file.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $file = __FILE__;
	/**
	 * Plugin menu hook.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $hook = false;

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Load the plugin.
		add_action( 'init', array( $this, 'init' ), 0 );
	}

	/**
	 * Loads the plugin into WordPress.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'menu' ), 22 );
	}

	/**
	 * Loads the admin menu item under the SEOPress menu.
	 *
	 * @since 1.0.0
	 */
	public function menu() {
		if ( '1' == seopress_get_toggle_option( 'bot' ) ) {
			add_submenu_page( 'seopress-option', __( 'Audit', 'wp-seopress-pro' ), __( 'Audit', 'wp-seopress-pro' ), seopress_capability( 'manage_options', 'bot' ), $this->plugin_slug, array( $this, 'menu_cb' ), 9 );
		}
	}

	/**
	 * Outputs the menu view.
	 *
	 * @since 1.0.0
	 */
	public function menu_cb() {
		if ( is_plugin_active( 'wp-seopress/seopress.php' ) ) {
			if ( function_exists( 'seopress_admin_header' ) ) {
				echo seopress_admin_header();
			}
		} ?>
		<div class="seopress-option">
			<h1><?php esc_html_e( 'Audit', 'wp-seopress-pro' ); ?></h1>
			<?php
			$current_tab = '';
			?>
			<div id="seopress-tabs" class="wrap">
				<?php
					$plugin_settings_tabs = array(
						'tab_seopress_audit'         => __( 'Site Audit', 'wp-seopress-pro' ),
						'tab_seopress_scan'          => __( 'Scan Broken Links', 'wp-seopress-pro' ),
						'tab_seopress_scan_settings' => __( 'Settings', 'wp-seopress-pro' ),
					);

					echo '<div class="nav-tab-wrapper">';
					foreach ( $plugin_settings_tabs as $tab_key => $tab_caption ) {
						echo '<a id="' . esc_attr( $tab_key ) . '-tab" class="nav-tab" href="?page=seopress-bot-batch#tab=' . esc_attr( $tab_key ) . '">' . esc_html( $tab_caption ) . '</a>';
					}
					echo '</div>';
					?>

				<!-- Site Audit -->
				<div class="seopress-tab seopress-option 
				<?php
				if ( 'tab_seopress_audit' === $current_tab ) {
					echo 'active';
				}
				?>
				" id="tab_seopress_audit">
					<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
						<?php settings_fields( 'seopress_pro_audit_option_group' ); ?>
						<?php do_settings_sections( 'seopress-settings-admin-audit' ); ?>
					</form>

					<hr>
					<h3>
						<?php esc_html_e( 'Issues', 'wp-seopress-pro' ); ?>
					</h3>
					<div id="seopress_site_audit_analysis" class="wrap-site-audit">
						<?php
							$analysis = \SEOPressPro\Helpers\Audit\SEOIssues::getData();
						if ( ! empty( $analysis ) ) {
							foreach ( $analysis as $key => $value ) {
								echo seopress_pro_get_service( 'SiteAudit' )->renderAnalysis( $key, $value );
							}
						} else {
							?>
								<div class="seopress-notice">
									<p>
									<?php esc_html_e( 'Currently no content analysis found. Go back later!', 'wp-seopress-pro' ); ?>
									</p>
								</div>
								<?php
						}
						?>
					</div>
				</div>

				<!-- Scan -->
				<div class="seopress-tab 
				<?php
				if ( 'tab_seopress_scan' === $current_tab ) {
					echo 'active';
				}
				?>
				" id="tab_seopress_scan">
					<?php do_settings_sections( 'seopress-settings-admin-bot' ); ?>

					<?php if ( '' != get_option( 'seopress_bot_log' ) ) { ?>
						<p>
							<strong>
								<?php esc_html_e( 'Latest scan: ', 'wp-seopress-pro' ); ?>
							</strong>
							<?php echo esc_html( get_option( 'seopress_bot_log' ) ); ?>
						</p>

						<p>
							<strong>
								<?php esc_html_e( 'Links found: ', 'wp-seopress-pro' ); ?>
							</strong>
							<?php echo esc_html( wp_count_posts( 'seopress_bot' )->publish ); ?>
						</p>

						<form method="post">
							<input type="hidden" name="seopress_action" value="export_csv_links_settings" />
							<p>
								<?php wp_nonce_field( 'seopress_export_csv_links_nonce', 'seopress_export_csv_links_nonce' ); ?>
								<input type="submit" class="btn btnSecondary" value="<?php esc_html_e( 'Export CSV', 'wp-seopress-pro' ); ?>">
							</p>
						</form>
						<?php
					} else {
						esc_html_e( 'No scan', 'wp-seopress-pro' );
					}
					?>
					<p>
						<div id="seopress_launch_bot" class="btn btnPrimary">
							<?php esc_html_e( 'Launch the bot!', 'wp-seopress-pro' ); ?>
						</div>

						<span class="spinner"></span>
					</p>

					<textarea id="seopress_bot_log" rows="10" width="100%" style="max-width: inherit;" readonly style="display:none"><?php esc_html_e( '---Click Launch the bot! button to run the scan (don\'t close this window)---', 'wp-seopress-pro' ); ?></textarea>
				</div><!--end .wrap-bot-form-->


				<!-- Settings -->
				<div class="seopress-tab seopress-option 
				<?php
				if ( 'tab_seopress_scan_settings' === $current_tab ) {
					echo 'active';
				}
				?>
				" id="tab_seopress_scan_settings">
					<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
						<?php settings_fields( 'seopress_bot_option_group' ); ?>
						<?php do_settings_sections( 'seopress-settings-admin-bot-settings' ); ?>
						<?php sp_submit_button( esc_html__( 'Save changes', 'wp-seopress-pro' ) ); ?>
					</form>
				</div>
		</div><!--seopress-tabs-->
	</div>
		<?php
	}
}

$seopress_bot_batch = new Bot();
