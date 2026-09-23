<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_Quiz_License_Page {

	public static function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage the license.', 'wsh-quiz' ) );
		}

		$notice = null;

		if ( isset( $_POST['wsh_quiz_license_action'] ) && check_admin_referer( 'wsh_quiz_license_form' ) ) {
			$act = sanitize_key( wp_unslash( $_POST['wsh_quiz_license_action'] ) );

			if ( 'activate' === $act ) {
				$key    = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
				$notice = WSH_Quiz_License::activate( $key );
			} elseif ( 'deactivate' === $act ) {
				$notice = WSH_Quiz_License::deactivate();
			} elseif ( 'verify' === $act ) {
				$notice = WSH_Quiz_License::verify( true );
			}
		}

		$key     = WSH_Quiz_License::get_key();
		$status  = WSH_Quiz_License::get_status();
		$expires = WSH_Quiz_License::get_expires();
		$error   = WSH_Quiz_License::get_last_error();
		$plan    = WSH_Quiz_Access::get_plan();
		?>
		<div class="wrap wsh-quiz-license-page">
			<h1><?php esc_html_e( 'WSH Quiz — License', 'wsh-quiz' ); ?></h1>
			<p class="description"><?php esc_html_e( 'One plugin. Free features always work. An active license unlocks Pro.', 'wsh-quiz' ); ?></p>

			<?php if ( is_array( $notice ) ) : ?>
				<div class="notice <?php echo ! empty( $notice['success'] ) ? 'notice-success' : 'notice-error'; ?> is-dismissible">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="wsh-quiz-plan-grid">
				<div class="wsh-quiz-plan-card<?php echo 'free' === $plan ? ' is-current' : ''; ?>">
					<h2><?php esc_html_e( 'Free', 'wsh-quiz' ); ?></h2>
					<p><?php esc_html_e( 'Create quizzes by hand and embed them in posts.', 'wsh-quiz' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Yes / No and multiple choice', 'wsh-quiz' ); ?></li>
						<li><?php esc_html_e( 'Daily, weekly and periodic schedules', 'wsh-quiz' ); ?></li>
						<li><?php esc_html_e( 'Guest entry or required account', 'wsh-quiz' ); ?></li>
						<li><?php esc_html_e( 'Timer, sponsor, leaderboard and share', 'wsh-quiz' ); ?></li>
					</ul>
				</div>
				<div class="wsh-quiz-plan-card<?php echo 'pro' === $plan ? ' is-current' : ''; ?>">
					<h2><?php esc_html_e( 'Pro', 'wsh-quiz' ); ?></h2>
					<p><?php esc_html_e( 'Unlock media questions, AI generation, and engagement tools.', 'wsh-quiz' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Image and video questions', 'wsh-quiz' ); ?></li>
						<li><?php esc_html_e( 'AI quiz from a topic or article', 'wsh-quiz' ); ?></li>
						<li><?php esc_html_e( 'Remove WSH branding', 'wsh-quiz' ); ?></li>
						<li><?php esc_html_e( 'Analytics (coming next)', 'wsh-quiz' ); ?></li>
					</ul>
				</div>
			</div>

			<table class="widefat striped" style="max-width:720px;margin:24px 0;">
				<tbody>
					<tr>
						<th style="width:180px;"><?php esc_html_e( 'Current plan', 'wsh-quiz' ); ?></th>
						<td><strong><?php echo esc_html( WSH_Quiz_Access::get_plan_label() ); ?></strong></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Status', 'wsh-quiz' ); ?></th>
						<td><?php echo 'valid' === $status ? esc_html__( 'License active', 'wsh-quiz' ) : esc_html__( 'License inactive', 'wsh-quiz' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Expires', 'wsh-quiz' ); ?></th>
						<td><?php echo esc_html( $expires ?: '—' ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Last error', 'wsh-quiz' ); ?></th>
						<td><?php echo esc_html( $error ?: '—' ); ?></td>
					</tr>
				</tbody>
			</table>

			<form method="post" style="max-width:720px;">
				<?php wp_nonce_field( 'wsh_quiz_license_form' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="license_key"><?php esc_html_e( 'License key', 'wsh-quiz' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="license_key" name="license_key" value="<?php echo esc_attr( $key ); ?>" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Enter the key from wppluginspro.io and activate it on this site.', 'wsh-quiz' ); ?></p>
						</td>
					</tr>
				</table>
				<p>
					<button class="button button-primary" name="wsh_quiz_license_action" value="activate"><?php esc_html_e( 'Activate', 'wsh-quiz' ); ?></button>
					<button class="button" name="wsh_quiz_license_action" value="verify"><?php esc_html_e( 'Verify', 'wsh-quiz' ); ?></button>
					<button class="button" name="wsh_quiz_license_action" value="deactivate"><?php esc_html_e( 'Deactivate', 'wsh-quiz' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}
}
