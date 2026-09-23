<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single-plugin plan gate. Free works without a key; Pro unlocks with a valid license.
 */
class WSH_Quiz_Access {

	public static function get_plan() : string {
		$plan = WSH_Quiz_License::is_active() ? 'pro' : 'free';
		$plan = apply_filters( 'wsh_quiz_current_plan', $plan );

		return in_array( $plan, array( 'free', 'pro' ), true ) ? $plan : 'free';
	}

	public static function is_pro() : bool {
		return 'pro' === self::get_plan();
	}

	public static function get_plan_label( ?string $plan = null ) : string {
		$labels = array(
			'free' => __( 'Free', 'wsh-quiz' ),
			'pro'  => __( 'Pro', 'wsh-quiz' ),
		);

		$plan = $plan ?: self::get_plan();

		return $labels[ $plan ] ?? ucfirst( $plan );
	}

	public static function can( string $feature ) : bool {
		if ( ! wsh_quiz_is_pro_feature( $feature ) ) {
			return true;
		}

		$allowed = self::is_pro();

		return (bool) apply_filters( 'wsh_quiz_can_use_feature', $allowed, $feature, self::get_plan() );
	}

	public static function get_upgrade_url() : string {
		return (string) apply_filters(
			'wsh_quiz_upgrade_url',
			admin_url( 'edit.php?post_type=wsh_quiz&page=wsh-quiz-license' )
		);
	}

	public static function get_upgrade_label() : string {
		return __( 'Upgrade to Pro', 'wsh-quiz' );
	}
}
