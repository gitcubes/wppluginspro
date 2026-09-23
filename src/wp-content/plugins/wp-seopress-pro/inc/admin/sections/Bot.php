<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * SEOPress PRO Bot section.
 *
 * @package SEOPress PRO
 * @subpackage Sections
 */

defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

/**
 * Print section info audit.
 *
 * @return void
 */
function seopress_print_section_info_audit() {
	$running       = get_option( 'seopress_pro_site_audit_running' ) ? get_option( 'seopress_pro_site_audit_running' ) : false;
	$post_count    = get_option( 'seopress_pro_site_audit_post_count' ) ? get_option( 'seopress_pro_site_audit_post_count' ) : 0;
	$count         = get_option( 'seopress_pro_site_audit_count_posts' ) ? get_option( 'seopress_pro_site_audit_count_posts' ) : 0;
	$last_audit    = get_option( 'seopress_pro_site_audit_last_scan' ) ? wp_date( get_option( 'date_format' ) . ' - ' . get_option( 'time_format' ), get_option( 'seopress_pro_site_audit_last_scan' ) ) : esc_html( 'No audit performed to date.', 'wp-seopress-pro' );
	$scan_duration = get_option( 'seopress_pro_site_audit_scan_duration' ) ? get_option( 'seopress_pro_site_audit_scan_duration' ) : null;
	$log           = get_option( 'seopress_pro_site_audit_log' ) ? get_option( 'seopress_pro_site_audit_log' ) : esc_html( 'Currently running...', 'wp-seopress-pro' );
	$latest_offset = get_option( 'seopress_pro_site_audit_offset' ) ? get_option( 'seopress_pro_site_audit_offset' ) : 0;

	if ( $scan_duration ) {
		$scan_duration_minutes = floor( $scan_duration / 60 );
		$scan_duration_seconds = $scan_duration % 60;
	}
	?>
	<div class="sp-section-header">
		<h2>
			<?php esc_html_e( 'Site Audit', 'wp-seopress-pro' ); ?>
		</h2>
	</div>
	<p>
		<?php echo wp_kses_post( __( 'Find all content analysis in one place to solve technical SEO issues. Everytime you edit a post, page or post type with the <strong>published status</strong>, your site audit will be automatically updated here.', 'wp-seopress-pro' ) ); ?>
	</p>
	<p>
		<?php echo /* translators: %s SEO, PRO, Google Search Console settings tab */ wp_kses_post( sprintf( __( 'If you have linked your <a href="%s"><strong>Google Search Console account</strong></a>, we also display <strong>clicks and positions</strong> data associated with each URL to help you prioritize your optimizations.', 'wp-seopress-pro' ), esc_url( admin_url( 'admin.php?page=seopress-pro-page#tab=tab_seopress_inspect_url' ) ) ) ); ?>
	</p>
	<p>
		<?php echo /* translators: %s SEO, PRO, Tools, Reset settings tab */ wp_kses_post( sprintf( __( 'Run the Site Audit to update existing data. If you want to start from scratch, go to <a href="%s">Tools, Reset</a>.', 'wp-seopress-pro' ), esc_url( admin_url( 'admin.php?page=seopress-import-export#tab=tab_seopress_tool_reset' ) ) ) ); ?>
	</p>
	<p>
		<?php if ( $running === '1' ) { ?>
			<button type="button" id="seopress-cancel-site-audit" class="btn btnPrimary">
				<?php esc_html_e( 'Cancel current audit', 'wp-seopress-pro' ); ?>
			</button>
		<?php } else { ?>
			<button type="button" id="seopress-run-site-audit" class="btn btnPrimary">
				<?php esc_html_e( 'Run audit', 'wp-seopress-pro' ); ?>
			</button>
			<?php if ( $latest_offset && $latest_offset > 0 ) { ?>
				<button type="button" id="seopress-resume-site-audit" class="btn btnSecondary" data-offset="<?php echo esc_attr( $latest_offset ); ?>">
					<?php esc_html_e( 'Resume audit', 'wp-seopress-pro' ); ?>
				</button>
			<?php } ?>
		<?php } ?>
		<span class="spinner" 
		<?php
		if ( $running === '1' ) {
			echo 'style="visibility:visible;float:none;margin:0 10px 0"'; }
		?>
		></span>
	</p>

	<p class="last-audit">
		<?php /* translators: %s Last scan date */ printf( wp_kses_post( __( '<strong>Last scan:</strong> %s', 'wp-seopress-pro' ) ), esc_html( $last_audit ) ); ?>
	</p>
	<?php if ( $scan_duration ) { ?>
		<p>
			<?php printf( wp_kses_post( /* translators: %1$s duration in minutes, %2$s duration in seconds */ __( '<strong>Scan duration:</strong> %1$s minutes %2$s seconds', 'wp-seopress-pro' ) ), esc_html( $scan_duration_minutes ), esc_html( $scan_duration_seconds ) ); ?>
		</p>
	<?php } ?>

	<div id="seopress-notice-site-audit-running" class="seopress-notice" style="display:none">
		<p>
			<?php
			if ( $post_count < $count ) {
				$current = absint( $post_count );
			} else {
				$current = absint( $count );
			}
				/* translators: %1$d current posts analyzed, %2$d total posts */ printf( wp_kses_post( __( '<span id="seopress-site-audit-offset">%1$d</span> posts analyzed out of <strong>%2$d</strong>.', 'wp-seopress-pro' ) ), absint( $current ), absint( $count ) );
			?>
		</p>
		
		<p id="seopress-site-audit-log"><?php echo esc_html( $log ); ?></p>
		
		<p><em><?php esc_html_e( 'You can close this tab, the site audit will continue to run in background.', 'wp-seopress-pro' ); ?></em></p>
	</div>

	<hr>
	<h3>
		<?php esc_html_e( 'Overview', 'wp-seopress-pro' ); ?>
	</h3>

	<div class="seopress-site-health">
		<?php
			$issues        = seopress_pro_get_service( 'SiteAudit' )->countTotalIssues() ? seopress_pro_get_service( 'SiteAudit' )->countTotalIssues() : 0;
			$hidden_issues = seopress_pro_get_service( 'SiteAudit' )->countTotalIssues( '', '', 0 ) ? seopress_pro_get_service( 'SiteAudit' )->countTotalIssues( '', '', 1 ) : 0;

		if ( $issues ) {
			?>
				<div class="seopress-card-item">
					<span class="title">
						<?php esc_html_e( 'Issues detected', 'wp-seopress-pro' ); ?>
					</span>
					<span class="value">
						<?php
						echo esc_html( $issues );
						?>
						<small><?php echo absint( $hidden_issues ) . ' ' . esc_html__( 'ignored', 'wp-seopress-pro' ); ?></small>
					</span>
				</div>
				<div class="seopress-card-item seopress-error">
					<span class="title">
						<?php esc_html_e( 'High impact issues', 'wp-seopress-pro' ); ?>
					</span>
					<span class="value">
						<?php
						$issues_high = seopress_pro_get_service( 'SiteAudit' )->countTotalIssues( '', 'high' );

						echo esc_html( $issues_high );
						echo '<small>' . esc_html( round( $issues_high / $issues * 100 ) ) . '%</small>';
						?>
					</span>
				</div>
				<div class="seopress-card-item seopress-warning">
					<span class="title">
						<?php esc_html_e( 'Medium impact issues', 'wp-seopress-pro' ); ?>
					</span>
					<span class="value">
						<?php
						$issues_medium = seopress_pro_get_service( 'SiteAudit' )->countTotalIssues( '', 'medium' );
						echo esc_html( $issues_medium );

						echo '<small>' . esc_html( round( $issues_medium / $issues * 100 ) ) . '%</small>';
						?>
					</span>
				</div>
				<div class="seopress-card-item">
					<span class="title">
						<?php esc_html_e( 'Crawled URL', 'wp-seopress-pro' ); ?>
					</span>
					<span class="value">
						<?php
						$crawledURL = seopress_pro_get_service( 'SiteAudit' )->countTotalCrawledURL();

						echo esc_html( $crawledURL );
						?>
					</span>
				</div>
			<?php
		} else {
			?>
				<p>
					<?php esc_html_e( 'Run a scan to find issues.', 'wp-seopress-pro' ); ?>
				</p>
			<?php
		}
		?>
	</div>
	<?php
}

/**
 * Print section info bot.
 *
 * @return void
 */
function seopress_print_section_info_bot() {
	$docs = function_exists( 'seopress_get_docs_links' ) ? seopress_get_docs_links() : '';
	?>
	<div class="sp-section-header">
		<h2>
			<?php esc_html_e( 'Scan', 'wp-seopress-pro' ); ?>
		</h2>
	</div>
	<p><?php esc_html_e( 'The bot scans links in your content to find errors (404...). We limit this search by default to the last 100 posts/pages/custom post types.', 'wp-seopress-pro' ); ?>

	<p>
		<?php esc_html_e( 'You can increase this value in the settings tab.', 'wp-seopress-pro' ); ?>

		<a class="seopress-help" href="<?php echo esc_url( $docs['bot'] ); ?>" target="_blank">
			<?php esc_html_e( 'Check our guide', 'wp-seopress-pro' ); ?>
		</a>
		<span class="seopress-help dashicons dashicons-external"></span>
	</p>

	<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=seopress_bot' ) ); ?>" class="btn btnTertiary">
		<?php esc_html_e( 'View scan results', 'wp-seopress-pro' ); ?>
	</a>

	<?php
}

/**
 * Print section info bot settings.
 *
 * @return void
 */
function seopress_print_section_info_bot_settings() {

	?>
	<hr>

	<h3 id="seopress-bot-broken-links">
		<?php esc_attr_e( 'Broken links settings', 'wp-seopress-pro' ); ?>
	</h3>
	<?php
}

/**
 * Print section info bot site audit.
 *
 * @return void
 */
function seopress_print_section_info_bot_site_audit() {

	?>
	<div class="sp-section-header">
		<h2>
			<?php esc_html_e( 'Settings', 'wp-seopress-pro' ); ?>
		</h2>
	</div>

	<div class="seopress-sub-tabs">
		<a href="#seopress-bot-site-audit"><?php esc_attr_e( 'Site audit settings', 'wp-seopress-pro' ); ?></a> | 
		<a href="#seopress-bot-broken-links"><?php esc_attr_e( 'Broken links settings', 'wp-seopress-pro' ); ?></a>
	</div>
	
	<hr>

	<h3 id="seopress-bot-site-audit">
		<?php esc_attr_e( 'Site audit settings', 'wp-seopress-pro' ); ?>
	</h3>
	<?php
}