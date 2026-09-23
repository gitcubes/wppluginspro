<?php
namespace SEOPressPro\Services\Audit;

defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

/**
 * Site Audit Class.
 *
 * @since 7.8.0
 */
class SiteAudit {
	private $postMetaCache  = array();
	private $editLinkCache  = array();
	private $permalinkCache = array();
	private $postCache      = array();

	public function __construct() {
		// add_action('init', [$this, 'init'], 0);
	}

	public function getDataIds( $type ) {
		global $wpdb;
		$table_name  = $wpdb->prefix . 'seopress_seo_issues';
		$column_name = 'post_id';
		$issue_type  = $type ? sanitize_text_field( $type ) : '';

		$sql = $wpdb->prepare(
			"SELECT DISTINCT $column_name FROM $table_name WHERE issue_type = %s AND issue_ignore = 0",
			$issue_type
		);
		return $wpdb->get_col( $sql );
	}

	public function getDataAnalysis( $post, $type ) {
		return seopress_pro_get_service( 'SEOIssuesDatabase' )->getData( $post->ID, $columns = array( $type ) );
	}

	public function renderAnalysis( $type, $details ) {
		ob_start();
		?>
		<details class="wrap-site-audit-analysis" data-type="<?php echo esc_attr( $type ); ?>">
			<?php $this->renderSummaryAndDescription( $details, $type ); ?>
			<div class="analysis-results-placeholder">
				<p><?php esc_html_e( 'Loading', 'wp-seopress-pro' ); ?></p>
			</div>
		</details>
		<?php
		echo ob_get_clean();
	}

	public function renderSummaryAndDescription( $details, $type ) {
		if ( isset( $details['title'] ) ) {
			$issues = seopress_pro_get_service( 'SiteAudit' )->countTotalIssues( $type, '', 0 ) ? seopress_pro_get_service( 'SiteAudit' )->countTotalIssues( $type, '', 0 ) : 0;

			$hidden_issues = seopress_pro_get_service( 'SiteAudit' )->countTotalIssues( $type, '', 0 ) ? seopress_pro_get_service( 'SiteAudit' )->countTotalIssues( $type, '', 1 ) : 0;

			$count = '';
			if ( $issues || $hidden_issues ) {
				$count .= ' <span>';
				if ( $issues ) {
					$count .= $issues . ' ' . esc_html__( 'issues', 'wp-seopress-pro' );
				}
				if ( $hidden_issues ) {
					$count .= ' <span style="font-weight: normal;font-style: italic;color: var(--colorWarning);">(' . $hidden_issues . ' ' . esc_html__( 'ignored', 'wp-seopress-pro' ) . ')</span>';
				}
				$count .= '</span>';
			}

			?>
				<summary>
					<?php echo wp_kses_post( $details['title'] ) . wp_kses_post( $count ); ?>
				</summary>
				<?php
				if ( $details['desc'] ) {
					echo '<p>' . esc_html( $details['desc'] ) . '</p>';
				}
				?>
			<?php
		}
	}

	public function countTotalIssues( $type = '', $priority = '', $ignore = 0 ) {
		global $wpdb;

		$sql = 'SELECT COUNT(*) FROM `' . $wpdb->prefix . 'seopress_seo_issues`';

		$conditions = array();

		if ( ! empty( $type ) ) {
			$conditions[] = $wpdb->prepare( '`issue_type` = %s', $type );
		}

		if ( ! empty( $priority ) ) {
			$conditions[] = $wpdb->prepare( '`issue_priority` = %s', $priority );
		}

		if ( ! empty( $ignore ) ) {
			$conditions[] = $wpdb->prepare( '`issue_ignore` = %d', $ignore );
		}

		if ( ! empty( $conditions ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $conditions );
		}

		$count = $wpdb->get_var( $sql );

		return $count;
	}

	public function countTotalCrawledURL() {
		global $wpdb;

		$sql = 'SELECT COUNT(DISTINCT `post_id`) FROM `' . $wpdb->prefix . 'seopress_seo_issues`';

		$count = $wpdb->get_var( $sql );

		return $count;
	}

	public function renderAnalysisResults( $type ) {
		$ids = $this->getDataIds( $type );

		if ( ! empty( $ids ) ) {
			$numItems = 0; // Initialize item count
			?>
			<div class="site-audit-analysis-desc gr-analysis">
				<table class="seopress-site-audit-table display">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Impact', 'wp-seopress-pro' ); ?></th>
							<th><?php esc_html_e( 'Post ID', 'wp-seopress-pro' ); ?></th>
							<th><?php esc_html_e( 'Post URL', 'wp-seopress-pro' ); ?></th>
							<th><?php esc_html_e( 'Issue name', 'wp-seopress-pro' ); ?></th>
							<th><?php esc_html_e( 'Issue description', 'wp-seopress-pro' ); ?></th>
							<th><?php esc_html_e( 'Target keywords', 'wp-seopress-pro' ); ?></th>
							<?php
							if ( seopress_get_service( 'ToggleOption' )->getToggleInspectUrl() === '1' ) {
								$google_api_key = $this->getGoogleApiKey();
								if ( ! empty( $google_api_key ) ) {
									?>
									<th><?php esc_html_e( 'Position', 'wp-seopress-pro' ); ?></th>
									<th><?php esc_html_e( 'Clicks', 'wp-seopress-pro' ); ?></th>
									<?php
								}
							}
							?>
							<?php if ( $type === 'img_alt' ) { ?>
								<th><?php esc_html_e( 'Actions', 'wp-seopress-pro' ); ?></th>
							<?php } ?>
							<th><?php esc_html_e( 'Ignore?', 'wp-seopress-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
							$renderedItems = $this->renderAllAnalysisItems( $ids, $type );
							$numItems      = array_sum( array_column( $renderedItems, 'count' ) );
							echo implode( '', array_column( $renderedItems, 'html' ) );
							$this->renderAllGoodMessage( $numItems );
						?>
					</tbody>
				</table>
			</div>
			<?php
		} else {
			?>
			<div class="site-audit-analysis-desc gr-analysis">
				<p>
					<span class="dashicons dashicons-yes"></span>
					<?php esc_html_e( 'All good!', 'wp-seopress-pro' ); ?>
				</p>
			</div>
			<?php
		}
	}

	public function renderAllAnalysisItems( $ids, $type ) {
		$items = array();
		foreach ( $ids as $id ) {
			$items[] = $this->renderAnalysisItem( $id, $type );
		}
		return $items;
	}

	public function renderAnalysisItem( $id, $type ) {
		if ( ! isset( $this->editLinkCache[ $id ] ) ) {
			$this->editLinkCache[ $id ] = get_edit_post_link( $id );
		}

		if ( ! isset( $this->permalinkCache[ $id ] ) ) {
			$this->permalinkCache[ $id ] = get_permalink( $id );
		}

		$post            = $this->getPost( $id );
		$post_title      = $post->post_title ?? '';
		$post_permalink  = $this->permalinkCache[ $id ];
		$edit_link       = $this->editLinkCache[ $id ];
		$target_keywords = $this->getPostMeta( $id, '_seopress_analysis_target_kw', '<span class="site-audit-target-keyword-missing">' . __( 'Missing!', 'wp-seopress-pro' ) . '</span>' );

		$data = $this->getDataAnalysis( $post, $type );

		if ( isset( $data ) && is_array( $data ) ) {
			ob_start();
			foreach ( $data as $key => $value ) {
				$issue_id   = $value['id'] ?? 0;
				$impact     = $value['issue_priority'] ?? 0;
				$issue_type = $value['issue_type'] ?? '';
				$ignore     = $value['issue_ignore'];
				$name       = wp_kses_post( $value['issue_name'] );
				$desc       = $value['issue_desc'];
				$desc       = \SEOPressPro\Helpers\Audit\SEOIssueDesc::getIssueDescI18n( $name, $desc );

				if ( $impact !== 'good' ) {
					switch ( $impact ) {
						case 'low':
							$priority = 1;
							break;
						case 'medium':
							$priority = 2;
							break;
						case 'high':
							$priority = 3;
							break;
					}
					?>
					<tr class="gr-analysis-title">
						<td class="site-audit-item-impact" aria-hidden="true" data-sort="<?php echo esc_attr( $priority ); ?>">
							<span class="impact <?php echo esc_attr( $impact ); ?>" aria-hidden="true"></span>
						</td>
						<td class="site-audit-item-id" aria-hidden="true" data-sort="<?php echo absint( $id ); ?>">
							<?php echo esc_html( absint( $id ) ); ?>
						</td>
						<td class="site-audit-item-link" data-sort="<?php echo esc_url( $post_permalink ?: '' ); ?>">
							<a href="<?php echo esc_url( $post_permalink ?: '' ); ?>" title="<?php esc_attr_e( 'View this content in a new window', 'wp-seopress-pro' ); ?>" target="_blank">
								<?php echo esc_html( $post_permalink ?: '' ); ?>
							</a>
							<span class="dashicons dashicons-external"></span>
							<a href="<?php echo esc_url( $edit_link ?: '' ); ?>" title="<?php esc_attr_e( 'Edit this content in a new window', 'wp-seopress-pro' ); ?>" class="site-audit-edit-url button button-link" target="_blank">
								<?php esc_html_e( 'Edit', 'wp-seopress-pro' ); ?>
							</a>
						</td>
						<td class="site-audit-name" data-sort="<?php echo esc_attr( sanitize_text_field( $value['issue_name'] ) ); ?>">
							<span>
								<?php echo esc_html( \SEOPressPro\Helpers\Audit\SEOIssueName::getIssueNameI18n( $name ) ); ?>
							</span>
						</td>
						<td class="site-audit-desc" data-sort="<?php echo esc_html( wp_strip_all_tags( $desc, true ) ); ?>">
							<?php echo wp_kses_post( $desc ); ?>
						</td>
						<td class="site-audit-target-keyword" data-sort="<?php echo esc_attr( sanitize_text_field( $target_keywords ) ); ?>">
							<span>
								<?php echo wp_kses_post( $target_keywords ); ?>
							</span>
						</td>
						<?php
						if ( seopress_get_service( 'ToggleOption' )->getToggleInspectUrl() === '1' ) {
							$google_api_key = $this->getGoogleApiKey();
							if ( ! empty( $google_api_key ) ) {
								$position = $this->getPostMeta( $id, '_seopress_search_console_analysis_position', 0 ) ?: 100;
								$clicks   = $this->getPostMeta( $id, '_seopress_search_console_analysis_clicks', 0 ) ?: 0;
								?>
								<td class="site-audit-gsc-rankings" data-sort="<?php echo esc_attr( $position ); ?>" data-search="<?php echo esc_attr( $position ); ?>">
									<?php echo esc_html( round( $position, 0 ) ); ?>
								</td>
								<td class="site-audit-gsc-clicks" data-sort="<?php echo esc_attr( $clicks ); ?>" data-search="<?php echo esc_attr( $clicks ); ?>">
									<?php echo esc_html( round( $clicks, 2 ) ); ?>
								</td>
								<?php
							}
						}
						?>
						<?php if ( $issue_type === 'img_alt' ) { ?>
							<td class="site-audit-actions">
								<?php echo $this->getActions( $id, $name ); ?>
							</td>
						<?php } ?>
						<td class="site-audit-ignore" data-sort="<?php echo absint( $ignore ); ?>" data-search="<?php echo absint( $ignore ); ?>">
							<?php if ( $ignore !== 1 ) : ?>
								<button type="button" class="seopress-site-audit-ignore-issue btn btnSecondary" data-issue-post-id="<?php echo esc_attr( absint( $id ) ); ?>" data-issue-type="<?php echo esc_attr( $issue_type ); ?>">
									<?php esc_html_e( 'Ignore', 'wp-seopress-pro' ); ?>
								</button>
							<?php endif; ?>
						</td>
					</tr>
					<?php
				}
			}
			$html = ob_get_clean();
			return array(
				'count' => 1,
				'html'  => $html,
			);
		}
		return array(
			'count' => 0,
			'html'  => '',
		);
	}

	public function renderAllGoodMessage( $numItems ) {
		if ( $numItems === 0 ) {
			?>
			<p><?php esc_html_e( 'All good!', 'wp-seopress-pro' ); ?></p>
			<?php
		}
	}

	public function getPost( $id ) {
		if ( ! isset( $this->postCache[ $id ] ) ) {
			$this->postCache[ $id ] = get_post( $id );
		}
		return $this->postCache[ $id ];
	}

	public function getPostMeta( $post_id, $meta_key, $default = '' ) {
		if ( ! isset( $this->postMetaCache[ $post_id ] ) ) {
			$this->postMetaCache[ $post_id ] = get_post_meta( $post_id );
		}

		// Check if the meta key exists and if its value is not empty
		if ( isset( $this->postMetaCache[ $post_id ][ $meta_key ] ) && ! empty( $this->postMetaCache[ $post_id ][ $meta_key ][0] ) ) {
			return $this->postMetaCache[ $post_id ][ $meta_key ][0];
		}

		return $default;
	}

	public function getActions( $id, $issue_name ) {
		$issue_name = sanitize_text_field( $issue_name );
		$html       = '';

		if ( $issue_name === 'img_alt_missing' ) {
			$html = '<button type="button" class="btn btnSecondary seopress-site-audit-run-actions-img-alt" data-issue-post-id="' . esc_attr( $id ) . '">' . esc_html__( 'Generate missing alt text with AI', 'wp-seopress-pro' ) . '</button>';
		}

		return $html;
	}

	public function getGoogleApiKey() {
		static $google_api_key = null;
		if ( $google_api_key === null ) {
			$options        = get_option( 'seopress_instant_indexing_option_name' );
			$google_api_key = $options['seopress_instant_indexing_google_api_key'] ?? '';
		}
		return $google_api_key;
	}
}

$seopress_pro_site_audit = new SiteAudit();
