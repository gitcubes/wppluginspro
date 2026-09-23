<?php
/**
 * The template for displaying comments
 *
 * This is the template that displays the area of the page that contains both the current comments
 * and the comment form.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package news_theme
 */

/*
 * If the current post is protected by a password and
 * the visitor has not yet entered the password we will
 * return early without loading the comments.
 */
if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="comments-area d-flex flex-column">

	<?php
	// You can start editing here -- including this comment!
	if ( have_comments() ) :
		?>
		<h2 class="comments-title">
			<?php
			$news_theme_comment_count = get_comments_number();
			if ( '1' === $news_theme_comment_count ) {
				printf(
					/* translators: 1: title. */
					esc_html__( 'Comment(1)', 'cubestheme' ),
					'<span>' . wp_kses_post( get_the_title() ) . '</span>'
				);
			} else {
				printf( 
					/* translators: 1: comment count number, 2: title. */
					esc_html( _nx( 'Comment(%1$s)', 'Comments(%1$s)', $news_theme_comment_count, 'comments title', 'cubestheme' ) ),
					number_format_i18n( $news_theme_comment_count ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'<span>' . wp_kses_post( get_the_title() ) . '</span>'
				);
			}
			?>
		</h2><!-- .comments-title -->
		<?php the_comments_navigation(); ?>
                
                    <ol class="comment-list" style="order:4">
			<?php
			wp_list_comments(
				array(
					'style'      => 'ol',
					'short_ping' => true,
				)
			);
			?>
		</ol><!-- .comment-list -->

		<?php
		the_comments_navigation();

		// If comments are closed and there are comments, let's leave a little note, shall we?
		if ( ! comments_open() ) :
			?>
                            <p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'cubestheme' ); ?></p>
			<?php
		endif;

	endif; // Check for have_comments().

	comment_form();
	?>
                
</div><!-- #comments -->
