<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_AI_Payload {

	public static function normalize( array $payload ) : array {
		return array(
			'source_type'  => isset( $payload['source_type'] ) ? sanitize_key( $payload['source_type'] ) : '',
			'source_name'  => isset( $payload['source_name'] ) ? sanitize_text_field( $payload['source_name'] ) : '',
			'origin_title' => isset( $payload['origin_title'] ) ? sanitize_text_field( $payload['origin_title'] ) : '',
			'origin_url'   => isset( $payload['origin_url'] ) ? esc_url_raw( $payload['origin_url'] ) : '',
			'origin_text'  => isset( $payload['origin_text'] ) ? wp_kses_post( $payload['origin_text'] ) : '',
			'origin_html'  => isset( $payload['origin_html'] ) ? wp_kses_post( $payload['origin_html'] ) : '',
			'image'        => isset( $payload['image'] ) ? esc_url_raw( $payload['image'] ) : '',
			'author_name'  => isset( $payload['author_name'] ) ? sanitize_text_field( $payload['author_name'] ) : '',
			'published_at' => isset( $payload['published_at'] ) ? sanitize_text_field( $payload['published_at'] ) : '',
			'embed_type'   => isset( $payload['embed_type'] ) ? sanitize_key( $payload['embed_type'] ) : '',
			'embed_url'    => isset( $payload['embed_url'] ) ? esc_url_raw( $payload['embed_url'] ) : '',
			'extra'        => isset( $payload['extra'] ) && is_array( $payload['extra'] ) ? self::sanitize_extra( $payload['extra'] ) : array(),
		);
	}

	protected static function sanitize_extra( array $extra ) : array {
		$clean = array();

		foreach ( $extra as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( is_array( $value ) ) {
				$clean[ $key ] = self::sanitize_array_deep( $value );
			} elseif ( is_scalar( $value ) || null === $value ) {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $clean;
	}

	protected static function sanitize_array_deep( array $data ) : array {
		$clean = array();

		foreach ( $data as $key => $value ) {
			$clean_key = is_string( $key ) ? sanitize_key( $key ) : $key;

			if ( is_array( $value ) ) {
				$clean[ $clean_key ] = self::sanitize_array_deep( $value );
			} elseif ( is_scalar( $value ) || null === $value ) {
				$clean[ $clean_key ] = sanitize_text_field( (string) $value );
			}
		}

		return $clean;
	}

	public static function build_from_local_rss( array $item ) : array {
		return self::normalize( array(
			'source_type'  => 'local_rss',
			'source_name'  => $item['source'] ?? '',
			'origin_title' => $item['title'] ?? '',
			'origin_url'   => $item['url'] ?? '',
			'origin_text'  => $item['description'] ?? '',
			'origin_html'  => $item['content'] ?? '',
			'image'        => $item['image'] ?? '',
			'author_username' => isset( $item['author_username'] ) ? sanitize_text_field( $item['author_username'] ) : '',
			'published_at' => $item['date'] ?? '',
			'embed_type'   => 'article',
			'embed_url'    => $item['url'] ?? '',
			'extra'        => array(),
		) );
	}
}