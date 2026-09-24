<?php
$ticket = class_exists('WSH_Tickets') ? WSH_Tickets::ticket_from_request() : null;
if (! $ticket instanceof WP_Post) {
	return;
}

$notice = sanitize_text_field(wp_unslash($_GET['notice'] ?? ''));
$replies = get_comments(array(
	'post_id' => $ticket->ID,
	'type' => 'wsh_reply',
	'status' => 'approve',
	'order' => 'ASC',
));
$product = (string) get_post_meta($ticket->ID, 'wsh_ticket_product', true);
?>
<section class="support-request">
	<div class="container">
		<div class="support-layout">
			<div class="support-request-panel box">
				<div class="content">
					<div class="support-panel-intro">
						<h2><?php echo esc_html(sprintf(__('Ticket #%d', 'cubestheme'), $ticket->ID)); ?></h2>
						<p><?php echo esc_html($ticket->post_title); ?><?php echo $product !== '' ? ' · ' . esc_html($product) : ''; ?></p>
					</div>

					<?php if ($notice === 'sent') : ?>
						<p class="form-success-message"><?php esc_html_e('Your reply was added to the ticket.', 'cubestheme'); ?></p>
					<?php elseif ($notice === 'required') : ?>
						<p class="form-error-message"><?php esc_html_e('Write a reply before sending.', 'cubestheme'); ?></p>
					<?php elseif ($notice === 'expired') : ?>
						<p class="form-error-message"><?php esc_html_e('The form expired. Send the reply again.', 'cubestheme'); ?></p>
					<?php endif; ?>

					<div class="support-thread">
						<article class="support-thread-item">
							<p><strong><?php echo esc_html(get_post_meta($ticket->ID, 'wsh_ticket_name', true)); ?></strong></p>
							<div><?php echo wp_kses_post(wpautop($ticket->post_content)); ?></div>
						</article>
						<?php foreach ($replies as $reply) : ?>
							<article class="support-thread-item">
								<p><strong><?php echo esc_html($reply->comment_author); ?></strong></p>
								<div><?php echo wp_kses_post(wpautop($reply->comment_content)); ?></div>
							</article>
						<?php endforeach; ?>
					</div>

					<form class="support-request-form" method="post" action="<?php echo esc_url(add_query_arg(array('view' => $ticket->ID, 'key' => sanitize_text_field(wp_unslash($_GET['key'] ?? ''))))); ?>">
						<?php wp_nonce_field('wsh_ticket_customer_reply', 'wsh_ticket_reply_nonce'); ?>
						<input type="hidden" name="wsh_ticket_customer_reply" value="1">
						<input type="hidden" name="view" value="<?php echo esc_attr($ticket->ID); ?>">
						<input type="hidden" name="key" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['key'] ?? ''))); ?>">
						<div class="support-field">
							<div class="support-field-head">
								<label for="wsh-customer-message"><?php esc_html_e('Your reply', 'cubestheme'); ?></label>
							</div>
							<textarea id="wsh-customer-message" name="wsh_customer_message" rows="8" required></textarea>
						</div>
						<button type="submit" class="btn btn-primary"><?php esc_html_e('Send reply', 'cubestheme'); ?></button>
					</form>
				</div>
			</div>
		</div>
	</div>
</section>
