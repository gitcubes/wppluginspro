<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pro AI quiz generator. Uses only the OpenAI key saved in this plugin.
 */
class WSH_Quiz_AI {

	const DIFFICULTIES = array( 'easy', 'medium', 'hard' );

	public static function site_locale() : string {
		$locale = (string) get_option( 'WPLANG' );
		if ( '' === $locale ) {
			$locale = get_locale();
		}

		if ( 'sr' === $locale ) {
			$locale = 'sr_RS';
		}

		return '' !== $locale ? $locale : 'en_US';
	}

	/**
	 * @return array<string, string>
	 */
	public static function languages() : array {
		$list = array(
			'sr_RS'   => 'Serbian',
			'sr_Latn' => 'Serbian (Latin)',
			'hr'      => 'Croatian',
			'bs_BA'   => 'Bosnian',
			'sl_SI'   => 'Slovenian',
			'mk_MK'   => 'Macedonian',
			'en_US'   => 'English',
			'de_DE'   => 'German',
			'fr_FR'   => 'French',
			'es_ES'   => 'Spanish',
			'it_IT'   => 'Italian',
			'hu_HU'   => 'Hungarian',
			'ro_RO'   => 'Romanian',
			'bg_BG'   => 'Bulgarian',
			'pl_PL'   => 'Polish',
			'ru_RU'   => 'Russian',
			'uk'      => 'Ukrainian',
			'tr_TR'   => 'Turkish',
			'sq'      => 'Albanian',
			'el'      => 'Greek',
			'nl_NL'   => 'Dutch',
			'pt_PT'   => 'Portuguese',
		);

		$site = self::site_locale();
		if ( ! isset( $list[ $site ] ) ) {
			$name = $site;
			if ( function_exists( 'locale_get_display_name' ) ) {
				$display = locale_get_display_name( $site, 'en' );
				if ( is_string( $display ) && '' !== $display ) {
					$name = $display;
				}
			}
			$list = array( $site => $name ) + $list;
		}

		return $list;
	}

	public static function language_name( string $locale ) : string {
		$all = self::languages();
		return $all[ $locale ] ?? ( $all[ self::site_locale() ] ?? 'English' );
	}

	public static function sanitize_language( string $locale ) : string {
		$all = self::languages();
		if ( isset( $all[ $locale ] ) ) {
			return $locale;
		}

		$site = self::site_locale();
		return isset( $all[ $site ] ) ? $site : 'en_US';
	}

	public static function prompt_template( string $locale ) : string {
		if ( 0 === strpos( $locale, 'sr' ) ) {
			return "Napravi kviz na srpskom jeziku.\nNaslov kviza: %title%\nOpis kviza: %description%\n\nPiši sva pitanja, odgovore, hintove i objašnjenja na srpskom.";
		}

		$name = self::language_name( $locale );
		return 'Create a quiz in ' . $name . ".\nQuiz title: %title%\nQuiz description: %description%\n\nWrite all questions, answers, hints and explanations in " . $name . '.';
	}

	public static function default_prompt( string $locale, string $title, string $description ) : string {
		$title       = trim( $title );
		$description = trim( wp_strip_all_tags( $description ) );

		if ( in_array( $title, array( '', 'Auto Draft' ), true ) ) {
			$title = '';
		}

		$text = self::prompt_template( $locale );
		$text = str_replace(
			array( '%title%', '%description%' ),
			array( $title, $description ),
			$text
		);

		$text = preg_replace( '/^Naslov kviza:\s*$/m', '', $text );
		$text = preg_replace( '/^Opis kviza:\s*$/m', '', $text );
		$text = preg_replace( '/^Quiz title:\s*$/m', '', $text );
		$text = preg_replace( '/^Quiz description:\s*$/m', '', $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", (string) $text );

		return trim( (string) $text );
	}

	public static function api_key() : string {
		$settings = wsh_quiz_get_global_settings();
		return isset( $settings['openai_api_key'] ) ? trim( (string) $settings['openai_api_key'] ) : '';
	}

	public static function model() : string {
		$settings = wsh_quiz_get_global_settings();
		$model    = isset( $settings['openai_model'] ) ? trim( (string) $settings['openai_model'] ) : '';

		if ( '' === $model ) {
			$model = 'gpt-4.1-mini';
		}

		return $model;
	}

	public static function suggest_timer( int $count, string $difficulty ) : int {
		$per = array(
			'easy'   => 15,
			'medium' => 25,
			'hard'   => 40,
		);

		return max( 0, $count ) * ( $per[ $difficulty ] ?? 25 );
	}

	/**
	 * @return array{title:string,description:string,timer:int,questions:array}|WP_Error
	 */
	public static function generate( array $args ) {
		if ( ! WSH_Quiz_Access::can( 'ai_generate' ) ) {
			return new WP_Error( 'pro_required', __( 'AI generation requires an active Pro license.', 'wsh-quiz' ) );
		}

		$key = self::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'missing_api_key', __( 'Add an OpenAI API key in WSH Quiz → Settings.', 'wsh-quiz' ) );
		}

		$topic      = isset( $args['topic'] ) ? trim( (string) $args['topic'] ) : '';
		$url        = isset( $args['url'] ) ? esc_url_raw( (string) $args['url'] ) : '';
		$count      = min( 20, max( 1, isset( $args['count'] ) ? (int) $args['count'] : 5 ) );
		$difficulty = isset( $args['difficulty'] ) ? sanitize_key( (string) $args['difficulty'] ) : 'medium';
		$photo      = max( 0, isset( $args['photo'] ) ? (int) $args['photo'] : 0 );
		$video      = max( 0, isset( $args['video'] ) ? (int) $args['video'] : 0 );
		$timer      = max( 0, isset( $args['timer'] ) ? (int) $args['timer'] : 0 );
		$locale     = self::sanitize_language( isset( $args['language'] ) ? (string) $args['language'] : self::site_locale() );
		$language   = self::language_name( $locale );

		if ( ! in_array( $difficulty, self::DIFFICULTIES, true ) ) {
			$difficulty = 'medium';
		}

		if ( $photo > $count ) {
			$photo = $count;
		}
		if ( $photo + $video > $count ) {
			$video = $count - $photo;
		}

		$choice = $count - $photo - $video;
		if ( $timer < 1 ) {
			$timer = self::suggest_timer( $count, $difficulty );
		}

		$source = $topic;
		if ( '' !== $url ) {
			$fetched = self::fetch_url_text( $url );
			if ( is_wp_error( $fetched ) ) {
				return $fetched;
			}
			$source = trim( $topic . "\n\n" . $fetched );
		}

		if ( '' === $source ) {
			return new WP_Error( 'empty_topic', __( 'Enter a quiz description or an article URL.', 'wsh-quiz' ) );
		}

		$request_body = array(
			'model'           => self::model(),
			'temperature'     => 'hard' === $difficulty ? 0.7 : 0.55,
			'response_format' => array( 'type' => 'json_object' ),
			'messages'        => array(
				array(
					'role'    => 'system',
					'content' => 'You generate factual newsroom quizzes. Return JSON only. Never invent sources, image URLs, or YouTube URLs. Questions must be answerable from the provided topic or article. Output language is ' . $language . '. Write title, description, questions, answers, hints, explanations and media_source only in ' . $language . '. Do not use any other language.',
				),
				array(
					'role'    => 'user',
					'content' => self::prompt( $source, $count, $choice, $photo, $video, $difficulty, $language ),
				),
			),
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 90,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $request_body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$message = __( 'Unexpected response from OpenAI.', 'wsh-quiz' );
			if ( is_array( $data ) && ! empty( $data['error']['message'] ) ) {
				$message = (string) $data['error']['message'];
			}
			return new WP_Error( 'openai_http_error', $message );
		}

		$content = $data['choices'][0]['message']['content'] ?? '';
		$parsed  = is_string( $content ) ? json_decode( $content, true ) : null;

		if ( ! is_array( $parsed ) ) {
			return new WP_Error( 'invalid_ai_json', __( 'AI response is not valid JSON.', 'wsh-quiz' ) );
		}

		$payload               = self::normalize_payload( $parsed, $choice, $photo, $video );
		$payload['timer']      = $timer;
		$payload['difficulty'] = $difficulty;

		return $payload;
	}

	protected static function prompt( string $source, int $count, int $choice, int $photo, int $video, string $difficulty, string $language ) : string {
		$levels = array(
			'easy'   => 'Easy: well-known facts, short clear wording, no trick answers.',
			'medium' => 'Medium: a mix of obvious and slightly harder facts.',
			'hard'   => 'Hard: details, dates, nuance, and plausible wrong answers.',
		);

		return implode(
			"\n",
			array(
				'OUTPUT LANGUAGE: ' . $language . '. Every user-facing string must be in ' . $language . '.',
				'Create a quiz in ' . $language . '.',
				'Write exactly ' . $count . ' questions.',
				$levels[ $difficulty ] ?? $levels['medium'],
				'Question mix:',
				'- ' . $choice . ' type "choice" with exactly 4 short answers.',
				'- ' . $photo . ' type "image_prompt". The editor will add the photo later. Put a short photo brief in media_source. Do not invent image URLs.',
				'- ' . $video . ' type "video_prompt". The editor will add the YouTube video later. Put a short video brief in media_source. Do not invent YouTube URLs.',
				'Interleave the types so the quiz does not group all media questions at the end.',
				'JSON shape: {"title":"","description":"","questions":[{"type":"choice","question":"","hint":"","explanation":"","correct":0,"options":["A","B","C","D"],"media_source":""}]}',
				'correct is the zero-based index of the right answer.',
				'Each question needs a short hint and a one-sentence explanation.',
				'Keep answers concise. No markdown.',
				'Source:',
				$source,
			)
		);
	}

	/**
	 * @return array{title:string,description:string,questions:array<int,array>}
	 */
	protected static function normalize_payload( array $parsed, int $choice, int $photo, int $video ) : array {
		$questions = array();
		$raw       = isset( $parsed['questions'] ) && is_array( $parsed['questions'] ) ? $parsed['questions'] : array();
		$plan      = self::type_plan( $choice, $photo, $video );

		foreach ( $raw as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$type = isset( $plan[ $index ] ) ? $plan[ $index ] : self::allowed_type( $row['type'] ?? 'choice' );
			$media_type = 'none';
			if ( 'image_prompt' === $type ) {
				$media_type = 'image';
			} elseif ( 'video_prompt' === $type ) {
				$media_type = 'video';
			}

			$options = array();
			if ( isset( $row['options'] ) && is_array( $row['options'] ) ) {
				foreach ( $row['options'] as $option ) {
					$text      = is_array( $option ) ? ( $option['text'] ?? '' ) : $option;
					$options[] = array( 'text' => sanitize_text_field( (string) $text ) );
				}
			}

			$question = wsh_quiz_normalize_question(
				array(
					'type'         => $type,
					'question'     => $row['question'] ?? '',
					'hint'         => $row['hint'] ?? '',
					'explanation'  => $row['explanation'] ?? '',
					'correct'      => $row['correct'] ?? 0,
					'options'      => $options,
					'score'        => 1,
					'media_type'   => $media_type,
					'media_source' => $row['media_source'] ?? '',
				)
			);

			if ( '' !== $question['question'] ) {
				$questions[] = $question;
			}
		}

		return array(
			'title'       => isset( $parsed['title'] ) ? sanitize_text_field( (string) $parsed['title'] ) : '',
			'description' => isset( $parsed['description'] ) ? sanitize_textarea_field( (string) $parsed['description'] ) : '',
			'questions'   => $questions,
		);
	}

	/**
	 * @return string[]
	 */
	protected static function type_plan( int $choice, int $photo, int $video ) : array {
		$bags = array(
			'choice'       => $choice,
			'image_prompt' => $photo,
			'video_prompt' => $video,
		);
		$plan = array();

		while ( array_sum( $bags ) > 0 ) {
			foreach ( $bags as $type => $left ) {
				if ( $left > 0 ) {
					$plan[]         = $type;
					$bags[ $type ] -= 1;
				}
			}
		}

		return $plan;
	}

	protected static function allowed_type( string $type ) : string {
		$type = sanitize_key( $type );
		if ( in_array( $type, array( 'choice', 'yes_no', 'image_prompt', 'video_prompt' ), true ) ) {
			return $type;
		}

		return 'choice';
	}

	/**
	 * @return string|WP_Error
	 */
	protected static function fetch_url_text( string $url ) {
		if ( ! preg_match( '#^https?://#i', $url ) || ( function_exists( 'wp_http_validate_url' ) && ! wp_http_validate_url( $url ) ) ) {
			return new WP_Error( 'bad_url', __( 'The article URL is not valid.', 'wsh-quiz' ) );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'User-Agent' => 'WSH Quiz',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'url_fetch_failed', __( 'Could not read that article URL.', 'wsh-quiz' ) );
		}

		$html = (string) wp_remote_retrieve_body( $response );
		$html = wp_strip_all_tags( $html, true );
		$html = preg_replace( '/\s+/', ' ', $html );
		$html = trim( (string) $html );

		if ( '' === $html ) {
			return new WP_Error( 'empty_article', __( 'That URL had no readable text.', 'wsh-quiz' ) );
		}

		return substr( $html, 0, 8000 );
	}
}
