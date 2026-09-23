<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Google_News_Page {

	public static function init() : void {
		add_action( 'admin_post_wsh_aine_prepare_google_ai', array( __CLASS__, 'handle_prepare_ai' ) );
	}

	public static function handle_prepare_ai() : void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Forbidden', 'wsh-ai-news-editor' ) );
		}

		check_admin_referer( 'wsh_aine_prepare_google_ai' );

		$item = array(
            'title'          => isset( $_POST['item_title'] ) ? sanitize_text_field( wp_unslash( $_POST['item_title'] ) ) : '',
            'url'            => isset( $_POST['item_url'] ) ? esc_url_raw( wp_unslash( $_POST['item_url'] ) ) : '',
            'image'          => isset( $_POST['item_image'] ) ? esc_url_raw( wp_unslash( $_POST['item_image'] ) ) : '',
            'description'    => isset( $_POST['item_description'] ) ? wp_kses_post( wp_unslash( $_POST['item_description'] ) ) : '',
            'content'        => isset( $_POST['item_content'] ) ? wp_kses_post( wp_unslash( $_POST['item_content'] ) ) : '',
            'date'           => isset( $_POST['item_date'] ) ? sanitize_text_field( wp_unslash( $_POST['item_date'] ) ) : '',
            'source'         => isset( $_POST['item_source'] ) ? sanitize_text_field( wp_unslash( $_POST['item_source'] ) ) : '',
            'publisher_name' => isset( $_POST['item_publisher_name'] ) ? sanitize_text_field( wp_unslash( $_POST['item_publisher_name'] ) ) : '',
            'publisher_url'  => isset( $_POST['item_publisher_url'] ) ? esc_url_raw( wp_unslash( $_POST['item_publisher_url'] ) ) : '',
        );

		$payload = self::build_google_payload( $item );
		$token   = WSH_AINE_AI_Drafts::store_seed( $payload );

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

	protected static function build_google_payload( array $item ) : array {
        $source_name = ! empty( $item['publisher_name'] ) ? $item['publisher_name'] : ( $item['source'] ?? 'Google News' );

        return WSH_AINE_AI_Payload::normalize(
            array(
                'source_type'  => 'google_news',
                'source_name'  => $source_name,
                'origin_title' => $item['title'] ?? '',
                'origin_url'   => $item['url'] ?? '',
                'origin_text'  => $item['description'] ?? '',
                'origin_html'  => $item['content'] ?? '',
                'image'        => $item['image'] ?? '',
                'author_name'  => '',
                'published_at' => $item['date'] ?? '',
                'embed_type'   => 'article',
                'embed_url'    => $item['url'] ?? '',
                'extra'        => array(
                    'publisher_name' => $item['publisher_name'] ?? '',
                    'publisher_url'  => $item['publisher_url'] ?? '',
                ),
            )
        );
    }

	public static function render() : void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Forbidden', 'wsh-ai-news-editor' ) );
		}

		$options = get_option( 'wsh_aine_settings', array() );
		$sources = isset( $options['google_sources'] ) && is_array( $options['google_sources'] ) ? $options['google_sources'] : array();

		$active_sources = array_values(
			array_filter(
				$sources,
				function ( $source ) {
					return ! empty( $source['active'] ) && ! empty( $source['url'] );
				}
			)
		);

		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		if ( '' === $current_tab && ! empty( $active_sources[0]['name'] ) ) {
			$current_tab = sanitize_title( $active_sources[0]['name'] );
		}

		$current_source = null;

		foreach ( $active_sources as $source ) {
			$tab_slug = sanitize_title( $source['name'] ?? '' );
			if ( $tab_slug === $current_tab ) {
				$current_source = $source;
				break;
			}
		}

		if ( ! $current_source && ! empty( $active_sources ) ) {
			$current_source = $active_sources[0];
			$current_tab    = sanitize_title( $current_source['name'] ?? '' );
		}

		$items = array();
		if ( $current_source ) {
			$items = WSH_AINE_RSS_Fetcher::fetch_feed_items( $current_source, 'google_news', 12 );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Google News', 'wsh-ai-news-editor' ); ?></h1>
			<p><?php esc_html_e( 'Browse configured Google News RSS categories and prepare AI article drafts.', 'wsh-ai-news-editor' ); ?></p>

			<?php if ( empty( $active_sources ) ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'No active Google News sources found. Go to Settings and add Google News RSS feeds first.', 'wsh-ai-news-editor' ); ?></p>
				</div>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsh-ai-news-editor-settings' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Open Settings', 'wsh-ai-news-editor' ); ?>
					</a>
				</p>
			<?php else : ?>
				<h2 class="nav-tab-wrapper" style="margin-top:20px;">
					<?php foreach ( $active_sources as $source ) : ?>
						<?php
						$tab_slug = sanitize_title( $source['name'] ?? '' );
						$url      = add_query_arg(
							array(
								'page' => 'wsh-ai-news-editor-google-news',
								'tab'  => $tab_slug,
							),
							admin_url( 'admin.php' )
						);
						$is_active = ( $tab_slug === $current_tab );
						?>
						<a href="<?php echo esc_url( $url ); ?>" class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>">
							<?php echo esc_html( $source['name'] ?? __( 'Category', 'wsh-ai-news-editor' ) ); ?>
						</a>
					<?php endforeach; ?>
				</h2>

				<div style="margin-top:20px;">
					<?php if ( empty( $items ) ) : ?>
						<div class="wsh-aine-empty-state">
							<?php esc_html_e( 'No articles found for this Google News category right now.', 'wsh-ai-news-editor' ); ?>
						</div>
					<?php else : ?>
						<div class="wsh-aine-news-list">
							<?php foreach ( $items as $item ) : ?>
								<?php self::render_item_card( $item ); ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	protected static function render_item_card( array $item ) : void {
		$title          = $item['title'] ?? '';
		$url            = $item['url'] ?? '';
		$image          = $item['image'] ?? '';
		$description    = $item['description'] ?? '';
		$date           = $item['date'] ?? '';
		$source         = $item['source'] ?? 'Google News';
		$display_domain = $item['display_domain'] ?? '';
		$content        = $item['content'] ?? '';
        $publisher_name = $item['publisher_name'] ?? '';
        $publisher_url  = $item['publisher_url'] ?? '';
		?>
		<div class="wsh-aine-news-card">
			<div class="wsh-aine-news-card__media">
				<?php if ( $image ) : ?>
					<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" />
				<?php else : ?>
					<div class="wsh-aine-news-card__noimage"><?php esc_html_e( 'No image', 'wsh-ai-news-editor' ); ?></div>
				<?php endif; ?>
			</div>

			<div class="wsh-aine-news-card__body">
				<div class="wsh-aine-news-card__top">
					<span class="wsh-aine-badge"><?php echo esc_html( $source ); ?></span>
					<?php if ( $date ) : ?>
						<span class="wsh-aine-date"><?php echo esc_html( $date ); ?></span>
					<?php endif; ?>
				</div>

				<h3 class="wsh-aine-news-card__title"><?php echo esc_html( $title ); ?></h3>

				<?php if ( $publisher_name || $display_domain ) : ?>
                    <div class="wsh-aine-news-card__linkmeta">
                        <?php if ( $publisher_name && $publisher_url ) : ?>
                            <a href="<?php echo esc_url( $publisher_url ); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo esc_html( $publisher_name ); ?>
                            </a>
                        <?php elseif ( $publisher_name ) : ?>
                            <?php echo esc_html( $publisher_name ); ?>
                        <?php else : ?>
                            <?php echo esc_html( $display_domain ); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

				<?php if ( $description ) : ?>
					<p class="wsh-aine-news-card__excerpt"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>

				<div class="wsh-aine-news-card__actions">
					<?php if ( $url ) : ?>
						<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" class="button">
							<?php esc_html_e( 'Pogledaj vest', 'wsh-ai-news-editor' ); ?>
						</a>
					<?php endif; ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-flex;">
						<?php wp_nonce_field( 'wsh_aine_prepare_google_ai' ); ?>
						<input type="hidden" name="action" value="wsh_aine_prepare_google_ai" />
						<input type="hidden" name="item_title" value="<?php echo esc_attr( $title ); ?>" />
						<input type="hidden" name="item_url" value="<?php echo esc_attr( $url ); ?>" />
						<input type="hidden" name="item_image" value="<?php echo esc_attr( $image ); ?>" />
						<input type="hidden" name="item_description" value="<?php echo esc_attr( $description ); ?>" />
						<input type="hidden" name="item_content" value="<?php echo esc_attr( $content ); ?>" />
						<input type="hidden" name="item_date" value="<?php echo esc_attr( $date ); ?>" />
						<input type="hidden" name="item_source" value="<?php echo esc_attr( $source ); ?>" />
                        <input type="hidden" name="item_publisher_name" value="<?php echo esc_attr( $publisher_name ); ?>" />
                        <input type="hidden" name="item_publisher_url" value="<?php echo esc_attr( $publisher_url ); ?>" />

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