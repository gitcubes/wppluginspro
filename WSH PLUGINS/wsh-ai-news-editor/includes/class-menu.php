<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Menu {

	public static function init() : void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	public static function register_menu() : void {
		$cap  = 'edit_posts';
		$admin_cap = 'manage_options';
		$slug = 'wsh-ai-news-editor';

		add_menu_page(
			__( 'WSH AI News Editor', 'wsh-ai-news-editor' ),
			__( 'WSH AI News Editor', 'wsh-ai-news-editor' ),
			$cap,
			$slug,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-media-document',
			56
		);

		add_submenu_page(
			$slug,
			__( 'Dashboard', 'wsh-ai-news-editor' ),
			__( 'Dashboard', 'wsh-ai-news-editor' ),
			$cap,
			$slug,
			array( __CLASS__, 'render_dashboard' )
		);

		add_submenu_page(
			$slug,
			__( 'Settings', 'wsh-ai-news-editor' ),
			__( 'Settings', 'wsh-ai-news-editor' ),
			$admin_cap,
			'wsh-ai-news-editor-settings',
			array( 'WSH_AINE_Settings_Page', 'render' )
		);

		foreach ( WSH_AINE_Module_Registry::get_modules() as $module ) {
			$menu_title    = isset( $module['menu_title'] ) ? (string) $module['menu_title'] : '';
			$page_title    = isset( $module['page_title'] ) ? (string) $module['page_title'] : $menu_title;
			$module_slug   = isset( $module['slug'] ) ? (string) $module['slug'] : '';
			$required_plan = isset( $module['required_plan'] ) ? (string) $module['required_plan'] : 'free';

			if ( '' === $menu_title || '' === $module_slug ) {
				continue;
			}

			$callback = self::resolve_module_callback( $module );

			if ( ! WSH_AINE_Access::can_access_module( $module ) ) {
				$menu_title .= ' [' . strtoupper( $required_plan ) . ']';
			}

			add_submenu_page(
				$slug,
				$page_title,
				$menu_title,
				$cap,
				$module_slug,
				$callback
			);
		}

		add_submenu_page(
			$slug,
			__( 'License', 'wsh-ai-news-editor' ),
			__( 'License', 'wsh-ai-news-editor' ),
			$cap,
			'wsh-ai-news-editor-license',
			array( 'WSH_AINE_License_Page', 'render' )
		);
	}

	protected static function resolve_module_callback( array $module ) {
		if ( WSH_AINE_Access::can_access_module( $module ) ) {
			return $module['callback'];
		}

		return function() use ( $module ) {
			self::render_locked_module( $module );
		};
	}

	public static function render_dashboard() : void {
		$modules     = WSH_AINE_Module_Registry::get_modules();
		$plan        = WSH_AINE_Access::get_plan();
		$plan_label  = WSH_AINE_Access::get_plan_label();
		$ai_limit    = WSH_AINE_Access::get_limit( 'daily_ai_generations' );
		$ai_used     = WSH_AINE_Usage::get_ai_total_today();
		$ai_left     = WSH_AINE_Usage::get_remaining_ai_today();
		$warn_level  = WSH_AINE_Access::get_usage_warning_level();
		$warn_msg    = WSH_AINE_Access::get_usage_warning_message();
		$source_keys = array(
			'local_sources'    => __( 'Local RSS', 'wsh-ai-news-editor' ),
			'google_sources'   => __( 'Google News', 'wsh-ai-news-editor' ),
			'twitter_accounts' => __( 'Twitter Accounts', 'wsh-ai-news-editor' ),
			'twitter_topics'   => __( 'Twitter Topics', 'wsh-ai-news-editor' ),
		);
		?>
		<div class="wrap wsh-aine-dashboard-page">
			<h1><?php esc_html_e( 'WSH AI News Editor', 'wsh-ai-news-editor' ); ?></h1>
			<p><?php esc_html_e( 'Welcome to the AI newsroom assistant for WordPress news sites.', 'wsh-ai-news-editor' ); ?></p>

			<div class="wsh-aine-plan-hero">
				<div>
					<div class="wsh-aine-overline"><?php esc_html_e( 'Current plan', 'wsh-ai-news-editor' ); ?></div>
					<div class="wsh-aine-plan-hero__title">
						<span class="wsh-aine-plan-badge wsh-aine-plan-badge--<?php echo esc_attr( $plan ); ?>"><?php echo esc_html( $plan_label ); ?></span>
						<span><?php esc_html_e( 'Usage and module access overview', 'wsh-ai-news-editor' ); ?></span>
					</div>
					<p class="wsh-aine-plan-hero__text"><?php esc_html_e( 'Use this page to track daily AI usage, source capacity, and which premium modules are available on your current plan.', 'wsh-ai-news-editor' ); ?></p>
				</div>
				<div class="wsh-aine-plan-hero__actions">
					<a class="button button-primary" href="<?php echo esc_url( WSH_AINE_Access::get_upgrade_url() ); ?>"><?php echo esc_html( WSH_AINE_Access::get_upgrade_button_label() ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wsh-ai-news-editor-settings' ) ); ?>"><?php esc_html_e( 'Open Settings', 'wsh-ai-news-editor' ); ?></a>
				</div>
			</div>

			<?php if ( $warn_msg ) : ?>
				<div class="notice <?php echo 'limit' === $warn_level ? 'notice-error' : 'notice-warning'; ?> inline"><p><?php echo esc_html( $warn_msg ); ?></p></div>
			<?php endif; ?>

			<div class="wsh-aine-kpi-grid">
				<div class="wsh-aine-kpi-card">
					<div class="wsh-aine-kpi-label"><?php esc_html_e( 'AI generations today', 'wsh-ai-news-editor' ); ?></div>
					<div class="wsh-aine-kpi-value"><?php echo esc_html( (string) $ai_used ); ?> / <?php echo esc_html( (string) $ai_limit ); ?></div>
					<div class="wsh-aine-kpi-meta"><?php echo esc_html( (string) $ai_left ); ?> <?php esc_html_e( 'remaining today', 'wsh-ai-news-editor' ); ?></div>
				</div>
				<?php foreach ( $source_keys as $source_key => $label ) : ?>
					<?php $usage = WSH_AINE_Usage::get_source_usage( $source_key ); ?>
					<div class="wsh-aine-kpi-card">
						<div class="wsh-aine-kpi-label"><?php echo esc_html( $label ); ?></div>
						<div class="wsh-aine-kpi-value"><?php echo esc_html( (string) $usage['count'] ); ?> / <?php echo esc_html( (string) $usage['limit'] ); ?></div>
						<div class="wsh-aine-kpi-meta"><?php esc_html_e( 'configured sources', 'wsh-ai-news-editor' ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="wsh-aine-settings-card" style="margin-top:20px;max-width:1200px;">
				<div class="wsh-aine-section-head">
					<div>
						<h2><?php esc_html_e( 'Modules', 'wsh-ai-news-editor' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Locked modules show an upgrade path directly inside the plugin UI.', 'wsh-ai-news-editor' ); ?></p>
					</div>
				</div>
				<div class="wsh-aine-module-grid">
					<?php foreach ( $modules as $module ) : ?>
						<?php
						$required_plan = (string) ( $module['required_plan'] ?? 'free' );
						$is_available  = WSH_AINE_Access::can_access_module( $module );
						?>
						<div class="wsh-aine-module-card <?php echo $is_available ? 'is-available' : 'is-locked'; ?>">
							<div class="wsh-aine-module-card__top">
								<h3><?php echo esc_html( $module['menu_title'] ?? '' ); ?></h3>
								<span class="wsh-aine-plan-badge wsh-aine-plan-badge--<?php echo esc_attr( $required_plan ); ?>"><?php echo esc_html( WSH_AINE_Access::get_plan_label( $required_plan ) ); ?></span>
							</div>
							<p class="desc"><?php echo esc_html( $module['description'] ?? '' ); ?></p>
							<div class="wsh-aine-module-card__meta">
								<span><?php echo esc_html( ucfirst( (string) ( $module['group'] ?? 'general' ) ) ); ?></span>
								<span class="wsh-aine-status-pill <?php echo $is_available ? 'is-available' : 'is-locked'; ?>"><?php echo $is_available ? esc_html__( 'Available', 'wsh-ai-news-editor' ) : esc_html__( 'Locked', 'wsh-ai-news-editor' ); ?></span>
							</div>
							<div class="wsh-aine-module-card__actions">
								<?php if ( $is_available ) : ?>
									<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . ( $module['slug'] ?? '' ) ) ); ?>"><?php esc_html_e( 'Open Module', 'wsh-ai-news-editor' ); ?></a>
								<?php else : ?>
									<a class="button button-primary" href="<?php echo esc_url( WSH_AINE_Access::get_upgrade_url() ); ?>"><?php echo esc_html( WSH_AINE_Access::get_upgrade_button_label( $required_plan ) ); ?></a>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	public static function render_locked_module( array $module ) : void {
		$required_plan = isset( $module['required_plan'] ) ? (string) $module['required_plan'] : 'pro';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $module['page_title'] ?? __( 'Locked Module', 'wsh-ai-news-editor' ) ); ?></h1>
			<div class="wsh-aine-lock-screen">
				<div class="wsh-aine-lock-screen__badge"><?php echo esc_html( WSH_AINE_Access::get_plan_label( $required_plan ) ); ?></div>
				<h2><?php esc_html_e( 'This module is locked on your current plan.', 'wsh-ai-news-editor' ); ?></h2>
				<p><?php esc_html_e( 'Upgrade to unlock this workflow and use it directly from the plugin menu.', 'wsh-ai-news-editor' ); ?></p>
				<div class="wsh-aine-lock-screen__meta">
					<div><strong><?php esc_html_e( 'Required plan:', 'wsh-ai-news-editor' ); ?></strong> <?php echo esc_html( WSH_AINE_Access::get_plan_label( $required_plan ) ); ?></div>
					<div><strong><?php esc_html_e( 'Current plan:', 'wsh-ai-news-editor' ); ?></strong> <?php echo esc_html( WSH_AINE_Access::get_plan_label() ); ?></div>
				</div>
				<div class="wsh-aine-lock-screen__actions">
					<a class="button button-primary button-hero" href="<?php echo esc_url( WSH_AINE_Access::get_upgrade_url() ); ?>"><?php echo esc_html( WSH_AINE_Access::get_upgrade_button_label( $required_plan ) ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wsh-ai-news-editor-license' ) ); ?>"><?php esc_html_e( 'Open License Page', 'wsh-ai-news-editor' ); ?></a>
				</div>
			</div>
		</div>
		<?php
	}
}
