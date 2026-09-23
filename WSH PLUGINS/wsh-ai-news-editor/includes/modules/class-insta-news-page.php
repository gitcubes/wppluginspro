<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Insta_News_Page {

	public static function init() : void {
		add_action( 'admin_post_wsh_aine_prepare_instagram_ai', array( __CLASS__, 'handle_prepare_ai' ) );
		add_action( 'admin_post_wsh_aine_refresh_instagram_news', array( __CLASS__, 'handle_refresh' ) );
	}

	public static function handle_prepare_ai() : void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Forbidden', 'wsh-ai-news-editor' ) );
		}

		check_admin_referer( 'wsh_aine_prepare_instagram_ai' );

		$item = array(
			'post_id'         => isset( $_POST['item_post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['item_post_id'] ) ) : '',
			'shortcode'       => isset( $_POST['item_shortcode'] ) ? sanitize_text_field( wp_unslash( $_POST['item_shortcode'] ) ) : '',
			'post_url'        => isset( $_POST['item_post_url'] ) ? esc_url_raw( wp_unslash( $_POST['item_post_url'] ) ) : '',
			'caption'         => isset( $_POST['item_caption'] ) ? sanitize_textarea_field( wp_unslash( $_POST['item_caption'] ) ) : '',
			'author_name'     => isset( $_POST['item_author_name'] ) ? sanitize_text_field( wp_unslash( $_POST['item_author_name'] ) ) : '',
			'author_username' => isset( $_POST['item_author_username'] ) ? sanitize_text_field( wp_unslash( $_POST['item_author_username'] ) ) : '',
			'media_type'      => isset( $_POST['item_media_type'] ) ? sanitize_key( wp_unslash( $_POST['item_media_type'] ) ) : 'image',
			'thumbnail_url'   => isset( $_POST['item_thumbnail_url'] ) ? esc_url_raw( wp_unslash( $_POST['item_thumbnail_url'] ) ) : '',
			'media_url'       => isset( $_POST['item_media_url'] ) ? esc_url_raw( wp_unslash( $_POST['item_media_url'] ) ) : '',
			'published_at'    => isset( $_POST['item_published_at'] ) ? sanitize_text_field( wp_unslash( $_POST['item_published_at'] ) ) : '',
			'like_count'      => isset( $_POST['item_like_count'] ) ? absint( $_POST['item_like_count'] ) : 0,
			'comment_count'   => isset( $_POST['item_comment_count'] ) ? absint( $_POST['item_comment_count'] ) : 0,
			'view_count'      => isset( $_POST['item_view_count'] ) ? absint( $_POST['item_view_count'] ) : 0,
			'source_name'     => isset( $_POST['item_source_name'] ) ? sanitize_text_field( wp_unslash( $_POST['item_source_name'] ) ) : '',
		);

        if(empty($item['author_name']) && !empty($item['source_name'])) $item['author_name'] = $item['source_name'];
        if(empty($item['author_username']) && !empty($item['source_name'])) $item['author_username'] = $item['source_name'];

		if ( empty( $item['post_url'] ) && empty( $item['caption'] ) ) {
			wp_die( esc_html__( 'Instagram post nema URL ni caption, pa AI draft ne može da se napravi.', 'wsh-ai-news-editor' ) );
		}

		if(empty($item['author_name']) && !empty($item['source_name'])) {
			$item['author_name'] = $item['source_name'];
		}

		if(empty($item['author_username']) && !empty($item['source_name'])) {
			$item['author_username'] = $item['source_name'];
		}

		if ( empty( $item['post_url'] ) && empty( $item['caption'] ) ) {
			wp_die( esc_html__( 'Instagram post nema URL ni caption, pa AI draft ne može da se napravi.', 'wsh-ai-news-editor' ) );
		}

		$origin_title = wp_html_excerpt( $item['caption'], 120, '...' );

		if ( empty( $origin_title ) ) {
			$origin_title = ! empty( $item['author_username'] )
				? 'Instagram post @' . ltrim( $item['author_username'], '@' )
				: 'Instagram post';
		}

		$payload = WSH_AINE_AI_Payload::normalize(
			array(
				'source_type'  => 'instagram',
				'source_name'  => $item['source_name'] ? $item['source_name'] : 'Instagram',
				'origin_title' => $origin_title,
				'origin_url'   => $item['post_url'],
				'origin_text'  => WSH_AINE_Instagram_Fetcher::build_origin_text( $item ),
				'origin_html'  => '',
				'image'        => $item['thumbnail_url'],
				'author_name'  => $item['author_name'],
				'published_at' => $item['published_at'],
				'embed_type'   => 'instagram',
				'embed_url'    => $item['post_url'],
				'extra'        => array(
					'post_id'         => $item['post_id'],
					'shortcode'       => $item['shortcode'],
					'author_username' => $item['author_username'],
					'media_type'      => $item['media_type'],
					'like_count'      => $item['like_count'],
					'comment_count'   => $item['comment_count'],
					'view_count'      => $item['view_count'],
					'embed_html'      => self::build_instagram_embed_html( $item['post_url'] ),
				),
			)
		);

		$token = WSH_AINE_AI_Drafts::store_seed( $payload );

		if ( empty( $token ) ) {
			wp_die(
				'<pre>' . esc_html( print_r( array(
					'message' => 'Instagram AI seed nije kreiran.',
					'payload' => $payload,
				), true ) ) . '</pre>'
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'wsh-ai-news-editor-ai-editor',
					'seed' => $token,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;

	}

	public static function handle_refresh() : void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Forbidden', 'wsh-ai-news-editor' ) );
		}

		check_admin_referer( 'wsh_aine_refresh_instagram_news' );

		$options = get_option( 'wsh_aine_settings', array() );
		$item_slug = isset( $_POST['insta_item'] ) ? sanitize_text_field( wp_unslash( $_POST['insta_item'] ) ) : '';

		$list = isset( $options['instagram_accounts'] ) && is_array( $options['instagram_accounts'] ) ? $options['instagram_accounts'] : array();

		foreach ( $list as $entry ) {
			if ( empty( $entry['active'] ) || empty( $entry['url'] ) ) {
				continue;
			}

			if ( sanitize_title( $entry['name'] ?? '' ) === $item_slug ) {
				WSH_AINE_Instagram_Fetcher::clear_cache_for_source( $entry, $options );
				break;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'wsh-ai-news-editor-insta-news',
					'insta_item'=> $item_slug,
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

		$module = WSH_AINE_Module_Registry::get_module( 'wsh-ai-news-editor-insta-news' );
		if ( ! WSH_AINE_Access::can_access_module( $module ) ) {
			WSH_AINE_Admin_Notices::locked_module( 'Insta News', 'PRO' );
			return;
		}

		$options = get_option( 'wsh_aine_settings', array() );

		$accounts = isset( $options['instagram_accounts'] ) && is_array( $options['instagram_accounts'] )
			? array_values( array_filter( $options['instagram_accounts'], function( $item ) {
				return ! empty( $item['active'] ) && ! empty( $item['url'] );
			} ) )
			: array();

		$current_item_slug = isset( $_GET['insta_item'] ) ? sanitize_text_field( wp_unslash( $_GET['insta_item'] ) ) : '';

		if ( '' === $current_item_slug && ! empty( $accounts[0]['name'] ) ) {
			$current_item_slug = sanitize_title( $accounts[0]['name'] );
		}

		$current_item = null;
		foreach ( $accounts as $entry ) {
			if ( sanitize_title( $entry['name'] ?? '' ) === $current_item_slug ) {
				$current_item = $entry;
				break;
			}
		}

		if ( ! $current_item && ! empty( $accounts ) ) {
			$current_item = $accounts[0];
			$current_item_slug = sanitize_title( $current_item['name'] ?? '' );
		}

		$posts = array();
		if ( $current_item ) {
			$posts = WSH_AINE_Instagram_Fetcher::fetch_account_posts( $current_item, $options );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Insta News', 'wsh-ai-news-editor' ); ?></h1>
			<p><?php esc_html_e( 'Track Instagram accounts and turn posts into AI news drafts.', 'wsh-ai-news-editor' ); ?></p>

			<?php if ( ! WSH_AINE_BrightData_Client::is_configured( $options ) ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Bright Data is not configured. Go to Settings → Instagram first.', 'wsh-ai-news-editor' ); ?></p>
				</div>
			<?php else : ?>

				<div style="margin:16px 0;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
						<?php wp_nonce_field( 'wsh_aine_refresh_instagram_news' ); ?>
						<input type="hidden" name="action" value="wsh_aine_refresh_instagram_news" />
						<input type="hidden" name="insta_item" value="<?php echo esc_attr( $current_item_slug ); ?>" />
						<button type="submit" class="button">
							<?php esc_html_e( 'Refresh posts', 'wsh-ai-news-editor' ); ?>
						</button>
					</form>
				</div>

				<?php if ( ! empty( $_GET['refreshed'] ) ) : ?>
					<div class="notice notice-success is-dismissible">
						<p><?php esc_html_e( 'Instagram feed refreshed successfully.', 'wsh-ai-news-editor' ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $accounts ) ) : ?>
					<h2 class="nav-tab-wrapper" style="margin-top:16px;">
						<?php foreach ( $accounts as $entry ) : ?>
							<?php
							$url = add_query_arg(
								array(
									'page'       => 'wsh-ai-news-editor-insta-news',
									'insta_item' => sanitize_title( $entry['name'] ?? '' ),
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
					<?php if ( empty( $posts ) ) : ?>
						<div class="wsh-aine-empty-state">
							<?php esc_html_e( 'No Instagram posts found right now for this account.', 'wsh-ai-news-editor' ); ?>
						</div>
					<?php else : ?>
						<div class="wsh-aine-news-list">
							<?php foreach ( $posts as $post ) : ?>
								<?php self::render_post_card( $post ); ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

			<?php endif; ?>
		</div>
		<?php
	}

	protected static function render_post_card( array $post ) : void {
		?>
		<div class="wsh-aine-news-card">
            <div class="wsh-aine-news-card__media">
                <?php if ( ! empty( $post['thumbnail_url'] ) ) : ?>
                    <img 
                        src="<?php echo esc_url( $post['thumbnail_url'] ); ?>" 
                        alt="<?php echo esc_attr( $post['caption'] ?? '' ); ?>"
                        loading="lazy"
                        referrerpolicy="no-referrer"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                    />
                    <div class="wsh-aine-news-card__noimage" style="display:none;">
                        <?php esc_html_e( 'Image unavailable', 'wsh-ai-news-editor' ); ?>
                    </div>
                <?php else : ?>
                    <div class="wsh-aine-news-card__noimage"><?php esc_html_e( 'No image', 'wsh-ai-news-editor' ); ?></div>
                <?php endif; ?>
            </div>

			<!-- <div class="wsh-aine-news-card__media">
				<?php if ( ! empty( $post['thumbnail_url'] ) ) : ?>
					<img src="<?php echo esc_url( $post['thumbnail_url'] ); ?>" alt="" />
				<?php else : ?>
					<div class="wsh-aine-news-card__noimage"><?php esc_html_e( 'No image', 'wsh-ai-news-editor' ); ?></div>
				<?php endif; ?>
			</div> -->

			<div class="wsh-aine-news-card__body">
				<div class="wsh-aine-news-card__top">
					<span class="wsh-aine-badge"><?php echo esc_html( $post['author_name'] ?: 'Instagram' ); ?></span>
					<?php if ( ! empty( $post['published_at'] ) ) : ?>
						<span class="wsh-aine-date"><?php echo esc_html( $post['published_at'] ); ?></span>
					<?php endif; ?>
				</div>

				<h3 class="wsh-aine-news-card__title"><?php echo esc_html( '@' . ltrim( (string) ( $post['author_username'] ?? '' ), '@' ) ); ?></h3>

				<?php if ( ! empty( $post['caption'] ) ) : ?>
					<p class="wsh-aine-news-card__excerpt"><?php echo esc_html( wp_html_excerpt( $post['caption'], 320, '...' ) ); ?></p>
				<?php endif; ?>

				<div class="wsh-aine-news-card__linkmeta">
					<?php
					printf(
						'❤ %s • 💬 %s • ▶ %s • %s',
						number_format_i18n( (int) ( $post['like_count'] ?? 0 ) ),
						number_format_i18n( (int) ( $post['comment_count'] ?? 0 ) ),
						number_format_i18n( (int) ( $post['view_count'] ?? 0 ) ),
						esc_html( ucfirst( (string) ( $post['media_type'] ?? 'image' ) ) )
					);
					?>
				</div>

				<div class="wsh-aine-news-card__actions">
					<?php if ( ! empty( $post['post_url'] ) ) : ?>
						<a href="<?php echo esc_url( $post['post_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button">
							<?php esc_html_e( 'Pogledaj post', 'wsh-ai-news-editor' ); ?>
						</a>
					<?php endif; ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-flex;">
						<?php wp_nonce_field( 'wsh_aine_prepare_instagram_ai' ); ?>
						<input type="hidden" name="action" value="wsh_aine_prepare_instagram_ai" />
						<input type="hidden" name="item_post_id" value="<?php echo esc_attr( $post['post_id'] ?? '' ); ?>" />
						<input type="hidden" name="item_shortcode" value="<?php echo esc_attr( $post['shortcode'] ?? '' ); ?>" />
						<input type="hidden" name="item_post_url" value="<?php echo esc_attr( $post['post_url'] ?? '' ); ?>" />
						<input type="hidden" name="item_caption" value="<?php echo esc_attr( $post['caption'] ?? '' ); ?>" />
						<input type="hidden" name="item_author_name" value="<?php echo esc_attr( $post['author_name'] ?? '' ); ?>" />
						<input type="hidden" name="item_author_username" value="<?php echo esc_attr( $post['author_username'] ?? '' ); ?>" />
						<input type="hidden" name="item_media_type" value="<?php echo esc_attr( $post['media_type'] ?? '' ); ?>" />
						<input type="hidden" name="item_thumbnail_url" value="<?php echo esc_attr( $post['thumbnail_url'] ?? '' ); ?>" />
						<input type="hidden" name="item_media_url" value="<?php echo esc_attr( $post['media_url'] ?? '' ); ?>" />
						<input type="hidden" name="item_published_at" value="<?php echo esc_attr( $post['published_at'] ?? '' ); ?>" />
						<input type="hidden" name="item_like_count" value="<?php echo esc_attr( $post['like_count'] ?? 0 ); ?>" />
						<input type="hidden" name="item_comment_count" value="<?php echo esc_attr( $post['comment_count'] ?? 0 ); ?>" />
						<input type="hidden" name="item_view_count" value="<?php echo esc_attr( $post['view_count'] ?? 0 ); ?>" />
						<input type="hidden" name="item_source_name" value="<?php echo esc_attr( $post['source_name'] ?? '' ); ?>" />

						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Generiši AI vest', 'wsh-ai-news-editor' ); ?>
						</button>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

    public static function build_instagram_embed_html( string $post_url ) : string {
        $post_url = trim( $post_url );

        if ( '' === $post_url ) {
            return '';
        }

        if ( ! preg_match( '~^https?://~i', $post_url ) ) {
            return '';
        }

        $permalink = $post_url;

        // Instagram embed obično bolje radi sa slash na kraju
        if ( '/' !== substr( $permalink, -1 ) ) {
            $permalink .= '/';
        }

        $permalink = add_query_arg(
            array(
                'utm_source'    => 'ig_embed',
                'utm_campaign'  => 'loading',
            ),
            $permalink
        );

        return '<blockquote class="instagram-media" data-instgrm-captioned data-instgrm-permalink="' . esc_url( $permalink ) . '" data-instgrm-version="14" style="background:#FFF; border:0; border-radius:3px; box-shadow:0 0 1px 0 rgba(0,0,0,0.5),0 1px 10px 0 rgba(0,0,0,0.15); margin:20px 0; max-width:540px; min-width:326px; padding:0; width:100%;"></blockquote>';
    }
}