<?php
if (! defined('ABSPATH')) {
	exit;
}

class WSH_AINE_Grok_News_Page
{

	public static function init(): void
	{
		add_action('admin_post_wsh_aine_prepare_grok_ai', array(__CLASS__, 'handle_prepare_ai'));
	}

	public static function handle_prepare_ai(): void
	{
		if (! current_user_can('edit_posts')) {
			wp_die(esc_html__('Forbidden', 'wsh-ai-news-editor'));
		}

		check_admin_referer('wsh_aine_prepare_grok_ai');

		$query   = isset($_POST['grok_query']) ? sanitize_text_field(wp_unslash($_POST['grok_query'])) : '';
		$summary = isset($_POST['grok_summary']) ? sanitize_textarea_field(wp_unslash($_POST['grok_summary'])) : '';
		$items_json = isset($_POST['grok_items_json']) ? wp_unslash($_POST['grok_items_json']) : '[]';
		$angles_json = isset($_POST['grok_angles_json']) ? wp_unslash($_POST['grok_angles_json']) : '[]';
		$angles = json_decode($angles_json, true);
		if (! is_array($angles)) {
			$angles = array();
		}


		$items = json_decode($items_json, true);
		if (! is_array($items)) {
			$items = array();
		}

		$lines = array();
		foreach ($items as $item) {
			if (! is_array($item)) {
				continue;
			}

			$title   = isset($item['title']) ? sanitize_text_field((string) $item['title']) : '';
			$url     = isset($item['url']) ? esc_url_raw((string) $item['url']) : '';
			$source  = isset($item['source']) ? sanitize_text_field((string) $item['source']) : '';
			$snippet = isset($item['snippet']) ? sanitize_textarea_field((string) $item['snippet']) : '';

			$block = array_filter(array(
				$title,
				$source ? 'Izvor: ' . $source : '',
				$snippet,
				$url,
			));

			if (! empty($block)) {
				$lines[] = implode("\n", $block);
			}
		}

		$origin_text = "Grok tema: {$query}\n\nSažetak:\n{$summary}";

		if (! empty($angles)) {
			$origin_text .= "\n\nGlavni angle-ovi:\n- " . implode("\n- ", array_map('sanitize_text_field', $angles));
		}

		if (! empty($lines)) {
			$origin_text .= "\n\nRelevantne stavke:\n\n" . implode("\n\n---\n\n", $lines);
		}

		$payload = WSH_AINE_AI_Payload::normalize(
			array(
				'source_type'  => 'grok',
				'source_name'  => 'Grok / xAI',
				'origin_title' => $query,
				'origin_url'   => '',
				'origin_text'  => $origin_text,
				'origin_html'  => '',
				'image'        => '',
				'author_name'  => 'Grok',
				'published_at' => current_time('mysql'),
				'embed_type'   => 'grok_search',
				'embed_url'    => '',
				'extra'        => array(
					'query'   => $query,
					'summary' => $summary,
					'items'   => $items,
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

	public static function render(): void
	{
		if (! current_user_can('edit_posts')) {
			wp_die(esc_html__('Forbidden', 'wsh-ai-news-editor'));
		}

		$module = WSH_AINE_Module_Registry::get_module('wsh-ai-news-editor-grok-news');
		if ( ! WSH_AINE_Access::can_access_module($module) ) {
			WSH_AINE_Admin_Notices::locked_module('Grok AI', 'PRO');
			return;
		}

		$options = get_option('wsh_aine_settings', array());
		$query        = isset( $_GET['grok_query'] ) ? sanitize_text_field( wp_unslash( $_GET['grok_query'] ) ) : '';
		$mode         = isset( $_GET['grok_mode'] ) ? sanitize_key( wp_unslash( $_GET['grok_mode'] ) ) : 'search';
		$filter       = isset( $_GET['grok_filter'] ) ? sanitize_key( wp_unslash( $_GET['grok_filter'] ) ) : 'all';
		$run_request  = isset( $_GET['grok_run'] ) ? absint( $_GET['grok_run'] ) : 0;
		$force_refresh = isset( $_GET['grok_refresh'] ) ? absint( $_GET['grok_refresh'] ) : 0;
		$result       = null;
		$error        = '';


		$allowed_filters = array(
			'all'      => __( 'All', 'wsh-ai-news-editor' ),
			'serbia'   => __( 'Serbia', 'wsh-ai-news-editor' ),
			'region'   => __( 'Region', 'wsh-ai-news-editor' ),
			'world'    => __( 'World', 'wsh-ai-news-editor' ),
			'sport'    => __( 'Sport', 'wsh-ai-news-editor' ),
			'politics' => __( 'Politics', 'wsh-ai-news-editor' ),
			'tech'     => __( 'Tech', 'wsh-ai-news-editor' ),
		);

		if ( ! isset( $allowed_filters[ $filter ] ) ) {
			$filter = 'all';
		}

		if (! in_array($mode, array('search', 'trending'), true)) {
			$mode = 'search';
		}

		if ( 'trending' === $mode && $run_request ) {
			$result = WSH_AINE_Grok_Fetcher::trending( $options, $filter, (bool) $force_refresh );

			if ( is_wp_error( $result ) ) {
				$error  = $result->get_error_message();
				$result = null;
			}
		} elseif ( 'search' === $mode && '' !== $query ) {
			$result = WSH_AINE_Grok_Fetcher::search( $query, $options );

			if ( is_wp_error( $result ) ) {
				$error  = $result->get_error_message();
				$result = null;
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Grok News', 'wsh-ai-news-editor'); ?></h1>
			<p><?php esc_html_e( 'Search X trends and discussions through Grok, then turn them into an AI news draft.', 'wsh-ai-news-editor' ); ?></p>

			<?php if (empty($options['grok_api_key'])) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e('Grok API key is not configured. Go to Settings first.', 'wsh-ai-news-editor'); ?></p>
				</div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper" style="margin-top:20px;">
				<a
					href="<?php echo esc_url(add_query_arg(array(
								'page'      => 'wsh-ai-news-editor-grok-news',
								'grok_mode' => 'trending',
							), admin_url('admin.php'))); ?>"
					class="nav-tab <?php echo ('trending' === $mode) ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e('Trending', 'wsh-ai-news-editor'); ?>
				</a>

				<a
					href="<?php echo esc_url(add_query_arg(array(
								'page'      => 'wsh-ai-news-editor-grok-news',
								'grok_mode' => 'search',
							), admin_url('admin.php'))); ?>"
					class="nav-tab <?php echo ('search' === $mode) ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e('Search', 'wsh-ai-news-editor'); ?>
				</a>
			</h2>

			<?php if ('search' === $mode) : ?>
				<form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin-top:16px;">
					<input type="hidden" name="page" value="wsh-ai-news-editor-grok-news" />
					<input type="hidden" name="grok_mode" value="search" />
					<input
						type="text"
						name="grok_query"
						value="<?php echo esc_attr($query); ?>"
						class="regular-text"
						style="width:420px;"
						placeholder="<?php echo esc_attr__('Unesi temu, osobu ili događaj...', 'wsh-ai-news-editor'); ?>" />
					<button type="submit" class="button button-primary"><?php esc_html_e('Search', 'wsh-ai-news-editor'); ?></button>
				</form>
			<?php else : ?>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-top:16px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
					<input type="hidden" name="page" value="wsh-ai-news-editor-grok-news" />
					<input type="hidden" name="grok_mode" value="trending" />
					<input type="hidden" name="grok_run" value="1" />

					<select name="grok_filter">
						<?php foreach ( $allowed_filters as $filter_key => $filter_label ) : ?>
							<option value="<?php echo esc_attr( $filter_key ); ?>" <?php selected( $filter, $filter_key ); ?>>
								<?php echo esc_html( $filter_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Load trending topics', 'wsh-ai-news-editor' ); ?>
					</button>

					<?php if ( $run_request ) : ?>
						<a
							class="button"
							href="<?php echo esc_url( add_query_arg( array(
								'page'         => 'wsh-ai-news-editor-grok-news',
								'grok_mode'    => 'trending',
								'grok_filter'  => $filter,
								'grok_run'     => 1,
								'grok_refresh' => 1,
							), admin_url( 'admin.php' ) ) ); ?>">
							<?php esc_html_e( 'Refresh', 'wsh-ai-news-editor' ); ?>
						</a>
					<?php endif; ?>
				</form>
				<?php if ( 'trending' === $mode && ! $run_request ) : ?>
					<div class="notice notice-info" style="margin-top:20px;">
						<p><?php esc_html_e( 'Select a filter and click "Load trending topics".', 'wsh-ai-news-editor' ); ?></p>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ($error) : ?>
				<div class="notice notice-error" style="margin-top:20px;">
					<p><?php echo esc_html($error); ?></p>
				</div>
			<?php endif; ?>

			<?php if (is_array($result)) : ?>
				<div class="wsh-aine-panel" style="margin-top:20px;">
						<?php echo ( 'trending' === $mode )
								? esc_html__( 'Trending Summary', 'wsh-ai-news-editor' )
								: esc_html__( 'Grok Summary', 'wsh-ai-news-editor' ); ?>
						</h2>

						<?php if ( 'trending' === $mode ) : ?>
							<p style="margin-top:6px; color:#666;">
								<strong><?php esc_html_e( 'Filter:', 'wsh-ai-news-editor' ); ?></strong>
								<?php echo esc_html( $allowed_filters[ $filter ] ?? 'All' ); ?>
							</p>
						<?php endif; ?>

					<p><?php echo nl2br(esc_html($result['summary'] ?? '')); ?></p>

					<?php if (! empty($result['angles'])) : ?>
						<h3 style="margin-top:16px;"><?php esc_html_e('Main story angles', 'wsh-ai-news-editor'); ?></h3>
						<ul style="margin-left:20px;">
							<?php foreach ($result['angles'] as $angle) : ?>
								<li><?php echo esc_html($angle); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;">
						<?php wp_nonce_field('wsh_aine_prepare_grok_ai'); ?>
						<input type="hidden" name="action" value="wsh_aine_prepare_grok_ai" />
						<input type="hidden" name="grok_angles_json" value="<?php echo esc_attr(wp_json_encode($result['angles'] ?? array())); ?>" />
						<input type="hidden" name="grok_query" value="<?php echo esc_attr($result['query'] ?? ''); ?>" />
						<input type="hidden" name="grok_summary" value="<?php echo esc_attr($result['summary'] ?? ''); ?>" />
						<input type="hidden" name="grok_items_json" value="<?php echo esc_attr(wp_json_encode($result['items'] ?? array())); ?>" />
						<button type="submit" class="button button-primary">
							<?php esc_html_e('Generate AI news from all results', 'wsh-ai-news-editor'); ?>
						</button>
					</form>
				</div>

				<?php if (! empty($result['items'])) : ?>
					<div class="wsh-aine-news-list" style="margin-top:20px;">
						<?php foreach ($result['items'] as $item) : ?>
							<div class="wsh-aine-news-card">
								<div class="wsh-aine-news-card__body" style="padding-left:0;">
									<div class="wsh-aine-news-card__top">
										<span class="wsh-aine-badge"><?php echo esc_html($item['source'] ?? 'Source'); ?></span>
									</div>
									<h3 class="wsh-aine-news-card__title"><?php echo esc_html($item['title'] ?? ''); ?></h3>

									<?php if (! empty($item['snippet'])) : ?>
										<p class="wsh-aine-news-card__excerpt"><?php echo esc_html($item['snippet']); ?></p>
									<?php endif; ?>

									<?php
									$item_summary = trim(
										( ! empty( $result['summary'] ) ? $result['summary'] . "\n\n" : '' ) .
										( $item['snippet'] ?? '' )
									);
									?>
									<div class="wsh-aine-news-card__actions" style="display:flex; gap:10px; flex-wrap:wrap;">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
											<?php wp_nonce_field( 'wsh_aine_prepare_grok_ai' ); ?>
											<input type="hidden" name="action" value="wsh_aine_prepare_grok_ai" />
											<input type="hidden" name="grok_query" value="<?php echo esc_attr( $result['query'] ?? '' ); ?>" />
											<input type="hidden" name="grok_summary" value="<?php echo esc_attr( $item_summary ); ?>" />
											<input type="hidden" name="grok_angles_json" value="<?php echo esc_attr( wp_json_encode( array( $item['title'] ?? '' ) ) ); ?>" />
											<input type="hidden" name="grok_items_json" value="<?php echo esc_attr( wp_json_encode( array( $item ) ) ); ?>" />

											<button type="submit" class="button button-primary">
												<?php esc_html_e( 'Generate AI new', 'wsh-ai-news-editor' ); ?>
											</button>
										</form>

										<?php if ( ! empty( $item['url'] ) ) : ?>
											<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button">
												<?php esc_html_e( 'View Source', 'wsh-ai-news-editor' ); ?>
											</a>
										<?php endif; ?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php
	}
}
