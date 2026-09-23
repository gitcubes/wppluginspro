<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Wizard.
 *
 * @package Wizard
 */
defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

/**
 * Wizard setup ready.
 *
 * @return void
 */
function seopress_pro_wizard_setup_ready() {
	$seo_title = 'SEOPress PRO';
	if ( method_exists( seopress_get_service( 'ToggleOption' ), 'getToggleWhiteLabel' ) && '1' === seopress_get_service( 'ToggleOption' )->getToggleWhiteLabel() ) {
		$seo_title = seopress_pro_get_service( 'OptionPro' )->getWhiteLabelListTitlePro() ? seopress_pro_get_service( 'OptionPro' )->getWhiteLabelListTitlePro() : 'SEOPress PRO';
	}
	?>
<li class="seopress-wizard-next-step-item">
	<!-- SEOPress PRO -->
	<?php if ( 'valid' !== get_option( 'seopress_pro_license_status' ) && ! is_multisite() ) { ?>
	<div class="seopress-wizard-next-step-description">
		<p class="next-step-heading">
			<?php esc_html_e( 'Next step', 'wp-seopress-pro' ); ?>
		</p>
		<h3 class="next-step-description">
			<?php /* translators: %s default: SEOPress */ printf( esc_html__( 'Welcome to %s!', 'wp-seopress-pro' ), esc_html( $seo_title ) ); ?>
		</h3>
		<p class="next-step-extra-info">
			<?php esc_html_e( 'Please activate your license to receive automatic updates and get premium support.', 'wp-seopress-pro' ); ?>
		</p>
	</div>
	<div class="seopress-wizard-next-step-action">
		<p class="seopress-setup-actions step">
			<a class="btn btnPrimary"
				href="<?php echo esc_url( admin_url( 'admin.php?page=seopress-license' ) ); ?>">
				<?php esc_html_e( 'Activate License', 'wp-seopress-pro' ); ?>
			</a>
		</p>
	</div>
	<?php } ?>
</li>
	<?php
}
add_action( 'seopress_wizard_setup_ready', 'seopress_pro_wizard_setup_ready' );
?>
