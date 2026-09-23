<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $items */

$brand = wsh_quiz_get_global_settings();
$logo  = WSH_Quiz_Settings::logo_url( $brand );
WSH_Quiz_Assets::print_frontend_css();
?>
<div class="wsh-quiz-list" data-wsh-quiz="0.4.13">
	<?php if ( $logo || $brand['slogan'] ) : ?>
		<header class="wsh-quiz-list__brand">
			<?php if ( $logo ) : ?>
				<img class="wsh-quiz-list__logo" src="<?php echo esc_url( $logo ); ?>" alt="" />
			<?php endif; ?>
			<?php if ( $brand['slogan'] ) : ?>
				<p class="wsh-quiz-list__slogan"><?php echo esc_html( $brand['slogan'] ); ?></p>
			<?php endif; ?>
		</header>
	<?php endif; ?>

	<?php foreach ( $items as $item ) : ?>
		<?php
		$quiz         = $item['quiz'];
		$data         = $item['data'];
		$sponsor      = $item['sponsor'];
		$availability = $item['availability'];
		$quiz_id      = (int) $quiz->ID;
		$date_label   = $data['start_date'] ? wsh_quiz_format_date( $data['start_date'] ) : get_the_date( '', $quiz );
		$question_n   = count( $data['questions'] );
		$stats_label  = sprintf(
			/* translators: 1: number of questions, 2: difficulty label */
			_n( '%1$s question / %2$s', '%1$s questions / %2$s', $question_n, 'wsh-quiz' ),
			number_format_i18n( $question_n ),
			wsh_quiz_difficulty_label( (string) ( $data['difficulty'] ?? 'medium' ) )
		);
		$featured     = wsh_quiz_get_featured_url( $quiz_id, 'large' );
		$excerpt      = wsh_quiz_get_excerpt( $data );
		$title_text   = get_the_title( $quiz );
		$initial      = function_exists( 'mb_substr' )
			? mb_strtoupper( mb_substr( $title_text, 0, 1 ) )
			: strtoupper( substr( $title_text, 0, 1 ) );
		$play_url     = wsh_quiz_get_play_url( $quiz_id );
		$has_sponsor  = ( $sponsor['logo'] || $sponsor['name'] );
		?>
		<article class="wsh-quiz-list__item<?php echo $has_sponsor ? ' has-sponsor' : ''; ?>">
			<div class="wsh-quiz-list__main">
				<a class="wsh-quiz-list__media" href="<?php echo esc_url( $play_url ); ?>" aria-hidden="true" tabindex="-1">
					<?php if ( $featured ) : ?>
						<img src="<?php echo esc_url( $featured ); ?>" alt="" />
					<?php else : ?>
						<span class="wsh-quiz-list__placeholder"><?php echo esc_html( $initial ); ?></span>
					<?php endif; ?>
				</a>

				<div class="wsh-quiz-list__copy">
					<p class="wsh-quiz-list__meta">
						<span class="wsh-quiz-list__when">
							<?php echo esc_html( wsh_quiz_schedule_label( $data['schedule'] ) ); ?>
							<?php if ( $date_label ) : ?>
								<span>· <?php echo esc_html( $date_label ); ?></span>
							<?php endif; ?>
						</span>
						<span class="wsh-quiz-list__stats"><?php echo esc_html( $stats_label ); ?></span>
					</p>
					<h3 class="wsh-quiz-list__title">
						<a href="<?php echo esc_url( $play_url ); ?>">
							<?php echo esc_html( get_the_title( $quiz ) ); ?>
						</a>
					</h3>
					<?php if ( $excerpt ) : ?>
						<p class="wsh-quiz-list__excerpt"><?php echo esc_html( $excerpt ); ?></p>
					<?php endif; ?>
					<p class="wsh-quiz-list__actions">
						<?php if ( ! empty( $availability['available'] ) ) : ?>
							<a class="wsh-quiz-list__play" href="<?php echo esc_url( $play_url ); ?>">
								<?php esc_html_e( 'Start the quiz', 'wsh-quiz' ); ?>
							</a>
						<?php else : ?>
							<span class="wsh-quiz-list__closed"><?php echo esc_html( $availability['message'] ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $data['leaderboard'] ) ) : ?>
							<a class="wsh-quiz-list__results" href="<?php echo esc_url( wsh_quiz_get_results_url( $quiz_id ) ); ?>">
								<?php esc_html_e( 'View results', 'wsh-quiz' ); ?>
							</a>
						<?php endif; ?>
					</p>
				</div>
			</div>

			<?php if ( $has_sponsor ) : ?>
				<aside class="wsh-quiz-list__sponsor">
					<p class="wsh-quiz-list__sponsor-label"><?php esc_html_e( 'Quiz sponsor', 'wsh-quiz' ); ?></p>
					<?php if ( $sponsor['logo'] ) : ?>
						<?php if ( $sponsor['url'] ) : ?>
							<a href="<?php echo esc_url( $sponsor['url'] ); ?>" target="_blank" rel="noopener sponsored">
								<img src="<?php echo esc_url( $sponsor['logo'] ); ?>" alt="<?php echo esc_attr( $sponsor['name'] ); ?>" />
							</a>
						<?php else : ?>
							<img src="<?php echo esc_url( $sponsor['logo'] ); ?>" alt="<?php echo esc_attr( $sponsor['name'] ); ?>" />
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( $sponsor['name'] ) : ?>
						<p class="wsh-quiz-list__sponsor-name"><?php echo esc_html( $sponsor['name'] ); ?></p>
					<?php endif; ?>
				</aside>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
</div>
