<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $settings */
/** @var string $logo */

settings_errors( 'wsh_quiz_settings' );
?>
<div class="wrap wsh-quiz-settings-page">
	<h1><?php esc_html_e( 'WSH Quiz settings', 'wsh-quiz' ); ?></h1>
	<p class="description"><?php esc_html_e( 'These defaults apply to new quizzes and to the public Quiz branding.', 'wsh-quiz' ); ?></p>

	<form method="post" class="wsh-quiz-settings-form">
		<?php wp_nonce_field( 'wsh_quiz_save_settings', 'wsh_quiz_settings_nonce' ); ?>

		<div class="wsh-quiz-admin-card">
			<h2><?php esc_html_e( 'Branding', 'wsh-quiz' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wsh_quiz_slogan"><?php esc_html_e( 'Slogan', 'wsh-quiz' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="wsh_quiz_slogan" name="wsh_quiz_settings[slogan]" value="<?php echo esc_attr( $settings['slogan'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Shown above the quiz list and in the player.', 'wsh-quiz' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Quiz logo', 'wsh-quiz' ); ?></th>
					<td class="wsh-quiz-sponsor-logo">
						<input type="hidden" class="wsh-quiz-media-id" name="wsh_quiz_settings[logo_id]" value="<?php echo (int) $settings['logo_id']; ?>" />
						<?php if ( $logo ) : ?>
							<img src="<?php echo esc_url( $logo ); ?>" alt="" class="wsh-quiz-sponsor-preview" />
						<?php endif; ?>
						<button type="button" class="button wsh-quiz-pick-image"><?php esc_html_e( 'Select image', 'wsh-quiz' ); ?></button>
						<button type="button" class="button-link wsh-quiz-clear-image" <?php echo $logo ? '' : 'hidden'; ?>>
							<?php esc_html_e( 'Remove logo', 'wsh-quiz' ); ?>
						</button>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wsh_quiz_color_accent"><?php esc_html_e( 'Accent color', 'wsh-quiz' ); ?></label></th>
					<td><input type="text" class="wsh-quiz-color" id="wsh_quiz_color_accent" name="wsh_quiz_settings[color_accent]" value="<?php echo esc_attr( $settings['color_accent'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wsh_quiz_color_text"><?php esc_html_e( 'Text color', 'wsh-quiz' ); ?></label></th>
					<td><input type="text" class="wsh-quiz-color" id="wsh_quiz_color_text" name="wsh_quiz_settings[color_text]" value="<?php echo esc_attr( $settings['color_text'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wsh_quiz_color_background"><?php esc_html_e( 'Background color', 'wsh-quiz' ); ?></label></th>
					<td><input type="text" class="wsh-quiz-color" id="wsh_quiz_color_background" name="wsh_quiz_settings[color_background]" value="<?php echo esc_attr( $settings['color_background'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wsh_quiz_font_family"><?php esc_html_e( 'Font', 'wsh-quiz' ); ?></label></th>
					<td>
						<select id="wsh_quiz_font_family" name="wsh_quiz_settings[font_family]">
							<?php foreach ( WSH_Quiz_Settings::font_choices() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['font_family'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<div class="wsh-quiz-admin-card">
			<h2><?php esc_html_e( 'Default quiz rules', 'wsh-quiz' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Used when an editor creates a new quiz. Each quiz can still override these.', 'wsh-quiz' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wsh_quiz_default_schedule"><?php esc_html_e( 'Schedule', 'wsh-quiz' ); ?></label></th>
					<td>
						<select id="wsh_quiz_default_schedule" name="wsh_quiz_settings[default_schedule]">
							<?php foreach ( wsh_quiz_schedule_types() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['default_schedule'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wsh_quiz_default_timer"><?php esc_html_e( 'Play time (seconds, 0 = off)', 'wsh-quiz' ); ?></label></th>
					<td><input type="number" min="0" id="wsh_quiz_default_timer" name="wsh_quiz_settings[default_timer]" value="<?php echo (int) $settings['default_timer']; ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wsh_quiz_default_entry_mode"><?php esc_html_e( 'Who can play', 'wsh-quiz' ); ?></label></th>
					<td>
						<select id="wsh_quiz_default_entry_mode" name="wsh_quiz_settings[default_entry_mode]">
							<?php foreach ( wsh_quiz_entry_modes() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['default_entry_mode'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Leaderboard', 'wsh-quiz' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wsh_quiz_settings[default_leaderboard]" value="1" <?php checked( $settings['default_leaderboard'] ); ?> />
							<?php esc_html_e( 'Show a leaderboard by default', 'wsh-quiz' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Replay', 'wsh-quiz' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wsh_quiz_settings[default_allow_replay]" value="1" <?php checked( $settings['default_allow_replay'] ); ?> />
							<?php esc_html_e( 'Allow play again by default', 'wsh-quiz' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<div class="wsh-quiz-admin-card">
			<h2><?php esc_html_e( 'AI generation', 'wsh-quiz' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Used to fill a quiz from a topic or article. Requires an active Pro license.', 'wsh-quiz' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wsh_quiz_openai_api_key"><?php esc_html_e( 'OpenAI API key', 'wsh-quiz' ); ?></label></th>
					<td>
						<input type="password" class="regular-text" id="wsh_quiz_openai_api_key" name="wsh_quiz_settings[openai_api_key]" value="" autocomplete="off" />
						<?php if ( '' !== $settings['openai_api_key'] ) : ?>
							<p class="description"><?php esc_html_e( 'A key is already saved. Leave this blank to keep it.', 'wsh-quiz' ); ?></p>
						<?php else : ?>
							<p class="description"><?php esc_html_e( 'Use your own OpenAI key. It is stored only in this plugin and is never shared with other products.', 'wsh-quiz' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wsh_quiz_openai_model"><?php esc_html_e( 'Model', 'wsh-quiz' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="wsh_quiz_openai_model" name="wsh_quiz_settings[openai_model]" value="<?php echo esc_attr( $settings['openai_model'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Default: gpt-4.1-mini.', 'wsh-quiz' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="wsh-quiz-admin-card">
			<h2><?php esc_html_e( 'Public pages', 'wsh-quiz' ); ?></h2>
			<p>
				<?php esc_html_e( 'Quiz list:', 'wsh-quiz' ); ?>
				<?php if ( wsh_quiz_get_list_page_id() > 0 ) : ?>
					<a href="<?php echo esc_url( wsh_quiz_get_list_url() ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wsh_quiz_get_list_url() ); ?></a>
				<?php else : ?>
					<?php esc_html_e( 'The Quiz page will be created automatically.', 'wsh-quiz' ); ?>
				<?php endif; ?>
			</p>
		</div>

		<?php submit_button( __( 'Save settings', 'wsh-quiz' ) ); ?>
	</form>
</div>
