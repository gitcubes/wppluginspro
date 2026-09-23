<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * SEOPress PRO Options Video Sitemap.
 *
 * @package SEOPress PRO
 * @subpackage Options
 */

defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

if ( ! $post ) {
	return false;
}

/*
 * YouTube Data API v3
 *
 * @var string
 */
$api_key = apply_filters( 'seopress_video_youtube_api_key', 'AIzaSyAQ6Dy36nerzetLbAt3xCClcOpOwAnpRu0' );

/*
 * YouTube remote URL to get video details
 *
 * @var string
 */
$yt_url = 'https://www.googleapis.com/youtube/v3/videos?key=' . $api_key . '&part=snippet,contentDetails,statistics,status,player';


/*
 * Get post content
 *
 * @var string
 */
$content = apply_filters( 'seopress_pro_video_sitemap_content', $post->post_content, $post->ID );

/*
 * Store our video links
 *
 * @var array
 */
$video_id = array();

/*
 * Get videos ID from content
 *
 * @var string
 * return array
*/
if ( preg_match_all( '/(youtu\.be\/|youtube\.com\/|youtube-nocookie\.com\/)(v\/|watch\?v=|embed\/)?([a-zA-Z0-9\-_]*)/', $content, $matches, PREG_SET_ORDER ) ) {
	if ( ! empty( $matches ) ) {
		foreach ( $matches as $video ) {
			if ( ! empty( $video[3] ) ) {
				$video_id[] = $video[3];
			}
		}
	}
} else {
	delete_post_meta( $post->ID, '_seopress_video_xml_yt' );
}

/*
 * Get video details from YouTube API
 *
 * @var array
 * return array
 */
if ( ! empty( $video_id ) ) {
	$video_id      = array_unique( $video_id );
	$video_details = array();

	$args = array(
		'timeout'     => 5,
		'redirection' => 5,
		'blocking'    => true,
	);
	foreach ( $video_id as $id ) {
		$video_details[] = wp_remote_get( $yt_url . '&id=' . $id, $args );
	}
}

/*
 * Build Video XML Sitemaps
 * @var array
 * return string
 */
if ( ! empty( $video_details ) ) {
	$video_xml = '';
	foreach ( $video_details as $video ) {
		if ( ! is_wp_error( $video ) && ! empty( $video['body'] ) ) {
			$video_data = json_decode( $video['body'] );
			if ( ! empty( $video_data->items ) ) {
				foreach ( $video_data->items as $item ) {
					$video_xml .= '<video:video>';
					$video_xml .= "\n";

					if ( isset( $item->snippet->thumbnails->high->url ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$video_xml .= '<video:thumbnail_loc>' . htmlspecialchars( urldecode( esc_attr( wp_filter_nohtml_kses( $item->snippet->thumbnails->high->url ) ) ) ) . '</video:thumbnail_loc>';
						$video_xml .= "\n";
					}

					if ( isset( $item->snippet->title ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$video_xml .= '<video:title><![CDATA[' . $item->snippet->title . ']]></video:title>';
						$video_xml .= "\n";
					}

					if ( isset( $item->snippet->description ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$video_xml .= '<video:description><![CDATA[' . esc_attr( wp_filter_nohtml_kses( htmlentities( substr( $item->snippet->description, 0, 2000 ) ) ) ) . ']]></video:description>';
						$video_xml .= "\n";
					}

					if ( isset( $item->id ) ) {
						$video_xml .= '<video:content_loc><![CDATA[https://www.youtube.com/watch?v=' . $item->id . ']]></video:content_loc>';
						$video_xml .= "\n";
						$video_xml .= '<video:player_loc><![CDATA[https://www.youtube.com/watch?v=' . $item->id . ']]></video:player_loc>';
						$video_xml .= "\n";
					}

					if ( isset( $item->contentDetails->duration ) && ! empty( $item->contentDetails->duration ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						try {
							$duration = new DateInterval( $item->contentDetails->duration ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
							$duration = $duration->days * 86400 + $duration->h * 3600 + $duration->i * 60 + $duration->s;

							$video_xml .= '<video:duration>' . $duration . '</video:duration>';
							$video_xml .= "\n";
						} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
							// Skip duration if format is invalid.
						}
					}

					if ( isset( $item->statistics->viewCount ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$video_xml .= '<video:view_count><![CDATA[' . $item->statistics->viewCount . ']]></video:view_count>';
						$video_xml .= "\n";
					}

					if ( isset( $item->snippet->publishedAt ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$video_xml .= '<video:publication_date><![CDATA[' . $item->snippet->publishedAt . ']]></video:publication_date>';
						$video_xml .= "\n";
					}

					$video_xml .= '<video:family_friendly>yes</video:family_friendly>';
					$video_xml .= "\n";
					$video_xml .= '</video:video>';
					$video_xml .= "\n";
				}
				update_post_meta( $post->ID, '_seopress_video_xml_yt', $video_xml );
			}
		}
	}
}
