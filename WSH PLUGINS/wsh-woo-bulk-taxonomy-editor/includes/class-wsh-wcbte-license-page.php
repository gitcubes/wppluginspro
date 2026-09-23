<?php
if ( ! defined('ABSPATH') ) exit;

class WSH_WCBTE_License_Page {

	public static function init() {
		add_action('admin_menu', array(__CLASS__, 'menu'));
	}

	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__('WSH Taxonomy Editor License', 'wsh-wcbte'),
			__('WSH Taxonomy Editor License', 'wsh-wcbte'),
			'manage_woocommerce',
			'wsh-wcbte-license',
			array(__CLASS__, 'render')
		);
	}

	public static function render() {

		if ( ! current_user_can('manage_woocommerce') ) {
			wp_die('Forbidden');
		}

		$notice = null;

		if ( isset($_POST['wsh_wcbte_action']) && check_admin_referer('wsh_wcbte_license_form') ) {

			$act = sanitize_key($_POST['wsh_wcbte_action']);

			if ( $act === 'activate' ) {
				$key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
				$notice = WSH_WCBTE_License::activate($key);
			} elseif ( $act === 'deactivate' ) {
				$notice = WSH_WCBTE_License::deactivate();
			} elseif ( $act === 'verify' ) {
				$notice = WSH_WCBTE_License::verify(true);
			}
		}

		$key     = WSH_WCBTE_License::get_key();
		$status  = WSH_WCBTE_License::get_status();
		$expires = WSH_WCBTE_License::get_expires();
		$error   = WSH_WCBTE_License::get_last_error();

		?>
		<div class="wrap">
			<h1><?php esc_html_e('WSH WooCommerce Bulk Taxonomy Editor — License', 'wsh-wcbte'); ?></h1>

			<?php if ( is_array($notice) ) : ?>
				<div class="notice <?php echo !empty($notice['success']) ? 'notice-success' : 'notice-error'; ?> is-dismissible">
					<p><?php echo esc_html($notice['message']); ?></p>
				</div>
			<?php endif; ?>

			<table class="widefat striped" style="max-width:900px;">
				<tbody>
					<tr>
						<th style="width:220px;"><?php esc_html_e('Status', 'wsh-wcbte'); ?></th>
						<td>
							<strong><?php echo esc_html($status ?: '—'); ?></strong>
							<?php if ($status === 'valid') : ?>
								<span style="margin-left:10px;padding:2px 8px;border-radius:999px;background:#46b450;color:#fff;"><?php esc_html_e('ACTIVE', 'wsh-wcbte'); ?></span>
							<?php else: ?>
								<span style="margin-left:10px;padding:2px 8px;border-radius:999px;background:#d63638;color:#fff;"><?php esc_html_e('INACTIVE', 'wsh-wcbte'); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e('Expires', 'wsh-wcbte'); ?></th>
						<td><?php echo esc_html($expires ?: '—'); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e('Last error', 'wsh-wcbte'); ?></th>
						<td><?php echo esc_html($error ?: '—'); ?></td>
					</tr>
				</tbody>
			</table>

			<form method="post" style="margin-top:16px;max-width:900px;">
				<?php wp_nonce_field('wsh_wcbte_license_form'); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="license_key"><?php esc_html_e('License key', 'wsh-wcbte'); ?></label></th>
						<td>
							<input type="password" id="license_key" name="license_key" class="regular-text" value="<?php echo esc_attr($key); ?>" />
							<p class="description"><?php esc_html_e('Enter your PRO license key and activate.', 'wsh-wcbte'); ?></p>
						</td>
					</tr>
				</table>

				<p>
					<button class="button button-primary" name="wsh_wcbte_action" value="activate"><?php esc_html_e('Activate', 'wsh-wcbte'); ?></button>
					<button class="button" name="wsh_wcbte_action" value="verify"><?php esc_html_e('Verify', 'wsh-wcbte'); ?></button>
					<button class="button" name="wsh_wcbte_action" value="deactivate"><?php esc_html_e('Deactivate', 'wsh-wcbte'); ?></button>
				</p>
			</form>
		</div>
		<?php
	}
}
