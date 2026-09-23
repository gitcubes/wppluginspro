<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $data */

$sponsor = wsh_quiz_get_sponsor( $data );
?>
<div class="wsh-quiz-metabox">
	<p class="description">
		<?php
		printf(
			/* translators: %s: settings page URL */
			esc_html__( 'Slogan, logo, colors and default rules are in %s.', 'wsh-quiz' ),
			'<a href="' . esc_url( admin_url( 'edit.php?post_type=wsh_quiz&page=wsh-quiz-settings' ) ) . '">' . esc_html__( 'Settings', 'wsh-quiz' ) . '</a>'
		);
		?>
	</p>
	<section class="wsh-quiz-admin-card">
		<h3><?php esc_html_e( 'About', 'wsh-quiz' ); ?></h3>
		<p>
			<label for="wsh_quiz_description"><?php esc_html_e( 'Description', 'wsh-quiz' ); ?></label>
			<textarea id="wsh_quiz_description" class="widefat" name="wsh_quiz[description]" rows="3"><?php echo esc_textarea( $data['description'] ); ?></textarea>
		</p>
		<p class="description"><?php esc_html_e( 'Set a featured image in the sidebar. It is the leading image on the Quiz page.', 'wsh-quiz' ); ?></p>
	</section>

	<section class="wsh-quiz-admin-card">
		<h3><?php esc_html_e( 'Schedule', 'wsh-quiz' ); ?></h3>
		<p>
			<label for="wsh_quiz_schedule"><?php esc_html_e( 'Type', 'wsh-quiz' ); ?></label>
			<select id="wsh_quiz_schedule" name="wsh_quiz[schedule]" class="widefat">
				<?php foreach ( wsh_quiz_schedule_types() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $data['schedule'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<div class="wsh-quiz-admin-row">
			<p>
				<label for="wsh_quiz_start_date"><?php esc_html_e( 'Start date', 'wsh-quiz' ); ?></label>
				<input type="date" id="wsh_quiz_start_date" name="wsh_quiz[start_date]" value="<?php echo esc_attr( $data['start_date'] ); ?>" />
			</p>
			<p>
				<label for="wsh_quiz_end_date"><?php esc_html_e( 'End date', 'wsh-quiz' ); ?></label>
				<input type="date" id="wsh_quiz_end_date" name="wsh_quiz[end_date]" value="<?php echo esc_attr( $data['end_date'] ); ?>" />
			</p>
		</div>
	</section>

	<section class="wsh-quiz-admin-card">
		<h3><?php esc_html_e( 'Play rules', 'wsh-quiz' ); ?></h3>
		<div class="wsh-quiz-admin-row">
			<p>
				<label for="wsh_quiz_entry_mode"><?php esc_html_e( 'Who can play', 'wsh-quiz' ); ?></label>
				<select id="wsh_quiz_entry_mode" name="wsh_quiz[entry_mode]" class="widefat">
					<?php foreach ( wsh_quiz_entry_modes() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $data['entry_mode'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="wsh_quiz_timer"><?php esc_html_e( 'Play time (seconds, 0 = off)', 'wsh-quiz' ); ?></label>
				<input type="number" min="0" id="wsh_quiz_timer" name="wsh_quiz[timer]" value="<?php echo (int) $data['timer']; ?>" />
			</p>
		</div>
		<p>
			<label for="wsh_quiz_difficulty"><?php esc_html_e( 'Difficulty', 'wsh-quiz' ); ?></label>
			<select id="wsh_quiz_difficulty" name="wsh_quiz[difficulty]" class="widefat">
				<?php foreach ( wsh_quiz_difficulties() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $data['difficulty'] ?? 'medium', $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label>
				<input type="checkbox" name="wsh_quiz[leaderboard]" value="1" <?php checked( ! empty( $data['leaderboard'] ) ); ?> />
				<?php esc_html_e( 'Show a leaderboard', 'wsh-quiz' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="wsh_quiz[allow_replay]" value="1" <?php checked( ! empty( $data['allow_replay'] ) ); ?> />
				<?php esc_html_e( 'Allow play again', 'wsh-quiz' ); ?>
			</label>
			<span class="description"><?php esc_html_e( 'If this is off, a player can play only once. Logged-in players are recognized by account, guests by email and this browser.', 'wsh-quiz' ); ?></span>
		</p>
	</section>

	<section class="wsh-quiz-admin-card">
		<h3><?php esc_html_e( 'Sponsor', 'wsh-quiz' ); ?></h3>
		<div class="wsh-quiz-admin-row">
			<p>
				<label for="wsh_quiz_sponsor_name"><?php esc_html_e( 'Sponsor name', 'wsh-quiz' ); ?></label>
				<input type="text" class="widefat" id="wsh_quiz_sponsor_name" name="wsh_quiz[sponsor_name]" value="<?php echo esc_attr( $data['sponsor_name'] ); ?>" />
			</p>
			<p>
				<label for="wsh_quiz_sponsor_url"><?php esc_html_e( 'Sponsor link', 'wsh-quiz' ); ?></label>
				<input type="url" class="widefat" id="wsh_quiz_sponsor_url" name="wsh_quiz[sponsor_url]" value="<?php echo esc_attr( $data['sponsor_url'] ); ?>" />
			</p>
		</div>
		<p class="wsh-quiz-sponsor-logo">
			<label><?php esc_html_e( 'Sponsor banner', 'wsh-quiz' ); ?></label>
			<input type="hidden" class="wsh-quiz-media-id" name="wsh_quiz[sponsor_logo_id]" value="<?php echo (int) $data['sponsor_logo_id']; ?>" />
			<?php if ( $sponsor['logo'] ) : ?>
				<img src="<?php echo esc_url( $sponsor['logo'] ); ?>" alt="" class="wsh-quiz-sponsor-preview" />
			<?php endif; ?>
			<button type="button" class="button wsh-quiz-pick-image"><?php esc_html_e( 'Select image', 'wsh-quiz' ); ?></button>
			<button type="button" class="button-link wsh-quiz-clear-image" <?php echo $sponsor['logo'] ? '' : 'hidden'; ?>>
				<?php esc_html_e( 'Remove banner', 'wsh-quiz' ); ?>
			</button>
		</p>
	</section>
</div>
