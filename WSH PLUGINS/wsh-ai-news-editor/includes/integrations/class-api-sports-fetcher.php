<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_API_Sports_Fetcher {

	const CACHE_TTL = 300;

	public static function get_supported_sports_config(): array {
		return array(
			'football' => array(
				'label'    => 'Football',
				'api_base' => 'https://v3.football.api-sports.io',
				'endpoint' => 'fixtures',
				'date_param' => 'date',
				'leagues'  => array(
					'super_liga' => array(
						'label'  => 'Super liga',
						'id'     => 286,
						'season' => 2026,
					),
					'premier_league' => array(
						'label'  => 'Premier League',
						'id'     => 39,
						'season' => 2026,
					),
					'la_liga' => array(
						'label'  => 'La Liga',
						'id'     => 140,
						'season' => 2026,
					),
					'serie_a' => array(
						'label'  => 'Serie A',
						'id'     => 135,
						'season' => 2026,
					),
					'bundesliga' => array(
						'label'  => 'Bundesliga',
						'id'     => 78,
						'season' => 2026,
					),
					'champions_league' => array(
						'label'  => 'Champions League',
						'id'     => 2,
						'season' => 2026,
					),
					'prva_liga' => array(
						'label'  => 'Prva liga',
						'id'     => 287,
						'season' => 2026,
					),
					'world_cup' => array(
						'label'       => 'World Cup',
						'id'          => 1,
						'season'      => 2026,
						'season_type' => 'calendar',
					),
				),
			),

			'basketball' => array(
				'label'    => 'Basketball',
				'api_base' => 'https://v1.basketball.api-sports.io',
				'endpoint' => 'games',
				'date_param' => 'date',
				'leagues'  => array(
					'nba' => array(
						'label'  => 'NBA',
						'id'     => 12,
						'season' => '2026-2027',
					),
					'euroleague' => array(
						'label'  => 'EuroLeague',
						'id'     => 120,
						'season' => '2026-2027',
					),
					'aba_league' => array(
						'label'  => 'ABA League',
						'id'     => 104,
						'season' => '2026-2027',
					),
				),
			),
		);
	}

	public static function get_events( string $sport_key, string $league_key, string $mode, array $settings, string $date = '', bool $force_refresh = false ) {
		$config = self::get_supported_sports_config();

		if ( empty( $config[ $sport_key ] ) ) {
			return new WP_Error( 'invalid_sport', __( 'Invalid sport selected.', 'wsh-ai-news-editor' ) );
		}

		if ( empty( $config[ $sport_key ]['leagues'][ $league_key ] ) ) {
			return new WP_Error( 'invalid_league', __( 'Invalid league selected.', 'wsh-ai-news-editor' ) );
		}

		if ( ! in_array( $mode, array( 'live', 'fixtures', 'results', 'standings' ), true ) ) {
			$mode = 'results';
		}

		$api_key = isset( $settings['api_sports_api_key'] ) ? trim( (string) $settings['api_sports_api_key'] ) : '';

		if ( '' === $api_key ) {
			return new WP_Error( 'missing_api_sports_key', __( 'API-SPORTS API key is not configured.', 'wsh-ai-news-editor' ) );
		}

		if ( '' === $date ) {
			$date = gmdate( 'Y-m-d' );
		}

		$sport  = $config[ $sport_key ];
		$league = $sport['leagues'][ $league_key ];
		$season = self::resolve_season( $sport_key, $league, $date );

		$cache_key = 'wsh_aine_sports_' . md5(
			wp_json_encode(
				array(
					'sport'  => $sport_key,
					'league' => $league_key,
					'mode'   => $mode,
					'date'   => $date,
					'season' => $season,
					'v'      => 4,
				)
			)
		);

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		} else {
			delete_transient( $cache_key );
		}

		$data = self::request_events_data( $sport_key, $sport, $league, $mode, $season, $date, $api_key );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$items = self::map_event_items( $sport_key, $mode, $data );

		if ( 'live' === $mode && 'football' === $sport_key ) {
			$items = self::enrich_football_live_items( $items, $api_key );
		}

		$result = array(
			'sport_key'    => $sport_key,
			'sport_label'  => $sport['label'],
			'league_key'   => $league_key,
			'league_label' => $league['label'],
			'league_id'    => $league['id'],
			'season'       => $season,
			'mode'         => $mode,
			'date'         => $date,
			'query'        => $sport['label'] . ' - ' . $league['label'],
			'summary'      => self::build_summary( $sport['label'], $league['label'], $mode, $date, $items, $season ),
			'items'        => array_values( $items ),
		);

		$ttl = ( 'live' === $mode ) ? 30 : self::CACHE_TTL;
		set_transient( $cache_key, $result, $ttl );

		return $result;
	}

	protected static function resolve_season( string $sport_key, array $league, string $date ): string {
		$timestamp = strtotime( $date );
		if ( ! $timestamp ) {
			$timestamp = time();
		}

		$year  = (int) gmdate( 'Y', $timestamp );
		$month = (int) gmdate( 'n', $timestamp );
		$type  = isset( $league['season_type'] ) ? (string) $league['season_type'] : '';

		if ( '' === $type ) {
			$type = ( 'basketball' === $sport_key ) ? 'nba' : 'european';
		}

		if ( 'calendar' === $type ) {
			return (string) $year;
		}

		if ( 'nba' === $type ) {
			if ( $month >= 10 ) {
				return $year . '-' . ( $year + 1 );
			}

			return ( $year - 1 ) . '-' . $year;
		}

		if ( $month >= 7 ) {
			return (string) $year;
		}

		return (string) ( $year - 1 );
	}

	protected static function request_events_data( string $sport_key, array $sport, array $league, string $mode, string $season, string $date, string $api_key ) {
		if ( 'standings' === $mode ) {
			return self::request_api(
				$sport['api_base'],
				'standings',
				array(
					'league' => $league['id'],
					'season' => $season,
				),
				$api_key
			);
		}

		if ( 'live' === $mode ) {
			return self::request_live_data( $sport_key, $sport, $league, $season, $date, $api_key );
		}

		$data = self::request_api(
			$sport['api_base'],
			$sport['endpoint'],
			array(
				$sport['date_param'] => $date,
				'league'             => $league['id'],
				'season'             => $season,
			),
			$api_key
		);

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$items = self::map_event_items( $sport_key, $mode, $data );

		if ( ! empty( $items ) ) {
			return $data;
		}

		if ( 'basketball' === $sport_key ) {
			return self::request_api(
				$sport['api_base'],
				$sport['endpoint'],
				array(
					'league' => $league['id'],
					'season' => $season,
				),
				$api_key
			);
		}

		$fallback_param = ( 'fixtures' === $mode ) ? 'next' : 'last';

		return self::request_api(
			$sport['api_base'],
			$sport['endpoint'],
			array(
				'league'        => $league['id'],
				'season'        => $season,
				$fallback_param => 20,
			),
			$api_key
		);
	}

	protected static function map_event_items( string $sport_key, string $mode, array $data ): array {
		if ( 'standings' === $mode ) {
			if ( 'football' === $sport_key ) {
				return self::map_football_standings( $data );
			}

			if ( 'basketball' === $sport_key ) {
				return self::map_basketball_standings( $data );
			}

			return array();
		}

		if ( 'football' === $sport_key ) {
			return self::map_football_items( $data, $mode );
		}

		if ( 'basketball' === $sport_key ) {
			return self::map_basketball_items( $data, $mode );
		}

		return array();
	}

	protected static function request_live_data( string $sport_key, array $sport, array $league, string $season, string $date, string $api_key ) {
		if ( 'football' === $sport_key ) {
			return self::request_api(
				$sport['api_base'],
				$sport['endpoint'],
				array(
					'live'   => 'all',
					'league' => $league['id'],
				),
				$api_key
			);
		}

		return self::request_basketball_live_data(
			$sport['api_base'],
			(int) $league['id'],
			$season,
			$date,
			$api_key
		);
	}

	protected static function request_basketball_live_data( string $api_base, int $league_id, string $season, string $date, string $api_key ) {
		$dates = array( $date );

		$yesterday = gmdate( 'Y-m-d', strtotime( $date . ' -1 day' ) );
		if ( $yesterday && $yesterday !== $date ) {
			$dates[] = $yesterday;
		}

		$merged = array(
			'response' => array(),
		);
		$seen = array();

		foreach ( $dates as $query_date ) {
			$data = self::request_api(
				$api_base,
				'games',
				array(
					'date'   => $query_date,
					'league' => $league_id,
					'season' => $season,
				),
				$api_key
			);

			if ( is_wp_error( $data ) ) {
				if ( empty( $merged['response'] ) ) {
					return $data;
				}
				continue;
			}

			if ( empty( $data['response'] ) || ! is_array( $data['response'] ) ) {
				continue;
			}

			foreach ( $data['response'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$game_id = isset( $row['id'] ) ? (int) $row['id'] : 0;
				if ( $game_id > 0 ) {
					if ( isset( $seen[ $game_id ] ) ) {
						continue;
					}
					$seen[ $game_id ] = true;
				}

				$merged['response'][] = $row;
			}
		}

		return $merged;
	}

	protected static function find_league_config( string $sport_key, int $league_id ): array {
		$config  = self::get_supported_sports_config();
		$leagues = isset( $config[ $sport_key ]['leagues'] ) && is_array( $config[ $sport_key ]['leagues'] )
			? $config[ $sport_key ]['leagues']
			: array();

		foreach ( $leagues as $league ) {
			if ( isset( $league['id'] ) && (int) $league['id'] === $league_id ) {
				return $league;
			}
		}

		return array();
	}

	protected static function request_api( string $api_base, string $endpoint, array $query, string $api_key ) {
		$url = add_query_arg(
			$query,
			trailingslashit( $api_base ) . ltrim( $endpoint, '/' )
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => self::get_request_headers( $api_key, $url ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( 200 !== $code || ! is_array( $data ) ) {
			$message = self::extract_api_error( is_array( $data ) ? $data : array() );

			if ( '' === $message ) {
				$message = sprintf(
					/* translators: %d: HTTP status code */
					__( 'Unexpected response from API-SPORTS (HTTP %d).', 'wsh-ai-news-editor' ),
					$code
				);
			}

			return new WP_Error( 'api_sports_http_error', $message );
		}

		$error = self::extract_api_error( $data );

		if ( '' !== $error ) {
			return new WP_Error( 'api_sports_error', $error );
		}

		return $data;
	}

	protected static function get_request_headers( string $api_key, string $url ): array {
		$headers = array(
			'x-apisports-key' => $api_key,
			'x-rapidapi-key'  => $api_key,
		);

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( is_string( $host ) && '' !== $host ) {
			$headers['x-rapidapi-host'] = $host;
		}

		return $headers;
	}

	protected static function extract_api_error( array $data ): string {
		if ( empty( $data['errors'] ) ) {
			return '';
		}

		$errors = $data['errors'];

		if ( is_string( $errors ) ) {
			return trim( $errors );
		}

		if ( ! is_array( $errors ) ) {
			return '';
		}

		$parts = array();

		foreach ( $errors as $key => $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$parts[] = is_string( $key ) && ! is_numeric( $key )
					? $key . ': ' . $value
					: $value;
			} elseif ( is_array( $value ) ) {
				$nested = array_filter( $value, 'is_string' );
				if ( ! empty( $nested ) ) {
					$parts[] = implode( ', ', $nested );
				}
			}
		}

		return implode( ' ', $parts );
	}

	private static function map_football_items( array $data, string $mode ): array {
		$items = array();

		if ( empty( $data['response'] ) || ! is_array( $data['response'] ) ) {
			return $items;
		}

		foreach ( $data['response'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$status_short = isset( $row['fixture']['status']['short'] ) ? (string) $row['fixture']['status']['short'] : '';
			$status_long  = isset( $row['fixture']['status']['long'] ) ? (string) $row['fixture']['status']['long'] : '';
			$elapsed      = isset( $row['fixture']['status']['elapsed'] ) ? (string) $row['fixture']['status']['elapsed'] : '';
			$match_date   = isset( $row['fixture']['date'] ) ? (string) $row['fixture']['date'] : '';
			$home_name    = isset( $row['teams']['home']['name'] ) ? (string) $row['teams']['home']['name'] : '';
			$away_name    = isset( $row['teams']['away']['name'] ) ? (string) $row['teams']['away']['name'] : '';
			$home_goals   = isset( $row['goals']['home'] ) && null !== $row['goals']['home'] ? (string) $row['goals']['home'] : '-';
			$away_goals   = isset( $row['goals']['away'] ) && null !== $row['goals']['away'] ? (string) $row['goals']['away'] : '-';
			$league_name  = isset( $row['league']['name'] ) ? (string) $row['league']['name'] : '';
			$country_name = isset( $row['league']['country'] ) ? (string) $row['league']['country'] : '';

			if ( ! self::match_mode_filter( 'football', $mode, $status_short ) ) {
				continue;
			}

			$title = trim( $home_name . ' vs ' . $away_name );
			$score = $home_goals . ' - ' . $away_goals;

			$snippet_parts = array_filter(
				array(
					$league_name,
					$country_name,
					$title,
					$score,
					$status_long,
				)
			);

			$items[] = array(
				'title'        => $title,
				'status'       => $status_short,
				'status_long'  => $status_long,
				'time'         => $elapsed,
				'score'        => $score,
				'snippet'      => implode( ' | ', $snippet_parts ),
				'source'       => 'API-SPORTS',
				'url'          => '',
				'fixture_id'   => isset( $row['fixture']['id'] ) ? (int) $row['fixture']['id'] : 0,
				'referee'      => isset( $row['fixture']['referee'] ) ? (string) $row['fixture']['referee'] : '',
				'timezone'     => isset( $row['fixture']['timezone'] ) ? (string) $row['fixture']['timezone'] : '',
				'date'         => $match_date,
				'timestamp'    => isset( $row['fixture']['timestamp'] ) ? (int) $row['fixture']['timestamp'] : 0,
				'venue_name'   => isset( $row['fixture']['venue']['name'] ) ? (string) $row['fixture']['venue']['name'] : '',
				'venue_city'   => isset( $row['fixture']['venue']['city'] ) ? (string) $row['fixture']['venue']['city'] : '',
				'league_id'    => isset( $row['league']['id'] ) ? (int) $row['league']['id'] : 0,
				'league_name'  => $league_name,
				'league_logo'  => isset( $row['league']['logo'] ) ? esc_url_raw( (string) $row['league']['logo'] ) : '',
				'league_flag'  => isset( $row['league']['flag'] ) ? esc_url_raw( (string) $row['league']['flag'] ) : '',
				'country_name' => $country_name,
				'season'       => isset( $row['league']['season'] ) ? (string) $row['league']['season'] : '',
				'round'        => isset( $row['league']['round'] ) ? (string) $row['league']['round'] : '',

				'home_team' => array(
					'id'     => isset( $row['teams']['home']['id'] ) ? (int) $row['teams']['home']['id'] : 0,
					'name'   => $home_name,
					'logo'   => isset( $row['teams']['home']['logo'] ) ? esc_url_raw( (string) $row['teams']['home']['logo'] ) : '',
					'winner' => isset( $row['teams']['home']['winner'] ) ? $row['teams']['home']['winner'] : null,
				),
				'away_team' => array(
					'id'     => isset( $row['teams']['away']['id'] ) ? (int) $row['teams']['away']['id'] : 0,
					'name'   => $away_name,
					'logo'   => isset( $row['teams']['away']['logo'] ) ? esc_url_raw( (string) $row['teams']['away']['logo'] ) : '',
					'winner' => isset( $row['teams']['away']['winner'] ) ? $row['teams']['away']['winner'] : null,
				),

				'goals' => array(
					'home' => isset( $row['goals']['home'] ) ? $row['goals']['home'] : null,
					'away' => isset( $row['goals']['away'] ) ? $row['goals']['away'] : null,
				),

				'score_breakdown' => array(
					'halftime'  => isset( $row['score']['halftime'] ) ? $row['score']['halftime'] : array(),
					'fulltime'  => isset( $row['score']['fulltime'] ) ? $row['score']['fulltime'] : array(),
					'extratime' => isset( $row['score']['extratime'] ) ? $row['score']['extratime'] : array(),
					'penalty'   => isset( $row['score']['penalty'] ) ? $row['score']['penalty'] : array(),
				),

				'raw' => $row,
			);
		}

		return array_slice( $items, 0, 30 );
	}

	private static function map_basketball_items( array $data, string $mode ): array {
		$items = array();

		if ( empty( $data['response'] ) || ! is_array( $data['response'] ) ) {
			return $items;
		}

		foreach ( $data['response'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$status_short = isset( $row['status']['short'] ) ? (string) $row['status']['short'] : '';
			$status_long  = isset( $row['status']['long'] ) ? (string) $row['status']['long'] : '';
			$timer        = isset( $row['status']['timer'] ) ? (string) $row['status']['timer'] : '';
			$game_date    = isset( $row['date'] ) ? (string) $row['date'] : '';
			$game_time    = isset( $row['time'] ) ? (string) $row['time'] : '';
			$home_name    = isset( $row['teams']['home']['name'] ) ? (string) $row['teams']['home']['name'] : '';
			$away_name    = isset( $row['teams']['away']['name'] ) ? (string) $row['teams']['away']['name'] : '';
			$home_total   = isset( $row['scores']['home']['total'] ) && null !== $row['scores']['home']['total'] ? (string) $row['scores']['home']['total'] : '-';
			$away_total   = isset( $row['scores']['away']['total'] ) && null !== $row['scores']['away']['total'] ? (string) $row['scores']['away']['total'] : '-';
			$league_name  = isset( $row['league']['name'] ) ? (string) $row['league']['name'] : '';
			$country_name = isset( $row['country']['name'] ) ? (string) $row['country']['name'] : '';

			if ( ! self::match_mode_filter( 'basketball', $mode, $status_short ) ) {
				continue;
			}

			$title = trim( $home_name . ' vs ' . $away_name );
			$score = $home_total . ' - ' . $away_total;

			$snippet_parts = array_filter(
				array(
					$league_name,
					$country_name,
					$title,
					$score,
					$status_long,
				)
			);

			$items[] = array(
				'title'        => $title,
				'status'       => $status_short,
				'status_long'  => $status_long,
				'time'         => $timer ? $timer : $game_time,
				'score'        => $score,
				'snippet'      => implode( ' | ', $snippet_parts ),
				'source'       => 'API-SPORTS',
				'url'          => '',
				'game_id'      => isset( $row['id'] ) ? (int) $row['id'] : 0,
				'timezone'     => isset( $row['timezone'] ) ? (string) $row['timezone'] : '',
				'date'         => $game_date,
				'timestamp'    => isset( $row['timestamp'] ) ? (int) $row['timestamp'] : 0,
				'venue_name'   => isset( $row['venue'] ) ? (string) $row['venue'] : '',
				'league_id'    => isset( $row['league']['id'] ) ? (int) $row['league']['id'] : 0,
				'league_name'  => $league_name,
				'league_logo'  => isset( $row['league']['logo'] ) ? esc_url_raw( (string) $row['league']['logo'] ) : '',
				'country_name' => $country_name,
				'season'       => isset( $row['league']['season'] ) ? (string) $row['league']['season'] : '',

				'home_team' => array(
					'id'   => isset( $row['teams']['home']['id'] ) ? (int) $row['teams']['home']['id'] : 0,
					'name' => $home_name,
					'logo' => isset( $row['teams']['home']['logo'] ) ? esc_url_raw( (string) $row['teams']['home']['logo'] ) : '',
				),
				'away_team' => array(
					'id'   => isset( $row['teams']['away']['id'] ) ? (int) $row['teams']['away']['id'] : 0,
					'name' => $away_name,
					'logo' => isset( $row['teams']['away']['logo'] ) ? esc_url_raw( (string) $row['teams']['away']['logo'] ) : '',
				),

				'score_breakdown' => array(
					'home' => isset( $row['scores']['home'] ) ? $row['scores']['home'] : array(),
					'away' => isset( $row['scores']['away'] ) ? $row['scores']['away'] : array(),
				),

				'raw' => $row,
			);
		}

		return self::sort_and_limit_events( $items, $mode );
	}

	private static function map_football_standings_bkp( array $data ): array {
		$items = array();

		if (
			empty( $data['response'][0]['league']['standings'][0] ) ||
			! is_array( $data['response'][0]['league']['standings'][0] )
		) {
			return $items;
		}

		foreach ( $data['response'][0]['league']['standings'][0] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$items[] = array(
				'rank'      => isset( $row['rank'] ) ? (int) $row['rank'] : 0,
				'team'      => array(
					'id'   => isset( $row['team']['id'] ) ? (int) $row['team']['id'] : 0,
					'name' => isset( $row['team']['name'] ) ? (string) $row['team']['name'] : '',
					'logo' => isset( $row['team']['logo'] ) ? esc_url_raw( (string) $row['team']['logo'] ) : '',
				),
				'points'    => isset( $row['points'] ) ? (int) $row['points'] : 0,
				'goalsDiff' => isset( $row['goalsDiff'] ) ? (int) $row['goalsDiff'] : 0,
				'group'     => isset( $row['group'] ) ? (string) $row['group'] : '',
				'form'      => isset( $row['form'] ) ? (string) $row['form'] : '',
				'status'    => isset( $row['status'] ) && is_array( $row['status'] ) ? $row['status'] : array(),
				'description' => isset( $row['description'] ) ? (string) $row['description'] : '',
				'all'       => isset( $row['all'] ) && is_array( $row['all'] ) ? $row['all'] : array(),
				'home'      => isset( $row['home'] ) && is_array( $row['home'] ) ? $row['home'] : array(),
				'away'      => isset( $row['away'] ) && is_array( $row['away'] ) ? $row['away'] : array(),
				'update'    => isset( $row['update'] ) ? (string) $row['update'] : '',
				'source'    => 'API-SPORTS',
			);
		}

		return array_slice( $items, 0, 30 );
	}

	private static function map_football_standings( array $data ): array {
		$items = array();

		if (
			empty( $data['response'][0]['league']['standings'] ) ||
			! is_array( $data['response'][0]['league']['standings'] )
		) {
			return $items;
		}

		$standings_groups = $data['response'][0]['league']['standings'];

		foreach ( $standings_groups as $group_index => $group_rows ) {

			if ( empty( $group_rows ) || ! is_array( $group_rows ) ) {
				continue;
			}

			foreach ( $group_rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$items[] = array(
					'rank'      => isset( $row['rank'] ) ? (int) $row['rank'] : 0,
					'team'      => array(
						'id'   => isset( $row['team']['id'] ) ? (int) $row['team']['id'] : 0,
						'name' => isset( $row['team']['name'] ) ? (string) $row['team']['name'] : '',
						'logo' => isset( $row['team']['logo'] ) ? esc_url_raw( (string) $row['team']['logo'] ) : '',
					),
					'points'      => isset( $row['points'] ) ? (int) $row['points'] : 0,
					'goalsDiff'   => isset( $row['goalsDiff'] ) ? (int) $row['goalsDiff'] : 0,
					'group'       => isset( $row['group'] ) ? (string) $row['group'] : 'Group ' . ( $group_index + 1 ),
					'form'        => isset( $row['form'] ) ? (string) $row['form'] : '',
					'status'      => isset( $row['status'] ) && is_array( $row['status'] ) ? $row['status'] : array(),
					'description' => isset( $row['description'] ) ? (string) $row['description'] : '',
					'all'         => isset( $row['all'] ) && is_array( $row['all'] ) ? $row['all'] : array(),
					'home'        => isset( $row['home'] ) && is_array( $row['home'] ) ? $row['home'] : array(),
					'away'        => isset( $row['away'] ) && is_array( $row['away'] ) ? $row['away'] : array(),
					'update'      => isset( $row['update'] ) ? (string) $row['update'] : '',
					'source'      => 'API-SPORTS',
				);
			}
		}

		return array_slice( $items, 0, 100 );
	}

	private static function match_mode_filter( string $sport, string $mode, string $status_short ): bool {
		$status_short = strtoupper( trim( $status_short ) );

		if ( 'football' === $sport ) {
			$live_statuses     = array( '1H', '2H', 'HT', 'ET', 'BT', 'P', 'LIVE', 'INT' );
			$fixture_statuses  = array( 'TBD', 'NS', 'PST', 'SUSP', 'CANC' );
			$result_statuses   = array( 'FT', 'AET', 'PEN' );
		} else {
			$live_statuses     = array( 'Q1', 'Q2', 'Q3', 'Q4', '1Q', '2Q', '3Q', '4Q', 'HT', 'OT', 'BT', 'LIVE', 'SUSP' );
			$fixture_statuses  = array( 'NS', 'TBD' );
			$result_statuses   = array( 'FT', 'AOT' );
		}

		if ( 'any' === $mode ) {
			return true;
		}

		if ( 'live' === $mode ) {
			return in_array( $status_short, $live_statuses, true );
		}

		if ( 'fixtures' === $mode ) {
			return in_array( $status_short, $fixture_statuses, true );
		}

		return in_array( $status_short, $result_statuses, true );
	}

	protected static function sort_and_limit_events( array $items, string $mode ): array {
		if ( empty( $items ) ) {
			return $items;
		}

		usort(
			$items,
			static function ( $a, $b ) {
				$time_a = isset( $a['timestamp'] ) ? (int) $a['timestamp'] : 0;
				$time_b = isset( $b['timestamp'] ) ? (int) $b['timestamp'] : 0;

				return $time_a <=> $time_b;
			}
		);

		if ( 'results' === $mode ) {
			$items = array_reverse( $items );
		}

		return array_slice( array_values( $items ), 0, 30 );
	}

	private static function map_basketball_standings( array $data ): array {
		$items = array();

		if ( empty( $data['response'] ) || ! is_array( $data['response'] ) ) {
			return $items;
		}

		foreach ( $data['response'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$items[] = array(
				'rank'      => isset( $row['position'] ) ? (int) $row['position'] : 0,
				'team'      => array(
					'id'   => isset( $row['team']['id'] ) ? (int) $row['team']['id'] : 0,
					'name' => isset( $row['team']['name'] ) ? (string) $row['team']['name'] : '',
					'logo' => isset( $row['team']['logo'] ) ? esc_url_raw( (string) $row['team']['logo'] ) : '',
				),
				'points'    => isset( $row['points'] ) ? (int) $row['points'] : 0,
				'goalsDiff' => 0,
				'all'       => array(
					'played' => isset( $row['games'] ) ? (int) $row['games'] : 0,
					'win'    => isset( $row['wins'] ) ? (int) $row['wins'] : 0,
					'draw'   => 0,
					'lose'   => isset( $row['losses'] ) ? (int) $row['losses'] : 0,
				),
				'form'      => isset( $row['streak'] ) ? (string) $row['streak'] : '',
				'source'    => 'API-SPORTS',
			);
		}

		return array_slice( $items, 0, 30 );
	}

	private static function map_football_round_items( array $data ): array {
		$items = array();

		if ( empty( $data['response'] ) || ! is_array( $data['response'] ) ) {
			return $items;
		}

		foreach ( $data['response'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$home_name   = isset( $row['teams']['home']['name'] ) ? (string) $row['teams']['home']['name'] : '';
			$home_logo   = isset( $row['teams']['home']['logo'] ) ? esc_url_raw( (string) $row['teams']['home']['logo'] ) : '';
			$away_name   = isset( $row['teams']['away']['name'] ) ? (string) $row['teams']['away']['name'] : '';
			$away_logo   = isset( $row['teams']['away']['logo'] ) ? esc_url_raw( (string) $row['teams']['away']['logo'] ) : '';
			$status      = isset( $row['fixture']['status']['short'] ) ? (string) $row['fixture']['status']['short'] : '';
			$status_long = isset( $row['fixture']['status']['long'] ) ? (string) $row['fixture']['status']['long'] : '';
			$home_goals = isset( $row['goals']['home'] ) ? (int) $row['goals']['home'] : null;
			$away_goals = isset( $row['goals']['away'] ) ? (int) $row['goals']['away'] : null;

			$score = '';
			if ( 'FT' === $status && null !== $home_goals && null !== $away_goals ) {
				$score = $home_goals . ' - ' . $away_goals;
			}

			$date_raw    = isset( $row['fixture']['date'] ) ? (string) $row['fixture']['date'] : '';

			$date_label = '';
			if ( $date_raw ) {
				$timestamp = strtotime( $date_raw );
				if ( $timestamp ) {
					$date_label = gmdate( 'j. n.', $timestamp );
				}
			}

			$items[] = array(
				'home_team' => array(
					'name' => $home_name,
					'logo' => $home_logo,
				),
				'away_team' => array(
					'name' => $away_name,
					'logo' => $away_logo,
				),
				'status'      => $status,
				'status_long' => $status_long,
				'score' => $score ? $score : '',
				'date'        => $date_raw,
				'date_label'  => $date_label,
			);
		}

		return array_values( $items );
	}

	private static function map_basketball_round_items( array $data ): array {
		$items = array();

		if ( empty( $data['response'] ) || ! is_array( $data['response'] ) ) {
			return $items;
		}

		foreach ( $data['response'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$home_name = isset( $row['teams']['home']['name'] ) ? (string) $row['teams']['home']['name'] : '';
			$home_logo = isset( $row['teams']['home']['logo'] ) ? esc_url_raw( (string) $row['teams']['home']['logo'] ) : '';

			$away_name = isset( $row['teams']['away']['name'] ) ? (string) $row['teams']['away']['name'] : '';
			$away_logo = isset( $row['teams']['away']['logo'] ) ? esc_url_raw( (string) $row['teams']['away']['logo'] ) : '';

			$status      = isset( $row['status']['short'] ) ? (string) $row['status']['short'] : '';
			$status_long = isset( $row['status']['long'] ) ? (string) $row['status']['long'] : '';
			$home_score = isset( $row['scores']['home']['total'] ) ? (int) $row['scores']['home']['total'] : null;
			$away_score = isset( $row['scores']['away']['total'] ) ? (int) $row['scores']['away']['total'] : null;

			$score = '';
			if ( 'FT' === $status && null !== $home_score && null !== $away_score ) {
				$score = $home_score . ' - ' . $away_score;
			}

			$date_raw = isset( $row['date'] ) ? (string) $row['date'] : '';

			$date_label = '';
			if ( $date_raw ) {
				$timestamp = strtotime( $date_raw );
				if ( $timestamp ) {
					$date_label = gmdate( 'j. n.', $timestamp );
				}
			}

			$items[] = array(
				'home_team' => array(
					'name' => $home_name,
					'logo' => $home_logo,
				),
				'away_team' => array(
					'name' => $away_name,
					'logo' => $away_logo,
				),
				'status'      => $status,
				'status_long' => $status_long,
				'score' => $score ? $score : '',
				'date'        => $date_raw,
				'date_label'  => $date_label,
			);
		}

		return array_values( $items );
	}

	private static function build_summary( string $sport_label, string $league_label, string $mode, string $date, array $items, string $season = '' ): string {
		$mode_labels = array(
			'live'      => 'live events',
			'fixtures'  => 'upcoming fixtures',
			'results'   => 'latest results',
			'standings' => 'standings table',
		);

		$mode_text = $mode_labels[ $mode ] ?? 'sports data';
		$count     = count( $items );
		$season    = '' !== $season ? $season : $date;

		return sprintf(
			/* translators: 1: sport, 2: league, 3: mode text, 4: date, 5: item count, 6: season */
			__( '%1$s data for %2$s: %3$s for %4$s. Season %6$s. Total events found: %5$d.', 'wsh-ai-news-editor' ),
			$sport_label,
			$league_label,
			$mode_text,
			$date,
			$count,
			$season
		);
	}

	public static function get_standings_by_params( string $sport, int $league_id, string $season, array $settings, bool $force_refresh = false ) {
		$api_key = isset( $settings['api_sports_api_key'] ) ? trim( (string) $settings['api_sports_api_key'] ) : '';

		if ( '' === $api_key ) {
			return new WP_Error( 'missing_api_sports_key', __( 'API-SPORTS API key is not configured.', 'wsh-ai-news-editor' ) );
		}

		$base_map = array(
			'football'   => 'https://v3.football.api-sports.io',
			'basketball' => 'https://v1.basketball.api-sports.io',
		);

		if ( empty( $base_map[ $sport ] ) ) {
			return new WP_Error( 'invalid_sport', __( 'Unsupported sport for standings shortcode.', 'wsh-ai-news-editor' ) );
		}

		$cache_key = 'wsh_aine_shortcode_standings_' . md5(
			wp_json_encode(
				array(
					'sport'     => $sport,
					'league_id' => $league_id,
					'season'    => $season,
				)
			)
		);

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		} else {
			delete_transient( $cache_key );
		}

		$data = self::request_api(
			$base_map[ $sport ],
			'standings',
			array(
				'league' => $league_id,
				'season' => $season,
			),
			$api_key
		);

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$items = array();
		$league_name = '';

		if ( 'football' === $sport ) {
			$items = self::map_football_standings( $data );
			$league_name = isset( $data['response'][0]['league']['name'] ) ? (string) $data['response'][0]['league']['name'] : '';
		} elseif ( 'basketball' === $sport ) {
			$items = self::map_basketball_standings( $data );
			$league_name = isset( $data['response'][0]['league']['name'] ) ? (string) $data['response'][0]['league']['name'] : '';
		}

		$result = array(
			'sport'       => $sport,
			'league_id'   => $league_id,
			'season'      => $season,
			'league_name' => $league_name,
			'items'       => $items,
		);

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	public static function get_round_fixtures_by_params( string $sport, int $league_id, string $season, string $round, array $settings, bool $force_refresh = false ) {
		$api_key = isset( $settings['api_sports_api_key'] ) ? trim( (string) $settings['api_sports_api_key'] ) : '';

		if ( '' === $api_key ) {
			return new WP_Error( 'missing_api_sports_key', __( 'API-SPORTS API key is not configured.', 'wsh-ai-news-editor' ) );
		}

		$base_map = array(
			'football'   => 'https://v3.football.api-sports.io',
			'basketball' => 'https://v1.basketball.api-sports.io',
		);

		if ( empty( $base_map[ $sport ] ) ) {
			return new WP_Error( 'invalid_sport', __( 'Unsupported sport for round fixtures shortcode.', 'wsh-ai-news-editor' ) );
		}

		$cache_key = 'wsh_aine_shortcode_round_' . md5(
			wp_json_encode(
				array(
					'sport'     => $sport,
					'league_id' => $league_id,
					'season'    => $season,
					'round'     => $round,
				)
			)
		);

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		} else {
			delete_transient( $cache_key );
		}

		if ( 'football' === $sport ) {
			$data = self::request_api(
				$base_map[ $sport ],
				'fixtures',
				array(
					'league' => $league_id,
					'season' => $season,
					'round'  => $round,
				),
				$api_key
			);
		} else {
			$data = self::request_api(
				$base_map[ $sport ],
				'games',
				array(
					'league' => $league_id,
					'season' => $season,
					'stage'  => $round,
				),
				$api_key
			);
		}

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( 'football' === $sport ) {
			$items = self::map_football_round_items( $data );
		} else {
			$items = self::map_basketball_round_items( $data );
		}

		$league_name = '';
		if ( ! empty( $data['response'][0]['league']['name'] ) ) {
			$league_name = (string) $data['response'][0]['league']['name'];
		}

		$result = array(
			'sport'       => $sport,
			'league_id'   => $league_id,
			'season'      => $season,
			'round'       => $round,
			'league_name' => $league_name,
			'items'       => $items,
		);

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	public static function is_live_status( string $sport, string $status_short ): bool {
		$status_short = strtoupper( trim( $status_short ) );

		if ( 'football' === $sport ) {
			return in_array( $status_short, array( '1H', '2H', 'HT', 'ET', 'BT', 'P', 'LIVE', 'INT', 'SUSP' ), true );
		}

		return in_array( $status_short, array( 'Q1', 'Q2', 'Q3', 'Q4', '1Q', '2Q', '3Q', '4Q', 'HT', 'OT', 'BT', 'LIVE', 'SUSP' ), true );
	}

	public static function get_live_scores_by_params( string $sport, int $league_id, int $event_id, array $settings, bool $force_refresh = false ) {
		$api_key = isset( $settings['api_sports_api_key'] ) ? trim( (string) $settings['api_sports_api_key'] ) : '';

		if ( '' === $api_key ) {
			return new WP_Error( 'missing_api_sports_key', __( 'API-SPORTS API key is not configured.', 'wsh-ai-news-editor' ) );
		}

		$base_map = array(
			'football'   => 'https://v3.football.api-sports.io',
			'basketball' => 'https://v1.basketball.api-sports.io',
		);

		if ( empty( $base_map[ $sport ] ) ) {
			return new WP_Error( 'invalid_sport', __( 'Unsupported sport for live score shortcode.', 'wsh-ai-news-editor' ) );
		}

		if ( $event_id <= 0 && $league_id <= 0 ) {
			return new WP_Error( 'missing_live_params', __( 'Live score shortcode is missing required parameters.', 'wsh-ai-news-editor' ) );
		}

		$endpoint = ( 'football' === $sport ) ? 'fixtures' : 'games';
		$mode     = ( $event_id > 0 ) ? 'any' : 'live';
		$date     = gmdate( 'Y-m-d' );
		$league   = ( $league_id > 0 ) ? self::find_league_config( $sport, $league_id ) : array();
		$season   = self::resolve_season( $sport, $league, $date );

		$cache_key = 'wsh_aine_shortcode_live_' . md5(
			wp_json_encode(
				array(
					'sport'     => $sport,
					'league_id' => $league_id,
					'event_id'  => $event_id,
					'mode'      => $mode,
					'season'    => $season,
					'date'      => $date,
					'v'         => 4,
				)
			)
		);

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		} else {
			delete_transient( $cache_key );
		}

		if ( $event_id > 0 ) {
			$data = self::request_api(
				$base_map[ $sport ],
				$endpoint,
				array(
					'id' => $event_id,
				),
				$api_key
			);
		} elseif ( 'football' === $sport ) {
			$data = self::request_api(
				$base_map[ $sport ],
				$endpoint,
				array(
					'live'   => 'all',
					'league' => $league_id,
				),
				$api_key
			);
		} else {
			$data = self::request_basketball_live_data(
				$base_map[ $sport ],
				$league_id,
				$season,
				$date,
				$api_key
			);
		}

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( 'football' === $sport ) {
			$items = self::map_football_items( $data, $mode );
			$items = self::enrich_football_live_items( $items, $api_key );
		} else {
			$items = self::map_basketball_items( $data, $mode );
		}

		$is_live = false;
		foreach ( $items as $item ) {
			$status = isset( $item['status'] ) ? (string) $item['status'] : '';
			if ( self::is_live_status( $sport, $status ) ) {
				$is_live = true;
				break;
			}
		}

		$result = array(
			'sport'     => $sport,
			'league_id' => $league_id,
			'event_id'  => $event_id,
			'is_live'   => $is_live,
			'items'     => array_values( $items ),
		);

		set_transient( $cache_key, $result, 30 );

		return $result;
	}

	protected static function enrich_football_live_items( array $items, string $api_key ): array {
		$count = 0;

		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$fixture_id = isset( $item['fixture_id'] ) ? (int) $item['fixture_id'] : 0;
			if ( $fixture_id <= 0 || $count >= 8 ) {
				continue;
			}

			$extras = self::get_football_match_extras( $fixture_id, $item, $api_key );
			$items[ $index ]['match_events'] = $extras['events'];
			$items[ $index ]['match_stats']  = $extras['stats'];
			$count++;
		}

		return $items;
	}

	protected static function get_football_match_extras( int $fixture_id, array $item, string $api_key ): array {
		$cache_key = 'wsh_aine_fixture_extras_' . $fixture_id;
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) && isset( $cached['events'], $cached['stats'] ) ) {
			return $cached;
		}

		$extras = array(
			'events' => self::map_football_match_events(
				self::request_api_soft(
					'https://v3.football.api-sports.io',
					'fixtures/events',
					array( 'fixture' => $fixture_id ),
					$api_key
				)
			),
			'stats'  => self::map_football_match_stats(
				self::request_api_soft(
					'https://v3.football.api-sports.io',
					'fixtures/statistics',
					array( 'fixture' => $fixture_id ),
					$api_key
				),
				$item
			),
		);

		set_transient( $cache_key, $extras, 30 );

		return $extras;
	}

	protected static function request_api_soft( string $api_base, string $endpoint, array $query, string $api_key ): array {
		$data = self::request_api( $api_base, $endpoint, $query, $api_key );

		if ( is_wp_error( $data ) || ! is_array( $data ) ) {
			return array();
		}

		return $data;
	}

	protected static function map_football_match_events( array $data ): array {
		$goals = array();
		$cards = array();

		if ( empty( $data['response'] ) || ! is_array( $data['response'] ) ) {
			return array(
				'goals' => $goals,
				'cards' => $cards,
			);
		}

		foreach ( $data['response'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$type   = isset( $row['type'] ) ? (string) $row['type'] : '';
			$detail = isset( $row['detail'] ) ? (string) $row['detail'] : '';
			$player = isset( $row['player']['name'] ) ? (string) $row['player']['name'] : '';
			$team   = isset( $row['team']['name'] ) ? (string) $row['team']['name'] : '';
			$elapsed = isset( $row['time']['elapsed'] ) ? (int) $row['time']['elapsed'] : 0;
			$extra   = isset( $row['time']['extra'] ) ? (int) $row['time']['extra'] : 0;
			$minute  = $elapsed ? (string) $elapsed . "'" : '';
			if ( $extra > 0 ) {
				$minute = $elapsed . '+' . $extra . "'";
			}

			$entry = array(
				'minute' => $minute,
				'player' => $player,
				'team'   => $team,
				'detail' => $detail,
				'label'  => self::football_event_label_sr( $detail ),
			);

			if ( 'Goal' === $type ) {
				$goals[] = $entry;
			} elseif ( 'Card' === $type ) {
				$cards[] = $entry;
			}
		}

		return array(
			'goals' => $goals,
			'cards' => $cards,
		);
	}

	protected static function map_football_match_stats( array $data, array $item ): array {
		$rows = array();

		if ( empty( $data['response'] ) || ! is_array( $data['response'] ) ) {
			return $rows;
		}

		$home_id = isset( $item['home_team']['id'] ) ? (int) $item['home_team']['id'] : 0;
		$away_id = isset( $item['away_team']['id'] ) ? (int) $item['away_team']['id'] : 0;
		$by_team = array();

		foreach ( $data['response'] as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$team_id = isset( $block['team']['id'] ) ? (int) $block['team']['id'] : 0;
			$stats   = array();

			if ( ! empty( $block['statistics'] ) && is_array( $block['statistics'] ) ) {
				foreach ( $block['statistics'] as $stat_row ) {
					if ( ! is_array( $stat_row ) || empty( $stat_row['type'] ) ) {
						continue;
					}

					$stats[ (string) $stat_row['type'] ] = isset( $stat_row['value'] ) && null !== $stat_row['value']
						? (string) $stat_row['value']
						: '0';
				}
			}

			if ( $team_id > 0 ) {
				$by_team[ $team_id ] = $stats;
			}
		}

		$home_stats = ( $home_id && isset( $by_team[ $home_id ] ) ) ? $by_team[ $home_id ] : array();
		$away_stats = ( $away_id && isset( $by_team[ $away_id ] ) ) ? $by_team[ $away_id ] : array();

		if ( empty( $home_stats ) && empty( $away_stats ) ) {
			$values = array_values( $by_team );
			$home_stats = isset( $values[0] ) ? $values[0] : array();
			$away_stats = isset( $values[1] ) ? $values[1] : array();
		}

		$wanted = array(
			'Ball Possession'  => 'Posed lopte',
			'Total Shots'      => 'Šutevi',
			'Shots on Goal'    => 'Šutevi u okvir',
			'Corner Kicks'     => 'Korneri',
			'Fouls'            => 'Faulovi',
			'Offsides'         => 'Ofsajdi',
			'Yellow Cards'     => 'Žuti kartoni',
			'Red Cards'        => 'Crveni kartoni',
			'Goalkeeper Saves' => 'Odbrane',
		);

		foreach ( $wanted as $type => $label ) {
			$home_val = isset( $home_stats[ $type ] ) ? (string) $home_stats[ $type ] : '';
			$away_val = isset( $away_stats[ $type ] ) ? (string) $away_stats[ $type ] : '';

			if ( '' === $home_val && '' === $away_val ) {
				continue;
			}

			$rows[] = array(
				'label' => $label,
				'home'  => '' !== $home_val ? $home_val : '-',
				'away'  => '' !== $away_val ? $away_val : '-',
			);
		}

		return $rows;
	}

	protected static function football_event_label_sr( string $detail ): string {
		$map = array(
			'Normal Goal'        => 'gol',
			'Penalty'            => 'penal',
			'Own Goal'           => 'autogol',
			'Missed Penalty'     => 'promašen penal',
			'Yellow Card'        => 'žuti karton',
			'Red Card'           => 'crveni karton',
			'Second Yellow card' => 'drugi žuti',
		);

		return isset( $map[ $detail ] ) ? $map[ $detail ] : $detail;
	}

}