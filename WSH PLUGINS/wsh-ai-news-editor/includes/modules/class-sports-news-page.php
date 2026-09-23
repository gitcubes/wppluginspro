<?php
if (! defined('ABSPATH')) {
	exit;
}

class WSH_AINE_Sports_News_Page
{

	public static function init(): void
	{
		add_action(
			'admin_post_wsh_aine_prepare_sports_ai',
			array(__CLASS__, 'handle_prepare_ai')
		);
	}

	public static function render(): void
	{
		if (! current_user_can('edit_posts')) {
			wp_die(esc_html__('Forbidden', 'wsh-ai-news-editor'));
		}

		$module = WSH_AINE_Module_Registry::get_module('wsh-ai-news-editor-sports-news');
		if ( ! WSH_AINE_Access::can_access_module($module) ) {
			WSH_AINE_Admin_Notices::locked_module('Sports News', 'PRO');
			return;
		}

		$options       = get_option('wsh_aine_settings', array());
		$config        = WSH_AINE_API_Sports_Fetcher::get_supported_sports_config();
		$sport         = isset($_GET['sports_sport']) ? sanitize_key(wp_unslash($_GET['sports_sport'])) : 'football';
		$league        = isset($_GET['sports_league']) ? sanitize_key(wp_unslash($_GET['sports_league'])) : '';
		$mode          = isset($_GET['sports_mode']) ? sanitize_key(wp_unslash($_GET['sports_mode'])) : 'results';
		$date          = isset($_GET['sports_date']) ? sanitize_text_field(wp_unslash($_GET['sports_date'])) : gmdate('Y-m-d');
		$run_request   = isset($_GET['sports_run']) ? absint($_GET['sports_run']) : 0;
		$force_refresh = isset($_GET['sports_refresh']) ? absint($_GET['sports_refresh']) : 0;
		$result        = null;
		$error         = '';

		if (empty($config[$sport])) {
			$sport = 'football';
		}

		$leagues = $config[$sport]['leagues'];

		if ('' === $league || empty($leagues[$league])) {
			$league_keys = array_keys($leagues);
			$league      = isset($league_keys[0]) ? $league_keys[0] : '';
		}

		if (! in_array($mode, array('live', 'fixtures', 'results', 'standings'), true)) {
			$mode = 'results';
		}

		if ($run_request && $league) {
			$result = WSH_AINE_API_Sports_Fetcher::get_events(
				$sport,
				$league,
				$mode,
				$options,
				$date,
				(bool) $force_refresh
			);

			if (is_wp_error($result)) {
				$error  = $result->get_error_message();
				$result = null;
			}
		}
	?>
		<div class="wrap">
			<h1><?php esc_html_e('Sports News', 'wsh-ai-news-editor'); ?></h1>
			<p><?php esc_html_e('Load sports data from API-SPORTS and turn it into an AI news draft.', 'wsh-ai-news-editor'); ?></p>

			<?php if (empty($options['api_sports_api_key'])) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e('API-SPORTS API key is not configured. Go to Settings first.', 'wsh-ai-news-editor'); ?></p>
				</div>
			<?php endif; ?>

			<form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="wsh-aine-sports-filters">
				<input type="hidden" name="page" value="wsh-ai-news-editor-sports-news" />
				<input type="hidden" name="sports_run" value="1" />

				<div class="wsh-aine-sports-filters__row">
					<div>
						<label for="sports_sport"><strong><?php esc_html_e('Sport', 'wsh-ai-news-editor'); ?></strong></label><br />
						<select name="sports_sport" id="sports_sport" onchange="this.form.submit()">
							<?php foreach ($config as $sport_key => $sport_config) : ?>
								<option value="<?php echo esc_attr($sport_key); ?>" <?php selected($sport, $sport_key); ?>>
									<?php echo esc_html($sport_config['label']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div>
						<label for="sports_league"><strong><?php esc_html_e('League', 'wsh-ai-news-editor'); ?></strong></label><br />
						<select name="sports_league" id="sports_league">
							<?php foreach ($leagues as $league_key => $league_config) : ?>
								<option value="<?php echo esc_attr($league_key); ?>" <?php selected($league, $league_key); ?>>
									<?php echo esc_html($league_config['label']); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div>
						<label for="sports_mode"><strong><?php esc_html_e('Mode', 'wsh-ai-news-editor'); ?></strong></label><br />
						<select name="sports_mode" id="sports_mode">
							<option value="live" <?php selected($mode, 'live'); ?>><?php esc_html_e('Live', 'wsh-ai-news-editor'); ?></option>
							<option value="fixtures" <?php selected($mode, 'fixtures'); ?>><?php esc_html_e('Fixtures', 'wsh-ai-news-editor'); ?></option>
							<option value="results" <?php selected($mode, 'results'); ?>><?php esc_html_e('Results', 'wsh-ai-news-editor'); ?></option>
							<option value="standings" <?php selected($mode, 'standings'); ?>><?php esc_html_e('Standings', 'wsh-ai-news-editor'); ?></option>
						</select>
					</div>

					<div>
						<label for="sports_date"><strong><?php esc_html_e('Date', 'wsh-ai-news-editor'); ?></strong></label><br />
						<input type="date" name="sports_date" id="sports_date" value="<?php echo esc_attr($date); ?>" />
					</div>

					<div>
						<button type="submit" class="button button-primary">
							<?php esc_html_e('Load Sports Data', 'wsh-ai-news-editor'); ?>
						</button>
					</div>

					<?php if ($run_request) : ?>
						<div>
							<a
								class="button"
								href="<?php echo esc_url(
											add_query_arg(
												array(
													'page'           => 'wsh-ai-news-editor-sports-news',
													'sports_sport'   => $sport,
													'sports_league'  => $league,
													'sports_mode'    => $mode,
													'sports_date'    => $date,
													'sports_run'     => 1,
													'sports_refresh' => 1,
												),
												admin_url('admin.php')
											)
										); ?>">
								<?php esc_html_e('Refresh', 'wsh-ai-news-editor'); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>

				<p class="description wsh-aine-sports-filters__help">
					<?php esc_html_e('Used for Results and Fixtures. If that day has no matches, latest season events are loaded instead. Live and Standings ignore the date.', 'wsh-ai-news-editor'); ?>
				</p>
			</form>

			<?php if (! $run_request) : ?>
				<div class="notice notice-info" style="margin-top:20px;">
					<p><?php esc_html_e('Select a sport, league, mode and date, then click "Load Sports Data".', 'wsh-ai-news-editor'); ?></p>
				</div>
			<?php endif; ?>

			<?php if ($error) : ?>
				<div class="notice notice-error" style="margin-top:20px;">
					<p><?php echo esc_html($error); ?></p>
				</div>
			<?php endif; ?>

			<?php if (is_array($result)) : ?>
				<div class="wsh-aine-panel" style="margin-top:20px;">
					<h2><?php esc_html_e('Sports Summary', 'wsh-ai-news-editor'); ?></h2>

					<p style="margin-top:6px; color:#666;">
						<strong><?php esc_html_e('Sport:', 'wsh-ai-news-editor'); ?></strong>
						<?php echo esc_html($result['sport_label'] ?? ''); ?>
						&nbsp; | &nbsp;
						<strong><?php esc_html_e('League:', 'wsh-ai-news-editor'); ?></strong>
						<?php echo esc_html($result['league_label'] ?? ''); ?>
						&nbsp; | &nbsp;
						<strong><?php esc_html_e('Mode:', 'wsh-ai-news-editor'); ?></strong>
						<?php echo esc_html(ucfirst((string) ($result['mode'] ?? ''))); ?>
						&nbsp; | &nbsp;
						<strong><?php esc_html_e('Season:', 'wsh-ai-news-editor'); ?></strong>
						<?php echo esc_html((string) ($result['season'] ?? '')); ?>
					</p>

					<?php if ( empty( $result['items'] ) ) : ?>
						<div class="notice notice-warning inline" style="margin:12px 0;">
							<p>
								<?php esc_html_e( 'No events found for this selection. If this is a rest day, try Standings, Fixtures, or another league. World Cup has no matches outside the tournament dates.', 'wsh-ai-news-editor' ); ?>
							</p>
						</div>
					<?php endif; ?>

					<?php
					$standings_table = '';
					if (! empty($result['items']) && $mode === 'standings'){
						$standings_table = self::handle_standings_table( $result['items'] );
					}
					?>

					<p><?php echo nl2br(esc_html($result['summary'] ?? '')); ?></p>

					<?php if ( 'standings' === ( $result['mode'] ?? '' ) ) : ?>
						<div style="margin:16px 0 0; padding:12px; background:#f6f7f7; border:1px solid #dcdcde;">
							<strong><?php esc_html_e( 'Shortcode', 'wsh-ai-news-editor' ); ?>:</strong>
							<code style="display:block; margin-top:8px;">
					[wsh_standings sport="<?php echo esc_attr( $result['sport_key'] ?? 'football' ); ?>" league_id="<?php echo esc_attr( $result['league_id'] ?? '' ); ?>" season="<?php echo esc_attr( $result['season'] ?? '' ); ?>" title=""]
							</code>
						</div>

					<div style="margin:12px 0 0; padding:12px; background:#f6f7f7; border:1px solid #dcdcde;">
							<strong><?php esc_html_e( 'Round fixtures shortcode example', 'wsh-ai-news-editor' ); ?>:</strong>
							<code style="display:block; margin-top:8px;">
					[wsh_round_fixtures sport="<?php echo esc_attr( $result['sport_key'] ?? 'football' ); ?>" league_id="<?php echo esc_attr( $result['league_id'] ?? '' ); ?>" season="<?php echo esc_attr( $result['season'] ?? '' ); ?>" round="Regular Season - 37" title=""]
							</code>
						</div>
					<?php endif; ?>

					<?php if ( 'live' === ( $result['mode'] ?? '' ) ) : ?>
						<div style="margin:16px 0 0; padding:12px; background:#f6f7f7; border:1px solid #dcdcde;">
							<strong><?php esc_html_e( 'League live shortcode', 'wsh-ai-news-editor' ); ?>:</strong>
							<p class="description" style="margin:6px 0 8px;">
								<?php esc_html_e( 'Paste this into a post to show all current live scores for this league. The widget refreshes automatically.', 'wsh-ai-news-editor' ); ?>
							</p>
							<code style="display:block; margin-top:8px;">
					[wsh_live_score sport="<?php echo esc_attr( $result['sport_key'] ?? 'football' ); ?>" league_id="<?php echo esc_attr( (string) ( $result['league_id'] ?? '' ) ); ?>" title=""]
							</code>
						</div>
					<?php endif; ?>

					<?php
					if(!empty($standings_table)){
						echo $standings_table;
					}
					?>

					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;">
						<?php wp_nonce_field('wsh_aine_prepare_sports_ai'); ?>
						<input type="hidden" name="action" value="wsh_aine_prepare_sports_ai" />
						<input type="hidden" name="sports_query" value="<?php echo esc_attr($result['query'] ?? ''); ?>" />
						<input type="hidden" name="sports_summary" value="<?php echo esc_attr($result['summary'] ?? ''); ?>" />
						<input type="hidden" name="sports_items_json" value="<?php echo esc_attr(wp_json_encode($result['items'] ?? array())); ?>" />
						<input type="hidden" name="sports_sport" value="<?php echo esc_attr($result['sport_key'] ?? ''); ?>" />
						<input type="hidden" name="sports_league" value="<?php echo esc_attr($result['league_key'] ?? ''); ?>" />
						<input type="hidden" name="sports_mode" value="<?php echo esc_attr($result['mode'] ?? ''); ?>" />

						<button type="submit" class="button button-primary">
							<?php esc_html_e('Generate AI news from all results', 'wsh-ai-news-editor'); ?>
						</button>
					</form>
				</div>

				<?php if (! empty($result['items']) && $mode !== 'standings') : ?>
					<div class="wsh-aine-news-list" style="margin-top:20px;">
						<?php foreach ($result['items'] as $item) : ?>
							<div class="wsh-aine-news-card wsh-aine-news-card--match">
								<div class="wsh-aine-news-card__body" style="padding-left:0;">
									<div class="wsh-aine-news-card__top">
										<span class="wsh-aine-badge"><?php echo esc_html($item['source'] ?? 'API-SPORTS'); ?></span>
									</div>

									<h3 class="wsh-aine-news-card__title"><?php echo esc_html($item['title'] ?? ''); ?></h3>

									<!-- Logo results -->
									<div style="display:flex; align-items:center; justify-content:space-between; gap:20px; margin:12px 0 16px; text-align:center;">
										<div style="flex:1;">
											<?php if (! empty($item['home_team']['logo'])) : ?>
												<div style="margin-bottom:8px;">
													<img src="<?php echo esc_url($item['home_team']['logo']); ?>" alt="<?php echo esc_attr($item['home_team']['name'] ?? ''); ?>" style="max-width:56px; height:auto;" />
												</div>
											<?php endif; ?>
											<div style="font-weight:600;">
												<?php echo esc_html($item['home_team']['name'] ?? ''); ?>
											</div>
										</div>

										<div style="min-width:140px;">
											<div style="font-size:22px; font-weight:700; margin-bottom:6px;">
												<?php echo esc_html($item['score'] ?? '-'); ?>
											</div>
											<div style="font-size:12px; color:#666;">
												<?php echo esc_html($item['status_long'] ?? ($item['status'] ?? '')); ?>
											</div>
											<?php if (! empty($item['time'])) : ?>
												<div style="font-size:12px; color:#666; margin-top:4px;">
													<?php echo esc_html($item['time']); ?>
												</div>
											<?php endif; ?>
										</div>

										<div style="flex:1;">
											<?php if (! empty($item['away_team']['logo'])) : ?>
												<div style="margin-bottom:8px;">
													<img src="<?php echo esc_url($item['away_team']['logo']); ?>" alt="<?php echo esc_attr($item['away_team']['name'] ?? ''); ?>" style="max-width:56px; height:auto;" />
												</div>
											<?php endif; ?>
											<div style="font-weight:600;">
												<?php echo esc_html($item['away_team']['name'] ?? ''); ?>
											</div>
										</div>
									</div>

									<?php
									if ( 'live' === ( $result['mode'] ?? '' ) ) {
										echo WSH_AINE_Live_Score_Shortcode::render_match_extras( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									}

									$match_shortcode = self::get_match_live_shortcode(
										(string) ( $result['sport_key'] ?? 'football' ),
										$item
									);
									if ( 'live' === ( $result['mode'] ?? '' ) && '' !== $match_shortcode ) :
										?>
										<div style="margin:0 0 16px; padding:12px; background:#f6f7f7; border:1px solid #dcdcde;">
											<strong><?php esc_html_e( 'Match live shortcode', 'wsh-ai-news-editor' ); ?>:</strong>
											<p class="description" style="margin:6px 0 8px;">
												<?php esc_html_e( 'Paste this into a post to keep only this match score updating live.', 'wsh-ai-news-editor' ); ?>
											</p>
											<code style="display:block;"><?php echo esc_html( $match_shortcode ); ?></code>
										</div>
									<?php endif; ?>

									<!-- League block -->
									<p class="wsh-aine-news-card__excerpt" style="margin-bottom:8px;">
										<strong><?php esc_html_e('League:', 'wsh-ai-news-editor'); ?></strong>
										<?php echo esc_html($item['league_name'] ?? '-'); ?>

										<?php if (! empty($item['round'])) : ?>
											&nbsp; | &nbsp;
											<strong><?php esc_html_e('Round:', 'wsh-ai-news-editor'); ?></strong>
											<?php echo esc_html($item['round']); ?>
										<?php endif; ?>

										<?php if (! empty($item['venue_name'])) : ?>
											&nbsp; | &nbsp;
											<strong><?php esc_html_e('Venue:', 'wsh-ai-news-editor'); ?></strong>
											<?php echo esc_html($item['venue_name']); ?>
										<?php endif; ?>
									</p>

									<!-- Snippet -->
									<?php if (! empty($item['snippet'])) : ?>
										<p class="wsh-aine-news-card__excerpt"><?php echo esc_html($item['snippet']); ?></p>
									<?php endif; ?>

									<p class="wsh-aine-news-card__excerpt" style="margin-bottom:8px;">
										<strong><?php esc_html_e('Score:', 'wsh-ai-news-editor'); ?></strong>
										<?php echo esc_html($item['score'] ?? '-'); ?>
										&nbsp; | &nbsp;
										<strong><?php esc_html_e('Status:', 'wsh-ai-news-editor'); ?></strong>
										<?php echo esc_html($item['status'] ?? '-'); ?>
										<?php if (! empty($item['time'])) : ?>
											&nbsp; | &nbsp;
											<strong><?php esc_html_e('Time:', 'wsh-ai-news-editor'); ?></strong>
											<?php echo esc_html($item['time']); ?>
										<?php endif; ?>
									</p>

									<?php if (! empty($item['snippet'])) : ?>
										<p class="wsh-aine-news-card__excerpt"><?php echo esc_html($item['snippet']); ?></p>
									<?php endif; ?>

									<?php
									$item_summary = trim(
										(! empty($result['summary']) ? $result['summary'] . "\n\n" : '') .
											($item['snippet'] ?? '')
									);
									?>

									<div class="wsh-aine-news-card__actions" style="display:flex; gap:10px; flex-wrap:wrap;">
										<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
											<?php wp_nonce_field('wsh_aine_prepare_sports_ai'); ?>
											<input type="hidden" name="action" value="wsh_aine_prepare_sports_ai" />
											<input type="hidden" name="sports_query" value="<?php echo esc_attr($result['query'] ?? ''); ?>" />
											<input type="hidden" name="sports_summary" value="<?php echo esc_attr($item_summary); ?>" />
											<input type="hidden" name="sports_items_json" value="<?php echo esc_attr(wp_json_encode(array($item))); ?>" />
											<input type="hidden" name="sports_sport" value="<?php echo esc_attr($result['sport_key'] ?? ''); ?>" />
											<input type="hidden" name="sports_league" value="<?php echo esc_attr($result['league_key'] ?? ''); ?>" />
											<input type="hidden" name="sports_mode" value="<?php echo esc_attr($result['mode'] ?? ''); ?>" />
											<button type="submit" class="button button-primary">
												<?php esc_html_e('Generate AI news', 'wsh-ai-news-editor'); ?>
											</button>
										</form>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="notice notice-info" style="margin-top:20px;">
						<p><?php esc_html_e('No events found for the selected filters.', 'wsh-ai-news-editor'); ?></p>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php
	}

	public static function handle_prepare_aiBKP(): void
	{
		if (! current_user_can('edit_posts')) {
			wp_die(esc_html__('Forbidden', 'wsh-ai-news-editor'));
		}

		check_admin_referer('wsh_aine_prepare_sports_ai');

		$query      = isset($_POST['sports_query']) ? sanitize_text_field(wp_unslash($_POST['sports_query'])) : '';
		$summary    = isset($_POST['sports_summary']) ? sanitize_textarea_field(wp_unslash($_POST['sports_summary'])) : '';
		$items_json = isset($_POST['sports_items_json']) ? wp_unslash($_POST['sports_items_json']) : '[]';
		$items      = json_decode($items_json, true);

		if (! is_array($items)) {
			$items = array();
		}

		$lines = array();

		foreach ($items as $item) {
			if (! is_array($item)) {
				continue;
			}


			$title   = isset($item['title']) ? sanitize_text_field((string) $item['title']) : '';
			$score   = isset($item['score']) ? sanitize_text_field((string) $item['score']) : '';
			$status  = isset($item['status']) ? sanitize_text_field((string) $item['status']) : '';
			$time    = isset($item['time']) ? sanitize_text_field((string) $item['time']) : '';
			$source  = isset($item['source']) ? sanitize_text_field((string) $item['source']) : 'API-SPORTS';
			$snippet = isset($item['snippet']) ? sanitize_textarea_field((string) $item['snippet']) : '';

			$home_team   = isset($item['home_team']['name']) ? sanitize_text_field((string) $item['home_team']['name']) : '';
			$away_team   = isset($item['away_team']['name']) ? sanitize_text_field((string) $item['away_team']['name']) : '';
			$league_name = isset($item['league_name']) ? sanitize_text_field((string) $item['league_name']) : '';
			$country     = isset($item['country_name']) ? sanitize_text_field((string) $item['country_name']) : '';
			$season      = isset($item['season']) ? sanitize_text_field((string) $item['season']) : '';
			$round       = isset($item['round']) ? sanitize_text_field((string) $item['round']) : '';
			$date        = isset($item['date']) ? sanitize_text_field((string) $item['date']) : '';
			$venue_name  = isset($item['venue_name']) ? sanitize_text_field((string) $item['venue_name']) : '';
			$venue_city  = isset($item['venue_city']) ? sanitize_text_field((string) $item['venue_city']) : '';
			$referee     = isset($item['referee']) ? sanitize_text_field((string) $item['referee']) : '';

			$block = array_filter(
				array(
					$title,
					($home_team || $away_team) ? 'Teams: ' . trim($home_team . ' vs ' . $away_team) : '',
					$score ? 'Score: ' . $score : '',
					$status ? 'Status: ' . $status : '',
					$time ? 'Time: ' . $time : '',
					$league_name ? 'League: ' . $league_name : '',
					$country ? 'Country: ' . $country : '',
					$season ? 'Season: ' . $season : '',
					$round ? 'Round: ' . $round : '',
					$date ? 'Date: ' . $date : '',
					$venue_name ? 'Venue: ' . $venue_name : '',
					$venue_city ? 'City: ' . $venue_city : '',
					$referee ? 'Referee: ' . $referee : '',
					$source ? 'Source: ' . $source : '',
					$snippet,
				)
			);

			if (! empty($block)) {
				$lines[] = implode("\n", $block);
			}
		}

		$origin_text = "Sports topic: {$query}\n\nSummary:\n{$summary}";

		if (! empty($lines)) {
			$origin_text .= "\n\nRelevant events:\n\n" . implode("\n\n---\n\n", $lines);
		}


		//Start HTML table
		$origin_html_parts   = array();
		/*$origin_html_parts[] = '<div class="wsh-aine-sports-source">';

		if ( $query ) {
			$origin_html_parts[] = '<h3 style="margin:0 0 12px;">' . esc_html( $query ) . '</h3>';
		}

		if ( $summary ) {
			$origin_html_parts[] = '<p style="margin:0 0 16px;">' . nl2br( esc_html( $summary ) ) . '</p>';
		}*/

		foreach ($items as $item) {
			if (! is_array($item)) {
				continue;
			}

			$home_name   = isset($item['home_team']['name']) ? sanitize_text_field((string) $item['home_team']['name']) : '';
			$home_logo   = isset($item['home_team']['logo']) ? esc_url((string) $item['home_team']['logo']) : '';
			$away_name   = isset($item['away_team']['name']) ? sanitize_text_field((string) $item['away_team']['name']) : '';
			$away_logo   = isset($item['away_team']['logo']) ? esc_url((string) $item['away_team']['logo']) : '';
			$score       = isset($item['score']) ? sanitize_text_field((string) $item['score']) : '-';
			$status_long = isset($item['status_long']) ? sanitize_text_field((string) $item['status_long']) : '';
			$status      = isset($item['status']) ? sanitize_text_field((string) $item['status']) : '';
			$time        = isset($item['time']) ? sanitize_text_field((string) $item['time']) : '';
			$league_name = isset($item['league_name']) ? sanitize_text_field((string) $item['league_name']) : '';
			$round       = isset($item['round']) ? sanitize_text_field((string) $item['round']) : '';
			$venue_name  = isset($item['venue_name']) ? sanitize_text_field((string) $item['venue_name']) : '';
			$snippet     = isset($item['snippet']) ? sanitize_textarea_field((string) $item['snippet']) : '';

			$origin_html_parts[] = '
			<table class="wsh-sport-result-table" style="width:100%; border-collapse:collapse; margin:0 0 20px; border:1px solid #dcdcde; background:#fff;">
				<tr>
					<td style="width:30%; padding:16px; text-align:center; vertical-align:middle; color: #000;">
						' . ($home_logo ? '<img src="' . $home_logo . '" alt="' . esc_attr($home_name) . '" style="max-width:56px; height:auto; display:block; margin:0 auto 8px;" />' : '') . '
						<div style="font-size:18px; font-weight:600;">' . esc_html($home_name) . '</div>
					</td>

					<td style="width:40%; padding:16px; text-align:center; vertical-align:middle; color: #000;">
						<div style="font-size:32px; font-weight:700; line-height:1.2;">' . esc_html($score) . '</div>
						<div style="font-size:15px; color:#555; margin-top:8px;">' . esc_html($status_long ? $status_long : $status) . '</div>
						' . ($time ? '<div style="font-size:14px; color:#666; margin-top:6px;">' . esc_html($time) . '</div>' : '') . '
					</td>

					<td style="width:30%; padding:16px; text-align:center; vertical-align:middle; color: #000;">
						' . ($away_logo ? '<img src="' . $away_logo . '" alt="' . esc_attr($away_name) . '" style="max-width:56px; height:auto; display:block; margin:0 auto 8px;" />' : '') . '
						<div style="font-size:18px; font-weight:600;">' . esc_html($away_name) . '</div>
					</td>
				</tr>

				<tr>
					<td colspan="3" style="padding:12px 16px; border-top:1px solid #dcdcde; font-size:14px; line-height:1.6; text-align: center;  color: #000;">
						<strong>League:</strong> ' . esc_html($league_name ? $league_name : '-') . '' . ($round ? ' | <strong>Round:</strong> ' . esc_html($round) : '') . ' ' . ($venue_name ? ' | <strong>Venue:</strong> ' . esc_html($venue_name) : '') . '
					</td>
				</tr>
				
			</table>';

			/*' . ( $snippet ? '
				<tr>
					<td colspan="3" style="padding:12px 16px; border-top:1px solid #dcdcde; font-size:14px; color:#444;">
						' . esc_html( $snippet ) . '
					</td>
				</tr>' : '' ) . '*/
		}

		//$origin_html_parts[] = '</div>';
		$origin_html = implode('', $origin_html_parts);


		$payload = WSH_AINE_AI_Payload::normalize(
			array(
				'source_type'  => 'sports',
				'source_name'  => 'API-SPORTS',
				'origin_title' => $query,
				'origin_url'   => '',
				'origin_text'  => $origin_text,
				'origin_html'  => $origin_html,
				'image'        => '',
				'author_name'  => 'API-SPORTS',
				'published_at' => current_time('mysql'),
				'embed_type'   => 'sports_data',
				'embed_url'    => '',
				'extra'        => array(
					'query'   => $query,
					'summary' => $summary,
					'items'   => $items,
					'sport'   => isset($_POST['sports_sport']) ? sanitize_text_field(wp_unslash($_POST['sports_sport'])) : '',
					'league'  => isset($_POST['sports_league']) ? sanitize_text_field(wp_unslash($_POST['sports_league'])) : '',
					'mode'    => isset($_POST['sports_mode']) ? sanitize_text_field(wp_unslash($_POST['sports_mode'])) : '',
				),
			)
		);

		$token = WSH_AINE_AI_Drafts::store_seed($payload);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'wsh-ai-news-editor-ai-editor',
					'seed' => rawurlencode($token),
				),
				admin_url('admin.php')
			)
		);
		exit;
	}

	public static function handle_prepare_ai(): void
	{
		if (! current_user_can('edit_posts')) {
			wp_die(esc_html__('Forbidden', 'wsh-ai-news-editor'));
		}

		check_admin_referer('wsh_aine_prepare_sports_ai');

		$query       = isset($_POST['sports_query']) ? sanitize_text_field(wp_unslash($_POST['sports_query'])) : '';
		$summary     = isset($_POST['sports_summary']) ? sanitize_textarea_field(wp_unslash($_POST['sports_summary'])) : '';
		$items_json  = isset($_POST['sports_items_json']) ? wp_unslash($_POST['sports_items_json']) : '[]';
		$mode        = isset($_POST['sports_mode']) ? sanitize_key(wp_unslash($_POST['sports_mode'])) : 'results';
		$sport       = isset($_POST['sports_sport']) ? sanitize_text_field(wp_unslash($_POST['sports_sport'])) : '';
		$league      = isset($_POST['sports_league']) ? sanitize_text_field(wp_unslash($_POST['sports_league'])) : '';
		$items       = json_decode($items_json, true);

		if (! is_array($items)) {
			$items = array();
		}

		$lines = array();

		if ('standings' === $mode) {
			foreach ($items as $item) {
				if (! is_array($item)) {
					continue;
				}

				$rank    = isset($item['rank']) ? (int) $item['rank'] : 0;
				$name    = isset($item['team']['name']) ? sanitize_text_field((string) $item['team']['name']) : '';
				$played  = isset($item['all']['played']) ? (int) $item['all']['played'] : 0;
				$win     = isset($item['all']['win']) ? (int) $item['all']['win'] : 0;
				$draw    = isset($item['all']['draw']) ? (int) $item['all']['draw'] : 0;
				$lose    = isset($item['all']['lose']) ? (int) $item['all']['lose'] : 0;
				$gd      = isset($item['goalsDiff']) ? (int) $item['goalsDiff'] : 0;
				$points  = isset($item['points']) ? (int) $item['points'] : 0;
				$form    = isset($item['form']) ? sanitize_text_field((string) $item['form']) : '';

				$block = array_filter(
					array(
						$rank ? 'Rank: ' . $rank : '',
						$name ? 'Team: ' . $name : '',
						'Played: ' . $played,
						'Wins: ' . $win,
						'Draws: ' . $draw,
						'Losses: ' . $lose,
						'Goal difference: ' . $gd,
						'Points: ' . $points,
						$form ? 'Form: ' . $form : '',
					)
				);

				if (! empty($block)) {
					$lines[] = implode("\n", $block);
				}
			}

			$origin_text = "Sports topic: {$query}\n\nSummary:\n{$summary}";

			if (! empty($lines)) {
				$origin_text .= "\n\nStandings table:\n\n" . implode("\n\n---\n\n", $lines);
			}

			$origin_html_parts   = array();
			$origin_html_parts[] = '<div class="wsh-aine-sports-source">';

			if ($query) {
				$origin_html_parts[] = '<h3 style="margin:0 0 6px; color:#111;">' . esc_html($query) . '</h3>';
			}

			$origin_html_parts[] = '<div style="margin:0 0 16px; color:#666; font-size:14px;">' . esc_html__('Standings', 'wsh-ai-news-editor') . '</div>';

			$origin_html_parts[] = '
			<table style="width:100%; border-collapse:collapse; margin:0 0 20px; border:1px solid #dcdcde; background:#fff;">
			<thead>
				<tr style="background:#f6f7f7;">
					<th style="padding:10px; text-align:left; color:#111;">#</th>
					<th style="padding:10px; text-align:left; color:#111;">' . esc_html__('Team', 'wsh-ai-news-editor') . '</th>
					<th style="padding:10px; text-align:center; color:#111;">P</th>
					<th style="padding:10px; text-align:center; color:#111;">W</th>
					<th style="padding:10px; text-align:center; color:#111;">D</th>
					<th style="padding:10px; text-align:center; color:#111;">L</th>
					<th style="padding:10px; text-align:center; color:#111;">GD</th>
					<th style="padding:10px; text-align:center; color:#111;">PTS</th>
				</tr>
			</thead>
			<tbody>
			';

			foreach ($items as $item) {
				if (! is_array($item)) {
					continue;
				}

				$rank    = isset($item['rank']) ? (int) $item['rank'] : 0;
				$name    = isset($item['team']['name']) ? sanitize_text_field((string) $item['team']['name']) : '';
				$logo    = isset($item['team']['logo']) ? esc_url((string) $item['team']['logo']) : '';
				$played  = isset($item['all']['played']) ? (int) $item['all']['played'] : 0;
				$win     = isset($item['all']['win']) ? (int) $item['all']['win'] : 0;
				$draw    = isset($item['all']['draw']) ? (int) $item['all']['draw'] : 0;
				$lose    = isset($item['all']['lose']) ? (int) $item['all']['lose'] : 0;
				$gd      = isset($item['goalsDiff']) ? (int) $item['goalsDiff'] : 0;
				$points  = isset($item['points']) ? (int) $item['points'] : 0;

				$row_bg = '';
				if ($rank > 0 && $rank <= 4) {
					$row_bg = 'background:#f6fff8;';
				}

				$origin_html_parts[] = '
				<tr style="border-top:1px solid #eee; ' . esc_attr($row_bg) . '">
					<td style="padding:10px; color:#111;">' . esc_html((string) $rank) . '</td>
					<td style="padding:10px; color:#111;">
						<div style="display:flex; align-items:center; gap:10px;">
							' . ($logo ? '<img src="' . $logo . '" alt="' . esc_attr($name) . '" style="width:24px; height:24px; object-fit:contain;" />' : '') . '
							<span>' . esc_html($name) . '</span>
						</div>
					</td>
					<td style="padding:10px; text-align:center; color:#111;">' . esc_html((string) $played) . '</td>
					<td style="padding:10px; text-align:center; color:#111;">' . esc_html((string) $win) . '</td>
					<td style="padding:10px; text-align:center; color:#111;">' . esc_html((string) $draw) . '</td>
					<td style="padding:10px; text-align:center; color:#111;">' . esc_html((string) $lose) . '</td>
					<td style="padding:10px; text-align:center; color:#111;">' . esc_html((string) $gd) . '</td>
					<td style="padding:10px; text-align:center; font-weight:700; color:#111;">' . esc_html((string) $points) . '</td>
				</tr>
			';
			}

			$origin_html_parts[] = '
			</tbody>
			</table>
			';

			$origin_html_parts[] = '</div>';
			$origin_html = implode('', $origin_html_parts);
		} else {
			foreach ($items as $item) {
				if (! is_array($item)) {
					continue;
				}

				$title   = isset($item['title']) ? sanitize_text_field((string) $item['title']) : '';
				$score   = isset($item['score']) ? sanitize_text_field((string) $item['score']) : '';
				$status  = isset($item['status']) ? sanitize_text_field((string) $item['status']) : '';
				$time    = isset($item['time']) ? sanitize_text_field((string) $item['time']) : '';
				$source  = isset($item['source']) ? sanitize_text_field((string) $item['source']) : 'API-SPORTS';
				$snippet = isset($item['snippet']) ? sanitize_textarea_field((string) $item['snippet']) : '';

				$home_team   = isset($item['home_team']['name']) ? sanitize_text_field((string) $item['home_team']['name']) : '';
				$away_team   = isset($item['away_team']['name']) ? sanitize_text_field((string) $item['away_team']['name']) : '';
				$league_name = isset($item['league_name']) ? sanitize_text_field((string) $item['league_name']) : '';
				$country     = isset($item['country_name']) ? sanitize_text_field((string) $item['country_name']) : '';
				$season      = isset($item['season']) ? sanitize_text_field((string) $item['season']) : '';
				$round       = isset($item['round']) ? sanitize_text_field((string) $item['round']) : '';
				$date        = isset($item['date']) ? sanitize_text_field((string) $item['date']) : '';
				$venue_name  = isset($item['venue_name']) ? sanitize_text_field((string) $item['venue_name']) : '';
				$venue_city  = isset($item['venue_city']) ? sanitize_text_field((string) $item['venue_city']) : '';
				$referee     = isset($item['referee']) ? sanitize_text_field((string) $item['referee']) : '';

				$block = array_filter(
					array(
						$title,
						($home_team || $away_team) ? 'Teams: ' . trim($home_team . ' vs ' . $away_team) : '',
						$score ? 'Score: ' . $score : '',
						$status ? 'Status: ' . $status : '',
						$time ? 'Time: ' . $time : '',
						$league_name ? 'League: ' . $league_name : '',
						$country ? 'Country: ' . $country : '',
						$season ? 'Season: ' . $season : '',
						$round ? 'Round: ' . $round : '',
						$date ? 'Date: ' . $date : '',
						$venue_name ? 'Venue: ' . $venue_name : '',
						$venue_city ? 'City: ' . $venue_city : '',
						$referee ? 'Referee: ' . $referee : '',
						$source ? 'Source: ' . $source : '',
						$snippet,
					)
				);

				if (! empty($block)) {
					$lines[] = implode("\n", $block);
				}
			}

			$origin_text = "Sports topic: {$query}\n\nSummary:\n{$summary}";

			if (! empty($lines)) {
				$origin_text .= "\n\nRelevant events:\n\n" . implode("\n\n---\n\n", $lines);
			}

			$origin_html_parts = array();

			if ( 'live' === $mode ) {
				$live_sport = sanitize_key( $sport );
				if ( ! in_array( $live_sport, array( 'football', 'basketball' ), true ) ) {
					$live_sport = 'football';
				}

				if ( 1 === count( $items ) && is_array( $items[0] ) ) {
					$live_attr = ( 'basketball' === $live_sport ) ? 'game_id' : 'fixture_id';
					$live_id   = ( 'basketball' === $live_sport )
						? (int) ( $items[0]['game_id'] ?? 0 )
						: (int) ( $items[0]['fixture_id'] ?? 0 );

					if ( $live_id > 0 ) {
						$origin_html_parts[] = '[wsh_live_score sport="' . esc_attr( $live_sport ) . '" ' . $live_attr . '="' . absint( $live_id ) . '" title=""]';
					}
				} else {
					$league_id = 0;
					foreach ( $items as $live_item ) {
						if ( is_array( $live_item ) && ! empty( $live_item['league_id'] ) ) {
							$league_id = absint( $live_item['league_id'] );
							break;
						}
					}

					if ( $league_id > 0 ) {
						$origin_html_parts[] = '[wsh_live_score sport="' . esc_attr( $live_sport ) . '" league_id="' . $league_id . '" title=""]';
					}
				}

				$origin_html = implode( '', $origin_html_parts );
			} else {
			foreach ($items as $item) {
				if (! is_array($item)) {
					continue;
				}

				$home_name   = isset($item['home_team']['name']) ? sanitize_text_field((string) $item['home_team']['name']) : '';
				$home_logo   = isset($item['home_team']['logo']) ? esc_url((string) $item['home_team']['logo']) : '';
				$away_name   = isset($item['away_team']['name']) ? sanitize_text_field((string) $item['away_team']['name']) : '';
				$away_logo   = isset($item['away_team']['logo']) ? esc_url((string) $item['away_team']['logo']) : '';
				$score       = isset($item['score']) ? sanitize_text_field((string) $item['score']) : '-';
				$status_long = isset($item['status_long']) ? sanitize_text_field((string) $item['status_long']) : '';
				$status      = isset($item['status']) ? sanitize_text_field((string) $item['status']) : '';
				$time        = isset($item['time']) ? sanitize_text_field((string) $item['time']) : '';
				$league_name = isset($item['league_name']) ? sanitize_text_field((string) $item['league_name']) : '';
				$round       = isset($item['round']) ? sanitize_text_field((string) $item['round']) : '';
				$venue_name  = isset($item['venue_name']) ? sanitize_text_field((string) $item['venue_name']) : '';

				$origin_html_parts[] = '
				<table class="wsh-sport-result-table" style="width:100%; border-collapse:collapse; margin:0 0 20px; border:1px solid #dcdcde; background:#fff;">
					<tr>
						<td style="width:30%; padding:16px; text-align:center; vertical-align:middle; color:#000;">
							' . ($home_logo ? '<img src="' . $home_logo . '" alt="' . esc_attr($home_name) . '" style="max-width:56px; height:auto; display:block; margin:0 auto 8px;" />' : '') . '
							<div style="font-size:18px; font-weight:600;">' . esc_html($home_name) . '</div>
						</td>

						<td style="width:40%; padding:16px; text-align:center; vertical-align:middle; color:#000;">
							<div style="font-size:32px; font-weight:700; line-height:1.2;">' . esc_html($score) . '</div>
							<div style="font-size:15px; color:#555; margin-top:8px;">' . esc_html($status_long ? $status_long : $status) . '</div>
							' . ($time ? '<div style="font-size:14px; color:#666; margin-top:6px;">' . esc_html($time) . '</div>' : '') . '
						</td>

						<td style="width:30%; padding:16px; text-align:center; vertical-align:middle; color:#000;">
							' . ($away_logo ? '<img src="' . $away_logo . '" alt="' . esc_attr($away_name) . '" style="max-width:56px; height:auto; display:block; margin:0 auto 8px;" />' : '') . '
							<div style="font-size:18px; font-weight:600;">' . esc_html($away_name) . '</div>
						</td>
					</tr>

					<tr>
						<td colspan="3" style="padding:12px 16px; border-top:1px solid #dcdcde; font-size:14px; line-height:1.6; text-align:center; color:#000;">
							<strong>League:</strong> ' . esc_html($league_name ? $league_name : '-') .
						($round ? ' | <strong>Round:</strong> ' . esc_html($round) : '') .
						($venue_name ? ' | <strong>Venue:</strong> ' . esc_html($venue_name) : '') . '
						</td>
					</tr>
				</table>';
			}

			$origin_html = implode('', $origin_html_parts);
			}
		}

		$payload = WSH_AINE_AI_Payload::normalize(
			array(
				'source_type'  => 'sports',
				'source_name'  => 'API-SPORTS',
				'origin_title' => $query,
				'origin_url'   => '',
				'origin_text'  => $origin_text,
				'origin_html'  => $origin_html,
				'image'        => '',
				'author_name'  => 'API-SPORTS',
				'published_at' => current_time('mysql'),
				'embed_type'   => 'sports_data',
				'embed_url'    => '',
				'extra'        => array(
					'query'   => $query,
					'summary' => $summary,
					'items'   => $items,
					'sport'   => $sport,
					'league'  => $league,
					'mode'    => $mode,
				),
			)
		);

		$token = WSH_AINE_AI_Drafts::store_seed($payload);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'wsh-ai-news-editor-ai-editor',
					'seed' => rawurlencode($token),
				),
				admin_url('admin.php')
			)
		);
		exit;
	}

	protected static function get_match_live_shortcode( string $sport, array $item ): string {
		$sport = sanitize_key( $sport );
		if ( ! in_array( $sport, array( 'football', 'basketball' ), true ) ) {
			$sport = 'football';
		}

		if ( 'basketball' === $sport ) {
			$event_id = isset( $item['game_id'] ) ? (int) $item['game_id'] : 0;
			if ( $event_id <= 0 && isset( $item['raw']['id'] ) ) {
				$event_id = (int) $item['raw']['id'];
			}

			if ( $event_id <= 0 ) {
				return '';
			}

			return '[wsh_live_score sport="basketball" game_id="' . $event_id . '" title=""]';
		}

		$event_id = isset( $item['fixture_id'] ) ? (int) $item['fixture_id'] : 0;
		if ( $event_id <= 0 && isset( $item['raw']['fixture']['id'] ) ) {
			$event_id = (int) $item['raw']['fixture']['id'];
		}

		if ( $event_id <= 0 ) {
			return '';
		}

		return '[wsh_live_score sport="football" fixture_id="' . $event_id . '" title=""]';
	}

	public static function handle_standings_table_bkp($items){

		if(empty($items)) return '';
		
		$html_table = '<div style="margin:0 0 16px; color:#666; font-size:14px;">' . esc_html__('Standings', 'wsh-ai-news-editor') . '</div>';

		$html_table .= '
		<table style="width:100%; border-collapse:collapse; margin:0 0 20px; border:1px solid #dcdcde; background:#fff;">
		<thead>
			<tr style="background:#f6f7f7;">
				<th style="padding:10px; text-align:left; color:#111;">#</th>
				<th style="padding:10px; text-align:left; color:#111;">' . esc_html__('Team', 'wsh-ai-news-editor') . '</th>
				<th style="padding:10px; text-align:center; color:#111;">P</th>
				<th style="padding:10px; text-align:center; color:#111;">W</th>
				<th style="padding:10px; text-align:center; color:#111;">D</th>
				<th style="padding:10px; text-align:center; color:#111;">L</th>
				<th style="padding:10px; text-align:center; color:#111;">GD</th>
				<th style="padding:10px; text-align:center; color:#111;">PTS</th>
			</tr>
		</thead>
		<tbody>
		';

		foreach ($items as $item) {
			if (! is_array($item)) {
				continue;
			}

			$rank    = isset($item['rank']) ? (int) $item['rank'] : 0;
			$name    = isset($item['team']['name']) ? sanitize_text_field((string) $item['team']['name']) : '';
			$logo    = isset($item['team']['logo']) ? esc_url((string) $item['team']['logo']) : '';
			$played  = isset($item['all']['played']) ? (int) $item['all']['played'] : 0;
			$win     = isset($item['all']['win']) ? (int) $item['all']['win'] : 0;
			$draw    = isset($item['all']['draw']) ? (int) $item['all']['draw'] : 0;
			$lose    = isset($item['all']['lose']) ? (int) $item['all']['lose'] : 0;
			$gd      = isset($item['goalsDiff']) ? (int) $item['goalsDiff'] : 0;
			$points  = isset($item['points']) ? (int) $item['points'] : 0;

			$row_bg = '';
			if ($rank > 0 && $rank <= 4) {
				$row_bg = 'background:#f6fff8;';
			}

			$html_table .= '
			<tr style="border-top:1px solid #eee; ' . esc_attr($row_bg) . '">
				<td style="padding:10px; color:#111;">' . esc_html((string) $rank) . '</td>
				<td style="padding:10px; color:#111;">
					<div style="display:flex; align-items:center; gap:10px;">
						' . ($logo ? '<img src="' . $logo . '" alt="' . esc_attr($name) . '" style="width:24px; height:24px; object-fit:contain;" />' : '') . '
						<span>' . esc_html($name) . '</span>
					</div>
				</td>
				<td style="padding:10px; text-align:center; color:#111;">' . esc_html((string) $played) . '</td>
				<td style="padding:10px; text-align:center; color:#111;">' . esc_html((string) $win) . '</td>
				<td style="padding:10px; text-align:center; color:#111;">' . esc_html((string) $draw) . '</td>
				<td style="padding:10px; text-align:center; color:#111;">' . esc_html((string) $lose) . '</td>
				<td style="padding:10px; text-align:center; color:#111;">' . esc_html((string) $gd) . '</td>
				<td style="padding:10px; text-align:center; font-weight:700; color:#111;">' . esc_html((string) $points) . '</td>
			</tr>
		';
		}

		$html_table .= '
		</tbody>
		</table>
		';	

		return $html_table;
	}

		public static function handle_standings_table($items){

		if(empty($items)) {
			return '';
		}

		// Grupisanje po grupama ako postoje
		$groups = [];

		foreach ($items as $item) {

			if (!is_array($item)) {
				continue;
			}

			$group = '';

			if (!empty($item['group'])) {
				$group = trim($item['group']);
			}

			if ($group) {
				$groups[$group][] = $item;
			} else {
				$groups['default'][] = $item;
			}
		}

		// Ako nema grupa ili postoji samo jedna grupa -> stari prikaz
		$multiple_groups = count($groups) > 1;

		$html_table = '<div style="margin:0 0 16px; color:#666; font-size:14px;">' .
			esc_html__('Standings', 'wsh-ai-news-editor') .
			'</div>';

		foreach ($groups as $group_name => $group_items) {

			if ($multiple_groups && $group_name !== 'default') {

				$html_table .= '
					<h3 style="
						margin:25px 0 10px;
						padding:8px 12px;
						background:#f6f7f7;
						border-left:4px solid #2271b1;
					">
						' . esc_html($group_name) . '
					</h3>
				';
			}

			$html_table .= '
			<table style="width:100%; border-collapse:collapse; margin:0 0 20px; border:1px solid #dcdcde; background:#fff;">
			<thead>
				<tr style="background:#f6f7f7;">
					<th style="padding:10px; text-align:left; color:#111;">#</th>
					<th style="padding:10px; text-align:left; color:#111;">' . esc_html__('Team', 'wsh-ai-news-editor') . '</th>
					<th style="padding:10px; text-align:center; color:#111;">P</th>
					<th style="padding:10px; text-align:center; color:#111;">W</th>
					<th style="padding:10px; text-align:center; color:#111;">D</th>
					<th style="padding:10px; text-align:center; color:#111;">L</th>
					<th style="padding:10px; text-align:center; color:#111;">GD</th>
					<th style="padding:10px; text-align:center; color:#111;">PTS</th>
				</tr>
			</thead>
			<tbody>
			';

			foreach ($group_items as $item) {

				$rank    = isset($item['rank']) ? (int) $item['rank'] : 0;
				$name    = isset($item['team']['name']) ? sanitize_text_field((string) $item['team']['name']) : '';
				$logo    = isset($item['team']['logo']) ? esc_url((string) $item['team']['logo']) : '';
				$played  = isset($item['all']['played']) ? (int) $item['all']['played'] : 0;
				$win     = isset($item['all']['win']) ? (int) $item['all']['win'] : 0;
				$draw    = isset($item['all']['draw']) ? (int) $item['all']['draw'] : 0;
				$lose    = isset($item['all']['lose']) ? (int) $item['all']['lose'] : 0;
				$gd      = isset($item['goalsDiff']) ? (int) $item['goalsDiff'] : 0;
				$points  = isset($item['points']) ? (int) $item['points'] : 0;

				$row_bg = '';

				if ($rank > 0 && $rank <= 4) {
					$row_bg = 'background:#f6fff8;';
				}

				$html_table .= '
				<tr style="border-top:1px solid #eee; ' . esc_attr($row_bg) . '">
					<td style="padding:10px; color:#111;">' . esc_html($rank) . '</td>

					<td style="padding:10px; color:#111;">
						<div style="display:flex; align-items:center; gap:10px;">
							' . ($logo ? '<img src="' . $logo . '" alt="' . esc_attr($name) . '" style="width:24px;height:24px;object-fit:contain;" />' : '') . '
							<span>' . esc_html($name) . '</span>
						</div>
					</td>

					<td style="padding:10px; text-align:center; color:#111;">' . esc_html($played) . '</td>
					<td style="padding:10px; text-align:center; color:#111;">' . esc_html($win) . '</td>
					<td style="padding:10px; text-align:center; color:#111;">' . esc_html($draw) . '</td>
					<td style="padding:10px; text-align:center; color:#111;">' . esc_html($lose) . '</td>
					<td style="padding:10px; text-align:center; color:#111;">' . esc_html($gd) . '</td>
					<td style="padding:10px; text-align:center; font-weight:700; color:#111;">' . esc_html($points) . '</td>
				</tr>';
			}

			$html_table .= '
			</tbody>
			</table>';
		}

		return $html_table;
	}


}
