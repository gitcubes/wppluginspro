<?php
if (! defined('ABSPATH')) {
    exit;
}

class WSH_AINE_Local_Media_Page
{

    public static function init() : void {
        add_action( 'admin_post_wsh_aine_prepare_local_ai', array( __CLASS__, 'handle_prepare_ai' ) );
        add_action( 'admin_post_wsh_aine_refresh_local_media', array( __CLASS__, 'handle_refresh' ) );
    }

    public static function handle_prepare_ai(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_die(esc_html__('Forbidden', 'wsh-ai-news-editor'));
        }

        check_admin_referer('wsh_aine_prepare_local_ai');

        $item = array(
            'title'       => isset( $_POST['item_title'] ) ? sanitize_text_field( wp_unslash( $_POST['item_title'] ) ) : '',
            'url'         => isset( $_POST['item_url'] ) ? esc_url_raw( wp_unslash( $_POST['item_url'] ) ) : '',
            'image'       => isset( $_POST['item_image'] ) ? esc_url_raw( wp_unslash( $_POST['item_image'] ) ) : '',
            'description' => isset( $_POST['item_description'] ) ? wp_kses_post( wp_unslash( $_POST['item_description'] ) ) : '',
            'content'     => isset( $_POST['item_content'] ) ? wp_kses_post( wp_unslash( $_POST['item_content'] ) ) : '',
            'date'        => isset( $_POST['item_date'] ) ? sanitize_text_field( wp_unslash( $_POST['item_date'] ) ) : '',
            'source'      => isset( $_POST['item_source'] ) ? sanitize_text_field( wp_unslash( $_POST['item_source'] ) ) : '',
        );

        $payload = WSH_AINE_AI_Payload::build_from_local_rss( $item );
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

    public static function render(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_die(esc_html__('Forbidden', 'wsh-ai-news-editor'));
        }

        $options = get_option('wsh_aine_settings', array());
        $sources = isset($options['local_sources']) && is_array($options['local_sources']) ? $options['local_sources'] : array();

        $active_sources = array_values(array_filter($sources, function ($source) {
            return ! empty($source['active']) && ! empty($source['url']);
        }));

    ?>
        <div class="wrap">
            <h1><?php esc_html_e('Local Media', 'wsh-ai-news-editor'); ?></h1>
            <p><?php esc_html_e('Browse your configured local media RSS feeds and prepare AI article drafts.', 'wsh-ai-news-editor'); ?></p>

            <div style="margin:16px 0;">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                    <?php wp_nonce_field( 'wsh_aine_refresh_local_media' ); ?>
                    <input type="hidden" name="action" value="wsh_aine_refresh_local_media" />
                    <button type="submit" class="button">
                        <?php esc_html_e( 'Refresh feeds', 'wsh-ai-news-editor' ); ?>
                    </button>
                </form>
            </div>

            <?php if ( ! empty( $_GET['refreshed'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Local media feeds refreshed successfully.', 'wsh-ai-news-editor' ); ?></p>
                </div>
            <?php endif; ?>


            <?php if (empty($active_sources)) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e('No active local media sources found. Go to Settings and add RSS feeds first.', 'wsh-ai-news-editor'); ?>
                    </p>
                </div>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wsh-ai-news-editor-settings')); ?>" class="button button-primary">
                        <?php esc_html_e('Open Settings', 'wsh-ai-news-editor'); ?>
                    </a>
                </p>
            <?php else : ?>
                <div class="wsh-aine-accordion">
                    <?php foreach ($active_sources as $index => $source) : ?>
                        <?php
                        $source_name = isset($source['name']) ? sanitize_text_field($source['name']) : __('Unnamed Source', 'wsh-ai-news-editor');
                        $items       = WSH_AINE_RSS_Fetcher::fetch_feed_items($source, 'local_rss', 10);
                        $panel_id    = 'wsh-aine-source-panel-' . $index;
                        ?>
                        <div class="wsh-aine-accordion-item">
                            <button type="button" class="wsh-aine-accordion-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr($panel_id); ?>">
                                <span><?php echo esc_html($source_name); ?></span>
                                <span class="wsh-aine-accordion-meta">
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %d = number of items */
                                            _n('%d article', '%d articles', count($items), 'wsh-ai-news-editor'),
                                            count($items)
                                        )
                                    );
                                    ?>
                                </span>
                            </button>

                            <div id="<?php echo esc_attr($panel_id); ?>" class="wsh-aine-accordion-panel" hidden>
                                <?php if (empty($items)) : ?>
                                    <div class="wsh-aine-empty-state">
                                        <?php esc_html_e('No articles found in this RSS feed right now.', 'wsh-ai-news-editor'); ?>
                                    </div>
                                <?php else : ?>
                                    <div class="wsh-aine-news-list">
                                        <?php foreach ($items as $item) : ?>
                                            <?php self::render_item_card($item); ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php
    }

    protected static function render_item_card(array $item): void
    {
        $title          = $item['title'] ?? '';
        $url            = $item['url'] ?? '';
        $image          = $item['image'] ?? '';
        $description    = $item['description'] ?? '';
        $date           = $item['date'] ?? '';
        $source         = $item['source'] ?? '';
        $display_domain = $item['display_domain'] ?? '';
    ?>
        <div class="wsh-aine-news-card">
            <div class="wsh-aine-news-card__media">
                <?php if ($image) : ?>
                    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($title); ?>" />
                <?php else : ?>
                    <div class="wsh-aine-news-card__noimage"><?php esc_html_e('No image', 'wsh-ai-news-editor'); ?></div>
                <?php endif; ?>
            </div>

            <div class="wsh-aine-news-card__body">
                <div class="wsh-aine-news-card__top">
                    <span class="wsh-aine-badge"><?php echo esc_html($source); ?></span>
                    <?php if ($date) : ?>
                        <span class="wsh-aine-date"><?php echo esc_html($date); ?></span>
                    <?php endif; ?>
                </div>

                <h3 class="wsh-aine-news-card__title"><?php echo esc_html($title); ?></h3>

                <?php if ($display_domain) : ?>
                    <div class="wsh-aine-news-card__linkmeta">
                        <?php echo esc_html($display_domain); ?>
                    </div>
                <?php endif; ?>

                <?php if ($description) : ?>
                    <p class="wsh-aine-news-card__excerpt"><?php echo esc_html($description); ?></p>
                <?php endif; ?>

                <div class="wsh-aine-news-card__actions">
                    <?php if ($url) : ?>
                        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" class="button">
                            <?php esc_html_e('Pogledaj vest', 'wsh-ai-news-editor'); ?>
                        </a>
                    <?php endif; ?>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-flex;">
                        <?php wp_nonce_field('wsh_aine_prepare_local_ai'); ?>
                        <input type="hidden" name="item_title" value="<?php echo esc_attr( $title ); ?>" />
                        <input type="hidden" name="item_url" value="<?php echo esc_attr( $url ); ?>" />
                        <input type="hidden" name="item_image" value="<?php echo esc_attr( $image ); ?>" />
                        <input type="hidden" name="item_description" value="<?php echo esc_attr( $description ); ?>" />
                        <input type="hidden" name="item_content" value="<?php echo esc_attr( $item['content'] ?? '' ); ?>" />
                        <input type="hidden" name="item_date" value="<?php echo esc_attr( $date ); ?>" />
                        <input type="hidden" name="item_source" value="<?php echo esc_attr( $source ); ?>" />
                        <input type="hidden" name="action" value="wsh_aine_prepare_local_ai" />

                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Generiši AI vest', 'wsh-ai-news-editor'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php
    }

    public static function handle_refresh() : void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Forbidden', 'wsh-ai-news-editor' ) );
        }

        check_admin_referer( 'wsh_aine_refresh_local_media' );

        $options = get_option( 'wsh_aine_settings', array() );
        $sources = isset( $options['local_sources'] ) && is_array( $options['local_sources'] ) ? $options['local_sources'] : array();

        $active_sources = array_values(
            array_filter(
                $sources,
                function ( $source ) {
                    return ! empty( $source['active'] ) && ! empty( $source['url'] );
                }
            )
        );

        foreach ( $active_sources as $source ) {
            WSH_AINE_RSS_Fetcher::clear_feed_cache( $source, 'local_rss', 10 );
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'      => 'wsh-ai-news-editor-local-media',
                    'refreshed' => 1,
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }
}
