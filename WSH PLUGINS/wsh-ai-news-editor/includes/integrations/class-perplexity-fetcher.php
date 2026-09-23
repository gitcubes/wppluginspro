<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Perplexity_Fetcher {

	const API_BASE  = 'https://api.perplexity.ai/chat/completions';
	const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	public static function search( string $query, array $settings ) {
		$query = trim( $query );

		if ( '' === $query ) {
			return new WP_Error( 'empty_query', __( 'Search query is empty.', 'wsh-ai-news-editor' ) );
		}

		$api_key = isset( $settings['perplexity_api_key'] ) ? trim( (string) $settings['perplexity_api_key'] ) : '';
		$model   = isset( $settings['perplexity_model'] ) ? trim( (string) $settings['perplexity_model'] ) : 'sonar';
		$max_items = isset( $settings['perplexity_max_items'] ) ? max( 1, min( 20, absint( $settings['perplexity_max_items'] ) ) ) : 10;
		$context_size = isset( $settings['perplexity_search_context_size'] ) ? trim( (string) $settings['perplexity_search_context_size'] ) : 'medium';

		if ( '' === $api_key ) {
			return new WP_Error( 'missing_api_key', __( 'Perplexity API key is not configured.', 'wsh-ai-news-editor' ) );
		}

		$cache_key = 'wsh_aine_perplexity_' . md5( $query . '|' . $model . '|' . $max_items . '|' . $context_size );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$prompt = "Pretraži web za temu: {$query}

        Vrati isključivo validan JSON u ovom formatu:
        {
        \"summary\": \"kratak pregled najvažnijeg\",
        \"items\": [
            {
            \"title\": \"\",
            \"url\": \"\",
            \"source\": \"\",
            \"snippet\": \"\"
            }
        ]
        }

        Pravila:
        - fokus na aktuelnim i relevantnim web izvorima
        - bez dodatnog teksta van JSON-a
        - najviše {$max_items} items";

		$body = array(
			'model' => $model,
			'messages' => array(
				array(
					'role'    => 'system',
					'content' => 'Ti si newsroom web research AI. Koristi web-grounded odgovore i vrati samo validan JSON.',
				),
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'temperature' => 0.2,
			'response_format' => array(
                'type' => 'json_schema',
                'json_schema' => array(
                    'name' => 'perplexity_news_result',
                    'schema' => array(
                        'type' => 'object',
                        'properties' => array(
                            'summary' => array(
                                'type' => 'string',
                            ),
                            'items' => array(
                                'type' => 'array',
                                'items' => array(
                                    'type' => 'object',
                                    'properties' => array(
                                        'title' => array( 'type' => 'string' ),
                                        'url' => array( 'type' => 'string' ),
                                        'source' => array( 'type' => 'string' ),
                                        'snippet' => array( 'type' => 'string' ),
                                    ),
                                    'required' => array( 'title', 'url', 'source', 'snippet' ),
                                    'additionalProperties' => false,
                                ),
                            ),
                        ),
                        'required' => array( 'summary', 'items' ),
                        'additionalProperties' => false,
                    ),
                ),
            ),
			'search_context_size' => $context_size,
		);

		$response = wp_remote_post(
			self::API_BASE,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( 200 !== $code || ! is_array( $data ) ) {
			$message = __( 'Unexpected response from Perplexity API.', 'wsh-ai-news-editor' );
			if ( is_array( $data ) && ! empty( $data['error']['message'] ) ) {
				$message = (string) $data['error']['message'];
			}
			return new WP_Error( 'perplexity_http_error', $message );
		}

		$content = $data['choices'][0]['message']['content'] ?? '';

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return new WP_Error( 'perplexity_empty', __( 'Empty Perplexity response.', 'wsh-ai-news-editor' ) );
		}

		$parsed = self::parse_json_response( $content );

		if ( empty( $parsed ) || ! is_array( $parsed ) ) {
			error_log( 'PERPLEXITY RAW OUTPUT: ' . $content );

			$parsed = array(
				'summary' => sanitize_textarea_field( wp_strip_all_tags( $content ) ),
				'items'   => array(),
			);
		}

		$summary = isset( $parsed['summary'] ) ? sanitize_textarea_field( (string) $parsed['summary'] ) : '';

		$items = array();
		if ( ! empty( $parsed['items'] ) && is_array( $parsed['items'] ) ) {
			foreach ( $parsed['items'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$items[] = array(
					'title'   => isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : '',
					'url'     => isset( $item['url'] ) ? esc_url_raw( (string) $item['url'] ) : '',
					'source'  => isset( $item['source'] ) ? sanitize_text_field( (string) $item['source'] ) : '',
					'snippet' => isset( $item['snippet'] ) ? sanitize_textarea_field( (string) $item['snippet'] ) : '',
				);
			}
		}

		// Bonus: ako Perplexity vrati citations, iskoristi ih kao fallback izvore.
		if ( empty( $items ) && ! empty( $data['citations'] ) && is_array( $data['citations'] ) ) {
			foreach ( array_slice( $data['citations'], 0, $max_items ) as $citation_url ) {
				$items[] = array(
					'title'   => '',
					'url'     => esc_url_raw( (string) $citation_url ),
					'source'  => 'Perplexity Citation',
					'snippet' => '',
				);
			}
		}

		$result = array(
			'query'      => $query,
			'summary'    => $summary,
			'items'      => array_values( array_slice( $items, 0, $max_items ) ),
			'citations'  => ! empty( $data['citations'] ) && is_array( $data['citations'] ) ? array_values( $data['citations'] ) : array(),
		);

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	protected static function parse_json_response( string $text ) : array {
		$text = trim( $text );

		$decoded = json_decode( $text, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		$text = preg_replace( '/^```(?:json)?\s*/i', '', $text );
		$text = preg_replace( '/\s*```$/', '', $text );
		$text = trim( $text );

		$decoded = json_decode( $text, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );

		if ( false !== $start && false !== $end && $end > $start ) {
			$json_only = substr( $text, $start, $end - $start + 1 );
			$decoded   = json_decode( $json_only, true );

			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return array();
	}
}