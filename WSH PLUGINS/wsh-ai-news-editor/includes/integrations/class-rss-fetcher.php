<?php
if (! defined('ABSPATH')) {
	exit;
}

class WSH_AINE_RSS_Fetcher
{

	const CACHE_TTL = 10 * MINUTE_IN_SECONDS;

	public static function fetch_feed_items(array $source, string $source_type = 'local_rss', int $limit = 10): array
	{
		$name = isset($source['name']) ? sanitize_text_field($source['name']) : '';
		$url  = isset($source['url']) ? esc_url_raw($source['url']) : '';

		if ('' === $url) {
			return array();
		}

		//$cache_key = 'wsh_aine_rss_' . md5($source_type . '|' . $name . '|' . $url . '|' . $limit);
		$cache_key = self::get_cache_key( $source, $source_type, $limit );
		
		$cached    = get_transient($cache_key);

		if (is_array($cached)) {
			return $cached;
		}

		include_once ABSPATH . WPINC . '/feed.php';

		$feed = fetch_feed($url);

		if (is_wp_error($feed)) {
			error_log('WSH AINE RSS ERROR for ' . $url . ': ' . $feed->get_error_message());

			$fallback_items = self::fetch_feed_items_fallback($url, $name, $source_type, $limit);
			if (! empty($fallback_items)) {
				set_transient($cache_key, $fallback_items, self::CACHE_TTL);
				return $fallback_items;
			}

			return array();
		}

		$maxitems  = $feed->get_item_quantity($limit);
		$rss_items = $feed->get_items(0, $maxitems);

		if (empty($rss_items)) {
			set_transient($cache_key, array(), self::CACHE_TTL);
			return array();
		}

		$items = array();

		foreach ($rss_items as $item) {
			$items[] = self::normalize_item($item, $name, $source_type);
		}

		set_transient($cache_key, $items, self::CACHE_TTL);

		return $items;
	}

	protected static function normalize_item($item, string $source_name, string $source_type): array
	{
		$title = trim(wp_strip_all_tags((string) $item->get_title()));
		$link  = esc_url_raw((string) $item->get_link());
		$date  = (string) $item->get_date('Y-m-d H:i:s');

		if ('google_news' === $source_type) {
			$link = WSH_AINE_Google_URL_Resolver::resolve($link);
		}

		$plain_desc   = self::extract_plain_description($item);
		$rich_content = self::extract_rich_content($item);

		$image   = self::extract_image($item, $rich_content ?: $plain_desc);
		$excerpt = self::build_excerpt($plain_desc ?: $rich_content);

		if ('' === $image && 'google_news' === $source_type && '' !== $link) {
			$image = WSH_AINE_Remote_Image_Finder::find_from_url($link);
		}

		$publisher = self::extract_source_publisher($item);

		$display_source = $source_name;
		if ('google_news' === $source_type && ! empty($publisher['name'])) {
			$display_source = $publisher['name'];
		}

		return array(
			'title'            => $title,
			'url'              => $link,
			'image'            => $image,
			'description'      => $excerpt,
			'content'          => $rich_content ?: $plain_desc,
			'date'             => $date,
			'source'           => $display_source,
			'source_type'      => $source_type,
			'display_domain'   => self::extract_domain($link),
			'publisher_name'   => $publisher['name'] ?? '',
			'publisher_url'    => $publisher['url'] ?? '',
			'feed_source_name' => $source_name,
			'hash'             => md5(strtolower($title . '|' . $link)),
		);
	}

	protected static function extract_plain_description($item): string
	{
		$desc = $item->get_description();

		if (is_string($desc) && '' !== trim($desc)) {
			return trim($desc);
		}

		return '';
	}

	protected static function extract_rich_content($item): string
	{
		$content = '';

		$content_encoded = $item->get_content();
		if (is_string($content_encoded) && '' !== trim($content_encoded)) {
			$content = $content_encoded;
		}

		if ('' === trim($content)) {
			$encoded_tags = $item->get_item_tags('http://purl.org/rss/1.0/modules/content/', 'encoded');
			if (! empty($encoded_tags[0]['data']) && is_string($encoded_tags[0]['data'])) {
				$content = $encoded_tags[0]['data'];
			}
		}

		// Fallback za feedove koji koriste obican <content> tag bez namespace-a.
		if ('' === trim($content)) {
			$content_tags = $item->get_item_tags('', 'content');
			if (! empty($content_tags[0]['data']) && is_string($content_tags[0]['data'])) {
				$content = $content_tags[0]['data'];
			}
		}

		return is_string($content) ? trim($content) : '';
	}

	protected static function build_excerpt(string $html): string
	{
		$text = wp_strip_all_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		$text = trim(preg_replace('/\s+/u', ' ', $text));

		if ('' === $text) {
			return '';
		}

		return wp_html_excerpt($text, 320, '...');
	}

	protected static function extract_image($item, string $html = ''): string
	{
		// 1. Standardni enclosure
		$enclosure = $item->get_enclosure();

		if ($enclosure) {
			if (method_exists($enclosure, 'get_link')) {
				$link = (string) $enclosure->get_link();
				if (self::looks_like_image_url($link)) {
					return esc_url_raw($link);
				}
			}

			if (method_exists($enclosure, 'get_type')) {
				$type = (string) $enclosure->get_type();
				if (0 === strpos($type, 'image/') && method_exists($enclosure, 'get_link')) {
					$link = (string) $enclosure->get_link();
					if ('' !== $link) {
						return esc_url_raw($link);
					}
				}
			}
		}

		// 2. Klasicni thumbnail tagovi
		$thumb_tags = $item->get_item_tags('', 'thumbnail');
		if (! empty($thumb_tags)) {
			foreach ($thumb_tags as $tag) {
				if (! empty($tag['attribs']['']['url'])) {
					$url = (string) $tag['attribs']['']['url'];
					if (self::looks_like_image_url($url)) {
						return esc_url_raw($url);
					}
				}
			}
		}

		// 3. Media RSS
		$media_content = $item->get_item_tags('http://search.yahoo.com/mrss/', 'content');
		if (! empty($media_content)) {
			foreach ($media_content as $tag) {
				if (! empty($tag['attribs']['']['url'])) {
					$url = (string) $tag['attribs']['']['url'];
					if (self::looks_like_image_url($url)) {
						return esc_url_raw($url);
					}
				}
			}
		}

		$media_thumbnail = $item->get_item_tags('http://search.yahoo.com/mrss/', 'thumbnail');
		if (! empty($media_thumbnail)) {
			foreach ($media_thumbnail as $tag) {
				if (! empty($tag['attribs']['']['url'])) {
					$url = (string) $tag['attribs']['']['url'];
					if (self::looks_like_image_url($url)) {
						return esc_url_raw($url);
					}
				}
			}
		}

		// 4. Custom <image> tag
		$image_tags = $item->get_item_tags('', 'image');
		if (! empty($image_tags)) {
			foreach ($image_tags as $tag) {
				if (! empty($tag['data'])) {
					$url = html_entity_decode(trim((string) $tag['data']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
					if (self::looks_like_image_url($url)) {
						return esc_url_raw($url);
					}
				}
			}
		}

		// 5. Custom <icon> tag
		$icon_tags = $item->get_item_tags('', 'icon');
		if (! empty($icon_tags)) {
			foreach ($icon_tags as $tag) {
				if (! empty($tag['data'])) {
					$url = html_entity_decode(trim((string) $tag['data']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
					if (self::looks_like_image_url($url)) {
						return esc_url_raw($url);
					}
				}
			}
		}

		// 6. HTML fallback - prvo data-src, pa data-lazy-src, pa src
		if ($html) {
			$patterns = array(
				'/<img[^>]+data-src=["\']([^"\']+)["\']/i',
				'/<img[^>]+data-lazy-src=["\']([^"\']+)["\']/i',
				'/<img[^>]+src=["\']([^"\']+)["\']/i',
			);

			foreach ($patterns as $pattern) {
				if (preg_match($pattern, $html, $matches) && ! empty($matches[1])) {
					$url = html_entity_decode(trim((string) $matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

					// Preskoci placeholder/relative src tipa /static/images/background/loader.png
					if (0 === strpos($url, '/')) {
						continue;
					}

					if (self::looks_like_image_url($url)) {
						return esc_url_raw($url);
					}
				}
			}
		}

		return '';
	}

	protected static function looks_like_image_url(string $url): bool
	{
		$url = trim($url);

		if ('' === $url) {
			return false;
		}

		if (! filter_var($url, FILTER_VALIDATE_URL)) {
			return false;
		}

		return true;
	}

	protected static function extract_domain(string $url): string
	{
		$host = wp_parse_url($url, PHP_URL_HOST);

		if (! is_string($host) || '' === $host) {
			return '';
		}

		return preg_replace('/^www\./i', '', $host);
	}

	protected static function extract_source_publisher($item): array
	{
		$tags = $item->get_item_tags('', 'source');

		if (empty($tags) || ! is_array($tags)) {
			return array(
				'name' => '',
				'url'  => '',
			);
		}

		$tag = $tags[0];

		$name = '';
		$url  = '';

		if (! empty($tag['data']) && is_string($tag['data'])) {
			$name = sanitize_text_field(html_entity_decode($tag['data'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		}

		if (! empty($tag['attribs']['']['url']) && is_string($tag['attribs']['']['url'])) {
			$url = esc_url_raw($tag['attribs']['']['url']);
		}

		return array(
			'name' => $name,
			'url'  => $url,
		);
	}

	protected static function fetch_feed_items_fallback(string $url, string $source_name, string $source_type, int $limit): array
	{
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 20,
				'user-agent' => 'Mozilla/5.0 (compatible; WSH-AI-News-Editor/1.0; +WordPress)',
			)
		);

		if (is_wp_error($response)) {
			return array();
		}

		$body = wp_remote_retrieve_body($response);

		if (! is_string($body) || '' === trim($body)) {
			return array();
		}

		$body = self::repair_missing_media_namespace($body);

		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

		if (false === $xml || empty($xml->channel->item)) {
			return array();
		}

		$items = array();
		$count = 0;

		foreach ($xml->channel->item as $item) {
			if ($count >= $limit) {
				break;
			}

			$items[] = self::normalize_fallback_xml_item($item, $source_name, $source_type);
			$count++;
		}

		return $items;
	}

	protected static function repair_missing_media_namespace(string $xml): string
	{
		if (strpos($xml, 'media:') !== false && strpos($xml, 'xmlns:media=') === false) {
			$xml = preg_replace(
				'/<rss\b([^>]*)>/i',
				'<rss$1 xmlns:media="http://search.yahoo.com/mrss/">',
				$xml,
				1
			);
		}

		return $xml;
	}

	protected static function normalize_fallback_xml_item(\SimpleXMLElement $item, string $source_name, string $source_type): array
	{
		$title = isset($item->title) ? trim(wp_strip_all_tags((string) $item->title)) : '';
		$link  = isset($item->link) ? esc_url_raw((string) $item->link) : '';
		$date  = isset($item->pubDate) ? sanitize_text_field((string) $item->pubDate) : '';

		$description = isset($item->description) ? (string) $item->description : '';
		$excerpt     = self::build_excerpt($description);
		$image       = '';

		$namespaces = $item->getNamespaces(true);

		if (isset($namespaces['media'])) {
			$media = $item->children($namespaces['media']);

			if (isset($media->content)) {
				$attrs = $media->content->attributes();
				if (! empty($attrs['url'])) {
					$image = esc_url_raw((string) $attrs['url']);
				}
			}

			if ('' === $image && isset($media->thumbnail)) {
				$attrs = $media->thumbnail->attributes();
				if (! empty($attrs['url'])) {
					$image = esc_url_raw((string) $attrs['url']);
				}
			}
		}

		if ('' === $image && isset($item->enclosure)) {
			$attrs = $item->enclosure->attributes();
			if (! empty($attrs['url'])) {
				$image = esc_url_raw((string) $attrs['url']);
			}
		}

		return array(
			'title'            => $title,
			'url'              => $link,
			'image'            => $image,
			'description'      => $excerpt,
			'content'          => $description,
			'date'             => $date,
			'source'           => $source_name,
			'source_type'      => $source_type,
			'display_domain'   => self::extract_domain($link),
			'publisher_name'   => '',
			'publisher_url'    => '',
			'feed_source_name' => $source_name,
			'hash'             => md5(strtolower($title . '|' . $link)),
		);
	}

	public static function get_cache_key( array $source, string $source_type = 'local_rss', int $limit = 10 ) : string {
		$name = isset( $source['name'] ) ? sanitize_text_field( $source['name'] ) : '';
		$url  = isset( $source['url'] ) ? esc_url_raw( $source['url'] ) : '';

		return 'wsh_aine_rss_' . md5( $source_type . '|' . $name . '|' . $url . '|' . $limit );
	}

	public static function clear_feed_cache( array $source, string $source_type = 'local_rss', int $limit = 10 ) : void {
		delete_transient( self::get_cache_key( $source, $source_type, $limit ) );
	}

}
