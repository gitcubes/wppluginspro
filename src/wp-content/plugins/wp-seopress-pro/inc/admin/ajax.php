<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * AJAX.
 *
 * @package AJAX
 */
defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

require_once __DIR__ . '/export/csv.php';

/**
 * SEOPress Bot.
 *
 * @return void
 */
function seopress_request_bot() {
	check_ajax_referer( 'seopress_request_bot_nonce' );

	if ( current_user_can( seopress_capability( 'manage_options', 'bot' ) ) && is_admin() ) {
		// Initialize data.
		$data = array();

		// Cleaning seopress_bot post type.
		if ( '1' === seopress_pro_get_service( 'OptionBot' )->getBotScanSettingsCleaning() && isset( $_POST['offset'] ) && 0 == $_POST['offset'] ) {
			global $wpdb;

			// Delete all posts by post type.
			$sql = 'DELETE `posts`, `pm`
				FROM `' . $wpdb->prefix . 'posts` AS `posts`
				LEFT JOIN `' . $wpdb->prefix . 'postmeta` AS `pm` ON `pm`.`post_id` = `posts`.`ID`
				WHERE `posts`.`post_type` = \'seopress_bot\'';
			$wpdb->query( $sql );
		}

		if ( isset( $_POST['offset'] ) ) {
			$offset = absint( $_POST['offset'] );
		}

		if ( ! empty( seopress_pro_get_service( 'OptionBot' )->getBotScanSettingsPostTypes() ) ) {
			$seopress_bot_post_types_cpt_array = array();
			foreach ( seopress_pro_get_service( 'OptionBot' )->getBotScanSettingsPostTypes() as $cpt_key => $cpt_value ) {
				foreach ( $cpt_value as $_cpt_key => $_cpt_value ) {
					if ( '1' == $_cpt_value ) {
						array_push( $seopress_bot_post_types_cpt_array, $cpt_key );
					}
				}
			}

			if ( '' != seopress_pro_get_service( 'OptionBot' )->getBotScanSettingsNumber() && seopress_pro_get_service( 'OptionBot' )->getBotScanSettingsNumber() >= 10 ) {
				$limit = seopress_pro_get_service( 'OptionBot' )->getBotScanSettingsNumber();
			} else {
				$limit = 100;
			}

			global $post;

			if ( $offset > $limit ) {
				wp_reset_postdata();
				// Log date.
				update_option( 'seopress_bot_log', current_time( 'Y-m-d H:i' ), false );

				$offset = 'done';
			} else {
				$args      = array(
					'posts_per_page' => 1,
					'offset'         => $offset,
					'cache_results'  => false,
					'order'          => 'DESC',
					'orderby'        => 'date',
					'post_type'      => $seopress_bot_post_types_cpt_array,
					'post_status'    => 'publish',
					'fields'         => 'ids',
				);
				$args      = apply_filters( 'seopress_bot_query', $args, $offset, $seopress_bot_post_types_cpt_array );
				$bot_query = get_posts( $args );

				if ( $bot_query ) {
					// DOM.
					$dom                     = new DOMDocument();
					$internalErrors          = libxml_use_internal_errors( true ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
					$dom->preserveWhiteSpace = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

					// Get source code.
					if ( '' != seopress_pro_get_service( 'OptionBot' )->getBotScanSettingsTimeout() ) {
						$timeout = seopress_pro_get_service( 'OptionBot' )->getBotScanSettingsTimeout();
					} else {
						$timeout = 5;
					}

					// Get cookies.
					if ( isset( $_COOKIE ) ) {
						$cookies = array();

						foreach ( $_COOKIE as $name => $value ) {
							if ( 'PHPSESSID' !== $name ) {
								$cookies[] = new WP_Http_Cookie(
									array(
										'name'  => $name,
										'value' => $value,
									)
								);
							}
						}
					}

					$args = array(
						'blocking'    => true,
						'timeout'     => $timeout,
						'sslverify'   => false,
						'compress'    => true,
						'redirection' => 4,
					);

					if ( isset( $cookies ) && ! empty( $cookies ) ) {
						$args['cookies'] = $cookies;
					}

					$args = apply_filters( 'seopress_bot_query_dom_args', $args );

					foreach ( $bot_query as $post ) {
						if ( '' === seopress_pro_get_service( 'OptionBot' )->getBotScanSettingsWhere() || 'post_content' === seopress_pro_get_service( 'OptionBot' )->getBotScanSettingsWhere() ) {// post content
							// This code will not run shortcodes.
							$response = get_post_field( 'post_content', $post );
						} else { // Body page.
							$response = wp_remote_get( get_permalink( $post ), $args );

							// Check for error.
							if ( is_wp_error( $response ) || '404' === wp_remote_retrieve_response_code( $response ) ) {
								$data['post_title'] = __( 'Unable to request page: ', 'wp-seopress-pro' ) . get_the_title( $post );
							} else {
								$response = wp_remote_retrieve_body( $response );
							}
						}

						if ( ! is_wp_error( $response ) || '404' !== wp_remote_retrieve_response_code( $response ) ) {
							if ( get_the_title( $post ) ) {
								$data['post_title'] = get_the_title( $post ) . ' (' . get_permalink( $post ) . ')';

								if ( is_string( $response ) ) {
									if ( $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $response ) ) {
										$xpath = new DOMXPath( $dom );

										// Links.
										$links = $xpath->query( '//a' );

										if ( ! empty( $links ) ) {
											foreach ( $links as $key => $link ) {
												$links2 = array();
												$links3 = array();

												$href = $link->getAttribute( 'href' );
												$text = esc_attr( $link->textContent ); //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

												// Remove anchors.
												if ( '#' != $href ) {
													$links2[ $text ] = $href;
												}

												// Remove duplicates.
												$links2 = array_unique( $links2 );

												$bot_status_code = ''; // Maybe use "domain not found" instead of empty string.

												foreach ( $links2 as $_key => $_value ) {
													$args = array(
														'timeout' => $timeout,
														'blocking' => true,
														'sslverify' => false,
														'compress' => true,
													);

													$response = wp_remote_get( $_value, $args );

													$bot_status_code = wp_remote_retrieve_response_code( $response );

													if ( ! $bot_status_code ) {
														$bot_status_code = __( 'domain not found', 'wp-seopress-pro' );
													}

													if ( '1' === seopress_pro_get_service( 'OptionBot' )->getBotScanSettingsType() ) {
														$bot_status_type = wp_remote_retrieve_header( $response, 'content-type' );
													}

													if ( '1' === seopress_pro_get_service( 'OptionBot' )->getBotScanSettings404() ) {
														if ( '404' == $bot_status_code || strpos( wp_json_encode( $response ), 'cURL error 6' ) ) {
															$links3[] = $_value;
														}
													} else {
														$links3[] = $_value;
													}
												}

												foreach ( $links3 as $_key => $_value ) {
													$check_page_id = seopress_pro_get_service( 'Redirection' )->getPageByTitle( $_value, '', 'seopress_bot' );

													if ( is_bool( $check_page_id ) ) {
														wp_insert_post(
															array(
																'post_title' => $_value,
																'post_type' => 'seopress_bot',
																'post_status' => 'publish',
																'meta_input' => array(
																	'seopress_bot_type' => $bot_status_type,
																	'seopress_bot_status' => $bot_status_code,
																	'seopress_bot_source_url' => get_permalink( $post ),
																	'seopress_bot_source_id' => $post,
																	'seopress_bot_cpt' => get_post_type( $post ),
																	'seopress_bot_source_title' => get_the_title( $post ),
																	'seopress_bot_a_title' => $text,
																),
															)
														);
													} elseif ( ! is_bool( $check_page_id ) && $check_page_id->post_title == $_value ) {
														$seopress_bot_count = get_post_meta( $check_page_id->ID, 'seopress_bot_count', true );
														update_post_meta( $check_page_id->ID, 'seopress_bot_count', ++$seopress_bot_count );
													}

													$data['link'][] = $_value;
												}
											}
										}
									}
								}
							}
						}
					}//End foreach
					libxml_use_internal_errors( $internalErrors ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
					++$offset;
				} else {
					wp_reset_postdata();
					// Log date.
					update_option( 'seopress_bot_log', current_time( 'Y-m-d H:i' ), false );

					$offset = 'done';
				}
			}
		}
		$data['offset'] = $offset;

		// Return.
		wp_send_json_success( $data );
	}
}
add_action( 'wp_ajax_seopress_request_bot', 'seopress_request_bot' );

/**
 * Admin Columns PRO.
 * Requires ACP version 6.0 or higher.
 */
if ( is_plugin_active( 'admin-columns-pro/admin-columns-pro.php' ) && defined( 'ACP_VERSION' ) && version_compare( ACP_VERSION, '6.0', '>=' ) ) {
	add_action( 'ac/column_groups', 'ac_register_seopress_column_group' );
	/**
	 * Register SEOPress column group.
	 *
	 * @param AC\Groups $groups The groups.
	 * @return void
	 */
	function ac_register_seopress_column_group( AC\Groups $groups ) {
		$groups->register_group( 'seopress', 'SEOPress' );
	}

	add_action( 'ac/column_types', 'ac_register_seopress_columns' );
	/**
	 * Register SEOPress columns.
	 *
	 * @param AC\ListScreen $list_screen The list screen.
	 * @return void
	 */
	function ac_register_seopress_columns( AC\ListScreen $list_screen ) {
		if ( $list_screen instanceof ACP\ListScreen\Post ) {
			require_once plugin_dir_path( __FILE__ ) . 'thirds/admin-columns/acp-column-sp_title.php';
			require_once plugin_dir_path( __FILE__ ) . 'thirds/admin-columns/acp-column-sp_desc.php';
			require_once plugin_dir_path( __FILE__ ) . 'thirds/admin-columns/acp-column-sp_noindex.php';
			require_once plugin_dir_path( __FILE__ ) . 'thirds/admin-columns/acp-column-sp_nofollow.php';
			require_once plugin_dir_path( __FILE__ ) . 'thirds/admin-columns/acp-column-sp_target_kw.php';
			require_once plugin_dir_path( __FILE__ ) . 'thirds/admin-columns/acp-column-sp_redirect.php';
			require_once plugin_dir_path( __FILE__ ) . 'thirds/admin-columns/acp-column-sp_redirect_url.php';
			require_once plugin_dir_path( __FILE__ ) . 'thirds/admin-columns/acp-column-sp_canonical.php';
			require_once plugin_dir_path( __FILE__ ) . 'thirds/admin-columns/acp-column-sp_gsc_positions.php';
			require_once plugin_dir_path( __FILE__ ) . 'thirds/admin-columns/acp-column-sp_gsc_clicks.php';
			require_once plugin_dir_path( __FILE__ ) . 'thirds/admin-columns/acp-column-sp_gsc_impressions.php';
			require_once plugin_dir_path( __FILE__ ) . 'thirds/admin-columns/acp-column-sp_gsc_ctr.php';

			$list_screen->register_column_type( new ACP_Column_Sp_Title() );
			$list_screen->register_column_type( new ACP_Column_Sp_Desc() );
			$list_screen->register_column_type( new ACP_Column_Sp_Noindex() );
			$list_screen->register_column_type( new ACP_Column_Sp_Nofollow() );
			$list_screen->register_column_type( new ACP_Column_Sp_Target_Kw() );
			$list_screen->register_column_type( new ACP_Column_Sp_Redirect() );
			$list_screen->register_column_type( new ACP_Column_Sp_Redirect_Url() );
			$list_screen->register_column_type( new ACP_Column_Sp_Canonical() );
			$list_screen->register_column_type( new ACP_Column_Sp_GSC_Positions() );
			$list_screen->register_column_type( new ACP_Column_Sp_GSC_Clicks() );
			$list_screen->register_column_type( new ACP_Column_Sp_GSC_Impressions() );
			$list_screen->register_column_type( new ACP_Column_Sp_GSC_CTR() );
		}
	}
}

/**
 * LB Widget order.
 *
 * @return void
 */
function seopress_pro_lb_widget() {
	check_ajax_referer( 'seopress_pro_lb_widget_nonce' );
	if ( current_user_can( 'edit_theme_options' ) && is_admin() ) {
		if ( isset( $_POST['order'] ) && $_POST['order'] && isset( $_POST['id'] ) && $_POST['id'] ) {
			$widget_option = get_option( 'widget_seopress_pro_lb_widget' );

			$widget_option[ (int) $_POST['id'] ]['order'] = sanitize_text_field( wp_unslash( $_POST['order'] ) );

			update_option( 'widget_seopress_pro_lb_widget', $widget_option );
		}
	}

	wp_send_json_success();
}
add_action( 'wp_ajax_seopress_pro_lb_widget', 'seopress_pro_lb_widget' );

/**
 * Clear Google Page Speed cache.
 *
 * @return void
 */
function seopress_clear_page_speed_cache() {
	check_ajax_referer( 'seopress_clear_page_speed_cache_nonce' );

	if ( ! current_user_can( seopress_capability( 'manage_options', 'pagespeed' ) ) ) {
		wp_send_json_error( 'not_authorized' );
	}

	global $wpdb;

	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_seopress_results_page_speed' " );
	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_seopress_results_page_speed' " );
	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_seopress_results_page_speed_desktop' " );
	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_seopress_results_page_speed_desktop' " );

	exit();
}
add_action( 'wp_ajax_seopress_clear_page_speed_cache', 'seopress_clear_page_speed_cache' );

/**
 * Reset License.
 *
 * @return void
 */
function seopress_request_reset_license() {
	check_ajax_referer( 'seopress_request_reset_license_nonce' );

	if ( current_user_can( seopress_capability( 'manage_options', 'license' ) ) && is_admin() ) {
		delete_option( 'seopress_pro_license_status' );
		delete_option( 'seopress_pro_license_key' );
		delete_option( 'seopress_pro_license_key_error' );
		delete_option( 'seopress_pro_license_automatic_attempt' );
		delete_option( 'seopress_pro_license_home_url' );

		$data = array( 'url' => admin_url( 'admin.php?page=seopress-license' ) );
		wp_send_json_success( $data );
	}
}
add_action( 'wp_ajax_seopress_request_reset_license', 'seopress_request_reset_license' );

/**
 * Lock Google Analytics view.
 *
 * @return void
 */
function seopress_google_analytics_lock() {
	check_ajax_referer( 'seopress_google_analytics_lock_nonce' );

	if ( ! current_user_can( seopress_capability( 'manage_options', 'google_analytics' ) ) ) {
		wp_send_json_error( 'not_authorized' );
	}

	update_option( 'seopress_google_analytics_lock_option_name', '1', 'yes' );

	wp_send_json_success();
}
add_action( 'wp_ajax_seopress_google_analytics_lock', 'seopress_google_analytics_lock' );

/**
 * Save htaccess file.
 *
 * @return void
 */
function seopress_save_htaccess() {
	check_ajax_referer( 'seopress_save_htaccess_nonce' );

	if ( ! current_user_can( seopress_capability( 'manage_options', 'htaccess' ) ) && ! is_admin() ) {
		exit;
	}
	$filename = get_home_path() . '/.htaccess';

	if ( ! file_exists( get_home_path() . '/.htaccess' ) ) {
		$msg   = __( 'Impossible to open file: ', 'wp-seopress-pro' ) . $filename;
		$class = 'is-error';
	}
	$old_htaccess = file_get_contents( $filename );

	if ( isset( $_POST['htaccess_content'] ) ) {
		$current_htaccess = stripslashes( $_POST['htaccess_content'] );
	}

	if ( is_writable( $filename ) ) {
		if ( ! $handle = fopen( $filename, 'w' ) ) {
			$msg   = __( 'Impossible to open file: ', 'wp-seopress-pro' ) . $filename;
			$class = 'is-error';
		}

		if ( false === fwrite( $handle, $current_htaccess ) ) {
			$msg   = __( 'Impossible to write in file: ', 'wp-seopress-pro' ) . $filename;
			$class = 'is-error';
		}

		fclose( $handle );

		$args = array(
			'blocking'    => true,
			'redirection' => 0,
		);

		$test = wp_remote_retrieve_response_code( wp_remote_get( get_home_url(), $args ) );

		if ( is_wp_error( $test ) || 200 !== $test ) {
			$handle = fopen( $filename, 'w' );
			fwrite( $handle, $old_htaccess );
			fclose( $handle );

			$msg   = __( '.htaccess not updated due to a syntax error!', 'wp-seopress-pro' );
			$class = 'is-error';
		} else {
			$msg   = __( '.htaccess successfully updated!', 'wp-seopress-pro' );
			$class = 'is-success';
		}
	} else {
		$msg   = __( 'Your .htaccess is not writable.', 'wp-seopress-pro' );
		$class = 'is-error';
	}

	$data = array(
		'msg'   => $msg,
		'class' => $class,
	);

	wp_send_json_success( $data );
}
add_action( 'wp_ajax_seopress_save_htaccess', 'seopress_save_htaccess' );

/**
 * Inspect URL with Google Search Console API.
 *
 * @return void
 */
function seopress_inspect_url() {
	check_ajax_referer( 'seopress_inspect_url_nonce' );

	if ( ! current_user_can( 'edit_posts' ) && ! is_admin() ) {
		return;
	}

	$data = array();

	// Get post id.
	if ( isset( $_POST['post_id'] ) ) {
		$id = absint( $_POST['post_id'] );
	}

	if ( empty( $id ) ) {
		return;
	}

	$data = seopress_pro_get_service( 'InspectUrlGoogle' )->handle( $id );

	wp_send_json_success( $data );
}
add_action( 'wp_ajax_seopress_inspect_url', 'seopress_inspect_url' );

/**
 * Regenerate Video XML Sitemap.
 *
 * @return void
 */
function seopress_pro_video_xml_sitemap_regenerate() {
	check_ajax_referer( 'seopress_video_regenerate_nonce', '_ajax_nonce', true );

	if ( current_user_can( seopress_capability( 'manage_options', 'migration' ) ) && is_admin() ) {
		if ( isset( $_POST['offset'] ) && isset( $_POST['offset'] ) ) {
			$offset = absint( $_POST['offset'] );
		}

		$cpt = array( 'any' );
		if ( ! empty( seopress_get_service( 'SitemapOption' )->getPostTypesList() ) ) {
			unset( $cpt[0] );
			foreach ( seopress_get_service( 'SitemapOption' )->getPostTypesList() as $cpt_key => $cpt_value ) {
				foreach ( $cpt_value as $_cpt_key => $_cpt_value ) {
					if ( '1' == $_cpt_value ) {
						$cpt[] = $cpt_key;
					}
				}
			}

			$cpt = array_map(
				function ( $item ) {
					return "'" . esc_sql( $item ) . "'";
				},
				$cpt
			);

			$cpt_string = implode( ',', $cpt );
		}

		global $wpdb;
		$total_count_posts = (int) $wpdb->get_var( "SELECT count(*) FROM {$wpdb->posts} WHERE post_status IN ('pending', 'draft', 'publish', 'future') AND post_type IN ( $cpt_string ) " );

		$total_count_posts = apply_filters( 'seopress_video_regeneration_total_count_posts', $total_count_posts );

		$increment = 10;

		$increment = apply_filters( 'seopress_video_regeneration_increment', $increment );

		global $post;

		if ( $offset > $total_count_posts ) {
			wp_reset_postdata();
			$count_items = $total_count_posts;
			$offset      = 'done';
		} else {
			$args = array(
				'posts_per_page' => $increment,
				'post_type'      => $cpt,
				'post_status'    => array( 'pending', 'draft', 'publish', 'future' ),
				'offset'         => $offset,
			);

			$args = apply_filters( 'seopress_video_regeneration_query', $args, $increment, $cpt, $offset );

			$video_query = get_posts( $args );

			if ( $video_query ) {
				foreach ( $video_query as $post ) {
					seopress_pro_video_xml_sitemap( $post->ID, $post );
				}
			}
			$offset += $increment;
		}
		$data = array();

		$data['total'] = $total_count_posts;

		if ( $offset >= $total_count_posts ) {
			$data['count'] = $total_count_posts;
		} else {
			$data['count'] = $offset;
		}

		$data['offset'] = $offset;

		// Clear cache.
		delete_transient( '_seopress_sitemap_ids_video' );

		wp_send_json_success( $data );
		exit();
	}
}
add_action( 'wp_ajax_seopress_pro_video_xml_sitemap_regenerate', 'seopress_pro_video_xml_sitemap_regenerate' );

/**
 * Open AI - Generate SEO metadata.
 *
 * @return void
 */
function seopress_ai_generate_seo_meta() {
	check_ajax_referer( 'seopress_ai_generate_seo_meta_nonce' );

	if ( ! current_user_can( 'edit_posts' ) && ! is_admin() ) {
		return;
	}

	$data = array();

	// Get post id.
	if ( isset( $_POST['post_id'] ) ) {
		$post_id = (int) $_POST['post_id'];
	}

	if ( empty( $post_id ) ) {
		return;
	}

	if ( isset( $_POST['lang'] ) ) {
		$language = esc_html( $_POST['lang'] );
	}

	$meta = '';
	if ( isset( $_POST['meta'] ) ) {
		$meta = esc_html( $_POST['meta'] );
	}

	if ( 'alt_text' === $meta || 'image_meta' === $meta ) {
		$fields = null;
		if ( isset( $_POST['fields'] ) && ! empty( $_POST['fields'] ) ) {
			$fields = array_map( 'sanitize_text_field', explode( ',', sanitize_text_field( $_POST['fields'] ) ) );
		}
		$data = seopress_pro_get_service( 'Completions' )->generateImgAltText( $post_id, $meta, $language, true, null, $fields );
	} elseif ( in_array( $meta, array( 'fb_title', 'fb_desc', 'twitter_title', 'twitter_desc' ), true ) ) {
		// Determine platform and meta_type from the meta value
		$platform  = ( strpos( $meta, 'fb_' ) === 0 ) ? 'facebook' : 'twitter';
		$meta_type = ( strpos( $meta, '_title' ) !== false ) ? 'title' : 'desc';

		$data = seopress_pro_get_service( 'Completions' )->generateSocialMetas( $post_id, $meta_type, $platform, $language );

		// Map the response key to match the expected format for JavaScript
		// generateSocialMetas returns 'content' key, we need to map it to the specific meta field
		if ( isset( $data['content'] ) ) {
			$data[ $meta ] = $data['content'];
		}
	} else {
		$data = seopress_pro_get_service( 'Completions' )->generateTitlesDesc( $post_id, $meta, $language );
	}

	if ( 'Success' !== $data['message'] ) {
		wp_send_json_error( $data );
	} else {
		wp_send_json_success( $data );
	}
}
add_action( 'wp_ajax_seopress_ai_generate_seo_meta', 'seopress_ai_generate_seo_meta' );

/**
 * AI - Check license key.
 *
 * @return void
 */
function seopress_ai_check_license_key() {
	check_ajax_referer( 'seopress_ai_check_license_key_nonce' );

	if ( ! current_user_can( 'manage_options' ) && ! is_admin() ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-seopress-pro' ) ) );
		return;
	}

	// Determine provider based on the request.
	$provider = 'openai'; // default
	if ( isset( $_REQUEST['seopress_ai_provider'] ) ) {
		$provider = sanitize_text_field( $_REQUEST['seopress_ai_provider'] );
	} elseif ( isset( $_REQUEST['seopress_ai_model'] ) ) {
		$model = sanitize_text_field( $_REQUEST['seopress_ai_model'] );
		// Extract provider from model or determine based on context.
		if ( strpos( $model, 'deepseek' ) !== false ) {
			$provider = 'deepseek';
		} else {
			$provider = 'openai';
		}
	}

	// Save API key for the specific provider (only if it's not the placeholder).
	$options        = get_option( 'seopress_pro_option_name' );
	$placeholder    = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
	$submitted_key  = isset( $_REQUEST['seopress_ai_api_key'] ) ? sanitize_text_field( $_REQUEST['seopress_ai_api_key'] ) : '';

	// Only update if a new key is provided (not the placeholder or empty).
	if ( ! empty( $submitted_key ) && $submitted_key !== $placeholder ) {
		$options[ 'seopress_ai_' . $provider . '_api_key' ] = $submitted_key;
		update_option( 'seopress_pro_option_name', $options );
	}

	// Save model selection if provided.
	if ( isset( $_REQUEST['seopress_ai_model'] ) ) {
		$options[ 'seopress_ai_' . $provider . '_model' ] = sanitize_text_field( $_REQUEST['seopress_ai_model'] );
		update_option( 'seopress_pro_option_name', $options );
	}

	$usage_service = seopress_pro_get_service( 'Usage' );
	if ( null === $usage_service ) {
		wp_send_json_error( array( 'message' => __( 'Service not available.', 'wp-seopress-pro' ) ) );
		return;
	}

	$data = $usage_service->checkLicenseKeyExists( $provider );

	if ( 'success' === $data['code'] ) {
		$data = $usage_service->checkLicenseKeyExpiration( $provider );
	}

	wp_send_json_success( $data );
}
add_action( 'wp_ajax_seopress_ai_check_license_key', 'seopress_ai_check_license_key' );



/**
 * Site Audit - Load dynamically analysis.
 *
 * @return void
 */
function seopress_site_audit_load_analysis() {
	// Check nonce.
	check_ajax_referer( 'seopress_request_bot_nonce' );

	// Check if user is in admin.
	if ( ! is_admin() ) {
		return;
	}

	// Get security settings.
	$options = seopress_get_service( 'AdvancedOption' )->getOption();

	$audit_permissions = isset( $options['seopress_advanced_security_metaboxe_seopress-bot-batch'] ) ? $options['seopress_advanced_security_metaboxe_seopress-bot-batch'] : null;

	$allowed = false;
	global $wp_roles;
	$user = wp_get_current_user();

	if ( isset( $user->roles ) && is_array( $user->roles ) && ! empty( $user->roles ) ) {
		$seopress_user_role = current( $user->roles );

		if ( ! empty( $audit_permissions ) ) {
			if ( array_key_exists( $seopress_user_role, $audit_permissions ) ) {
				$allowed = true;
			}
		}
	}

	// Check if user has the capability to manage options.
	if ( current_user_can( seopress_capability( 'manage_options', 'site-audit' ) ) ) {
		$allowed = true;
	}

	// Check if user is allowed to access the analysis.
	if ( ! $allowed ) {
		return;
	}

	// Check if GSC toggle is ON.
	if ( seopress_get_service( 'ToggleOption' )->getToggleBot() !== '1' ) {
		return;
	}

	if ( ! isset( $_POST['type'] ) ) {
		wp_send_json_error( 'Type not provided.' );
	}

	$type = sanitize_text_field( $_POST['type'] );

	ob_start();
	seopress_pro_get_service( 'SiteAudit' )->renderAnalysisResults( $type );
	$content = ob_get_clean();

	echo $content;
	wp_die();
}
add_action( 'wp_ajax_seopress_site_audit_load_analysis', 'seopress_site_audit_load_analysis' );

/**
 * Site Audit - Run actions.
 *
 * @return void
 */
function seopress_site_audit_run_actions() {
	// Check nonce.
	check_ajax_referer( 'seopress_request_bot_nonce' );

	// Check if user is in admin.
	if ( ! is_admin() ) {
		return;
	}

	// Get security settings.
	$options = seopress_get_service( 'AdvancedOption' )->getOption();

	$audit_permissions = isset( $options['seopress_advanced_security_metaboxe_seopress-bot-batch'] ) ? $options['seopress_advanced_security_metaboxe_seopress-bot-batch'] : null;

	$allowed = false;
	global $wp_roles;
	$user = wp_get_current_user();

	if ( isset( $user->roles ) && is_array( $user->roles ) && ! empty( $user->roles ) ) {
		$seopress_user_role = current( $user->roles );

		if ( ! empty( $audit_permissions ) ) {
			if ( array_key_exists( $seopress_user_role, $audit_permissions ) ) {
				$allowed = true;
			}
		}
	}

	// Check if user has the capability to manage options.
	if ( current_user_can( seopress_capability( 'manage_options', 'site-audit' ) ) ) {
		$allowed = true;
	}

	// Check if user is allowed to access the analysis.
	if ( ! $allowed ) {
		return;
	}

	// Validate and sanitize input.
	if ( ! isset( $_POST['issue_post_id'] ) ) {
		wp_send_json_error( 'Post ID not provided.' );
	}

	$issue_post_id = absint( $_POST['issue_post_id'] );
	if ( ! $issue_post_id ) {
		wp_send_json_error( 'Invalid Post ID.' );
	}

	// Retrieve data.
	$data = seopress_pro_get_service( 'SEOIssuesDatabase' )->getData( $issue_post_id, array( 'img_alt' ) );

	if ( empty( $data ) || empty( $data[0] ) ) {
		wp_send_json_error( 'No data found.' );
	}

	$issue_desc = maybe_unserialize( $data[0]['issue_desc'] );
	if ( is_array( $issue_desc ) && ! empty( $issue_desc ) ) {
		$results = array();
		foreach ( $issue_desc as $issue ) {
			$attachment_id = seopress_get_service( 'SearchAttachment' )->searchByUrl( $issue );

			if ( ! empty( $attachment_id ) ) {
				foreach ( $attachment_id as $id ) {
					$result = seopress_pro_get_service( 'Completions' )->generateImgAltText( $id, 'alt_text' );
					if ( ! is_wp_error( $result ) ) {
						$results[] = $result;
					}
				}
			}
		}
		wp_send_json_success( $results );
	} else {
		wp_send_json_error( 'No valid issues found.' );
	}
}
add_action( 'wp_ajax_seopress_site_audit_run_actions', 'seopress_site_audit_run_actions' );
