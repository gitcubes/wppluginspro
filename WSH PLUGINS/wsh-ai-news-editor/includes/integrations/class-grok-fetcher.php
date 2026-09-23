<?php
if (! defined('ABSPATH')) {
	exit;
}

class WSH_AINE_Grok_Fetcher
{

	const API_BASE  = 'https://api.x.ai/v1/responses';
	const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	public static function simle_search(string $query, array $settings)
	{
		return self::legacy_search($query, $settings);
	}

	/**
	 * Legacy Grok search kept only for backward compatibility.
	 * Prefer search() which is X-only.
	 */
	public static function legacy_search(string $query, array $settings)
	{
		$query = trim($query);
		if ('' === $query) {
			return new WP_Error('empty_query', __('Search query is empty.', 'wsh-ai-news-editor'));
		}

		$api_key = isset($settings['grok_api_key']) ? trim((string) $settings['grok_api_key']) : '';
		$model   = isset($settings['grok_model']) ? trim((string) $settings['grok_model']) : 'grok-4.20-beta-latest-non-reasoning';

		if ('' === $api_key) {
			return new WP_Error('missing_grok_api_key', __('Grok API key is not configured.', 'wsh-ai-news-editor'));
		}

		$cache_key = 'wsh_aine_grok_' . md5($query . '|' . wp_json_encode($settings));
		$cached    = get_transient($cache_key);

		if (is_array($cached)) {
			return $cached;
		}

		$tools = array();

		if (! empty($settings['grok_use_x_search'])) {
			$tools[] = array(
				'type' => 'x_search',
			);
		}

		if (! empty($settings['grok_use_web_search'])) {
			$tools[] = array(
				'type' => 'web_search',
			);
		}

		$max_items = isset($settings['grok_max_items']) ? max(1, min(20, absint($settings['grok_max_items']))) : 10;

		$input = array(
			array(
				'role'    => 'system',
				'content' => 'You are a newsroom research assistant. Search X and/or the web if tools are enabled, then return a concise Serbian summary with the most relevant items. Return valid JSON only.',
			),
			array(
				'role'    => 'user',
				'content' => "Tema pretrage: {$query}\n\nVrati JSON u formatu:\n{\n  \"summary\": \"\",\n  \"items\": [\n    {\n      \"title\": \"\",\n      \"url\": \"\",\n      \"source\": \"\",\n      \"snippet\": \"\"\n    }\n  ]\n}\n\nOgraniči items na {$max_items}.",
			),
		);

		$body = array(
			'model' => $model,
			'input' => $input,
		);

		if (! empty($tools)) {
			$body['tools'] = $tools;
		}

		$response = wp_remote_post(
			self::API_BASE,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode($body),
			)
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code($response);
		$raw  = wp_remote_retrieve_body($response);
		$data = json_decode($raw, true);

		if (200 !== $code || ! is_array($data)) {
			$message = __('Unexpected response from Grok API.', 'wsh-ai-news-editor');
			if (is_array($data) && ! empty($data['error']['message'])) {
				$message = (string) $data['error']['message'];
			}
			return new WP_Error('grok_http_error', $message);
		}

		$text = self::extract_output_text($data);

		if ('' === trim($text)) {
			return new WP_Error('grok_empty_output', __('Empty Grok output.', 'wsh-ai-news-editor'));
		}

		$parsed = json_decode($text, true);
		if (! is_array($parsed)) {
			return new WP_Error('grok_invalid_json', __('Grok output is not valid JSON.', 'wsh-ai-news-editor'));
		}

		$summary = isset($parsed['summary']) ? sanitize_textarea_field((string) $parsed['summary']) : '';
		$items   = array();

		if (! empty($parsed['items']) && is_array($parsed['items'])) {
			foreach ($parsed['items'] as $item) {
				if (! is_array($item)) {
					continue;
				}

				$items[] = array(
					'title'   => isset($item['title']) ? sanitize_text_field((string) $item['title']) : '',
					'url'     => isset($item['url']) ? esc_url_raw((string) $item['url']) : '',
					'source'  => isset($item['source']) ? sanitize_text_field((string) $item['source']) : '',
					'snippet' => isset($item['snippet']) ? sanitize_textarea_field((string) $item['snippet']) : '',
				);
			}
		}

		$result = array(
			'query'   => $query,
			'summary' => $summary,
			'items'   => array_slice($items, 0, $max_items),
		);

		set_transient($cache_key, $result, self::CACHE_TTL);

		return $result;
	}

	public static function search(string $query, array $settings)
	{
		$query = trim($query);
		if ('' === $query) {
			return new WP_Error('empty_query', __('Search query is empty.', 'wsh-ai-news-editor'));
		}

		$api_key = isset($settings['grok_api_key']) ? trim((string) $settings['grok_api_key']) : '';
		$model   = isset($settings['grok_model']) ? trim((string) $settings['grok_model']) : 'grok-4.20-beta-latest-non-reasoning';

		if ('' === $api_key) {
			return new WP_Error('missing_grok_api_key', __('Grok API key is not configured.', 'wsh-ai-news-editor'));
		}

		$max_items = isset($settings['grok_max_items']) ? max(1, min(20, absint($settings['grok_max_items']))) : 10;

		$cache_key = 'wsh_aine_grok_x_only_' . md5($query . '|' . $model . '|' . $max_items);
		$cached    = get_transient($cache_key);

		if (is_array($cached)) {
			return $cached;
		}

		$body = array(
			'model' => $model,
			'input' => array(
				array(
					'role' => 'system',
					'content' => 'Ti si newsroom research AI koji analizira isključivo diskusiju na X (Twitter). Tvoj zadatak je da pronađeš najrelevantnije, najaktuelnije i najpopularnije objave o zadatoj temi i da iz njih izvučeš glavne angle-ove priče. Ne koristi web izvore. Koristi samo X Search. Vrati isključivo validan JSON.'
				),
				array(
					'role' => 'user',
					'content' => "Pretraži X (Twitter) za temu ili upit: {$query}

					PRAVILA:
					- koristi samo X objave
					- fokus na najpopularnijim i najrelevantnijim aktuelnim objavama
					- ako je upit nejasan, protumači ga kao temu za praćenje diskusije na X
					- ako postoji više različitih angle-ova, navedi ih
					- ne izmišljaj činjenice
					- ne koristi web izvore
					- ne vraćaj objašnjenja van JSON-a

					Vrati JSON tačno u ovom formatu:
					{
					\"summary\": \"Kratak pregled šta se trenutno priča na X o ovoj temi\",
					\"angles\": [
						\"angle 1\",
						\"angle 2\",
						\"angle 3\"
					],
					\"items\": [
						{
						\"title\": \"kratak naslov objave ili angle-a\",
						\"url\": \"https://x.com/... ili prazan string\",
						\"source\": \"X / Twitter\",
						\"snippet\": \"kratak sažetak relevantne objave ili diskusije\"
						}
					]
					}

					VAŽNO:
					- vrati samo validan JSON
					- bez markdown code blockova
					- bez dodatnog teksta pre ili posle JSON-a
					- svi navodnici unutar JSON stringova moraju biti pravilno escape-ovani

					Ograniči items na {$max_items}."
				),
			),
			'tools' => array(
				array(
					'type' => 'x_search',
				),
			),
		);

		$response = wp_remote_post(
			self::API_BASE,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode($body),
			)
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code($response);
		$raw  = wp_remote_retrieve_body($response);
		$data = json_decode($raw, true);

		if (200 !== $code || ! is_array($data)) {
			$message = __('Unexpected response from Grok API.', 'wsh-ai-news-editor');
			if (is_array($data) && ! empty($data['error']['message'])) {
				$message = (string) $data['error']['message'];
			}
			return new WP_Error('grok_http_error', $message);
		}

		$text = self::extract_output_text($data);

		if ('' === trim($text)) {
			return new WP_Error('grok_empty_output', __('Empty Grok output.', 'wsh-ai-news-editor'));
		}

		$parsed = self::parse_json_response($text);

		if (! is_array($parsed) || empty($parsed)) {
			error_log('GROK RAW OUTPUT: ' . $text);

			return array(
				'query'   => $query,
				'summary' => sanitize_textarea_field(wp_strip_all_tags($text)),
				'angles'  => array(),
				'items'   => array(),
			);
		}

		$summary = isset($parsed['summary']) ? sanitize_textarea_field((string) $parsed['summary']) : '';

		$angles = array();
		if (! empty($parsed['angles']) && is_array($parsed['angles'])) {
			foreach ($parsed['angles'] as $angle) {
				if (is_scalar($angle)) {
					$angle = sanitize_text_field((string) $angle);
					if ('' !== trim($angle)) {
						$angles[] = $angle;
					}
				}
			}
		}

		$items = array();
		if (! empty($parsed['items']) && is_array($parsed['items'])) {
			foreach ($parsed['items'] as $item) {
				if (! is_array($item)) {
					continue;
				}

				$items[] = array(
					'title'   => isset($item['title']) ? sanitize_text_field((string) $item['title']) : '',
					'url'     => isset($item['url']) ? esc_url_raw((string) $item['url']) : '',
					'source'  => isset($item['source']) ? sanitize_text_field((string) $item['source']) : 'X / Twitter',
					'snippet' => isset($item['snippet']) ? sanitize_textarea_field((string) $item['snippet']) : '',
				);
			}
		}

		$result = array(
			'query'   => $query,
			'summary' => $summary,
			'angles'  => array_values(array_slice($angles, 0, 5)),
			'items'   => array_values(array_slice($items, 0, $max_items)),
		);

		set_transient($cache_key, $result, self::CACHE_TTL);

		return $result;
	}

	public static function trending(array $settings, string $filter = 'all', bool $force_refresh = false)
	{
		$api_key = isset($settings['grok_api_key']) ? trim((string) $settings['grok_api_key']) : '';
		$model   = isset($settings['grok_model']) ? trim((string) $settings['grok_model']) : 'grok-4.20-beta-latest-non-reasoning';

		if ('' === $api_key) {
			return new WP_Error('missing_grok_api_key', __('Grok API key is not configured.', 'wsh-ai-news-editor'));
		}

		$max_items = isset($settings['grok_max_items']) ? max(1, min(20, absint($settings['grok_max_items']))) : 10;

		$allowed_filters = array(
			'all'       => 'all trending discussions on X',
			'serbia'    => 'trending discussions on X related to Serbia',
			'region'    => 'trending discussions on X related to Balkans and ex-Yu region',
			'world'     => 'global trending discussions on X',
			'sport'     => 'trending sports discussions on X',
			'politics'  => 'trending political discussions on X',
			'tech'      => 'trending technology and AI discussions on X',
		);

		if (! isset($allowed_filters[$filter])) {
			$filter = 'all';
		}

		$filter_prompt = $allowed_filters[$filter];

		$cache_key = 'wsh_aine_grok_trending_' . md5($model . '|' . $max_items . '|' . $filter);

		if ( ! $force_refresh ) {
			$cached = get_transient($cache_key);

			if ( is_array($cached) ) {
				return $cached;
			}
		} else {
			delete_transient($cache_key);
		}

		$body = array(
			'model' => $model,
			'input' => array(
				array(
					'role'    => 'system',
					'content' => 'Ti si newsroom research AI koji analizira isključivo diskusiju na X (Twitter). Tvoj zadatak je da pronađeš trenutno najpopularnije i najaktuelnije teme koje dominiraju na X-u, da ih grupišeš po angle-ovima i vratiš samo validan JSON. Ne koristi web izvore. Koristi samo X Search.',
				),
				array(
					'role'    => 'user',
					'content' => "Pronađi {$filter_prompt}.

				PRAVILA:
				- koristi samo X objave
				- fokus na aktuelnim i popularnim temama
				- grupiši teme po angle-ovima
				- ne koristi web izvore
				- ne izmišljaj činjenice
				- ne vraćaj objašnjenja van JSON-a

				Vrati JSON tačno u ovom formatu:
				{
				\"summary\": \"Kratak pregled šta je trenutno trending na X\",
				\"angles\": [
					\"angle 1\",
					\"angle 2\",
					\"angle 3\"
				],
				\"items\": [
					{
					\"title\": \"kratak naslov teme ili angle-a\",
					\"url\": \"https://x.com/... ili prazan string\",
					\"source\": \"X / Twitter\",
					\"snippet\": \"kratak sažetak relevantne diskusije\"
					}
				]
				}

				VAŽNO:
				- vrati samo validan JSON
				- bez markdown code blockova
				- bez dodatnog teksta pre ili posle JSON-a
				- svi navodnici unutar JSON stringova moraju biti pravilno escape-ovani

				Ograniči items na {$max_items}.",
				),
			),
			'tools' => array(
				array(
					'type' => 'x_search',
				),
			),
		);

		$response = wp_remote_post(
			self::API_BASE,
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode($body),
			)
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code($response);
		$raw  = wp_remote_retrieve_body($response);
		$data = json_decode($raw, true);

		if (200 !== $code || ! is_array($data)) {
			$message = __('Unexpected response from Grok API.', 'wsh-ai-news-editor');
			if (is_array($data) && ! empty($data['error']['message'])) {
				$message = (string) $data['error']['message'];
			}
			return new WP_Error('grok_http_error', $message);
		}

		$text = self::extract_output_text($data);

		if ('' === trim($text)) {
			return new WP_Error('grok_empty_output', __('Empty Grok output.', 'wsh-ai-news-editor'));
		}

		$parsed = self::parse_json_response($text);

		if (! is_array($parsed) || empty($parsed)) {
			error_log('GROK TRENDING RAW OUTPUT: ' . $text);

			return array(
				'query'   => 'Trending',
				'filter'  => $filter,
				'summary' => sanitize_textarea_field(wp_strip_all_tags($text)),
				'angles'  => array(),
				'items'   => array(),
			);
		}

		$summary = isset($parsed['summary']) ? sanitize_textarea_field((string) $parsed['summary']) : '';

		$angles = array();
		if (! empty($parsed['angles']) && is_array($parsed['angles'])) {
			foreach ($parsed['angles'] as $angle) {
				if (is_scalar($angle)) {
					$angle = sanitize_text_field((string) $angle);
					if ('' !== trim($angle)) {
						$angles[] = $angle;
					}
				}
			}
		}

		$items = array();
		if (! empty($parsed['items']) && is_array($parsed['items'])) {
			foreach ($parsed['items'] as $item) {
				if (! is_array($item)) {
					continue;
				}

				$items[] = array(
					'title'   => isset($item['title']) ? sanitize_text_field((string) $item['title']) : '',
					'url'     => isset($item['url']) ? esc_url_raw((string) $item['url']) : '',
					'source'  => isset($item['source']) ? sanitize_text_field((string) $item['source']) : 'X / Twitter',
					'snippet' => isset($item['snippet']) ? sanitize_textarea_field((string) $item['snippet']) : '',
				);
			}
		}

		$result = array(
			'query'   => 'Trending',
			'filter'  => $filter,
			'summary' => $summary,
			'angles'  => array_values(array_slice($angles, 0, 5)),
			'items'   => array_values(array_slice($items, 0, $max_items)),
		);

		set_transient($cache_key, $result, self::CACHE_TTL);

		return $result;
	}

	protected static function extract_output_text(array $data): string
	{
		if (! empty($data['output']) && is_array($data['output'])) {
			foreach ($data['output'] as $output_item) {
				if (empty($output_item['content']) || ! is_array($output_item['content'])) {
					continue;
				}

				foreach ($output_item['content'] as $content_item) {
					if (isset($content_item['type']) && 'output_text' === $content_item['type'] && isset($content_item['text'])) {
						return (string) $content_item['text'];
					}
				}
			}
		}

		return '';
	}

	protected static function parse_json_response(string $text): array
	{
		$text = trim($text);

		if ('' === $text) {
			return array();
		}

		$decoded = json_decode($text, true);
		if (is_array($decoded)) {
			return $decoded;
		}

		// Skini markdown code fences ako postoje.
		$text = preg_replace('/^```(?:json)?\s*/i', '', $text);
		$text = preg_replace('/\s*```$/', '', $text);
		$text = trim($text);

		$decoded = json_decode($text, true);
		if (is_array($decoded)) {
			return $decoded;
		}

		// Probaj da izvučeš samo JSON deo od prvog { do poslednjeg }.
		$start = strpos($text, '{');
		$end   = strrpos($text, '}');

		if (false !== $start && false !== $end && $end > $start) {
			$json_only = substr($text, $start, $end - $start + 1);
			$decoded   = json_decode($json_only, true);

			if (is_array($decoded)) {
				return $decoded;
			}
		}

		return array();
	}
}
