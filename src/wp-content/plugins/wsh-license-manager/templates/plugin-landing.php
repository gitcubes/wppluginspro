<?php
if (! defined('ABSPATH')) {
	exit;
}

$plugin_id = (int) get_post_meta(get_queried_object_id(), '_wsh_plugin_id', true);
$plugin = get_post($plugin_id);
$title = $plugin instanceof WP_Post ? get_the_title($plugin) : get_the_title();
$summary = $plugin instanceof WP_Post ? (string) get_post_meta($plugin->ID, 'wsh_plugin_summary', true) : '';
$version = $plugin instanceof WP_Post ? (string) get_post_meta($plugin->ID, 'wsh_plugin_version', true) : '';

if (function_exists('themeVersion')) {
	wp_enqueue_style('static-page', get_template_directory_uri() . '/frontend/css/static-page.css', array(), themeVersion());
}

get_header(null, array('force_scrolled_header' => true));
?>
<section class="static-page">
	<div class="container">
		<h1 class="page-title"><?php echo esc_html($title); ?></h1>
		<div class="content">
			<?php if ($summary !== '') : ?>
				<p><?php echo esc_html($summary); ?></p>
			<?php endif; ?>
			<?php if ($version !== '') : ?>
				<p><?php echo esc_html(sprintf('Version %s', $version)); ?></p>
			<?php endif; ?>
			<?php if (is_user_logged_in() && $plugin instanceof WP_Post && WSH_Plugin_Storage::user_can_download(get_current_user_id(), $plugin->ID)) : ?>
				<p><a class="btn btn-primary" href="<?php echo esc_url(function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('plugin-files') : home_url('/my-account/')); ?>"><?php esc_html_e('Download in your account', 'wsh-license-manager'); ?></a></p>
			<?php else : ?>
				<p><a class="btn btn-primary" href="<?php echo esc_url(home_url('/pricing/')); ?>"><?php esc_html_e('See pricing', 'wsh-license-manager'); ?></a></p>
			<?php endif; ?>
		</div>
	</div>
</section>
<?php
get_footer();
