<?php
if (! defined('ABSPATH')) {
	exit;
}

class WSH_AINE_AI_Post_Metabox
{

	public static function init(): void
	{
		add_action('add_meta_boxes', array(__CLASS__, 'add_metabox'));

		add_action('restrict_manage_posts', array(__CLASS__, 'render_ai_filter'));
		add_action('pre_get_posts', array(__CLASS__, 'filter_posts_by_ai_status'));

		add_filter( 'manage_post_posts_columns', array( __CLASS__, 'add_ai_column' ) );
		add_action( 'manage_post_posts_custom_column', array( __CLASS__, 'render_ai_column' ), 10, 2 );
	}

	public static function add_metabox(): void
	{
		add_meta_box(
			'wsh_aine_ai_post_info',
			__('WSH AI Editor', 'wsh-ai-news-editor'),
			array(__CLASS__, 'render_metabox'),
			'post',
			'side',
			'high'
		);
	}

	public static function render_metabox(WP_Post $post): void
	{
		$generated_at = get_post_meta($post->ID, '_wsh_aine_generated_at', true);
		$source_type  = get_post_meta($post->ID, '_wsh_aine_source_type', true);
		$source_name  = get_post_meta($post->ID, '_wsh_aine_source_name', true);

		if (empty($generated_at) && empty($source_type) && empty($source_name)) {
			echo '<p>' . esc_html__('This post was not generated through the WSH AI Editor.', 'wsh-ai-news-editor') . '</p>';
			return;
		}

		$source_label = $source_name ? $source_name : $source_type;
		?>
		<div style="padding:10px 0;">
			<div style="
				display:inline-flex;
				align-items:center;
				gap:6px;
				background:#eef7ff;
				color:#135e96;
				border:1px solid #b8d9f4;
				border-radius:20px;
				padding:6px 10px;
				font-weight:700;
				font-size:12px;
				margin-bottom:10px;
			">
				<span>🤖</span>
				<span><?php esc_html_e('WSH AI generated post', 'wsh-ai-news-editor'); ?></span>
			</div>

			<?php if (! empty($source_label)) : ?>
				<p style="margin:8px 0 0;">
					<strong><?php esc_html_e('Source:', 'wsh-ai-news-editor'); ?></strong>
					<br>
					<span><?php echo esc_html(ucfirst((string) $source_label)); ?></span>
				</p>
			<?php endif; ?>
		</div>
	<?php
	}

	public static function render_ai_filter(string $post_type): void
	{
		if ('post' !== $post_type) {
			return;
		}

		$current = isset($_GET['wsh_aine_ai_filter'])
			? sanitize_text_field(wp_unslash($_GET['wsh_aine_ai_filter']))
			: '';

	?>
		<select name="wsh_aine_ai_filter">
			<option value=""><?php esc_html_e('All AI statuses', 'wsh-ai-news-editor'); ?></option>
			<option value="ai" <?php selected($current, 'ai'); ?>>
				<?php esc_html_e('WSH AI generated', 'wsh-ai-news-editor'); ?>
			</option>
			<option value="non_ai" <?php selected($current, 'non_ai'); ?>>
				<?php esc_html_e('Not AI generated', 'wsh-ai-news-editor'); ?>
			</option>
		</select>
	<?php
	}

	public static function filter_posts_by_ai_status(WP_Query $query): void
	{
		if (! is_admin() || ! $query->is_main_query()) {
			return;
		}

		global $pagenow;

		if ('edit.php' !== $pagenow) {
			return;
		}

		$post_type = isset($_GET['post_type'])
			? sanitize_key(wp_unslash($_GET['post_type']))
			: 'post';

		if ('post' !== $post_type) {
			return;
		}

		$filter = isset($_GET['wsh_aine_ai_filter'])
			? sanitize_text_field(wp_unslash($_GET['wsh_aine_ai_filter']))
			: '';

		if ('ai' === $filter) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'     => '_wsh_aine_generated_at',
						'compare' => 'EXISTS',
					),
				)
			);
		}

		if ('non_ai' === $filter) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'     => '_wsh_aine_generated_at',
						'compare' => 'NOT EXISTS',
					),
				)
			);
		}
	}

	public static function add_ai_column( array $columns ) : array {
		$columns['wsh_aine_ai'] = __( 'AI', 'wsh-ai-news-editor' );
		return $columns;
	}

	public static function render_ai_column( string $column, int $post_id ) : void {
		if ( 'wsh_aine_ai' !== $column ) {
			return;
		}

		$generated_at = get_post_meta( $post_id, '_wsh_aine_generated_at', true );
		$source_name  = get_post_meta( $post_id, '_wsh_aine_source_name', true );
		$source_type  = get_post_meta( $post_id, '_wsh_aine_source_type', true );

		if ( empty( $generated_at ) ) {
			echo '—';
			return;
		}

		$source = $source_name ? $source_name : $source_type;

		echo '<span style="display:inline-block;padding:3px 8px;background:#2271b1;color:#fff;border-radius:12px;font-size:11px;font-weight:600;">AI</span>';

		if ( $source ) {
			echo '<br><small>' . esc_html( ucfirst( (string) $source ) ) . '</small>';
		}
	}
}
