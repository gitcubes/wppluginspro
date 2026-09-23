<?php


// ===== includes/TemplateBuilder.php =====
class BrevoTemplateBuilder
{

    /**
     * Build HTML using provided payload and WP content (posts/products) for up to 5 sections.
     * $payload structure mirrors the form fields.
     */
    public function build_html_from_payload($payload)
    {
        $opts = get_option(BrevoCampaignsPlugin::OPTION_KEY, []);
        $subject = sanitize_text_field($payload['subject'] ?? '');
        $preheader = $opts['preheader_text'] ?? '';
        $logo = $opts['logo_url'] ?? '';
        $site = get_bloginfo('url');
        $cta_color = $opts['cta_color'] ?? '#34af0c';
        $cta_radius = intval($opts['cta_radius'] ?? 4);

        $template = sanitize_text_field($payload['template'] ?? 'template1.html');

        $slogan_html = '';
        if (!empty($opts['sender_slogan'])) {
            $slogan_html = "<tr><td class='mailpoet_text mailpoet_padded_vertical' style='padding:10px 20px; border-bottom: double 4px $cta_color; text-align: center;'><h1 style='margin:0 0 10px;color:#333333;font-family:Arial,Helvetica,sans-serif;font-size:22px'>" . esc_html($opts['sender_slogan']) . "</h1></td></tr>";
        }

        $social_links  = [
            'facebook'  => $opts['social_facebook']  ?? '',
            'youtube'   => $opts['social_youtube']   ?? '',
            'x'         => $opts['social_x']         ?? '',
            'instagram' => $opts['social_instagram'] ?? '',
            'tiktok'    => $opts['social_tiktok']    ?? '',
            'linkedin'  => $opts['social_linkedin']  ?? '',
        ];

        $sections = $payload['sections'] ?? [];

        // Build sections in a MailPoet-like structure (image block + text block)
        $sections_html = '';
        foreach ($sections as $sec) {
            $title = sanitize_text_field($sec['title'] ?? '');
            $link  = esc_url_raw($sec['link'] ?? '');
            $link_utm = $link ? $this->apply_utm($link, $subject, ['content' => $title]) : '';
            $image = esc_url_raw($sec['image'] ?? '');
            $source = sanitize_text_field($sec['source'] ?? 'none');
            $filter = sanitize_text_field($sec['filter'] ?? '');
            $limit  = intval($sec['limit'] ?? 4);

            $layout = sanitize_text_field($sec['layout'] ?? 'l1');
            $post_ids_csv = $sec['postIds'] ?? [];
            $post_ids = is_array($post_ids_csv) ? array_map('intval', $post_ids_csv)
                : array_map('intval', array_filter(array_map('trim', explode(',', (string)$post_ids_csv))));
            
            //Banners
            $banners = [];
            if (!empty($sec['banners']) && is_array($sec['banners'])) {
                foreach ($sec['banners'] as $bn) {
                    $banners[] = [
                        'type'  => 'banner',
                        'image' => $bn['image'] ?? '',
                        'link'  => $bn['link'] ?? '',
                        'alt'   => $bn['alt'] ?? '',
                        'position'   => $bn['position'] ?? '',
                    ];
                }
            }
            //print_r($banners);

            $cards = $this->render_posts_by_ids($post_ids, $layout, $subject, $title, $template, $banners);
            //$cards = $this->render_section_items($sec, $layout, $subject, $title, $template, $post_ids);

            // Section header + optional lead image
            $sections_html .= "
              <tr>
                <td class='mailpoet_text mailpoet_padded_vertical' valign='top' style='padding:10px 20px;word-break:break-word;'>
                  " . ($title ? "<h3 style=\"margin:0 0 6.6px;color:#333333;font-family:Arial,Helvetica,sans-serif;\">" . ($link ? "<a href='" . esc_url($link_utm) . "' target='_blank' style='text-decoration: none; color: #fff; background-color: $cta_color; padding: 10px 20px; border-radius: 5px;'>" : "") . "<strong>" . esc_html($title) . "</strong>" . ($link ? "</a>" : "") . "</h3>" : '') . "
                </td>
              </tr>
            ";
            if ($image) {
                $sections_html .= "
                <tr>
                  <td class='mailpoet_image mailpoet_padded_vertical' align='center' style='padding:10px 20px;'>"
                    . ($image ? "<p>" . ($link ? "<a href='" . esc_url($link_utm) . "' target='_blank'>" : "") . "<img src='" . esc_url($image) . "' alt='lead' style='max-width:100%'>" . ($link ? "</a>" : "") . "</p>" : '') .
                    "</td>
                </tr>";
            }

            // Items under section
            $sections_html .= $cards;

            // Divider
            $sections_html .= "
              <tr>
                <td class='mailpoet_divider' style='padding:10px 20px;'>
                  <table width='100%' cellpadding='0' cellspacing='0' border='0'>
                    <tr><td style='border-top:4px double #e3e3e3;font-size:0;line-height:0'>&nbsp;</td></tr>
                  </table>
                </td>
              </tr>";
        }

        // Header (logo)
        $logo_html = $logo ? ("<tr><td class='mailpoet_image mailpoet_padded_vertical' align='center' style='padding:10px 20px; border-top: double 4px $cta_color; text-align: center;'>
        <a href='" . esc_url($site) . "' style='text-decoration:none' target='_blank'><img src='" . esc_url($logo) . "' alt='" . esc_attr(get_bloginfo('name')) . "' width='600' style='max-width:100%;height:auto;display:block;border:0;outline:none;text-align:center' /></a></td></tr>") : '';

        $footer_html = $this->render_social_footer($social_links);

        $website_link = home_url('/');

        $template = sanitize_text_field($payload['template'] ?? 'template1.html');
        $template_url = $this->asset_url('templates/' . $template);
        $html = $this->render_template_html($template, $cta_color, $subject, $logo_html, $preheader, $slogan_html, $sections_html, $footer_html, $website_link);


        // Build full HTML
        $html_bkp = "<!doctype html><html lang='en' style='margin:0;padding:0'><head>
        <meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
        <meta name='viewport' content='width=device-width, initial-scale=1' />
        <meta http-equiv='X-UA-Compatible' content='IE=edge' />
        <meta name='format-detection' content='telephone=no' />
        <style type='text/css'>
        :root {
        --main-color: $cta_color;
        };
        a:hover { opacity: 0.8; };

        /* L1 – shared */
        .l1-row .l1-col { vertical-align: top; }

        /* Desktop widths */
        .l1-col-img  { display:inline-block; width:280px; max-width:280px; }
        .l1-col-text { display:inline-block; width: calc(100% - 300px); max-width:100%; }

        /* Img */
        .l1-col-img img { display:block; width:280px; max-width:280px; height:auto; border:0; outline:none; }

        /* Text */
        .l1-excerpt { margin:0; color:#444; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:20px; word-break:normal; }

        /* Mobile stack */
        @media only screen and (max-width:600px){
            .l1-col,
            .l1-col-img,
            .l1-col-text { display:block !important; width:100% !important; max-width:100% !important; }
            .l1-col-img img { width:100% !important; max-width:100% !important; height:auto !important; }
            .l1-cta-wrap { text-align:left !important; padding-top:10px !important; }
        }
        </style>
        <title>" . esc_html($subject) . "</title>
        </head>
        <body style='margin:0;padding:0;background-color:#f2f2f2'>
        <span style='display:none!important;visibility:hidden;opacity:0;height:0;width:0;overflow:hidden;mso-hide:all'>" . esc_html($preheader ?: $subject) . "</span>
        <table class='mailpoet_template' width='100%' cellpadding='0' cellspacing='0' border='0' style='border-collapse:collapse;background-color:#f2f2f2;'>
            <tr>
            <td align='center' class='mailpoet-wrapper' valign='top'>
                <table class='mailpoet_content-wrapper' width='100%' cellpadding='0' cellspacing='0' border='0' style='max-width:660px;width:100%'>
                <tr>
                    <td class='mailpoet_content' align='center'>
                    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='background-color:#ffffff;margin-left:auto;margin-right:auto; padding: 20px;'>
                        " . $logo_html . "
                        " . $slogan_html . "
                        " . $sections_html . "
                        " . $footer_html . "
                        <tr>
                        <td class='mailpoet_text' style='padding:20px;color:#888888;font-family:Arial,Helvetica,sans-serif;font-size:12px'>
                            <p style='margin:0; text-align: center;'>You’re receiving this email because you opted in to our mailing list at <a href='" . $website_link . "' target='_blank'>" . $website_link . "</a></p>
                            <p style='text-align: center; margin:8px 0;color:#777;font-size:12px;line-height:18px'>If you no longer wish to receive these emails, <a href='{{ unsubscribe }}' target='_blank' rel='noopener' style='color:#777;text-decoration:underline'>unsubscribe here</a>.&nbsp;|&nbsp;<a href='{{ mirror }}' target='_blank' rel='noopener' style='color:#777;text-decoration:underline'>View in browser</a></p>
                        </td>
                        </tr>
                    </table>
                    </td>
                </tr>
                </table>
            </td>
            </tr>
        </table>
        </body></html>";

        return $html;
    }

    private function render_posts_by_ids($ids, $layout, $subject, $section_title, $template, $banners = null)
    {

        //Special sections
        $special_titles = array("jobs", "poslovi", "events", "dogadjaji", "događaji");
        $title_index = strtolower($section_title);
        if (in_array($title_index, $special_titles)) {
            $html = '';
            if ($title_index == "jobs" || $title_index == "poslovi") {
                if (have_rows('jobs_repeater', 'options')):
                    $cnt = 0;
                    while (have_rows('jobs_repeater', 'options')) : the_row();
                        $title = get_sub_field('name');
                        $url = get_sub_field('url');
                        if (empty($url)) $url = "https://balkangreenenergynews.com/jobs";
                        $thumb = $category = $country = '';
                        $excerpt = get_sub_field('date') . " | " . get_sub_field('location') . " | " . get_sub_field('employer');
                        $html .= $this->card_html_layout($title, $url, $thumb, $excerpt, $subject, $layout, $category, $country, $template);
                        $cnt++;
                        if ($cnt == 5) break;
                    endwhile;
                endif;
            } else {
                if (have_rows('event_repeater', 'options')):
                    $cnt = 0;
                    while (have_rows('event_repeater', 'options')) : the_row();
                        $title = get_sub_field('name');
                        $url = get_sub_field('url');
                        if (empty($url)) $url = "https://balkangreenenergynews.com/events-dates";
                        $thumb = $category = $country = '';
                        $excerpt = get_sub_field('date') . " | " . get_sub_field('location') . " | " . get_sub_field('organiser');
                        $html .= $this->card_html_layout($title, $url, $thumb, $excerpt, $subject, $layout, $category, $country, $template);
                        $cnt++;
                        if ($cnt == 5) break;
                    endwhile;
                endif;
            }
            return $html;
        }
        //End special sections

        if (empty($ids)) return '';

        $ass_banners = [];
        if(!empty($banners)){
            foreach($banners as $bn){
                $ass_banners[$bn['position']] = [
                    'type'  => 'banner',
                    'image' => $bn['image'] ?? '',
                    'link'  => $bn['link'] ?? '',
                    'alt'   => $bn['alt'] ?? '',
                    'position'   => $bn['position'] ?? '',
                ];
            }
        }
             
        $args = [
            'post_type' => 'post',
            'post__in' => $ids,
            'orderby' => 'post__in',
            'posts_per_page' => count($ids),
            'post_status' => 'publish'
        ];
        $q = new WP_Query($args);

        //Banners
        $html = '';
        if(isset($ass_banners[0]) && !empty($ass_banners[0])){
            $html .= $this->card_html_banner($ass_banners[0]['image'], $ass_banners[0]['link'], $ass_banners[0]['alt']);
        }

        $index = 1;
        while ($q->have_posts()) {
            $q->the_post();
            $title = get_the_title();
            $url   = get_permalink();
            $thumb = get_the_post_thumbnail_url(get_the_ID(), 'large') ?: '';
            $excerpt = wp_strip_all_tags(get_the_excerpt());
            $country_terms = get_the_terms(get_the_ID(), 'country');
            $country = "";
            if (! empty($country_terms) && ! is_wp_error($country_terms)) {
                foreach ($country_terms as $ct) {
                    $country .= ($country == "") ? $ct->name : ", " . $ct->name;
                }
            }

            $terms = get_the_terms(get_the_ID(), 'category');
            $category = "";
            if (! empty($terms) && ! is_wp_error($terms)) {
                foreach ($terms as $t) {
                    $category .= ($category == "") ? $t->name : ", " . $t->name;
                }
            }

            $html .= $this->card_html_layout($title, $url, $thumb, $excerpt, $subject, $layout, $category, $country, $template);

            //Banners
            if(isset($ass_banners[$index]) && !empty($ass_banners[$index])){
                $html .= $this->card_html_banner($ass_banners[$index]['image'], $ass_banners[$index]['link'], $ass_banners[$index]['alt']);
            }
            $index++;
        }
        wp_reset_postdata();


        return $html;
    }

    private function render_section_items(array $sec, string $layout, string $subject, string $section_title, string $template, array $fallback_post_ids = []): string
    {
        // Ako nema 'items', koristi stari mehanizam radi kompatibilnosti
        if (empty($sec['items']) || !is_array($sec['items'])) {
            return $this->render_posts_by_ids($fallback_post_ids, $layout, $subject, $section_title, $template);
        }

        $items = $sec['items'];

        // Prikupi sve ID-eve postova iz items da bismo ih učitali efikasno
        $post_ids = [];
        foreach ($items as $it) {
            if (($it['type'] ?? '') === 'post' && !empty($it['id'])) {
                $post_ids[] = (int)$it['id'];
            }
        }
        $post_ids = array_values(array_unique(array_filter($post_ids)));

        // Napravi mapu podataka o postovima: id => [title,url,thumb,excerpt,category,country]
        $post_map = [];
        if (!empty($post_ids)) {
            $args = [
                'post_type'      => 'post',
                'post__in'       => $post_ids,
                'orderby'        => 'post__in',
                'posts_per_page' => count($post_ids),
                'post_status'    => 'publish',
                'ignore_sticky_posts' => true,
            ];
            $q = new \WP_Query($args);
            while ($q->have_posts()) {
                $q->the_post();
                $pid     = get_the_ID();
                $title   = get_the_title();
                $url     = get_permalink();
                $thumb   = get_the_post_thumbnail_url($pid, 'large') ?: '';
                $excerpt = wp_strip_all_tags(get_the_excerpt($pid));

                $country_terms = get_the_terms($pid, 'country');
                $country = '';
                if (!empty($country_terms) && !is_wp_error($country_terms)) {
                    foreach ($country_terms as $ct) {
                        $country .= ($country ? ', ' : '') . $ct->name;
                    }
                }
                $cats = get_the_terms($pid, 'category');
                $category = '';
                if (!empty($cats) && !is_wp_error($cats)) {
                    foreach ($cats as $t) {
                        $category .= ($category ? ', ' : '') . $t->name;
                    }
                }

                $post_map[$pid] = compact('title', 'url', 'thumb', 'excerpt', 'category', 'country');
            }
            wp_reset_postdata();
        }

        // Složi HTML po redosledu iz items
        $html = '';
        foreach ($items as $it) {
            $type = $it['type'] ?? 'post';

            if ($type === 'post') {
                $pid = (int)($it['id'] ?? 0);
                if (!$pid || empty($post_map[$pid])) {
                    continue;
                }
                $p = $post_map[$pid];

                $html .= $this->card_html_layout(
                    $p['title'],
                    $p['url'],
                    $p['thumb'],
                    $p['excerpt'],
                    $subject,
                    $layout,
                    $p['category'],
                    $p['country'],
                    $template
                );
            } elseif ($type === 'banner') {
                // Očekujemo: image, link, alt (opciono width)
                $img  = esc_url_raw($it['image'] ?? '');
                $link = esc_url_raw($it['link']  ?? '');
                $alt  = sanitize_text_field($it['alt'] ?? '');
                $width = isset($it['width']) ? (int)$it['width'] : 620;

                if (!$img) {
                    continue;
                }

                // UTM i za baner (content => section title ili alt)
                $utm_link = $link ? $this->apply_utm($link, $subject, ['content' => ($alt ?: $section_title)]) : '';

                $html .= $this->card_html_banner($img, $utm_link, $alt, $width);
            }
        }

        return $html;
    }

    private function card_html_banner(string $img, string $link = '', string $alt = '', int $width = 620): string {
        $img_tag =
            "<img src='".esc_url($img)."' alt='".esc_attr($alt ?: 'banner')."' width='".(int)$width."' style='max-width:100%;height:auto;display:block;border:0;outline:none;text-align:center' />";

        $inner = $link ? "<a href='".esc_url($link)."' target='_blank'>".$img_tag."</a>" : $img_tag;

        return "
        <tr>
            <td class='mailpoet_image mailpoet_padded_vertical' align='center' style='padding:10px 20px'>
                {$inner}
            </td>
        </tr>";
    }

    private function card_html_layout($title, $url, $img, $excerpt, $subject, $layout, $category, $country, $template)
    {
        $opts = get_option(BrevoCampaignsPlugin::OPTION_KEY, []);
        $cta_color = $opts['cta_color'] ?? '#34af0c';
        $cta_radius = isset($opts['cta_radius']) ? intval($opts['cta_radius']) : 4;
        $cta_text = $opts['cta_text'] ?? 'Read more →';

        $url_utm = $this->apply_utm($url, $subject, ['content' => $title]);
        $excerpt_trimmed = method_exists($this, 'strim') ? $this->strim($excerpt, 300, '…') : $excerpt;

        $title_html = '<h3 style="margin:0 0 6.6px;mso-ansi-font-size:22px;color:#333333;font-family:Arial, Helvetica Neue,Helvetica,sans-serif;font-size:22px;line-height:26.4px;mso-line-height-alt:26px;margin-bottom:0;text-align:left;padding:0;font-style:normal;font-weight:normal">
                       <strong><a href="' . esc_url($url_utm) . '" style="color:' . esc_attr($cta_color) . ';text-decoration:none">' . esc_html($title) . '</a></strong>
                       </h3>';

        $country_title = '';
        if (!empty($country)) {
            $country_title = '<h3 style="margin:0 0 6.6px;mso-ansi-font-size:22px;color:#333333;font-family:Arial, Helvetica Neue ,Helvetica,sans-serif;font-size:22px;line-height:26.4px;mso-line-height-alt:26px;text-align:left;padding:0;font-style:normal;font-weight:normal"><strong>' . $country . '</strong></h3>';
            $title_html = $country_title . $title_html;
        }

        $btn = "<table border='0' cellspacing='0' cellpadding='0' style='margin-top:10px'><tr><td align='left'><a href='" . esc_url($url_utm) . "' target='_blank' style='font-weight:bold;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:14px;color:#ffffff;text-decoration:none;padding:10px 0px;display:inline-block;color:" . esc_attr($cta_color) . "'>" . esc_html($cta_text) . "</a></td></tr></table>";

        // L1: Title + excerpt + image + CTA
        if ($layout === 'l1') {

            $imgRow = $img ? "<tr class='section-mobile-only'><td class='mailpoet_image mailpoet_padded_vertical' align='center' style='padding:10px 20px'>
                <a href='" . esc_url($url_utm) . "'><img src='" . esc_url($img) . "' alt='' width='620' style='max-width:100%;height:auto;display:block;border:0;outline:none;text-align:center' /></a>
            </td></tr>" : '';
            return $imgRow .
                "<tr class='section-mobile-only'><td class='mailpoet_text mailpoet_padded_vertical' valign='top' style='padding:10px 20px;word-break:break-word'>" . $title_html . "
                    <p style='margin:8px 0 0;color:#444444;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:20px'>" . esc_html($excerpt_trimmed) . "</p>" . $btn . "
                    </td></tr>";

            $img_col = $img ? "
            <table role='presentation' class='l1-col l1-col-img' align='right' width='280' cellpadding='0' cellspacing='0' border='0' style='display:inline-block;width:280px;max-width:280px;'>
                <tr>
                <td valign='top' align='right' style='padding:8px 0 0 12px;'>
                    <a href='" . esc_url($url_utm) . "' target='_blank'>
                    <img src='" . esc_url($img) . "' alt='' width='280' />
                    </a>
                </td>
                </tr>
            </table>" : '';

            $text_col = "
            <table role='presentation' class='l1-col l1-col-text' align='left' width='100%' cellpadding='0' cellspacing='0' border='0' style='display:inline-block;width:calc(100% - 300px);max-width:100%;'>
                <tr>
                <td valign='top' align='left' style='padding:8px 12px 0 0;'>
                    <p class='l1-excerpt' style='border-collapse:collapse;mso-ansi-font-size:14px;color:#000000;font-family:Arial,Helvetica Neue,Helvetica,sans-serif;font-size:13px;line-height:20.8px;mso-line-height-alt:22px;word-break:break-word;word-wrap:break-word;text-align:left'>" . esc_html($excerpt_trimmed) . "</p>
                </td>
                </tr>
            </table>";

            // redosled: TEXT levo, SLIKA desno (na mobilnom oba idu 100% jedan ispod drugog; slika će biti iznad jer ide druga? -> ne, zato ubacimo img PRVO da bude iznad!)
            // Hoćeš da na mobilnom slika bude GORE → img prvo u DOM-u.

            $backup_html =  "
            <tr class='section-desktop-only'>
                <td class='mailpoet_text mailpoet_padded_vertical' valign='top' style='padding:10px 20px;word-break:normal;'>
                {$title_html}

                <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0' class='l1-row'>
                    <tr>
                    <td align='left' style='padding:0;'>
                        {$img_col}
                        {$text_col}
                    </td>
                    </tr>
                    <tr>
                    <td class='l1-cta-wrap' align='left' style='padding-top:10px;'>{$btn}</td>
                    </tr>
                </table>
                </td>
            </tr>";
        }


        /*if ($layout === 'l1') {
            $imgRow = $img ? "<tr class="section-mobile-oonly'><td class='mailpoet_image mailpoet_padded_vertical' align='center' style='padding:10px 20px'>
                <a href='".esc_url($url_utm)."'><img src='".esc_url($img)."' alt='' width='620' style='max-width:100%;height:auto;display:block;border:0;outline:none;text-align:center' /></a>
            </td></tr>" : '';
             $imgRow .
                "<tr  class="section-mobile-oonly'><td class='mailpoet_text mailpoet_padded_vertical' valign='top' style='padding:10px 20px;word-break:break-word'>".$title_html."
                    <p style='margin:8px 0 0;color:#444444;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:20px'>".esc_html($excerpt_trimmed)."</p>".$btn."
                    </td></tr>";
        }*/

        // L2: Title + excerpt + CTA (bez slike)
        if ($layout === 'l2') {
            return "<tr><td class='mailpoet_text mailpoet_padded_vertical' valign='top' style='padding:10px 20px;word-break:break-word'>" . $title_html . "
                    <p style='margin:8px 0 0;color:#444444;font-family:Arial,Helvetica,sans-serif;font-size:18px;line-height:20px'>" . esc_html($excerpt_trimmed) . "</p>" . $btn . "
                    </td></tr>";
        }

        // L3: Title + CTA (bez opisa/slike)
        if ($layout === 'l3') {
            return "<tr><td class='mailpoet_text mailpoet_padded_vertical' valign='top' style='padding:10px 20px;word-break:break-word'>" . $title_html . $btn . "</td></tr>";
        }

        // L4: Title + excerpt
        if ($layout === 'l4') {
            return "<tr><td class='mailpoet_text mailpoet_padded_vertical' valign='top' style='padding:10px 20px;word-break:break-word'>" . $title_html . "
                    <p style='margin:8px 0 0;color:#444444;font-family:Arial,Helvetica,sans-serif;font-size:18px;line-height:20px'>" . esc_html($excerpt_trimmed) . "</p>
                    </td></tr>";
        }

        // L5: Just title
        if ($layout === 'l5') {
            return "<tr><td class='mailpoet_text mailpoet_padded_vertical' valign='top' style='padding:10px 20px;word-break:break-word'>" . $title_html . "</td></tr>";
        }
    }

    private function strim($text, $width = 240, $suffix = '…')
    {
        $text = (string)$text;
        // Ako je mbstring prisutan, koristi ga (bolja obrada UTF-8)
        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $width, $suffix, 'UTF-8');
        }
        // Fallback bez mbstring: single-byte cut + suffix
        $plain = preg_replace('/\s+/', ' ', $text);
        if (strlen($plain) <= $width) return $plain;
        return substr($plain, 0, $width) . $suffix;
    }

    private function apply_utm($url, $subject, $extra = [])
    {
        if (!$url) return $url;
        $opts = get_option(BrevoCampaignsPlugin::OPTION_KEY, []);
        if (empty($opts['utm_enable'])) return $url;

        $utm_source   = $opts['utm_source']   ?? 'newsletter';
        $utm_medium   = $opts['utm_medium']   ?? 'email';
        $utm_campaign = $opts['utm_campaign'] ?? '';
        if (!$utm_campaign) {
            // fallback na subject (slugifikovan)
            if (function_exists('sanitize_title')) $utm_campaign = sanitize_title($subject);
            else $utm_campaign = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string)$subject), '-'));
        }
        $utm_term    = $opts['utm_term']    ?? '';
        $utm_content = $opts['utm_content'] ?? '';
        if (!$utm_content && !empty($extra['content'])) {
            // ako nije zadato u settings, koristi naslov sekcije/item-a
            if (function_exists('sanitize_title')) $utm_content = sanitize_title($extra['content']);
            else $utm_content = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string)$extra['content']), '-'));
        }

        $args = array_filter([
            'utm_source'   => $utm_source,
            'utm_medium'   => $utm_medium,
            'utm_campaign' => $utm_campaign,
            'utm_term'     => $utm_term,
            'utm_content'  => $utm_content,
        ], function ($v) {
            return $v !== '' && $v !== null;
        });

        // WordPress helper za dodavanje query parametara
        return esc_url(add_query_arg($args, $url));
    }

    private function asset_url($rel_path)
    {
        // Apsolutni URL do asseta iz ovog plugina
        return plugins_url($rel_path, BREVO_CAMPAIGNS_FILE);
    }

    private function render_social_footer(array $social_links): string
    {
        // mapiraj tip -> ikona
        $icons = [
            'facebook'  => $this->asset_url('assets/icons/facebook.png'),
            'youtube'   => $this->asset_url('assets/icons/youtube.png'),
            'x'         => $this->asset_url('assets/icons/x.png'),
            'instagram' => $this->asset_url('assets/icons/instagram.png'),
            'tiktok'    => $this->asset_url('assets/icons/tiktok.png'),
            'linkedin'  => $this->asset_url('assets/icons/linkedin.png'),
        ];

        $items = [];
        foreach ($social_links as $key => $href) {
            $href = trim((string)$href);
            if ($href === '' || empty($icons[$key])) continue;

            $alt = ucfirst($key === 'x' ? 'X (Twitter)' : $key);
            $items[] =
                "<a href='" . esc_url($href) . "' target='_blank' rel='noopener' style='color:var(--main-color);text-decoration:none!important'>
                <img src='" . esc_url($icons[$key]) . "' width='32' height='32' style='width:32px;height:32px;-ms-interpolation-mode:bicubic;border:0;display:inline;outline:none;' alt='" . esc_attr($alt) . "' />
                </a>";
        }

        if (empty($items)) {
            return ''; // nema linkova => nema footera
        }

        $icons_html = implode('&nbsp;', $items);

        // identičan table-layout kao tvoj postojeći footer, samo dinamički
        return "
        <!-- FOOTER -->
        <tr>
            <td class='mailpoet_content' align='center' style='border-collapse:collapse'>
            <table width='100%' border='0' cellpadding='0' cellspacing='0' style='border-collapse:collapse;border-spacing:0;mso-table-lspace:0;mso-table-rspace:0'>
                <tbody>
                <tr>
                    <td style='border-collapse:collapse;padding-left:0;padding-right:0'>
                    <table width='100%' border='0' cellpadding='0' cellspacing='0' class='mailpoet_cols-one'
                            style='border-collapse:collapse;border-spacing:0;mso-table-lspace:0;mso-table-rspace:0;table-layout:fixed;margin-left:auto;margin-right:auto;padding-left:0;padding-right:0'>
                        <tbody>
                        <tr>
                            <td class='mailpoet_padded_side mailpoet_padded_vertical' valign='top' align='center' style='border-collapse:collapse;padding-top:10px;padding-bottom:10px;padding-left:20px;padding-right:20px'>
                            {$icons_html}
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    </td>
                </tr>
                </tbody>
            </table>
            </td>
        </tr>
        <!-- END FOOTER -->
        ";
    }

    private function render_template_html($template_ref, $cta_color, $subject, $logo_html, $preheader, $slogan_html, $sections_html, $footer_html, $website_link)
    {
        // 1) Učitaj template (prefer lokalni fajl iz /templates/)
        $tpl = '';
        $file = '';

        // Izvuci samo ime fajla radi sigurnosti (nema putanja izvan /templates)
        $basename = basename(is_string($template_ref) ? $template_ref : '');
        $local = trailingslashit(plugin_dir_path(BREVO_CAMPAIGNS_FILE)) . 'templates/' . $basename;

        if ($basename && file_exists($local)) {
            $file = $local;
            $tpl  = file_get_contents($local);
        } else {
            // fallback: pokušaj URL (ako si prosledio URL)
            $url = $template_ref;
            if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                $resp = wp_remote_get($url, ['timeout' => 10]);
                if (!is_wp_error($resp)) {
                    $tpl = wp_remote_retrieve_body($resp);
                }
            }
        }

        if (!$tpl) {
            // krajnji fallback – minimalni skelet da ne pukne
            $tpl = "<!doctype html><html><head><meta charset='UTF-8'></head><body><!-- BREVO REPLACE SECTIONS --></body></html>";
        }

        // 2) Priprema vrednosti (sanitizacija)
        $cta  = sanitize_hex_color($cta_color) ?: '#34af0c';
        $subj = wp_strip_all_tags($subject ?? '');
        $site = esc_url($website_link ?: '');

        // 3) Zamene u šablonu
        // CTA boja (u tvojoj CSS varijabli)
        $tpl = str_replace('/* <!-- BREVO REPLACE CTA COLOR --> */', $cta, $tpl);

        // Subject u <title> markeru
        $tpl = str_replace('<!-- BREVO REPLACE SUBJECT -->', esc_html($subj), $tpl);

        // Logo / Slogan / Sekcije / Footer su već HTML (svestan si izvora)
        $tpl = str_replace('<!-- BREVO REPLACE LOGO -->',    $logo_html ?? '',    $tpl);
        $tpl = str_replace('<!-- BREVO REPLACE SLOGAN -->',  $slogan_html ?? '',  $tpl);
        $tpl = str_replace('<!-- BREVO REPLACE SECTIONS -->', $sections_html ?? '', $tpl);
        $tpl = str_replace('<!-- BREVO REPLACE FOOTER -->',  $footer_html ?? '',  $tpl);

        // Website link (dvostruki marker u tvojem templatu)
        $tpl = str_replace('<!-- BREVO REPLACE WEBSITE LINK  -->', $site, $tpl);

        // 4) Preheader: ako marker postoji – zameni; ako ne postoji – ubaci odmah posle <body ...>
        $pre = wp_strip_all_tags($preheader ?? '');
        $pre_span = "<span style='display:none!important;visibility:hidden;opacity:0;height:0;width:0;overflow:hidden;mso-hide:all'>"
            . esc_html($pre) . "</span>";

        if (strpos($tpl, '<!-- BREVO REPLACE PREHEADER -->') !== false) {
            $tpl = str_replace('<!-- BREVO REPLACE PREHEADER -->', esc_html($pre), $tpl);
        } else {
            // ubaci posle otvarajućeg <body>
            $tpl = preg_replace('/(<body[^>]*>)/i', '$1' . $pre_span, $tpl, 1);
        }

        // 5) Dodatna zaštita: ako u hero/većim slikama kasnije ubacuješ <img>,
        // OBAVEZNO setuj width atribut da Outlook ne "razvali" layout.

        return $tpl;
    }
}
