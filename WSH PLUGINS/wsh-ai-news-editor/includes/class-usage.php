<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Usage {

	const OPTION_DAILY_USAGE = 'wsh_aine_daily_usage';

	public static function get_today_key() : string {
		return (string) current_time( 'Y-m-d' );
	}

	public static function get_daily_usage( ?string $date_key = null ) : array {
		$date_key = $date_key ?: self::get_today_key();
		$all = get_option( self::OPTION_DAILY_USAGE, array() );

		if ( ! is_array( $all ) ) {
			$all = array();
		}

		self::maybe_cleanup_old_usage( $all );

		$usage = isset( $all[ $date_key ] ) && is_array( $all[ $date_key ] ) ? $all[ $date_key ] : array();

		return wp_parse_args(
			$usage,
			array(
				'ai_total'    => 0,
				'ai_article'  => 0,
				'ai_rewrite'  => 0,
				'ai_comments' => 0,
			)
		);
	}

	public static function increment_ai( string $type = 'article' ) : array {
		$date_key = self::get_today_key();
		$all = get_option( self::OPTION_DAILY_USAGE, array() );

		if ( ! is_array( $all ) ) {
			$all = array();
		}

		self::maybe_cleanup_old_usage( $all );

		if ( ! isset( $all[ $date_key ] ) || ! is_array( $all[ $date_key ] ) ) {
			$all[ $date_key ] = array();
		}

		$all[ $date_key ] = wp_parse_args(
			$all[ $date_key ],
			array(
				'ai_total'    => 0,
				'ai_article'  => 0,
				'ai_rewrite'  => 0,
				'ai_comments' => 0,
			)
		);

		$map = array(
			'article'  => 'ai_article',
			'rewrite'  => 'ai_rewrite',
			'comments' => 'ai_comments',
		);

		$key = $map[ $type ] ?? 'ai_article';

		$all[ $date_key ]['ai_total']++;
		$all[ $date_key][ $key ]++;

		update_option( self::OPTION_DAILY_USAGE, $all, false );

		return $all[ $date_key ];
	}

	public static function get_ai_total_today() : int {
		$usage = self::get_daily_usage();
		return isset( $usage['ai_total'] ) ? (int) $usage['ai_total'] : 0;
	}

	public static function get_remaining_ai_today() : int {
		$limit = WSH_AINE_Access::get_limit( 'daily_ai_generations' );

		if ( $limit <= 0 ) {
			return PHP_INT_MAX;
		}

		return max( 0, $limit - self::get_ai_total_today() );
	}

	public static function get_source_count( string $source_key ) : int {
		$options = get_option( 'wsh_aine_settings', array() );
		$rows    = isset( $options[ $source_key ] ) && is_array( $options[ $source_key ] ) ? $options[ $source_key ] : array();

		return count( $rows );
	}

	public static function get_source_usage( string $source_key ) : array {
		$limit = WSH_AINE_Access::get_source_limit( $source_key );
		$count = self::get_source_count( $source_key );

		return array(
			'count'     => $count,
			'limit'     => $limit,
			'remaining' => $limit > 0 ? max( 0, $limit - $count ) : PHP_INT_MAX,
		);
	}

	protected static function maybe_cleanup_old_usage( array &$all ) : void {
		if ( empty( $all ) ) {
			return;
		}

		$cutoff = strtotime( '-14 days', current_time( 'timestamp' ) );

		foreach ( $all as $date_key => $row ) {
			$ts = strtotime( $date_key . ' 00:00:00' );
			if ( false !== $ts && $ts < $cutoff ) {
				unset( $all[ $date_key ] );
			}
		}
	}
}
