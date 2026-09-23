<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Access {

	public static function get_plan() : string {
		$plan = WSH_AINE_License::is_active() ? 'pro' : 'free';

		$plan = apply_filters( 'wsh_aine_current_plan', $plan );

		$allowed = array( 'free', 'pro');
		if ( ! in_array( $plan, $allowed, true ) ) {
			$plan = 'free';
		}

		return $plan;
	}

	public static function get_plan_label( ?string $plan = null ) : string {
		$labels = array(
			'free'   => __( 'Free', 'wsh-ai-news-editor' ),
			'pro'    => __( 'Pro', 'wsh-ai-news-editor' ),
		);

		$plan = $plan ?: self::get_plan();

		return $labels[ $plan ] ?? ucfirst( $plan );
	}

	public static function get_plan_rank( ?string $plan = null ) : int {
		$rank = array(
			'free'   => 1,
			'pro'    => 2,
		);

		$plan = $plan ?: self::get_plan();

		return $rank[ $plan ] ?? 1;
	}

	public static function can_access_plan( string $required_plan ) : bool {
		$required_plan = $required_plan ?: 'free';

		return self::get_plan_rank() >= self::get_plan_rank( $required_plan );
	}

	public static function can_access_module( array $module ) : bool {
		$required_plan = isset( $module['required_plan'] ) ? (string) $module['required_plan'] : 'free';
		$allowed       = self::can_access_plan( $required_plan );

		return (bool) apply_filters( 'wsh_aine_can_access_module', $allowed, $module, self::get_plan() );
	}

	public static function get_plan_limits( ?string $plan = null ) : array {
		$plan = $plan ?: self::get_plan();

		$limits = array(
			'free' => array(
				'daily_ai_generations' => 5,
				'source_limits'        => array(
					'local_sources'    => 5,
					'google_sources'   => 5,
					'twitter_accounts' => 2,
					'twitter_topics'   => 2,
					'instagram_accounts' => 0,
				),
			),
			'pro' => array(
				'daily_ai_generations' => 1000,
				'source_limits'        => array(
					'local_sources'    => 50,
					'google_sources'   => 50,
					'twitter_accounts' => 50,
					'twitter_topics'   => 50,
					'instagram_accounts' => 25,
				),
			),
		);

		$plan_limits = $limits[ $plan ] ?? $limits['free'];

		return apply_filters( 'wsh_aine_plan_limits', $plan_limits, $plan );
	}

	public static function get_limit( string $key ) : int {
		$limits = self::get_plan_limits();
		return isset( $limits[ $key ] ) ? (int) $limits[ $key ] : 0;
	}

	public static function get_source_limit( string $source_key ) : int {
		$limits = self::get_plan_limits();
		$map    = isset( $limits['source_limits'] ) && is_array( $limits['source_limits'] ) ? $limits['source_limits'] : array();

		return isset( $map[ $source_key ] ) ? (int) $map[ $source_key ] : 0;
	}

	public static function enforce_source_limit( array $rows, string $source_key ) : array {
		$limit = self::get_source_limit( $source_key );

		if ( $limit > 0 && count( $rows ) > $limit ) {
			$rows = array_slice( array_values( $rows ), 0, $limit );
		}

		return array_values( $rows );
	}

	public static function can_generate_ai() : bool {
		$limit = self::get_limit( 'daily_ai_generations' );

		if ( $limit <= 0 ) {
			return true;
		}

		return WSH_AINE_Usage::get_ai_total_today() < $limit;
	}

	public static function get_ai_limit_message() : string {
		$limit = self::get_limit( 'daily_ai_generations' );

		return sprintf(
			/* translators: 1: current plan label, 2: daily generation limit */
			__( 'You reached the daily AI generation limit for the %1$s plan (%2$d/day).', 'wsh-ai-news-editor' ),
			self::get_plan_label(),
			$limit
		);
	}

	public static function get_upgrade_url() : string {
		return (string) apply_filters( 'wsh_aine_upgrade_url', admin_url( 'admin.php?page=wsh-ai-news-editor-license' ) );
	}

	public static function get_recommended_upgrade_plan( string $required_plan = 'pro' ) : string {
		$current = self::get_plan();

		if ( self::get_plan_rank( $required_plan ) > self::get_plan_rank( $current ) ) {
			return $required_plan;
		}

		return 'pro' === $current ? 'pro' : 'pro';
	}

	public static function get_upgrade_button_label( string $required_plan = 'pro' ) : string {
		return sprintf(
			/* translators: %s: target plan */
			__( 'Upgrade to %s', 'wsh-ai-news-editor' ),
			self::get_plan_label( self::get_recommended_upgrade_plan( $required_plan ) )
		);
	}

	public static function get_usage_warning_level() : string {
		$limit = self::get_limit( 'daily_ai_generations' );
		if ( $limit <= 0 ) {
			return 'ok';
		}

		$used = WSH_AINE_Usage::get_ai_total_today();
		$ratio = $limit > 0 ? $used / $limit : 0;

		if ( $used >= $limit ) {
			return 'limit';
		}
		if ( $ratio >= 0.8 ) {
			return 'warning';
		}
		return 'ok';
	}

	public static function get_usage_warning_message() : string {
		$level = self::get_usage_warning_level();
		$used  = WSH_AINE_Usage::get_ai_total_today();
		$limit = self::get_limit( 'daily_ai_generations' );

		if ( 'limit' === $level ) {
			return self::get_ai_limit_message();
		}
		if ( 'warning' === $level ) {
			return sprintf(
				/* translators: 1: used AI generations today, 2: daily limit */
				__( 'You are close to your daily AI limit: %1$d / %2$d used today.', 'wsh-ai-news-editor' ),
				$used,
				$limit
			);
		}
		return '';
	}
}
