<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * SEOPress PRO Bot section.
 *
 * @package SEOPress PRO
 * @subpackage Settings
 */

defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

// Bot SECTION.
add_settings_section(
	'seopress_setting_section_audit', // ID.
	'',
	// __("Audit","wp-seopress-pro"), // Title.
	'seopress_print_section_info_audit', // Callback.
	'seopress-settings-admin-audit' // Page.
);

add_settings_section(
	'seopress_setting_section_bot', // ID.
	'',
	// __("Bot","wp-seopress-pro"), // Title.
	'seopress_print_section_info_bot', // Callback.
	'seopress-settings-admin-bot' // Page.
);

// Site audit settings.
add_settings_section(
	'seopress_setting_section_bot_site_audit_settings', // ID.
	'',
	// __("Site audit settings","wp-seopress"), // Title.
	'seopress_print_section_info_bot_site_audit', // Callback.
	'seopress-settings-admin-bot-settings' // Page.
);

// Broken links settings.
add_settings_section(
	'seopress_print_section_info_bot_settings_broken_links', // ID.
	'',
	// __("Broken links settings","wp-seopress-pro"), // Title.
	'seopress_print_section_info_bot_settings', // Callback.
	'seopress-settings-admin-bot-settings' // Page.
);

add_settings_field(
	'seopress_bot_scan_settings_email', // ID.
	__( 'Recipients', 'wp-seopress-pro' ), // Title.
	'seopress_bot_scan_settings_email_callback', // Callback.
	'seopress-settings-admin-bot-settings', // Page.
	'seopress_setting_section_bot_site_audit_settings' // Section.
);

add_settings_field(
	'seopress_bot_scan_settings_audit_cpt', // ID.
	__( 'Post types to scan', 'wp-seopress-pro' ), // Title.
	'seopress_bot_scan_settings_audit_cpt_callback', // Callback.
	'seopress-settings-admin-bot-settings', // Page.
	'seopress_setting_section_bot_site_audit_settings' // Section.
);

add_settings_field(
	'seopress_bot_scan_settings_audit_noindex', // ID.
	__( 'Scan noindex content', 'wp-seopress-pro' ), // Title.
	'seopress_bot_scan_settings_audit_noindex_callback', // Callback.
	'seopress-settings-admin-bot-settings', // Page.
	'seopress_setting_section_bot_site_audit_settings' // Section.
);

add_settings_field(
	'seopress_bot_scan_settings_audit_batch_size', // ID.
	__( 'Batch size', 'wp-seopress-pro' ), // Title.
	'seopress_bot_scan_settings_audit_batch_size_callback', // Callback.
	'seopress-settings-admin-bot-settings', // Page.
	'seopress_setting_section_bot_site_audit_settings' // Section.
);

add_settings_field(
	'seopress_bot_scan_settings_post_types', // ID.
	__( 'Post types to scan', 'wp-seopress-pro' ), // Title.
	'seopress_bot_scan_settings_post_types_callback', // Callback.
	'seopress-settings-admin-bot-settings', // Page.
	'seopress_print_section_info_bot_settings_broken_links' // Section.
);

add_settings_field(
	'seopress_bot_scan_settings_where', // ID.
	__( 'Find links in', 'wp-seopress-pro' ), // Title.
	'seopress_bot_scan_settings_where_callback', // Callback.
	'seopress-settings-admin-bot-settings', // Page.
	'seopress_print_section_info_bot_settings_broken_links' // Section.
);

add_settings_field(
	'seopress_bot_scan_settings_number', // ID.
	__( 'Number of posts / pages / post types to scan', 'wp-seopress-pro' ), // Title.
	'seopress_bot_scan_settings_number_callback', // Callback.
	'seopress-settings-admin-bot-settings', // Page.
	'seopress_print_section_info_bot_settings_broken_links' // Section.
);

add_settings_field(
	'seopress_bot_scan_settings_type', // ID.
	__( 'Scan link type (slow down the bot)', 'wp-seopress-pro' ), // Title.
	'seopress_bot_scan_settings_type_callback', // Callback.
	'seopress-settings-admin-bot-settings', // Page.
	'seopress_print_section_info_bot_settings_broken_links' // Section.
);

add_settings_field(
	'seopress_bot_scan_settings_404', // ID.
	__( 'Scan 404 only', 'wp-seopress-pro' ), // Title.
	'seopress_bot_scan_settings_404_callback', // Callback.
	'seopress-settings-admin-bot-settings', // Page.
	'seopress_print_section_info_bot_settings_broken_links' // Section.
);

add_settings_field(
	'seopress_bot_scan_settings_timeout', // ID.
	__( 'Request Timeout (default 5 sec)', 'wp-seopress-pro' ), // Title.
	'seopress_bot_scan_settings_timeout_callback', // Callback.
	'seopress-settings-admin-bot-settings', // Page.
	'seopress_print_section_info_bot_settings_broken_links' // Section.
);

add_settings_field(
	'seopress_bot_scan_settings_cleaning', // ID.
	__( 'Clean broken links list when requesting a new scan', 'wp-seopress-pro' ), // Title.
	'seopress_bot_scan_settings_cleaning_callback', // Callback.
	'seopress-settings-admin-bot-settings', // Page.
	'seopress_print_section_info_bot_settings_broken_links' // Section.
);
