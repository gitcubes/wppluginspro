<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var WP_Post $quiz */
/** @var array $data */
/** @var array $questions */
/** @var bool $show_branding */
/** @var array $availability */
/** @var array $sponsor */
/** @var WP_User|null $user */
/** @var bool $already_played */

$login_url = wp_login_url( get_permalink() );
$payload   = array(
	'id'           => (int) $quiz->ID,
	'title'        => get_the_title( $quiz ),
	'questions'    => $questions,
	'timer'        => (int) $data['timer'],
	'entryMode'    => $data['entry_mode'],
	'leaderboard'  => ! empty( $data['leaderboard'] ),
	'allowReplay'    => ! empty( $data['allow_replay'] ),
	'alreadyPlayed'  => ! empty( $already_played ),
	'available'      => ! empty( $availability['available'] ),
	'message'        => $availability['message'],
	'loggedIn'       => (bool) $user,
	'loginUrl'     => $login_url,
	'player'       => array(
		'first_name' => $user ? (string) $user->first_name : '',
		'last_name'  => $user ? (string) $user->last_name : '',
		'email'      => $user ? (string) $user->user_email : '',
	),
);

$already_played = ! empty( $already_played );
$needs_account = ( 'account' === $data['entry_mode'] && ! $user );
$needs_guest   = ( 'guest' === $data['entry_mode'] && ! $user );
$has_sponsor   = ( ! empty( $sponsor['logo'] ) || ! empty( $sponsor['name'] ) );
?>
<div class="wsh-quiz<?php echo ( $needs_account || $needs_guest ) ? ' is-gate' : ''; ?>" data-wsh-quiz="0.4.21" data-quiz="<?php echo esc_attr( wp_json_encode( $payload ) ); ?>">
	<div class="wsh-quiz__head">
		<h3 class="wsh-quiz__title"><?php echo esc_html( get_the_title( $quiz ) ); ?></h3>
		<a class="wsh-quiz__back" href="<?php echo esc_url( wsh_quiz_get_list_url() ); ?>"><?php esc_html_e( 'Back to quiz list', 'wsh-quiz' ); ?></a>
	</div>

	<div class="wsh-quiz__stage">
		<?php if ( empty( $availability['available'] ) ) : ?>
			<p class="wsh-quiz__notice"><?php echo esc_html( $availability['message'] ); ?></p>
		<?php else : ?>
			<div class="wsh-quiz__played" <?php echo $already_played ? '' : 'hidden'; ?>>
				<p class="wsh-quiz__played-title"><?php esc_html_e( 'You have already played this quiz.', 'wsh-quiz' ); ?></p>
				<p class="wsh-quiz__played-rule"><?php esc_html_e( 'This quiz can be played only once. Logged-in players are recognized by their account. Guests are recognized by the email they entered and this browser.', 'wsh-quiz' ); ?></p>
			</div>
		<?php if ( ! $already_played ) : ?>
			<div class="wsh-quiz__gate" <?php echo ( $needs_account || $needs_guest ) ? '' : 'hidden'; ?>>
				<?php if ( $needs_account ) : ?>
					<p class="wsh-quiz__gate-lead"><?php esc_html_e( 'Create an account or log in to play this quiz.', 'wsh-quiz' ); ?></p>
					<a class="wsh-quiz__start" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Log in to play', 'wsh-quiz' ); ?></a>
				<?php elseif ( $needs_guest ) : ?>
					<p class="wsh-quiz__gate-lead"><?php esc_html_e( 'Enter your name and email to start.', 'wsh-quiz' ); ?></p>
					<form class="wsh-quiz__player-form">
						<div class="wsh-quiz__player-row">
							<label>
								<span><?php esc_html_e( 'First name', 'wsh-quiz' ); ?></span>
								<input type="text" name="first_name" autocomplete="given-name" required />
							</label>
							<label>
								<span><?php esc_html_e( 'Last name', 'wsh-quiz' ); ?></span>
								<input type="text" name="last_name" autocomplete="family-name" required />
							</label>
						</div>
						<label>
							<span><?php esc_html_e( 'Email', 'wsh-quiz' ); ?></span>
							<input type="email" name="email" autocomplete="email" required />
						</label>
						<button type="submit" class="wsh-quiz__start"><?php esc_html_e( 'Start the quiz', 'wsh-quiz' ); ?></button>
					</form>
				<?php endif; ?>
			</div>

			<div class="wsh-quiz__play" <?php echo ( $needs_account || $needs_guest ) ? 'hidden' : ''; ?>>
				<div class="wsh-quiz__toolbar">
					<span class="wsh-quiz__timer" hidden>00:00</span>
					<button type="button" class="wsh-quiz__giveup"><?php esc_html_e( 'Give up', 'wsh-quiz' ); ?></button>
				</div>
				<div class="wsh-quiz__track">
					<div class="wsh-quiz__bar"><span class="wsh-quiz__bar-fill"></span></div>
					<span class="wsh-quiz__progress-text"></span>
				</div>

				<div class="wsh-quiz__body">
					<div class="wsh-quiz__media" hidden></div>
					<div class="wsh-quiz__prompt">
						<button type="button" class="wsh-quiz__hint-btn" hidden aria-expanded="false">
							<span aria-hidden="true">i</span>
						</button>
						<p class="wsh-quiz__question"></p>
						<p class="wsh-quiz__hint" hidden></p>
					</div>
					<div class="wsh-quiz__options"></div>
					<div class="wsh-quiz__feedback" hidden></div>
				</div>

				<div class="wsh-quiz__footer">
					<button type="button" class="wsh-quiz__skip"><?php esc_html_e( 'Skip question', 'wsh-quiz' ); ?></button>
					<button type="button" class="wsh-quiz__next" hidden></button>
				</div>
			</div>
		<?php endif; ?>
		<?php endif; ?>

		<div class="wsh-quiz__result" hidden>
			<div class="wsh-quiz__result-hero">
				<div class="wsh-quiz__trophy" hidden>
					<span class="wsh-quiz__trophy-burst" aria-hidden="true"></span>
					<svg class="wsh-quiz__trophy-icon" viewBox="0 0 80 80" aria-hidden="true">
						<path class="wsh-quiz__trophy-cup" d="M22 16h36v10c0 16-9 26-18 26S22 42 22 26V16z"></path>
						<path class="wsh-quiz__trophy-handle" d="M22 20h-9c0 13 8 20 15 23"></path>
						<path class="wsh-quiz__trophy-handle" d="M58 20h9c0 13-8 20-15 23"></path>
						<rect class="wsh-quiz__trophy-stem" x="36" y="50" width="8" height="10" rx="1"></rect>
						<path class="wsh-quiz__trophy-base" d="M28 64h24l-3 8H31z"></path>
					</svg>
				</div>
				<p class="wsh-quiz__result-kicker"><?php esc_html_e( 'Your result', 'wsh-quiz' ); ?></p>
				<p class="wsh-quiz__score"><span class="wsh-quiz__score-num"></span></p>
				<p class="wsh-quiz__score-caption"></p>
				<div class="wsh-quiz__marks"></div>
				<p class="wsh-quiz__rank" hidden></p>
				<p class="wsh-quiz__time" hidden></p>
				<div class="wsh-quiz__result-actions">
					<button type="button" class="wsh-quiz__restart" <?php echo empty( $data['allow_replay'] ) ? 'hidden' : ''; ?>></button>
				</div>
			</div>
			<div class="wsh-quiz__share-row">
				<p class="wsh-quiz__share-label"><?php esc_html_e( 'Share your result', 'wsh-quiz' ); ?></p>
				<div class="wsh-quiz__share-list">
					<button type="button" class="wsh-quiz__share" data-network="facebook">Facebook</button>
					<button type="button" class="wsh-quiz__share" data-network="x">X</button>
					<button type="button" class="wsh-quiz__share" data-network="whatsapp">WhatsApp</button>
					<button type="button" class="wsh-quiz__share" data-network="copy"><?php esc_html_e( 'Copy link', 'wsh-quiz' ); ?></button>
				</div>
			</div>
			<div class="wsh-quiz__board" hidden>
				<h4 class="wsh-quiz__board-title"><?php esc_html_e( 'Leaderboard', 'wsh-quiz' ); ?></h4>
				<ol class="wsh-quiz__board-list"></ol>
			</div>
		</div>
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

	<?php if ( $show_branding ) : ?>
		<p class="wsh-quiz__branding">
			<a href="https://wppluginspro.io" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Powered by WSH Quiz', 'wsh-quiz' ); ?></a>
		</p>
	<?php endif; ?>
</div>
