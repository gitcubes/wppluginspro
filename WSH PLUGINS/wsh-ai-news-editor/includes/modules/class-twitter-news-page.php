<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Twitter_News_Page {

	public static function init() : void {
		add_action( 'admin_post_wsh_aine_prepare_twitter_ai', array( __CLASS__, 'handle_prepare_ai' ) );
		add_action( 'admin_post_wsh_aine_refresh_twitter_news', array( __CLASS__, 'handle_refresh' ) );
	}

	public static function handle_prepare_ai() : void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Forbidden', 'wsh-ai-news-editor' ) );
		}

		check_admin_referer( 'wsh_aine_prepare_twitter_ai' );

		$item = array(
			'tweet_id'        => isset( $_POST['item_tweet_id'] ) ? sanitize_text_field( wp_unslash( $_POST['item_tweet_id'] ) ) : '',
			'text'            => isset( $_POST['item_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['item_text'] ) ) : '',
			'author_name'     => isset( $_POST['item_author_name'] ) ? sanitize_text_field( wp_unslash( $_POST['item_author_name'] ) ) : '',
			'author_username' => isset( $_POST['item_author_username'] ) ? sanitize_text_field( wp_unslash( $_POST['item_author_username'] ) ) : '',
			'media_url'       => isset( $_POST['item_media_url'] ) ? esc_url_raw( wp_unslash( $_POST['item_media_url'] ) ) : '',
			'tweet_url'       => isset( $_POST['item_tweet_url'] ) ? esc_url_raw( wp_unslash( $_POST['item_tweet_url'] ) ) : '',
			'embed_url'       => isset( $_POST['item_embed_url'] ) ? esc_url_raw( wp_unslash( $_POST['item_embed_url'] ) ) : '',
			'created_at'      => isset( $_POST['item_created_at'] ) ? sanitize_text_field( wp_unslash( $_POST['item_created_at'] ) ) : '',
			'like_count'      => isset( $_POST['item_like_count'] ) ? absint( $_POST['item_like_count'] ) : 0,
			'retweet_count'   => isset( $_POST['item_retweet_count'] ) ? absint( $_POST['item_retweet_count'] ) : 0,
			'reply_count'     => isset( $_POST['item_reply_count'] ) ? absint( $_POST['item_reply_count'] ) : 0,
			'quote_count'     => isset( $_POST['item_quote_count'] ) ? absint( $_POST['item_quote_count'] ) : 0,
			'source_name'     => isset( $_POST['item_source_name'] ) ? sanitize_text_field( wp_unslash( $_POST['item_source_name'] ) ) : '',
		);

		$xid = self::wsh_extract_tco_id($item['text']);
		$item['xid'] = $xid;
		$item['embed_html'] = self::build_tweet_embed_html( $item );

		$metrics_text = sprintf(
			"Lajkovi: %d\nRetvitovi: %d\nOdgovori: %d\nCitati: %d",
			$item['like_count'],
			$item['retweet_count'],
			$item['reply_count'],
			$item['quote_count']
		);

		$payload = WSH_AINE_AI_Payload::normalize(
			array(
				'source_type'  => 'twitter',
				'source_name'  => $item['source_name'] ? $item['source_name'] : ( $item['author_username'] ? '@' . ltrim( $item['author_username'], '@' ) : 'X / Twitter' ),
				'origin_title' => $item['text'],
				'origin_url'   => $item['tweet_url'],
				'origin_text'  => "Autor: {$item['author_name']} (@{$item['author_username']})\n\nTvit:\n{$item['text']}\n\nStatistika:\n{$metrics_text}",
				'origin_html'  => '',
				'image'        => $item['media_url'],
				'author_name'  => $item['author_name'],
				'author_username'  => $item['author_username'],
				'published_at' => $item['created_at'],
				'embed_type'   => 'tweet',
				'embed_url'    => $item['tweet_url'],
				'extra'        => array(
					'xid'       	 => $item['xid'],
					'tweet_id'       => $item['tweet_id'],
					'author_username'=> $item['author_username'],
					'like_count'     => $item['like_count'],
					'retweet_count'  => $item['retweet_count'],
					'reply_count'    => $item['reply_count'],
					'quote_count'    => $item['quote_count'],
					'embed_html'    => '',
				),
			)
		);
		$payload['extra']['embed_html'] = $item['embed_html'];

		$token = WSH_AINE_AI_Drafts::store_seed( $payload );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'wsh-ai-news-editor-ai-editor',
					'seed' => rawurlencode( $token ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function wsh_extract_tco_id( string $text ) : ?string {
		if ( preg_match('/https?:\/\/t\.co\/([A-Za-z0-9]+)/i', $text, $match) ) {
			return $match[1];
		}
		return null;
	}

	protected static function build_tweet_embed_html( array $item ) : string {
		$tweet_url       = ! empty( $item['tweet_url'] ) ? esc_url( $item['tweet_url'] ) : '';
		$tweet_text      = ! empty( $item['text'] ) ? esc_html( $item['text'] ) : '';
		$author_name     = ! empty( $item['author_name'] ) ? esc_html( $item['author_name'] ) : '';
		$author_username = ! empty( $item['author_username'] ) ? ltrim( (string) $item['author_username'], '@' ) : '';
		$author_username = esc_html( $author_username );
		$created_at      = ! empty( $item['created_at'] ) ? esc_html( $item['created_at'] ) : '';

		if ( '' === $tweet_url ) {
			return '';
		}

		$pic = "<a href='https://t.co/" . $item['tweet_id']. "'>pic.twitter.com/" . $item['tweet_id']. "</a></p>";
		$html  = '<blockquote class="twitter-tweet">';
		$html .= '<p lang="und" dir="ltr">' . $tweet_text . ' ' . $pic . '</p>';
		$html .= '&mdash; ' . $author_name;

		if ( '' !== $author_username ) {
			$html .= ' (@' . $author_username . ')';
		}

		$embed_url = str_replace( 'https://x.com', 'https://twitter.com', $tweet_url );
		$html .= ' <a href="' . $embed_url . '">' . $created_at . '</a>';
		$html .= '</blockquote>';

		return $html;
	}

	public static function handle_refresh() : void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Forbidden', 'wsh-ai-news-editor' ) );
		}

		check_admin_referer( 'wsh_aine_refresh_twitter_news' );

		$options = get_option( 'wsh_aine_settings', array() );

		$mode = isset( $_POST['x_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['x_mode'] ) ) : 'accounts';
		$item_slug = isset( $_POST['x_item'] ) ? sanitize_text_field( wp_unslash( $_POST['x_item'] ) ) : '';

		if ( ! in_array( $mode, array( 'accounts', 'topics' ), true ) ) {
			$mode = 'accounts';
		}

		$list = 'accounts' === $mode
			? ( isset( $options['twitter_accounts'] ) && is_array( $options['twitter_accounts'] ) ? $options['twitter_accounts'] : array() )
			: ( isset( $options['twitter_topics'] ) && is_array( $options['twitter_topics'] ) ? $options['twitter_topics'] : array() );

		foreach ( $list as $entry ) {
			if ( empty( $entry['active'] ) || empty( $entry['url'] ) ) {
				continue;
			}

			if ( sanitize_title( $entry['name'] ?? '' ) === $item_slug ) {
				WSH_AINE_Twitter_Fetcher::clear_cache_for_source( $mode, $entry, $options );
				break;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'wsh-ai-news-editor-twitter-news',
					'x_mode'    => $mode,
					'x_item'    => $item_slug,
					'refreshed' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function render() : void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Forbidden', 'wsh-ai-news-editor' ) );
		}

		$module = WSH_AINE_Module_Registry::get_module('wsh-ai-news-editor-twitter-news');
		if ( ! WSH_AINE_Access::can_access_module($module) ) {
			WSH_AINE_Admin_Notices::locked_module('Twitter/X', 'PRO');
			return;
		}

		$options = get_option( 'wsh_aine_settings', array() );

		$accounts = isset( $options['twitter_accounts'] ) && is_array( $options['twitter_accounts'] ) ? array_values( array_filter( $options['twitter_accounts'], function( $item ) {
			return ! empty( $item['active'] ) && ! empty( $item['url'] );
		} ) ) : array();

		$topics = isset( $options['twitter_topics'] ) && is_array( $options['twitter_topics'] ) ? array_values( array_filter( $options['twitter_topics'], function( $item ) {
			return ! empty( $item['active'] ) && ! empty( $item['url'] );
		} ) ) : array();

		$mode = isset( $_GET['x_mode'] ) ? sanitize_text_field( wp_unslash( $_GET['x_mode'] ) ) : 'accounts';
		if ( ! in_array( $mode, array( 'accounts', 'topics' ), true ) ) {
			$mode = 'accounts';
		}

		$list = 'accounts' === $mode ? $accounts : $topics;
		$current_item_slug = isset( $_GET['x_item'] ) ? sanitize_text_field( wp_unslash( $_GET['x_item'] ) ) : '';

		if ( '' === $current_item_slug && ! empty( $list[0]['name'] ) ) {
			$current_item_slug = sanitize_title( $list[0]['name'] );
		}

		$current_item = null;
		foreach ( $list as $entry ) {
			if ( sanitize_title( $entry['name'] ?? '' ) === $current_item_slug ) {
				$current_item = $entry;
				break;
			}
		}

		if ( ! $current_item && ! empty( $list ) ) {
			$current_item = $list[0];
			$current_item_slug = sanitize_title( $current_item['name'] ?? '' );
		}

		$tweets = array();

		if ( $current_item ) {
			if ( 'accounts' === $mode ) {
				$tweets = WSH_AINE_Twitter_Fetcher::fetch_account_tweets( $current_item, $options );
			} else {
				$tweets = WSH_AINE_Twitter_Fetcher::fetch_topic_tweets( $current_item, $options );
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Twitter / X News', 'wsh-ai-news-editor' ); ?></h1>
			<p><?php esc_html_e( 'Track selected accounts and topics from X and turn tweets into AI news drafts.', 'wsh-ai-news-editor' ); ?></p>

			<?php if ( empty( $options['twitter_bearer_token'] ) ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Twitter / X Bearer Token is not configured. Go to Settings first.', 'wsh-ai-news-editor' ); ?></p>
				</div>
			<?php else : ?>

				<h2 class="nav-tab-wrapper" style="margin-top:20px;">
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wsh-ai-news-editor-twitter-news', 'x_mode' => 'accounts' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo 'accounts' === $mode ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'Accounts', 'wsh-ai-news-editor' ); ?>
					</a>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wsh-ai-news-editor-twitter-news', 'x_mode' => 'topics' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo 'topics' === $mode ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'Topics', 'wsh-ai-news-editor' ); ?>
					</a>
				</h2>

				<div style="margin:16px 0;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
						<?php wp_nonce_field( 'wsh_aine_refresh_twitter_news' ); ?>
						<input type="hidden" name="action" value="wsh_aine_refresh_twitter_news" />
						<input type="hidden" name="x_mode" value="<?php echo esc_attr( $mode ); ?>" />
						<input type="hidden" name="x_item" value="<?php echo esc_attr( $current_item_slug ); ?>" />
						<button type="submit" class="button">
							<?php esc_html_e( 'Refresh tweets', 'wsh-ai-news-editor' ); ?>
						</button>
					</form>
				</div>

				<?php if ( ! empty( $_GET['refreshed'] ) ) : ?>
					<div class="notice notice-success is-dismissible">
						<p><?php esc_html_e( 'Twitter / X feed refreshed successfully.', 'wsh-ai-news-editor' ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $list ) ) : ?>
					<h2 class="nav-tab-wrapper" style="margin-top:16px;">
						<?php foreach ( $list as $entry ) : ?>
							<?php
							$url = add_query_arg(
								array(
									'page'   => 'wsh-ai-news-editor-twitter-news',
									'x_mode' => $mode,
									'x_item' => sanitize_title( $entry['name'] ?? '' ),
								),
								admin_url( 'admin.php' )
							);
							?>
							<a href="<?php echo esc_url( $url ); ?>" class="nav-tab <?php echo sanitize_title( $entry['name'] ?? '' ) === $current_item_slug ? 'nav-tab-active' : ''; ?>">
								<?php echo esc_html( $entry['name'] ?? '' ); ?>
							</a>
						<?php endforeach; ?>
					</h2>
				<?php endif; ?>

				<div style="margin-top:20px;">
					<?php if ( empty( $tweets ) ) : ?>
						<div class="wsh-aine-empty-state">
							<?php esc_html_e( 'No tweets found right now for this source.', 'wsh-ai-news-editor' ); ?>
						</div>
					<?php else : ?>
						<div class="wsh-aine-news-list">
							<?php foreach ( $tweets as $tweet ) : ?>
								<?php self::render_tweet_card( $tweet ); ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

			<?php endif; ?>
		</div>
		<?php
	}

	protected static function render_tweet_card( array $tweet ) : void {
		?>
		<div class="wsh-aine-news-card">
			<div class="wsh-aine-news-card__media">
				<?php if ( ! empty( $tweet['media_url'] ) ) : ?>
					<img src="<?php echo esc_url( $tweet['media_url'] ); ?>" alt="<?php echo esc_attr( $tweet['text'] ?? '' ); ?>" />
				<?php elseif ( ! empty( $tweet['author_avatar'] ) ) : ?>
					<img src="<?php echo esc_url( $tweet['author_avatar'] ); ?>" alt="<?php echo esc_attr( $tweet['author_name'] ?? '' ); ?>" />
				<?php else : ?>
					<div class="wsh-aine-news-card__noimage"><?php esc_html_e( 'No image', 'wsh-ai-news-editor' ); ?></div>
				<?php endif; ?>
			</div>

			<div class="wsh-aine-news-card__body">
				<div class="wsh-aine-news-card__top">
					<span class="wsh-aine-badge"><?php echo esc_html( $tweet['author_name'] ?: 'X / Twitter' ); ?></span>
					<?php if ( ! empty( $tweet['created_at'] ) ) : ?>
						<span class="wsh-aine-date"><?php echo esc_html( $tweet['created_at'] ); ?></span>
					<?php endif; ?>
				</div>

				<h3 class="wsh-aine-news-card__title"><?php echo esc_html( '@' . ltrim( (string) ( $tweet['author_username'] ?? '' ), '@' ) ); ?></h3>

				<?php if ( ! empty( $tweet['text'] ) ) : ?>
					<p class="wsh-aine-news-card__excerpt"><?php echo esc_html( wp_html_excerpt( $tweet['text'], 320, '...' ) ); ?></p>
				<?php endif; ?>

				<div class="wsh-aine-news-card__linkmeta">
					<?php
					printf(
						'❤ %s • ↻ %s • 💬 %s • ❝ %s',
						number_format_i18n( (int) ( $tweet['like_count'] ?? 0 ) ),
						number_format_i18n( (int) ( $tweet['retweet_count'] ?? 0 ) ),
						number_format_i18n( (int) ( $tweet['reply_count'] ?? 0 ) ),
						number_format_i18n( (int) ( $tweet['quote_count'] ?? 0 ) )
					);
					?>
				</div>

				<div class="wsh-aine-news-card__actions">
					<?php if ( ! empty( $tweet['tweet_url'] ) ) : ?>
						<a href="<?php echo esc_url( $tweet['tweet_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button">
							<?php esc_html_e( 'Pogledaj tweet', 'wsh-ai-news-editor' ); ?>
						</a>
					<?php endif; ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-flex;">
						<?php wp_nonce_field( 'wsh_aine_prepare_twitter_ai' ); ?>
						<input type="hidden" name="action" value="wsh_aine_prepare_twitter_ai" />
						<input type="hidden" name="item_tweet_id" value="<?php echo esc_attr( $tweet['tweet_id'] ?? '' ); ?>" />
						<input type="hidden" name="item_text" value="<?php echo esc_attr( $tweet['text'] ?? '' ); ?>" />
						<input type="hidden" name="item_author_name" value="<?php echo esc_attr( $tweet['author_name'] ?? '' ); ?>" />
						<input type="hidden" name="item_author_username" value="<?php echo esc_attr( $tweet['author_username'] ?? '' ); ?>" />
						<input type="hidden" name="item_media_url" value="<?php echo esc_attr( $tweet['media_url'] ?? '' ); ?>" />
						<input type="hidden" name="item_tweet_url" value="<?php echo esc_attr( $tweet['tweet_url'] ?? '' ); ?>" />
						<input type="hidden" name="item_embed_url" value="<?php echo esc_attr( $tweet['embed_url'] ?? '' ); ?>" />
						<input type="hidden" name="item_created_at" value="<?php echo esc_attr( $tweet['created_at'] ?? '' ); ?>" />
						<input type="hidden" name="item_like_count" value="<?php echo esc_attr( $tweet['like_count'] ?? 0 ); ?>" />
						<input type="hidden" name="item_retweet_count" value="<?php echo esc_attr( $tweet['retweet_count'] ?? 0 ); ?>" />
						<input type="hidden" name="item_reply_count" value="<?php echo esc_attr( $tweet['reply_count'] ?? 0 ); ?>" />
						<input type="hidden" name="item_quote_count" value="<?php echo esc_attr( $tweet['quote_count'] ?? 0 ); ?>" />
						<input type="hidden" name="item_source_name" value="<?php echo esc_attr( $tweet['source_name'] ?? '' ); ?>" />

						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Generiši AI vest', 'wsh-ai-news-editor' ); ?>
						</button>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}
