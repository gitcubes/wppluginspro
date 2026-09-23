<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Round_Fixtures_Shortcode {

	public static function init(): void {
		add_shortcode( 'wsh_round_fixtures', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'sport'     => 'football',
				'league_id' => '',
				'season'    => '',
				'round'     => '',
				'title'     => '1',
			),
			(array) $atts,
			'wsh_round_fixtures'
		);

		$sport      = sanitize_key( (string) $atts['sport'] );
		$league_id  = absint( $atts['league_id'] );
		$season     = sanitize_text_field( (string) $atts['season'] );
		$round      = sanitize_text_field( (string) $atts['round'] );
		$show_title = '1' === (string) $atts['title'];

		if ( 'football' !== $sport ) {
			return '<p>' . esc_html__( 'Round fixtures shortcode currently supports football only.', 'wsh-ai-news-editor' ) . '</p>';
		}

		if ( ! $league_id || '' === $season || '' === $round ) {
			return '<p>' . esc_html__( 'Round fixtures shortcode is missing required parameters.', 'wsh-ai-news-editor' ) . '</p>';
		}

		$options = get_option( 'wsh_aine_settings', array() );

		$result = WSH_AINE_API_Sports_Fetcher::get_round_fixtures_by_params(
			$sport,
			$league_id,
			$season,
			$round,
			$options,
			false
		);

		if ( is_wp_error( $result ) ) {
			return '<p>' . esc_html( $result->get_error_message() ) . '</p>';
		}

		if ( empty( $result['items'] ) || ! is_array( $result['items'] ) ) {
			return '<p>' . esc_html__( 'No fixtures found for this round.', 'wsh-ai-news-editor' ) . '</p>';
		}

		ob_start();

		$league_name = isset( $result['league_name'] ) ? (string) $result['league_name'] : '';
		$round_name  = isset( $result['round'] ) ? (string) $result['round'] : '';
		?>
		<div class="wsh-aine-round-fixtures-shortcode">
			<?php if ( $show_title ) : ?>
				<div style="margin:0 0 14px;">
					<h3 style="margin:0 0 4px;"><?php echo esc_html( $league_name ); ?></h3>
					<div style="color:#666; font-size:14px;"><?php echo esc_html( $round_name ); ?></div>
				</div>
			<?php endif; ?>

			<div style="display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:0; border:1px solid #dcdcde; background:#fff;">
				<?php foreach ( $result['items'] as $item ) : ?>
					<?php
					$home_name   = isset( $item['home_team']['name'] ) ? (string) $item['home_team']['name'] : '';
					$home_logo   = isset( $item['home_team']['logo'] ) ? (string) $item['home_team']['logo'] : '';
					$away_name   = isset( $item['away_team']['name'] ) ? (string) $item['away_team']['name'] : '';
					$away_logo   = isset( $item['away_team']['logo'] ) ? (string) $item['away_team']['logo'] : '';
					$date_label  = isset( $item['date_label'] ) ? (string) $item['date_label'] : '';
					$status_long = isset( $item['status_long'] ) ? (string) $item['status_long'] : '';
                    $score = isset( $item['score'] ) ? (string) $item['score'] : '';
					$status      = isset( $item['status'] ) ? (string) $item['status'] : '';
					?>
					<div style="padding:20px; border-top:1px solid #e5e5e5; border-right:1px solid #e5e5e5;">
						<div style="display:grid; grid-template-columns:minmax(0,1fr) 160px; gap:18px; align-items:center;">
							<div>
								<div style="display:flex; align-items:center; gap:12px; margin-bottom:14px; color: #000;">
									<?php if ( $home_logo ) : ?>
										<img src="<?php echo esc_url( $home_logo ); ?>" alt="<?php echo esc_attr( $home_name ); ?>" style="width:28px; height:28px; object-fit:contain;" />
									<?php endif; ?>
									<span style="font-size:16px; font-weight:500;"><?php echo esc_html( $home_name ); ?></span>
								</div>

								<div style="display:flex; align-items:center; gap:12px;  color: #000;">
									<?php if ( $away_logo ) : ?>
										<img src="<?php echo esc_url( $away_logo ); ?>" alt="<?php echo esc_attr( $away_name ); ?>" style="width:28px; height:28px; object-fit:contain;" />
									<?php endif; ?>
									<span style="font-size:16px; font-weight:500;"><?php echo esc_html( $away_name ); ?></span>
								</div>
							</div>

							<div style="border-left:1px solid #ddd; text-align:center; padding-left:18px;  color: #000;">
								<div style="font-size:16px; font-weight:600; margin-bottom:6px;"><?php echo esc_html( $date_label ); ?></div>
								<div style="font-size:15px; color:#444;">
                                    <?php echo esc_html( $status_long ? $status_long : $status ); ?><br>
                                    <strong><?php echo esc_html( $score ? $score : '-' ); ?></strong>
                                </div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}