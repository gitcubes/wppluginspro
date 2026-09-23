<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WSH_AINE_Social_Url_Helper' ) ) {
	require_once WSH_AINE_PATH . 'includes/helpers/class-social-url-helper.php';
}

class WSH_AINE_Social_Post_Fetcher {

	public static function fetch_post( string $url, array $options = array() ) : array {
		$options  = ! empty( $options ) ? $options : get_option( 'wsh_aine_settings', array() );
		$url      = esc_url_raw( trim( $url ) );
		$platform = WSH_AINE_Social_Url_Helper::detect_platform( $url );

		if ( '' === $url || '' === $platform ) {
			return array();
		}

		$item = self::fetch_brightdata_post( $platform, $url, $options );

		if ( empty( $item ) || ! is_array( $item ) ) {
			return array();
		}

		$comments = self::fetch_brightdata_comments($platform, $item, $options, 5 );
		if ( ! empty( $comments ) ) {
			$item['comments'] = $comments;
		}
		
		return $item;
	}

	protected static function fetch_instagram_post( string $url, array $options ) : array {
		$item = WSH_AINE_Instagram_Fetcher::fetch_single_post( $url, $options );

		if ( empty( $item ) ) {
			return array();
		}

		$item['platform'] = 'instagram';

		return $item;
	}

	protected static function fetch_brightdata_post( string $platform, string $url, array $options ) : array {
		$dataset_id = self::get_dataset_id( $platform, 'post', $options );

		if ( '' === $dataset_id ) {
			return array();
		}

		$rows = array(
			array(
				'url' => $url,
			),
		);

		$items = self::run_dataset_request( $rows, $dataset_id, $options );
		

		if ( empty( $items ) || ! is_array( $items ) ) {
			return array();
		}

		$raw = self::extract_raw_record( $items );
		
	
		if ( empty( $raw ) || ! is_array( $raw ) ) {
			return array();
		}

		return self::normalize_brightdata_post( $platform, $raw, $url );
	}

	protected static function extract_raw_record( array $items ) : array {
		// Ako je već jedan record
		if ( self::is_assoc( $items ) ) {
			return $items;
		}

		// Ako je lista recorda
		if ( isset( $items[0] ) && is_array( $items[0] ) ) {
			return $items[0];
		}

		return array();
	}

	protected static function is_assoc( array $array ) : bool {
		if ( array() === $array ) {
			return false;
		}

		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}

	protected static function normalize_brightdata_post( string $platform, array $raw, string $fallback_url = '' ) : array {
		switch ( $platform ) {
			case 'instagram':
				return self::normalize_instagram_post( $raw, $fallback_url );

			case 'linkedin':
				return self::normalize_linkedin_post( $raw, $fallback_url );

			case 'twitter':
				return self::normalize_twitter_post( $raw, $fallback_url );

			case 'youtube':
				return self::normalize_youtube_post( $raw, $fallback_url );

			case 'tiktok':
				return self::normalize_tiktok_post( $raw, $fallback_url );
		}

		return array();
	}

	protected static function normalize_youtube_post( array $raw, string $fallback_url = '' ) : array {
		return array();
	}

	protected static function normalize_tiktok_post( array $raw, string $fallback_url = '' ) : array {
		return array();
	}

	protected static function normalize_instagram_post( array $raw, string $fallback_url = '' ) : array {
		$post_url = ! empty( $raw['url'] ) ? (string) $raw['url'] : $fallback_url;

		$thumbnail = '';
		if ( ! empty( $raw['thumbnail'] ) ) {
			$thumbnail = (string) $raw['thumbnail'];
		} elseif ( ! empty( $raw['photos'][0] ) ) {
			$thumbnail = (string) $raw['photos'][0];
		} elseif ( ! empty( $raw['images'][0]['url'] ) ) {
			$thumbnail = (string) $raw['images'][0]['url'];
		}

		$text = ! empty( $raw['description'] ) ? (string) $raw['description'] : '';

		$author_username = ! empty( $raw['user_posted'] ) ? (string) $raw['user_posted'] : '';
		$author_name     = '' !== $author_username ? $author_username : 'Instagram';

		$media_type = 'image';
		if ( ! empty( $raw['content_type'] ) ) {
			$type = strtolower( (string) $raw['content_type'] );
			if ( 'video' === $type ) {
				$media_type = 'reel';
			} else {
				$media_type = sanitize_key( $type );
			}
		}

		return array(
			'platform'          => 'instagram',
			'post_id'           => sanitize_text_field( (string) ( $raw['post_id'] ?? $raw['pk'] ?? '' ) ),
			'shortcode'         => sanitize_text_field( (string) ( $raw['shortcode'] ?? $raw['content_id'] ?? '' ) ),
			'post_url'          => esc_url_raw( $post_url ),
			'author_name'       => sanitize_text_field( $author_name ),
			'author_username'   => sanitize_text_field( ltrim( $author_username, '@' ) ),
			'text'              => sanitize_textarea_field( $text ),
			'caption'           => sanitize_textarea_field( $text ),
			'media_type'        => $media_type,
			'thumbnail_old_url' => esc_url_raw( $thumbnail ),
			'thumbnail_url'     => esc_url_raw( $thumbnail ),
			'media_url'         => '',
			'published_at'      => sanitize_text_field( (string) ( $raw['date_posted'] ?? '' ) ),
			'like_count'        => absint( $raw['likes'] ?? 0 ),
			'comment_count'     => absint( $raw['num_comments'] ?? 0 ),
			'view_count'        => 0,
			'comments'          => array(),
			'embed_url'         => esc_url_raw( $post_url ),
			'source_name'       => 'Instagram Post',
			'extra'             => $raw,
		);
	}

	protected static function wsh_extract_linkedin_post_id( string $url ) : string {
		$url = trim( $url );

		if ( empty( $url ) ) {
			return '';
		}

		// Ukloni query parametre
		$url = strtok( $url, '?' );

		// 1. activity format
		if ( preg_match( '/activity-(\d+)-/i', $url, $m ) ) {
			return $m[1];
		}

		// 2. standard format (broj između dva "-")
		if ( preg_match( '/-(\d+)-[A-Za-z0-9_]+$/', $url, $m ) ) {
			return $m[1];
		}

		// 3. fallback (bilo koji veliki broj)
		if ( preg_match( '/(\d{10,})/', $url, $m ) ) {
			return $m[1];
		}

		return '';
	}

	protected static function normalize_linkedin_post( array $raw, string $fallback_url = '' ) : array {
		$post_url = ! empty( $raw['url'] ) ? (string) $raw['url'] : $fallback_url;

		$thumbnail = '';
		if ( ! empty( $raw['video_thumbnail'] ) ) {
			$thumbnail = (string) $raw['video_thumbnail'];
		} elseif ( ! empty( $raw['images'][0] ) ) {
			$thumbnail = (string) $raw['images'][0];
		}

		$text = '';
		if ( ! empty( $raw['post_text'] ) ) {
			$text = (string) $raw['post_text'];
		}  elseif ( ! empty( $raw['post_text_html'] ) ) {
			$text = (string) $raw['post_text_html'];
		} elseif ( ! empty( $raw['original_post_text'] ) ) {
			$text = (string) $raw['original_post_text'];
		}

		$caption = ! empty( $raw['title'] ) ? (string) $raw['title'] : '';
		if(empty($caption)) $caption = $raw['title'] ?? $raw['headline'] ?? '';

		$author_username = ! empty( $raw['user_id'] ) ? (string) $raw['user_id'] : '';
		$author_name     = ! empty( $raw['title'] ) ? preg_replace( '/\s*\|.*$/', '', (string) $raw['title'] ) : '';
		if ( '' === $author_name ) {
			$author_name = 'LinkedIn';
		}

		$media_type = ! empty( $raw['videos'][0] ) ? 'video' : 'text';

		$embed_url = "";
		if(isset($raw['input']['url'])){
			$uid = self::wsh_extract_linkedin_post_id($raw['input']['url']);
			if(strpos($raw['input']['url'], "-ugcPost-") !== false || strpos($raw['input']['url'], "-activity-") !== false){
				$embed_url = "https://www.linkedin.com/embed/feed/update/urn:li:ugcPost:$uid?collapsed=1";
			}else{
				$embed_url = "https://www.linkedin.com/embed/feed/update/urn:li:share:$uid?collapsed=1";
			}
		}

		return array(
			'platform'          => 'linkedin',
			'post_id'           => sanitize_text_field( (string) ( $raw['id'] ?? '' ) ),
			'shortcode'         => '',
			'post_url'          => esc_url_raw( $post_url ),
			'author_name'       => sanitize_text_field( $author_name ),
			'author_username'   => sanitize_text_field( $author_username ),
			'text'              => sanitize_textarea_field( $text ),
			'caption'           => sanitize_textarea_field( $caption ),
			'media_type'        => $media_type,
			'thumbnail_old_url' => esc_url_raw( $thumbnail ),
			'thumbnail_url'     => esc_url_raw( $thumbnail ),
			'media_url'         => ! empty( $raw['videos'][0] ) ? esc_url_raw( (string) $raw['videos'][0] ) : '',
			'published_at'      => sanitize_text_field( (string) ( $raw['date_posted'] ?? '' ) ),
			'like_count'        => absint( $raw['num_likes'] ?? 0 ),
			'comment_count'     => absint( $raw['num_comments'] ?? 0 ),
			'view_count'        => 0,
			'comments'          => array(),
			'embed_url'         => $embed_url,
			'source_name'       => 'LinkedIn Post',
			'extra'             => $raw,
		);
	}

	protected static function normalize_twitter_post( array $raw, string $fallback_url = '' ) : array {
		$post_url = ! empty( $raw['url'] ) ? (string) $raw['url'] : $fallback_url;

		$thumbnail = '';
		if ( ! empty( $raw['photos'][0] ) ) {
			$thumbnail = (string) $raw['photos'][0];
		} elseif ( ! empty( $raw['profile_image_link'] ) ) {
			$thumbnail = (string) $raw['profile_image_link'];
		}

		$text = ! empty( $raw['description'] ) ? (string) $raw['description'] : '';
		$author_username = ! empty( $raw['user_posted'] ) ? (string) $raw['user_posted'] : '';
		$author_name     = ! empty( $raw['name'] ) ? (string) $raw['name'] : $author_username;

		$media_type = 'text';
		if ( ! empty( $raw['videos'] ) ) {
			$media_type = 'video';
		} elseif ( ! empty( $raw['photos'] ) ) {
			$media_type = 'image';
		}

		return array(
			'platform'          => 'twitter',
			'post_id'           => sanitize_text_field( (string) ( $raw['id'] ?? '' ) ),
			'shortcode'         => '',
			'post_url'          => esc_url_raw( $post_url ),
			'author_name'       => sanitize_text_field( $author_name ),
			'author_username'   => sanitize_text_field( ltrim( $author_username, '@' ) ),
			'text'              => sanitize_textarea_field( $text ),
			'caption'           => sanitize_textarea_field( $text ),
			'media_type'        => $media_type,
			'thumbnail_old_url' => esc_url_raw( $thumbnail ),
			'thumbnail_url'     => esc_url_raw( $thumbnail ),
			'media_url'         => '',
			'published_at'      => sanitize_text_field( (string) ( $raw['date_posted'] ?? '' ) ),
			'like_count'        => absint( $raw['likes'] ?? 0 ),
			'comment_count'     => absint( $raw['replies'] ?? 0 ),
			'view_count'        => absint( $raw['views'] ?? 0 ),
			'comments'          => array(),
			'embed_url'         => esc_url_raw( str_replace( 'https://x.com', 'https://twitter.com', $post_url ) ),
			'source_name'       => 'Twitter Post',
			'extra'             => $raw,
		);
	}

	protected static function fetch_brightdata_comments( string $platform, array $item, array $options, int $limit = 5 ) : array {
		if ( 'instagram' === $platform && ! empty( $item['extra']['latest_comments'] ) && is_array( $item['extra']['latest_comments'] ) ) {
			return self::normalize_instagram_comments( $item['extra']['latest_comments'], $limit );
		}

		return array();
	}

	protected static function normalize_instagram_comments( array $rows, int $limit = 5 ) : array {
		$comments = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$text = ! empty( $row['comments'] ) ? (string) $row['comments'] : '';
			if ( '' === trim( $text ) ) {
				continue;
			}

			$comments[] = array(
				'author' => isset( $row['user_commenting'] ) ? sanitize_text_field( ltrim( (string) $row['user_commenting'], '@' ) ) : '',
				'text'   => sanitize_textarea_field( $text ),
				'likes'  => isset( $row['likes'] ) ? absint( $row['likes'] ) : 0,
			);
		}

		usort( $comments, function( $a, $b ) {
			return ( $b['likes'] ?? 0 ) <=> ( $a['likes'] ?? 0 );
		} );

		return array_slice( $comments, 0, $limit );
	}

	public static function build_origin_text( array $item ) : string {
		$platform = $item['platform'] ?? '';
		$text     = $item['text'] ?? $item['caption'] ?? $item['post_text'];

		$lines   = array();
		$lines[] = 'Platforma: ' . $platform;
		$lines[] = 'Autor: ' . trim( ( $item['author_name'] ?? '' ) . ' (@' . ltrim( (string) ( $item['author_username'] ?? '' ), '@' ) . ')' );
		$lines[] = '';
		$lines[] = 'Sadržaj objave:';
		$lines[] = (string) $text;
		$lines[] = '';
		$lines[] = 'Statistika:';
		$lines[] = 'Lajkovi: ' . (int) ( $item['like_count'] ?? 0 );
		$lines[] = 'Komentari: ' . (int) ( $item['comment_count'] ?? 0 );
		$lines[] = 'Pregledi: ' . (int) ( $item['view_count'] ?? 0 );

		if ( ! empty( $item['comments'] ) && is_array( $item['comments'] ) ) {
			$lines[] = '';
			$lines[] = 'Top 5 komentara:';

			foreach ( array_slice( $item['comments'], 0, 5 ) as $comment ) {
				if ( empty( $comment['text'] ) ) {
					continue;
				}

				$author = ! empty( $comment['author'] ) ? '@' . ltrim( (string) $comment['author'], '@' ) : 'user';
				$likes  = isset( $comment['likes'] ) ? absint( $comment['likes'] ) : 0;

				$lines[] = '- ' . $author . ' (' . $likes . ' likes): ' . $comment['text'];
			}
		}

		return implode( "\n", $lines );
	}

	protected static function get_dataset_id( string $platform, string $type, array $options ) : string {
		$key = 'brightdata_' . $platform . '_' . $type . '_dataset_id';
		return isset( $options[ $key ] ) ? trim( (string) $options[ $key ] ) : '';
	}

	protected static function run_dataset_request( array $rows, string $dataset_id, array $options ) : array {
		if ( '' === $dataset_id ) {
			return array();
		}

		$trigger = WSH_AINE_BrightData_Client::trigger_dataset( $rows, $dataset_id, $options );
		if ( empty( $trigger['snapshot_id'] ) ) {
			return array();
		}

		if ( method_exists( 'WSH_AINE_BrightData_Client', 'wait_until_ready' ) ) {
			$ready = WSH_AINE_BrightData_Client::wait_until_ready( (string) $trigger['snapshot_id'], $options );
			if ( ! $ready ) {
				return array();
			}
		} else {
			sleep( 5 );
		}

		$items = WSH_AINE_BrightData_Client::fetch_snapshot_items( (string) $trigger['snapshot_id'], $options );
		
		
		if ( ! is_array( $items ) || empty( $items ) ) {
			return array();
		}

		if ( isset( $items['error_code'] ) ) {
			return array();
		}

		return $items;
	}

}