<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return all question types with labels and plan requirements.
 *
 * @return array<string, array{label:string, plan:string, description:string}>
 */
function wsh_quiz_question_types() : array {
	return array(
		'yes_no'       => array(
			'label'       => __( 'Yes / No', 'wsh-quiz' ),
			'plan'        => 'free',
			'icon'        => 'dashicons-yes-alt',
			'description' => __( 'Two fixed answers: Yes and No.', 'wsh-quiz' ),
		),
		'choice'       => array(
			'label'       => __( 'Multiple choice', 'wsh-quiz' ),
			'plan'        => 'free',
			'icon'        => 'dashicons-editor-ul',
			'description' => __( 'Text question with two to five answers.', 'wsh-quiz' ),
		),
		'image_prompt' => array(
			'label'       => __( 'Question with image', 'wsh-quiz' ),
			'plan'        => 'pro',
			'icon'        => 'dashicons-format-image',
			'description' => __( 'An image is part of the question. Answers stay text.', 'wsh-quiz' ),
		),
		'video_prompt' => array(
			'label'       => __( 'Question with video', 'wsh-quiz' ),
			'plan'        => 'pro',
			'icon'        => 'dashicons-format-video',
			'description' => __( 'A YouTube video is part of the question.', 'wsh-quiz' ),
		),
		'image_choice' => array(
			'label'       => __( 'Image answers', 'wsh-quiz' ),
			'plan'        => 'pro',
			'icon'        => 'dashicons-images-alt2',
			'description' => __( 'Answers are images (A / B / C).', 'wsh-quiz' ),
		),
	);
}

function wsh_quiz_question_type_icon( string $type ) : string {
	$types = wsh_quiz_question_types();
	return isset( $types[ $type ]['icon'] ) ? (string) $types[ $type ]['icon'] : 'dashicons-editor-ul';
}

/**
 * Features that require an active Pro license.
 *
 * @return string[]
 */
function wsh_quiz_pro_features() : array {
	return array(
		'image_prompt',
		'video_prompt',
		'image_choice',
		'ai_generate',
		'multi_round',
		'analytics',
		'no_branding',
	);
}

/**
 * How long a quiz stays open.
 *
 * @return array<string, string>
 */
function wsh_quiz_schedule_types() : array {
	return array(
		'open'     => __( 'Always open', 'wsh-quiz' ),
		'daily'    => __( 'Daily', 'wsh-quiz' ),
		'weekly'   => __( 'Weekly', 'wsh-quiz' ),
		'periodic' => __( 'Periodic', 'wsh-quiz' ),
	);
}

/**
 * Who may start a quiz.
 *
 * @return array<string, string>
 */
function wsh_quiz_difficulties() : array {
	return array(
		'easy'   => __( 'Easy', 'wsh-quiz' ),
		'medium' => __( 'Medium', 'wsh-quiz' ),
		'hard'   => __( 'Hard', 'wsh-quiz' ),
	);
}

function wsh_quiz_sanitize_difficulty( string $value ) : string {
	$value = sanitize_key( $value );
	return isset( wsh_quiz_difficulties()[ $value ] ) ? $value : 'medium';
}

function wsh_quiz_difficulty_label( string $value ) : string {
	$all = wsh_quiz_difficulties();
	$key = wsh_quiz_sanitize_difficulty( $value );
	return $all[ $key ];
}

function wsh_quiz_entry_modes() : array {
	return array(
		'open'    => __( 'Anyone, no details', 'wsh-quiz' ),
		'guest'   => __( 'First name, last name and email', 'wsh-quiz' ),
		'account' => __( 'Registered account required', 'wsh-quiz' ),
	);
}

/**
 * Whether a question type or named feature is Pro-only.
 */
function wsh_quiz_is_pro_feature( string $feature ) : bool {
	return in_array( $feature, wsh_quiz_pro_features(), true );
}

/**
 * Default empty quiz payload.
 */
function wsh_quiz_default_data() : array {
	$global = function_exists( 'wsh_quiz_get_global_settings' ) ? wsh_quiz_get_global_settings() : array();

	return array(
		'description'      => '',
		'schedule'         => isset( $global['default_schedule'] ) ? (string) $global['default_schedule'] : 'open',
		'start_date'       => '',
		'end_date'         => '',
		'timer'            => isset( $global['default_timer'] ) ? (int) $global['default_timer'] : 60,
		'entry_mode'       => isset( $global['default_entry_mode'] ) ? (string) $global['default_entry_mode'] : 'guest',
		'leaderboard'      => array_key_exists( 'default_leaderboard', $global ) ? (bool) $global['default_leaderboard'] : true,
		'allow_replay'     => array_key_exists( 'default_allow_replay', $global ) ? (bool) $global['default_allow_replay'] : true,
		'difficulty'       => 'medium',
		'branding'         => true,
		'sponsor_name'     => '',
		'sponsor_url'      => '',
		'sponsor_logo_id'  => 0,
		'questions'        => array(),
	);
}

/**
 * Normalize stored quiz meta into a consistent array.
 *
 * @param mixed $raw Raw meta value.
 */
function wsh_quiz_normalize_data( $raw ) : array {
	$data = wsh_quiz_default_data();

	if ( is_string( $raw ) && '' !== $raw ) {
		$decoded = json_decode( $raw, true );
		$raw     = is_array( $decoded ) ? $decoded : array();
	}

	if ( ! is_array( $raw ) ) {
		return $data;
	}

	if ( isset( $raw['description'] ) ) {
		$data['description'] = wp_kses_post( (string) $raw['description'] );
	}

	$schedules = array_keys( wsh_quiz_schedule_types() );
	if ( isset( $raw['schedule'] ) && in_array( (string) $raw['schedule'], $schedules, true ) ) {
		$data['schedule'] = (string) $raw['schedule'];
	}

	$data['start_date'] = wsh_quiz_sanitize_date( $raw['start_date'] ?? '' );
	$data['end_date']   = wsh_quiz_sanitize_date( $raw['end_date'] ?? '' );

	if ( isset( $raw['timer'] ) ) {
		$data['timer'] = max( 0, (int) $raw['timer'] );
	}

	$modes = array_keys( wsh_quiz_entry_modes() );
	if ( isset( $raw['entry_mode'] ) && in_array( (string) $raw['entry_mode'], $modes, true ) ) {
		$data['entry_mode'] = (string) $raw['entry_mode'];
	}

	if ( array_key_exists( 'leaderboard', $raw ) ) {
		$data['leaderboard'] = ! empty( $raw['leaderboard'] );
	}

	if ( array_key_exists( 'allow_replay', $raw ) ) {
		$data['allow_replay'] = ! empty( $raw['allow_replay'] );
	}

	$data['difficulty'] = wsh_quiz_sanitize_difficulty( (string) ( $raw['difficulty'] ?? $data['difficulty'] ) );

	if ( array_key_exists( 'branding', $raw ) ) {
		$data['branding'] = (bool) $raw['branding'];
	}

	$data['sponsor_name']    = isset( $raw['sponsor_name'] ) ? sanitize_text_field( (string) $raw['sponsor_name'] ) : '';
	$data['sponsor_url']     = isset( $raw['sponsor_url'] ) ? esc_url_raw( (string) $raw['sponsor_url'] ) : '';
	$data['sponsor_logo_id'] = isset( $raw['sponsor_logo_id'] ) ? (int) $raw['sponsor_logo_id'] : 0;

	if ( isset( $raw['questions'] ) && is_array( $raw['questions'] ) ) {
		foreach ( $raw['questions'] as $question ) {
			$normalized = wsh_quiz_normalize_question( $question );
			if ( ! empty( $normalized['id'] ) && ! wsh_quiz_question_is_blank( $normalized ) ) {
				$data['questions'][] = $normalized;
			}
		}
	}

	return $data;
}

/**
 * Normalize a single question.
 *
 * @param mixed $question Raw question.
 */
function wsh_quiz_normalize_question( $question ) : array {
	$types = array_keys( wsh_quiz_question_types() );
	$empty = array(
		'id'          => '',
		'type'        => 'choice',
		'question'    => '',
		'hint'        => '',
		'explanation' => '',
		'score'       => 1,
		'media_type'  => 'none',
		'media_url'   => '',
		'media_id'    => 0,
		'media_source'=> '',
		'options'     => array(),
		'correct'     => 0,
	);

	if ( ! is_array( $question ) ) {
		return $empty;
	}

	$type = isset( $question['type'] ) ? sanitize_key( (string) $question['type'] ) : 'choice';
	if ( ! in_array( $type, $types, true ) ) {
		$type = 'choice';
	}

	$media_type = isset( $question['media_type'] ) ? sanitize_key( (string) $question['media_type'] ) : 'none';
	if ( ! in_array( $media_type, array( 'none', 'image', 'video' ), true ) ) {
		$media_type = 'none';
	}

	$options = array();
	$raw_options = isset( $question['options'] ) && is_array( $question['options'] ) ? $question['options'] : array();

	foreach ( $raw_options as $option ) {
		if ( ! is_array( $option ) ) {
			continue;
		}

		$text = isset( $option['text'] ) ? sanitize_text_field( (string) $option['text'] ) : '';
		$url  = isset( $option['media_url'] ) ? esc_url_raw( (string) $option['media_url'] ) : '';
		$id   = isset( $option['media_id'] ) ? (int) $option['media_id'] : 0;
		$src  = isset( $option['source'] ) ? sanitize_text_field( (string) $option['source'] ) : '';

		if ( '' === $text && '' === $url && $id < 1 ) {
			continue;
		}

		$options[] = array(
			'text'      => $text,
			'media_url' => $url,
			'media_id'  => $id,
			'source'    => $src,
		);
	}

	if ( 'yes_no' === $type ) {
		$options = array(
			array(
				'text'      => 'yes',
				'media_url' => '',
				'media_id'  => 0,
				'source'    => '',
			),
			array(
				'text'      => 'no',
				'media_url' => '',
				'media_id'  => 0,
				'source'    => '',
			),
		);
	}

	$id = isset( $question['id'] ) ? sanitize_text_field( (string) $question['id'] ) : '';
	if ( '' === $id ) {
		$id = wp_generate_uuid4();
	}

	$correct = isset( $question['correct'] ) ? (int) $question['correct'] : 0;
	if ( $correct < 0 || $correct >= count( $options ) ) {
		$correct = 0;
	}

	return array(
		'id'           => $id,
		'type'         => $type,
		'question'     => isset( $question['question'] ) ? sanitize_textarea_field( (string) $question['question'] ) : '',
		'hint'         => isset( $question['hint'] ) ? sanitize_textarea_field( (string) $question['hint'] ) : '',
		'explanation'  => isset( $question['explanation'] ) ? sanitize_textarea_field( (string) $question['explanation'] ) : '',
		'score'        => max( 1, isset( $question['score'] ) ? (int) $question['score'] : 1 ),
		'media_type'   => $media_type,
		'media_url'    => isset( $question['media_url'] ) ? esc_url_raw( (string) $question['media_url'] ) : '',
		'media_id'     => isset( $question['media_id'] ) ? (int) $question['media_id'] : 0,
		'media_source' => isset( $question['media_source'] ) ? sanitize_text_field( (string) $question['media_source'] ) : '',
		'options'      => $options,
		'correct'      => $correct,
	);
}

function wsh_quiz_is_image_url( string $url ) : bool {
	if ( '' === $url ) {
		return false;
	}

	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	return (bool) preg_match( '/\.(png|jpe?g|gif|webp|avif|svg)$/i', $path );
}

function wsh_quiz_question_is_blank( array $question ) : bool {
	if ( '' !== trim( (string) ( $question['question'] ?? '' ) ) ) {
		return false;
	}

	if ( '' !== trim( (string) ( $question['hint'] ?? '' ) ) || '' !== trim( (string) ( $question['explanation'] ?? '' ) ) ) {
		return false;
	}

	if ( ! empty( $question['media_id'] ) || '' !== trim( (string) ( $question['media_url'] ?? '' ) ) ) {
		return false;
	}

	if ( 'yes_no' === ( $question['type'] ?? '' ) ) {
		return true;
	}

	$options = isset( $question['options'] ) && is_array( $question['options'] ) ? $question['options'] : array();
	foreach ( $options as $option ) {
		$text = is_array( $option ) ? (string) ( $option['text'] ?? '' ) : (string) $option;
		if ( '' !== trim( $text ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Load normalized quiz data for a post.
 */
function wsh_quiz_get_data( int $quiz_id ) : array {
	$raw = get_post_meta( $quiz_id, '_wsh_quiz_data', true );
	return wsh_quiz_normalize_data( $raw );
}

/**
 * Public payload for the player (no correct answers).
 */
function wsh_quiz_get_public_questions( int $quiz_id ) : array {
	$data      = wsh_quiz_get_data( $quiz_id );
	$public    = array();

	foreach ( $data['questions'] as $question ) {
		$options = array();
		foreach ( $question['options'] as $option ) {
			$text = $option['text'];
			if ( 'yes_no' === $question['type'] ) {
				$text = ( 'no' === $text ) ? __( 'No', 'wsh-quiz' ) : __( 'Yes', 'wsh-quiz' );
			}

			$options[] = array(
				'text'      => $text,
				'media_url' => $option['media_url'],
				'source'    => $option['source'],
			);
		}

		$public[] = array(
			'id'          => $question['id'],
			'type'        => $question['type'],
			'question'    => $question['question'],
			'hint'        => $question['hint'],
			'score'       => $question['score'],
			'media_type'  => $question['media_type'],
			'media_url'   => $question['media_url'],
			'media_source'=> $question['media_source'],
			'options'     => $options,
		);
	}

	return $public;
}

/**
 * Find a question by id.
 */
function wsh_quiz_find_question( int $quiz_id, string $question_id ) : ?array {
	$data = wsh_quiz_get_data( $quiz_id );

	foreach ( $data['questions'] as $question ) {
		if ( $question['id'] === $question_id ) {
			return $question;
		}
	}

	return null;
}

/**
 * Convert a YouTube URL into an embeddable URL.
 */
function wsh_quiz_youtube_embed_url( string $url ) : string {
	if ( '' === $url ) {
		return '';
	}

	$video_id = '';

	if ( preg_match( '~youtu\.be/([A-Za-z0-9_-]+)~', $url, $match ) ) {
		$video_id = $match[1];
	} elseif ( preg_match( '~[?&]v=([A-Za-z0-9_-]+)~', $url, $match ) ) {
		$video_id = $match[1];
	} elseif ( preg_match( '~youtube\.com/embed/([A-Za-z0-9_-]+)~', $url, $match ) ) {
		$video_id = $match[1];
	}

	if ( '' === $video_id ) {
		return '';
	}

	return 'https://www.youtube.com/embed/' . rawurlencode( $video_id );
}

/**
 * Resolve an image URL from attachment id or stored URL.
 */
function wsh_quiz_sanitize_date( $value ) : string {
	$value = is_string( $value ) ? trim( $value ) : '';
	if ( '' === $value ) {
		return '';
	}

	$timestamp = strtotime( $value );
	return $timestamp ? gmdate( 'Y-m-d', $timestamp ) : '';
}

/**
 * Whether the quiz is currently inside its scheduled window.
 *
 * @return array{available:bool, message:string}
 */
function wsh_quiz_availability( array $data ) : array {
	if ( 'open' === $data['schedule'] ) {
		return array(
			'available' => true,
			'message'   => '',
		);
	}

	$today = current_time( 'Y-m-d' );
	$start = $data['start_date'];
	$end   = $data['end_date'];

	if ( '' !== $start && $today < $start ) {
		return array(
			'available' => false,
			'message'   => sprintf(
				/* translators: %s: start date */
				__( 'This quiz opens on %s.', 'wsh-quiz' ),
				wsh_quiz_format_date( $start )
			),
		);
	}

	if ( '' !== $end && $today > $end ) {
		return array(
			'available' => false,
			'message'   => __( 'This quiz is no longer active.', 'wsh-quiz' ),
		);
	}

	return array(
		'available' => true,
		'message'   => '',
	);
}

function wsh_quiz_format_date( string $date ) : string {
	if ( '' === $date ) {
		return '';
	}

	$timestamp = strtotime( $date );
	if ( ! $timestamp ) {
		return $date;
	}

	return date_i18n( get_option( 'date_format' ), $timestamp );
}

function wsh_quiz_schedule_label( string $schedule ) : string {
	$types = wsh_quiz_schedule_types();
	return $types[ $schedule ] ?? $schedule;
}

/**
 * @return array{name:string, url:string, logo:string}
 */
function wsh_quiz_get_featured_url( int $quiz_id, string $size = 'medium' ) : string {
	$url = get_the_post_thumbnail_url( $quiz_id, $size );
	return is_string( $url ) ? $url : '';
}

function wsh_quiz_get_excerpt( array $data, int $words = 28 ) : string {
	$text = wp_strip_all_tags( (string) ( $data['description'] ?? '' ) );
	if ( '' === $text ) {
		return '';
	}

	return wp_trim_words( $text, $words );
}

function wsh_quiz_get_sponsor( array $data ) : array {
	$logo = '';
	if ( ! empty( $data['sponsor_logo_id'] ) ) {
		$logo = (string) wp_get_attachment_image_url( (int) $data['sponsor_logo_id'], 'large' );
	}

	return array(
		'name' => isset( $data['sponsor_name'] ) ? (string) $data['sponsor_name'] : '',
		'url'  => isset( $data['sponsor_url'] ) ? (string) $data['sponsor_url'] : '',
		'logo' => $logo,
	);
}

function wsh_quiz_media_image_url( int $media_id, string $fallback = '' ) : string {
	if ( $media_id > 0 ) {
		$url = wp_get_attachment_image_url( $media_id, 'medium_large' );
		if ( is_string( $url ) && '' !== $url ) {
			return $url;
		}
	}

	return $fallback;
}

/**
 * Render one admin question card.
 *
 * @param int|string $index    Question index or placeholder.
 * @param array      $question Normalized question.
 * @param array      $types    Type map.
 * @param bool       $is_pro   Whether Pro is unlocked.
 */
function wsh_quiz_render_question_row( $index, array $question, array $types, bool $is_pro, bool $lock_delete = false ) : void {
	$locked_type = wsh_quiz_is_pro_feature( $question['type'] ) && ! $is_pro;
	$index_attr  = (string) $index;
	$is_saved    = '__INDEX__' !== $index_attr;
	?>
	<div class="wsh-quiz-question is-collapsed<?php echo $locked_type ? ' is-locked' : ''; ?>" data-index="<?php echo esc_attr( $index_attr ); ?>" data-type="<?php echo esc_attr( $question['type'] ); ?>" data-saved="<?php echo $is_saved ? '1' : '0'; ?>">
		<div class="wsh-quiz-question__head">
			<button type="button" class="wsh-quiz-question__toggle">
				<span class="dashicons dashicons-arrow-right-alt2 wsh-quiz-question__chevron" aria-hidden="true"></span>
				<span class="dashicons <?php echo esc_attr( wsh_quiz_question_type_icon( $question['type'] ) ); ?> wsh-quiz-question__type-icon" title="<?php echo esc_attr( $types[ $question['type'] ]['label'] ?? '' ); ?>" aria-hidden="true"></span>
				<strong><?php esc_html_e( 'Question', 'wsh-quiz' ); ?> <span class="wsh-quiz-question-number"><?php echo is_numeric( $index ) ? (int) $index + 1 : ''; ?></span></strong>
				<span class="wsh-quiz-question__preview"><?php echo esc_html( $question['question'] ); ?></span>
			</button>
			<button
				type="button"
				class="button wsh-quiz-remove-question"
				<?php disabled( $lock_delete && $is_saved ); ?>
				<?php echo $lock_delete && $is_saved ? 'title="' . esc_attr__( 'Questions cannot be removed after players have already played this quiz.', 'wsh-quiz' ) . '"' : ''; ?>
			>
				<?php esc_html_e( 'Remove', 'wsh-quiz' ); ?>
			</button>
		</div>

		<div class="wsh-quiz-question__body">
		<input type="hidden" name="wsh_quiz[questions][<?php echo esc_attr( $index_attr ); ?>][id]" value="<?php echo esc_attr( $question['id'] ); ?>" />

		<p>
			<label><?php esc_html_e( 'Type', 'wsh-quiz' ); ?></label>
			<select class="wsh-quiz-type" name="wsh_quiz[questions][<?php echo esc_attr( $index_attr ); ?>][type]">
				<?php foreach ( $types as $key => $type ) : ?>
					<?php $disabled = ( 'pro' === $type['plan'] && ! $is_pro && $question['type'] !== $key ); ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $question['type'], $key ); ?> <?php disabled( $disabled ); ?>>
						<?php
						echo esc_html( $type['label'] );
						if ( 'pro' === $type['plan'] ) {
							echo ' (' . esc_html__( 'Pro', 'wsh-quiz' ) . ')';
						}
						?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label><?php esc_html_e( 'Question text', 'wsh-quiz' ); ?></label>
			<textarea class="widefat" name="wsh_quiz[questions][<?php echo esc_attr( $index_attr ); ?>][question]" rows="2"><?php echo esc_textarea( $question['question'] ); ?></textarea>
		</p>

		<div class="wsh-quiz-media wsh-quiz-field-media" data-types="image_prompt,video_prompt">
			<p>
				<label><?php esc_html_e( 'Question media', 'wsh-quiz' ); ?></label>
				<select class="wsh-quiz-media-type" name="wsh_quiz[questions][<?php echo esc_attr( $index_attr ); ?>][media_type]">
					<option value="none" <?php selected( $question['media_type'], 'none' ); ?>><?php esc_html_e( 'None', 'wsh-quiz' ); ?></option>
					<option value="image" <?php selected( $question['media_type'], 'image' ); ?>><?php esc_html_e( 'Image', 'wsh-quiz' ); ?></option>
					<option value="video" <?php selected( $question['media_type'], 'video' ); ?>><?php esc_html_e( 'YouTube video', 'wsh-quiz' ); ?></option>
				</select>
			</p>
			<div class="wsh-quiz-media-image">
				<?php
				$media_url  = (string) $question['media_url'];
				$show_thumb = ( '' !== $media_url && wsh_quiz_is_image_url( $media_url ) );
				?>
				<button type="button" class="wsh-quiz-media-preview" data-full="<?php echo esc_url( $media_url ); ?>" <?php echo $show_thumb ? '' : 'hidden'; ?> title="<?php esc_attr_e( 'View image', 'wsh-quiz' ); ?>">
					<?php if ( $show_thumb ) : ?>
						<img src="<?php echo esc_url( $media_url ); ?>" alt="" />
					<?php endif; ?>
				</button>
				<input type="hidden" class="wsh-quiz-media-id" name="wsh_quiz[questions][<?php echo esc_attr( $index_attr ); ?>][media_id]" value="<?php echo (int) $question['media_id']; ?>" />
				<input type="url" class="widefat wsh-quiz-media-url" name="wsh_quiz[questions][<?php echo esc_attr( $index_attr ); ?>][media_url]" value="<?php echo esc_attr( $media_url ); ?>" placeholder="<?php esc_attr_e( 'Image URL, or YouTube URL for video questions', 'wsh-quiz' ); ?>" />
				<button type="button" class="button wsh-quiz-pick-image"><?php esc_html_e( 'Select image', 'wsh-quiz' ); ?></button>
				<button type="button" class="button-link wsh-quiz-clear-image" <?php echo $media_url ? '' : 'hidden'; ?>>
					<?php esc_html_e( 'Remove', 'wsh-quiz' ); ?>
				</button>
			</div>
			<p>
				<input type="text" class="widefat" name="wsh_quiz[questions][<?php echo esc_attr( $index_attr ); ?>][media_source]" value="<?php echo esc_attr( $question['media_source'] ); ?>" placeholder="<?php esc_attr_e( 'Source / credit', 'wsh-quiz' ); ?>" />
			</p>
		</div>

		<p>
			<label><?php esc_html_e( 'Hint', 'wsh-quiz' ); ?></label>
			<input type="text" class="widefat" name="wsh_quiz[questions][<?php echo esc_attr( $index_attr ); ?>][hint]" value="<?php echo esc_attr( $question['hint'] ); ?>" />
		</p>
		<p>
			<label><?php esc_html_e( 'Explanation', 'wsh-quiz' ); ?></label>
			<textarea class="widefat" name="wsh_quiz[questions][<?php echo esc_attr( $index_attr ); ?>][explanation]" rows="2"><?php echo esc_textarea( $question['explanation'] ); ?></textarea>
		</p>
		<p>
			<label><?php esc_html_e( 'Score', 'wsh-quiz' ); ?></label>
			<input type="number" min="1" name="wsh_quiz[questions][<?php echo esc_attr( $index_attr ); ?>][score]" value="<?php echo (int) $question['score']; ?>" />
		</p>

		<div class="wsh-quiz-options">
			<?php
			for ( $i = 0; $i < 5; $i++ ) {
				$option = $question['options'][ $i ] ?? array(
					'text'      => '',
					'media_url' => '',
					'media_id'  => 0,
					'source'    => '',
				);
				?>
				<div class="wsh-quiz-option" data-option="<?php echo (int) $i; ?>">
					<label class="wsh-quiz-option__correct">
						<input type="radio" name="wsh_quiz[questions][<?php echo esc_attr( $index_attr ); ?>][correct]" value="<?php echo (int) $i; ?>" <?php checked( (int) $question['correct'], $i ); ?> />
						<?php esc_html_e( 'Correct', 'wsh-quiz' ); ?>
					</label>
					<input
						type="text"
						class="widefat wsh-quiz-option-text"
						name="wsh_quiz[questions][<?php echo esc_attr( $index_attr ); ?>][options][<?php echo (int) $i; ?>][text]"
						value="<?php echo esc_attr( $option['text'] ); ?>"
						placeholder="<?php echo esc_attr( sprintf( __( 'Answer %d', 'wsh-quiz' ), $i + 1 ) ); ?>"
					/>
					<div class="wsh-quiz-option-media" data-types="image_choice">
						<input type="hidden" class="wsh-quiz-media-id" name="wsh_quiz[questions][<?php echo esc_attr( $index_attr ); ?>][options][<?php echo (int) $i; ?>][media_id]" value="<?php echo (int) $option['media_id']; ?>" />
						<input type="url" class="widefat wsh-quiz-media-url" name="wsh_quiz[questions][<?php echo esc_attr( $index_attr ); ?>][options][<?php echo (int) $i; ?>][media_url]" value="<?php echo esc_attr( $option['media_url'] ); ?>" />
						<button type="button" class="button wsh-quiz-pick-image"><?php esc_html_e( 'Select image', 'wsh-quiz' ); ?></button>
					</div>
				</div>
				<?php
			}
			?>
		</div>
		</div>
	</div>
	<?php
}

function wsh_quiz_get_list_page_id() : int {
	return (int) get_option( 'wsh_quiz_page_list', 0 );
}

function wsh_quiz_get_list_url() : string {
	$page_id = wsh_quiz_get_list_page_id();
	if ( $page_id > 0 ) {
		$url = get_permalink( $page_id );
		if ( is_string( $url ) && '' !== $url ) {
			return $url;
		}
	}

	return home_url( '/' );
}

function wsh_quiz_pretty_quiz_url( string $view, int $quiz_id ) : string {
	return add_query_arg(
		array(
			'wsh_quiz_view' => $view,
			'wsh_quiz_id'   => $quiz_id,
		),
		wsh_quiz_get_list_url()
	);
}

function wsh_quiz_get_play_url( int $quiz_id ) : string {
	return wsh_quiz_pretty_quiz_url( 'play', $quiz_id );
}

function wsh_quiz_get_results_url( int $quiz_id ) : string {
	return wsh_quiz_pretty_quiz_url( 'results', $quiz_id );
}

function wsh_quiz_format_duration( int $seconds ) : string {
	$seconds = max( 0, $seconds );
	$minutes = (int) floor( $seconds / 60 );
	$rest    = $seconds % 60;

	return sprintf( '%d:%02d', $minutes, $rest );
}

/**
 * Latest published quiz that is currently playable.
 */
function wsh_quiz_get_latest_playable_id() : int {
	$posts = get_posts(
		array(
			'post_type'      => 'wsh_quiz',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	foreach ( $posts as $quiz ) {
		$availability = wsh_quiz_availability( wsh_quiz_get_data( (int) $quiz->ID ) );
		if ( ! empty( $availability['available'] ) ) {
			return (int) $quiz->ID;
		}
	}

	return 0;
}
