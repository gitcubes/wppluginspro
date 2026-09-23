<?php
if (! defined('ABSPATH')) {
    exit;
}

class WSH_AINE_Instagram_Fetcher
{

    const CACHE_GROUP  = 'wsh_aine_instagram';
    const CACHE_PREFIX = 'wsh_aine_instagram_';

    public static function fetch_account_posts(array $source, array $options = array()): array
    {
        $options  = ! empty($options) ? $options : get_option('wsh_aine_settings', array());
        $username = isset($source['url']) ? ltrim(trim((string) $source['url']), '@') : '';

        if ('' === $username) {
            return array();
        }

        $cache_key = self::CACHE_PREFIX . md5(wp_json_encode(array(
            'username'         => $username,
            'max_posts'        => (int) ($options['instagram_max_posts'] ?? 6),
            'include_comments' => ! empty($options['instagram_include_comments']),
            'include_reels'    => ! empty($options['instagram_include_reels']),
        )));

        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $dataset_id = isset($options['brightdata_instagram_profile_dataset_id']) ? (string) $options['brightdata_instagram_profile_dataset_id'] : '';
        if ('' === $dataset_id) {
            return array();
        }

        $profile_url = 'https://www.instagram.com/' . trim($username, '@') . '/';

        $rows = array(
            array(
                'url' => $profile_url,
            ),
        );

        $trigger = WSH_AINE_BrightData_Client::trigger_dataset($rows, $dataset_id, $options);
        if (empty($trigger['snapshot_id'])) {
            return array();
        }

        $ready = WSH_AINE_BrightData_Client::wait_until_ready((string) $trigger['snapshot_id'], $options);
        if (! $ready) {
            return array();
        }

        $items = WSH_AINE_BrightData_Client::fetch_snapshot_items((string) $trigger['snapshot_id'], $options);
        if (empty($items) || ! is_array($items)) {
            return array();
        }

        //echo "33"; print_r($items);exit();
        $records = array();

        if(isset($items['posts']) && is_array($items['posts'])){
            foreach($items['posts'] as $post){
                if (! is_array($post)) {
                    continue;
                }

                $post['_profile_account']    = $raw['account'] ?? '';
                $post['_profile_full_name']  = $raw['full_name'] ?? '';
                $post['_profile_url']        = $raw['profile_url'] ?? '';
                $post['_profile_image_link'] = $raw['profile_image_link'] ?? '';
                $post['_profile_followers']  = $raw['followers'] ?? 0;
                $post['_profile_biography']  = $raw['biography'] ?? '';

                $records[] = $post;
            }
        }


        if (empty($records)) {
            return array();
        }

        usort($records, function ($a, $b) {
            $a_pinned = ! empty($a['is_pinned']) ? 1 : 0;
            $b_pinned = ! empty($b['is_pinned']) ? 1 : 0;

            if ($a_pinned !== $b_pinned) {
                return $b_pinned <=> $a_pinned;
            }

            $a_time = ! empty($a['datetime']) ? strtotime((string) $a['datetime']) : 0;
            $b_time = ! empty($b['datetime']) ? strtotime((string) $b['datetime']) : 0;

            return $b_time <=> $a_time;
        });

        $max_posts  = max(1, min(20, (int) ($options['instagram_max_posts'] ?? 6)));
        $normalized = array();

        foreach ($records as $raw) {
            $item = self::normalize_post($raw, $source);

            // samo pravi IG post/reel URL-ovi
            if (empty($item['post_url']) || ! self::is_instagram_post_url($item['post_url'])) {
                continue;
            }

            if (empty($options['instagram_include_reels']) && 'reel' === $item['media_type']) {
                continue;
            }

            $normalized[] = $item;

            if (count($normalized) >= $max_posts) {
                break;
            }
        }

        set_transient($cache_key, $normalized, 15 * MINUTE_IN_SECONDS);

        return $normalized;
    }

    protected static function is_instagram_post_url(string $url): bool
    {
        if ('' === $url) {
            return false;
        }

        return (bool) preg_match('~instagram\.com/(p|reel)/[^/?#]+/?~i', $url);
    }

    public static function fetch_single_post(string $post_url, array $options = array()): array
    {


        $options = ! empty($options) ? $options : get_option('wsh_aine_settings', array());
        $post_url = esc_url_raw(trim($post_url));

        if ('' === $post_url) {
            return array();
        }

        $cache_key = self::CACHE_PREFIX . 'single_' . md5($post_url);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $dataset_id = isset($options['brightdata_instagram_post_dataset_id']) && '' !== $options['brightdata_instagram_post_dataset_id']
            ? (string) $options['brightdata_instagram_post_dataset_id']
            : (string) ($options['brightdata_instagram_profile_dataset_id'] ?? '');

        if ('' === $dataset_id) {
            return array();
        }

        $rows = array(
            array(
                'url' => $post_url,
            ),
        );

        $trigger = WSH_AINE_BrightData_Client::trigger_dataset($rows, $dataset_id, $options);
        if (empty($trigger['snapshot_id'])) {
            return array();
        }

        $items = WSH_AINE_BrightData_Client::fetch_snapshot_items((string) $trigger['snapshot_id'], $options);

        if (empty($items) || ! is_array($items)) {
            return array();
        }



        $raw = isset($items[0]) && is_array($items[0]) ? $items[0] : array();
        $item = self::normalize_post($raw, array('name' => 'Instagram Post', 'url' => $post_url));

        set_transient($cache_key, $item, 15 * MINUTE_IN_SECONDS);

        return $item;
    }

    public static function clear_cache_for_source(array $source, array $options = array()): void
    {
        $options  = ! empty($options) ? $options : get_option('wsh_aine_settings', array());
        $username = isset($source['url']) ? ltrim(trim((string) $source['url']), '@') : '';

        if ('' === $username) {
            return;
        }

        $cache_key = self::CACHE_PREFIX . md5(wp_json_encode(array(
            'username'         => $username,
            'max_posts'        => (int) ($options['instagram_max_posts'] ?? 6),
            'include_comments' => ! empty($options['instagram_include_comments']),
            'include_reels'    => ! empty($options['instagram_include_reels']),
        )));

        delete_transient($cache_key);
    }

    public static function normalize_post(array $raw, array $source = array()): array
    {
        $post_url = '';
        if (! empty($raw['url'])) {
            $post_url = (string) $raw['url'];
        } elseif (! empty($raw['post_url'])) {
            $post_url = (string) $raw['post_url'];
        }

        $caption = '';
        if (! empty($raw['caption'])) {
            $caption = (string) $raw['caption'];
        } elseif (! empty($raw['text'])) {
            $caption = (string) $raw['text'];
        } elseif (! empty($raw['description'])) {
            $caption = (string) $raw['description'];
        }

        $author_username = '';
        if (! empty($raw['username'])) {
            $author_username = (string) $raw['username'];
        } elseif (! empty($raw['_profile_account'])) {
            $author_username = (string) $raw['_profile_account'];
        }

        $author_name = '';
        if (! empty($raw['full_name'])) {
            $author_name = (string) $raw['full_name'];
        } elseif (! empty($raw['_profile_full_name'])) {
            $author_name = (string) $raw['_profile_full_name'];
        } else {
            $author_name = $author_username;
        }

        $thumbnail = '';
        if (! empty($raw['image_url'])) {
            $thumbnail = (string) $raw['image_url'];
        } elseif (! empty($raw['image'])) {
            $thumbnail = (string) $raw['image'];
        } elseif (! empty($raw['thumbnail_url'])) {
            $thumbnail = (string) $raw['thumbnail_url'];
        }

        $media_type = 'image';
        if (! empty($raw['content_type'])) {
            $type = strtolower((string) $raw['content_type']);
            $media_type = ('video' === $type) ? 'reel' : 'image';
        } elseif (! empty($raw['video_url'])) {
            $media_type = 'reel';
        }

        if(empty($author_name) && !empty($source['name'])) $author_name = $source['name'];
        if(empty($author_username) && !empty($source['name'])) $author_username = $source['name'];
        return array(
            'post_id'         => sanitize_text_field((string) ($raw['id'] ?? '')),
            'shortcode'       => sanitize_text_field(self::extract_shortcode_from_url($post_url)),
            'post_url'        => esc_url_raw($post_url),
            'caption'         => sanitize_textarea_field($caption),
            'author_name'     => sanitize_text_field($author_name),
            'author_username' => sanitize_text_field(ltrim($author_username, '@')),
            'media_type'      => $media_type,
            'thumbnail_old_url'   => esc_url_raw($thumbnail),
            'thumbnail_url'   => esc_url_raw( self::cache_remote_image( $thumbnail ) ),
            'media_url'       => esc_url_raw((string) ($raw['video_url'] ?? '')),
            'published_at'    => sanitize_text_field((string) ($raw['datetime'] ?? '')),
            'like_count'      => absint($raw['likes'] ?? 0),
            'comment_count'   => absint($raw['comments'] ?? 0),
            'view_count'      => absint($raw['views'] ?? 0),
            'comments'        => array(),
            'embed_url'       => esc_url_raw($post_url),
            'source_name'     => sanitize_text_field((string) ($source['name'] ?? 'Instagram')),
            'is_pinned'       => ! empty($raw['is_pinned']) ? 1 : 0,
        );
    }

    public static function build_origin_text(array $item): string
    {
        $lines = array();

        $lines[] = 'Autor: ' . trim($item['author_name'] . ' (@' . ltrim((string) $item['author_username'], '@') . ')');
        $lines[] = '';
        $lines[] = 'Caption:';
        $lines[] = (string) ($item['caption'] ?? '');
        $lines[] = '';
        $lines[] = 'Tip objave: ' . ($item['media_type'] ?? 'image');
        $lines[] = '';
        $lines[] = 'Statistika:';
        $lines[] = 'Lajkovi: ' . (int) ($item['like_count'] ?? 0);
        $lines[] = 'Komentari: ' . (int) ($item['comment_count'] ?? 0);
        $lines[] = 'Pregledi: ' . (int) ($item['view_count'] ?? 0);

        if (! empty($item['comments']) && is_array($item['comments'])) {
            $lines[] = '';
            $lines[] = 'Najvažniji komentari:';

            $counter = 0;
            foreach ($item['comments'] as $comment) {
                if (empty($comment['text'])) {
                    continue;
                }

                $author = ! empty($comment['author']) ? '@' . ltrim((string) $comment['author'], '@') : 'user';
                $lines[] = '- ' . $author . ': ' . $comment['text'];

                $counter++;
                if ($counter >= 5) {
                    break;
                }
            }
        }

        return implode("\n", $lines);
    }

    protected static function extract_shortcode_from_url( string $url ) : string {
        if ( preg_match( '~/p/([^/?#]+)/?~', $url, $matches ) ) {
            return (string) $matches[1];
        }

        if ( preg_match( '~/reel/([^/?#]+)/?~', $url, $matches ) ) {
            return (string) $matches[1];
        }

        return '';
    }

    protected static function cache_remote_image( string $image_url ) : string {
        if ( '' === $image_url ) {
            return '';
        }

        $key = 'wsh_aine_img_' . md5( $image_url );
        $cached = get_transient( $key );
        if ( is_string( $cached ) && '' !== $cached ) {
            return $cached;
        }

        $response = wp_remote_get(
            $image_url,
            array(
                'timeout' => 20,
                'redirection' => 5,
                'headers' => array(
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36',
                    'Referer'    => 'https://www.instagram.com/',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $image_url;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 || empty( $body ) ) {
            return $image_url;
        }

        $upload = wp_upload_dir();
        if ( empty( $upload['basedir'] ) || empty( $upload['baseurl'] ) ) {
            return $image_url;
        }

        $dir = trailingslashit( $upload['basedir'] ) . 'wsh-aine-cache/';
        if ( ! wp_mkdir_p( $dir ) ) {
            return $image_url;
        }

        $filename = md5( $image_url ) . '.jpg';
        $filepath = $dir . $filename;

        $result = file_put_contents( $filepath, $body );
        if ( false === $result ) {
            return $image_url;
        }

        $local_url = trailingslashit( $upload['baseurl'] ) . 'wsh-aine-cache/' . $filename;

        set_transient( $key, $local_url, DAY_IN_SECONDS );

        return $local_url;
    }
}
