<?php
if (! defined('ABSPATH')) {
	exit;
}

function wsh_aine_get_option(string $key, $default = '')
{
	$options = get_option('wsh_aine_settings', array());
	return isset($options[$key]) ? $options[$key] : $default;
}

function wsh_aine_is_pro_active(): bool
{
	return class_exists('WSH_AINE_License') && WSH_AINE_License::is_active();
}

function wsh_aine_sanitize_editor_content(string $content): string
{
	$allowed = wp_kses_allowed_html('post');

	$allowed['iframe'] = array(
		'src'             => true,
		'width'           => true,
		'height'          => true,
		'frameborder'     => true,
		'allow'           => true,
		'allowfullscreen' => true,
		'loading'         => true,
		'referrerpolicy'  => true,
		'title'           => true,
		'class'           => true,
		'id'              => true,
		'style'           => true,
	);

	return wp_kses($content, $allowed);
}

function wsh_aine_parse_comment_line(string $line): array
{
	$line = trim($line);

	if ('' === $line) {
		return array(
			'author' => '',
			'text'   => '',
		);
	}

	if (preg_match('/^\s*@?([^|]+?)\s*\|\s*(.+)$/u', $line, $matches)) {
		$author = trim($matches[1]);
		$text   = trim($matches[2]);

		if ('' !== $author && '' !== $text) {
			return array(
				'author' => '@' . ltrim($author, '@'),
				'text'   => $text,
			);
		}
	}

	return array(
		'author' => '@Komentator',
		'text'   => $line,
	);
}

function wsh_aine_format_comment_line(string $author, string $text): string
{
	$author = trim($author);
	$text   = trim($text);

	if ('' === $author) {
		$author = '@Komentator';
	}

	if ('' !== $author && '@' !== $author[0]) {
		$author = '@' . $author;
	}

	return $author . ' | ' . $text;
}
