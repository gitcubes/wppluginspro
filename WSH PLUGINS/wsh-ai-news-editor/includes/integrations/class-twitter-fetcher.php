<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Twitter_Fetcher {

	const API_BASE  = 'https://api.x.com/2/';
	const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	public static function fetch_account_tweets( array $account, array $settings ) : array {
		$username = isset( $account['url'] ) ? trim( (string) $account['url'] ) : '';
		$label    = isset( $account['name'] ) ? sanitize_text_field( (string) $account['name'] ) : $username;

	
		if ( '' === $username ) {
			return array();
		}

		$user = self::fetch_user_by_username( $username, $settings );
		if ( empty( $user['id'] ) ) {
			return array();
		}

		//$cache_key = 'wsh_aine_x_account_' . md5( $username . '|' . wp_json_encode( $settings ) );
		$cache_key = self::get_account_cache_key( $account, $settings );
		$cached    = get_transient( $cache_key );

		/*if ( is_array( $cached ) ) {
			return $cached;
		}*/

		$params = array(
			'max_results' => max( 5, min( 25, absint( $settings['twitter_max_results'] ?? 10 ) ) ),
			'tweet.fields' => 'created_at,public_metrics,lang,attachments,entities,context_annotations',
			'expansions'   => 'author_id,attachments.media_keys',
			'user.fields'  => 'name,username,profile_image_url',
			'media.fields' => 'preview_image_url,url,type',
			'exclude'      => self::build_exclude_param( $settings ),
		);

		$response = self::api_get(
			'users/' . rawurlencode( $user['id'] ) . '/tweets',
			$params,
			$settings
		);

		if ( empty( $response['data'] ) || ! is_array( $response['data'] ) ) {
			return array();
		}

		$items = self::normalize_tweets_response( $response, 'account', $label, $username );
		set_transient( $cache_key, $items, self::CACHE_TTL );

		return $items;
	}

	public static function fetch_topic_tweets( array $topic, array $settings ) : array {
		$query = isset( $topic['url'] ) ? trim( (string) $topic['url'] ) : '';
		$label = isset( $topic['name'] ) ? sanitize_text_field( (string) $topic['name'] ) : $query;

		if ( '' === $query ) {
			return array();
		}

		//$cache_key = 'wsh_aine_x_topic_' . md5( $query . '|' . wp_json_encode( $settings ) );
		$cache_key = self::get_topic_cache_key( $topic, $settings );
		$cached    = get_transient( $cache_key );

		/*if ( is_array( $cached ) ) {
			return $cached;
		}*/

		$final_query = self::build_search_query( $query, $settings );

		$response = self::api_get(
			'tweets/search/recent',
			array(
				'query'        => $final_query,
				'max_results'  => max( 5, min( 25, absint( $settings['twitter_max_results'] ?? 10 ) ) ),
				'tweet.fields' => 'created_at,public_metrics,lang,attachments,entities,context_annotations',
				'expansions'   => 'author_id,attachments.media_keys',
				'user.fields'  => 'name,username,profile_image_url',
				'media.fields' => 'preview_image_url,url,type',
			),
			$settings
		);

		if ( empty( $response['data'] ) || ! is_array( $response['data'] ) ) {
			return array();
		}

		$items = self::normalize_tweets_response( $response, 'topic', $label, $query );
		set_transient( $cache_key, $items, self::CACHE_TTL );

		return $items;
	}

	protected static function fetch_user_by_username( string $username, array $settings ) : array {
		$response = self::api_get(
			'users/by/username/' . rawurlencode( ltrim( $username, '@' ) ),
			array(
				'user.fields' => 'name,username,profile_image_url',
			),
			$settings
		);

		return isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
	}

	protected static function normalize_tweets_response( array $response, string $mode, string $source_name, string $source_query ) : array {
		$users = array();
		$media = array();

		if ( ! empty( $response['includes']['users'] ) && is_array( $response['includes']['users'] ) ) {
			foreach ( $response['includes']['users'] as $user ) {
				if ( ! empty( $user['id'] ) ) {
					$users[ $user['id'] ] = $user;
				}
			}
		}

		if ( ! empty( $response['includes']['media'] ) && is_array( $response['includes']['media'] ) ) {
			foreach ( $response['includes']['media'] as $item ) {
				if ( ! empty( $item['media_key'] ) ) {
					$media[ $item['media_key'] ] = $item;
				}
			}
		}

		$items = array();

		foreach ( $response['data'] as $tweet ) {
			$author = array();
			if ( ! empty( $tweet['author_id'] ) && isset( $users[ $tweet['author_id'] ] ) ) {
				$author = $users[ $tweet['author_id'] ];
			}

			$media_url = '';
			if ( ! empty( $tweet['attachments']['media_keys'] ) && is_array( $tweet['attachments']['media_keys'] ) ) {
				foreach ( $tweet['attachments']['media_keys'] as $media_key ) {
					if ( ! empty( $media[ $media_key ]['url'] ) ) {
						$media_url = esc_url_raw( (string) $media[ $media_key ]['url'] );
						break;
					}
					if ( ! empty( $media[ $media_key ]['preview_image_url'] ) ) {
						$media_url = esc_url_raw( (string) $media[ $media_key ]['preview_image_url'] );
						break;
					}
				}
			}

			$expanded_urls = array();
			if ( ! empty( $tweet['entities']['urls'] ) ) {
				foreach ( $tweet['entities']['urls'] as $url ) {
					if ( ! empty( $url['expanded_url'] ) ) {
						$expanded_urls[] = $url['expanded_url'];
					}
				}
			}
			

			$username = isset( $author['username'] ) ? sanitize_text_field( (string) $author['username'] ) : '';
			$tweet_id = isset( $tweet['id'] ) ? sanitize_text_field( (string) $tweet['id'] ) : '';

			$context_annotations = '';

			if (!empty($tweet['context_annotations'])) {
				if (is_array($tweet['context_annotations'])) {
					$parts = [];

					foreach ($tweet['context_annotations'] as $tc) {
						$domain = $tc['domain'] ?? null;

						if ($domain && isset($domain['name'], $domain['description'])) {
							$parts[] = $domain['name'] . ' ' . $domain['description'];
						}
					}

					$context_annotations = implode(' ', $parts);
				} else {
					$context_annotations = (string) $tweet['context_annotations'];
				}
			}

			$items[] = array(
				'tweet_id'         => $tweet_id,
				'text'             => isset( $tweet['text'] ) ? sanitize_textarea_field( (string) $tweet['text'] ) : '',
				'created_at'       => isset( $tweet['created_at'] ) ? sanitize_text_field( (string) $tweet['created_at'] ) : '',
				'author_name'      => isset( $author['name'] ) ? sanitize_text_field( (string) $author['name'] ) : '',
				'author_username'  => $username,
				'author_avatar'    => isset( $author['profile_image_url'] ) ? esc_url_raw( (string) $author['profile_image_url'] ) : '',
				'media_url'        => $media_url,
				'expanded_urls'    => $expanded_urls,
				'context_annotations'  => $context_annotations,
				'like_count'       => isset( $tweet['public_metrics']['like_count'] ) ? absint( $tweet['public_metrics']['like_count'] ) : 0,
				'retweet_count'    => isset( $tweet['public_metrics']['retweet_count'] ) ? absint( $tweet['public_metrics']['retweet_count'] ) : 0,
				'reply_count'      => isset( $tweet['public_metrics']['reply_count'] ) ? absint( $tweet['public_metrics']['reply_count'] ) : 0,
				'quote_count'      => isset( $tweet['public_metrics']['quote_count'] ) ? absint( $tweet['public_metrics']['quote_count'] ) : 0,
				'tweet_url'        => ( $username && $tweet_id ) ? 'https://x.com/' . rawurlencode( $username ) . '/status/' . rawurlencode( $tweet_id ) : '',
				'embed_url'        => ( $username && $tweet_id ) ? 'https://twitframe.com/show?url=' . rawurlencode( 'https://x.com/' . $username . '/status/' . $tweet_id ) : '',
				'source_mode'      => $mode,
				'source_name'      => $source_name,
				'source_query'     => $source_query,
			);
		}

		return $items;
	}

	protected static function build_search_query( string $query, array $settings ) : string {
		$final = $query;

		/*if ( ! empty( $settings['twitter_language'] ) && false === strpos( $final, 'lang:' ) ) {
			$final .= ' lang:' . sanitize_text_field( (string) $settings['twitter_language'] );
		}*/

		if ( ! empty( $settings['twitter_exclude_replies'] ) && false === strpos( $final, '-is:reply' ) ) {
			$final .= ' -is:reply';
		}

		if ( ! empty( $settings['twitter_exclude_retweets'] ) && false === strpos( $final, '-is:retweet' ) ) {
			$final .= ' -is:retweet';
		}

		return trim( $final );
	}

	protected static function build_exclude_param( array $settings ) : string {
		$exclude = array();

		if ( ! empty( $settings['twitter_exclude_replies'] ) ) {
			$exclude[] = 'replies';
		}

		if ( ! empty( $settings['twitter_exclude_retweets'] ) ) {
			$exclude[] = 'retweets';
		}

		return implode( ',', $exclude );
	}

	protected static function api_get( string $endpoint, array $params, array $settings ) : array {
		$token = isset( $settings['twitter_bearer_token'] ) ? trim( (string) $settings['twitter_bearer_token'] ) : '';
		if ( '' === $token ) {
			return array();
		}

		$url = add_query_arg( $params, self::API_BASE . ltrim( $endpoint, '/' ) );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code || ! is_array( $data ) ) {
			return array();
		}

		return $data;
	}

	public static function get_account_cache_key( array $account, array $settings ) : string {
		$username = isset( $account['url'] ) ? trim( (string) $account['url'] ) : '';
		return 'wsh_aine_x_account_' . md5( $username . '|' . wp_json_encode( $settings ) );
	}

	public static function get_topic_cache_key( array $topic, array $settings ) : string {
		$query = isset( $topic['url'] ) ? trim( (string) $topic['url'] ) : '';
		return 'wsh_aine_x_topic_' . md5( $query . '|' . wp_json_encode( $settings ) );
	}

	public static function clear_cache_for_source( string $mode, array $item, array $settings ) : void {
		if ( 'accounts' === $mode ) {
			delete_transient( self::get_account_cache_key( $item, $settings ) );
			return;
		}

		if ( 'topics' === $mode ) {
			delete_transient( self::get_topic_cache_key( $item, $settings ) );
		}
	}
}
