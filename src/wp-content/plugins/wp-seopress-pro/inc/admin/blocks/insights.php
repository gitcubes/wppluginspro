<?php
/**
 * SEOPress PRO Insights block.
 *
 * @package SEOPress PRO
 * @subpackage Blocks
 */

defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

/**
 * Display the insights block.
 *
 * @param string $current_tab The current tab.
 * @return void
 */
function seopress_pro_dashboard_insights( $current_tab ) {
	if ( defined( 'SEOPRESS_WL_ADMIN_HEADER' ) && SEOPRESS_WL_ADMIN_HEADER === false ) {
		// Do nothing.
	} else {
		$hide_site_overview = method_exists( seopress_get_service( 'AdvancedOption' ), 'getAppearanceHideSiteOverview' ) ? seopress_get_service( 'AdvancedOption' )->getAppearanceHideSiteOverview() : '';
		$class              = '1' !== $hide_site_overview ? ' is-active' : '';
		?>
<div class="seopress-dashboard-column">
	<div id="notice-insights-alert"
		class="seopress-card<?php echo esc_attr( $class ); ?>"
		style="display: none">
		<div class="seopress-card-title">
			<h2><?php esc_html_e( 'Site overview', 'wp-seopress-pro' ); ?>
			</h2>
		</div>
		<div class="seopress-card-content">
			<div id="seopress-admin-tabs" class="wrap">
				<?php
					$dashboard_settings_tabs = array(
						'tab_seopress_analytics' => esc_html__( 'Google Analytics', 'wp-seopress-pro' ),
						'tab_seopress_matomo'    => esc_html__( 'Matomo Analytics', 'wp-seopress-pro' ),
						'tab_seopress_ps'        => esc_html__( 'PageSpeed', 'wp-seopress-pro' ),
						'tab_seopress_gsc'       => esc_html__( 'Search Console', 'wp-seopress-pro' ),
					);

					// Google Analytics.
					if ( seopress_get_toggle_option( 'google-analytics' ) !== '1' || seopress_pro_get_service( 'GoogleAnalyticsWidgetsOptionPro' )->getGA4DashboardWidget() === '1' ) {
						unset( $dashboard_settings_tabs['tab_seopress_analytics'] );
					}

					// Matomo Analytics.
					if ( seopress_get_toggle_option( 'google-analytics' ) !== '1' || seopress_pro_get_service( 'GoogleAnalyticsWidgetsOptionPro' )->getMatomoDashboardWidget() === '1' ) {
						unset( $dashboard_settings_tabs['tab_seopress_matomo'] );
					}

					$matomo_id = seopress_get_service( 'GoogleAnalyticsOption' )->getMatomoId() ? seopress_get_service( 'GoogleAnalyticsOption' )->getMatomoId() : null;
					if ( empty( $matomo_id ) ) {
						unset( $dashboard_settings_tabs['tab_seopress_matomo'] );
					}

					// Get Google API Key.
					$options        = get_option( 'seopress_instant_indexing_option_name' );
					$google_api_key = isset( $options['seopress_instant_indexing_google_api_key'] ) ? $options['seopress_instant_indexing_google_api_key'] : '';

					if ( seopress_get_toggle_option( 'inspect-url' ) !== '1' ) {
						unset( $dashboard_settings_tabs['tab_seopress_gsc'] );
					}

					$dashboard_settings_tabs = apply_filters( 'seopress_dashboard_site_overview_tabs', $dashboard_settings_tabs );
					?>

				<div class="nav-tab-wrapper">
					<?php foreach ( $dashboard_settings_tabs as $tab_key => $tab_caption ) { ?>
					<a id="<?php echo esc_attr( $tab_key ); ?>-tab" class="nav-tab"
						href="?page=seopress-option#tab=<?php echo esc_attr( $tab_key ); ?>"><?php echo esc_html( $tab_caption ); ?></a>
					<?php } ?>
				</div>

				<?php
				$seopress_page_speed_results         = array();
				$seopress_page_speed_results         = json_decode( get_transient( 'seopress_results_page_speed' ), true );
				$seopress_page_speed_desktop_results = array();
				$seopress_page_speed_desktop_results = json_decode( get_transient( 'seopress_results_page_speed_desktop' ), true );
				$cwv_svg                             = '<svg enable-background="new 0 0 24 24" focusable="false" height="15" viewBox="0 0 24 24" width="15" style="fill:#06f;vertical-align:middle"><g><g><path d="M0,0h24v24H0V0z" fill="none"></path></g></g><g><path d="M17,3H7C5.9,3,5,3.9,5,5v16l7-3l7,3V5C19,3.9,18.1,3,17,3z"></path></g></svg>';

				$fetch_time = '';

				$ps_score              = '';
				$core_web_vitals_score = '';
				if ( ! empty( $seopress_page_speed_results ) ) {
					$ps_score              = seopress_pro_get_ps_score( $seopress_page_speed_results, true );
					$ps_score_desktop      = seopress_pro_get_ps_score( $seopress_page_speed_desktop_results );
					$core_web_vitals_score = seopress_pro_get_cwv_score( $seopress_page_speed_results );
				}
				?>
				<div class="wrap-seopress-tab-content">
					<?php if ( seopress_get_toggle_option( 'google-analytics' ) ) { ?>
						<div id="tab_seopress_analytics" class="seopress-tab seopress-analytics 
						<?php
						if ( 'tab_seopress_analytics' === $current_tab ) {
							echo 'active'; }
						?>
						">
							<?php
							if ( '1' !== seopress_pro_get_service( 'GoogleAnalyticsWidgetsOptionPro' )->getGA4DashboardWidget() ) {
								if ( function_exists( 'seopress_ga_dashboard_widget_display' ) ) {
									seopress_ga_dashboard_widget_display();
								}
							}
							?>
						</div>
					<?php } ?>

					<?php if ( seopress_get_toggle_option( 'google-analytics' ) ) { ?>
						<div id="tab_seopress_matomo" class="seopress-tab seopress-analytics 
						<?php
						if ( 'tab_seopress_matomo' === $current_tab ) {
							echo 'active'; }
						?>
						">
							<?php
							if ( '1' !== seopress_pro_get_service( 'GoogleAnalyticsWidgetsOptionPro' )->getMatomoDashboardWidget() && ! empty( $matomo_id ) ) {
								if ( function_exists( 'seopress_matomo_dashboard_widget_display' ) ) {
									seopress_matomo_dashboard_widget_display();
								}
							}
							?>
						</div>
					<?php } ?>

					<div id="tab_seopress_ps" class="seopress-tab seopress-page-speed inside
					<?php
					if ( 'tab_seopress_ps' === $current_tab ) {
						echo 'active'; }
					?>
					">
						<div class="seopress-tools-card">
							<h3>
								<?php esc_html_e( 'Google Page Speed Score', 'wp-seopress-pro' ); ?>
							</h3>

							<p>
								<?php esc_html_e( 'Learn how your site has performed, based on data from your actual users around the world.', 'wp-seopress-pro' ); ?>
							</p>

							<?php if ( $ps_score && $ps_score_desktop ) { ?>
								<div class="seopress-cwv seopress-summary-item-data">
									<?php echo $ps_score; ?>
									<?php echo $ps_score_desktop; ?>
									<p class="wrap-scale">
										<span><span class="score red"></span>0-49</span><span><span class="score yellow"></span>50-89</span><span><span class="score green"></span>90-100</span>
									</p>
								</div>
								<div class="seopress-cwv">
									<?php if ( true === $core_web_vitals_score ) { ?>
									<img src="<?php echo esc_url( SEOPRESS_PRO_ASSETS_DIR . '/img/cwv-pass.svg' ); ?>"
										alt='' width='96' height='96' />
									<?php } else { ?>
									<img src="<?php echo esc_url( SEOPRESS_PRO_ASSETS_DIR . '/img/cwv-fail.svg' ); ?>"
										alt='' width='96' height='96' />
									<?php } ?>
									<div>
										<h3>
											<?php esc_html_e( 'Core Web Vitals Assessment: ', 'wp-seopress-pro' ); ?>

											<?php if ( true === $core_web_vitals_score ) { ?>
											<span
												class="green"><?php esc_html_e( 'Passed', 'wp-seopress-pro' ); ?></span>
											<?php } elseif ( null === $core_web_vitals_score ) { ?>
											<span
												class="red"><?php esc_html_e( 'No data found', 'wp-seopress-pro' ); ?></span>
											<?php } else { ?>
											<span
												class="red"><?php esc_html_e( 'Failed', 'wp-seopress-pro' ); ?></span>
											<?php } ?>
										</h3>
										<p>
											<?php
												/* translators: %s outputs an SVG icon */
												printf( esc_html__( 'Computed from the %s Core Web Vitals metrics over the latest 28-day collection period.', 'wp-seopress-pro' ), $cwv_svg );
											?>
										</p>
									</div>
								</div>
							<?php } else { ?>
								<p>
									<?php esc_html_e( 'No data available.', 'wp-seopress-pro' ); ?>
								</p>
								<span class="dashicons dashicons-performance seopress-bg-icon"></span>
							<?php } ?>
							<p>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=seopress-pro-page#tab=tab_seopress_page_speed' ) ); ?>"
									class="seopress-btn">
									<?php esc_html_e( 'See full report', 'wp-seopress-pro' ); ?>
								</a>
							</p>
						</div>
					</div>

					<?php
					if ( seopress_get_toggle_option( 'inspect-url' ) ) {
						?>
						<div id="tab_seopress_gsc" class="seopress-tab seopress-gsc inside
						<?php
						if ( 'tab_seopress_gsc' === $current_tab ) {
							echo 'active'; }
						?>
						">
							<div class="seopress-tools-card">
							<?php
							if ( empty( $google_api_key ) ) {
								global $pagenow;
								?>
										<p>
									<?php esc_html_e( 'You need to login to Google Search Console to get your data.', 'wp-seopress-pro' ); ?>
										</p>

										<p>
											<a class="
											<?php
											if ( 'index.php' === $pagenow ) {
												echo 'button';
											} else {
												echo 'seopress-btn';
											}
											?>
											" href="<?php echo esc_url( admin_url( 'admin.php?page=seopress-pro-page#tab=tab_seopress_inspect_url' ) ); ?>">
										<?php esc_html_e( 'Authenticate', 'wp-seopress-pro' ); ?>
											</a>
										</p>
										<span class="dashicons dashicons-google seopress-bg-icon"></span>
								<?php
							} elseif ( ! empty( $google_api_key ) ) {
								$date_range = seopress_pro_get_service( 'OptionPro' )->getGSCDateRange() ? seopress_pro_get_service( 'OptionPro' )->getGSCDateRange() : '- 3 months';

								$keywords = seopress_pro_get_service( 'SearchConsole' )->getKeywords();

								if ( ! empty( $keywords ) ) {
									?>
											<div class="seopress-card-title">
												<div>
													<span class="dashicons dashicons-google"></span>
												</div>
												<div>
													<h3>
													<?php esc_html_e( 'Most searched queries', 'wp-seopress-pro' ); ?>
													</h3>
													<p>
													<?php printf( esc_html__( 'How visitors find your site on Google', 'wp-seopress-pro' ), esc_html( $date_range ) ); ?>
													</p>
												</div>
											</div>

											<div class="seopress-card-content">
												<ol>
												<?php
												if ( ! empty( $keywords['data'] ) ) {
													foreach ( $keywords['data'] as $keyword ) {
														?>
																<li>
																	<div class="gsc-item">
																		<div>
																			<div class="seopress-summary-item-data">
																			<?php echo esc_html( $keyword['keyword'] ); ?>
																			</div>
																			<div class="seopress-summary-item-label">
																			<?php
																			/* translators: %s keyword position, eg: 12 */ printf( esc_html__( 'Avg. position:  %s', 'wp-seopress-pro' ), esc_html( round( $keyword['position'], 1 ) ) );

																			if ( $keyword['position'] <= 5 ) {
																				?>
																					<span class="top-results">
																					<?php esc_html_e( 'Top 5 results', 'wp-seopress-pro' ); ?>
																					</span>
																				<?php
																			}
																			?>
																			</div>
																		</div>
																		<div>
																			<div class="seopress-summary-item">
																				<div class="seopress-summary-item-label">
																				<?php esc_html_e( ' Clicks', 'wp-seopress-pro' ); ?>
																				</div>
																				<div class="seopress-summary-item-data">
																				<?php echo esc_html( $keyword['clicks'] ); ?>
																				</div>
																			</div>
																		</div>
																	</div>
																</li>
															<?php
													}
												}
												?>
												</ol>
											</div>
										<?php } ?>

										<div class="seopress-card-title">
											<div>
												<span class="dashicons dashicons-awards"></span>
											</div>
											<div>
												<h3>
													<?php esc_html_e( 'Your most popular content', 'wp-seopress-pro' ); ?>
												</h3>
												<p>
													<?php
													/* translators: %s date range, eg: - 3 months */
													printf( esc_html__( 'By clicks in the past %s', 'wp-seopress-pro' ), esc_html( $date_range ) );
													?>
												</p>
											</div>
										</div>

										<?php
										global $wpdb;

										// Define the meta key.
										$meta_key = '_seopress_search_console_analysis_clicks';

										// Query the database to get the post IDs.
										$results = $wpdb->get_results(
											$wpdb->prepare(
												"SELECT post_id, meta_value
                                                            FROM $wpdb->postmeta
                                                            WHERE meta_key = %s
                                                            ORDER BY CAST(meta_value AS UNSIGNED) DESC
                                                            LIMIT %d",
												$meta_key,
												5
											)
										);

										// Store the post IDs in an array.
										$post_ids = array();
										if ( ! empty( $results ) ) {
											foreach ( $results as $result ) {
												$post_ids[] = $result->post_id;
											}
										}

										// Output the post IDs.
										if ( ! empty( $post_ids ) ) {
											?>
												<div class="seopress-card-content">
													<ol>
														<?php
														foreach ( $post_ids as $post_id ) {
															?>
																<li>
																	<div class="gsc-item">
																		<div>
																			<a href="<?php echo esc_url( get_the_permalink( $post_id ) ); ?>" title="<?php esc_html_e( 'Open in a new tab', 'wp-seopress-pro' ); ?>" target="_blank">
																				<?php echo esc_html( get_the_title( $post_id ) ); ?>
																			</a>
																		</div>
																		<div>
																			<div class="seopress-summary-item">
																				<div class="seopress-summary-item-label">
																					<?php esc_html_e( ' Clicks', 'wp-seopress-pro' ); ?>
																				</div>
																				<div class="seopress-summary-item-data">
																					<?php echo esc_html( get_post_meta( $post_id, '_seopress_search_console_analysis_clicks', true ) ); ?>
																				</div>
																			</div>
																		</div>
																	</div>
																</li>
															<?php
														}
														?>
													</ol>
												</div>
											<?php
										}
							}
							?>
							</div>
						</div>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
	</div>
		<?php
	}
}
add_action( 'seopress_dashboard_insights', 'seopress_pro_dashboard_insights' );
?>
