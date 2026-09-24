<?php
if (! defined('ABSPATH')) {
	exit;
}

class WSH_Tickets
{
	public static function init()
	{
		add_action('init', array(__CLASS__, 'register_type'));
		add_action('template_redirect', array(__CLASS__, 'handle_form'));
		add_action('add_meta_boxes', array(__CLASS__, 'meta_box'));
		add_action('save_post_wsh_ticket', array(__CLASS__, 'save_ticket'), 10, 2);
		add_filter('manage_wsh_ticket_posts_columns', array(__CLASS__, 'columns'));
		add_action('manage_wsh_ticket_posts_custom_column', array(__CLASS__, 'column_value'), 10, 2);
		add_filter('manage_edit-wsh_ticket_sortable_columns', array(__CLASS__, 'sortable_columns'));
	}

	public static function activate()
	{
		self::register_type();
		flush_rewrite_rules();
	}

	public static function register_type()
	{
		register_post_type('wsh_ticket', array(
			'labels' => array(
				'name' => __('Tickets', 'wsh-ticketing'),
				'singular_name' => __('Ticket', 'wsh-ticketing'),
				'add_new_item' => __('Add ticket', 'wsh-ticketing'),
				'edit_item' => __('Ticket', 'wsh-ticketing'),
				'search_items' => __('Search tickets', 'wsh-ticketing'),
				'not_found' => __('No tickets yet.', 'wsh-ticketing'),
			),
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => true,
			'menu_icon' => 'dashicons-tickets-alt',
			'menu_position' => 56,
			'capability_type' => 'post',
			'map_meta_cap' => true,
			'supports' => array('title'),
			'capabilities' => array(
				'create_posts' => 'do_not_allow',
			),
		));

		foreach (self::statuses() as $status => $label) {
			register_post_status($status, array(
				'label' => $label,
				'public' => false,
				'internal' => false,
				'protected' => true,
				'show_in_admin_all_list' => true,
				'show_in_admin_status_list' => true,
				'label_count' => _n_noop($label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>', 'wsh-ticketing'),
			));
		}
	}

	public static function statuses()
	{
		return array(
			'wsh-open' => __('Open', 'wsh-ticketing'),
			'wsh-answered' => __('Answered', 'wsh-ticketing'),
			'wsh-closed' => __('Closed', 'wsh-ticketing'),
		);
	}

	public static function products()
	{
		return get_posts(array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'post_parent' => 0,
			'posts_per_page' => 200,
			'orderby' => 'title',
			'order' => 'ASC',
		));
	}

	public static function handle_form()
	{
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['wsh_support_request'])) {
			return;
		}

		if (! isset($_POST['wsh_support_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wsh_support_nonce'])), 'wsh_support_request')) {
			self::redirect_with('support_error', 'expired');
		}

		$name = sanitize_text_field(wp_unslash($_POST['support_name'] ?? ''));
		$email = sanitize_email(wp_unslash($_POST['support_email'] ?? ''));
		$type = sanitize_text_field(wp_unslash($_POST['support_type'] ?? ''));
		$product_id = absint($_POST['support_plugin'] ?? 0);
		$site = esc_url_raw(wp_unslash($_POST['support_site'] ?? ''));
		$priority = sanitize_text_field(wp_unslash($_POST['support_priority'] ?? 'normal'));
		$subject = sanitize_text_field(wp_unslash($_POST['support_subject'] ?? ''));
		$issue = sanitize_textarea_field(wp_unslash($_POST['support_issue'] ?? ''));
		$environment = sanitize_textarea_field(wp_unslash($_POST['support_environment'] ?? ''));
		$privacy = ! empty($_POST['support_privacy']);

		if ($name === '' || ! is_email($email) || $subject === '' || $issue === '' || ! $privacy || $product_id <= 0) {
			self::redirect_with('support_error', 'required');
		}

		$product_name = get_the_title($product_id);
		$ticket_id = wp_insert_post(array(
			'post_type' => 'wsh_ticket',
			'post_status' => 'wsh-open',
			'post_title' => $subject,
			'post_content' => $issue,
		), true);

		if (is_wp_error($ticket_id) || ! $ticket_id) {
			self::redirect_with('support_error', 'save');
		}

		$user_id = get_current_user_id();
		update_post_meta($ticket_id, 'wsh_ticket_name', $name);
		update_post_meta($ticket_id, 'wsh_ticket_email', $email);
		update_post_meta($ticket_id, 'wsh_ticket_type', $type);
		update_post_meta($ticket_id, 'wsh_ticket_product_id', $product_id);
		update_post_meta($ticket_id, 'wsh_ticket_product', $product_name);
		update_post_meta($ticket_id, 'wsh_ticket_site', $site);
		update_post_meta($ticket_id, 'wsh_ticket_priority', $priority === 'high' ? 'high' : 'normal');
		update_post_meta($ticket_id, 'wsh_ticket_environment', $environment);
		update_post_meta($ticket_id, 'wsh_ticket_user_id', $user_id);

		self::email(
			$email,
			sprintf(__('We received your support request (#%d)', 'wsh-ticketing'), $ticket_id),
			__('Request received', 'wsh-ticketing'),
			'<p style="margin:0 0 12px;font-size:16px;line-height:1.5;">' . esc_html(sprintf(__('Hi %s,', 'wsh-ticketing'), $name)) . '</p>'
			. '<p style="margin:0 0 12px;font-size:16px;line-height:1.5;">' . esc_html(sprintf(__('We received your request “%1$s” for %2$s. The ticket number is #%3$d.', 'wsh-ticketing'), $subject, $product_name, $ticket_id)) . '</p>'
			. '<p style="margin:0;font-size:16px;line-height:1.5;">' . esc_html__('We will reply to this email address.', 'wsh-ticketing') . '</p>'
		);

		$admin = get_option('admin_email');
		if (is_email($admin)) {
			self::email(
				$admin,
				sprintf(__('New support ticket #%d', 'wsh-ticketing'), $ticket_id),
				__('New ticket', 'wsh-ticketing'),
				'<p style="margin:0 0 12px;font-size:16px;line-height:1.5;"><strong>' . esc_html($subject) . '</strong></p>'
				. '<p style="margin:0 0 12px;font-size:16px;line-height:1.5;">' . esc_html($name . ' · ' . $email . ' · ' . $product_name) . '</p>'
				. '<p style="margin:0;font-size:16px;line-height:1.5;">' . nl2br(esc_html($issue)) . '</p>'
			);
		}

		self::redirect_with('ticket', (string) $ticket_id);
	}

	public static function columns($columns)
	{
		return array(
			'cb' => $columns['cb'],
			'title' => __('Subject', 'wsh-ticketing'),
			'customer' => __('Customer', 'wsh-ticketing'),
			'product' => __('Product', 'wsh-ticketing'),
			'priority' => __('Priority', 'wsh-ticketing'),
			'status' => __('Status', 'wsh-ticketing'),
			'date' => __('Received', 'wsh-ticketing'),
		);
	}

	public static function sortable_columns($columns)
	{
		$columns['status'] = 'post_status';
		return $columns;
	}

	public static function column_value($column, $post_id)
	{
		if ($column === 'customer') {
			echo esc_html(get_post_meta($post_id, 'wsh_ticket_name', true));
			echo '<br><a href="mailto:' . esc_attr(get_post_meta($post_id, 'wsh_ticket_email', true)) . '">' . esc_html(get_post_meta($post_id, 'wsh_ticket_email', true)) . '</a>';
		} elseif ($column === 'product') {
			echo esc_html(get_post_meta($post_id, 'wsh_ticket_product', true));
		} elseif ($column === 'priority') {
			echo esc_html(ucfirst((string) get_post_meta($post_id, 'wsh_ticket_priority', true)));
		} elseif ($column === 'status') {
			$status = get_post_status($post_id);
			$labels = self::statuses();
			echo esc_html($labels[$status] ?? $status);
		}
	}

	public static function meta_box()
	{
		add_meta_box('wsh_ticket_details', __('Ticket', 'wsh-ticketing'), array(__CLASS__, 'render_meta_box'), 'wsh_ticket', 'normal', 'high');
	}

	public static function render_meta_box($post)
	{
		wp_nonce_field('wsh_ticket_admin', 'wsh_ticket_admin_nonce');
		$statuses = self::statuses();
		$status = get_post_status($post);
		$replies = get_comments(array(
			'post_id' => $post->ID,
			'type' => 'wsh_reply',
			'status' => 'approve',
			'order' => 'ASC',
		));

		echo '<p><strong>' . esc_html(get_post_meta($post->ID, 'wsh_ticket_name', true)) . '</strong> · ' . esc_html(get_post_meta($post->ID, 'wsh_ticket_email', true)) . '</p>';
		echo '<p>' . esc_html(get_post_meta($post->ID, 'wsh_ticket_product', true));
		$site = (string) get_post_meta($post->ID, 'wsh_ticket_site', true);
		if ($site !== '') {
			echo ' · ' . esc_html($site);
		}
		echo ' · ' . esc_html(ucfirst((string) get_post_meta($post->ID, 'wsh_ticket_type', true)));
		echo ' · ' . esc_html(ucfirst((string) get_post_meta($post->ID, 'wsh_ticket_priority', true))) . '</p>';
		echo '<h3>' . esc_html__('Issue', 'wsh-ticketing') . '</h3>';
		echo '<div style="padding:12px 16px;border:1px solid #dcdcde;border-radius:8px;background:#fff;">' . wp_kses_post(wpautop($post->post_content)) . '</div>';
		$environment = (string) get_post_meta($post->ID, 'wsh_ticket_environment', true);
		if ($environment !== '') {
			echo '<h3>' . esc_html__('Environment', 'wsh-ticketing') . '</h3>';
			echo '<pre style="white-space:pre-wrap;">' . esc_html($environment) . '</pre>';
		}

		if ($replies) {
			echo '<h3>' . esc_html__('Replies', 'wsh-ticketing') . '</h3>';
			foreach ($replies as $reply) {
				echo '<p><strong>' . esc_html($reply->comment_author) . '</strong> · ' . esc_html($reply->comment_date) . '</p>';
				echo '<div style="margin:0 0 16px;padding:12px 16px;border-radius:8px;background:#f6f7f7;">' . wp_kses_post(wpautop($reply->comment_content)) . '</div>';
			}
		}

		echo '<p><label for="wsh_ticket_status"><strong>' . esc_html__('Status', 'wsh-ticketing') . '</strong></label><br>';
		echo '<select id="wsh_ticket_status" name="wsh_ticket_status">';
		foreach ($statuses as $key => $label) {
			echo '<option value="' . esc_attr($key) . '" ' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select></p>';
		echo '<p><strong>' . esc_html__('Reply to the customer', 'wsh-ticketing') . '</strong></p>';
		wp_editor('', 'wsh_ticket_reply', array(
			'textarea_name' => 'wsh_ticket_reply',
			'textarea_rows' => 10,
			'media_buttons' => false,
			'teeny' => false,
			'quicktags' => true,
		));
		echo '<p class="description">' . esc_html__('Saving a reply emails it to the customer and marks the ticket answered.', 'wsh-ticketing') . '</p>';
	}

	public static function save_ticket($post_id, $post)
	{
		if (! isset($_POST['wsh_ticket_admin_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wsh_ticket_admin_nonce'])), 'wsh_ticket_admin')) {
			return;
		}
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		$statuses = self::statuses();
		$status = sanitize_text_field(wp_unslash($_POST['wsh_ticket_status'] ?? 'wsh-open'));
		if (! isset($statuses[$status])) {
			$status = 'wsh-open';
		}

		$reply = wp_kses_post(wp_unslash($_POST['wsh_ticket_reply'] ?? ''));
		if (trim(wp_strip_all_tags($reply)) !== '') {
			$user = wp_get_current_user();
			wp_insert_comment(array(
				'comment_post_ID' => $post_id,
				'comment_content' => $reply,
				'comment_type' => 'wsh_reply',
				'comment_approved' => 1,
				'user_id' => $user->ID,
				'comment_author' => $user->display_name,
				'comment_author_email' => $user->user_email,
			));

			$email = (string) get_post_meta($post_id, 'wsh_ticket_email', true);
			$name = (string) get_post_meta($post_id, 'wsh_ticket_name', true);
			if (is_email($email)) {
				self::email(
					$email,
					sprintf(__('Reply to your support request (#%d)', 'wsh-ticketing'), $post_id),
					__('Support reply', 'wsh-ticketing'),
					'<p style="margin:0 0 12px;font-size:16px;line-height:1.5;">' . esc_html(sprintf(__('Hi %s,', 'wsh-ticketing'), $name)) . '</p>'
					. '<p style="margin:0 0 12px;font-size:16px;line-height:1.5;">' . esc_html(sprintf(__('Reply to “%s”:', 'wsh-ticketing'), $post->post_title)) . '</p>'
					. '<div style="font-size:16px;line-height:1.5;">' . $reply . '</div>'
				);
			}

			if ($status === 'wsh-open') {
				$status = 'wsh-answered';
			}
		}

		if (get_post_status($post_id) !== $status) {
			remove_action('save_post_wsh_ticket', array(__CLASS__, 'save_ticket'), 10);
			wp_update_post(array(
				'ID' => $post_id,
				'post_status' => $status,
			));
			add_action('save_post_wsh_ticket', array(__CLASS__, 'save_ticket'), 10, 2);
		}
	}

	private static function email($to, $subject, $heading, $body_html)
	{
		$headers = array('Content-Type: text/html; charset=UTF-8');
		$message = function_exists('cubestheme_brand_email_html')
			? cubestheme_brand_email_html($heading, $body_html)
			: $body_html;
		wp_mail($to, $subject, $message, $headers);
	}

	private static function redirect_with($key, $value)
	{
		$target = wp_get_referer();
		if (! $target) {
			$target = home_url('/get-support/');
		}
		wp_safe_redirect(add_query_arg($key, rawurlencode($value), remove_query_arg(array('ticket', 'support_error'), $target)));
		exit;
	}
}
