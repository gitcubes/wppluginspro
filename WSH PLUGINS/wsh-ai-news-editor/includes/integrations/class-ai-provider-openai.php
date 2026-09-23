<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_AI_OpenAI {

	public static function generate_article( array $payload ) {
		$options = get_option( 'wsh_aine_settings', array() );

		$api_key     = isset( $options['openai_api_key'] ) ? trim( (string) $options['openai_api_key'] ) : '';
		$model       = isset( $options['openai_model'] ) ? trim( (string) $options['openai_model'] ) : 'gpt-4.1-mini';
		$temperature = isset( $options['openai_temperature'] ) ? (float) $options['openai_temperature'] : 0.7;
		$language    = isset( $options['language'] ) ? trim( (string) $options['language'] ) : 'sr';
		$country     = isset( $options['country'] ) ? trim( (string) $options['country'] ) : 'RS';

		if ( '' === $api_key ) {
			return new WP_Error( 'missing_api_key', __( 'OpenAI API key not set in Settings.', 'wsh-ai-news-editor' ) );
		}

		$prompt = self::build_article_prompt( $payload, $language, $country );

		$request_body = array(
			'model'       => $model,
			'temperature' => $temperature,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => self::get_system_prompt(),
				),
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'response_format' => array(
				'type' => 'json_object',
			),
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $request_body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			$message = __( 'Unexpected response from OpenAI API.', 'wsh-ai-news-editor' );

			if ( is_array( $data ) && ! empty( $data['error']['message'] ) ) {
				$message = (string) $data['error']['message'];
			}

			return new WP_Error( 'openai_http_error', $message );
		}

		$content = $data['choices'][0]['message']['content'] ?? '';

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return new WP_Error( 'empty_ai_response', __( 'Empty AI response received.', 'wsh-ai-news-editor' ) );
		}

		$parsed = json_decode( $content, true );

		if ( ! is_array( $parsed ) ) {
			return new WP_Error( 'invalid_ai_json', __( 'AI response is not valid JSON.', 'wsh-ai-news-editor' ) );
		}

		$title     = isset( $parsed['title'] ) ? sanitize_text_field( (string) $parsed['title'] ) : '';
		$excerpt   = isset( $parsed['excerpt'] ) ? sanitize_textarea_field( (string) $parsed['excerpt'] ) : '';
		$seo_title = isset( $parsed['seo_title'] ) ? sanitize_text_field( (string) $parsed['seo_title'] ) : '';
		$html      = isset( $parsed['content'] ) ? (string) $parsed['content'] : '';
		$tags      = isset( $parsed['tags'] ) && is_array( $parsed['tags'] ) ? $parsed['tags'] : array();

		$clean_tags = array();
		foreach ( $tags as $tag ) {
			if ( is_scalar( $tag ) ) {
				$tag = sanitize_text_field( (string) $tag );
				if ( '' !== $tag ) {
					$clean_tags[] = $tag;
				}
			}
		}

		if ( '' === $title ) {
			$title = isset( $payload['origin_title'] ) ? sanitize_text_field( (string) $payload['origin_title'] ) : '';
		}

		if ( '' === $excerpt ) {
			$excerpt = self::build_fallback_excerpt( $payload );
		}

		if ( '' === $seo_title ) {
			$seo_title = $title;
		}

		if ( '' === trim( $html ) ) {
			$html = self::build_fallback_content( $payload );
		}

		return array(
			'title'     => $title,
			'excerpt'   => $excerpt,
			'seo_title' => $seo_title,
			'content'   => wp_kses_post( $html ),
			'tags'      => array_values( array_unique( $clean_tags ) ),
		);
	}

	protected static function get_system_prompt() : string {
		return 'You are an AI newsroom assistant for WordPress news portals. You must NEVER invent facts. Use only information from the provided source. If information is missing, omit it. Write clear factual journalism. Return only valid JSON.';
	}

	protected static function build_article_prompt( array $payload, string $language, string $country ) : string {
		$source_type  = $payload['source_type'] ?? '';
		$source_name  = $payload['source_name'] ?? '';
		$origin_title = $payload['origin_title'] ?? '';
		$origin_url   = $payload['origin_url'] ?? '';
		$origin_text  = wp_strip_all_tags( $payload['origin_text'] ?? '' );
		$origin_html  = wp_strip_all_tags( $payload['origin_html'] ?? '' );
		$published_at = $payload['published_at'] ?? '';

		$text_basis = $origin_text;
		if ( '' === trim( $text_basis ) ) {
			$text_basis = $origin_html;
		}

		return "Write a professional news article in Serbian language for a news portal.

Country context: {$country}
Language code: {$language}
Source type: {$source_type}
Source name: {$source_name}
Original title: {$origin_title}
Original URL: {$origin_url}
Published at: {$published_at}

SOURCE CONTENT:
{$text_basis}

STRICT RULES:
- Use ONLY information present in the source content.
- Do NOT invent facts, quotes, names, statements, numbers or events.
- Do NOT add information from outside knowledge.
- If some detail is missing, do not fabricate it.
- If the source text is short, expand only by organizing and explaining the same source information in a clearer journalistic form.
- Do NOT speculate.

ARTICLE RULES:
- Write in Serbian.
- Write a professional news article suitable for a news portal.
- Article should be between 400 and 700 words.
- Start with a strong lead paragraph.
- Continue with 4 to 6 informative paragraphs.
- Use neutral journalistic tone.
- Use HTML formatting with <p> paragraphs.
- At the end add: <p><em>Izvor: {$source_name}</em></p>

EXCERPT RULES:
- Write a short excerpt in Serbian.
- Excerpt should be 140 to 200 characters.
- Excerpt must summarize the main news without clickbait.

SEO TITLE RULES:
- Write an SEO-friendly title in Serbian.
- Keep it clear and natural.
- Maximum 60 characters if possible.

OUTPUT FORMAT:
Return ONLY JSON.

JSON structure:
{
  \"title\": \"\",
  \"excerpt\": \"\",
  \"seo_title\": \"\",
  \"content\": \"<p>...</p>\",
  \"tags\": [\"tag1\", \"tag2\", \"tag3\", \"tag4\"]
}";
	}

	protected static function build_fallback_excerpt( array $payload ) : string {
		$text = '';

		if ( ! empty( $payload['origin_text'] ) ) {
			$text = wp_strip_all_tags( (string) $payload['origin_text'] );
		} elseif ( ! empty( $payload['origin_html'] ) ) {
			$text = wp_strip_all_tags( (string) $payload['origin_html'] );
		}

		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );

		if ( '' === $text ) {
			return '';
		}

		return wp_html_excerpt( $text, 180, '...' );
	}

	protected static function build_fallback_content( array $payload ) : string {
		$parts = array();

		if ( ! empty( $payload['origin_text'] ) ) {
			$parts[] = '<p>' . esc_html( wp_strip_all_tags( (string) $payload['origin_text'] ) ) . '</p>';
		}

		if ( ! empty( $payload['source_name'] ) ) {
			$parts[] = '<p><em>' . esc_html__( 'Izvor:', 'wsh-ai-news-editor' ) . ' ' . esc_html( (string) $payload['source_name'] ) . '</em></p>';
		}

		return implode( "\n\n", $parts );
	}

	public static function rewrite_article( string $title, string $content, string $instruction, array $payload = array() ) {
		$options = get_option( 'wsh_aine_settings', array() );

		$api_key     = isset( $options['openai_api_key'] ) ? trim( (string) $options['openai_api_key'] ) : '';
		$model       = isset( $options['openai_model'] ) ? trim( (string) $options['openai_model'] ) : 'gpt-4.1-mini';
		$temperature = isset( $options['openai_temperature'] ) ? (float) $options['openai_temperature'] : 0.7;
		$language    = isset( $options['language'] ) ? trim( (string) $options['language'] ) : 'sr';
		$country     = isset( $options['country'] ) ? trim( (string) $options['country'] ) : 'RS';

		if ( '' === $api_key ) {
			return new WP_Error( 'missing_api_key', __( 'OpenAI API key not set in Settings.', 'wsh-ai-news-editor' ) );
		}

		$source_name = isset( $payload['source_name'] ) ? sanitize_text_field( (string) $payload['source_name'] ) : '';
		$source_url  = isset( $payload['origin_url'] ) ? esc_url_raw( (string) $payload['origin_url'] ) : '';

		$prompt = "You are editing an existing Serbian news article draft.

		Country context: {$country}
		Language: {$language}
		Source name: {$source_name}
		Source URL: {$source_url}

		USER INSTRUCTION:
		{$instruction}

		CURRENT TITLE:
		{$title}

		CURRENT ARTICLE HTML:
		{$content}

		IMPORTANT RULES:
		- Keep the article in Serbian.
		- Keep journalistic tone.
		- Do not invent facts.
		- Do not add facts that are not supported by the existing article or source context.
		- Preserve valid HTML paragraphs.
		- If the user asks to change only one part, keep the rest coherent.
		- Return only valid JSON.

		Return JSON in this exact structure:
		{
		\"title\": \"\",
		\"content\": \"<p>...</p>\"
		}";

		$request_body = array(
			'model'       => $model,
			'temperature' => $temperature,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => 'You are an AI newsroom editor. Rewrite and improve article drafts without inventing facts. Return only valid JSON.',
				),
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'response_format' => array(
				'type' => 'json_object',
			),
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $request_body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			$message = __( 'Unexpected response from OpenAI API.', 'wsh-ai-news-editor' );

			if ( is_array( $data ) && ! empty( $data['error']['message'] ) ) {
				$message = (string) $data['error']['message'];
			}

			return new WP_Error( 'openai_http_error', $message );
		}

		$result_content = $data['choices'][0]['message']['content'] ?? '';

		if ( ! is_string( $result_content ) || '' === trim( $result_content ) ) {
			return new WP_Error( 'empty_ai_response', __( 'Empty AI response received.', 'wsh-ai-news-editor' ) );
		}

		$parsed = json_decode( $result_content, true );

		if ( ! is_array( $parsed ) ) {
			return new WP_Error( 'invalid_ai_json', __( 'AI response is not valid JSON.', 'wsh-ai-news-editor' ) );
		}

		$new_title   = isset( $parsed['title'] ) ? sanitize_text_field( (string) $parsed['title'] ) : $title;
		$new_content = isset( $parsed['content'] ) ? (string) $parsed['content'] : $content;

		if ( '' === trim( $new_title ) ) {
			$new_title = $title;
		}

		if ( '' === trim( $new_content ) ) {
			$new_content = $content;
		}

		return array(
			'title'   => $new_title,
			'content' => wsh_aine_sanitize_editor_content( $new_content ),
		);
	}

	public static function generate_comments( string $title, string $content, array $payload = array() ) {
		$options = get_option( 'wsh_aine_settings', array() );

		$api_key     = isset( $options['openai_api_key'] ) ? trim( (string) $options['openai_api_key'] ) : '';
		$model       = isset( $options['openai_model'] ) ? trim( (string) $options['openai_model'] ) : 'gpt-4.1-mini';
		$temperature = isset( $options['openai_temperature'] ) ? (float) $options['openai_temperature'] : 0.7;

		if ( '' === $api_key ) {
			return new WP_Error( 'missing_api_key', __( 'OpenAI API key not set in Settings.', 'wsh-ai-news-editor' ) );
		}

		$source_name = isset( $payload['source_name'] ) ? sanitize_text_field( (string) $payload['source_name'] ) : '';
		$source_url  = isset( $payload['origin_url'] ) ? esc_url_raw( (string) $payload['origin_url'] ) : '';

		$prompt = "Generate 5 short realistic Serbian reader comments for a news article.

		ARTICLE TITLE:
		{$title}

		ARTICLE HTML:
		{$content}

		SOURCE NAME:
		{$source_name}

		SOURCE URL:
		{$source_url}

		RULES:
		- Write in Serbian.
		- Generate 5 realistic reader comments.
		- Each comment must start with a generated nickname in this format: @Name | Comment text
		- Examples:
		@Milica | Baš jaka vest.
		@Dragan | Sve pohvale.
		@Nikolica | Ne znam šta reći na ovaj tekst.
		@Noa | Ovo je potpuno očekivano.
		@Dezurni_krivac | Svaki dan neka nova drama.
		- Keep comments short to medium length.
		- Mix tones naturally: surprise, support, skepticism, opinion.
		- Do NOT include hate speech, threats, slurs, or illegal content.
		- Do NOT mention AI.
		- Return only valid JSON.

		Return JSON in this exact format:
		{
		\"comments\": [
			\"...\",
			\"...\",
			\"...\",
			\"...\",
			\"...\"
		]
		}";

		$request_body = array(
			'model'       => $model,
			'temperature' => $temperature,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => 'You generate realistic reader comments for online news portals. Return only valid JSON.',
				),
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'response_format' => array(
				'type' => 'json_object',
			),
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $request_body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			$message = __( 'Unexpected response from OpenAI API.', 'wsh-ai-news-editor' );

			if ( is_array( $data ) && ! empty( $data['error']['message'] ) ) {
				$message = (string) $data['error']['message'];
			}

			return new WP_Error( 'openai_http_error', $message );
		}

		$result_content = $data['choices'][0]['message']['content'] ?? '';

		if ( ! is_string( $result_content ) || '' === trim( $result_content ) ) {
			return new WP_Error( 'empty_ai_response', __( 'Empty AI response received.', 'wsh-ai-news-editor' ) );
		}

		$parsed = json_decode( $result_content, true );

		if ( ! is_array( $parsed ) || empty( $parsed['comments'] ) || ! is_array( $parsed['comments'] ) ) {
			return new WP_Error( 'invalid_ai_json', __( 'AI comments response is not valid JSON.', 'wsh-ai-news-editor' ) );
		}

		$comments = array();

		foreach ( $parsed['comments'] as $comment ) {
			if ( is_scalar( $comment ) ) {
				$comment = sanitize_textarea_field( (string) $comment );
				if ( '' !== trim( $comment ) ) {
					$comments[] = $comment;
				}
			}
		}

		return array(
			'comments' => array_values( array_slice( $comments, 0, 5 ) ),
		);
	}


}