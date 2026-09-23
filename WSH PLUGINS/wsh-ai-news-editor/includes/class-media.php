<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Media {

	public static function sideload_featured_image( string $image_url, int $post_id ) {
		$image_url = esc_url_raw( trim( $image_url ) );

		if ( '' === $image_url || $post_id <= 0 ) {
			return new WP_Error( 'invalid_image_data', __( 'Invalid image URL or post ID.', 'wsh-ai-news-editor' ) );
		}

		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$temp_file = download_url( $image_url, 30 );

		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		$path = wp_parse_url( $image_url, PHP_URL_PATH );
		$filename = is_string( $path ) ? wp_basename( $path ) : 'featured-image';

		if ( '' === $filename || '.' === $filename ) {
			$filename = 'featured-image.jpg';
		}

		$file_array = array(
			'name'     => sanitize_file_name( $filename ),
			'tmp_name' => $temp_file,
		);

		$attachment_id = media_handle_sideload( $file_array, $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $temp_file );
			return $attachment_id;
		}

		set_post_thumbnail( $post_id, (int) $attachment_id );

		return (int) $attachment_id;
	}
}