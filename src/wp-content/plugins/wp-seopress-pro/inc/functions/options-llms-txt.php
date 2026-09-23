<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * SEOPress PRO Options LLMS.txt.
 *
 * @package SEOPress PRO
 * @subpackage Options
 */

defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

/**
 * Get post types to include in llms.txt output.
 *
 * Returns all public post types except attachments and SEOPress internal types.
 * Filterable via 'seopress_llms_post_types'.
 *
 * @return array
 */
function seopress_get_llms_post_types() {
	$excluded = array( 'attachment', 'seopress_404', 'seopress_schemas', 'seopress_bot' );
	$types    = get_post_types( array( 'public' => true ), 'names' );
	$types    = array_diff( array_values( $types ), $excluded );

	return apply_filters( 'seopress_llms_post_types', $types );
}

/**
 * Generate default LLMS.txt content.
 *
 * @return string
 */
function seopress_generate_default_llms_txt_content() {
	$content  = "# " . get_bloginfo( 'name' ) . "\n\n";
	$content .= "> " . get_bloginfo( 'description' ) . "\n";
	$content .= "> Last updated: " . gmdate( 'Y-m-d' ) . "\n\n";
	$content .= "## Search\n\n";
	$content .= "- Search URL: `" . add_query_arg( 's', '{query}', get_home_url() ) . "`\n\n";
	$content .= "## Recent Content\n\n";

	// Get public post types for llms.txt (excluding 'attachment' and SEOPress internal types).
	$post_types = seopress_get_llms_post_types();

	// Get recent posts.
	$recent_posts = get_posts(
		array(
			'numberposts'  => 5,
			'post_status'  => 'publish',
			'post_type'    => $post_types,
			'orderby'      => 'date',
			'has_password' => false,
			'meta_query'   => array(
				'relation' => 'OR',
				array(
					'key'     => '_seopress_robots_index',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_seopress_robots_index',
					'value'   => 'yes',
					'compare' => '!=',
				),
			),
		)
	);

	foreach ( $recent_posts as $post ) {
		$post_url = get_permalink( $post );
		$excerpt  = wp_strip_all_tags( get_the_excerpt( $post ) );
		if ( empty( $excerpt ) ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 20 );
		}
		$content .= "- [{$post->post_title}]({$post_url})";
		if ( ! empty( $excerpt ) ) {
			$content .= ": {$excerpt}";
		}
		$content .= "\n";
	}

	$content .= "\n## Complete Sitemap\n\n";
	$content .= "For a comprehensive list of all URLs, see: " . get_home_url() . "/sitemaps.xml\n\n";
	$content .= "---\n\n";
	$content .= "*This file is dynamically generated and highlights our most important content.*\n";

	return apply_filters( 'seopress_default_llms_txt_content', $content );
}

/**
 * Replace placeholders in llms.txt content.
 *
 * @param string $content The content with placeholders.
 * @return string Content with placeholders replaced.
 */
function seopress_replace_llms_placeholders( $content ) {
	// Simple placeholders.
	$placeholders = array(
		'{{site_name}}'        => get_bloginfo( 'name' ),
		'{{site_description}}' => get_bloginfo( 'description' ),
		'{{site_url}}'         => get_home_url(),
		'{{current_date}}'     => gmdate( 'Y-m-d' ),
		'{{search_url}}'       => add_query_arg( 's', '{query}', get_home_url() ),
		'{{sitemap_url}}'      => get_home_url() . '/sitemaps.xml',
	);

	// Replace simple placeholders.
	$content = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $content );

	// Handle latest posts placeholder, with optional post type filter.
	if ( preg_match_all( '/\{\{latest_posts:(\d+)(?:,\(([^)]+)\))?\}\}/', $content, $matches ) ) {
		foreach ( $matches[0] as $index => $match ) {
			$count         = (int) $matches[1][ $index ];
			$posts_content = '';

			// Default to 'post' if no post types specified.
			$post_type = 'post';
			if ( ! empty( $matches[2][ $index ] ) ) {
				$post_type = array_map( 'trim', explode( ',', $matches[2][ $index ] ) );
			}

			$recent_posts = get_posts(
				array(
					'numberposts'  => $count,
					'post_status'  => 'publish',
					'post_type'    => $post_type,
					'orderby'      => 'date',
					'has_password' => false,
					'meta_query'   => array(
						'relation' => 'OR',
						array(
							'key'     => '_seopress_robots_index',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => '_seopress_robots_index',
							'value'   => 'yes',
							'compare' => '!=',
						),
					),
				)
			);

			foreach ( $recent_posts as $post ) {
				$post_url = get_permalink( $post );
				$excerpt  = wp_strip_all_tags( get_the_excerpt( $post ) );
				if ( empty( $excerpt ) ) {
					$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 20 );
				}
				$posts_content .= "- [{$post->post_title}]({$post_url})";
				if ( ! empty( $excerpt ) ) {
					$posts_content .= ": {$excerpt}";
				}
				$posts_content .= "\n";
			}

			$content = str_replace( $match, $posts_content, $content );
		}
	}

	// Handle featured products: {{featured_products:X}} and latest products: {{latest_products:X}}.
	if ( class_exists( 'WooCommerce' ) ) {
		// Featured products.
		if ( preg_match_all( '/\{\{featured_products:(\d+)\}\}/', $content, $matches ) ) {
			foreach ( $matches[0] as $index => $match ) {
				$count            = (int) $matches[1][ $index ];
				$products_content = '';

				$product_args = array(
					'post_type'      => 'product',
					'posts_per_page' => $count,
					'post_status'    => 'publish',
					'has_password'   => false,
					'tax_query'      => array(
						'relation' => 'AND',
						array(
							'taxonomy' => 'product_visibility',
							'field'    => 'name',
							'terms'    => 'featured',
						),
						array(
							'taxonomy' => 'product_visibility',
							'field'    => 'name',
							'terms'    => array( 'exclude-from-catalog', 'exclude-from-search' ),
							'operator' => 'NOT IN',
						),
					),
					'meta_query'     => array(
						'relation' => 'OR',
						array(
							'key'     => '_seopress_robots_index',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => '_seopress_robots_index',
							'value'   => 'yes',
							'compare' => '!=',
						),
					),
				);

				$products = get_posts( $product_args );

				// Fallback to latest if no featured products.
				if ( empty( $products ) ) {
					$products = get_posts(
						array(
							'numberposts'  => $count,
							'post_status'  => 'publish',
							'post_type'    => 'product',
							'has_password' => false,
							'tax_query'    => array(
								array(
									'taxonomy' => 'product_visibility',
									'field'    => 'name',
									'terms'    => array( 'exclude-from-catalog', 'exclude-from-search' ),
									'operator' => 'NOT IN',
								),
							),
							'meta_query'   => array(
								'relation' => 'OR',
								array(
									'key'     => '_seopress_robots_index',
									'compare' => 'NOT EXISTS',
								),
								array(
									'key'     => '_seopress_robots_index',
									'value'   => 'yes',
									'compare' => '!=',
								),
							),
						)
					);
				}

				foreach ( $products as $product ) {
					$product_url = get_permalink( $product );
					$product_obj = wc_get_product( $product->ID );
					$excerpt     = $product_obj ? wp_strip_all_tags( $product_obj->get_short_description() ) : '';

					if ( empty( $excerpt ) ) {
						$excerpt = wp_trim_words( wp_strip_all_tags( $product->post_content ), 15 );
					}

					$products_content .= "- [{$product->post_title}]({$product_url})";
					if ( ! empty( $excerpt ) ) {
						$products_content .= ": {$excerpt}";
					}
					$products_content .= "\n";
				}

				$content = str_replace( $match, $products_content, $content );
			}
		}

		// Latest products.
		if ( preg_match_all( '/\{\{latest_products:(\d+)\}\}/', $content, $matches ) ) {
			foreach ( $matches[0] as $index => $match ) {
				$count            = (int) $matches[1][ $index ];
				$products_content = '';

				$products = get_posts(
					array(
						'numberposts'  => $count,
						'post_status'  => 'publish',
						'post_type'    => 'product',
						'orderby'      => 'date',
						'has_password' => false,
						'tax_query'    => array(
							array(
								'taxonomy' => 'product_visibility',
								'field'    => 'name',
								'terms'    => array( 'exclude-from-catalog', 'exclude-from-search' ),
								'operator' => 'NOT IN',
							),
						),
						'meta_query'   => array(
							'relation' => 'OR',
							array(
								'key'     => '_seopress_robots_index',
								'compare' => 'NOT EXISTS',
							),
							array(
								'key'     => '_seopress_robots_index',
								'value'   => 'yes',
								'compare' => '!=',
							),
						),
					)
				);

				foreach ( $products as $product ) {
					$product_url = get_permalink( $product );
					$product_obj = wc_get_product( $product->ID );
					$excerpt     = $product_obj ? wp_strip_all_tags( $product_obj->get_short_description() ) : '';

					if ( empty( $excerpt ) ) {
						$excerpt = wp_trim_words( wp_strip_all_tags( $product->post_content ), 15 );
					}

					$products_content .= "- [{$product->post_title}]({$product_url})";
					if ( ! empty( $excerpt ) ) {
						$products_content .= ": {$excerpt}";
					}
					$products_content .= "\n";
				}

				$content = str_replace( $match, $products_content, $content );
			}
		}
	}

	return apply_filters( 'seopress_llms_placeholders_replaced', $content );
}

/**
 * Serve virtual llms.txt file.
 *
 * Uses 'do_parse_request' filter to intercept the request early,
 * similar to how WordPress handles robots.txt internally.
 *
 * @param bool         $do_parse Whether to parse the request.
 * @param WP           $wp       Current WordPress environment instance.
 * @param array|string $extra_query_vars Extra passed query variables.
 *
 * @return bool
 */
function seopress_serve_llms_txt( $do_parse, $wp, $extra_query_vars ) {
	// Get the home URL path to handle subdirectory installations.
	$home_path = wp_parse_url( home_url(), PHP_URL_PATH );
	$home_path = $home_path ? trim( $home_path, '/' ) : '';

	// Build the expected path for llms.txt.
	$llms_path = $home_path ? $home_path . '/llms.txt' : 'llms.txt';

	// Get the request path.
	$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$request_path = trim( wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );

	// Check if the request is for llms.txt.
	if ( $request_path !== $llms_path ) {
		return $do_parse;
	}

	// Get custom content from settings.
	$seopress_llms = seopress_pro_get_service( 'OptionPro' )->getLlmsTxtFile();

	// If custom content is empty, use default.
	if ( empty( $seopress_llms ) ) {
		$seopress_llms = seopress_generate_default_llms_txt_content();
	} else {
		// Replace placeholders in custom content.
		$seopress_llms = seopress_replace_llms_placeholders( $seopress_llms );
	}

	// Apply filter to allow customization.
	$seopress_llms = apply_filters( 'seopress_llms_txt_file', $seopress_llms );

	// Set the content type header.
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );

	// Output the content.
	echo $seopress_llms;

	// Exit to prevent WordPress from continuing.
	exit;
}
add_filter( 'do_parse_request', 'seopress_serve_llms_txt', 0, 3 );
