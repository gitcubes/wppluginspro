<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Standings_Shortcode {

	public static function init(): void {
		add_shortcode( 'wsh_standings', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'sport'     => 'football',
				'league_id' => '',
				'season'    => '',
				'title'     => '1',
			),
			(array) $atts,
			'wsh_standings'
		);

		$sport      = sanitize_key( (string) $atts['sport'] );
		$league_id  = absint( $atts['league_id'] );
		$season     = sanitize_text_field( (string) $atts['season'] );
		$show_title = '1' === (string) $atts['title'];

		if ( ! $league_id || '' === $season ) {
			return '<p>' . esc_html__( 'Standings shortcode is missing required parameters.', 'wsh-ai-news-editor' ) . '</p>';
		}

		$options = get_option( 'wsh_aine_settings', array() );

		$result = WSH_AINE_API_Sports_Fetcher::get_standings_by_params(
			$sport,
			$league_id,
			$season,
			$options,
			false
		);

		if ( is_wp_error( $result ) ) {
			return '<p>' . esc_html( $result->get_error_message() ) . '</p>';
		}

		if ( empty( $result['items'] ) || ! is_array( $result['items'] ) ) {
			return '<p>' . esc_html__( 'No standings found.', 'wsh-ai-news-editor' ) . '</p>';
		}

		$league_name = isset( $result['league_name'] ) ? (string) $result['league_name'] : '';
		$items       = $result['items'];

		$groups = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$group = '';

			if ( ! empty( $item['group'] ) ) {
				$group = trim( (string) $item['group'] );
			}

			if ( $group ) {
				$groups[ $group ][] = $item;
			} else {
				$groups['default'][] = $item;
			}
		}

		$multiple_groups = count( $groups ) > 1;

		ob_start();
		?>
		<div class="wsh-aine-standings-shortcode">
			<?php if ( $show_title ) : ?>
				<div style="margin:0 0 14px;">
					<?php if ( $league_name ) : ?>
						<h3 style="margin:0 0 4px;"><?php echo esc_html( $league_name ); ?></h3>
					<?php endif; ?>
					<div style="color:#666; font-size:14px;"><?php esc_html_e( 'Standings', 'wsh-ai-news-editor' ); ?></div>
				</div>
			<?php endif; ?>

			<?php foreach ( $groups as $group_name => $group_items ) : ?>

				<?php if ( $multiple_groups && 'default' !== $group_name ) : ?>
					<h4 style="margin:22px 0 10px; padding:8px 12px; background:#f6f7f7; border-left:4px solid #2271b1; color:#111;">
						<?php echo esc_html( $group_name ); ?>
					</h4>
				<?php endif; ?>

				<div style="overflow-x:auto; margin-bottom:24px;">
					<table style="width:100%; border-collapse:collapse; background:#fff; border:1px solid #dcdcde;">
						<thead>
							<tr style="background:#f6f7f7;">
								<th style="padding:10px; text-align:left; color:#000;"><?php esc_html_e( '#', 'wsh-ai-news-editor' ); ?></th>
								<th style="padding:10px; text-align:left; color:#000;"><?php esc_html_e( 'Team', 'wsh-ai-news-editor' ); ?></th>
								<th style="padding:10px; text-align:center; color:#000;">P</th>
								<th style="padding:10px; text-align:center; color:#000;">W</th>
								<th style="padding:10px; text-align:center; color:#000;">D</th>
								<th style="padding:10px; text-align:center; color:#000;">L</th>
								<th style="padding:10px; text-align:center; color:#000;">GD</th>
								<th style="padding:10px; text-align:center; color:#000;">PTS</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $group_items as $row ) : ?>
								<?php
								if ( ! is_array( $row ) ) {
									continue;
								}

								$rank   = isset( $row['rank'] ) ? (int) $row['rank'] : 0;
								$name   = isset( $row['team']['name'] ) ? (string) $row['team']['name'] : '';
								$logo   = isset( $row['team']['logo'] ) ? (string) $row['team']['logo'] : '';
								$played = isset( $row['all']['played'] ) ? (int) $row['all']['played'] : 0;
								$win    = isset( $row['all']['win'] ) ? (int) $row['all']['win'] : 0;
								$draw   = isset( $row['all']['draw'] ) ? (int) $row['all']['draw'] : 0;
								$lose   = isset( $row['all']['lose'] ) ? (int) $row['all']['lose'] : 0;
								$gd     = isset( $row['goalsDiff'] ) ? (int) $row['goalsDiff'] : 0;
								$pts    = isset( $row['points'] ) ? (int) $row['points'] : 0;

								$row_bg = '';

								if ( $rank > 0 && $rank <= 4 ) {
									$row_bg = 'background:#f6fff8;';
								}
								?>
								<tr style="border-top:1px solid #eee; <?php echo esc_attr( $row_bg ); ?>">
									<td style="padding:10px; color:#000;"><?php echo esc_html( (string) $rank ); ?></td>

									<td style="padding:10px; color:#000;">
										<div style="display:flex; align-items:center; gap:10px; color:#000;">
											<?php if ( $logo ) : ?>
												<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $name ); ?>" style="width:24px; height:24px; object-fit:contain;" />
											<?php endif; ?>
											<span><?php echo esc_html( $name ); ?></span>
										</div>
									</td>

									<td style="padding:10px; text-align:center; color:#000;"><?php echo esc_html( (string) $played ); ?></td>
									<td style="padding:10px; text-align:center; color:#000;"><?php echo esc_html( (string) $win ); ?></td>
									<td style="padding:10px; text-align:center; color:#000;"><?php echo esc_html( (string) $draw ); ?></td>
									<td style="padding:10px; text-align:center; color:#000;"><?php echo esc_html( (string) $lose ); ?></td>
									<td style="padding:10px; text-align:center; color:#000;"><?php echo esc_html( (string) $gd ); ?></td>
									<td style="padding:10px; text-align:center; color:#000; font-weight:700;"><?php echo esc_html( (string) $pts ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

			<?php endforeach; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}