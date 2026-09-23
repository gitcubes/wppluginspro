<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSH_AINE_Comments {

	public static function insert_generated_comments( int $post_id, array $raw_comments ) : array {
		if ( $post_id <= 0 ) {
			return array();
		}

		$inserted = array();

		foreach ( $raw_comments as $line ) {
			$line = sanitize_textarea_field( (string) $line );

			if ( '' === trim( $line ) ) {
				continue;
			}

			$parsed = wsh_aine_parse_comment_line( $line );
			$author = $parsed['author'] ?? '@Komentator';
			$text   = $parsed['text'] ?? '';

			if ( '' === $text ) {
				continue;
			}

			$commentdata = array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => ltrim( $author, '@' ),
				'comment_author_email' => '',
				'comment_author_url'   => '',
				'comment_content'      => $text,
				'comment_type'         => '',
				'comment_parent'       => 0,
				'user_id'              => 0,
				'comment_approved'     => 1,
			);

			$comment_id = wp_insert_comment( wp_slash( $commentdata ) );

			if ( $comment_id ) {
				$inserted[] = (int) $comment_id;
			}
		}

		return $inserted;
	}
}
