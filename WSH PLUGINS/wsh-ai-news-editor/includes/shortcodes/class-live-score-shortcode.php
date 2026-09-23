<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Live_Score_Shortcode {

	const AJAX_ACTION = 'wsh_aine_refresh_live_score';

	public static function init(): void {
		add_shortcode( 'wsh_live_score', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_refresh' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_refresh' ) );
	}

	public static function register_assets(): void {
		wp_register_style(
			'wsh-aine-live-score',
			WSH_AINE_URL . 'assets/css/live-score.css',
			array(),
			WSH_AINE_VERSION
		);

		wp_register_script(
			'wsh-aine-live-score',
			WSH_AINE_URL . 'assets/js/live-score.js',
			array(),
			WSH_AINE_VERSION,
			true
		);

		wp_localize_script(
			'wsh-aine-live-score',
			'wshAineLiveScore',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::AJAX_ACTION ),
				'interval' => 15000,
			)
		);
	}

	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'sport'      => 'football',
				'league_id'  => '',
				'fixture_id' => '',
				'game_id'    => '',
				'title'      => '1',
			),
			(array) $atts,
			'wsh_live_score'
		);

		$parsed = self::parse_atts( $atts );

		if ( is_wp_error( $parsed ) ) {
			return '<p>' . esc_html( $parsed->get_error_message() ) . '</p>';
		}

		$result = self::fetch_scores( $parsed );

		if ( is_wp_error( $result ) ) {
			return '<p>' . esc_html( $result->get_error_message() ) . '</p>';
		}

		wp_enqueue_style( 'wsh-aine-live-score' );
		wp_enqueue_script( 'wsh-aine-live-score' );

		$is_live = ! empty( $result['is_live'] );

		ob_start();
		?>
		<div
			class="wsh-aine-live-score-shortcode"
			data-sport="<?php echo esc_attr( $parsed['sport'] ); ?>"
			data-league-id="<?php echo esc_attr( (string) $parsed['league_id'] ); ?>"
			data-event-id="<?php echo esc_attr( (string) $parsed['event_id'] ); ?>"
			data-title="<?php echo $parsed['show_title'] ? '1' : ''; ?>"
			data-live="<?php echo $is_live ? '1' : '0'; ?>"
		>
			<?php echo self::render_inner( $result, $parsed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	public static function ajax_refresh(): void {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );

		$parsed = self::parse_atts(
			array(
				'sport'      => isset( $_POST['sport'] ) ? sanitize_key( wp_unslash( $_POST['sport'] ) ) : 'football',
				'league_id'  => isset( $_POST['league_id'] ) ? absint( $_POST['league_id'] ) : 0,
				'fixture_id' => isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0,
				'game_id'    => 0,
				'title'      => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '1',
			)
		);

		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( $parsed->get_error_message() );
		}

		$result = self::fetch_scores( $parsed );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'html'    => self::render_inner( $result, $parsed ),
				'is_live' => ! empty( $result['is_live'] ),
			)
		);
	}

	protected static function parse_atts( array $atts ) {
		$sport     = sanitize_key( (string) ( $atts['sport'] ?? 'football' ) );
		$league_id = absint( $atts['league_id'] ?? 0 );
		$event_id  = absint( $atts['fixture_id'] ?? 0 );

		if ( $event_id <= 0 ) {
			$event_id = absint( $atts['game_id'] ?? 0 );
		}

		if ( ! in_array( $sport, array( 'football', 'basketball' ), true ) ) {
			return new WP_Error( 'invalid_sport', __( 'Live score shortcode currently supports football and basketball.', 'wsh-ai-news-editor' ) );
		}

		if ( $league_id <= 0 && $event_id <= 0 ) {
			return new WP_Error( 'missing_live_params', __( 'Live score shortcode is missing required parameters.', 'wsh-ai-news-editor' ) );
		}

		return array(
			'sport'      => $sport,
			'league_id'  => $league_id,
			'event_id'   => $event_id,
			'show_title' => '1' === (string) ( $atts['title'] ?? '1' ),
		);
	}

	protected static function fetch_scores( array $parsed ) {
		$options = get_option( 'wsh_aine_settings', array() );

		return WSH_AINE_API_Sports_Fetcher::get_live_scores_by_params(
			$parsed['sport'],
			$parsed['league_id'],
			$parsed['event_id'],
			$options,
			false
		);
	}

	protected static function render_inner( array $result, array $parsed ): string {
		$items     = isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();
		$is_live   = ! empty( $result['is_live'] );
		$show_title = ! empty( $parsed['show_title'] );

		$league_name = '';
		if ( ! empty( $items[0]['league_name'] ) ) {
			$league_name = (string) $items[0]['league_name'];
		}

		ob_start();
		?>
		<div class="wsh-aine-live-score-inner">
			<?php if ( $show_title ) : ?>
				<div class="wsh-aine-live-score-head">
					<?php if ( $league_name ) : ?>
						<h3><?php echo esc_html( $league_name ); ?></h3>
					<?php endif; ?>
					<div class="wsh-aine-live-score-status <?php echo $is_live ? 'is-live' : 'is-finished'; ?>">
						<?php if ( $is_live ) : ?>
							<span class="wsh-aine-live-dot"></span>
							<?php esc_html_e( 'LIVE', 'wsh-ai-news-editor' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Score', 'wsh-ai-news-editor' ); ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( empty( $items ) ) : ?>
				<p class="wsh-aine-live-score-empty"><?php esc_html_e( 'No live matches at the moment.', 'wsh-ai-news-editor' ); ?></p>
			<?php else : ?>
				<?php foreach ( $items as $item ) : ?>
					<div class="wsh-aine-live-match-block">
						<?php echo self::render_match_extras( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo self::render_match_table( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	public static function render_match_extras( array $item ): string {
		$stats = isset( $item['match_stats'] ) && is_array( $item['match_stats'] ) ? $item['match_stats'] : array();
		$events = isset( $item['match_events'] ) && is_array( $item['match_events'] ) ? $item['match_events'] : array();
		$goals = isset( $events['goals'] ) && is_array( $events['goals'] ) ? $events['goals'] : array();
		$cards = isset( $events['cards'] ) && is_array( $events['cards'] ) ? $events['cards'] : array();

		if ( empty( $stats ) && empty( $goals ) && empty( $cards ) ) {
			return '';
		}

		$home_name = isset( $item['home_team']['name'] ) ? (string) $item['home_team']['name'] : '';
		$away_name = isset( $item['away_team']['name'] ) ? (string) $item['away_team']['name'] : '';

		ob_start();
		?>
		<div class="wsh-aine-live-extras">
			<?php if ( ! empty( $stats ) ) : ?>
				<div class="wsh-aine-live-extras__block">
					<div class="wsh-aine-live-extras__title">Statistika</div>
					<table class="wsh-aine-live-stats-table">
						<thead>
							<tr>
								<th><?php echo esc_html( $home_name ); ?></th>
								<th></th>
								<th><?php echo esc_html( $away_name ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $stats as $row ) : ?>
								<tr>
									<td><?php echo esc_html( (string) ( $row['home'] ?? '-' ) ); ?></td>
									<td><?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?></td>
									<td><?php echo esc_html( (string) ( $row['away'] ?? '-' ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $goals ) ) : ?>
				<div class="wsh-aine-live-extras__block">
					<div class="wsh-aine-live-extras__title">Strelci</div>
					<ul class="wsh-aine-live-extras__list">
						<?php foreach ( $goals as $goal ) : ?>
							<li>
								<?php
								$parts = array_filter(
									array(
										isset( $goal['minute'] ) ? (string) $goal['minute'] : '',
										isset( $goal['player'] ) ? (string) $goal['player'] : '',
										isset( $goal['team'] ) ? '(' . (string) $goal['team'] . ')' : '',
										isset( $goal['label'] ) ? '— ' . (string) $goal['label'] : '',
									)
								);
								echo esc_html( implode( ' ', $parts ) );
								?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $cards ) ) : ?>
				<div class="wsh-aine-live-extras__block">
					<div class="wsh-aine-live-extras__title">Kartoni</div>
					<ul class="wsh-aine-live-extras__list">
						<?php foreach ( $cards as $card ) : ?>
							<li>
								<?php
								$parts = array_filter(
									array(
										isset( $card['minute'] ) ? (string) $card['minute'] : '',
										isset( $card['label'] ) ? (string) $card['label'] : '',
										isset( $card['player'] ) ? (string) $card['player'] : '',
										isset( $card['team'] ) ? '(' . (string) $card['team'] . ')' : '',
									)
								);
								echo esc_html( implode( ' ', $parts ) );
								?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	protected static function render_match_table( array $item ): string {
		$home_name   = isset( $item['home_team']['name'] ) ? (string) $item['home_team']['name'] : '';
		$home_logo   = isset( $item['home_team']['logo'] ) ? (string) $item['home_team']['logo'] : '';
		$away_name   = isset( $item['away_team']['name'] ) ? (string) $item['away_team']['name'] : '';
		$away_logo   = isset( $item['away_team']['logo'] ) ? (string) $item['away_team']['logo'] : '';
		$score       = isset( $item['score'] ) ? (string) $item['score'] : '-';
		$status_long = isset( $item['status_long'] ) ? (string) $item['status_long'] : '';
		$status      = isset( $item['status'] ) ? (string) $item['status'] : '';
		$time        = isset( $item['time'] ) ? (string) $item['time'] : '';
		$league_name = isset( $item['league_name'] ) ? (string) $item['league_name'] : '';
		$round       = isset( $item['round'] ) ? (string) $item['round'] : '';
		$venue_name  = isset( $item['venue_name'] ) ? (string) $item['venue_name'] : '';

		ob_start();
		?>
		<table class="wsh-sport-result-table wsh-aine-live-match" style="width:100%; border-collapse:collapse; margin:0 0 20px; border:1px solid #dcdcde; background:#fff;">
			<tr>
				<td style="width:30%; padding:16px; text-align:center; vertical-align:middle; color:#000;">
					<?php if ( $home_logo ) : ?>
						<img src="<?php echo esc_url( $home_logo ); ?>" alt="<?php echo esc_attr( $home_name ); ?>" style="max-width:56px; height:auto; display:block; margin:0 auto 8px;" />
					<?php endif; ?>
					<div style="font-size:18px; font-weight:600;"><?php echo esc_html( $home_name ); ?></div>
				</td>
				<td style="width:40%; padding:16px; text-align:center; vertical-align:middle; color:#000;">
					<div style="font-size:32px; font-weight:700; line-height:1.2;"><?php echo esc_html( $score ); ?></div>
					<div style="font-size:15px; color:#555; margin-top:8px;"><?php echo esc_html( $status_long ? $status_long : $status ); ?></div>
					<?php if ( $time ) : ?>
						<div style="font-size:14px; color:#666; margin-top:6px;"><?php echo esc_html( $time ); ?></div>
					<?php endif; ?>
				</td>
				<td style="width:30%; padding:16px; text-align:center; vertical-align:middle; color:#000;">
					<?php if ( $away_logo ) : ?>
						<img src="<?php echo esc_url( $away_logo ); ?>" alt="<?php echo esc_attr( $away_name ); ?>" style="max-width:56px; height:auto; display:block; margin:0 auto 8px;" />
					<?php endif; ?>
					<div style="font-size:18px; font-weight:600;"><?php echo esc_html( $away_name ); ?></div>
				</td>
			</tr>
			<tr>
				<td colspan="3" style="padding:12px 16px; border-top:1px solid #dcdcde; font-size:14px; line-height:1.6; text-align:center; color:#000;">
					<strong><?php esc_html_e( 'League:', 'wsh-ai-news-editor' ); ?></strong>
					<?php echo esc_html( $league_name ? $league_name : '-' ); ?>
					<?php if ( $round ) : ?>
						| <strong><?php esc_html_e( 'Round:', 'wsh-ai-news-editor' ); ?></strong> <?php echo esc_html( $round ); ?>
					<?php endif; ?>
					<?php if ( $venue_name ) : ?>
						| <strong><?php esc_html_e( 'Venue:', 'wsh-ai-news-editor' ); ?></strong> <?php echo esc_html( $venue_name ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php

		return (string) ob_get_clean();
	}
}
