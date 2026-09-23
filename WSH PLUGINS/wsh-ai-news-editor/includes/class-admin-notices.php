<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WSH_AINE_Admin_Notices {

	public static function render( $type, $title, $message, $cta = '' ) {
		?>
		<div class="wsh-aine-notice wsh-aine-notice-<?php echo esc_attr($type); ?>">
			<h3><?php echo esc_html($title); ?></h3>
			<p><?php echo esc_html($message); ?></p>

			<?php if ( $cta ) : ?>
				<a href="<?php echo esc_url( admin_url('admin.php?page=wsh-ai-news-editor-license') ); ?>" class="button button-primary">
					<?php echo esc_html($cta); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function locked_module( $module_name, $plan = 'PRO' ) {
		self::render(
			'error',
			'🔒 Module Locked',
			"$module_name is available in $plan plan.",
			'Upgrade Now'
		);
	}

	public static function limit_reached( $used, $limit ) {
		self::render(
			'warning',
			'Daily Limit Reached',
			"You used $used / $limit AI generations today.",
			'Upgrade for more'
		);
	}
}