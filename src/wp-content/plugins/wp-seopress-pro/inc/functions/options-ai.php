<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * SEOPress PRO Options AI.
 *
 * @package SEOPress PRO
 * @subpackage Options
 */

defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

/**
 * Generate image metadata (alt text, caption, description) when sending an image to WP
 *
 * @param string $post_ID The post ID.
 *
 * @return void
 */
function seopress_ai_alt_text_upload( $post_ID ) {
	if ( seopress_pro_get_service( 'OptionPro' )->getAIOpenaiAltText() !== '1' ) {
		return;
	}

	if ( ! isset( $post_ID ) ) {
		return;
	}

	if ( false === wp_attachment_is_image( $post_ID ) ) {
		return;
	}

	// Check if the upload is from a Gravity Forms request.
	if ( isset( $_POST['gform_submit'] ) || isset( $_POST['gform_unique_id'] ) ) {
		return;
	}

	$language = function_exists( 'seopress_get_current_lang' ) ? seopress_get_current_lang() : get_locale();

	// generateImgAltText now generates all three fields (alt_text, caption, description) and saves them
	seopress_pro_get_service( 'Completions' )->generateImgAltText( $post_ID, 'image_meta', $language );
}
add_action( 'add_attachment', 'seopress_ai_alt_text_upload', 20 );

/**
 * Add AI button to media modal
 *
 * @param array  $form_fields The form fields.
 * @param object $post The post.
 *
 * @return array $form_fields
 */
function seopress_ai_alt_text_media_modal( $form_fields, $post ) {
	$language = function_exists( 'seopress_get_current_lang' ) ? seopress_get_current_lang() : get_locale();

	$form_fields['seopress_ai_generate_image_meta'] = array(
		'label' => esc_html__( 'AI', 'wp-seopress-pro' ),
		'input' => 'html',
		'html'  => sprintf(
			'<div id="seopress-ai-generate-image-meta" style="display:flex;align-items:center;gap:8px;">
				<span class="spinner" style="float:none;margin:0;"></span>
				<button
					class="seopress-ai-generate-image-meta seopress-ai-icon-button"
					type="button"
					title="%s"
					style="border:none;background:transparent;padding:6px 12px;min-width:auto;cursor:pointer;transition:background-color 0.2s ease-in-out, border-radius 0.2s ease-in-out;"
					onmouseover="this.style.backgroundColor=\'#f0f0f1\';this.style.borderRadius=\'4px\';this.querySelector(\'svg\').style.transform=\'rotate(-15deg) scale(1.1)\';"
					onmouseout="this.style.backgroundColor=\'transparent\';this.style.borderRadius=\'0\';this.querySelector(\'svg\').style.transform=\'rotate(0deg) scale(1)\';"
					onclick="SEOPRESSMedia.generateImageMeta(%d, \'%s\')"
				>
					<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block;transition:transform 0.2s ease-in-out;">
						<path d="m21.64 3.64-1.28-1.28a1.21 1.21 0 0 0-1.72 0L2.36 18.64a1.21 1.21 0 0 0 0 1.72l1.28 1.28a1.2 1.2 0 0 0 1.72 0L21.64 5.36a1.2 1.2 0 0 0 0-1.72"/><path d="m14 7 3 3"/><path d="M5 6v4"/><path d="M19 14v4"/><path d="M10 2v2"/><path d="M7 8H3"/><path d="M21 16h-4"/><path d="M11 3H9"/>
					</svg>
				</button>
				<span style="font-size:12px;color:#646970;">%s</span>
				<a href="%s" style="font-size:12px;color:#646970;">%s</a>
				<div class="seopress-error" style="display:none;color:#d63638;font-size:12px;"></div>
			</div>',
			esc_attr__( 'Generate alt text, caption, and description with AI', 'wp-seopress-pro' ),
			(int) $post->ID,
			(string) $language,
			esc_html__( 'Alt, Caption, Description', 'wp-seopress-pro' ),
			esc_url( admin_url( 'admin.php?page=seopress-pro-page#tab=tab_seopress_ai' ) ),
			esc_html__( 'Configure AI', 'wp-seopress-pro' )
		),
	);
	return $form_fields;
}
add_filter( 'attachment_fields_to_edit', 'seopress_ai_alt_text_media_modal', 10, 2 );
