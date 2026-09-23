<?php
if (! defined('ABSPATH')) {
    exit;
}

class WSH_AINE_AI_Editor_Page
{

    public static function init(): void
    {
        add_action('admin_post_wsh_aine_create_draft', array(__CLASS__, 'handle_create_draft'));

        add_action(
            'wp_ajax_wsh_aine_generate_ai',
            array(__CLASS__, 'ajax_generate_ai')
        );

        add_action('wp_ajax_wsh_aine_editor_chat', array(__CLASS__, 'ajax_editor_chat'));

        add_action('wp_ajax_wsh_aine_generate_comments', array(__CLASS__, 'ajax_generate_comments'));

        add_action('admin_post_wsh_aine_create_draft_with_comments', array(__CLASS__, 'handle_create_draft_with_comments'));

        add_action('wp_ajax_wsh_aine_search_images', array(__CLASS__, 'ajax_search_images'));
        add_action('wp_ajax_wsh_aine_import_image', array(__CLASS__, 'ajax_import_image'));
    }

    public static function handle_create_draft(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_die(esc_html__('Forbidden', 'wsh-ai-news-editor'));
        }

        check_admin_referer('wsh_aine_create_draft');

        $seed_token = isset($_POST['seed_token']) ? sanitize_text_field(wp_unslash($_POST['seed_token'])) : '';
        $payload    = WSH_AINE_AI_Drafts::get_seed($seed_token);

        $data = self::build_post_data_from_request($payload);

        /*$data = array(
            'title'            => isset( $_POST['ai_title'] ) ? sanitize_text_field( wp_unslash( $_POST['ai_title'] ) ) : '',
            'excerpt'          => isset( $_POST['ai_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_excerpt'] ) ) : '',
            'seo_title'        => isset( $_POST['ai_seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['ai_seo_title'] ) ) : '',
            //'content'          => isset( $_POST['ai_content'] ) ? wp_kses_post( wp_unslash( $_POST['ai_content'] ) ) : '',
            'content'          => isset( $_POST['ai_content'] ) ? wsh_aine_sanitize_editor_content( wp_unslash( $_POST['ai_content'] ) ) : '',
            'tags'             => isset( $_POST['ai_tags'] ) ? sanitize_text_field( wp_unslash( $_POST['ai_tags'] ) ) : '',
            'categories'       => isset( $_POST['ai_categories'] ) ? (array) $_POST['ai_categories'] : array(),
            'use_source_image' => ! empty( $_POST['use_source_image'] ) ? 1 : 0,
            'custom_image_id'  => isset( $_POST['custom_image_id'] ) ? absint( $_POST['custom_image_id'] ) : 0,
            'generated_comments' => array(
                isset( $_POST['ai_comment_1'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_comment_1'] ) ) : '',
                isset( $_POST['ai_comment_2'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_comment_2'] ) ) : '',
                isset( $_POST['ai_comment_3'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_comment_3'] ) ) : '',
                isset( $_POST['ai_comment_4'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_comment_4'] ) ) : '',
                isset( $_POST['ai_comment_5'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_comment_5'] ) ) : '',
            ),
            'source_payload'   => $payload,
        );*/

        $post_id = WSH_AINE_Posts::create_draft($data);

        if (is_wp_error($post_id)) {
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page'  => 'wsh-ai-news-editor-ai-editor',
                        'seed'  => rawurlencode($seed_token),
                        'error' => rawurlencode($post_id->get_error_message()),
                    ),
                    admin_url('admin.php')
                )
            );
            exit;
        }

        WSH_AINE_AI_Drafts::clear_seed($seed_token);

        wp_safe_redirect(
            add_query_arg(
                array(
                    'post'   => $post_id,
                    'action' => 'edit',
                ),
                admin_url('post.php')
            )
        );
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_die(esc_html__('Forbidden', 'wsh-ai-news-editor'));
        }

        $img_nonce = wp_create_nonce( 'wsh_aine_ai_editor' );

        $seed_token = isset($_GET['seed']) ? sanitize_text_field(wp_unslash($_GET['seed'])) : '';
        $payload    = $seed_token ? WSH_AINE_AI_Drafts::get_seed($seed_token) : array();
        $error      = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';

        $title   = $payload['origin_title'] ?? '';
        $content = self::build_initial_content($payload);
        $tags    = self::build_initial_tags($payload);
        $excerpt   = self::build_initial_excerpt($payload);
        $seo_title = $title;

        $categories = get_categories(array(
            'hide_empty' => false,
        ));

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Editor', 'wsh-ai-news-editor'); ?></h1>

            <?php if ($error) : ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html($error); ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($payload)) : ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('No AI draft seed found. Please return to a source module and click "Generiši AI vest".', 'wsh-ai-news-editor'); ?></p>
                </div>
            <?php else : ?>
                <div class="wsh-aine-editor-layout">

                    <div class="wsh-aine-editor-main">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('wsh_aine_create_draft'); ?>
                            <input type="hidden" name="action" id="wsh_aine_editor_form_action" value="wsh_aine_create_draft" />
                            <input type="hidden" name="seed_token" value="<?php echo esc_attr($seed_token); ?>" />

                            <div class="wsh-aine-panel">
                                <h2><?php esc_html_e('Draft Content', 'wsh-ai-news-editor'); ?></h2>

                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="ai_title"><?php esc_html_e('Title', 'wsh-ai-news-editor'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" id="ai_title" name="ai_title" class="regular-text" style="width:100%;max-width:none;" value="<?php echo esc_attr($title); ?>" />
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="ai_excerpt"><?php esc_html_e('Excerpt', 'wsh-ai-news-editor'); ?></label>
                                        </th>
                                        <td>
                                            <textarea id="ai_excerpt" name="ai_excerpt" rows="4" style="width:100%;max-width:none;"><?php echo esc_textarea($excerpt); ?></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="ai_seo_title"><?php esc_html_e('SEO Title', 'wsh-ai-news-editor'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" id="ai_seo_title" name="ai_seo_title" class="regular-text" style="width:100%;max-width:none;" value="<?php echo esc_attr($seo_title); ?>" />
                                        </td>
                                    </tr>
                                </table>

                                <?php
                                wp_editor(
                                    $content,
                                    'wsh_aine_ai_content',
                                    array(
                                        'textarea_name' => 'ai_content',
                                        'media_buttons' => true,
                                        'textarea_rows' => 18,
                                    )
                                );
                                ?>

                                <div class="wsh-aine-panel" style="margin-top:16px;">
                                    <h2><?php esc_html_e('AI Editor Chat', 'wsh-ai-news-editor'); ?></h2>

                                    <div class="wsh-aine-ai-quick-actions">
                                        <button type="button" class="button wsh-aine-ai-quick" data-instruction="<?php echo esc_attr__('Proširi vest i dodaj više relevantnih detalja bez izmišljanja činjenica.', 'wsh-ai-news-editor'); ?>">
                                            <?php esc_html_e('Proširi vest', 'wsh-ai-news-editor'); ?>
                                        </button>

                                        <button type="button" class="button wsh-aine-ai-quick" data-instruction="<?php echo esc_attr__('Skrati vest i zadrži samo najvažnije informacije.', 'wsh-ai-news-editor'); ?>">
                                            <?php esc_html_e('Skrati vest', 'wsh-ai-news-editor'); ?>
                                        </button>

                                        <button type="button" class="button wsh-aine-ai-quick" data-instruction="<?php echo esc_attr__('Napiši jači i informativniji uvod za ovu vest.', 'wsh-ai-news-editor'); ?>">
                                            <?php esc_html_e('Napiši uvod', 'wsh-ai-news-editor'); ?>
                                        </button>

                                        <button type="button" class="button wsh-aine-ai-quick" data-instruction="<?php echo esc_attr__('Dodaj dobar završni pasus i zaključi vest profesionalno.', 'wsh-ai-news-editor'); ?>">
                                            <?php esc_html_e('Napiši zaključak', 'wsh-ai-news-editor'); ?>
                                        </button>

                                        <button type="button" class="button wsh-aine-ai-quick" data-instruction="<?php echo esc_attr__('Promeni naslov da bude jasniji, jači i novinarski profesionalan.', 'wsh-ai-news-editor'); ?>">
                                            <?php esc_html_e('Promeni naslov', 'wsh-ai-news-editor'); ?>
                                        </button>
                                    </div>

                                    <textarea
                                        id="wsh-aine-ai-chat-instruction"
                                        rows="4"
                                        style="width:100%;margin-top:12px;"
                                        placeholder="<?php echo esc_attr__('Npr: proširi vest, prepravi ton, napiši jači uvod, dodaj zaključak...', 'wsh-ai-news-editor'); ?>"></textarea>

                                    <p style="margin-top:12px;">
                                        <button
                                            type="button"
                                            class="button button-secondary"
                                            id="wsh-aine-ai-chat-submit"
                                            data-seed="<?php echo esc_attr($seed_token); ?>">
                                            <?php esc_html_e('Pošalji AI', 'wsh-ai-news-editor'); ?>
                                        </button>
                                    </p>
                                </div>

                                <table class="form-table" style="margin-top:16px;">
                                    <tr>
                                        <th scope="row">
                                            <label for="ai_tags"><?php esc_html_e('Tags', 'wsh-ai-news-editor'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" id="ai_tags" name="ai_tags" class="regular-text" style="width:100%;max-width:none;" value="<?php echo esc_attr($tags); ?>" />
                                            <p class="description"><?php esc_html_e('Comma separated tags.', 'wsh-ai-news-editor'); ?></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row"><?php esc_html_e('Categories', 'wsh-ai-news-editor'); ?></th>
                                        <td>
                                            <div class="wsh-aine-category-box">
                                                <?php if (! empty($categories)) : ?>
                                                    <?php foreach ($categories as $category) : ?>
                                                        <label class="wsh-aine-category-item">
                                                            <input type="checkbox" name="ai_categories[]" value="<?php echo esc_attr($category->term_id); ?>" />
                                                            <span><?php echo esc_html($category->name); ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                <?php else : ?>
                                                    <p><?php esc_html_e('No categories found.', 'wsh-ai-news-editor'); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Featured Image', 'wsh-ai-news-editor'); ?></th>
                                        <td>
                                            <input type="hidden" name="custom_image_id" id="wsh_aine_custom_image_id" value="" />

                                            <div class="wsh-aine-image-actions">
                                                <button type="button" class="button" id="wsh-aine-select-custom-image">
                                                    <?php esc_html_e('Dodaj svoju sliku', 'wsh-ai-news-editor'); ?>
                                                </button>

                                                <button type="button" class="button" id="wsh-aine-remove-custom-image" style="display:none;">
                                                    <?php esc_html_e('Ukloni svoju sliku', 'wsh-ai-news-editor'); ?>
                                                </button>

                                                <button type="button" class="button button-secondary" id="wsh-aine-open-image-search">
                                                    <?php esc_html_e('Dodaj novu sliku', 'wsh-ai-news-editor'); ?>
                                                </button>
                                            </div>

                                            <div id="wsh-aine-custom-image-preview-wrap" style="display:none;margin-top:12px;">
                                                <img
                                                    id="wsh-aine-custom-image-preview"
                                                    src=""
                                                    alt=""
                                                    style="max-width:220px;height:auto;border-radius:8px;border:1px solid #ddd;" />
                                                <p class="description" style="margin-top:8px;">
                                                    <?php esc_html_e('Biće korišćena vaša slika umesto originalne source slike.', 'wsh-ai-news-editor'); ?>
                                                </p>
                                            </div>

                                            <?php if (! empty($payload['image'])) : ?>
                                                <hr style="margin:16px 0;" />

                                                <label style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                                                    <input type="checkbox" name="use_source_image" id="wsh-aine-use-source-image" value="1" checked />
                                                    <span><?php esc_html_e('Use source image as featured image', 'wsh-ai-news-editor'); ?></span>
                                                </label>

                                                <div id="wsh-aine-source-image-preview-wrap">
                                                    <img src="<?php echo esc_url($payload['image']); ?>" alt="" style="max-width:220px;height:auto;border-radius:8px;border:1px solid #ddd;" />
                                                </div>
                                            <?php else : ?>
                                                <p><?php esc_html_e('No source image available.', 'wsh-ai-news-editor'); ?></p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>

                                <!-- Select images Modal -->
                                <div id="wsh-aine-image-search-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:99999;">
                                    <div style="background:#fff; max-width:950px; margin:60px auto; padding:24px; border-radius:10px; position:relative;">

                                        <button type="button" id="wsh-aine-close-image-search" class="button" style="position:absolute; right:16px; top:16px;">
                                            ×
                                        </button>

                                        <h2><?php esc_html_e('Pretraga nove slike', 'wsh-ai-news-editor'); ?></h2>

                                        <table class="form-table">
                                            <tr>
                                                <th><?php esc_html_e('Termin za pretragu', 'wsh-ai-news-editor'); ?></th>
                                                <td>
                                                    <input type="text" id="wsh-aine-image-search-query" class="regular-text" style="width:100%; max-width:600px;" value="<?php echo esc_attr($title); ?>" />
                                                </td>
                                            </tr>

                                            <tr>
                                                <th><?php esc_html_e('Izvor slika', 'wsh-ai-news-editor'); ?></th>
                                                <td>
                                                    <select id="wsh-aine-image-provider">
                                                        <?php
                                                        $options = get_option('wsh_aine_settings', array());
                                                        $enabled_services = isset($options['image_services_enabled']) && is_array($options['image_services_enabled'])
                                                            ? $options['image_services_enabled']
                                                            : array('pexels', 'pixabay', 'unsplash', 'ai_generated');

                                                        $labels = array(
                                                            'pexels'   => 'Pexels',
                                                            'pixabay'  => 'Pixabay',
                                                            'unsplash' => 'Unsplash',
                                                            'ai_generated' => 'AI Generated',
                                                            'reuters' => 'Reuters'
                                                        );

                                                        foreach ($enabled_services as $service_key) :
                                                            if (empty($labels[$service_key])) {
                                                                continue;
                                                            }
                                                        ?>
                                                            <option value="<?php echo esc_attr($service_key); ?>">
                                                                <?php echo esc_html($labels[$service_key]); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>

                                                    <button type="button" class="button button-primary" id="wsh-aine-run-image-search">
                                                        <?php esc_html_e('Pretraži', 'wsh-ai-news-editor'); ?>
                                                    </button>
                                                </td>
                                            </tr>
                                        </table>

                                        <div id="wsh-aine-image-search-status" style="margin:12px 0;"></div>

                                        <div id="wsh-aine-image-search-results" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:14px; margin-top:20px;"></div>
                                    </div>
                                </div>

                                <div class="wsh-aine-panel" style="margin-top:16px;">
                                    <h2><?php esc_html_e('AI Comments', 'wsh-ai-news-editor'); ?></h2>

                                    <p>
                                        <button
                                            type="button"
                                            class="button"
                                            id="wsh-aine-generate-comments"
                                            data-seed="<?php echo esc_attr($seed_token); ?>">
                                            <?php esc_html_e('Generiši komentare', 'wsh-ai-news-editor'); ?>
                                        </button>
                                    </p>

                                    <div class="wsh-aine-comments-fields">
                                        <textarea name="ai_comment_1" id="ai_comment_1" rows="3" style="width:100%;margin-bottom:10px;" placeholder="<?php echo esc_attr__('Komentar 1', 'wsh-ai-news-editor'); ?>"></textarea>
                                        <textarea name="ai_comment_2" id="ai_comment_2" rows="3" style="width:100%;margin-bottom:10px;" placeholder="<?php echo esc_attr__('Komentar 2', 'wsh-ai-news-editor'); ?>"></textarea>
                                        <textarea name="ai_comment_3" id="ai_comment_3" rows="3" style="width:100%;margin-bottom:10px;" placeholder="<?php echo esc_attr__('Komentar 3', 'wsh-ai-news-editor'); ?>"></textarea>
                                        <textarea name="ai_comment_4" id="ai_comment_4" rows="3" style="width:100%;margin-bottom:10px;" placeholder="<?php echo esc_attr__('Komentar 4', 'wsh-ai-news-editor'); ?>"></textarea>
                                        <textarea name="ai_comment_5" id="ai_comment_5" rows="3" style="width:100%;" placeholder="<?php echo esc_attr__('Komentar 5', 'wsh-ai-news-editor'); ?>"></textarea>
                                    </div>
                                </div>

                                <p style="margin-top:20px;">
                                    <button type="button" class="button button-secondary" id="wsh-generate-ai" data-seed="<?php echo esc_attr($seed_token); ?>">Generate AI Article</button>
                                    <button type="submit" class="button button-primary button-large">
                                        <?php esc_html_e('Create Draft', 'wsh-ai-news-editor'); ?>
                                    </button>
                                    <label>
                                        <input type="checkbox" id="wsh-aine-insert-comments-toggle">
                                        Dodaj AI komentare uz vest
                                    </label>
                                </p>
                            </div>
                        </form>
                    </div>

                    <div class="wsh-aine-editor-sidebar">
                        <div class="wsh-aine-panel">
                            <h2><?php esc_html_e('Source Info', 'wsh-ai-news-editor'); ?></h2>



                            <table class="form-table">
                                <tr>
                                    <th><?php esc_html_e('Source Type', 'wsh-ai-news-editor'); ?></th>
                                    <td><?php echo esc_html($payload['source_type'] ?? '—'); ?></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Source Name', 'wsh-ai-news-editor'); ?></th>
                                    <td><?php echo esc_html($payload['source_name'] ?? '—'); ?></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Original Title', 'wsh-ai-news-editor'); ?></th>
                                    <td><?php echo esc_html($payload['origin_title'] ?? '—'); ?></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Published', 'wsh-ai-news-editor'); ?></th>
                                    <td><?php echo esc_html($payload['published_at'] ?? '—'); ?></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Original URL', 'wsh-ai-news-editor'); ?></th>
                                    <td>
                                        <?php if (! empty($payload['origin_url'])) : ?>
                                            <a href="<?php echo esc_url($payload['origin_url']); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo esc_html($payload['origin_url']); ?>
                                            </a>
                                        <?php else : ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>

                            <?php if (! empty($payload['image'])) : ?>
                                <div style="margin-top:12px;">
                                    <img src="<?php echo esc_url($payload['image']); ?>" alt="" style="max-width:100%;height:auto;border-radius:8px;border:1px solid #ddd;" />
                                </div>
                            <?php endif; ?>

                            <?php if (! empty($payload['extra']['citations']) && is_array($payload['extra']['citations'])) : ?>
                                <div style="margin-top:16px;">
                                    <h3 style="margin:0 0 8px;"><?php esc_html_e('Citations', 'wsh-ai-news-editor'); ?></h3>
                                    <ol style="margin:0 0 0 18px;">
                                        <?php foreach (array_values(array_unique(array_filter($payload['extra']['citations']))) as $citation_url) : ?>
                                            <li style="margin-bottom:6px;word-break:break-word;">
                                                <a href="<?php echo esc_url($citation_url); ?>" target="_blank" rel="noopener noreferrer">
                                                    <?php echo esc_html($citation_url); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ol>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            <?php endif; ?>
        </div>

        <!-- Images -->
        <script>
            jQuery(function($){

                var imageSearchNonce = '<?php echo esc_js( $img_nonce ); ?>';

                $('#wsh-aine-open-image-search').on('click', function(){
                    $('#wsh-aine-image-search-modal').show();
                });

                $('#wsh-aine-close-image-search').on('click', function(){
                    $('#wsh-aine-image-search-modal').hide();
                });

                $('#wsh-aine-run-image-search').on('click', function(){
                    var query = $('#wsh-aine-image-search-query').val();
                    var provider = $('#wsh-aine-image-provider').val();

                    if (!query) {
                        alert('Unesite termin za pretragu.');
                        return;
                    }

                    $('#wsh-aine-image-search-status').html('Pretraga je u toku...');
                    $('#wsh-aine-image-search-results').html('');

                    $.post(ajaxurl, {
                        action: 'wsh_aine_search_images',
                        nonce: imageSearchNonce,
                        query: query,
                        provider: provider
                    }, function(response){
                        if (!response.success) {
                            $('#wsh-aine-image-search-status').html(response.data && response.data.message ? response.data.message : 'Greška pri pretrazi.');
                            return;
                        }

                        var images = response.data.images || [];

                        if (!images.length) {
                            $('#wsh-aine-image-search-status').html('Nema pronađenih slika.');
                            return;
                        }

                        $('#wsh-aine-image-search-status').html('Kliknite na sliku koju želite da koristite.');

                        images.forEach(function(img){
                            var html = `
                                <div class="wsh-aine-found-image"
                                    data-full="${img.full}"
                                    data-provider="${img.provider}"
                                    data-author="${img.author}"
                                    style="cursor:pointer;border:1px solid #ddd;border-radius:8px;padding:8px;background:#fff;">
                                    <img src="${img.thumb}" style="width:100%;height:110px;object-fit:cover;border-radius:6px;" />
                                    <div style="font-size:11px;margin-top:6px;color:#666;">
                                        ${img.provider} ${img.author ? ' / ' + img.author : ''}
                                    </div>
                                </div>
                            `;

                            $('#wsh-aine-image-search-results').append(html);
                        });
                    });
                });

                $(document).on('click', '.wsh-aine-found-image', function(){
                    var box = $(this);
                    var imageUrl = box.data('full');

                    $('#wsh-aine-image-search-status').html('Dodavanje slike u Media Library...');

                    $.post(ajaxurl, {
                        action: 'wsh_aine_import_image',
                        nonce: imageSearchNonce,
                        image_url: imageUrl,
                        provider: box.data('provider'),
                        author: box.data('author')
                    }, function(response){
                        if (!response.success) {
                            $('#wsh-aine-image-search-status').html(response.data && response.data.message ? response.data.message : 'Greška pri importu slike.');
                            return;
                        }

                        $('#wsh_aine_custom_image_id').val(response.data.attachment_id);
                        $('#wsh-aine-custom-image-preview').attr('src', response.data.image_url);
                        $('#wsh-aine-custom-image-preview-wrap').show();
                        $('#wsh-aine-remove-custom-image').show();

                        $('#wsh-aine-use-source-image').prop('checked', false);
                        $('#wsh-aine-image-search-modal').hide();
                    });
                });

            });
        </script>
    <?php
    }

    protected static function build_initial_content(array $payload): string
    {
        $parts = array();

        if (! empty($payload['embed_type']) && 'youtube' === $payload['embed_type'] && ! empty($payload['embed_url'])) {
            $parts[] = '<p><iframe width="100%" height="315" src="' . esc_url($payload['embed_url']) . '" frameborder="0" allowfullscreen></iframe></p>';
        }

        /*if ( ! empty( $payload['embed_type'] ) && 'tweet' === $payload['embed_type'] ) {
            if ( ! empty( $payload['extra']['embed_html'] ) ) {
                $parts[] = (string) self::build_tweet_embed_html($payload);
            } elseif ( ! empty( $payload['embed_url'] ) ) {
                $parts[] = '<blockquote class="twitter-tweet"><a href="' . esc_url( $payload['embed_url'] ) . '"></a></blockquote>';
            }
        }*/

        /*if ( ! empty( $payload['embed_type'] ) && 'instagram' === $payload['embed_type'] ) {
            if ( ! empty( $payload['extra']['embed_html'] ) ) {
                $parts[] = (string) self::build_instagram_embed_html($payload);
            } elseif ( ! empty( $payload['embed_url'] ) ) {
                $embed_link = $payload['embed_url'] . "/embed";
                $embed_link = str_replace("//embed", "/embed", $embed_link);
                $parts[] = "<div style='display:flex; justify-content:center; margin:20px 0;'>
                            <iframe 
                                class='instagram-media instagram-media-rendered'
                                style='background:#fff; border:1px solid #dbdbdb; border-radius:3px; box-shadow:none; width:100%; max-width:540px; min-height:900px;'
                                src='" . esc_url( $embed_link ) . "'
                                frameborder='0'
                                allowfullscreen
                            ></iframe>
                        </div>";
                //$parts[] = '<blockquote class="instagram-po"><a href="' . esc_url( $payload['embed_url'] ) . '"></a></blockquote>';
            }
        }*/

        if (
            ! empty($payload['embed_type']) &&
            in_array(
                strtolower((string) $payload['embed_type']),
                array('x', 'twitter', 'tweet'),
                true
            )
        ) {
            $embed_url = ! empty($payload['embed_url'])
                ? (string) $payload['embed_url']
                : (string) ($payload['origin_url'] ?? '');

            if (! empty($embed_url)) {
                $clean_url = strtok($embed_url, '?');

                if (preg_match('#/(status|statuses)/(\d+)#', $clean_url, $matches)) {
                    $tweet_id = $matches[2];

                    $parts[] = "
				<div style='display:flex; justify-content:center; margin:20px 0;'>
					<iframe
						src='https://platform.twitter.com/embed/Tweet.html?id=" . esc_attr($tweet_id) . "'
						width='550'
						height='650'
						frameborder='0'
						scrolling='no'
						allowfullscreen
						style='max-width:100%; border:0;'>
					</iframe>
				</div>
			";
                }
            }
        }



        if (! empty($payload['embed_type']) && 'linkedin' === $payload['embed_type']) {
            if (! empty($payload['embed_url'])) {
                $parts[] = "<div style='display:flex; justify-content:center; margin:20px 0;'>
                            <iframe src='" . esc_url($payload['embed_url']) . "' height='669' width='504' frameborder='0' allowfullscreen='' title='Embedded post'></iframe>
                        </div>";
            }
        }


        if (! empty($payload['embed_type']) && 'grok_search' === $payload['embed_type']) {
            $parts[] = '<p><strong>' . esc_html__('Grok source summary', 'wsh-ai-news-editor') . '</strong></p>';
        }

        if (! empty($payload['origin_html'])) {
            $parts[] = $payload['origin_html'];
        }

        if (! empty($payload['origin_text'])) {
            $parts[] = '<p>' . nl2br(esc_html(wp_strip_all_tags((string) $payload['origin_text']))) . '</p>';
        }


        if (! empty($payload['origin_url'])) {
            $parts[] = '<p><strong>' . esc_html__('Source:', 'wsh-ai-news-editor') . '</strong> <a href="' . esc_url($payload['origin_url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($payload['origin_url']) . '</a></p>';
        }

        return implode("\n\n", $parts);
    }

    protected static function build_initial_tags(array $payload): string
    {
        $tags = array();

        if (! empty($payload['source_name'])) {
            $tags[] = sanitize_text_field($payload['source_name']);
        }

        if (! empty($payload['source_type'])) {
            $tags[] = sanitize_text_field($payload['source_type']);
        }

        return implode(', ', array_unique(array_filter($tags)));
    }


    protected static function build_tweet_embed_html(array $payload): string
    {
        $tweet_url       = ! empty($payload['origin_url']) ? esc_url($payload['origin_url']) : '';
        $tweet_text      = ! empty($payload['origin_title']) ? esc_html($payload['origin_title']) : '';
        $author_name     = ! empty($payload['author_name']) ? esc_html($payload['author_name']) : '';
        $author_username = ! empty($payload['author_username']) ? ltrim((string) $payload['author_username'], '@') : '';
        $author_username = esc_html($author_username);
        $created_at      = ! empty($payload['published_at']) ? esc_html($payload['published_at']) : '';
        if ('' === $tweet_url) {
            return '';
        }

        $pic = "<a href='https://t.co/" . $payload['extra']['xid'] . "'>pic.twitter.com/" . $payload['extra']['xid'] . "</a></p>";
        $html  = '<blockquote class="twitter-tweet">';
        $html .= '<p lang="und" dir="ltr">' . $tweet_text . ' ' . $pic . '</p>';
        $html .= '&mdash; ' . $author_name;

        if ('' !== $author_username) {
            $html .= ' (@' . $author_username . ')';
        }

        $embed_url = str_replace("https://x.com", "https://twitter.com", $tweet_url);
        $html .= ' <a href="' . $embed_url . '">' . $created_at . '</a>';
        $html .= '</blockquote>';
        //$html .= '<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>';

        return $html;
    }

    protected static function build_instagram_embed_html(array $payload): string
    {
        $post_url        = ! empty($payload['origin_url']) ? esc_url($payload['origin_url']) : '';
        $caption         = ! empty($payload['origin_title']) ? esc_html($payload['origin_title']) : '';
        $author_name     = ! empty($payload['author_name']) ? esc_html($payload['author_name']) : '';
        $author_username = ! empty($payload['author_username']) ? ltrim((string) $payload['author_username'], '@') : '';
        $author_username = esc_html($author_username);
        $created_at      = ! empty($payload['published_at']) ? esc_html($payload['published_at']) : '';

        if ('' === $post_url) {
            return '';
        }

        if ('/' !== substr($post_url, -1)) {
            $post_url .= '/';
        }

        $permalink = add_query_arg(
            array(
                'utm_source'   => 'ig_embed',
                'utm_campaign' => 'loading',
            ),
            $post_url
        );

        $html  = '<blockquote class="instagram-media"';
        $html .= ' data-instgrm-captioned';
        $html .= ' data-instgrm-permalink="' . esc_url($permalink) . '"';
        $html .= ' data-instgrm-version="14"';
        $html .= ' style="background:#FFF; border:0; border-radius:3px; box-shadow:0 0 1px 0 rgba(0,0,0,0.5),0 1px 10px 0 rgba(0,0,0,0.15); margin:20px 0; max-width:540px; min-width:326px; padding:0; width:100%;">';

        // fallback sadržaj dok embed.js ne obradi blockquote
        $html .= '<div style="padding:16px;">';

        if ('' !== $caption) {
            $html .= '<p style="margin:0 0 12px;">' . $caption . '</p>';
        }

        $html .= '<p style="margin:0;">';

        if ('' !== $author_name) {
            $html .= '<strong>' . $author_name . '</strong>';
        }

        if ('' !== $author_username) {
            if ('' !== $author_name) {
                $html .= ' ';
            }
            $html .= '(@' . $author_username . ')';
        }

        if ('' !== $created_at) {
            $html .= ' · ' . $created_at;
        }

        $html .= '</p>';
        $html .= '<p style="margin:12px 0 0;">';
        $html .= '<a href="' . esc_url($post_url) . '" target="_blank" rel="noopener noreferrer">View this post on Instagram</a>';
        $html .= '</p>';
        $html .= '</div>';
        $html .= '</blockquote>';

        return $html;
    }

    protected static function build_initial_excerpt(array $payload): string
    {
        $text = '';

        if (! empty($payload['origin_text'])) {
            $text = wp_strip_all_tags((string) $payload['origin_text']);
        } elseif (! empty($payload['origin_html'])) {
            $text = wp_strip_all_tags((string) $payload['origin_html']);
        }

        $text = trim(preg_replace('/\s+/u', ' ', $text));

        if ('' === $text) {
            return '';
        }

        return wp_html_excerpt($text, 180, '...');
    }

    protected static function build_post_data_from_request(array $payload): array
    {
        return array(
            'title'              => isset($_POST['ai_title']) ? sanitize_text_field(wp_unslash($_POST['ai_title'])) : '',
            'excerpt'            => isset($_POST['ai_excerpt']) ? sanitize_textarea_field(wp_unslash($_POST['ai_excerpt'])) : '',
            'seo_title'          => isset($_POST['ai_seo_title']) ? sanitize_text_field(wp_unslash($_POST['ai_seo_title'])) : '',
            'content'            => isset($_POST['ai_content']) ? wsh_aine_sanitize_editor_content(wp_unslash($_POST['ai_content'])) : '',
            'tags'               => isset($_POST['ai_tags']) ? sanitize_text_field(wp_unslash($_POST['ai_tags'])) : '',
            'categories'         => isset($_POST['ai_categories']) ? (array) $_POST['ai_categories'] : array(),
            'use_source_image'   => ! empty($_POST['use_source_image']) ? 1 : 0,
            'custom_image_id'    => isset($_POST['custom_image_id']) ? absint($_POST['custom_image_id']) : 0,
            'generated_comments' => array(
                isset($_POST['ai_comment_1']) ? sanitize_textarea_field(wp_unslash($_POST['ai_comment_1'])) : '',
                isset($_POST['ai_comment_2']) ? sanitize_textarea_field(wp_unslash($_POST['ai_comment_2'])) : '',
                isset($_POST['ai_comment_3']) ? sanitize_textarea_field(wp_unslash($_POST['ai_comment_3'])) : '',
                isset($_POST['ai_comment_4']) ? sanitize_textarea_field(wp_unslash($_POST['ai_comment_4'])) : '',
                isset($_POST['ai_comment_5']) ? sanitize_textarea_field(wp_unslash($_POST['ai_comment_5'])) : '',
            ),
            'source_payload'     => $payload,
        );
    }

    public static function handle_create_draft_with_comments(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_die(esc_html__('Forbidden', 'wsh-ai-news-editor'));
        }

        check_admin_referer('wsh_aine_create_draft');

        $seed_token = isset($_POST['seed_token']) ? sanitize_text_field(wp_unslash($_POST['seed_token'])) : '';
        $payload    = WSH_AINE_AI_Drafts::get_seed($seed_token);

        $data = self::build_post_data_from_request($payload);

        $post_id = WSH_AINE_Posts::create_draft_and_insert_comments($data);

        if (is_wp_error($post_id)) {
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page'  => 'wsh-ai-news-editor-ai-editor',
                        'seed'  => rawurlencode($seed_token),
                        'error' => rawurlencode($post_id->get_error_message()),
                    ),
                    admin_url('admin.php')
                )
            );
            exit;
        }

        WSH_AINE_AI_Drafts::clear_seed($seed_token);

        wp_safe_redirect(
            add_query_arg(
                array(
                    'post'   => $post_id,
                    'action' => 'edit',
                ),
                admin_url('post.php')
            )
        );
        exit;
    }

    public static function ajax_generate_ai()
    {
        if (! current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission denied.', 'wsh-ai-news-editor'));
        }

        check_ajax_referer('wsh_aine_generate_ai', 'nonce');

        $seed = isset($_POST['seed']) ? sanitize_text_field(wp_unslash($_POST['seed'])) : '';
        $payload = WSH_AINE_AI_Drafts::get_seed($seed);

        if (empty($payload)) {
            wp_send_json_error(__('Seed not found.', 'wsh-ai-news-editor'));
        }

        if (! WSH_AINE_Access::can_generate_ai()) {
            wp_send_json_error(WSH_AINE_Access::get_ai_limit_message());
        }

        $result = WSH_AINE_AI_OpenAI::generate_article($payload);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        WSH_AINE_Usage::increment_ai('article');

        wp_send_json_success($result);
    }

    public static function ajax_editor_chat(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission denied.', 'wsh-ai-news-editor'));
        }

        check_ajax_referer('wsh_aine_generate_ai', 'nonce');

        $seed_token   = isset($_POST['seed']) ? sanitize_text_field(wp_unslash($_POST['seed'])) : '';
        $title        = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $content      = isset($_POST['content']) ? wsh_aine_sanitize_editor_content(wp_unslash($_POST['content'])) : '';
        $instruction  = isset($_POST['instruction']) ? sanitize_textarea_field(wp_unslash($_POST['instruction'])) : '';

        if ('' === $instruction) {
            wp_send_json_error(__('Instruction is required.', 'wsh-ai-news-editor'));
        }

        $payload = $seed_token ? WSH_AINE_AI_Drafts::get_seed($seed_token) : array();

        if (! WSH_AINE_Access::can_generate_ai()) {
            wp_send_json_error(WSH_AINE_Access::get_ai_limit_message());
        }

        $result = WSH_AINE_AI_OpenAI::rewrite_article($title, $content, $instruction, $payload);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        WSH_AINE_Usage::increment_ai('rewrite');

        wp_send_json_success($result);
    }

    public static function ajax_generate_comments(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission denied.', 'wsh-ai-news-editor'));
        }

        check_ajax_referer('wsh_aine_generate_ai', 'nonce');

        $seed_token = isset($_POST['seed']) ? sanitize_text_field(wp_unslash($_POST['seed'])) : '';
        $title      = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $content    = isset($_POST['content']) ? wsh_aine_sanitize_editor_content(wp_unslash($_POST['content'])) : '';

        $payload = $seed_token ? WSH_AINE_AI_Drafts::get_seed($seed_token) : array();

        if (! WSH_AINE_Access::can_generate_ai()) {
            wp_send_json_error(WSH_AINE_Access::get_ai_limit_message());
        }

        $result = WSH_AINE_AI_OpenAI::generate_comments($title, $content, $payload);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        WSH_AINE_Usage::increment_ai('comments');

        wp_send_json_success($result);
    }

    //Images
    public static function ajax_search_images(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Forbidden'));
        }

        check_ajax_referer('wsh_aine_ai_editor', 'nonce');

        $query    = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        $provider = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : '';

        if ('' === $query || '' === $provider) {
            wp_send_json_error(array('message' => 'Nedostaje termin za pretragu ili izvor.'));
        }

        $options = get_option('wsh_aine_settings', array());

        $images = array();

        if ('pexels' === $provider) {
            $images = self::search_pexels_images($query, $options);
        } elseif ('pixabay' === $provider) {
            $images = self::search_pixabay_images($query, $options);
        } elseif ('unsplash' === $provider) {
            $images = self::search_unsplash_images($query, $options);
        } elseif ('ai_generated' === $provider) {
            $images = self::search_ai_generated_images($query, $options);
        } elseif ('reuters' === $provider) {
            $images = self::search_reuters_images($query, $options);
        }else {
            wp_send_json_error(array('message' => 'Nepoznat image provider.'));
        }

        wp_send_json_success(array('images' => $images));
    }

    protected static function search_pexels_images(string $query, array $options): array
    {
        $api_key = isset($options['pexels_api_key']) ? trim((string) $options['pexels_api_key']) : '';

        if ('' === $api_key) {
            return array();
        }

        $url = add_query_arg(
            array(
                'query'    => $query,
                'per_page' => 10,
                'locale'   => 'sr-RS',
            ),
            'https://api.pexels.com/v1/search'
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => $api_key,
                ),
            )
        );

        if (is_wp_error($response)) {
            return array();
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['photos']) || ! is_array($data['photos'])) {
            return array();
        }

        $images = array();

        foreach ($data['photos'] as $photo) {
            $images[] = array(
                'id'        => (string) ($photo['id'] ?? ''),
                'thumb'     => esc_url_raw($photo['src']['medium'] ?? ''),
                'full'      => esc_url_raw($photo['src']['large2x'] ?? $photo['src']['large'] ?? ''),
                'author'    => sanitize_text_field($photo['photographer'] ?? ''),
                'provider'  => 'pexels',
                'credit_url' => esc_url_raw($photo['url'] ?? ''),
            );
        }

        return $images;
    }

    protected static function search_pixabay_images(string $query, array $options): array
    {
        $api_key = isset($options['pixabay_api_key']) ? trim((string) $options['pixabay_api_key']) : '';

        if ('' === $api_key) {
            return array();
        }

        $url = add_query_arg(
            array(
                'key'       => $api_key,
                'q'         => $query,
                'image_type' => 'photo',
                'per_page'  => 10,
                'safesearch' => 'true',
            ),
            'https://pixabay.com/api/'
        );

        $response = wp_remote_get($url, array('timeout' => 20));

        if (is_wp_error($response)) {
            return array();
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['hits']) || ! is_array($data['hits'])) {
            return array();
        }

        $images = array();

        foreach ($data['hits'] as $photo) {
            $images[] = array(
                'id'        => (string) ($photo['id'] ?? ''),
                'thumb'     => esc_url_raw($photo['webformatURL'] ?? ''),
                'full'      => esc_url_raw($photo['largeImageURL'] ?? ''),
                'author'    => sanitize_text_field($photo['user'] ?? ''),
                'provider'  => 'pixabay',
                'credit_url' => esc_url_raw($photo['pageURL'] ?? ''),
            );
        }

        return $images;
    }

    protected static function search_unsplash_images(string $query, array $options): array
    {
        $api_key = isset($options['unsplash_access_key']) ? trim((string) $options['unsplash_access_key']) : '';

        if ('' === $api_key) {
            return array();
        }

        $url = add_query_arg(
            array(
                'query'    => $query,
                'per_page' => 10,
                'orientation' => 'landscape',
            ),
            'https://api.unsplash.com/search/photos'
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Client-ID ' . $api_key,
                ),
            )
        );

        if (is_wp_error($response)) {
            return array();
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['results']) || ! is_array($data['results'])) {
            return array();
        }

        $images = array();

        foreach ($data['results'] as $photo) {
            $images[] = array(
                'id'        => sanitize_text_field($photo['id'] ?? ''),
                'thumb'     => esc_url_raw($photo['urls']['small'] ?? ''),
                'full'      => esc_url_raw($photo['urls']['regular'] ?? ''),
                'author'    => sanitize_text_field($photo['user']['name'] ?? ''),
                'provider'  => 'unsplash',
                'credit_url' => esc_url_raw($photo['links']['html'] ?? ''),
            );
        }

        return $images;
    }

    protected static function search_ai_generated_images(string $query, array $options): array
    {
        $api_key = isset($options['openai_api_key']) ? trim((string) $options['openai_api_key']) : '';

        if ('' === $api_key) {
            return array();
        }

        $prompt = trim($query);

        if ('' === $prompt) {
            return array();
        }

        $prompt = "Create a realistic, editorial news-style featured image for the following topic: {$prompt}. The image should look professional, suitable for an online news article, high quality, no text, no logos, no watermark.";

        $body = array(
            'model'  => 'gpt-image-1',
            'prompt' => $prompt,
            'size'   => '1536x1024',
            'n'      => 1,
        );

        $response = wp_remote_post(
            'https://api.openai.com/v1/images/generations',
            array(
                'timeout' => 90,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body' => wp_json_encode($body),
            )
        );

        if (is_wp_error($response)) {
            return array();
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (200 !== $code || empty($data['data'][0]['b64_json'])) {
            return array();
        }

        $upload = wp_upload_dir();

        if (! empty($upload['error'])) {
            return array();
        }

        $image_binary = base64_decode($data['data'][0]['b64_json']);

        if (false === $image_binary) {
            return array();
        }

        $filename = 'wsh-ai-generated-' . time() . '.png';
        $filepath = trailingslashit($upload['path']) . $filename;

        file_put_contents($filepath, $image_binary);

        $image_url = trailingslashit($upload['url']) . $filename;

        return array(
            array(
                'id'         => 'ai_generated_' . time(),
                'thumb'      => esc_url_raw($image_url),
                'full'       => esc_url_raw($image_url),
                'author'     => 'OpenAI',
                'provider'   => 'ai_generated',
                'credit_url' => '',
            ),
        );
    }

    protected static function search_reuters_images_bkp( string $query, array $options ) : array {
        $client_id = isset( $options['reuters_client_id'] ) ? trim( (string) $options['reuters_client_id'] ) : '';
        $secret_id = isset( $options['reuters_secret_id'] ) ? trim( (string) $options['reuters_secret_id'] ) : '';
        $audience  = isset( $options['reuters_audience'] ) ? trim( (string) $options['reuters_audience'] ) : '';

        if ( '' === $client_id || '' === $secret_id || '' === $audience || '' === $query ) {
            return array();
        }

        $token = self::get_reuters_access_token( $client_id, $secret_id, $audience );

        if ( '' === $token ) {
            return array();
        }

        $search_url = add_query_arg(
            array(
                'q'        => $query,
                'type'     => 'pictures',
                'pageSize' => 10,
            ),
            'https://api.reutersconnect.com/content/search'
        );

        $response = wp_remote_get(
            $search_url,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return array();
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code || empty( $data ) ) {
            return array();
        }

        $items = array();

        $results = $data['results'] ?? $data['items'] ?? $data['data'] ?? array();

        if ( empty( $results ) || ! is_array( $results ) ) {
            return array();
        }

        foreach ( $results as $photo ) {
            $thumb = $photo['thumbnailUrl'] ?? $photo['thumbnail_url'] ?? $photo['renditions']['thumbnail']['url'] ?? '';
            $full  = $photo['downloadUrl'] ?? $photo['download_url'] ?? $photo['renditions']['full']['url'] ?? $thumb;

            if ( empty( $thumb ) || empty( $full ) ) {
                continue;
            }

            $caption = $photo['caption'] ?? $photo['headline'] ?? $photo['title'] ?? '';
            $author  = $photo['byline'] ?? $photo['credit'] ?? 'Reuters';

            $items[] = array(
                'id'         => sanitize_text_field( (string) ( $photo['id'] ?? md5( $full ) ) ),
                'thumb'      => esc_url_raw( $thumb ),
                'full'       => esc_url_raw( $full ),
                'author'     => sanitize_text_field( $author ),
                'provider'   => 'reuters',
                'credit_url' => esc_url_raw( $photo['url'] ?? '' ),
                'caption'    => sanitize_text_field( $caption ),
            );
        }

        return array_slice( $items, 0, 10 );
    }

    protected static function search_reuters_images( string $query, array $options ) : array {
        $client_id = isset( $options['reuters_client_id'] ) ? trim( (string) $options['reuters_client_id'] ) : '';
        $secret_id = isset( $options['reuters_secret_id'] ) ? trim( (string) $options['reuters_secret_id'] ) : '';
        $audience  = isset( $options['reuters_audience'] ) ? trim( (string) $options['reuters_audience'] ) : '';

        if ( '' === $client_id || '' === $secret_id || '' === $audience || '' === $query ) {
            return array();
        }

        $token = self::get_reuters_access_token( $client_id, $secret_id, $audience );

        if ( '' === $token ) {
            return array();
        }

        $search_endpoints = array(
            'https://api.thomsonreuters.com/reutersconnect/contentapi/v1/search',
            'https://api.thomsonreuters.com/reutersconnect/contentapi/search',
            'https://api.thomsonreuters.com/contentapi/v1/search',
            'https://api.thomsonreuters.com/contentapi/search',
        );

        foreach ( $search_endpoints as $endpoint ) {
            $search_url = add_query_arg(
                array(
                    'q'        => $query,
                    'type'     => 'pictures',
                    'pageSize' => 10,
                ),
                $endpoint
            );

            $response = wp_remote_get(
                $search_url,
                array(
                    'timeout' => 30,
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                        'Accept'        => 'application/json',
                    ),
                )
            );

            echo $endpoint; echo "\n\n";
            print_r($response); // Debugging line to inspect the response
            echo "\n\n"; // Add a newline for better readability
            if ( is_wp_error( $response ) ) {
                continue;
            }

            $code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( 200 !== $code || empty( $data ) || ! is_array( $data ) ) {
                continue;
            }

            $results = array();

            if ( ! empty( $data['results'] ) && is_array( $data['results'] ) ) {
                $results = $data['results'];
            } elseif ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
                $results = $data['items'];
            } elseif ( ! empty( $data['data'] ) && is_array( $data['data'] ) ) {
                $results = $data['data'];
            } elseif ( ! empty( $data['content'] ) && is_array( $data['content'] ) ) {
                $results = $data['content'];
            } elseif ( ! empty( $data['documents'] ) && is_array( $data['documents'] ) ) {
                $results = $data['documents'];
            }

            if ( empty( $results ) ) {
                continue;
            }

            $items = array();

            foreach ( $results as $photo ) {
                if ( ! is_array( $photo ) ) {
                    continue;
                }

                $thumb = '';
                $full  = '';

                if ( ! empty( $photo['thumbnailUrl'] ) ) {
                    $thumb = $photo['thumbnailUrl'];
                } elseif ( ! empty( $photo['thumbnail_url'] ) ) {
                    $thumb = $photo['thumbnail_url'];
                } elseif ( ! empty( $photo['thumbnail'] ) ) {
                    $thumb = is_array( $photo['thumbnail'] ) ? ( $photo['thumbnail']['url'] ?? '' ) : $photo['thumbnail'];
                } elseif ( ! empty( $photo['renditions']['thumbnail']['url'] ) ) {
                    $thumb = $photo['renditions']['thumbnail']['url'];
                } elseif ( ! empty( $photo['renditions'][0]['url'] ) ) {
                    $thumb = $photo['renditions'][0]['url'];
                }

                if ( ! empty( $photo['downloadUrl'] ) ) {
                    $full = $photo['downloadUrl'];
                } elseif ( ! empty( $photo['download_url'] ) ) {
                    $full = $photo['download_url'];
                } elseif ( ! empty( $photo['url'] ) ) {
                    $full = $photo['url'];
                } elseif ( ! empty( $photo['renditions']['full']['url'] ) ) {
                    $full = $photo['renditions']['full']['url'];
                } elseif ( ! empty( $photo['renditions']['original']['url'] ) ) {
                    $full = $photo['renditions']['original']['url'];
                } elseif ( ! empty( $photo['renditions'][0]['url'] ) ) {
                    $full = $photo['renditions'][0]['url'];
                }

                if ( empty( $thumb ) && ! empty( $full ) ) {
                    $thumb = $full;
                }

                if ( empty( $full ) && ! empty( $thumb ) ) {
                    $full = $thumb;
                }

                if ( empty( $thumb ) || empty( $full ) ) {
                    continue;
                }

                $caption = $photo['caption'] ?? $photo['headline'] ?? $photo['title'] ?? $photo['description'] ?? '';
                $author  = $photo['byline'] ?? $photo['credit'] ?? $photo['creator'] ?? 'Reuters';

                $items[] = array(
                    'id'         => sanitize_text_field( (string) ( $photo['id'] ?? $photo['guid'] ?? md5( $full ) ) ),
                    'thumb'      => esc_url_raw( (string) $thumb ),
                    'full'       => esc_url_raw( (string) $full ),
                    'author'     => sanitize_text_field( (string) $author ),
                    'provider'   => 'reuters',
                    'credit_url' => esc_url_raw( (string) ( $photo['webUrl'] ?? $photo['web_url'] ?? $photo['url'] ?? '' ) ),
                    'caption'    => sanitize_text_field( (string) $caption ),
                );
            }

            if ( ! empty( $items ) ) {
                return array_slice( $items, 0, 10 );
            }
        }

        return array();
    }

    protected static function get_reuters_access_token( string $client_id, string $secret_id, string $audience ) : string {
        $cache_key = 'wsh_aine_reuters_token_' . md5( $client_id . '|' . $audience );
        $cached    = get_transient( $cache_key );

        if ( is_string( $cached ) && '' !== $cached ) {
            return $cached;
        }

        $token_url = 'https://auth.thomsonreuters.com/oauth/token';

        $response = wp_remote_post(
            $token_url,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept'       => 'application/json',
                ),
                'body' => array(
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $client_id,
                    'client_secret' => $secret_id,
                    'audience'      => $audience,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code || empty( $data['access_token'] ) ) {
            return '';
        }

        $access_token = sanitize_text_field( (string) $data['access_token'] );
        $expires_in   = isset( $data['expires_in'] ) ? absint( $data['expires_in'] ) : HOUR_IN_SECONDS;

        set_transient(
            $cache_key,
            $access_token,
            max( 300, $expires_in - 120 )
        );

        return $access_token;
    }

    public static function ajax_import_image(): void
    {
        if (! current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Forbidden'));
        }

        check_ajax_referer('wsh_aine_ai_editor', 'nonce');

        $image_url = isset($_POST['image_url']) ? esc_url_raw(wp_unslash($_POST['image_url'])) : '';
        $provider  = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : '';
        $author    = isset($_POST['author']) ? sanitize_text_field(wp_unslash($_POST['author'])) : '';

        if ('' === $image_url) {
            wp_send_json_error(array('message' => 'Nedostaje URL slike.'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) {
            wp_send_json_error(array('message' => $tmp->get_error_message()));
        }

        $file_array = array(
            'name'     => 'wsh-ai-image-' . time() . '.jpg',
            'tmp_name' => $tmp,
        );

        $attachment_id = media_handle_sideload($file_array, 0);

        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }

        //Cqaption/Source info
        $provider_label = '';

        if ( 'pexels' === $provider ) {
            $provider_label = 'Pexels';
        } elseif ( 'pixabay' === $provider ) {
            $provider_label = 'Pixabay';
        } elseif ( 'unsplash' === $provider ) {
            $provider_label = 'Unsplash';
        }  elseif ( 'ai_generated' === $provider ) {
            $provider_label = 'AI Generated';
        } else {
            $provider_label = ucfirst( $provider );
        }

        $image_source = $provider_label;

        if ( ! empty( $author ) ) {
            $image_source .= ' / ' . $author;
        }

        // Caption u Media Library.
        wp_update_post(
            array(
                'ID'           => $attachment_id,
                'post_excerpt' => 'Source: ' . $image_source,
            )
        );

        // Standard meta.
        update_post_meta( $attachment_id, '_wsh_aine_image_provider', $provider );
        update_post_meta( $attachment_id, '_wsh_aine_image_author', $author );
        update_post_meta( $attachment_id, '_wsh_aine_image_source', $image_source );


        // ACF field za attachment.
        if ( function_exists( 'update_field' ) ) {
            update_field( 'image_source', $image_source, $attachment_id );
        } else {
            update_post_meta( $attachment_id, 'image_source', $image_source );
        }

        $image_src = wp_get_attachment_image_src($attachment_id, 'medium');

        wp_send_json_success(
            array(
                'attachment_id' => $attachment_id,
                'image_url'     => $image_src ? $image_src[0] : wp_get_attachment_url($attachment_id),
            )
        );
    }
}
