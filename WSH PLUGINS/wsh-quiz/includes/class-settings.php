<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global branding and default quiz settings.
 */
class WSH_Quiz_Settings {

	const OPTION = 'wsh_quiz_global_settings';

	public static function init() : void {
		add_action( 'admin_init', array( __CLASS__, 'maybe_save' ) );
	}

	public static function defaults() : array {
		return array(
			'slogan'               => '',
			'logo_id'              => 0,
			'color_accent'         => '#d63638',
			'color_text'           => '#1d2327',
			'color_background'     => '#ffffff',
			'font_family'          => 'inherit',
			'default_schedule'     => 'open',
			'default_timer'        => 60,
			'default_entry_mode'   => 'guest',
			'default_leaderboard'  => true,
			'default_allow_replay' => true,
			'openai_api_key'       => '',
			'openai_model'         => 'gpt-4.1-mini',
		);
	}

	public static function get() : array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$data = array_merge( self::defaults(), $stored );

		$schedules = array_keys( wsh_quiz_schedule_types() );
		if ( ! in_array( $data['default_schedule'], $schedules, true ) ) {
			$data['default_schedule'] = 'open';
		}

		$modes = array_keys( wsh_quiz_entry_modes() );
		if ( ! in_array( $data['default_entry_mode'], $modes, true ) ) {
			$data['default_entry_mode'] = 'guest';
		}

		$data['logo_id']              = (int) $data['logo_id'];
		$data['default_timer']        = max( 0, (int) $data['default_timer'] );
		$data['default_leaderboard']  = ! empty( $data['default_leaderboard'] );
		$data['default_allow_replay'] = ! empty( $data['default_allow_replay'] );
		$data['color_accent']         = self::sanitize_color( (string) $data['color_accent'], '#d63638' );
		$data['color_text']           = self::sanitize_color( (string) $data['color_text'], '#1d2327' );
		$data['color_background']     = self::sanitize_color( (string) $data['color_background'], '#ffffff' );
		$data['font_family']          = sanitize_text_field( (string) $data['font_family'] );
		$data['slogan']               = sanitize_text_field( (string) $data['slogan'] );
		$data['openai_api_key']       = sanitize_text_field( (string) $data['openai_api_key'] );
		$data['openai_model']         = self::sanitize_model( (string) $data['openai_model'] );

		return $data;
	}

	public static function sanitize_model( string $model ) : string {
		$model = preg_replace( '/[^a-zA-Z0-9._-]/', '', $model );
		return is_string( $model ) && '' !== $model ? $model : 'gpt-4.1-mini';
	}

	public static function sanitize_color( string $color, string $fallback ) : string {
		$color = sanitize_hex_color( $color );
		return is_string( $color ) && '' !== $color ? $color : $fallback;
	}

	public static function contrast_color( string $hex ) : string {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== strlen( $hex ) ) {
			return '#ffffff';
		}

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		$luminance = ( ( 0.299 * $r ) + ( 0.587 * $g ) + ( 0.114 * $b ) ) / 255;

		return $luminance > 0.62 ? '#1d2327' : '#ffffff';
	}

	public static function font_choices() : array {
		return array(
			'inherit'                         => __( 'Theme default', 'wsh-quiz' ),
			'system-ui, sans-serif'           => __( 'System UI', 'wsh-quiz' ),
			'Arial, Helvetica, sans-serif'    => __( 'Arial', 'wsh-quiz' ),
			'Georgia, serif'                  => __( 'Georgia', 'wsh-quiz' ),
			'"Times New Roman", Times, serif' => __( 'Times New Roman', 'wsh-quiz' ),
		);
	}

	public static function logo_url( ?array $settings = null ) : string {
		$settings = $settings ?: self::get();
		if ( $settings['logo_id'] < 1 ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $settings['logo_id'], 'medium' );
		return is_string( $url ) ? $url : '';
	}

	public static function css_variables( ?array $settings = null ) : string {
		$settings = $settings ?: self::get();

		return sprintf(
			'.wsh-quiz,.wsh-quiz-list,.wsh-quiz-widget,.wsh-quiz-board{--wsh-quiz-accent:%1$s;--wsh-quiz-on-accent:%2$s;--wsh-quiz-text:%3$s;--wsh-quiz-bg:%4$s;font-family:%5$s;color:%3$s;}',
			$settings['color_accent'],
			self::contrast_color( $settings['color_accent'] ),
			$settings['color_text'],
			$settings['color_background'],
			$settings['font_family']
		);
	}

	public static function maybe_save() : void {
		if ( ! isset( $_POST['wsh_quiz_settings_nonce'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wsh_quiz_settings_nonce'] ) ), 'wsh_quiz_save_settings' ) ) {
			return;
		}

		$raw      = isset( $_POST['wsh_quiz_settings'] ) && is_array( $_POST['wsh_quiz_settings'] ) ? wp_unslash( $_POST['wsh_quiz_settings'] ) : array();
		$defaults = self::defaults();
		$current  = self::get();
		$key      = isset( $raw['openai_api_key'] ) ? trim( (string) $raw['openai_api_key'] ) : '';

		if ( '' === $key ) {
			$key = $current['openai_api_key'];
		}

		$saved = array(
			'slogan'               => isset( $raw['slogan'] ) ? sanitize_text_field( (string) $raw['slogan'] ) : '',
			'logo_id'              => isset( $raw['logo_id'] ) ? (int) $raw['logo_id'] : 0,
			'color_accent'         => self::sanitize_color( isset( $raw['color_accent'] ) ? (string) $raw['color_accent'] : '', $defaults['color_accent'] ),
			'color_text'           => self::sanitize_color( isset( $raw['color_text'] ) ? (string) $raw['color_text'] : '', $defaults['color_text'] ),
			'color_background'     => self::sanitize_color( isset( $raw['color_background'] ) ? (string) $raw['color_background'] : '', $defaults['color_background'] ),
			'font_family'          => isset( $raw['font_family'] ) ? sanitize_text_field( (string) $raw['font_family'] ) : 'inherit',
			'default_schedule'     => isset( $raw['default_schedule'] ) ? sanitize_key( (string) $raw['default_schedule'] ) : 'open',
			'default_timer'        => isset( $raw['default_timer'] ) ? max( 0, (int) $raw['default_timer'] ) : 60,
			'default_entry_mode'   => isset( $raw['default_entry_mode'] ) ? sanitize_key( (string) $raw['default_entry_mode'] ) : 'guest',
			'default_leaderboard'  => ! empty( $raw['default_leaderboard'] ),
			'default_allow_replay' => ! empty( $raw['default_allow_replay'] ),
			'openai_api_key'       => sanitize_text_field( $key ),
			'openai_model'         => self::sanitize_model( isset( $raw['openai_model'] ) ? (string) $raw['openai_model'] : $defaults['openai_model'] ),
		);

		update_option( self::OPTION, $saved );

		add_settings_error( 'wsh_quiz_settings', 'saved', __( 'Settings saved.', 'wsh-quiz' ), 'updated' );
	}

	public static function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change quiz settings.', 'wsh-quiz' ) );
		}

		$settings = self::get();
		$logo     = self::logo_url( $settings );

		include WSH_QUIZ_PATH . 'templates/admin/settings-page.php';
	}
}

function wsh_quiz_get_global_settings() : array {
	return WSH_Quiz_Settings::get();
}
