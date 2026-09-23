<?php

// ===== includes/Admin.php =====
class AdminScreens {
    /**
     * Renders a hidden modal with the Create Campaign form and simple template builder (5 sections)
     */
    public static function render_builder_modal() {
        ?>
        <div id="brevo-builder-modal" style="display:none">
            <div class="brevo-modal">
                <h2>Create Campaign</h2>
                <p class="description">Popunite podatke i generišite preview HTML. Zatim snimite kao draft, pošaljite test, zakažite ili pošaljite.</p>
                <form id="brevo-builder-form">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('brevo_nonce')); ?>" />
                    <div class="field"><label>Campaign name</label><input type="text" name="name" required></div>
                    <div class="field"><label>Subject</label><input type="text" name="subject" required></div>
                    <div class="field"><label>Recipients (Lists)</label>
                        <select name="listIds[]" multiple style="min-width:280px">
                            <?php self::render_list_options(); ?>
                        </select>
                        <p class="description">Drži CTRL/Cmd za odabir više lista.</p>
                    </div>

                    <fieldset class="sections">
                        <legend>Sections (max 5)</legend>
                        <?php for ($i=1; $i<=5; $i++): ?>
                            <div class="section" data-section-index="<?php echo $i; ?>">
                                <h3>Section <?php echo $i; ?></h3>
                                <label>Section title <input type="text" name="sections[<?php echo $i; ?>][title]"></label>
                                <label>Section link <input type="url" name="sections[<?php echo $i; ?>][link]"></label>
                                <label>Lead image (URL) <input type="url" name="sections[<?php echo $i; ?>][image]"></label>
                                <button class="button select-image" data-target="sections[<?php echo $i; ?>][image]">Upload/Choose Image</button>
                                <label>Items source
                                    <select name="sections[<?php echo $i; ?>][source]">
                                        <option value="none">None</option>
                                        <option value="posts">Blog posts</option>
                                        <option value="products">Woo products</option>
                                    </select>
                                </label>
                                <label>Category/IDs <input type="text" name="sections[<?php echo $i; ?>][filter]" placeholder="category slug or comma IDs"></label>
                                <label>Items limit <input type="number" name="sections[<?php echo $i; ?>][limit]" value="4" min="1" max="12"></label>
                            </div>
                        <?php endfor; ?>
                    </fieldset>

                    <div class="actions">
                        <button type="button" class="button" id="brevo-generate-preview">Generate & Preview</button>
                        <button type="button" class="button button-primary" id="brevo-create-campaign-submit" disabled>Create Draft in Brevo</button>
                        <button type="button" class="button" id="brevo-send-test" disabled>Send Test</button>
                        <button type="button" class="button" id="brevo-schedule" disabled>Schedule</button>
                        <button type="button" class="button" id="brevo-send-now" disabled>Confirm/Send</button>
                    </div>

                    <input type="hidden" name="previewFile" id="brevo-preview-file" />
                    <input type="hidden" name="campaignId" id="brevo-campaign-id" />
                </form>
            </div>
        </div>
        <?php
    }

    public static function render_builder_form_bkp() { ?>
        <form id="brevo-builder-form">
            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('brevo_nonce')); ?>" />
            <div class="field"><label>Campaign name</label><input type="text" name="name" required></div>
            <div class="field"><label>Subject</label><input type="text" name="subject" required></div>
            <div class="field"><label>Recipients (Lists)</label>
                <select name="listIds[]" multiple style="min-width:280px">
                <?php self::render_list_options(); ?>
                </select>
                <p class="description">Drži CTRL/Cmd za odabir više lista.</p>
            </div>

            <fieldset class="sections">
                <legend>Sections (max 5)</legend>
                <?php for ($i=1; $i<=5; $i++): ?>
                <div class="section" data-section-index="<?php echo $i; ?>">
                    <h3>Section <?php echo $i; ?></h3>
                    <label>Section title <input type="text" name="sections[<?php echo $i; ?>][title]"></label>
                    <label>Section link <input type="url" name="sections[<?php echo $i; ?>][link]"></label>
                    <label>Lead image (URL) <input type="url" name="sections[<?php echo $i; ?>][image]"></label>
                    <button class="button select-image" data-target="sections[<?php echo $i; ?>][image]">Upload/Choose Image</button>
                    <label>Section layout
                        <select name="sections[<?php echo $i; ?>][layout]" class="section-layout">
                            <option value="l1">Title + short description + image + Read more</option>
                            <option value="l2">Title + short description + Read more</option>
                            <option value="l3">Title + Read more</option>
                        </select>
                    </label>

                    <div class="post-picker" data-section-index="<?php echo $i; ?>">
                        <label>Search posts</label>
                        <input type="text" class="post-search" placeholder="Type to search published posts...">
                        <div class="post-results"></div>

                        <div class="post-selected">
                            <p class="description">Selected posts:</p>
                            <ul class="post-selected-list"></ul>
                        </div>

                        <!-- Hidden CSV of post IDs for this section -->
                        <input type="hidden" name="sections[<?php echo $i; ?>][postIds]" class="post-ids" value="">
                    </div>
                </div>
                <?php endfor; ?>
            </fieldset>

            <div class="actions">
                <label style="margin-left:8px">
                    <input type="checkbox" id="brevo-save-as-new" /> Save as new copy
                </label>
                <button type="button" class="button" id="brevo-generate-preview">Generate & Preview</button>
                <button type="button" class="button button-primary" id="brevo-create-campaign-submit" disabled>Create Draft in Brevo</button>
                <button type="button" class="button" id="brevo-send-test" disabled>Send Test</button>
                <button type="button" class="button" id="brevo-schedule" disabled>Schedule</button>
                <button type="button" class="button" id="brevo-send-now" disabled>Confirm/Send</button>
            </div>

            <input type="hidden" name="previewFile" id="brevo-preview-file" />
            <input type="hidden" name="campaignId" id="brevo-campaign-id" />
        </form>
    <?php }

    public static function render_builder_form($d = []) { 
        // Defaults za edit / create
        $d = wp_parse_args($d, [
            'brevo_id'     => '',
            'name'         => '',
            'subject'      => '',
            'listIds'      => [],
            'sections'     => [], // [{title,link,image,layout,postIds:[]|csv}, ...]
            'preview_file' => '',
            'template'     => 'template1.html', // ⬅️ default izabrani templejt
        ]);

        // Normalizacija izabranih lista
        $selected_lists = array_map('intval', (array)($d['listIds'] ?? []));

        ?>
        <form id="brevo-builder-form"  autocomplete="off">
            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('brevo_nonce')); ?>" />

            <div class="field">
                <label>Campaign name</label>
                <input type="text" name="name" value="<?php echo esc_attr($d['name']); ?>" required>
            </div>

            <div class="field">
                <label>Subject</label>
                <input type="text" name="subject" value="<?php echo esc_attr($d['subject']); ?>" required>
            </div>

            <div class="field">
                <label>Recipients (Lists)</label>
                <select name="listIds[]" multiple style="min-width:280px">
                    <?php 
                    // očekuje se da postoji helper koji prima $selected (ako nema, lako dodaš)
                    if (method_exists(__CLASS__, 'render_list_options')) {
                        self::render_list_options($selected_lists);
                    }
                    ?>
                </select>
                <p class="description">Drži CTRL/Cmd za odabir više lista.</p>
            </div>

            <?php
            // ---- TEMPLATE PICKER ----
            $tpl_dir = trailingslashit( plugin_dir_path( BREVO_CAMPAIGNS_FILE ) ) . 'templates/';

            // URL do foldera (za preview linkove u adminu):
            $tpl_urls = trailingslashit( plugin_dir_url( BREVO_CAMPAIGNS_FILE ) ) . 'templates/';
            
            $tpl_files = [];
            if ( is_dir( $tpl_dir ) ) {
                foreach ( glob( $tpl_dir . '*.html' ) as $p ) {
                    $base = basename($p);
                    if ( stripos($base, 'preview') !== false ) {
                        continue; // preskoči preview fajlove
                    }
                    $tpl_files[] = $base;
                }
            }
            ?>

            <div class="field">
                <label style="display:block;margin-bottom:6px;">Template</label>

                <?php if ( empty($tpl_files) ) : ?>
                    <p class="description">U folderu <code>/templates</code> nisu pronađeni templejti.</p>
                <?php else : ?>
                    <ul style="margin:6px 0 0; padding:0; list-style:none;">
                        <?php
                        $current = $d['template'] ?: 'template1.html';
                        foreach ( $tpl_files as $file ) :
                            $checked = checked( $current, $file, false );

                            // npr. template1.html -> template1_preview.html
                            $preview_file = preg_replace('/\.html$/', '_preview.html', $file);
                            $preview_url  = $tpl_urls . $preview_file;
                        ?>
                        <li style="margin-bottom:6px;">
                            <label>
                                <input type="radio" name="template" value="<?php echo esc_attr($file); ?>" <?php echo $checked; ?> />
                                <?php echo esc_html($file); ?>
                            </label>
                            &nbsp;—&nbsp;
                            <a href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener">Pogledaj izgled</a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="description">Može biti izabran samo jedan templejt. Podrazumevano: <code>template1.html</code>.</p>
                <?php endif; ?>
            </div>


            <fieldset class="sections">
                <legend>Sections (max 5)</legend>

                <?php for ($i = 1; $i <= 5; $i++):
                    // podaci za sekciju iz $d['sections']
                    $sec     = isset($d['sections'][$i-1]) ? (array)$d['sections'][$i-1] : [];
                    $title   = (string)($sec['title']  ?? '');
                    $link    = (string)($sec['link']   ?? '');
                    $image   = (string)($sec['image']  ?? '');
                    $layout  = (string)($sec['layout'] ?? 'l1');

                    // normalizuj postIds u CSV
                    $postIdsCsv = '';
                    if (!empty($sec['postIds'])) {
                        $ids = is_array($sec['postIds']) ? $sec['postIds'] : explode(',', (string)$sec['postIds']);
                        $ids = array_map('intval', array_filter(array_map('trim', $ids)));
                        $postIdsCsv = implode(',', $ids);
                    }
                ?>
                <div class="section" data-section-index="<?php echo (int)$i; ?>">
                    <h3>Section <?php echo (int)$i; ?></h3>

                    <label>Section title
                        <input type="text" name="sections[<?php echo (int)$i; ?>][title]" value="<?php echo esc_attr($title); ?>">
                    </label>

                    <label>Section link
                        <input type="url" name="sections[<?php echo (int)$i; ?>][link]" value="<?php echo esc_url($link); ?>">
                    </label>

                    <label>Lead image (URL)
                        <input type="url" name="sections[<?php echo (int)$i; ?>][image]" value="<?php echo esc_url($image); ?>">
                    </label>
                    <button class="button select-image" data-target="sections[<?php echo (int)$i; ?>][image]">Upload/Choose Image</button>

                    <label>Section layout
                        <select name="sections[<?php echo (int)$i; ?>][layout]" class="section-layout">
                            <option value="l1" <?php selected($layout, 'l1'); ?>>Title + short description + image + Read more</option>
                            <option value="l2" <?php selected($layout, 'l2'); ?>>Title + short description + Read more</option>
                            <option value="l3" <?php selected($layout, 'l3'); ?>>Title + Read more</option>
                            <option value="l4" <?php selected($layout, 'l4'); ?>>Title + short description</option>
                            <option value="l5" <?php selected($layout, 'l5'); ?>>Just Title</option>
                        </select>
                    </label>

                    <div class="post-picker" data-section-index="<?php echo (int)$i; ?>">
                        <label>Search posts</label>
                        <input type="text" class="post-search" placeholder="Type to search published posts...">
                        <div class="post-results" style="display:none"></div>

                        <div class="post-selected">
                            <p class="description">Selected posts:</p>
                            <ul class="post-selected-list"></ul>
                        </div>

                        <!-- Hidden CSV of post IDs for this section -->
                        <input type="hidden" name="sections[<?php echo (int)$i; ?>][postIds]" class="post-ids" value="<?php echo esc_attr($postIdsCsv); ?>">

                        <!-- Banners -->
                        <div class="banner-picker" style="margin-top:15px;">
                            <h4>Optional Banners</h4>
                            <p class="description">Dodaj slike sa linkom koje će se prikazati između vesti u ovoj sekciji.</p>

                            <div class="banner-list" data-section-index="<?php echo (int)$i; ?>">
                                <?php
                                $banners = isset($sec['banners']) && is_array($sec['banners']) ? $sec['banners'] : [];
                                if (!empty($banners)) :
                                    foreach ($banners as $bi => $bn) :
                                        $img = esc_url($bn['image'] ?? '');
                                        $link = esc_url($bn['link'] ?? '');
                                        $alt = esc_attr($bn['alt'] ?? '');
                                ?>
                                <div class="banner-item" style="margin-bottom:10px;border:1px solid #ccc;padding:6px;">
                                    <label>Banner image URL
                                        <input type="url" name="sections[<?php echo (int)$i; ?>][banners][<?php echo (int)$bi; ?>][image]" value="<?php echo $img; ?>">
                                    </label>
                                    <label>Banner link
                                        <input type="url" name="sections[<?php echo (int)$i; ?>][banners][<?php echo (int)$bi; ?>][link]" value="<?php echo $link; ?>">
                                    </label>
                                    <label>Alt text (optional)
                                        <input type="text" name="sections[<?php echo (int)$i; ?>][banners][<?php echo (int)$bi; ?>][alt]" value="<?php echo $alt; ?>">
                                    </label>
                                    <button type="button" class="button remove-banner">Remove</button>
                                </div>
                                <?php endforeach;
                                endif;
                                ?>
                            </div>

                            <button type="button" class="button add-banner" data-section="<?php echo (int)$i; ?>">+ Add Banner</button>
                        </div>


                    </div>
                </div>
                <?php endfor; ?>
            </fieldset>

            <div class="actions">
                <label style="margin-left:8px">
                    <input type="checkbox" id="brevo-save-as-new" /> Save as new copy
                </label>

                <button type="button" class="button" id="brevo-generate-preview">Generate & Preview</button>
                <button type="button" class="button button-primary" id="brevo-create-campaign-submit" disabled>
                    <?php echo ($d['brevo_id'] > 0) ? 'Save / Update draft' : 'Create Draft in Brevo'; ?>
                </button>
                <button type="button" class="button" id="brevo-send-test" disabled>Send Test</button>
                <button type="button" class="button" id="brevo-schedule" disabled>Schedule</button>
                <button type="button" class="button" id="brevo-send-now" disabled>Confirm/Send</button>
            </div>

            <!-- Hidden meta -->
            <input type="hidden" name="previewFile" id="brevo-preview-file" value="<?php echo esc_attr($d['preview_file']); ?>" />
            <input type="hidden" name="campaignId" id="brevo-campaign-id" value="<?php echo (($d['brevo_id'] > 0) ? esc_attr($d['brevo_id']): '' ); ?>" />
        </form>
        <?php
    }

    private static function render_list_options() {
        try {
            $plugin = $GLOBALS['brevo_plugin_instance'] ?? null;
            if (!$plugin || !($plugin instanceof BrevoCampaignsPlugin)) {
                // fallback: instantiate a temp client
                $opts = get_option(BrevoCampaignsPlugin::OPTION_KEY, []);
                $api = new BrevoApiClient($opts['api_key'] ?? '');
            } else {
                $api = $plugin->api;
            }
            $lists = $api->get_lists();
            foreach (($lists['lists'] ?? []) as $l) {
                printf('<option value="%d">%s</option>', intval($l['id']), esc_html($l['name']));
            }
        } catch (Exception $e) {
            echo '<option disabled>Load lists error: ' . esc_html($e->getMessage()) . '</option>';
        }
    }
}
