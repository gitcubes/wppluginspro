<?php
if (! defined('ABSPATH')) {
	exit;
}

class WSH_AINE_Social_Network_News_Page
{

	public static function init(): void
	{
		add_action('admin_post_wsh_aine_social_fetch_post', array(__CLASS__, 'handle_fetch_post'));
		add_action('admin_post_wsh_aine_social_prepare_ai', array(__CLASS__, 'handle_prepare_ai'));
	}

	public static function handle_fetch_post(): void
	{

		if (! current_user_can('edit_posts')) {
			wp_die(esc_html__('Forbidden', 'wsh-ai-news-editor'));
		}

		check_admin_referer('wsh_aine_social_fetch_post');

		$url = isset($_POST['social_post_url']) ? esc_url_raw(wp_unslash($_POST['social_post_url'])) : '';


		if ('' === $url) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'wsh-ai-news-editor-social-network-news',
						'error' => 'missing_url',
					),
					admin_url('admin.php')
				)
			);
			exit;
		}

		$options = get_option('wsh_aine_settings', array());
		$item    = WSH_AINE_Social_Post_Fetcher::fetch_post($url, $options);

		if ( isset( $item['extra'] ) ) {
			unset( $item['extra'] );
		}

		if (empty($item)) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'wsh-ai-news-editor-social-network-news',
						'url'   => $url,
						'error' => 'fetch_failed',
					),
					admin_url('admin.php')
				)
			);
			exit;
		}

		//$token = WSH_AINE_AI_Drafts::store_seed( $item );
		$token = self::store_preview_item($item);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'wsh-ai-news-editor-social-network-news',
					'preview' => $token,
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

		check_admin_referer('wsh_aine_social_prepare_ai');

		$preview_token = isset($_POST['preview_token']) ? sanitize_text_field(wp_unslash($_POST['preview_token'])) : '';
		//$item = WSH_AINE_AI_Drafts::get_seed( $preview_token );
		$item = self::get_preview_item($preview_token);

		if (empty($item) || ! is_array($item)) {
			wp_die(esc_html__('Missing preview payload.', 'wsh-ai-news-editor'));
		}

		$payload = WSH_AINE_AI_Payload::normalize(
			array(
				'source_type'     => 'social_post',
				'source_name'     => $item['source_name'] ?? 'Social Post',
				'origin_title'    => wp_html_excerpt((string) ($item['caption'] ?? $item['text'] ?? ''), 120, '...'),
				'origin_url'      => $item['post_url'] ?? '',
				'origin_text'     => WSH_AINE_Social_Post_Fetcher::build_origin_text($item),
				'origin_html'     => '',
				'image'           => $item['thumbnail_url'] ?? '',
				'author_name'     => $item['author_name'] ?? '',
				'author_username' => $item['author_username'] ?? '',
				'published_at'    => $item['published_at'] ?? '',
				'embed_type'      => $item['platform'] ?? '',
				'embed_url'       => $item['embed_url'] ?? ($item['post_url'] ?? ''),
				'extra'           => array(
					'platform'      => $item['platform'] ?? '',
					'post_id'       => $item['post_id'] ?? '',
					'media_type'    => $item['media_type'] ?? '',
					'like_count'    => $item['like_count'] ?? 0,
					'comment_count' => $item['comment_count'] ?? 0,
					'view_count'    => $item['view_count'] ?? 0,
					'comments'      => $item['comments'] ?? array(),
				),
			)
		);

		//$seed_token = self::store_preview_item( $item );
		$seed_token = WSH_AINE_AI_Drafts::store_seed($payload);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'wsh-ai-news-editor-ai-editor',
					'seed' => $seed_token,
				),
				admin_url('admin.php')
			)
		);
		exit;
	}

	protected static function store_preview_item(array $item): string
	{
		$token = wp_generate_password(40, false, false);

		// Bright Data raw response ume da bude veliki.
		// Za preview i AI pripremu nam nije potreban ceo raw extra.
		if (isset($item['extra'])) {
			unset($item['extra']);
		}

		$data = array(
			'user_id'    => get_current_user_id(),
			'created_at' => time(),
			'payload'    => $item,
		);

		$transient_key = 'wsh_aine_social_preview_' . $token;
		$option_key    = 'wsh_aine_social_preview_option_' . $token;

		$saved = set_transient($transient_key, $data, HOUR_IN_SECONDS);

		// Fallback ako transient ne radi ili payload ne može da se pročita.
		if (! $saved || ! is_array(get_transient($transient_key))) {
			update_option($option_key, $data, false);
		}

		return $token;
	}

	protected static function get_preview_item(string $token): array
	{
		$token = preg_replace('/[^a-zA-Z0-9]/', '', $token);

		if (empty($token)) {
			return array();
		}

		$transient_key = 'wsh_aine_social_preview_' . $token;
		$option_key    = 'wsh_aine_social_preview_option_' . $token;

		$data = get_transient($transient_key);

		if (! is_array($data)) {
			$data = get_option($option_key, false);
		}

		if (! is_array($data)) {
			return array();
		}

		$user_id = isset($data['user_id']) ? (int) $data['user_id'] : 0;

		if ($user_id && $user_id !== get_current_user_id()) {
			return array();
		}

		$item = isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : array();

		return $item;
	}

	public static function render(): void
	{
		$preview_token = isset($_GET['preview']) ? sanitize_text_field(wp_unslash($_GET['preview'])) : '';
		//$item = $preview_token ? WSH_AINE_AI_Drafts::get_seed( $preview_token ) : array();
		$item = $preview_token ? self::get_preview_item($preview_token) : array();

		$error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';
		$url   = isset($_GET['url']) ? esc_url_raw(wp_unslash($_GET['url'])) : '';


		if (! is_array($item)) {
			$item = array();
		}
?>
		<div class="wrap">
			<?php if ('missing_url' === $error) : ?>
				<div class="notice notice-error">
					<p><?php esc_html_e('Please enter a social post URL.', 'wsh-ai-news-editor'); ?></p>
				</div>
			<?php elseif ('fetch_failed' === $error) : ?>
				<div class="notice notice-error">
					<p>
						<?php esc_html_e('The post could not be fetched for this platform. Check the dataset ID and the raw response shape.', 'wsh-ai-news-editor'); ?>
						<?php if (! empty($url)) : ?>
							<br><code><?php echo esc_html($url); ?></code>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if (! empty($preview_token) && empty($item)) : ?>
				<div class="notice notice-error">
					<p>Preview token exists, but preview payload could not be loaded.</p>
				</div>
			<?php endif; ?>

			<h1><?php esc_html_e('Social Network News', 'wsh-ai-news-editor'); ?></h1>
			<p><?php esc_html_e('Paste a post URL from Instagram, X, LinkedIn or YouTube and turn it into an AI article.', 'wsh-ai-news-editor'); ?></p>

			<div class="wsh-aine-settings-card" style="max-width:900px;">
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<?php wp_nonce_field('wsh_aine_social_fetch_post'); ?>
					<input type="hidden" name="action" value="wsh_aine_social_fetch_post" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e('Post URL', 'wsh-ai-news-editor'); ?></th>
							<td>
								<input type="url" name="social_post_url" class="regular-text code" style="width:100%;max-width:700px;" placeholder="https://www.instagram.com/p/... or https://x.com/... or https://www.linkedin.com/posts/... or https://www.youtube.com/watch?v=..." />
							</td>
						</tr>
					</table>

					<p>
						<button type="submit" class="button button-primary"><?php esc_html_e('Fetch Post', 'wsh-ai-news-editor'); ?></button>
					</p>
				</form>
			</div>

			<?php if (! empty($item)) : ?>
				<?php self::render_preview($item, $preview_token); ?>
			<?php endif; ?>
		</div>
	<?php
	}

	protected static function render_preview(array $item, string $preview_token): void
	{
		$text = $item['text'] ?? $item['post_text'] ?? $item['post_html_text'];

	?>
		<div class="wsh-aine-settings-card" style="max-width:900px; margin-top:24px;">
			<h2><?php esc_html_e('Post Preview', 'wsh-ai-news-editor'); ?></h2>

			<p><strong><?php esc_html_e('Platform:', 'wsh-ai-news-editor'); ?></strong> <?php echo esc_html(ucfirst((string) ($item['platform'] ?? ''))); ?></p>
			<p><strong><?php esc_html_e('Author:', 'wsh-ai-news-editor'); ?></strong> <?php echo esc_html((string) ($item['author_name'] ?? '')); ?><?php echo ! empty($item['author_username']) ? ' (@' . esc_html(ltrim((string) $item['author_username'], '@')) . ')' : ''; ?></p>
			<p><strong><?php esc_html_e('URL:', 'wsh-ai-news-editor'); ?></strong> <a href="<?php echo esc_url($item['post_url'] ?? ''); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($item['post_url'] ?? ''); ?></a></p>

			<?php if (! empty($item['thumbnail_url'])) : ?>
				<p><img src="<?php echo esc_url($item['thumbnail_url']); ?>" alt="" style="max-width:320px;height:auto;border-radius:8px;" /></p>
			<?php endif; ?>

			<?php if (! empty($item['text'])) : ?>
				<h3><?php esc_html_e('Text', 'wsh-ai-news-editor'); ?></h3>
				<div style="background:#fff;border:1px solid #ddd;padding:12px;border-radius:8px;white-space:pre-wrap;"><?php echo esc_html($text); ?></div>
			<?php endif; ?>

			<h3 style="margin-top:20px;"><?php esc_html_e('Top 5 Comments', 'wsh-ai-news-editor'); ?></h3>
			<?php if (! empty($item['comments']) && is_array($item['comments'])) : ?>
				<ol>
					<?php foreach (array_slice($item['comments'], 0, 5) as $comment) : ?>
						<li style="margin-bottom:10px;">
							<strong><?php echo esc_html($comment['author'] ?? 'user'); ?></strong>
							<?php if (isset($comment['likes'])) : ?>
								<em>(<?php echo esc_html((string) $comment['likes']); ?> likes)</em>
							<?php endif; ?>
							<div><?php echo esc_html($comment['text'] ?? ''); ?></div>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php else : ?>
				<p><?php esc_html_e('No comment details available for this post yet.', 'wsh-ai-news-editor'); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:20px;">
				<?php wp_nonce_field('wsh_aine_social_prepare_ai'); ?>
				<input type="hidden" name="action" value="wsh_aine_social_prepare_ai" />
				<input type="hidden" name="preview_token" value="<?php echo esc_attr($preview_token); ?>" />
				<button type="submit" class="button button-primary button-large"><?php esc_html_e('Generate AI Article', 'wsh-ai-news-editor'); ?></button>
			</form>
		</div>
<?php
	}
}
