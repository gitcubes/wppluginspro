<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Perplexity_News_Page {

	public static function init() : void {
		add_action( 'admin_post_wsh_aine_prepare_perplexity_ai', array( __CLASS__, 'handle_prepare_ai' ) );
	}

	public static function handle_prepare_ai() : void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Forbidden', 'wsh-ai-news-editor' ) );
		}

		check_admin_referer( 'wsh_aine_prepare_perplexity_ai' );

		$query          = isset( $_POST['perplexity_query'] ) ? sanitize_text_field( wp_unslash( $_POST['perplexity_query'] ) ) : '';
		$summary        = isset( $_POST['perplexity_summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['perplexity_summary'] ) ) : '';
		$items_json     = isset( $_POST['perplexity_items_json'] ) ? wp_unslash( $_POST['perplexity_items_json'] ) : '[]';
		$citations_json = isset( $_POST['perplexity_citations_json'] ) ? wp_unslash( $_POST['perplexity_citations_json'] ) : '[]';

		$items = json_decode( $items_json, true );
		if ( ! is_array( $items ) ) {
			$items = array();
		}

		$citations = json_decode( $citations_json, true );
		if ( ! is_array( $citations ) ) {
			$citations = array();
		}
		$citations = array_values( array_unique( array_filter( array_map( 'esc_url_raw', $citations ) ) ) );

		$lines = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$title   = isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : '';
			$url     = isset( $item['url'] ) ? esc_url_raw( (string) $item['url'] ) : '';
			$source  = isset( $item['source'] ) ? sanitize_text_field( (string) $item['source'] ) : '';
			$snippet = isset( $item['snippet'] ) ? sanitize_textarea_field( (string) $item['snippet'] ) : '';

			$block = array_filter( array(
				$title,
				$source ? 'Izvor: ' . $source : '',
				$snippet,
				$url,
			) );

			if ( ! empty( $block ) ) {
				$lines[] = implode( "\n", $block );
			}
		}

		$origin_text = "Perplexity tema: {$query}\n\nSažetak:\n{$summary}";
		if ( ! empty( $lines ) ) {
			$origin_text .= "\n\nRelevantni web izvori:\n\n" . implode( "\n\n---\n\n", $lines );
		}

		if ( ! empty( $citations ) ) {
			$origin_text .= "\n\nCitations:\n- " . implode( "\n- ", $citations );
		}

		$origin_html = '';
		if ( ! empty( $citations ) ) {
			$origin_html .= '<h3>Citations</h3><ol>';
			foreach ( $citations as $citation_url ) {
				$origin_html .= '<li><a href="' . esc_url( $citation_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $citation_url ) . '</a></li>';
			}
			$origin_html .= '</ol>';
		}

		$payload = WSH_AINE_AI_Payload::normalize(
			array(
				'source_type'  => 'perplexity',
				'source_name'  => 'Perplexity',
				'origin_title' => $query,
				'origin_url'   => '',
				'origin_text'  => $origin_text,
				'origin_html'  => $origin_html,
				'image'        => '',
				'author_name'  => 'Perplexity',
				'published_at' => current_time( 'mysql' ),
				'embed_type'   => 'web_research',
				'embed_url'    => '',
				'extra'        => array(
					'query' => $query,
					'summary' => $summary,
					'items' => $items,
					'citations' => $citations,
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

	public static function render() : void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Forbidden', 'wsh-ai-news-editor' ) );
		}

		$module = WSH_AINE_Module_Registry::get_module('wsh-ai-news-editor-perplexity-news');
		if ( ! WSH_AINE_Access::can_access_module($module) ) {
			WSH_AINE_Admin_Notices::locked_module('Perplexity AI', 'PRO');
			return;
		}

		$options = get_option( 'wsh_aine_settings', array() );
		$query   = isset( $_GET['perplexity_query'] ) ? sanitize_text_field( wp_unslash( $_GET['perplexity_query'] ) ) : '';
		$result  = null;
		$error   = '';

		if ( '' !== $query ) {
			$result = WSH_AINE_Perplexity_Fetcher::search( $query, $options );
			if ( is_wp_error( $result ) ) {
				$error = $result->get_error_message();
				$result = null;
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Perplexity News', 'wsh-ai-news-editor' ); ?></h1>
			<p><?php esc_html_e( 'Search the web through Perplexity and turn grounded results into an AI news draft.', 'wsh-ai-news-editor' ); ?></p>

			<?php if ( empty( $options['perplexity_api_key'] ) ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Perplexity API key is not configured. Go to Settings first.', 'wsh-ai-news-editor' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-top:16px;">
				<input type="hidden" name="page" value="wsh-ai-news-editor-perplexity-news" />
				<input type="text" name="perplexity_query" value="<?php echo esc_attr( $query ); ?>" class="regular-text" style="width:420px;" placeholder="<?php echo esc_attr__( 'Unesi temu za web pretragu...', 'wsh-ai-news-editor' ); ?>" />
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Search', 'wsh-ai-news-editor' ); ?></button>
			</form>

			<?php if ( $error ) : ?>
				<div class="notice notice-error" style="margin-top:20px;">
					<p><?php echo esc_html( $error ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( is_array( $result ) ) : ?>
				<div class="wsh-aine-panel" style="margin-top:20px;">
					<h2><?php esc_html_e( 'Perplexity Summary', 'wsh-ai-news-editor' ); ?></h2>
					<p><?php echo nl2br( esc_html( $result['summary'] ?? '' ) ); ?></p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;">
						<?php wp_nonce_field( 'wsh_aine_prepare_perplexity_ai' ); ?>
						<input type="hidden" name="action" value="wsh_aine_prepare_perplexity_ai" />
						<input type="hidden" name="perplexity_query" value="<?php echo esc_attr( $result['query'] ?? '' ); ?>" />
						<input type="hidden" name="perplexity_summary" value="<?php echo esc_attr( $result['summary'] ?? '' ); ?>" />
						<input type="hidden" name="perplexity_items_json" value="<?php echo esc_attr( wp_json_encode( $result['items'] ?? array() ) ); ?>" />
						<input type="hidden" name="perplexity_citations_json" value="<?php echo esc_attr( wp_json_encode( $result['citations'] ?? array() ) ); ?>" />
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Generiši AI vest', 'wsh-ai-news-editor' ); ?>
						</button>
					</form>
				</div>

				<?php if ( ! empty( $result['citations'] ) ) : ?>
					<div class="wsh-aine-panel" style="margin-top:20px;">
						<h2><?php esc_html_e( 'Citations', 'wsh-ai-news-editor' ); ?></h2>
						<ol style="margin-left:20px;">
							<?php foreach ( array_values( array_unique( array_filter( $result['citations'] ) ) ) as $citation_url ) : ?>
								<li style="margin-bottom:8px;word-break:break-word;">
									<a href="<?php echo esc_url( $citation_url ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( $citation_url ); ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ol>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $result['items'] ) ) : ?>
					<div class="wsh-aine-news-list" style="margin-top:20px;">
						<?php foreach ( $result['items'] as $item ) : ?>
							<div class="wsh-aine-news-card">
								<div class="wsh-aine-news-card__body" style="padding-left:0;">
									<div class="wsh-aine-news-card__top">
										<span class="wsh-aine-badge"><?php echo esc_html( $item['source'] ?? 'Source' ); ?></span>
									</div>
									<h3 class="wsh-aine-news-card__title"><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
									<?php if ( ! empty( $item['snippet'] ) ) : ?>
										<p class="wsh-aine-news-card__excerpt"><?php echo esc_html( $item['snippet'] ); ?></p>
									<?php endif; ?>
									<?php if ( ! empty( $item['url'] ) ) : ?>
										<div class="wsh-aine-news-card__actions">
											<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="button">
												<?php esc_html_e( 'Pogledaj izvor', 'wsh-ai-news-editor' ); ?>
											</a>
										</div>
									<?php endif; ?>
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