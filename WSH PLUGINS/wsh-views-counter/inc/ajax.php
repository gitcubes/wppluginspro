<?php

// Detect mobile device (server-side).
function wsh_views_counter_is_mobile() {
	$useragent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';

	if ( empty( $useragent ) ) {
		return false;
	}

	if (
		preg_match( '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent )
		|| preg_match( '/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr( $useragent, 0, 4 ) )
	) {
		return true;
	}

	return false;
}

// Ajax – front-end: store page view.
function wordpress_ajax_wsh_views_counter_init_bkp() {
	global $wpdb;

	$cvc_count_interval = get_option( 'wsh_views_counter-count-interval' );
	$cvc_admin_visits   = get_option( 'wsh_views_counter-exclude-admin-visits' );
	$cvc_exclude_ips    = get_option( 'wsh_views_counter-exclude-ips' );

	if ( empty( $cvc_count_interval ) ) {
		$cvc_count_interval = '1h';
	}

	$is_mobile  = 0;
	$is_desktop = 1;
	$is_logged  = is_user_logged_in() ? 1 : 0;

	if ( isset( $_POST['is_desktop'] ) ) {
		$is_desktop = intval( $_POST['is_desktop'] );
	}

	if ( isset( $_POST['is_mobile'] ) ) {
		$is_mobile = intval( $_POST['is_mobile'] );
	}

	// Server-side device detection (override JS info if mobile device is detected).
	if ( wsh_views_counter_is_mobile() ) {
		$is_mobile  = 1;
		$is_desktop = 0;
	}

	// Exclude admin visits if enabled in settings.
	if ( (int) $cvc_admin_visits === 1 && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
		wp_send_json(
			array(
				'status'         => 'done',
				'info'           => 'Admin visits excluded',
				'is_logged'      => $is_logged,
				'is_mobile'      => $is_mobile,
				'is_desktop'     => $is_desktop,
				'count_interval' => $cvc_count_interval,
			)
		);
	}

	// Exclude specific IP addresses.
	if ( ! empty( $cvc_exclude_ips ) ) {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// If a list of IP addresses is present, use the first one.
			$ip_parts = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$ip       = trim( $ip_parts[0] );
		} else {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
		}

		$ip        = trim( $ip );
		$arr_ips   = array_map( 'trim', explode( ';', $cvc_exclude_ips ) );
		$arr_ips   = array_filter( $arr_ips );

		if ( ! empty( $ip ) && is_array( $arr_ips ) && ! empty( $arr_ips ) && in_array( $ip, $arr_ips, true ) ) {
			wp_send_json(
				array(
					'status'         => 'done',
					'info'           => 'IP address excluded',
					'is_logged'      => $is_logged,
					'is_mobile'      => $is_mobile,
					'is_desktop'     => $is_desktop,
					'count_interval' => $cvc_count_interval,
				)
			);
		}
	}

	// Detect post via POST or referrer URL.
	$post_id = 0;

	// 1) First, try to get the post_id directly from the AJAX request (the most reliable).
	if ( isset( $_POST['post_id'] ) ) {
		$post_id = intval( $_POST['post_id'] );
	}

	// 2) If for some reason the post_id did not arrive, try via the referrer URL (fallback).
	if ( $post_id <= 0 ) {
		$url = wp_get_referer();

		if ( ! empty( $url ) ) {
			$post_id = url_to_postid( $url );
		}
	}
	$post_id   = intval( $post_id );
	$post_type = $post_id ? get_post_type( $post_id ) : '';

	/*$cvc_types = array( 'post', 'page', 'product' );
	if ( ! in_array( $post_type, $cvc_types, true ) ) {
		$post_type = 'post';
	}*/

	// Allowed post types from plugin settings.
	if ( class_exists( 'WSH_Views_Counter' ) ) {
		$allowed_post_types = WSH_Views_Counter::get_supported_post_types();
	} else {
		$allowed_post_types = array( 'post', 'page', 'product' );
	}

	if ( ! in_array( $post_type, $allowed_post_types, true ) ) {
		wp_send_json(
			array(
				'status'         => 'done',
				'info'           => 'Post type excluded',
				'is_logged'      => $is_logged,
				'is_mobile'      => $is_mobile,
				'is_desktop'     => $is_desktop,
				'count_interval' => $cvc_count_interval,
			)
		);
	}


	if ( $post_id > 0 ) {
		// Total views (post meta).
		$cvc_views_counter = get_post_meta( $post_id, 'wsh_views_count', true );
		$cvc_views_counter = (int) $cvc_views_counter;
		$cvc_views_counter = ( $cvc_views_counter > 0 ) ? $cvc_views_counter + 1 : 1;

		update_post_meta( $post_id, 'wsh_views_count', $cvc_views_counter );

		// Views per day in the custom table.
		$cvc_table = $wpdb->prefix . 'wsh_views_counter';
		$today     = current_time( 'Y-m-d' );

		// Find existing record for this post and date.
		$check_res = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $cvc_table WHERE post_id = %d AND view_date = %s",
				$post_id,
				$today
			)
		);

		if ( ! empty( $check_res ) && isset( $check_res->ID ) ) {
			$single_cvc_views_counter = (int) $check_res->view_count + 1;
			$is_mobile_counter        = (int) $check_res->is_mobile;
			$is_desktop_counter       = (int) $check_res->is_desktop;
			$is_logged_counter        = (int) $check_res->is_logged;

			if ( $is_mobile > 0 ) {
				$is_mobile_counter++;
			}
			if ( $is_desktop > 0 ) {
				$is_desktop_counter++;
			}
			if ( $is_logged > 0 ) {
				$is_logged_counter++;
			}

			$wpdb->update(
				$cvc_table,
				array(
					'view_count' => $single_cvc_views_counter,
					'is_mobile'  => $is_mobile_counter,
					'is_desktop' => $is_desktop_counter,
					'is_logged'  => $is_logged_counter,
				),
				array(
					'ID' => (int) $check_res->ID,
				)
			);
		} else {
			$single_cvc_views_counter = 1;
			$is_mobile_counter        = ( $is_mobile > 0 ) ? 1 : 0;
			$is_desktop_counter       = ( $is_desktop > 0 ) ? 1 : 0;
			$is_logged_counter        = ( $is_logged > 0 ) ? 1 : 0;

			$wpdb->insert(
				$cvc_table,
				array(
					'post_id'    => $post_id,
					'post_type'  => $post_type,
					'view_date'  => $today,
					'view_count' => $single_cvc_views_counter,
					'is_mobile'  => $is_mobile_counter,
					'is_desktop' => $is_desktop_counter,
					'is_logged'  => $is_logged_counter,
				)
			);
		}
	}

	wp_send_json(
		array(
			'status'         => 'done',
			'info'           => 'Success',
			'is_logged'      => $is_logged,
			'is_mobile'      => $is_mobile,
			'is_desktop'     => $is_desktop,
			'count_interval' => $cvc_count_interval,
		)
	);
}

// Ajax – front-end: store page view.
function wordpress_ajax_wsh_views_counter_init() {
	global $wpdb;

	$cvc_count_interval = get_option( 'wsh_views_counter-count-interval' );
	$cvc_admin_visits   = get_option( 'wsh_views_counter-exclude-admin-visits' );
	$cvc_exclude_ips    = get_option( 'wsh_views_counter-exclude-ips' );

	if ( empty( $cvc_count_interval ) ) {
		$cvc_count_interval = '1h';
	}

	$is_mobile  = 0;
	$is_desktop = 1;
	$is_logged  = is_user_logged_in() ? 1 : 0;

	if ( isset( $_POST['is_desktop'] ) ) {
		$is_desktop = intval( $_POST['is_desktop'] );
	}

	if ( isset( $_POST['is_mobile'] ) ) {
		$is_mobile = intval( $_POST['is_mobile'] );
	}

	// Server-side device detection (override JS info if mobile device is detected).
	if ( function_exists( 'wsh_views_counter_is_mobile' ) && wsh_views_counter_is_mobile() ) {
		$is_mobile  = 1;
		$is_desktop = 0;
	}

	// Exclude admin visits if enabled in settings.
	if ( (int) $cvc_admin_visits === 1 && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
		wp_send_json(
			array(
				'status'         => 'done',
				'info'           => 'Admin visits excluded',
				'is_logged'      => $is_logged,
				'is_mobile'      => $is_mobile,
				'is_desktop'     => $is_desktop,
				'count_interval' => $cvc_count_interval,
			)
		);
	}

	/*
	* Detect IP once – we need it for:
	* - exclude list (free)
	* - geo analytics (free data, PRO UI)
	*/
	$ip = '';
	if ( isset( $_POST['client_ip'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_POST['client_ip'] ) );
	}else if ( function_exists( 'wsh_views_counter_get_client_ip' ) ) {
		$ip = wsh_views_counter_get_client_ip();
	}

	// Exclude specific IP addresses (legacy option – semicolon separated).
	if ( ! empty( $cvc_exclude_ips ) ) {

		$arr_ips = array_map( 'trim', explode( ';', (string) $cvc_exclude_ips ) );
		$arr_ips = array_filter( $arr_ips );

		if ( ! empty( $ip ) && ! empty( $arr_ips ) && in_array( $ip, $arr_ips, true ) ) {
			wp_send_json(
				array(
					'status'         => 'done',
					'info'           => 'IP address excluded',
					'is_logged'      => $is_logged,
					'is_mobile'      => $is_mobile,
					'is_desktop'     => $is_desktop,
					'count_interval' => $cvc_count_interval,
				)
			);
		}
	}

	$cvc_exclude_bots = get_option( 'wsh_views_counter-exclude-bots' );
	
	// Exclude bots / crawlers if enabled.
	if ( (int) $cvc_exclude_bots === 1 ) {

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';

		if ( wsh_views_counter_is_bot_user_agent( $user_agent ) ) {
			wp_send_json(
				array(
					'status'         => 'done',
					'info'           => 'Bot visit excluded',
					'is_logged'      => $is_logged,
					'is_mobile'      => $is_mobile,
					'is_desktop'     => $is_desktop,
					'count_interval' => $cvc_count_interval,
				)
			);
		}
	}

	// Detect post via POST or referrer URL.
	$post_id = 0;

	// 1) First, try to get the post_id directly from the AJAX request (the most reliable).
	if ( isset( $_POST['post_id'] ) ) {
		$post_id = intval( $_POST['post_id'] );
	}

	// 2) If for some reason the post_id did not arrive, try via the referrer URL (fallback).
	if ( $post_id <= 0 ) {
		$url = wp_get_referer();

		if ( ! empty( $url ) ) {
			$post_id = url_to_postid( $url );
		}
	}

	$post_id   = intval( $post_id );
	$post_type = $post_id ? get_post_type( $post_id ) : '';

	// Allowed post types from plugin settings.
	if ( class_exists( 'WSH_Views_Counter' ) ) {
		$allowed_post_types = WSH_Views_Counter::get_supported_post_types();
	} else {
		$allowed_post_types = array( 'post', 'page', 'product' );
	}

	if ( ! $post_id || empty( $post_type ) || ! in_array( $post_type, $allowed_post_types, true ) ) {
		wp_send_json(
			array(
				'status'         => 'done',
				'info'           => 'Post type excluded or no valid post ID',
				'is_logged'      => $is_logged,
				'is_mobile'      => $is_mobile,
				'is_desktop'     => $is_desktop,
				'count_interval' => $cvc_count_interval,
			)
		);
	}

	// Prepare some common vars.
	$today  = current_time( 'Y-m-d' );
	$now_dt = current_time( 'mysql' );

	// Session ID from JS (for sessions & real-time).
	$session_key = '';
	if ( isset( $_POST['session_id'] ) ) {
		$session_key = sanitize_text_field( wp_unslash( $_POST['session_id'] ) );
	}

	/*
	 * 1) Main daily views table + post meta total
	 */
	// Total views (post meta).
	$cvc_views_counter = get_post_meta( $post_id, 'wsh_views_count', true );
	$cvc_views_counter = (int) $cvc_views_counter;
	$cvc_views_counter = ( $cvc_views_counter > 0 ) ? $cvc_views_counter + 1 : 1;

	update_post_meta( $post_id, 'wsh_views_count', $cvc_views_counter );

	// Views per day in the custom main table.
	$cvc_table = $wpdb->prefix . 'wsh_views_counter';

	// Find existing record for this post and date.
	$check_res = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $cvc_table WHERE post_id = %d AND view_date = %s",
			$post_id,
			$today
		)
	);

	if ( ! empty( $check_res ) && isset( $check_res->ID ) ) {
		$single_cvc_views_counter = (int) $check_res->view_count + 1;
		$is_mobile_counter        = (int) $check_res->is_mobile;
		$is_desktop_counter       = (int) $check_res->is_desktop;
		$is_logged_counter        = (int) $check_res->is_logged;

		if ( $is_mobile > 0 ) {
			$is_mobile_counter++;
		}
		if ( $is_desktop > 0 ) {
			$is_desktop_counter++;
		}
		if ( $is_logged > 0 ) {
			$is_logged_counter++;
		}

		$wpdb->update(
			$cvc_table,
			array(
				'view_count' => $single_cvc_views_counter,
				'is_mobile'  => $is_mobile_counter,
				'is_desktop' => $is_desktop_counter,
				'is_logged'  => $is_logged_counter,
			),
			array(
				'ID' => (int) $check_res->ID,
			)
		);
	} else {
		$single_cvc_views_counter = 1;
		$is_mobile_counter        = ( $is_mobile > 0 ) ? 1 : 0;
		$is_desktop_counter       = ( $is_desktop > 0 ) ? 1 : 0;
		$is_logged_counter        = ( $is_logged > 0 ) ? 1 : 0;

		$wpdb->insert(
			$cvc_table,
			array(
				'post_id'    => $post_id,
				'post_type'  => $post_type,
				'view_date'  => $today,
				'view_count' => $single_cvc_views_counter,
				'is_mobile'  => $is_mobile_counter,
				'is_desktop' => $is_desktop_counter,
				'is_logged'  => $is_logged_counter,
				'created'    => $now_dt,
			)
		);
	}

	/*
	 * 2) GEO analytics (FREE collects, PRO displays)
	 */
	$geo_country_code = '';
	$geo_country_name = '';
	$geo_city         = '';

	if ( function_exists( 'wsh_views_counter_get_geo_from_ip' ) && ! empty( $ip ) ) {
		$geo = wsh_views_counter_get_geo_from_ip( $ip );

		if ( is_array( $geo ) ) {
			$geo_country_code = isset( $geo['country_code'] ) ? $geo['country_code'] : '';
			$geo_country_name = isset( $geo['country_name'] ) ? $geo['country_name'] : '';
			$geo_city         = isset( $geo['city'] ) ? $geo['city'] : '';
		}
	}

	$geo_table = $wpdb->prefix . 'wsh_views_counter_geo';

	if ( ! empty( $geo_country_code ) ) {
		$geo_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $geo_table 
				 WHERE post_id = %d 
				   AND view_date = %s
				   AND country_code = %s
				   AND city = %s",
				$post_id,
				$today,
				$geo_country_code,
				$geo_city
			)
		);

		if ( $geo_row ) {
			$wpdb->update(
				$geo_table,
				array(
					'view_count' => (int) $geo_row->view_count + 1,
				),
				array(
					'ID' => (int) $geo_row->ID,
				)
			);
		} else {
			$wpdb->insert(
				$geo_table,
				array(
					'post_id'      => $post_id,
					'post_type'    => $post_type,
					'view_date'    => $today,
					'country_code' => $geo_country_code,
					'country_name' => $geo_country_name,
					'city'         => $geo_city,
					'view_count'   => 1,
				)
			);
		}
	}

	/*
	 * 3) Referrer analytics (FREE collects, PRO displays)
	 */
	$ref_type   = 'direct';
	$ref_domain = '';

	// Referrer prosleđen iz JS-a (document.referrer).
	$ref_url = '';
	if ( isset( $_POST['referrer'] ) ) {
		$ref_url = wp_unslash( $_POST['referrer'] );
	}

	if ( function_exists( 'wsh_views_counter_parse_referrer' ) ) {
		$ref_data   = wsh_views_counter_parse_referrer( $ref_url );
		$ref_type   = isset( $ref_data['ref_type'] ) ? $ref_data['ref_type'] : 'direct';
		$ref_domain = isset( $ref_data['ref_domain'] ) ? $ref_data['ref_domain'] : '';
	}

	$ref_table = $wpdb->prefix . 'wsh_views_counter_ref';

	$ref_row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $ref_table 
			 WHERE post_id = %d
			   AND view_date = %s
			   AND ref_type = %s
			   AND ref_domain = %s",
			$post_id,
			$today,
			$ref_type,
			$ref_domain
		)
	);

	if ( $ref_row ) {
		$wpdb->update(
			$ref_table,
			array(
				'view_count' => (int) $ref_row->view_count + 1,
			),
			array(
				'ID' => (int) $ref_row->ID,
			)
		);
	} else {
		$wpdb->insert(
			$ref_table,
			array(
				'post_id'    => $post_id,
				'post_type'  => $post_type,
				'view_date'  => $today,
				'ref_type'   => $ref_type,
				'ref_domain' => $ref_domain,
				'view_count' => 1,
			)
		);
	}

	/*
	 * 4) Sessions table (landing/exit, hit_count)
	 */
	$session_table = $wpdb->prefix . 'wsh_views_sessions';

	if ( ! empty( $session_key ) ) {

		$session_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $session_table WHERE session_key = %s",
				$session_key
			)
		);

		if ( $session_row ) {
			$first_post_id = (int) $session_row->first_post_id;
			if ( $first_post_id <= 0 ) {
				$first_post_id = $post_id;
			}

			$wpdb->update(
				$session_table,
				array(
					'last_seen'    => $now_dt,
					'last_post_id' => $post_id,
					'hit_count'    => (int) $session_row->hit_count + 1,
					'country_code' => $geo_country_code,
				),
				array(
					'ID' => (int) $session_row->ID,
				)
			);
		} else {
			$wpdb->insert(
				$session_table,
				array(
					'session_key'   => $session_key,
					'first_seen'    => $now_dt,
					'last_seen'     => $now_dt,
					'first_post_id' => $post_id,
					'last_post_id'  => $post_id,
					'hit_count'     => 1,
					'country_code'  => $geo_country_code,
				)
			);
		}
	}

	/*
	 * 5) Live hits (real-time) – lightweight, for "active users" & "last 10 hits"
	 */
	$live_table = $wpdb->prefix . 'wsh_views_live_hits';

	$wpdb->insert(
		$live_table,
		array(
			'session_key'  => $session_key,
			'post_id'      => $post_id,
			'post_type'    => $post_type,
			'view_time'    => $now_dt,
			'country_code' => $geo_country_code,
			'ref_type'     => $ref_type,
			'ref_domain'   => $ref_domain,
			'is_mobile'    => $is_mobile,
			'is_logged'    => $is_logged,
		)
	);

	// Optionally: simple cleanup here or via WP-Cron (recommended).
	// Example (very conservative), cleanup older than 48 hours:
	/*
	$cleanup_threshold = gmdate( 'Y-m-d H:i:s', strtotime( '-48 hours', current_time( 'timestamp', true ) ) );
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $live_table WHERE view_time < %s",
			$cleanup_threshold
		)
	);
	*/

	wp_send_json(
		array(
			'status'         => 'done',
			'info'           => 'Success',
			'is_logged'      => $is_logged,
			'is_mobile'      => $is_mobile,
			'is_desktop'     => $is_desktop,
			'count_interval' => $cvc_count_interval,
		)
	);
}


add_action( 'wp_ajax_wordpress_ajax_wsh_views_counter_init', 'wordpress_ajax_wsh_views_counter_init' );
add_action( 'wp_ajax_nopriv_wordpress_ajax_wsh_views_counter_init', 'wordpress_ajax_wsh_views_counter_init' );

// Admin – save plugin settings.
function wordpress_ajax_wsh_views_counter_save_settings() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json(
			array(
				'status'  => 'error',
				'message' => __( 'You are not allowed to change settings.', 'wsh-views-counter' ),
			),
			403
		);
	}

	
	// Basic settings.
	$cvc_admin_visits   = isset( $_POST['cvc_admin_visits'] ) ? (int) $_POST['cvc_admin_visits'] : 0;
	$cvc_count_interval = isset( $_POST['cvc_count_interval'] ) ? sanitize_text_field( wp_unslash( $_POST['cvc_count_interval'] ) ) : '1h';
	$cvc_exclude_ips    = isset( $_POST['cvc_exclude_ips'] ) ? sanitize_text_field( wp_unslash( $_POST['cvc_exclude_ips'] ) ) : '';
	$cvc_exclude_bots = isset( $_POST['cvc_exclude_bots'] ) ? 1 : 0;

	// Visitor filters.
	$exclude_logged_in = isset( $_POST['exclude_logged_in'] ) ? (int) $_POST['exclude_logged_in'] : 0;
	$exclude_guests    = isset( $_POST['exclude_guests'] ) ? (int) $_POST['exclude_guests'] : 0;
	$exclude_roles     = array();

	if ( isset( $_POST['exclude_roles'] ) && is_array( $_POST['exclude_roles'] ) ) {
		foreach ( $_POST['exclude_roles'] as $role_slug ) {
			$exclude_roles[] = sanitize_text_field( wp_unslash( $role_slug ) );
		}
	}

	$exclude_roles_str = implode( ',', $exclude_roles );

	// Display options.
	$display_enable        = isset( $_POST['display_enable'] ) ? (int) $_POST['display_enable'] : 0;
	$display_position      = isset( $_POST['display_position'] ) ? sanitize_text_field( wp_unslash( $_POST['display_position'] ) ) : 'after';
	$display_label         = isset( $_POST['display_label'] ) ? sanitize_text_field( wp_unslash( $_POST['display_label'] ) ) : 'Views:';
	$display_format_number = isset( $_POST['display_format_number'] ) ? (int) $_POST['display_format_number'] : 1;

	// Post types list.
	$post_types = array();
	if ( isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ) {
		foreach ( $_POST['post_types'] as $pt_slug ) {
			$post_types[] = sanitize_key( wp_unslash( $pt_slug ) );
		}
	}
	if ( empty( $post_types ) ) {
		// Fallback – always at least "post".
		$post_types = array( 'post' );
	}

	// Data retention.
	$cleanup_period = isset( $_POST['cleanup_period'] )
		? sanitize_text_field( wp_unslash( $_POST['cleanup_period'] ) )
		: '0';

	// Validate to ensure it is one of the allowed values.
	$allowed_cleanup = array( '0', '30', '90', '180', '365', '730' );
	if ( ! in_array( $cleanup_period, $allowed_cleanup, true ) ) {
		$cleanup_period = '0';
	}


	// Save options.
	update_option( 'wsh_views_counter-count-interval', $cvc_count_interval );
	update_option( 'wsh_views_counter-exclude-admin-visits', $cvc_admin_visits );
	update_option( 'wsh_views_counter-exclude-ips', $cvc_exclude_ips );
	update_option( 'wsh_views_counter-exclude-bots', $cvc_exclude_bots );

	update_option( 'wsh_views_counter-exclude-logged-in', $exclude_logged_in );
	update_option( 'wsh_views_counter-exclude-guests', $exclude_guests );
	update_option( 'wsh_views_counter-exclude-roles', $exclude_roles_str );

	update_option( 'wsh_views_counter-display-enable', $display_enable );
	update_option( 'wsh_views_counter-display-position', $display_position );
	update_option( 'wsh_views_counter-display-label', $display_label );
	update_option( 'wsh_views_counter-display-format-number', $display_format_number );
	update_option( 'wsh_views_counter-post-types', $post_types );
	update_option( 'wsh_views_counter-cleanup-period', $cleanup_period );

	wp_send_json(
		array(
			'status'  => 'done',
			'message' => __( 'Success! Settings have been saved.', 'wsh-views-counter' ),
		)
	);
}


add_action( 'wp_ajax_wordpress_ajax_wsh_views_counter_save_settings', 'wordpress_ajax_wsh_views_counter_save_settings' );
// No need for nopriv – only admins can change settings.
// add_action( 'wp_ajax_nopriv_wordpress_ajax_wsh_views_counter_save_settings', 'wordpress_ajax_wsh_views_counter_save_settings' );


// Admin – import all data from Post Views Counter (wp_post_views).
function wordpress_ajax_wsh_views_counter_import_from_pvc() {

	function formatDate($date) {
		// Assumes format YYYYMMDD.
		$y = substr($date, 0, 4);
		$m  = substr($date, 4, 2);
		$d    = substr($date, 6, 2);

		return "$y-$m-$d";
	}

	function getWeekStartDate($param) {
		// First 4 digits are the year.
		$year = (int) substr($param, 0, 4);
		// The rest is the week number (can be 1 or 2 digits).
		$week = (int) substr($param, 4);

		$dt = new DateTime();
		// 1 = Monday (ISO standard).
		$dt->setISODate($year, $week, 1);

		return $dt->format('Y-m-d');
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json(
			array(
				'status'  => 'error',
				'message' => __( 'You are not allowed to run this import.', 'wsh-views-counter' ),
			),
			403
		);
	}

	// If the import has already been run, do not allow it again.
	$import_done = get_option( 'wsh_views_counter_pvc_import_done' );
	if ( $import_done ) {
		wp_send_json(
			array(
				'status'  => 'error',
				'message' => __( 'Import has already been completed. To run it again, reset the wsh_views_counter_pvc_import_done option manually.', 'wsh-views-counter' ),
			)
		);
	}

	global $wpdb;

	$source_table = $wpdb->prefix . 'post_views';        // source table from the competing plugin.
	$target_table = $wpdb->prefix . 'wsh_views_counter'; // your plugin's table.
	$target_meta_table = $wpdb->prefix . 'postmeta';

	// Check whether the wp_post_views table exists.
	$like         = $wpdb->esc_like( $source_table );
	$found_table  = $wpdb->get_var(
		$wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$like
		)
	);

	if ( $found_table !== $source_table ) {
		wp_send_json(
			array(
				'status'  => 'error',
				/* translators: 1: table name */
				'message' => sprintf(
					__( 'Source table %s was not found. Make sure Post Views Counter is using this table name.', 'wsh-views-counter' ),
					esc_html( $source_table )
				),
			)
		);
	}

	// Try to avoid timeout/memory limit issues.
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}

	// Optional: clean existing data first (to avoid duplicates).
	$truncate_ok = $wpdb->query( "TRUNCATE TABLE $target_table" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$delete_ok = $wpdb->query( "DELETE FROM $target_meta_table WHERE meta_key = 'wsh_views_count'" ); 

	if ( $truncate_ok === false || $delete_ok === false) {
		wp_send_json(
			array(
				'status'  => 'error',
				'message' => sprintf(
					__( 'Could not truncate target table %s. DB error: %s', 'wsh-views-counter' ),
					esc_html( $target_table ),
					esc_html( $wpdb->last_error )
				),
			)
		);
	}

	// 1) Insert all data from wp_post_views into wsh_views_counter
	// in batches, grouped by post_id + view_date.
	//
	// period is of type 20251112 => STR_TO_DATE(period, '%Y%m%d') => '2025-11-12'
	// Everything is treated as desktop view (is_desktop = view_count).

	$insert_count = 0;
	$new_counts = [];

	// How many rows to process per batch.
	$limit  = 1000;
	$last_id = 0;

	do {

		// Fetch rows in batches and also get post_type from wp_posts.
		$sql_select = $wpdb->prepare("
			SELECT s.id AS post_id, s.period, s.count, p.post_type
			FROM $source_table AS s
			LEFT JOIN {$wpdb->posts} AS p ON p.ID = s.id
			WHERE s.type = %d 
			AND s.id > %d
			ORDER BY s.id
			LIMIT %d
		", 1, $last_id, $limit);

		$rows = $wpdb->get_results( $sql_select );

		if ( empty( $rows ) ) {
			break;
		}

		$values_sql = [];

		foreach ( $rows as $row ) {
			$post_id     = (int) $row->post_id;
			//$target_date = formatDate( $row->period ); // assumption: returns 'Y-m-d'.
			$count       = (int) $row->count;
			$post_type   = $row->post_type ? $row->post_type : '';
			$target_date = getWeekStartDate($row->period);

			// One row in the INSERT VALUES list, using $wpdb->prepare for safety.
			$values_sql[] = $wpdb->prepare(
				"(%d, %s, %s, %d, %d, %d, %d)",
				$post_id,
				$post_type,
				$target_date,
				$count,
				0, // is_mobile
				1, // is_desktop
				0  // is_logged
			);

			if ( ! isset( $new_counts[ $post_id ] ) ) {
				$new_counts[ $post_id ] = 0;
			}
			$new_counts[ $post_id ] += $count;

			$insert_count++;
			$last_id = $post_id;
		}

		// One bulk INSERT for the entire batch.
		if ( ! empty( $values_sql ) ) {
			$sql_insert = "
				INSERT INTO $target_table
					(post_id, post_type, view_date, view_count, is_mobile, is_desktop, is_logged)
				VALUES " . implode( ",\n", $values_sql );

			$wpdb->query( $sql_insert );
		}

	} while ( count( $rows ) === $limit );

	/*$sql_insert = "
		INSERT INTO $target_table (post_id, post_type, view_date, view_count, is_mobile, is_desktop, is_logged)
		SELECT 
			p.ID AS post_id,
			p.post_type AS post_type,
			STR_TO_DATE(v.period, '%Y%m%d') AS view_date,
			SUM(v.count) AS view_count,
			0 AS is_mobile,
			SUM(v.count) AS is_desktop,
			0 AS is_logged
		FROM $source_table v
		JOIN {$wpdb->posts} p ON p.ID = v.id
		WHERE p.post_type IN ('post', 'page', 'product')
		GROUP BY post_id, view_date
	";

	$rows_inserted = $wpdb->query( $sql_insert ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
	*/

	//if ( $rows_inserted === false ) {
    if ( $insert_count == 0 ) {
		wp_send_json(
			array(
				'status'  => 'error',
				'message' => sprintf(
					__( 'Insert into %1$s failed. DB error: %2$s', 'wsh-views-counter' ),
					esc_html( $target_table ),
					esc_html( $wpdb->last_error )
				),
			)
		);
	}

	$posts_updated = 0;
	/*
	// 2) Calculate totals per post and save into post meta (wsh_views_count).
	$totals = $wpdb->get_results(
		"SELECT post_id, SUM(view_count) AS total
		 FROM $target_table
		 GROUP BY post_id",
		ARRAY_A
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

	if ( $totals === null ) {
		wp_send_json(
			array(
				'status'  => 'error',
				'message' => sprintf(
					__( 'Failed to calculate totals from %1$s. DB error: %2$s', 'wsh-views-counter' ),
					esc_html( $target_table ),
					esc_html( $wpdb->last_error )
				),
			)
		);
	}

	if ( ! empty( $totals ) ) {
		foreach ( $totals as $row ) {
			$post_id = (int) $row['post_id'];
			$total   = (int) $row['total'];

			if ( $post_id > 0 ) {
				update_post_meta( $post_id, 'wsh_views_count', $total );
				$posts_updated++;
			}
		}
	}
	*/

	foreach($new_counts as $post_id => $count){
		$post_id = (int) $post_id;
		$total   = (int) $count;
		if ( $post_id > 0 ) {
			update_post_meta( $post_id, 'wsh_views_count', $total );
			$posts_updated++;
		}
	}

	// Store a flag that import has been completed (to prevent accidental re-runs).
	update_option( 'wsh_views_counter_pvc_import_done', current_time( 'mysql' ) );

	$message = sprintf(
		/* translators: 1: rows inserted, 2: posts updated */
		__( 'Import completed. Inserted rows into wsh_views_counter: %1$d. Updated posts: %2$d.', 'wsh-views-counter' ),
		(int) $insert_count,
		(int) $posts_updated
	);

	wp_send_json(
		array(
			'status'  => 'done',
			'message' => $message,
		)
	);
}

add_action( 'wp_ajax_wordpress_ajax_wsh_views_counter_import_from_pvc', 'wordpress_ajax_wsh_views_counter_import_from_pvc' );


// Delete ALL data: logs table + post meta.
function wordpress_ajax_wsh_views_counter_delete_all_data() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json(
			array(
				'status'  => 'error',
				'message' => __( 'You are not allowed to perform this action.', 'wsh-views-counter' ),
			),
			403
		);
	}

	global $wpdb;

	$table_logs = $wpdb->prefix . 'wsh_views_counter';
	$table_geo = $wpdb->prefix . 'wsh_views_counter_geo';
	$table_ref = $wpdb->prefix . 'wsh_views_counter_ref';
	$table_hits = $wpdb->prefix . 'wsh_views_live_hits';
	$table_sessions = $wpdb->prefix . 'wsh_wsh_views_sessions';
	$table_woo = $wpdb->prefix . 'wsh_views_woo_funnel';
	$meta_key   = 'wsh_views_count';

	// Try to raise resource limits, just in case.
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'admin' );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}

	// 1) Clear the custom logs table.
	$truncate_result_logs = $wpdb->query( "TRUNCATE TABLE $table_logs" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
	$truncate_result_geo = $wpdb->query( "TRUNCATE TABLE $table_geo" );
	$truncate_result_ref = $wpdb->query( "TRUNCATE TABLE $table_ref" );
	$truncate_result_hits = $wpdb->query( "TRUNCATE TABLE $table_hits" );
	$truncate_result_ses = $wpdb->query( "TRUNCATE TABLE $table_sessions" );
	$truncate_result_woo = $wpdb->query( "TRUNCATE TABLE $table_woo" );


	if ( $truncate_result_logs === false || $truncate_result_geo === false || $truncate_result_ref === false || $truncate_result_hits === false || $truncate_result_ses === false || $truncate_result_woo === false ) {
		wp_send_json(
			array(
				'status'  => 'error',
				'message' => sprintf(
					/* translators: 1: table name, 2: DB error */
					__( 'Could not truncate table %1$s. DB error: %2$s', 'wsh-views-counter' ),
					esc_html( $table_logs ),
					esc_html( $wpdb->last_error )
				),
			)
		);
	}

	// 2) Delete all meta values for wsh_views_count.
	$deleted_meta_rows = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
			$meta_key
		)
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

	// 3) Reset the import flag (so import can be re-run if desired).
	delete_option( 'wsh_views_counter_pvc_import_done' );

	$message = sprintf(
		/* translators: 1: deleted meta rows */
		__( 'All data deleted. Removed %d post meta rows and truncated logs table.', 'wsh-views-counter' ),
		(int) $deleted_meta_rows
	);

	wp_send_json(
		array(
			'status'  => 'done',
			'message' => $message,
		)
	);
}

add_action(
	'wp_ajax_wordpress_ajax_wsh_views_counter_delete_all_data', 'wordpress_ajax_wsh_views_counter_delete_all_data'
);

/**
 * AJAX: manually update views count from the meta box.
 */
function wordpress_ajax_wsh_views_counter_update_manual_views() {

    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wsh_views_counter_meta_ajax' ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'Security check failed.', 'wsh-views-counter' ),
            )
        );
    }

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $views   = isset( $_POST['views'] ) ? intval( $_POST['views'] ) : 0;

    if ( $post_id <= 0 ) {
        wp_send_json_error(
            array(
                'message' => __( 'Invalid post ID.', 'wsh-views-counter' ),
            )
        );
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error(
            array(
                'message' => __( 'You are not allowed to edit this post.', 'wsh-views-counter' ),
            )
        );
    }

    if ( $views < 0 ) {
        $views = 0;
    }

    // Store the total number of views.
    update_post_meta( $post_id, 'wsh_views_count', $views );

    // (Optional: here you can later add logic to synchronize with the custom table if needed.)

    wp_send_json_success(
        array(
            'views' => $views,
        )
    );
}
add_action( 'wp_ajax_wsh_views_counter_update_manual_views', 'wordpress_ajax_wsh_views_counter_update_manual_views' );


/**
 * Detect client IP address in a robust way (Cloudflare / proxies aware).
 *
 * @return string
 */
function wsh_views_counter_get_client_ip() {

	$ip_keys = array(
		'HTTP_CF_CONNECTING_IP', // Cloudflare
		'HTTP_CLIENT_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_REAL_IP',
		'REMOTE_ADDR',
	);

	foreach ( $ip_keys as $key ) {
		if ( ! empty( $_SERVER[ $key ] ) ) {
			$ip = $_SERVER[ $key ];

			// HTTP_X_FORWARDED_FOR može imati listu IP-jeva – uzmi prvi.
			if ( 'HTTP_X_FORWARDED_FOR' === $key && strpos( $ip, ',' ) !== false ) {
				$parts = explode( ',', $ip );
				$ip    = trim( $parts[0] );
			}

			$ip = trim( (string) $ip );

			if ( ! empty( $ip ) ) {
				return $ip;
			}
		}
	}

	return '';
}

/**
 * Get geo info (country & city) from IP address, with multi-provider fallback.
 *
 * Primarni:  ipapi.co
 * Fallback:  ipwhois.app
 *
 * @param string $ip
 * @return array {
 *   @type string $country_code
 *   @type string $country_name
 *   @type string $city
 * }
 */
function wsh_views_counter_get_geo_from_ip( $ip ) {

	$ip = trim( (string) $ip );

	$result_empty = array(
		'country_code' => '',
		'country_name' => '',
		'city'         => '',
	);

	if ( empty( $ip ) ) {
		return $result_empty;
	}

	$cache_key = 'wsh_vc_geo_' . md5( $ip );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached && is_array( $cached ) && !empty($cached['country'])) {
		return $cached;
	}

	$result = $result_empty;

	/*
	 * 1) Primarni provider: ipapi.co
	 */
	$response = wp_remote_get(
		'https://ipapi.co/' . rawurlencode( $ip ) . '/json/',
		array(
			'timeout' => 3,
		)
	);

	if ( ! is_wp_error( $response ) ) {
		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $code ) {
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( is_array( $data ) ) {
				if ( ! empty( $data['country'] ) ) {
					$result['country_code'] = sanitize_text_field( $data['country'] );
				}
				if ( ! empty( $data['country_name'] ) ) {
					$result['country_name'] = sanitize_text_field( $data['country_name'] );
				}
				if ( ! empty( $data['city'] ) ) {
					$result['city'] = sanitize_text_field( $data['city'] );
				}
			}

			set_transient( $cache_key, $result, DAY_IN_SECONDS );
			return $result;
		}

		// Ako je 429, nećemo više dirati ipapi za ovaj IP neko vreme.
		if ( 429 === $code ) {
			// ne vraćamo odmah – probaćemo fallback provider.
		}
	}

	/*
	 * 2) Fallback provider: ipwhois.app
	 *    https://ipwhois.app/json/8.8.8.8
	 */
	$response2 = wp_remote_get(
		'https://ipwhois.app/json/' . rawurlencode( $ip ),
		array(
			'timeout' => 3,
		)
	);

	if ( ! is_wp_error( $response2 ) && 200 === wp_remote_retrieve_response_code( $response2 ) ) {
		$body2 = wp_remote_retrieve_body( $response2 );
		$data2 = json_decode( $body2, true );

		if ( is_array( $data2 ) ) {
			if ( ! empty( $data2['country_code'] ) ) {
				$result['country_code'] = sanitize_text_field( $data2['country_code'] );
			}
			if ( ! empty( $data2['country'] ) ) {
				$result['country_name'] = sanitize_text_field( $data2['country'] );
			}
			if ( ! empty( $data2['city'] ) ) {
				$result['city'] = sanitize_text_field( $data2['city'] );
			}
		}

		// Fallback uspeo – keširaj 1 dan.
		set_transient( $cache_key, $result, DAY_IN_SECONDS );
		return $result;
	}

	/*
	 * 3) Ako oba padnu – keširaj prazan rezultat kratko (da ne spamaš servise).
	 */
	set_transient( $cache_key, $result_empty, HOUR_IN_SECONDS );

	return $result_empty;
}


/**
 * Parse referrer URL and classify it.
 *
 * @param string $ref_url Full referrer URL (from JS: document.referrer).
 * @return array {
 *   @type string $ref_type   direct|internal|search|social|referral
 *   @type string $ref_domain Domain part of referrer (e.g. google.com).
 * }
 */
function wsh_views_counter_parse_referrer( $ref_url = '' ) {

	$ref_url = trim( (string) $ref_url );

	// Ako referrer nije prosleđen iz JS-a, fallback na HTTP_REFERER (može biti internal).
	if ( empty( $ref_url ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		$ref_url = trim( (string) $_SERVER['HTTP_REFERER'] );
	}

	if ( empty( $ref_url ) ) {
		return array(
			'ref_type'   => 'direct',
			'ref_domain' => '',
		);
	}

	$parsed = wp_parse_url( $ref_url );
	$host   = isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';

	if ( empty( $host ) ) {
		return array(
			'ref_type'   => 'direct',
			'ref_domain' => '',
		);
	}

	$site_host = parse_url( home_url(), PHP_URL_HOST );
	$site_host = strtolower( (string) $site_host );

	// Internal: isti domen ili subdomen istog domena.
	if ( $host === $site_host || substr( $host, -strlen( $site_host ) ) === $site_host ) {
		return array(
			'ref_type'   => 'internal',
			'ref_domain' => $host,
		);
	}

	// Search engines.
	if ( preg_match( '/google\.|bing\.|yahoo\./', $host ) ) {
		return array(
			'ref_type'   => 'search',
			'ref_domain' => $host,
		);
	}

	// Social networks.
	if ( preg_match( '/facebook\.|fb\.|instagram\.|t\.co|twitter\.com|x\.com/', $host ) ) {
		return array(
			'ref_type'   => 'social',
			'ref_domain' => $host,
		);
	}

	// Ostalo = referral.
	return array(
		'ref_type'   => 'referral',
		'ref_domain' => $host,
	);
}

/**
 * Simple bot / crawler detection based on User-Agent.
 *
 * @param string $user_agent Raw user agent string.
 * @return bool True if UA looks like a bot/crawler.
 */
function wsh_views_counter_is_bot_user_agent( $user_agent ) {

	$user_agent = strtolower( (string) $user_agent );

	if ( '' === $user_agent ) {
		return false;
	}

	// Common bots / crawlers / SEO tools.
	$bot_signatures = array(
		'bot',
		'crawl',
		'spider',
		'slurp',
		'mediapartners-google',
		'adsbot-google',
		'googlebot',
		'bingbot',
		'yandex',
		'baiduspider',
		'duckduckbot',
		'ahrefsbot',
		'semrushbot',
		'mj12bot',
		'facebookexternalhit',
		'ia_archiver',
		'uptimerobot',
		'python-requests',
		'curl/',
	);

	foreach ( $bot_signatures as $signature ) {
		if ( false !== strpos( $user_agent, $signature ) ) {
			return true;
		}
	}

	return false;
}
