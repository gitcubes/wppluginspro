<?php
if (! defined('ABSPATH')) {
	exit;
}

class WSH_AINE_YouTube_Fetcher
{

	const API_BASE  = 'https://www.googleapis.com/youtube/v3/';
	const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	public static function fetch_popular_videos_bkp(array $settings, string $category_id = ''): array
	{
		$api_key    = isset($settings['youtube_api_key']) ? trim((string) $settings['youtube_api_key']) : '';
		$region     = isset($settings['youtube_region']) ? trim((string) $settings['youtube_region']) : 'RS';
		$max_videos = isset($settings['youtube_max_videos']) ? absint($settings['youtube_max_videos']) : 10;

		if ('' === $api_key) {
			return array();
		}

		$cache_key = 'wsh_aine_yt_popular_' . md5($region . '|' . $max_videos . '|' . $category_id);
		$cached    = get_transient($cache_key);

		if (is_array($cached)) {
			return $cached;
		}

		/*$url = add_query_arg(
			array(
				'part'       => 'snippet,statistics',
				'chart'      => 'mostPopular',
				'regionCode' => $region,
				'maxResults' => max( 1, min( 25, $max_videos ) ),
				'key'        => $api_key,
			),
			self::API_BASE . 'videos'
		);*/
		$args = array(
			'part'       => 'snippet,statistics',
			'chart'      => 'mostPopular',
			'regionCode' => $region,
			'maxResults' => max(1, min(25, $max_videos)),
			'key'        => $api_key,
		);

		if ('' !== $category_id) {
			$args['videoCategoryId'] = $category_id;
		}

		$url = add_query_arg(
			$args,
			self::API_BASE . 'videos'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
			)
		);

		if (is_wp_error($response)) {
			return array();
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (200 !== $code || empty($data['items']) || ! is_array($data['items'])) {
			return array();
		}

		$items = array();

		foreach ($data['items'] as $item) {
			$items[] = self::normalize_video_item($item);
		}

		set_transient($cache_key, $items, self::CACHE_TTL);

		return $items;
	}

	public static function fetch_popular_videos(array $settings, string $category_id = ''): array
	{
		$api_key    = isset($settings['youtube_api_key']) ? trim((string) $settings['youtube_api_key']) : '';
		$region     = isset($settings['youtube_region']) ? trim((string) $settings['youtube_region']) : 'RS';
		$max_videos = isset($settings['youtube_max_videos']) ? absint($settings['youtube_max_videos']) : 10;

		if ('' === $api_key) {
			return array();
		}

		$cache_key = 'wsh_aine_yt_popular_smart_' . md5($region . '|' . $max_videos . '|' . $category_id);
		$cached    = get_transient($cache_key);

		if (is_array($cached)) {
			return $cached;
		}

		$days_attempts = array(3, 7, 14, 30);

		foreach ($days_attempts as $days) {
			$items = self::fetch_recent_popular_videos_search(
				$api_key,
				$region,
				$max_videos,
				$category_id,
				$days
			);

			if (! empty($items)) {
				set_transient($cache_key, $items, self::CACHE_TTL);
				return $items;
			}
		}

		// Fallback bez kategorije, ali i dalje sveže.
		if ('' !== $category_id) {
			foreach ($days_attempts as $days) {
				$items = self::fetch_recent_popular_videos_search(
					$api_key,
					$region,
					$max_videos,
					'',
					$days
				);

				if (! empty($items)) {
					set_transient($cache_key, $items, self::CACHE_TTL);
					return $items;
				}
			}
		}

		// Poslednji fallback: originalni YouTube trending.
		$items = self::fetch_most_popular_videos_chart(
			$api_key,
			$region,
			$max_videos,
			$category_id
		);

		set_transient($cache_key, $items, self::CACHE_TTL);

		return $items;
	}

	protected static function fetch_recent_popular_videos_search(
		string $api_key,
		string $region,
		int $max_videos,
		string $category_id = '',
		int $days = 3
	): array {
		$published_after = gmdate('Y-m-d\TH:i:s\Z', time() - ($days * DAY_IN_SECONDS));

		$args = array(
			'part'           => 'snippet',
			'type'           => 'video',
			'order'          => 'viewCount',
			'regionCode'     => $region,
			'publishedAfter' => $published_after,
			'maxResults'     => max(1, min(25, $max_videos)),
			'key'            => $api_key,
		);

		if ('' !== $category_id) {
			$args['videoCategoryId'] = $category_id;
		}

		$url = add_query_arg(
			$args,
			self::API_BASE . 'search'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
			)
		);

		if (is_wp_error($response)) {
			return array();
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (200 !== $code || empty($data['items']) || ! is_array($data['items'])) {
			return array();
		}

		$video_ids = array();

		foreach ($data['items'] as $item) {
			if (! empty($item['id']['videoId'])) {
				$video_ids[] = sanitize_text_field((string) $item['id']['videoId']);
			}
		}

		if (empty($video_ids)) {
			return array();
		}

		return self::fetch_video_details_by_ids($video_ids, $api_key);
	}

	protected static function fetch_most_popular_videos_chart(
		string $api_key,
		string $region,
		int $max_videos,
		string $category_id = ''
	): array {
		$args = array(
			'part'       => 'snippet,statistics',
			'chart'      => 'mostPopular',
			'regionCode' => $region,
			'maxResults' => max(1, min(25, $max_videos)),
			'key'        => $api_key,
		);

		if ('' !== $category_id) {
			$args['videoCategoryId'] = $category_id;
		}

		$url = add_query_arg(
			$args,
			self::API_BASE . 'videos'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
			)
		);

		if (is_wp_error($response)) {
			return array();
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (200 !== $code || empty($data['items']) || ! is_array($data['items'])) {
			return array();
		}

		$items = array();

		foreach ($data['items'] as $item) {
			$items[] = self::normalize_video_item($item);
		}

		return $items;
	}

	protected static function fetch_video_details_by_ids(array $video_ids, string $api_key): array
	{
		$video_ids = array_filter(array_unique($video_ids));

		if (empty($video_ids)) {
			return array();
		}

		$url = add_query_arg(
			array(
				'part' => 'snippet,statistics',
				'id'   => implode(',', $video_ids),
				'key'  => $api_key,
			),
			self::API_BASE . 'videos'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
			)
		);

		if (is_wp_error($response)) {
			return array();
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (200 !== $code || empty($data['items']) || ! is_array($data['items'])) {
			return array();
		}

		$items = array();

		foreach ($data['items'] as $item) {
			$items[] = self::normalize_video_item($item);
		}

		usort(
			$items,
			function ($a, $b) {
				$a_score = isset($a['view_count']) ? (int) $a['view_count'] : 0;
				$b_score = isset($b['view_count']) ? (int) $b['view_count'] : 0;

				return $b_score <=> $a_score;
			}
		);

		return $items;
	}
	public static function fetch_video_comments(string $video_id, array $settings): array
	{
		$api_key      = isset($settings['youtube_api_key']) ? trim((string) $settings['youtube_api_key']) : '';
		$max_comments = isset($settings['youtube_max_comments']) ? absint($settings['youtube_max_comments']) : 5;

		if ('' === $api_key || '' === $video_id || $max_comments < 1) {
			return array();
		}

		$cache_key = 'wsh_aine_yt_comments_' . md5($video_id . '|' . $max_comments);
		$cached    = get_transient($cache_key);

		if (is_array($cached)) {
			return $cached;
		}

		$url = add_query_arg(
			array(
				'part'       => 'snippet',
				'videoId'    => $video_id,
				'maxResults' => max(1, min(20, $max_comments)),
				'order'      => 'relevance',
				'textFormat' => 'plainText',
				'key'        => $api_key,
			),
			self::API_BASE . 'commentThreads'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
			)
		);

		if (is_wp_error($response)) {
			return array();
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (200 !== $code || empty($data['items']) || ! is_array($data['items'])) {
			return array();
		}

		$comments = array();

		foreach ($data['items'] as $item) {
			$snippet = $item['snippet']['topLevelComment']['snippet'] ?? array();

			$text = isset($snippet['textDisplay']) ? sanitize_textarea_field((string) $snippet['textDisplay']) : '';
			$author = isset($snippet['authorDisplayName']) ? sanitize_text_field((string) $snippet['authorDisplayName']) : '';

			if ('' !== $text) {
				$comments[] = array(
					'author' => $author,
					'text'   => $text,
				);
			}
		}

		set_transient($cache_key, $comments, self::CACHE_TTL);

		return $comments;
	}

	protected static function normalize_video_item(array $item): array
	{
		$snippet    = $item['snippet'] ?? array();
		$statistics = $item['statistics'] ?? array();

		$video_id = isset($item['id']) ? sanitize_text_field((string) $item['id']) : '';
		$title    = isset($snippet['title']) ? sanitize_text_field((string) $snippet['title']) : '';
		$desc     = isset($snippet['description']) ? sanitize_textarea_field((string) $snippet['description']) : '';
		$channel  = isset($snippet['channelTitle']) ? sanitize_text_field((string) $snippet['channelTitle']) : '';
		$date     = isset($snippet['publishedAt']) ? sanitize_text_field((string) $snippet['publishedAt']) : '';

		$thumb = '';
		if (! empty($snippet['thumbnails']['high']['url'])) {
			$thumb = esc_url_raw((string) $snippet['thumbnails']['high']['url']);
		} elseif (! empty($snippet['thumbnails']['medium']['url'])) {
			$thumb = esc_url_raw((string) $snippet['thumbnails']['medium']['url']);
		} elseif (! empty($snippet['thumbnails']['default']['url'])) {
			$thumb = esc_url_raw((string) $snippet['thumbnails']['default']['url']);
		}

		return array(
			'video_id'      => $video_id,
			'title'         => $title,
			'description'   => $desc,
			'channel_title' => $channel,
			'published_at'  => $date,
			'thumbnail'     => $thumb,
			'video_url'     => $video_id ? 'https://www.youtube.com/watch?v=' . rawurlencode($video_id) : '',
			'embed_url'     => $video_id ? 'https://www.youtube.com/embed/' . rawurlencode($video_id) : '',
			'view_count'    => isset($statistics['viewCount']) ? absint($statistics['viewCount']) : 0,
			'like_count'    => isset($statistics['likeCount']) ? absint($statistics['likeCount']) : 0,
			'comment_count' => isset($statistics['commentCount']) ? absint($statistics['commentCount']) : 0,
			'category_id'   => isset($snippet['categoryId']) ? sanitize_text_field((string) $snippet['categoryId']) : '',
		);
	}

	public static function get_available_categories(): array
	{
		return array(
			'1'  => 'Film & Animation',
			'2'  => 'Autos & Vehicles',
			'10' => 'Music',
			'15' => 'Pets & Animals',
			'17' => 'Sports',
			'19' => 'Travel & Events',
			'20' => 'Gaming',
			'22' => 'People & Blogs',
			'23' => 'Comedy',
			'24' => 'Entertainment',
			'25' => 'News & Politics',
			'26' => 'Howto & Style',
			'27' => 'Education',
			'28' => 'Science & Technology',
		);
	}

	public static function get_category_label(string $category_id): string
	{
		$categories = self::get_available_categories();
		return isset($categories[$category_id]) ? $categories[$category_id] : $category_id;
	}
}
