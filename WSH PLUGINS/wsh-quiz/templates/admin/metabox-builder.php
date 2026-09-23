<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $data */
/** @var bool $is_pro */
/** @var array $types */
/** @var string $upgrade */
/** @var bool $has_games */
/** @var string $site_locale */
/** @var array $ai_languages */
/** @var string $ai_prompt */

$questions    = ! empty( $data['questions'] ) ? $data['questions'] : array();
$has_games    = ! empty( $has_games );
$site_locale  = isset( $site_locale ) ? (string) $site_locale : WSH_Quiz_AI::site_locale();
$ai_languages = isset( $ai_languages ) && is_array( $ai_languages ) ? $ai_languages : WSH_Quiz_AI::languages();
$ai_prompt    = isset( $ai_prompt ) ? (string) $ai_prompt : '';
?>
<div class="wsh-quiz-builder" data-pro="<?php echo $is_pro ? '1' : '0'; ?>" data-has-games="<?php echo $has_games ? '1' : '0'; ?>">
	<div class="wsh-quiz-toolbar">
		<button type="button" class="button button-primary wsh-quiz-add-question">
			<?php esc_html_e( 'Add question', 'wsh-quiz' ); ?>
		</button>

		<button
			type="button"
			class="button wsh-quiz-ai-button"
			<?php echo $is_pro ? '' : 'disabled="disabled"'; ?>
			data-locked="<?php echo $is_pro ? '0' : '1'; ?>"
		>
			<?php esc_html_e( 'Generate with AI', 'wsh-quiz' ); ?>
			<?php if ( ! $is_pro ) : ?>
				<span class="wsh-quiz-pro-pill"><?php esc_html_e( 'Pro', 'wsh-quiz' ); ?></span>
			<?php endif; ?>
		</button>

		<?php if ( ! $is_pro ) : ?>
			<a class="wsh-quiz-upgrade-link" href="<?php echo esc_url( $upgrade ); ?>">
				<?php esc_html_e( 'Unlock AI and media questions', 'wsh-quiz' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<p class="wsh-quiz-questions-locked" <?php echo $has_games ? '' : 'hidden'; ?>>
		<?php esc_html_e( 'Questions cannot be removed after players have already played this quiz. Delete all games first if you need to change the question list.', 'wsh-quiz' ); ?>
	</p>

	<div class="wsh-quiz-modal" id="wsh-quiz-ai-modal" hidden>
		<div class="wsh-quiz-modal__box wsh-quiz-modal__box--ai" role="dialog" aria-modal="true" aria-labelledby="wsh-quiz-ai-title">
			<div class="wsh-quiz-modal__head">
				<h2 id="wsh-quiz-ai-title"><?php esc_html_e( 'Generate with AI', 'wsh-quiz' ); ?></h2>
				<button type="button" class="button-link wsh-quiz-modal-close"><?php esc_html_e( 'Close', 'wsh-quiz' ); ?></button>
			</div>
			<div class="wsh-quiz-modal__body">
				<div class="wsh-quiz-ai-grid">
					<p>
						<label for="wsh-quiz-ai-count"><?php esc_html_e( 'Number of questions', 'wsh-quiz' ); ?></label>
						<input type="number" id="wsh-quiz-ai-count" min="1" max="20" value="5" />
					</p>
					<p>
						<label for="wsh-quiz-ai-difficulty"><?php esc_html_e( 'Difficulty', 'wsh-quiz' ); ?></label>
						<select id="wsh-quiz-ai-difficulty">
							<option value="easy"><?php esc_html_e( 'Easy', 'wsh-quiz' ); ?></option>
							<option value="medium" selected><?php esc_html_e( 'Medium', 'wsh-quiz' ); ?></option>
							<option value="hard"><?php esc_html_e( 'Hard', 'wsh-quiz' ); ?></option>
						</select>
					</p>
					<p>
						<label for="wsh-quiz-ai-language"><?php esc_html_e( 'Quiz language', 'wsh-quiz' ); ?></label>
						<select id="wsh-quiz-ai-language">
							<?php foreach ( $ai_languages as $locale => $label ) : ?>
								<option value="<?php echo esc_attr( $locale ); ?>" <?php selected( $site_locale, $locale ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="wsh-quiz-ai-timer"><?php esc_html_e( 'Suggested play time (seconds)', 'wsh-quiz' ); ?></label>
						<input type="number" id="wsh-quiz-ai-timer" min="0" value="125" />
					</p>
				</div>
				<p class="description"><?php esc_html_e( 'Time is calculated from the number of questions and the difficulty. You can change it.', 'wsh-quiz' ); ?></p>
				<p>
					<label for="wsh-quiz-ai-topic"><?php esc_html_e( 'Prompt', 'wsh-quiz' ); ?></label>
					<textarea id="wsh-quiz-ai-topic" class="widefat" rows="6"><?php echo esc_textarea( $ai_prompt ); ?></textarea>
					<span class="description"><?php esc_html_e( 'Prepared from the quiz title and description. You can edit it before generating.', 'wsh-quiz' ); ?></span>
				</p>
				<p>
					<label for="wsh-quiz-ai-url"><?php esc_html_e( 'Article URL', 'wsh-quiz' ); ?></label>
					<input type="url" id="wsh-quiz-ai-url" class="widefat" placeholder="https://" />
				</p>
				<fieldset class="wsh-quiz-ai-mix">
					<legend><?php esc_html_e( 'Question mix', 'wsh-quiz' ); ?></legend>
					<p class="description"><?php esc_html_e( 'Set how many questions of each type. The total above follows this mix.', 'wsh-quiz' ); ?></p>
					<div class="wsh-quiz-ai-mix__row">
						<label for="wsh-quiz-ai-choice"><?php esc_html_e( 'Multiple choice', 'wsh-quiz' ); ?></label>
						<input type="number" id="wsh-quiz-ai-choice" min="0" max="20" value="5" />
					</div>
					<div class="wsh-quiz-ai-mix__row">
						<label for="wsh-quiz-ai-photo"><?php esc_html_e( 'Photo questions', 'wsh-quiz' ); ?></label>
						<input type="number" id="wsh-quiz-ai-photo" min="0" max="20" value="0" />
					</div>
					<div class="wsh-quiz-ai-mix__row">
						<label for="wsh-quiz-ai-video"><?php esc_html_e( 'Video questions', 'wsh-quiz' ); ?></label>
						<input type="number" id="wsh-quiz-ai-video" min="0" max="20" value="0" />
					</div>
					<p class="description"><?php esc_html_e( 'Photo and video questions get text and a media hint. Add the image or YouTube link after generation.', 'wsh-quiz' ); ?></p>
				</fieldset>
				<p>
					<label>
						<input type="checkbox" id="wsh-quiz-ai-replace" value="1" <?php echo $has_games ? 'disabled' : 'checked'; ?> />
						<?php esc_html_e( 'Replace existing questions', 'wsh-quiz' ); ?>
					</label>
				</p>
				<p class="wsh-quiz-ai-status" hidden></p>
				<p>
					<button type="button" class="button button-primary wsh-quiz-ai-submit"><?php esc_html_e( 'Generate questions', 'wsh-quiz' ); ?></button>
				</p>
			</div>
		</div>
	</div>

	<p class="wsh-quiz-questions-empty" <?php echo empty( $questions ) ? '' : 'hidden'; ?>>
		<?php esc_html_e( 'No questions yet. Add a question or generate with AI.', 'wsh-quiz' ); ?>
	</p>
	<div class="wsh-quiz-questions" data-count="<?php echo count( $questions ); ?>">
		<?php
		foreach ( $questions as $index => $question ) {
			wsh_quiz_render_question_row( $index, $question, $types, $is_pro, $has_games );
		}
		?>
	</div>

	<script type="text/html" id="tmpl-wsh-quiz-question">
		<?php
		wsh_quiz_render_question_row(
			'__INDEX__',
			wsh_quiz_normalize_question( array( 'type' => 'choice', 'id' => '__ID__' ) ),
			$types,
			$is_pro
		);
		?>
	</script>
</div>
