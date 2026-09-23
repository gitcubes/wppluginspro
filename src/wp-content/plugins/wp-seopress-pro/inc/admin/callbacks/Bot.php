<?php //phpcs:ignore
/**
 * SEOPress PRO Bot callbacks.
 *
 * @package SEOPress PRO
 * @subpackage Callbacks
 */

defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

/**
 * Bot scan settings email callback.
 *
 * @return void
 */
function seopress_bot_scan_settings_email_callback() {
	$options = get_option( 'seopress_bot_option_name' );
	$check   = isset( $options['seopress_bot_scan_settings_email'] ) ? $options['seopress_bot_scan_settings_email'] : null;

	printf(
		'<input type="text" name="seopress_bot_option_name[seopress_bot_scan_settings_email]" aria-label="' . esc_html__( 'Your email address', 'wp-seopress-pro' ) . '" placeholder="' . esc_html__( 'e.g. admin@example.com', 'wp-seopress-pro' ) . '" value="%s" />',
		esc_html( $check )
	); ?>

<p class="description">
	<?php esc_html_e( 'Receive an alert by mail once the site audit is completed. Separate emails by commas.', 'wp-seopress-pro' ); ?>
</p>

	<?php
}

/**
 * Bot scan settings audit CPT callback.
 *
 * @return void
 */
function seopress_bot_scan_settings_audit_cpt_callback() {
	$options = get_option( 'seopress_bot_option_name' );

	$args = array(
		'show_ui' => true,
		'public'  => true,
	);

	$post_types = seopress_get_service( 'WordPressData' )->getPostTypes( false, $args );

	unset(
		$post_types['elementor_library'],
		$post_types['fl-builder-template'],
		$post_types['editor-template'],
		$post_types['editor-form-entry'],
		$post_types['breakdance_form_res'],
		$post_types['customer_discount'],
		$post_types['cuar_private_file'],
		$post_types['cuar_private_page'],
		$post_types['vc_grid_item'],
		$post_types['zion_template'],
		$post_types['tbuilder_layout'],
		$post_types['tbuilder_layout_part'],
		$post_types['tb_cf'],
		$post_types['ct_template'],
		$post_types['oxy_user_library'],
		$post_types['bricks_template']
	);

	if ( ! empty( $post_types ) ) {
		foreach ( $post_types as $seopress_cpt_key => $seopress_cpt_value ) {
			?>
		<!--List all post types-->
		<div class="seopress_wrap_single_cpt">

			<?php
				$check = isset( $options['seopress_bot_scan_settings_audit_cpt'][ $seopress_cpt_key ]['include'] );
			?>

			<label
				for="seopress_bot_scan_settings_audit_cpt_include[<?php echo esc_attr( $seopress_cpt_key ); ?>]">
				<input
					id="seopress_bot_scan_settings_audit_cpt_include[<?php echo esc_attr( $seopress_cpt_key ); ?>]"
					name="seopress_bot_option_name[seopress_bot_scan_settings_audit_cpt][<?php echo esc_attr( $seopress_cpt_key ); ?>][include]"
					type="checkbox" <?php if ( true === $check ) { ?>
				checked="yes"
				<?php } ?>
				value="1"/>

				<?php echo esc_html( $seopress_cpt_value->labels->name ); ?>
			</label>

			<?php
			if ( isset( $options['seopress_bot_scan_settings_audit_cpt'][ $seopress_cpt_key ]['include'] ) ) {
					esc_attr( $options['seopress_bot_scan_settings_audit_cpt'][ $seopress_cpt_key ]['include'] );
			}
			?>
		</div>

			<?php
		}
	}
	?>
	<p class="description">
		<?php echo wp_kses_post( sprintf( /* translators: %s public */ __( 'By default, all custom post types registered as %s will be analyzed.', 'wp-seopress-pro' ), '<code>public</code>' ) ); ?>
	</p>
	<?php
}

/**
 * Bot scan settings audit noindex callback.
 *
 * @return void
 */
function seopress_bot_scan_settings_audit_noindex_callback() {
	$options = get_option( 'seopress_bot_option_name' );

	$check = isset( $options['seopress_bot_scan_settings_audit_noindex'] );
	?>

<label for="seopress_bot_scan_settings_audit_noindex">
	<input id="seopress_bot_scan_settings_audit_noindex" name="seopress_bot_option_name[seopress_bot_scan_settings_audit_noindex]"
		type="checkbox" <?php if ( true === $check ) { ?>
	checked="yes"
	<?php } ?>
	value="1"/>

	<?php esc_html_e( 'Yes', 'wp-seopress-pro' ); ?>
</label>

<p class="description">
	<?php echo wp_kses_post( sprintf( /* translators: %s noindex */ __( 'By default, content marked as %s will NOT be scanned.', 'wp-seopress-pro' ), '<code>noindex</code>' ) ); ?>
</p>

	<?php
	if ( isset( $options['seopress_bot_scan_settings_audit_noindex'] ) ) {
		esc_attr( $options['seopress_bot_scan_settings_audit_noindex'] );
	}
}

/**
 * Bot scan settings audit batch size callback.
 *
 * @return void
 */
function seopress_bot_scan_settings_audit_batch_size_callback() {
	$options = get_option( 'seopress_bot_option_name' );
	$check   = isset( $options['seopress_bot_scan_settings_audit_batch_size'] );
	?>

	<input type="number" min="10" placeholder="10" max="50" name="seopress_bot_option_name[seopress_bot_scan_settings_audit_batch_size]" <?php if ( true === $check ) { ?>
value="<?php echo esc_attr( $options['seopress_bot_scan_settings_audit_batch_size'] ); ?>"
<?php } ?>
value="10"/>

	<p class="description">
		<?php esc_html_e( 'How many posts to scan at once. Default: 10. Max: 50', 'wp-seopress-pro' ); ?>
	</p>
	<?php
	if ( isset( $options['seopress_bot_scan_settings_audit_batch_size'] ) ) {
		esc_html( $options['seopress_bot_scan_settings_audit_batch_size'] );
	}
}

/**
 * Bot scan settings post types callback.
 *
 * @return void
 */
function seopress_bot_scan_settings_post_types_callback() {
	$options = get_option( 'seopress_bot_option_name' );

	global $wp_post_types;

	$args = array(
		'show_ui' => true,
		'public'  => true,
	);

	$output   = 'objects'; // names or objects, note names is the default.
	$operator = 'and'; // 'and' or 'or'. Default: and.

	$post_types = get_post_types( $args, $output, $operator );

	foreach ( $post_types as $seopress_cpt_key => $seopress_cpt_value ) {
		?>
<!--List all post types-->
<div class="seopress_wrap_single_cpt">

		<?php
		$check = isset( $options['seopress_bot_scan_settings_post_types'][ $seopress_cpt_key ]['include'] );
		?>

	<label
		for="seopress_bot_scan_settings_post_types_include[<?php echo esc_attr( $seopress_cpt_key ); ?>]">
		<input
			id="seopress_bot_scan_settings_post_types_include[<?php echo esc_attr( $seopress_cpt_key ); ?>]"
			name="seopress_bot_option_name[seopress_bot_scan_settings_post_types][<?php echo esc_attr( $seopress_cpt_key ); ?>][include]"
			type="checkbox" <?php if ( true === $check ) { ?>
		checked="yes"
		<?php } ?>
		value="1"/>

		<?php echo esc_html( $seopress_cpt_value->labels->name ); ?>
	</label>

		<?php
		if ( isset( $options['seopress_bot_scan_settings_post_types'][ $seopress_cpt_key ]['include'] ) ) {
			esc_attr( $options['seopress_bot_scan_settings_post_types'][ $seopress_cpt_key ]['include'] );
		}
		?>
</div>

		<?php
	}
}

/**
 * Bot scan settings where callback.
 *
 * @return void
 */
function seopress_bot_scan_settings_where_callback() {
	$options = get_option( 'seopress_bot_option_name' );

	$where = array(
		'post_content' => __( 'Post content', 'wp-seopress-pro' ),
		'body_page'    => __( 'Source code of your page (extremely slow)', 'wp-seopress-pro' ),
	);

	foreach ( $where as $key => $value ) {
		?>
<div class="seopress_wrap_single_cpt">

		<?php
		if ( isset( $options['seopress_bot_scan_settings_where'] ) ) {
			$check = $options['seopress_bot_scan_settings_where'];
		} else {
			$check = 'post_content';
		}
		?>

	<label
		for="seopress_bot_scan_settings_where_include[<?php echo esc_attr( $key ); ?>]">
		<input
			id="seopress_bot_scan_settings_where_include[<?php echo esc_attr( $key ); ?>]"
			name="seopress_bot_option_name[seopress_bot_scan_settings_where]" type="radio" <?php if ( $key === $check ) { ?>
		checked="yes"
		<?php } ?>
		value="<?php echo esc_attr( $key ); ?>"/>

		<?php echo esc_html( $value ); ?>
	</label>

		<?php
		if ( isset( $options['seopress_bot_scan_settings_where'] ) ) {
			esc_attr( $options['seopress_bot_scan_settings_where'] );
		}
		?>
</div>
		<?php
	}
}

/**
 * Bot scan settings number callback.
 *
 * @return void
 */
function seopress_bot_scan_settings_number_callback() {
	$options = get_option( 'seopress_bot_option_name' );

	$check = isset( $options['seopress_bot_scan_settings_number'] );
	?>

<input type="number" min="10" placeholder="10" name="seopress_bot_option_name[seopress_bot_scan_settings_number]" <?php if ( true === $check ) { ?>
value="<?php echo esc_attr( $options['seopress_bot_scan_settings_number'] ); ?>"
<?php } ?>
value="100"/>

	<?php
	if ( isset( $options['seopress_bot_scan_settings_number'] ) ) {
		esc_html( $options['seopress_bot_scan_settings_number'] );
	}
	?>
<p class="description">
	<?php esc_html_e( 'The higher the value, the more time it will take. Min 10. Default: 100', 'wp-seopress-pro' ); ?>
</p>

	<?php
}

/**
 * Bot scan settings type callback.
 *
 * @return void
 */
function seopress_bot_scan_settings_type_callback() {
	$options = get_option( 'seopress_bot_option_name' );

	$check = isset( $options['seopress_bot_scan_settings_type'] );
	?>

<label for="seopress_bot_scan_settings_type">
	<input id="seopress_bot_scan_settings_type" name="seopress_bot_option_name[seopress_bot_scan_settings_type]"
		type="checkbox" <?php if ( true === $check ) { ?>
	checked="yes"
	<?php } ?>
	value="1"/>

	<?php esc_html_e( 'Yes', 'wp-seopress-pro' ); ?>
</label>

	<?php
	if ( isset( $options['seopress_bot_scan_settings_type'] ) ) {
		esc_attr( $options['seopress_bot_scan_settings_type'] );
	}
}

/**
 * Bot scan settings 404 callback.
 *
 * @return void
 */
function seopress_bot_scan_settings_404_callback() {
	$options = get_option( 'seopress_bot_option_name' );

	$check = isset( $options['seopress_bot_scan_settings_404'] );
	?>

<label for="seopress_bot_scan_settings_404">
	<input id="seopress_bot_scan_settings_404" name="seopress_bot_option_name[seopress_bot_scan_settings_404]"
		type="checkbox" <?php if ( true === $check ) { ?>
	checked="yes"
	<?php } ?>
	value="1"/>

	<?php esc_html_e( 'Yes', 'wp-seopress-pro' ); ?>
</label>

	<?php
	if ( isset( $options['seopress_bot_scan_settings_404'] ) ) {
		esc_attr( $options['seopress_bot_scan_settings_404'] );
	}
}

/**
 * Bot scan settings timeout callback.
 *
 * @return void
 */
function seopress_bot_scan_settings_timeout_callback() {
	$options = get_option( 'seopress_bot_option_name' );

	$check = isset( $options['seopress_bot_scan_settings_timeout'] );
	?>

<input type="number" min="0" max="60" placeholder="5" name="seopress_bot_option_name[seopress_bot_scan_settings_timeout]" <?php if ( true === $check ) { ?>
value="<?php echo esc_attr( $options['seopress_bot_scan_settings_timeout'] ); ?>"
<?php } ?>
value="5"/>

	<?php
	if ( isset( $options['seopress_bot_scan_settings_timeout'] ) ) {
		esc_html( $options['seopress_bot_scan_settings_timeout'] );
	}
	?>

<p class="description">
	<?php esc_html_e( 'If the request exceeds x seconds of delay, the link will be considered as down', 'wp-seopress-pro' ); ?>
</p>

	<?php
}

/**
 * Bot scan settings cleaning callback.
 *
 * @return void
 */
function seopress_bot_scan_settings_cleaning_callback() {
	$options = get_option( 'seopress_bot_option_name' );

	$check = isset( $options['seopress_bot_scan_settings_cleaning'] );
	?>

<label for="seopress_bot_scan_settings_cleaning">
	<input id="seopress_bot_scan_settings_cleaning" name="seopress_bot_option_name[seopress_bot_scan_settings_cleaning]"
		type="checkbox" <?php if ( true === $check ) { ?>
	checked="yes"
	<?php } ?>
	value="1"/>

	<?php esc_html_e( 'Yes', 'wp-seopress-pro' ); ?>
</label>

	<?php
	if ( isset( $options['seopress_bot_scan_settings_cleaning'] ) ) {
		esc_attr( $options['seopress_bot_scan_settings_cleaning'] );
	}
}
