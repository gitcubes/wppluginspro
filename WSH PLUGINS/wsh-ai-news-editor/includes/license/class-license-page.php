<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_License_Page {

	public static function init() : void {
		// Meni je već registrovan kroz WSH_AINE_Menu.
	}

	public static function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'wsh-ai-news-editor' ) );
		}

		$notice = null;

		if ( isset( $_POST['wsh_aine_action'] ) && check_admin_referer( 'wsh_aine_license_form' ) ) {
			$act = sanitize_key( wp_unslash( $_POST['wsh_aine_action'] ) );

			if ( 'activate' === $act ) {
				$key    = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
				$notice = WSH_AINE_License::activate( $key );
			} elseif ( 'deactivate' === $act ) {
				$notice = WSH_AINE_License::deactivate();
			} elseif ( 'verify' === $act ) {
				$notice = WSH_AINE_License::verify( true );
			}
		}

		$key        = WSH_AINE_License::get_key();
		$status     = WSH_AINE_License::get_status();
		$expires    = WSH_AINE_License::get_expires();
		$error      = WSH_AINE_License::get_last_error();
		$plan       = WSH_AINE_Access::get_plan();
		$plan_label = WSH_AINE_Access::get_plan_label();
		?>
		<div class="wrap wsh-aine-license-page">
			<h1><?php esc_html_e( 'WSH AI News Editor — License', 'wsh-ai-news-editor' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Manage your active plan and unlock premium AI newsroom workflows.', 'wsh-ai-news-editor' ); ?></p>

			<?php if ( is_array( $notice ) ) : ?>
				<div class="notice <?php echo ! empty( $notice['success'] ) ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<div class="wsh-aine-plan-hero">
				<div>
					<div class="wsh-aine-overline"><?php esc_html_e( 'Active subscription', 'wsh-ai-news-editor' ); ?></div>
					<div class="wsh-aine-plan-hero__title">
						<span class="wsh-aine-plan-badge wsh-aine-plan-badge--<?php echo esc_attr( $plan ); ?>"><?php echo esc_html( $plan_label ); ?></span>
						<span><?php echo 'valid' === $status ? esc_html__( 'License active', 'wsh-ai-news-editor' ) : esc_html__( 'License inactive', 'wsh-ai-news-editor' ); ?></span>
					</div>
					<p class="wsh-aine-plan-hero__text"><?php esc_html_e( 'Activate a license key to switch the plugin from Free to Pro.', 'wsh-ai-news-editor' ); ?></p>
				</div>
			</div>

			<div class="wsh-aine-pricing-grid">
				<?php self::render_plan_card( 'free', __( 'Good for testing the workflow and publishing a few AI drafts per day.', 'wsh-ai-news-editor' ), array( __( '5 AI generations / day', 'wsh-ai-news-editor' ), __( 'Core modules: RSS, Google News, YouTube, AI Editor', 'wsh-ai-news-editor' ), __( 'Up to 5 Local RSS and Google News sources', 'wsh-ai-news-editor' ) ), 'free' === $plan ); ?>
				<?php self::render_plan_card( 'pro', __( 'Best for most publishers who need X, Grok, and Perplexity workflows and Sport News AI API.', 'wsh-ai-news-editor' ), array( __( '1000 AI generations / day', 'wsh-ai-news-editor' ), __( 'Unlock Twitter / X, Grok, and Perplexity modules', 'wsh-ai-news-editor' ), __( '25 sources per source type', 'wsh-ai-news-editor' ) ), 'pro' === $plan ); ?>
			</div>

			<div class="wsh-aine-settings-card" style="max-width:980px;">
				<div class="wsh-aine-section-head">
					<div>
						<h2><?php esc_html_e( 'License status', 'wsh-ai-news-editor' ); ?></h2>
					</div>
				</div>
				<table class="widefat striped">
					<tbody>
						<tr>
							<th style="width:220px;"><?php esc_html_e( 'Status', 'wsh-ai-news-editor' ); ?></th>
							<td>
								<strong><?php echo esc_html( $status ?: '—' ); ?></strong>
								<span class="wsh-aine-status-pill <?php echo 'valid' === $status ? 'is-available' : 'is-locked'; ?>" style="margin-left:10px;"><?php echo 'valid' === $status ? esc_html__( 'ACTIVE', 'wsh-ai-news-editor' ) : esc_html__( 'INACTIVE', 'wsh-ai-news-editor' ); ?></span>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Expires', 'wsh-ai-news-editor' ); ?></th>
							<td><?php echo esc_html( $expires ?: '—' ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Last error', 'wsh-ai-news-editor' ); ?></th>
							<td><?php echo esc_html( $error ?: '—' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<form method="post" class="wsh-aine-settings-card" style="max-width:980px;">
				<?php wp_nonce_field( 'wsh_aine_license_form' ); ?>
				<h2><?php esc_html_e( 'Activate license key', 'wsh-ai-news-editor' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="license_key"><?php esc_html_e( 'License key', 'wsh-ai-news-editor' ); ?></label></th>
						<td>
							<input type="password" id="license_key" name="license_key" class="regular-text" value="<?php echo esc_attr( $key ); ?>" />
							<p class="description"><?php esc_html_e( 'Enter your PRO license key and activate.', 'wsh-ai-news-editor' ); ?></p>
						</td>
					</tr>
				</table>
				<p>
					<button class="button button-primary" name="wsh_aine_action" value="activate"><?php esc_html_e( 'Activate', 'wsh-ai-news-editor' ); ?></button>
					<button class="button" name="wsh_aine_action" value="verify"><?php esc_html_e( 'Verify', 'wsh-ai-news-editor' ); ?></button>
					<button class="button" name="wsh_aine_action" value="deactivate"><?php esc_html_e( 'Deactivate', 'wsh-ai-news-editor' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	protected static function render_plan_card( string $plan, string $text, array $features, bool $is_current ) : void {
		?>
		<div class="wsh-aine-pricing-card <?php echo $is_current ? 'is-current' : ''; ?>">
			<div class="wsh-aine-pricing-card__top">
				<span class="wsh-aine-plan-badge wsh-aine-plan-badge--<?php echo esc_attr( $plan ); ?>"><?php echo esc_html( WSH_AINE_Access::get_plan_label( $plan ) ); ?></span>
				<?php if ( $is_current ) : ?>
					<span class="wsh-aine-status-pill is-available"><?php esc_html_e( 'Current', 'wsh-ai-news-editor' ); ?></span>
				<?php endif; ?>
			</div>
			<p><?php echo esc_html( $text ); ?></p>
			<ul>
				<?php foreach ( $features as $feature ) : ?>
					<li><?php echo esc_html( $feature ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}
