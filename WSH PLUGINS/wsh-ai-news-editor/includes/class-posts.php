<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Posts {

	public static function create_draft( array $data ) {
		$title            = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
		//$content          = isset( $data['content'] ) ? wp_kses_post( $data['content'] ) : '';
		$content          = isset( $data['content'] ) ? wsh_aine_sanitize_editor_content( (string) $data['content'] ) : '';
		$excerpt          = isset( $data['excerpt'] ) ? sanitize_textarea_field( $data['excerpt'] ) : '';
		$seo_title        = isset( $data['seo_title'] ) ? sanitize_text_field( $data['seo_title'] ) : '';
		$tags_raw         = isset( $data['tags'] ) ? sanitize_text_field( $data['tags'] ) : '';
		$categories       = isset( $data['categories'] ) && is_array( $data['categories'] ) ? array_map( 'absint', $data['categories'] ) : array();
		$source           = isset( $data['source_payload'] ) && is_array( $data['source_payload'] ) ? $data['source_payload'] : array();
		$use_source_image = ! empty( $data['use_source_image'] );
		$options     = get_option( 'wsh_aine_settings', array() );
		$post_status = isset( $options['default_post_status'] ) ? sanitize_key( $options['default_post_status'] ) : 'draft';
		$default_author = intval($options['default_author']);
		$author_id   = isset($default_author) && $default_author > 0 ? $default_author : get_current_user_id();

		$custom_image_id  = isset( $data['custom_image_id'] ) ? absint( $data['custom_image_id'] ) : 0;

		$generated_comments = isset( $data['generated_comments'] ) && is_array( $data['generated_comments'] )
		? array_values( array_filter( array_map( 'sanitize_textarea_field', $data['generated_comments'] ) ) )
		: array();

		if ( '' === $title ) {
			return new WP_Error( 'missing_title', __( 'Title is required.', 'wsh-ai-news-editor' ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_status'   => $post_status,
				'post_title'    => $title,
				'post_content'  => $content,
				'post_excerpt'  => $excerpt,
				'post_author'   => $author_id,
				'post_category' => $categories,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( '' !== $tags_raw ) {
			$tags = array_filter( array_map( 'trim', explode( ',', $tags_raw ) ) );
			if ( ! empty( $tags ) ) {
				wp_set_post_tags( $post_id, $tags, false );
			}
		}

		update_post_meta( $post_id, '_wsh_aine_source_type', $source['source_type'] ?? '' );
		update_post_meta( $post_id, '_wsh_aine_source_name', $source['source_name'] ?? '' );
		update_post_meta( $post_id, '_wsh_aine_origin_title', $source['origin_title'] ?? '' );
		update_post_meta( $post_id, '_wsh_aine_origin_url', $source['origin_url'] ?? '' );
		update_post_meta( $post_id, '_wsh_aine_origin_text', $source['origin_text'] ?? '' );
		update_post_meta( $post_id, '_wsh_aine_image', $source['image'] ?? '' );
		update_post_meta( $post_id, '_wsh_aine_embed_type', $source['embed_type'] ?? '' );
		update_post_meta( $post_id, '_wsh_aine_embed_url', $source['embed_url'] ?? '' );
		update_post_meta( $post_id, '_wsh_aine_seo_title', $seo_title );
		update_post_meta( $post_id, '_wsh_aine_generated_at', current_time( 'mysql' ) );

		if ( $custom_image_id > 0 ) {
			set_post_thumbnail( $post_id, $custom_image_id );
			update_post_meta( $post_id, '_wsh_aine_custom_featured_image_id', $custom_image_id );
		} elseif ( $use_source_image && ! empty( $source['image'] ) ) {
			$result = WSH_AINE_Media::sideload_featured_image( (string) $source['image'], (int) $post_id );

			if ( is_wp_error( $result ) ) {
				update_post_meta( $post_id, '_wsh_aine_featured_image_error', $result->get_error_message() );
			}
		}

		if ( ! empty( $generated_comments ) ) {
			update_post_meta( $post_id, '_wsh_aine_generated_comments', $generated_comments );
		}

		/*$action = isset( $data['action'] ) ? sanitize_text_field( $data['action'] ) : '';
		if($action == 'wsh_aine_create_draft_with_comments'){
			if ( ! empty( $generated_comments ) ) {
				WSH_AINE_Comments::insert_generated_comments( (int) $post_id, $generated_comments );
				update_post_meta( $post_id, '_wsh_aine_comments_inserted', 1 );
			}
		}*/

		return $post_id;
	}

	public static function create_draft_and_insert_comments( array $data ) {
		$post_id = self::create_draft( $data );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$generated_comments = isset( $data['generated_comments'] ) && is_array( $data['generated_comments'] )
			? array_values( array_filter( array_map( 'sanitize_textarea_field', $data['generated_comments'] ) ) )
			: array();

		if ( ! empty( $generated_comments ) ) {
			WSH_AINE_Comments::insert_generated_comments( (int) $post_id, $generated_comments );
			update_post_meta( $post_id, '_wsh_aine_comments_inserted', 1 );
		}

		return $post_id;
	}


}