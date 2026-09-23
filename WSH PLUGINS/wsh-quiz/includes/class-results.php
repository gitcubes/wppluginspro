<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores finished attempts and builds the public leaderboard.
 */
class WSH_Quiz_Results {

	public static function table_name() : string {
		global $wpdb;
		return $wpdb->prefix . 'wsh_quiz_results';
	}

	public static function create_table() : void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			quiz_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			score int(11) NOT NULL DEFAULT 0,
			max_score int(11) NOT NULL DEFAULT 0,
			duration int(11) NOT NULL DEFAULT 0,
			answers longtext NULL,
			created datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY quiz_score (quiz_id, score, duration),
			KEY quiz_email (quiz_id, email)
		) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * @param array $row Attempt payload.
	 */
	public static function save( array $row ) : int {
		global $wpdb;

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'quiz_id'    => (int) $row['quiz_id'],
				'user_id'    => (int) ( $row['user_id'] ?? 0 ),
				'first_name' => sanitize_text_field( (string) ( $row['first_name'] ?? '' ) ),
				'last_name'  => sanitize_text_field( (string) ( $row['last_name'] ?? '' ) ),
				'email'      => sanitize_email( (string) ( $row['email'] ?? '' ) ),
				'score'      => (int) $row['score'],
				'max_score'  => (int) $row['max_score'],
				'duration'   => (int) ( $row['duration'] ?? 0 ),
				'answers'    => wp_json_encode( $row['answers'] ?? array() ),
				'created'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * @return array<int, object>
	 */
	public static function leaderboard( int $quiz_id, int $limit = 10 ) : array {
		global $wpdb;

		$table = self::table_name();

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT first_name, last_name, score, max_score, duration, created
				FROM {$table}
				WHERE quiz_id = %d
				ORDER BY score DESC, duration ASC, created ASC
				LIMIT %d",
				$quiz_id,
				$limit
			)
		);

		return is_array( $results ) ? $results : array();
	}

	public static function rank( int $quiz_id, int $score, int $duration ) : int {
		global $wpdb;

		$table = self::table_name();
		$ahead = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE quiz_id = %d AND ( score > %d OR ( score = %d AND duration < %d ) )",
				$quiz_id,
				$score,
				$score,
				$duration
			)
		);

		return $ahead + 1;
	}

	public static function display_name( object $row ) : string {
		$name = trim( $row->first_name . ' ' . $row->last_name );
		return '' !== $name ? $name : __( 'Player', 'wsh-quiz' );
	}

	public static function player_count( int $quiz_id ) : int {
		global $wpdb;

		if ( $quiz_id < 1 ) {
			return 0;
		}

		$table = self::table_name();
		$named = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT email) FROM {$table} WHERE quiz_id = %d AND email <> ''",
				$quiz_id
			)
		);
		$anon  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE quiz_id = %d AND email = ''",
				$quiz_id
			)
		);

		return $named + $anon;
	}

	/**
	 * @return array<int, object>
	 */
	public static function list_admin( int $quiz_id, int $limit = 500 ) : array {
		global $wpdb;

		if ( $quiz_id < 1 ) {
			return array();
		}

		$table   = self::table_name();
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, first_name, last_name, email, score, max_score, duration, created
				FROM {$table}
				WHERE quiz_id = %d
				ORDER BY score DESC, duration ASC, created ASC
				LIMIT %d",
				$quiz_id,
				max( 1, $limit )
			)
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * @return object|null
	 */
	public static function get( int $result_id, int $quiz_id = 0 ) {
		global $wpdb;

		if ( $result_id < 1 ) {
			return null;
		}

		$table = self::table_name();
		if ( $quiz_id > 0 ) {
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE id = %d AND quiz_id = %d",
					$result_id,
					$quiz_id
				)
			);
		}

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $result_id )
		);
	}

	public static function delete_one( int $result_id, int $quiz_id ) : bool {
		global $wpdb;

		if ( $result_id < 1 || $quiz_id < 1 ) {
			return false;
		}

		$deleted = $wpdb->delete(
			self::table_name(),
			array(
				'id'      => $result_id,
				'quiz_id' => $quiz_id,
			),
			array( '%d', '%d' )
		);

		return (bool) $deleted;
	}

	public static function cookie_name( int $quiz_id ) : string {
		return 'wsh_quiz_played_' . $quiz_id;
	}

	public static function browser_played( int $quiz_id ) : bool {
		if ( $quiz_id < 1 || empty( $_COOKIE[ self::cookie_name( $quiz_id ) ] ) ) {
			return false;
		}

		$result_id = (int) $_COOKIE[ self::cookie_name( $quiz_id ) ];
		if ( $result_id > 0 ) {
			return (bool) self::get( $result_id, $quiz_id );
		}

		return true;
	}

	public static function mark_browser_played( int $quiz_id, int $result_id = 0 ) : void {
		if ( $quiz_id < 1 || headers_sent() ) {
			return;
		}

		$path  = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$host  = defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '';
		$value = $result_id > 0 ? (string) $result_id : '1';

		setcookie( self::cookie_name( $quiz_id ), $value, time() + YEAR_IN_SECONDS, $path, $host, is_ssl(), true );
		$_COOKIE[ self::cookie_name( $quiz_id ) ] = $value;
	}

	/**
	 * @return object|null
	 */
	public static function find_player( int $quiz_id, int $user_id = 0, string $email = '' ) {
		global $wpdb;

		if ( $quiz_id < 1 ) {
			return null;
		}

		$table = self::table_name();

		if ( $user_id > 0 ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE quiz_id = %d AND user_id = %d ORDER BY id DESC LIMIT 1",
					$quiz_id,
					$user_id
				)
			);
			if ( $row ) {
				return $row;
			}
		}

		$email = sanitize_email( $email );
		if ( '' === $email ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE quiz_id = %d AND email = %s ORDER BY id DESC LIMIT 1",
				$quiz_id,
				$email
			)
		);

		return $row ?: null;
	}

	public static function replay_blocked( int $quiz_id, array $data, int $user_id = 0, string $email = '' ) : bool {
		if ( ! empty( $data['allow_replay'] ) ) {
			return false;
		}

		if ( self::browser_played( $quiz_id ) ) {
			return true;
		}

		return (bool) self::find_player( $quiz_id, $user_id, $email );
	}

	public static function delete_for_quiz( int $quiz_id ) : int {
		global $wpdb;

		if ( $quiz_id < 1 ) {
			return 0;
		}

		return (int) $wpdb->delete(
			self::table_name(),
			array( 'quiz_id' => $quiz_id ),
			array( '%d' )
		);
	}
}
