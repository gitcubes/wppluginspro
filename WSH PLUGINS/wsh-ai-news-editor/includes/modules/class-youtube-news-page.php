<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_YouTube_News_Page {

	public static function init() : void {
		add_action( 'admin_post_wsh_aine_prepare_youtube_ai', array( __CLASS__, 'handle_prepare_ai' ) );
	}

	public static function handle_prepare_ai() : void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Forbidden', 'wsh-ai-news-editor' ) );
		}

		check_admin_referer( 'wsh_aine_prepare_youtube_ai' );

		$item = array(
			'title'         => isset( $_POST['item_title'] ) ? sanitize_text_field( wp_unslash( $_POST['item_title'] ) ) : '',
			'description'   => isset( $_POST['item_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['item_description'] ) ) : '',
			'thumbnail'     => isset( $_POST['item_thumbnail'] ) ? esc_url_raw( wp_unslash( $_POST['item_thumbnail'] ) ) : '',
			'video_url'     => isset( $_POST['item_video_url'] ) ? esc_url_raw( wp_unslash( $_POST['item_video_url'] ) ) : '',
			'embed_url'     => isset( $_POST['item_embed_url'] ) ? esc_url_raw( wp_unslash( $_POST['item_embed_url'] ) ) : '',
			'channel_title' => isset( $_POST['item_channel_title'] ) ? sanitize_text_field( wp_unslash( $_POST['item_channel_title'] ) ) : '',
			'published_at'  => isset( $_POST['item_published_at'] ) ? sanitize_text_field( wp_unslash( $_POST['item_published_at'] ) ) : '',
			'comments_json' => isset( $_POST['item_comments_json'] ) ? wp_unslash( $_POST['item_comments_json'] ) : '[]',
		);

		$comments = json_decode( $item['comments_json'], true );
		if ( ! is_array( $comments ) ) {
			$comments = array();
		}

		$payload = WSH_AINE_AI_Payload::normalize(
			array(
				'source_type'  => 'youtube',
				'source_name'  => $item['channel_title'],
				'origin_title' => $item['title'],
				'origin_url'   => $item['video_url'],
				'origin_text'  => self::build_origin_text( $item['description'], $comments ),
				'origin_html'  => '',
				'image'        => $item['thumbnail'],
				'author_name'  => $item['channel_title'],
				'published_at' => $item['published_at'],
				'embed_type'   => 'youtube',
				'embed_url'    => $item['embed_url'],
				'extra'        => array(
					'comments'      => $comments,
					'channel_title' => $item['channel_title'],
				),
			)
		);

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

	protected static function build_origin_text( string $description, array $comments ) : string {
		$parts = array();

		if ( '' !== trim( $description ) ) {
			$parts[] = "Opis videa:\n" . $description;
		}

		if ( ! empty( $comments ) ) {
			$parts[] = "Najvažniji komentari publike:";
			foreach ( $comments as $comment ) {
				$author = $comment['author'] ?? '';
				$text   = $comment['text'] ?? '';

				if ( '' !== trim( $text ) ) {
					$parts[] = '- ' . ( $author ? $author . ': ' : '' ) . $text;
				}
			}
		}

		return implode( "\n\n", $parts );
	}

	public static function render() : void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Forbidden', 'wsh-ai-news-editor' ) );
		}

		//$options = get_option( 'wsh_aine_settings', array() );
		//$videos  = WSH_AINE_YouTube_Fetcher::fetch_popular_videos( $options );
		$options = get_option( 'wsh_aine_settings', array() );
		$selected_categories = isset( $options['youtube_categories'] ) && is_array( $options['youtube_categories'] )
			? array_values( array_map( 'strval', $options['youtube_categories'] ) )
			: array( '25', '17', '24', '28' );

		$available_categories = WSH_AINE_YouTube_Fetcher::get_available_categories();
		$filtered_categories = array();
		foreach ( $selected_categories as $cat_id ) {
			if ( isset( $available_categories[ $cat_id ] ) ) {
				$filtered_categories[ $cat_id ] = $available_categories[ $cat_id ];
			}
		}

		$current_category = isset( $_GET['yt_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['yt_cat'] ) ) : '';
		if ( '' === $current_category && ! empty( $filtered_categories ) ) {
			$keys = array_keys( $filtered_categories );
			$current_category = (string) $keys[0];
		}
		if ( '' !== $current_category && ! isset( $filtered_categories[ $current_category ] ) ) {
			$keys = array_keys( $filtered_categories );
			$current_category = ! empty( $keys[0] ) ? (string) $keys[0] : '';
		}
		$videos = WSH_AINE_YouTube_Fetcher::fetch_popular_videos( $options, $current_category );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'YouTube News', 'wsh-ai-news-editor' ); ?></h1>
			<p><?php esc_html_e( 'Browse popular YouTube videos and turn them into AI news drafts.', 'wsh-ai-news-editor' ); ?></p>

			<?php if ( ! empty( $filtered_categories ) ) : ?>
			<h2 class="nav-tab-wrapper" style="margin-top:20px;">
				<?php foreach ( $filtered_categories as $cat_id => $cat_label ) : ?>
					<?php
					$tab_url = add_query_arg(
						array(
							'page'   => 'wsh-ai-news-editor-youtube-news',
							'yt_cat' => $cat_id,
						),
						admin_url( 'admin.php' )
					);
					?>
					<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo ( (string) $cat_id === (string) $current_category ) ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $cat_label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>
			<?php endif; ?>

			<?php if ( empty( $filtered_categories ) ) : ?>
				<div class="notice notice-warning" style="margin-top:20px;">
					<p><?php esc_html_e( 'No YouTube categories selected. Go to Settings and choose at least one category.', 'wsh-ai-news-editor' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( empty( $options['youtube_api_key'] ) ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'YouTube API key is not configured. Go to Settings first.', 'wsh-ai-news-editor' ); ?></p>
				</div>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsh-ai-news-editor-settings' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Open Settings', 'wsh-ai-news-editor' ); ?>
					</a>
				</p>
			<?php elseif ( empty( $videos ) ) : ?>
				<div class="wsh-aine-empty-state">
					<?php esc_html_e( 'No YouTube videos found right now.', 'wsh-ai-news-editor' ); ?>
				</div>
			<?php else : ?>
				<div class="wsh-aine-news-list" style="margin-top:20px;">
					<?php foreach ( $videos as $video ) : ?>
						<?php self::render_video_card( $video, $options ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	protected static function render_video_card( array $video, array $settings ) : void {
		$comments = WSH_AINE_YouTube_Fetcher::fetch_video_comments( $video['video_id'] ?? '', $settings );
		?>
		<div class="wsh-aine-news-card">
			<div class="wsh-aine-news-card__media">
				<?php if ( ! empty( $video['thumbnail'] ) ) : ?>
					<img src="<?php echo esc_url( $video['thumbnail'] ); ?>" alt="<?php echo esc_attr( $video['title'] ?? '' ); ?>" />
				<?php else : ?>
					<div class="wsh-aine-news-card__noimage"><?php esc_html_e( 'No image', 'wsh-ai-news-editor' ); ?></div>
				<?php endif; ?>
			</div>

			<div class="wsh-aine-news-card__body">
				<div class="wsh-aine-news-card__top">
					<span class="wsh-aine-badge"><?php echo esc_html( $video['channel_title'] ?? 'YouTube' ); ?></span>
					<?php if ( ! empty( $video['published_at'] ) ) : ?>
						<span class="wsh-aine-date"><?php echo esc_html( $video['published_at'] ); ?></span>
					<?php endif; ?>
				</div>

				<h3 class="wsh-aine-news-card__title"><?php echo esc_html( $video['title'] ?? '' ); ?></h3>

				<?php if ( ! empty( $video['description'] ) ) : ?>
					<p class="wsh-aine-news-card__excerpt">
						<?php echo esc_html( wp_html_excerpt( $video['description'], 260, '...' ) ); ?>
					</p>
				<?php endif; ?>

				<div class="wsh-aine-news-card__linkmeta">
					<?php
					printf(
						'%s • %s • %s',
						esc_html__( 'Views', 'wsh-ai-news-editor' ) . ': ' . number_format_i18n( (int) ( $video['view_count'] ?? 0 ) ),
						esc_html__( 'Likes', 'wsh-ai-news-editor' ) . ': ' . number_format_i18n( (int) ( $video['like_count'] ?? 0 ) ),
						esc_html__( 'Comments', 'wsh-ai-news-editor' ) . ': ' . number_format_i18n( (int) ( $video['comment_count'] ?? 0 ) )
					);
					?>
				</div>

				<div class="wsh-aine-news-card__actions">
					<?php if ( ! empty( $video['video_url'] ) ) : ?>
						<a href="<?php echo esc_url( $video['video_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button">
							<?php esc_html_e( 'Pogledaj video', 'wsh-ai-news-editor' ); ?>
						</a>
					<?php endif; ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-flex;">
						<?php wp_nonce_field( 'wsh_aine_prepare_youtube_ai' ); ?>
						<input type="hidden" name="action" value="wsh_aine_prepare_youtube_ai" />
						<input type="hidden" name="item_title" value="<?php echo esc_attr( $video['title'] ?? '' ); ?>" />
						<input type="hidden" name="item_description" value="<?php echo esc_attr( $video['description'] ?? '' ); ?>" />
						<input type="hidden" name="item_thumbnail" value="<?php echo esc_attr( $video['thumbnail'] ?? '' ); ?>" />
						<input type="hidden" name="item_video_url" value="<?php echo esc_attr( $video['video_url'] ?? '' ); ?>" />
						<input type="hidden" name="item_embed_url" value="<?php echo esc_attr( $video['embed_url'] ?? '' ); ?>" />
						<input type="hidden" name="item_channel_title" value="<?php echo esc_attr( $video['channel_title'] ?? '' ); ?>" />
						<input type="hidden" name="item_published_at" value="<?php echo esc_attr( $video['published_at'] ?? '' ); ?>" />
						<input type="hidden" name="item_comments_json" value="<?php echo esc_attr( wp_json_encode( $comments ) ); ?>" />

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
