<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $rows */
/** @var string $title */
/** @var int $quiz_id */
/** @var array $sponsor */

$quiz_id     = isset( $quiz_id ) ? (int) $quiz_id : 0;
$has_sponsor = ( ! empty( $sponsor['logo'] ) || ! empty( $sponsor['name'] ) );
?>
<div class="wsh-quiz-board" data-wsh-quiz="0.4.21">
	<div class="wsh-quiz__head">
		<h3 class="wsh-quiz-board__title">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: quiz title */
					__( 'Leaderboard: %s', 'wsh-quiz' ),
					$title
				)
			);
			?>
		</h3>
		<a class="wsh-quiz__back" href="<?php echo esc_url( wsh_quiz_get_list_url() ); ?>"><?php esc_html_e( 'Back to quiz list', 'wsh-quiz' ); ?></a>
	</div>

	<div class="wsh-quiz__stage">
		<?php if ( empty( $rows ) ) : ?>
			<p class="wsh-quiz-board__empty"><?php esc_html_e( 'No results yet. Be the first to play.', 'wsh-quiz' ); ?></p>
		<?php else : ?>
			<ol class="wsh-quiz-board__list">
				<?php foreach ( $rows as $index => $row ) : ?>
					<?php $place = $index + 1; ?>
					<li<?php echo $place <= 3 ? ' class="is-place-' . esc_attr( (string) $place ) . '"' : ''; ?>>
						<span class="wsh-quiz-board__rank"><?php echo esc_html( $place . '.' ); ?></span>
						<span class="wsh-quiz-board__name"><?php echo esc_html( $row['name'] ); ?></span>
						<span class="wsh-quiz-board__time"><?php echo esc_html( wsh_quiz_format_duration( (int) $row['duration'] ) ); ?></span>
						<span class="wsh-quiz-board__score"><?php echo esc_html( $row['score'] . '/' . $row['max'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</div>

	<?php if ( $has_sponsor ) : ?>
		<div class="wsh-quiz__credit">
			<?php if ( ! empty( $sponsor['logo'] ) ) : ?>
				<?php if ( ! empty( $sponsor['url'] ) ) : ?>
					<a href="<?php echo esc_url( $sponsor['url'] ); ?>" target="_blank" rel="noopener sponsored">
						<img src="<?php echo esc_url( $sponsor['logo'] ); ?>" alt="<?php echo esc_attr( $sponsor['name'] ); ?>" />
					</a>
				<?php else : ?>
					<img src="<?php echo esc_url( $sponsor['logo'] ); ?>" alt="<?php echo esc_attr( $sponsor['name'] ); ?>" />
				<?php endif; ?>
			<?php elseif ( ! empty( $sponsor['name'] ) ) : ?>
				<span class="wsh-quiz__credit-name"><?php echo esc_html( $sponsor['name'] ); ?></span>
			<?php endif; ?>
			<span class="wsh-quiz__credit-label"><?php esc_html_e( 'Quiz sponsor', 'wsh-quiz' ); ?></span>
		</div>
	<?php endif; ?>

	<?php if ( $quiz_id > 0 ) : ?>
		<p class="wsh-quiz-page-nav">
			<a href="<?php echo esc_url( wsh_quiz_get_play_url( $quiz_id ) ); ?>"><?php esc_html_e( 'Start the quiz', 'wsh-quiz' ); ?></a>
		</p>
	<?php endif; ?>
</div>
