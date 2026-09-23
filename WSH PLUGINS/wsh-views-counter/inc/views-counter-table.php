<?php
/*
Plugin Name: WSH Views Counter Table
Description: Admin table view for WSH Views Counter plugin.
Version: 1.4.1
Author: Web Solutions Hub LLC
Author URI: https://www.websolutions.online
License: GPL2
*/

/*
 * Please do not alter this file directly in production without version control.
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// Ajax handler (located in the same folder as this file).
require_once dirname( __FILE__ ) . '/ajax.php';

if ( ! class_exists( 'WSH_Views_Counter_List_Table' ) ) :

class WSH_Views_Counter_List_Table extends WP_List_Table {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	public $table;

	public $total          = 0;
	public $total_failed   = 0;
	public $counter        = 0;
	public $counter_failed = 0;

	/**
	 * Class Constructor.
	 */
	public function __construct() {
		global $wpdb;

		parent::__construct(
			array(
				'singular' => __( 'Views Counter', 'wsh-views-counter' ),
				'plural'   => __( 'Views Counter', 'wsh-views-counter' ),
				'ajax'     => false,
			)
		);

		$this->table = $wpdb->prefix . 'wsh_views_counter';
	}

	/**
	 * Extra markup before/after the table.
	 *
	 * @param string $which top|bottom.
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' === $which ) {
			?>
			<form action="" method="get" id="items-search">
				<?php
				// Filter by report.
				$cvc_report = '';
				if ( isset( $_GET['cvc_report'] ) ) {
					$cvc_report = intval( $_GET['cvc_report'] );
				}
				?>
				<select name="cvc_report" id="search-item-id-report-select">
					<option value=""><?php esc_html_e( 'Select report', 'wsh-views-counter' ); ?></option>
					<option value="1" <?php selected( $cvc_report, 1 ); ?>><?php esc_html_e( 'Views by Post', 'wsh-views-counter' ); ?></option>
					<option value="2" <?php selected( $cvc_report, 2 ); ?>><?php esc_html_e( 'Views by Author', 'wsh-views-counter' ); ?></option>
					<option value="3" <?php selected( $cvc_report, 3 ); ?>><?php esc_html_e( 'Views by Date', 'wsh-views-counter' ); ?></option>
					<option value="4" <?php selected( $cvc_report, 4 ); ?>><?php esc_html_e( 'Views by Taxonomy', 'wsh-views-counter' ); ?></option>
				</select>

				<?php
				// Filter by period.
				$cvc_period = '';
				if ( isset( $_GET['cvc_period'] ) ) {
					$cvc_period = intval( $_GET['cvc_period'] );
				}
				?>
				<select name="cvc_period" id="search-item-id-period-select" style="margin-right: 20px;">
					<option value=""><?php esc_html_e( 'Select period', 'wsh-views-counter' ); ?></option>
					<option value="1"   <?php selected( $cvc_period, 1 ); ?>><?php esc_html_e( 'Yesterday', 'wsh-views-counter' ); ?></option>
					<option value="7"   <?php selected( $cvc_period, 7 ); ?>><?php esc_html_e( 'Last 7 days', 'wsh-views-counter' ); ?></option>
					<option value="15"  <?php selected( $cvc_period, 15 ); ?>><?php esc_html_e( 'Last 15 days', 'wsh-views-counter' ); ?></option>
					<option value="30"  <?php selected( $cvc_period, 30 ); ?>><?php esc_html_e( 'Last month', 'wsh-views-counter' ); ?></option>
					<option value="90"  <?php selected( $cvc_period, 90 ); ?>><?php esc_html_e( 'Last three months', 'wsh-views-counter' ); ?></option>
					<option value="365" <?php selected( $cvc_period, 365 ); ?>><?php esc_html_e( 'Last year', 'wsh-views-counter' ); ?></option>
				</select>

				<?php
				// Filter by post type.
				$cvc_post_type = '';
				if ( isset( $_GET['cvc_post_type'] ) ) {
					$cvc_post_type = sanitize_text_field( wp_unslash( $_GET['cvc_post_type'] ) );
				}

				// Get the list of post types from plugin settings.
				if ( class_exists( 'WSH_Views_Counter' ) ) {
					$enabled_post_types = WSH_Views_Counter::get_supported_post_types();
				} else {
					$enabled_post_types = array( 'post', 'page', 'product' );
				}

				// All public post types (to get a proper label).
				$all_post_types = get_post_types(
					array(
						'public' => true,
					),
					'objects'
				);
				?>
				<select name="cvc_post_type" id="search-item-id-post-type-select" style="margin-right: 20px;">
					<option value=""><?php esc_html_e( 'Select post type', 'wsh-views-counter' ); ?></option>

					<?php foreach ( $enabled_post_types as $pt_slug ) : ?>
						<?php
						if ( ! isset( $all_post_types[ $pt_slug ] ) ) {
							continue;
						}
						$pt_obj   = $all_post_types[ $pt_slug ];
						$pt_label = $pt_obj->labels->name;
						?>
						<option value="<?php echo esc_attr( $pt_slug ); ?>" <?php selected( $cvc_post_type, $pt_slug ); ?>>
							<?php echo esc_html( $pt_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<?php
				// WP search field (also adds its own submit button).
				$this->search_box( __( 'Search', 'wsh-views-counter' ), 'search-item-id' );
				?>

				<input type="hidden" name="page" value="<?php echo isset( $_REQUEST['page'] ) ? esc_attr( wp_unslash( $_REQUEST['page'] ) ) : ''; ?>" />

				<button type="submit" class="button button-secondary" style="margin-left: 5px; display: none;">
					<?php esc_html_e( 'Filter', 'wsh-views-counter' ); ?>
				</button>
			</form>
			<br><br><br>
			<?php
		}

		if ( 'bottom' === $which ) {
			// The code that goes after the table is here.
		}
	}

	/**
	 * Default column rendering.
	 *
	 * @param array  $item        Row item.
	 * @param string $column_name Column key.
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'ID':
			case 'post_id':
			case 'post_title':
			case 'post_author':
			case 'author':
			case 'view_date':
			case 'view_count':
			case 'is_mobile':
			case 'is_desktop':
			case 'is_logged':
			case 'published_posts':
			case 'category_name':
				return $item[ $column_name ];
			default:
				return print_r( $item, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}
	}


	/**
	 * Author column with drill-down popup link for the author report.
	 *
	 * @param array $item Row item.
	 *
	 * @return string
	 */
	public function column_author( $item ) {
		$cvc_report = isset( $_REQUEST['cvc_report'] ) ? intval( $_REQUEST['cvc_report'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 2 !== $cvc_report || empty( $item['post_author'] ) ) {
			return isset( $item['author'] ) ? $item['author'] : '';
		}

		return sprintf(
			'<a href="#" class="wsh-vc-author-details" data-author-id="%1$d" data-author-name="%2$s">%3$s</a>',
			(int) $item['post_author'],
			esc_attr( wp_strip_all_tags( $item['author'] ) ),
			esc_html( wp_strip_all_tags( $item['author'] ) )
		);
	}

	/**
	 * Category column with drill-down popup link for taxonomy report.
	 *
	 * @param array $item Row item.
	 *
	 * @return string
	 */
	public function column_category_name( $item ) {
		$cvc_report = isset( $_REQUEST['cvc_report'] ) ? intval( $_REQUEST['cvc_report'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 4 !== $cvc_report || empty( $item['term_id'] ) ) {
			return isset( $item['category_name'] ) ? $item['category_name'] : '';
		}

		return sprintf(
			'<a href="#" class="wsh-vc-taxonomy-details" data-term-id="%1$d" data-term-name="%2$s">%3$s</a>',
			(int) $item['term_id'],
			esc_attr( wp_strip_all_tags( $item['category_name'] ) ),
			esc_html( wp_strip_all_tags( $item['category_name'] ) )
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item Row item.
	 *
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			$this->_args['singular'],
			$item['ID']
		);
	}

	/**
	 * Columns definition.
	 *
	 * @return array
	 */
	public function get_columns() {

		$columns = array(
			'post_id'    => __( 'Post ID', 'wsh-views-counter' ),
			'post_title' => __( 'Post Title', 'wsh-views-counter' ),
			'author'     => __( 'Author', 'wsh-views-counter' ),
			'category_name' => __( 'Category', 'wsh-views-counter' ),
			'published_posts' => __( 'Published Posts', 'wsh-views-counter' ),
			'view_count' => __( 'Total Views', 'wsh-views-counter' ),
			'is_mobile'  => __( 'Mobile Views', 'wsh-views-counter' ),
			'is_desktop' => __( 'Desktop Views', 'wsh-views-counter' ),
			'is_logged'  => __( 'Logged Views', 'wsh-views-counter' ),
			'view_date'  => __( 'Date', 'wsh-views-counter' ),
		);

		// Update columns by report.
		$cvc_reports = array(
			1 => 'Views by Post',
			2 => 'Views by Author',
			3 => 'Views by Date',
			4 => 'Views by Taxonomy',
		);

		$cvc_report = isset( $_REQUEST['cvc_report'] ) ? intval( $_REQUEST['cvc_report'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( array_key_exists( $cvc_report, $cvc_reports ) && 1 === $cvc_report ) {
			unset( $columns['view_date'], $columns['published_posts'] );
		} elseif ( array_key_exists( $cvc_report, $cvc_reports ) && 2 === $cvc_report ) {
			unset( $columns['post_id'], $columns['post_title'], $columns['view_date'] );
		} elseif ( array_key_exists( $cvc_report, $cvc_reports ) && 3 === $cvc_report ) {
			unset( $columns['post_id'], $columns['post_title'], $columns['author'], $columns['category_name'], $columns['published_posts'] );
		} elseif ( array_key_exists( $cvc_report, $cvc_reports ) && 4 === $cvc_report ) {
			unset( $columns['post_id'], $columns['post_title'], $columns['author'], $columns['is_logged'], $columns['view_date'] );
		} else {
			unset( $columns['category_name'], $columns['published_posts'] );
		}

		return $columns;
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'ID'         => array( 'ID', false ),
			'post_id'    => array( 'post_id', true ),
			'post_type'  => array( 'post_type', true ),
			'post_title' => array( 'post_title', true ),
			'post_author'=> array( 'post_author', true ),
			'author'     => array( 'author', true ),
			'category_name' => array( 'category_name', true ),
			'view_date'  => array( 'view_date', true ),
			'view_count' => array( 'view_count', true ),
			'is_mobile'  => array( 'is_mobile', true ),
			'is_desktop' => array( 'is_desktop', true ),
			'is_logged'  => array( 'is_logged', true ),
			'published_posts' => array( 'published_posts', true ),
		);

		return $sortable_columns;
	}

	/**
	 * Bulk actions (currently empty).
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = array();
		return $actions;
	}

	/**
	 * Bulk actions processing.
	 */
	public function process_bulk_action() {

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		if ( 'delete' === $this->current_action() ) {
			if ( isset( $_REQUEST['item'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->process_delete_action( $_REQUEST['item'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		} elseif ( 'edit' === $this->current_action() ) {
			$save_item = ! empty( $_POST['save_item'] ) ? sanitize_text_field( wp_unslash( $_POST['save_item'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( isset( $save_item, $_REQUEST['item'] ) && (int) $_REQUEST['item'] === (int) $save_item ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->process_save_action( (int) $_REQUEST['item'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} elseif ( isset( $_REQUEST['item'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$this->process_edit_action( (int) $_REQUEST['item'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
	}

	/**
	 * Delete record(s).
	 *
	 * @param int|array $request_ids IDs.
	 */
	public function process_delete_action( $request_ids = 0 ) {
		global $wpdb;

		if ( ! current_user_can( 'delete_posts' ) ) {
			return;
		}

		$table = $this->table;
		$items = array();

		if ( is_array( $request_ids ) ) {
			$items = array_map( 'intval', $request_ids );
		} else {
			$items[] = intval( $request_ids );
		}

		$items = array_filter( $items );

		if ( ! empty( $items ) ) {
			$ids = implode( ',', $items );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "DELETE FROM $table WHERE ID IN ($ids)" );
		}

		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			wp_safe_redirect( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
			exit;
		}
	}

	/**
	 * Save (insert/update) a single record.
	 *
	 * @param int $request_id Record ID.
	 */
	public function process_save_action( $request_id = 0 ) {
		global $wpdb;

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$table = $this->table;
		$item  = array();

		if ( $request_id > 0 ) {
			$query = $wpdb->prepare(
				"SELECT * FROM $table WHERE ID = %d",
				$request_id
			);
			$item  = $wpdb->get_row( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		}

		$post_id    = isset( $_POST['post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_type  = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$view_date  = isset( $_POST['view_date'] ) ? sanitize_text_field( wp_unslash( $_POST['view_date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$view_count = isset( $_POST['view_count'] ) ? sanitize_text_field( wp_unslash( $_POST['view_count'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$is_mobile  = isset( $_POST['is_mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['is_mobile'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$is_desktop = isset( $_POST['is_desktop'] ) ? sanitize_text_field( wp_unslash( $_POST['is_desktop'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$is_logged  = isset( $_POST['is_logged'] ) ? sanitize_text_field( wp_unslash( $_POST['is_logged'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! empty( $view_date ) ) {
			$view_date = gmdate( 'Y-m-d', strtotime( $view_date ) );
		} else {
			$view_date = gmdate( 'Y-m-d' );
		}

		$data = array(
			'post_id'    => (int) $post_id,
			'post_type'  => $post_type,
			'view_date'  => $view_date,
			'view_count' => (int) $view_count,
			'is_mobile'  => (int) $is_mobile,
			'is_desktop' => (int) $is_desktop,
			'is_logged'  => (int) $is_logged,
		);

		if ( ! empty( $item ) ) {
			$wpdb->update( $table, $data, array( 'ID' => (int) $request_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			wp_safe_redirect( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
			exit;
		}
	}

	/**
	 * Edit screen for a single record.
	 *
	 * @param int $request_id Record ID.
	 */
	public function process_edit_action( $request_id = 0 ) {
		global $wpdb;

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$table = $this->table;
		$item  = null;

		if ( $request_id > 0 ) {
			$query = $wpdb->prepare(
				"SELECT * FROM $table WHERE ID = %d",
				$request_id
			);
			$item  = $wpdb->get_row( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		}

		$plugin_path = dirname( dirname( __FILE__ ) ) . '/';
		$plugin_url  = dirname( dirname( plugin_dir_url( __FILE__ ) ) );
		?>
		<div class="wrap">
			<link rel="stylesheet" href="<?php echo esc_url( $plugin_url . '/assets/css/admin-style.css?v=' . time() ); ?>" />

			<div id="icon-users" class="icon32"><br /></div>
			<h2><?php esc_html_e( 'Edit View Record', 'wsh-views-counter' ); ?></h2>

			<table class="item-form-table">
				<tr>
					<td class="item-form-left">
						<?php
						// Expected template file (adjust if your path is different).
						include $plugin_path . 'templates/admin/item-form.php';
						?>
					</td>
					<td class="item-form-right">
						<?php
						// Tabs template.
						include $plugin_path . 'templates/admin/tabs.php';
						?>
					</td>
				</tr>
			</table>
		</div>

		<script type="text/javascript" src="<?php echo esc_url( $plugin_url . '/assets/js/admin-script.js?v=' . time() ); ?>"></script>
		<script type="text/javascript" src="<?php echo esc_url( $plugin_url . '/assets/js/jquery.min.js' ); ?>"></script>
		<script type="text/javascript" src="<?php echo esc_url( $plugin_url . '/assets/js/jquery.validate.min.js' ); ?>"></script>
		<?php
		wp_die();
	}

	/**
	 * Prepare data for the table.
	 */
	public function prepare_items() {
		global $wpdb;

		$per_page = 150;

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();

		$table_posts = $wpdb->prefix . 'posts';
		$table_users = $wpdb->prefix . 'users';
		$table_views = $this->table;

		$search        = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$default_order = 'view_date';
		$report_start_date = '';
		$report_end_date   = '';

		// Default query.
		$query = "SELECT wv.ID, wv.post_id, wv.view_date, wv.view_count, wv.is_mobile, wv.is_desktop, wv.is_logged, wp.post_title as post_title, wp.post_author, wu.display_name as author
				  FROM $table_views as wv, $table_posts as wp, $table_users as wu";

		// Filter by report.
		$cvc_reports = array(
			1 => 'Views by Post',
			2 => 'Views by Author',
			3 => 'Views by Date',
			4 => 'Views by Taxonomy',
		);
		$cvc_report  = isset( $_REQUEST['cvc_report'] ) ? intval( $_REQUEST['cvc_report'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( array_key_exists( $cvc_report, $cvc_reports ) ) {
			$query = "SELECT wv.ID, wv.post_id, wv.view_date,
						 SUM(wv.view_count) as view_count,
						 SUM(wv.is_mobile) as is_mobile,
						 SUM(wv.is_desktop) as is_desktop,
						 SUM(wv.is_logged) as is_logged,
						 wp.post_title as post_title,
						 wp.post_author,
						 wu.display_name as author,
						 0 as term_id,
						 '' as category_name,
						  0 as published_posts
					  FROM $table_views as wv, $table_posts as wp, $table_users as wu";
			$default_order = 'view_count';
		}

		if ( 4 === $cvc_report ) {
			$table_term_relationships = $wpdb->term_relationships;
			$table_term_taxonomy     = $wpdb->term_taxonomy;
			$table_terms             = $wpdb->terms;

			$query = "SELECT MIN(wv.ID) as ID,
						 0 as post_id,
						 wv.view_date,
						 SUM(wv.view_count) as view_count,
						 SUM(wv.is_mobile) as is_mobile,
						 SUM(wv.is_desktop) as is_desktop,
						 SUM(wv.is_logged) as is_logged,
						 '' as post_title,
						 0 as post_author,
						 '' as author,
						 t.term_id as term_id,
						 t.name as category_name,
						 0 as published_posts
					  FROM $table_views as wv
					  INNER JOIN $table_posts as wp ON wv.post_id = wp.ID
					  INNER JOIN $table_term_relationships as tr ON wp.ID = tr.object_id
					  INNER JOIN $table_term_taxonomy as tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'category'
					  INNER JOIN $table_terms as t ON tt.term_id = t.term_id";
			$default_order = 'view_count';
		}

		// Search.
		$do_search = '';
		if ( ! empty( $search ) ) {
			if ( array_key_exists( $cvc_report, $cvc_reports ) && 1 === $cvc_report ) {
				// by post.
				$do_search = $wpdb->prepare(
					" WHERE wv.post_id = wp.ID AND wp.post_author = wu.ID
					  AND (CAST(wv.post_id AS CHAR) LIKE %s OR wp.post_title LIKE %s)",
					'%' . $wpdb->esc_like( $search ) . '%',
					'%' . $wpdb->esc_like( $search ) . '%'
				);
			} elseif ( array_key_exists( $cvc_report, $cvc_reports ) && 2 === $cvc_report ) {
				// by author.
				$do_search = $wpdb->prepare(
					" WHERE wv.post_id = wp.ID AND wp.post_author = wu.ID
					  AND wu.display_name LIKE %s",
					'%' . $wpdb->esc_like( $search ) . '%'
				);
			} elseif ( array_key_exists( $cvc_report, $cvc_reports ) && 3 === $cvc_report ) {
				// by date.
				$do_search = $wpdb->prepare(
					" WHERE wv.post_id = wp.ID AND wp.post_author = wu.ID
					  AND wv.view_date LIKE %s",
					'%' . $wpdb->esc_like( $search ) . '%'
				);
			} elseif ( array_key_exists( $cvc_report, $cvc_reports ) && 4 === $cvc_report ) {
				// by taxonomy/category.
				$do_search = $wpdb->prepare(
					" WHERE t.name LIKE %s",
					'%' . $wpdb->esc_like( $search ) . '%'
				);
			} else {
				// general.
				$do_search = $wpdb->prepare(
					" WHERE wv.post_id = wp.ID AND wp.post_author = wu.ID
					  AND (CAST(wv.post_id AS CHAR) LIKE %s OR wp.post_title LIKE %s OR wu.display_name LIKE %s)",
					'%' . $wpdb->esc_like( $search ) . '%',
					'%' . $wpdb->esc_like( $search ) . '%',
					'%' . $wpdb->esc_like( $search ) . '%'
				);
			}
		}

		if ( ! empty( $do_search ) ) {
			$query .= " $do_search ";
		} else {
			if ( 4 === $cvc_report ) {
				$query .= ' WHERE 1=1 ';
			} else {
				$query .= ' WHERE wv.post_id = wp.ID AND wp.post_author = wu.ID ';
			}
		}

		// Filter by period.
		$cvc_periods = array( 1, 7, 15, 30, 90, 365 );
		$cvc_period  = isset( $_REQUEST['cvc_period'] ) ? intval( $_REQUEST['cvc_period'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $cvc_period ) && in_array( $cvc_period, $cvc_periods, true ) ) {
			$end_date   = gmdate( 'Y-m-d' );
			$start_date = gmdate( 'Y-m-d', strtotime( "-$cvc_period days" ) );
			$report_start_date = $start_date;
			$report_end_date   = $end_date;

			$query .= $wpdb->prepare(
				' AND (wv.view_date BETWEEN %s AND %s) ',
				$start_date,
				$end_date
			);
		}

		// Filter by post type.
		$cvc_post_types = array( 'post', 'page', 'product' );
		if ( class_exists( 'WSH_Views_Counter' ) ) {
			$cvc_post_types = WSH_Views_Counter::get_supported_post_types();
		}

		$cvc_post_type = isset( $_REQUEST['cvc_post_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['cvc_post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $cvc_post_type ) && in_array( $cvc_post_type, $cvc_post_types, true ) ) {
			$query .= $wpdb->prepare(
				' AND (wv.post_type = %s) ',
				$cvc_post_type
			);
		}


		// Add published posts count for the author report.
		if ( array_key_exists( $cvc_report, $cvc_reports ) && 2 === $cvc_report ) {
			$published_posts_conditions = "p2.post_author = wp.post_author AND p2.post_status = 'publish'";

			if ( ! empty( $cvc_post_type ) && in_array( $cvc_post_type, $cvc_post_types, true ) ) {
				$published_posts_conditions .= $wpdb->prepare( ' AND p2.post_type = %s', $cvc_post_type );
			}

			if ( ! empty( $report_start_date ) && ! empty( $report_end_date ) ) {
				$published_posts_conditions .= $wpdb->prepare(
					' AND DATE(p2.post_date) BETWEEN %s AND %s',
					$report_start_date,
					$report_end_date
				);
			}

			$query = str_replace(
				'0 as published_posts',
				"(SELECT COUNT(DISTINCT p2.ID) FROM $table_posts as p2 WHERE $published_posts_conditions) as published_posts",
				$query
			);
		}

		// Add published posts count for the taxonomy/category report.
		if ( array_key_exists( $cvc_report, $cvc_reports ) && 4 === $cvc_report ) {
			$published_posts_conditions = "p2.ID = tr2.object_id AND tr2.term_taxonomy_id = tt2.term_taxonomy_id AND tt2.term_id = t.term_id AND tt2.taxonomy = 'category' AND p2.post_status = 'publish'";

			if ( ! empty( $cvc_post_type ) && in_array( $cvc_post_type, $cvc_post_types, true ) ) {
				$published_posts_conditions .= $wpdb->prepare( ' AND p2.post_type = %s', $cvc_post_type );
			}

			if ( ! empty( $report_start_date ) && ! empty( $report_end_date ) ) {
				$published_posts_conditions .= $wpdb->prepare(
					' AND DATE(p2.post_date) BETWEEN %s AND %s',
					$report_start_date,
					$report_end_date
				);
			}

			$query = str_replace(
				'0 as published_posts',
				"(SELECT COUNT(DISTINCT p2.ID) FROM $table_posts as p2 INNER JOIN $table_term_relationships as tr2 ON p2.ID = tr2.object_id INNER JOIN $table_term_taxonomy as tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id WHERE $published_posts_conditions) as published_posts",
				$query
			);
		}

		// Group by.
		if ( array_key_exists( $cvc_report, $cvc_reports ) && 1 === $cvc_report ) {
			$query .= ' GROUP BY wv.post_id ';
		} elseif ( array_key_exists( $cvc_report, $cvc_reports ) && 2 === $cvc_report ) {
			$query .= ' GROUP BY wp.post_author ';
		} elseif ( array_key_exists( $cvc_report, $cvc_reports ) && 3 === $cvc_report ) {
			$query .= ' GROUP BY wv.view_date ';
		} elseif ( array_key_exists( $cvc_report, $cvc_reports ) && 4 === $cvc_report ) {
			$query .= ' GROUP BY t.term_id ';
		}

		// Order.
		$sortable    = $this->get_sortable_columns();
		$orderby_req = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_req   = isset( $_REQUEST['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$orderby = $default_order;
		if ( $orderby_req && array_key_exists( $orderby_req, $sortable ) ) {
			$orderby = $orderby_req;
		}

		$order = in_array( $order_req, array( 'ASC', 'DESC' ), true ) ? $order_req : 'DESC';

		if ( ! empty( $orderby ) && ! empty( $order ) ) {
			$query .= " ORDER BY $orderby $order";
		}

		// Total items – we run COUNT(*) over a subquery to avoid loading all rows into memory.
		$count_query = "SELECT COUNT(*) FROM ( $query ) AS subquery";
		$total_items = (int) $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

		// Pagination.
		$paged = isset( $_REQUEST['paged'] ) ? intval( $_REQUEST['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $paged <= 0 ) {
			$paged = 1;
		}

		$offset          = ( $paged - 1 ) * $per_page;
		$query_with_limit = $query . ' LIMIT ' . (int) $offset . ',' . (int) $per_page;

		$results = $wpdb->get_results( $query_with_limit ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

		$data    = array();
		$counter = 0;

		if ( $results ) {
			foreach ( $results as $res ) {
				$link   = '<a href="' . esc_url( get_permalink( $res->post_id ) ) . '" target="_blank">' . esc_html( $res->post_title ) . '</a>';
				
				$published_posts_count = isset( $res->published_posts ) ? (int) $res->published_posts : 0;
				$published_posts_days  = ! empty( $cvc_period ) ? (int) $cvc_period : 1;
				$published_posts_daily = (int) $published_posts_count / max( 1, $published_posts_days );
				
				$data[] = array(
					'ID'         => (int) $res->ID,
					'post_id'    => (int) $res->post_id,
					'post_title' => $link,
					'post_author'=> (int) $res->post_author,
					'author'     => esc_html( $res->author ),
					'term_id'    => isset( $res->term_id ) ? (int) $res->term_id : 0,
					'category_name' => isset( $res->category_name ) ? esc_html( $res->category_name ) : '',
					'published_posts' => sprintf(
						'%d (%s/day)',
						$published_posts_count,
						number_format_i18n( $published_posts_daily, 0 )
					),
					'view_date'  => date_i18n( 'd.m.Y.', strtotime( $res->view_date ) ),
					'view_count' => (int) $res->view_count,
					'is_mobile'  => (int) $res->is_mobile,
					'is_desktop' => (int) $res->is_desktop,
					'is_logged'  => (int) $res->is_logged,
				);
				$counter++;
			}
		}

		$this->total   = 0;
		$this->counter = $counter;

		// Local sorting (if needed, although SQL already sorts).
		$default_order_local = $default_order;
		if ( array_key_exists( $cvc_report, $cvc_reports ) ) {
			$default_order_local = 'view_count';
		}

		$orderby_local   = ! empty( $orderby_req ) ? $orderby_req : $default_order_local;
		$order_local     = ! empty( $order_req ) ? strtolower( $order_req ) : 'desc';
		$numeric_fields  = array( 'post_id', 'post_author', 'view_count', 'is_mobile', 'is_desktop', 'is_logged', 'published_posts' );

		usort(
			$data,
			function( $a, $b ) use ( $orderby_local, $order_local, $numeric_fields ) {
				if ( in_array( $orderby_local, $numeric_fields, true ) ) {
					$val_a = (int) $a[ $orderby_local ];
					$val_b = (int) $b[ $orderby_local ];
					if ( $val_a === $val_b ) {
						$result = 0;
					} elseif ( $val_a > $val_b ) {
						$result = 1;
					} else {
						$result = -1;
					}
				} else {
					$result = strcmp( (string) $a[ $orderby_local ], (string) $b[ $orderby_local ] );
				}

				return ( 'asc' === $order_local ) ? $result : -$result;
			}
		);

		$this->items = $data;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ( $per_page > 0 ) ? ceil( $total_items / $per_page ) : 1,
			)
		);
	}
}

endif;

/**
 * Author report popup markup and JavaScript.
 */
function wsh_views_counter_author_report_popup_footer() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'toplevel_page_wsh_views_counter_reports' !== $screen->id ) {
		return;
	}

	$cvc_report = isset( $_REQUEST['cvc_report'] ) ? intval( $_REQUEST['cvc_report'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 2 !== $cvc_report ) {
		return;
	}
	?>
	<style>
		#wsh-vc-author-modal-backdrop{display:none;position:fixed;z-index:100000;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.45)}
		#wsh-vc-author-modal{display:none;position:fixed;z-index:100001;top:7%;left:50%;transform:translateX(-50%);width:min(1100px,92vw);max-height:84vh;overflow:auto;background:#fff;border:1px solid #c3c4c7;box-shadow:0 12px 40px rgba(0,0,0,.25);border-radius:6px}
		#wsh-vc-author-modal .wsh-vc-modal-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #dcdcde;background:#f6f7f7;position:sticky;top:0;z-index:1}
		#wsh-vc-author-modal .wsh-vc-modal-title{font-size:18px;font-weight:600;margin:0}
		#wsh-vc-author-modal .wsh-vc-modal-close{border:0;background:transparent;font-size:26px;line-height:1;cursor:pointer;color:#50575e}
		#wsh-vc-author-modal .wsh-vc-modal-body{padding:18px 20px 22px}
		#wsh-vc-author-modal h3{margin:18px 0 10px;font-size:15px}
		#wsh-vc-author-modal .widefat{margin-bottom:18px}
		#wsh-vc-author-modal .wsh-vc-loading{padding:25px;text-align:center;color:#646970}
	</style>
	<div id="wsh-vc-author-modal-backdrop"></div>
	<div id="wsh-vc-author-modal" role="dialog" aria-modal="true" aria-labelledby="wsh-vc-author-modal-title">
		<div class="wsh-vc-modal-header">
			<h2 id="wsh-vc-author-modal-title" class="wsh-vc-modal-title"></h2>
			<button type="button" class="wsh-vc-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wsh-views-counter' ); ?>">&times;</button>
		</div>
		<div class="wsh-vc-modal-body"><div class="wsh-vc-loading"><?php esc_html_e( 'Loading...', 'wsh-views-counter' ); ?></div></div>
	</div>
	<script>
	jQuery(function($){
		function closeModal(){ $('#wsh-vc-author-modal-backdrop,#wsh-vc-author-modal').hide(); }
		$(document).on('click','.wsh-vc-author-details',function(e){
			e.preventDefault();
			var $link=$(this);
			$('#wsh-vc-author-modal-title').text($link.data('author-name'));
			$('#wsh-vc-author-modal .wsh-vc-modal-body').html('<div class="wsh-vc-loading"><?php echo esc_js( __( 'Loading...', 'wsh-views-counter' ) ); ?></div>');
			$('#wsh-vc-author-modal-backdrop,#wsh-vc-author-modal').show();
			$.post(ajaxurl,{
				action:'wsh_views_counter_author_details',
				nonce:'<?php echo esc_js( wp_create_nonce( 'wsh_views_counter_author_details' ) ); ?>',
				author_id:$link.data('author-id'),
				cvc_period:$('#search-item-id-period-select').val() || '',
				cvc_post_type:$('#search-item-id-post-type-select').val() || ''
			}).done(function(resp){
				if(resp && resp.success){
					$('#wsh-vc-author-modal .wsh-vc-modal-body').html(resp.data.html);
				}else{
					$('#wsh-vc-author-modal .wsh-vc-modal-body').html('<p><?php echo esc_js( __( 'Unable to load author details.', 'wsh-views-counter' ) ); ?></p>');
				}
			}).fail(function(){
				$('#wsh-vc-author-modal .wsh-vc-modal-body').html('<p><?php echo esc_js( __( 'Unable to load author details.', 'wsh-views-counter' ) ); ?></p>');
			});
		});
		$(document).on('click','.wsh-vc-modal-close,#wsh-vc-author-modal-backdrop',closeModal);
		$(document).on('keyup',function(e){ if(e.key==='Escape'){ closeModal(); } });
	});
	</script>
	<?php
}
add_action( 'admin_footer', 'wsh_views_counter_author_report_popup_footer' );

/**
 * Ajax details for author report popup.
 */
function wsh_views_counter_ajax_author_details() {
	global $wpdb;

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'wsh-views-counter' ) ), 403 );
	}

	check_ajax_referer( 'wsh_views_counter_author_details', 'nonce' );

	$author_id = isset( $_POST['author_id'] ) ? absint( $_POST['author_id'] ) : 0;
	if ( ! $author_id ) {
		wp_send_json_error( array( 'message' => __( 'Missing author.', 'wsh-views-counter' ) ), 400 );
	}

	$cvc_periods = array( 1, 7, 15, 30, 90, 365 );
	$cvc_period  = isset( $_POST['cvc_period'] ) ? intval( $_POST['cvc_period'] ) : 0;
	$start_date  = '';
	$end_date    = '';
	if ( ! empty( $cvc_period ) && in_array( $cvc_period, $cvc_periods, true ) ) {
		$end_date   = gmdate( 'Y-m-d' );
		$start_date = gmdate( 'Y-m-d', strtotime( "-$cvc_period days" ) );
	}

	$cvc_post_types = array( 'post', 'page', 'product' );
	if ( class_exists( 'WSH_Views_Counter' ) ) {
		$cvc_post_types = WSH_Views_Counter::get_supported_post_types();
	}
	$cvc_post_type = isset( $_POST['cvc_post_type'] ) ? sanitize_key( wp_unslash( $_POST['cvc_post_type'] ) ) : '';
	if ( empty( $cvc_post_type ) || ! in_array( $cvc_post_type, $cvc_post_types, true ) ) {
		$cvc_post_type = 'post';
	}

	$table_views = $wpdb->prefix . 'wsh_views_counter';
	$table_posts = $wpdb->prefix . 'posts';

	$where_views = $wpdb->prepare( 'wv.post_id = wp.ID AND wp.post_author = %d AND wv.post_type = %s', $author_id, $cvc_post_type );
	if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
		$where_views .= $wpdb->prepare( ' AND wv.view_date BETWEEN %s AND %s', $start_date, $end_date );
	}

	$top_posts = $wpdb->get_results(
		"SELECT wp.ID, wp.post_title, wp.post_date, SUM(wv.view_count) as views
		 FROM $table_views as wv
		 INNER JOIN $table_posts as wp ON wv.post_id = wp.ID
		 WHERE $where_views
		 GROUP BY wp.ID
		 ORDER BY views DESC
		 LIMIT 10"
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

	$where_posts = $wpdb->prepare( "post_author = %d AND post_status = 'publish' AND post_type = %s", $author_id, $cvc_post_type );
	if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
		$where_posts .= $wpdb->prepare( ' AND DATE(post_date) BETWEEN %s AND %s', $start_date, $end_date );
	}

	$published_ids = $wpdb->get_col( "SELECT ID FROM $table_posts WHERE $where_posts" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

	$category_counts = array();
	foreach ( $published_ids as $post_id ) {
		$terms = get_the_terms( (int) $post_id, 'category' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			$category_counts[ __( 'Uncategorized', 'wsh-views-counter' ) ] = isset( $category_counts[ __( 'Uncategorized', 'wsh-views-counter' ) ] ) ? $category_counts[ __( 'Uncategorized', 'wsh-views-counter' ) ] + 1 : 1;
			continue;
		}
		foreach ( $terms as $term ) {
			$category_counts[ $term->name ] = isset( $category_counts[ $term->name ] ) ? $category_counts[ $term->name ] + 1 : 1;
		}
	}
	arsort( $category_counts );

	ob_start();
	?>
	<h3><?php esc_html_e( 'Top 10 most viewed posts for selected period', 'wsh-views-counter' ); ?></h3>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Post', 'wsh-views-counter' ); ?></th><th><?php esc_html_e( 'Publish Date', 'wsh-views-counter' ); ?></th><th><?php esc_html_e( 'Category', 'wsh-views-counter' ); ?></th><th><?php esc_html_e( 'Views', 'wsh-views-counter' ); ?></th></tr></thead>
		<tbody>
		<?php if ( ! empty( $top_posts ) ) : ?>
			<?php foreach ( $top_posts as $post ) : ?>
				<?php
				$terms = get_the_terms( (int) $post->ID, 'category' );
				$cats  = array();
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$cats[] = $term->name;
					}
				}
				?>
				<tr>
					<td><a href="<?php echo esc_url( get_permalink( (int) $post->ID ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $post->post_title ); ?></a></td>
					<td><?php echo esc_html( date_i18n( 'd.m.Y.', strtotime( $post->post_date ) ) ); ?></td>
					<td><?php echo esc_html( ! empty( $cats ) ? implode( ', ', $cats ) : __( 'Uncategorized', 'wsh-views-counter' ) ); ?></td>
					<td><?php echo esc_html( (int) $post->views ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr><td colspan="4"><?php esc_html_e( 'No views found for this author and period.', 'wsh-views-counter' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>

	<h3><?php esc_html_e( 'Published posts by category for selected period', 'wsh-views-counter' ); ?></h3>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Category Name', 'wsh-views-counter' ); ?></th><th><?php esc_html_e( 'Published Posts', 'wsh-views-counter' ); ?></th></tr></thead>
		<tbody>
		<?php if ( ! empty( $category_counts ) ) : ?>
			<?php foreach ( $category_counts as $category_name => $count ) : ?>
				<tr><td><?php echo esc_html( $category_name ); ?></td><td><?php echo esc_html( (int) $count ); ?></td></tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr><td colspan="2"><?php esc_html_e( 'No published posts found for this author and period.', 'wsh-views-counter' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>
	<?php
	$html = ob_get_clean();

	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_wsh_views_counter_author_details', 'wsh_views_counter_ajax_author_details' );

/**
 * Taxonomy report popup markup and JavaScript.
 */
function wsh_views_counter_taxonomy_report_popup_footer() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'toplevel_page_wsh_views_counter_reports' !== $screen->id ) {
		return;
	}

	$cvc_report = isset( $_REQUEST['cvc_report'] ) ? intval( $_REQUEST['cvc_report'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 4 !== $cvc_report ) {
		return;
	}
	?>
	<style>
		#wsh-vc-taxonomy-modal-backdrop{display:none;position:fixed;z-index:100000;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.45)}
		#wsh-vc-taxonomy-modal{display:none;position:fixed;z-index:100001;top:7%;left:50%;transform:translateX(-50%);width:min(1100px,92vw);max-height:84vh;overflow:auto;background:#fff;border:1px solid #c3c4c7;box-shadow:0 12px 40px rgba(0,0,0,.25);border-radius:6px}
		#wsh-vc-taxonomy-modal .wsh-vc-modal-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #dcdcde;background:#f6f7f7;position:sticky;top:0;z-index:1}
		#wsh-vc-taxonomy-modal .wsh-vc-modal-title{font-size:18px;font-weight:600;margin:0}
		#wsh-vc-taxonomy-modal .wsh-vc-modal-close{border:0;background:transparent;font-size:26px;line-height:1;cursor:pointer;color:#50575e}
		#wsh-vc-taxonomy-modal .wsh-vc-modal-body{padding:18px 20px 22px}
		#wsh-vc-taxonomy-modal h3{margin:18px 0 10px;font-size:15px}
		#wsh-vc-taxonomy-modal .widefat{margin-bottom:18px}
		#wsh-vc-taxonomy-modal .wsh-vc-loading{padding:25px;text-align:center;color:#646970}
	</style>
	<div id="wsh-vc-taxonomy-modal-backdrop"></div>
	<div id="wsh-vc-taxonomy-modal" role="dialog" aria-modal="true" aria-labelledby="wsh-vc-taxonomy-modal-title">
		<div class="wsh-vc-modal-header">
			<h2 id="wsh-vc-taxonomy-modal-title" class="wsh-vc-modal-title"></h2>
			<button type="button" class="wsh-vc-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wsh-views-counter' ); ?>">&times;</button>
		</div>
		<div class="wsh-vc-modal-body"><div class="wsh-vc-loading"><?php esc_html_e( 'Loading...', 'wsh-views-counter' ); ?></div></div>
	</div>
	<script>
	jQuery(function($){
		function closeTaxonomyModal(){ $('#wsh-vc-taxonomy-modal-backdrop,#wsh-vc-taxonomy-modal').hide(); }
		$(document).on('click','.wsh-vc-taxonomy-details',function(e){
			e.preventDefault();
			var $link=$(this);
			$('#wsh-vc-taxonomy-modal-title').text($link.data('term-name'));
			$('#wsh-vc-taxonomy-modal .wsh-vc-modal-body').html('<div class="wsh-vc-loading"><?php echo esc_js( __( 'Loading...', 'wsh-views-counter' ) ); ?></div>');
			$('#wsh-vc-taxonomy-modal-backdrop,#wsh-vc-taxonomy-modal').show();
			$.post(ajaxurl,{
				action:'wsh_views_counter_taxonomy_details',
				nonce:'<?php echo esc_js( wp_create_nonce( 'wsh_views_counter_taxonomy_details' ) ); ?>',
				term_id:$link.data('term-id'),
				cvc_period:$('#search-item-id-period-select').val() || '',
				cvc_post_type:$('#search-item-id-post-type-select').val() || ''
			}).done(function(resp){
				if(resp && resp.success){
					$('#wsh-vc-taxonomy-modal .wsh-vc-modal-body').html(resp.data.html);
				}else{
					$('#wsh-vc-taxonomy-modal .wsh-vc-modal-body').html('<p><?php echo esc_js( __( 'Unable to load category details.', 'wsh-views-counter' ) ); ?></p>');
				}
			}).fail(function(){
				$('#wsh-vc-taxonomy-modal .wsh-vc-modal-body').html('<p><?php echo esc_js( __( 'Unable to load category details.', 'wsh-views-counter' ) ); ?></p>');
			});
		});
		$(document).on('click','.wsh-vc-modal-close,#wsh-vc-taxonomy-modal-backdrop',closeTaxonomyModal);
		$(document).on('keyup',function(e){ if(e.key==='Escape'){ closeTaxonomyModal(); } });
	});
	</script>
	<?php
}
add_action( 'admin_footer', 'wsh_views_counter_taxonomy_report_popup_footer' );

/**
 * Ajax details for taxonomy/category report popup.
 */
function wsh_views_counter_ajax_taxonomy_details() {
	global $wpdb;

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Not allowed.', 'wsh-views-counter' ) ), 403 );
	}

	check_ajax_referer( 'wsh_views_counter_taxonomy_details', 'nonce' );

	$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
	if ( ! $term_id ) {
		wp_send_json_error( array( 'message' => __( 'Missing category.', 'wsh-views-counter' ) ), 400 );
	}

	$cvc_periods = array( 1, 7, 15, 30, 90, 365 );
	$cvc_period  = isset( $_POST['cvc_period'] ) ? intval( $_POST['cvc_period'] ) : 0;
	$start_date  = '';
	$end_date    = '';
	if ( ! empty( $cvc_period ) && in_array( $cvc_period, $cvc_periods, true ) ) {
		$end_date   = gmdate( 'Y-m-d' );
		$start_date = gmdate( 'Y-m-d', strtotime( "-$cvc_period days" ) );
	}

	$cvc_post_types = array( 'post', 'page', 'product' );
	if ( class_exists( 'WSH_Views_Counter' ) ) {
		$cvc_post_types = WSH_Views_Counter::get_supported_post_types();
	}
	$cvc_post_type = isset( $_POST['cvc_post_type'] ) ? sanitize_key( wp_unslash( $_POST['cvc_post_type'] ) ) : '';
	if ( empty( $cvc_post_type ) || ! in_array( $cvc_post_type, $cvc_post_types, true ) ) {
		$cvc_post_type = 'post';
	}

	$table_views = $wpdb->prefix . 'wsh_views_counter';
	$table_posts = $wpdb->prefix . 'posts';

	$where_views = $wpdb->prepare( 'wv.post_id = wp.ID AND wv.post_type = %s', $cvc_post_type );
	if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
		$where_views .= $wpdb->prepare( ' AND wv.view_date BETWEEN %s AND %s', $start_date, $end_date );
	}

	$top_posts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT wp.ID, wp.post_title, wp.post_date, SUM(wv.view_count) as views
			 FROM $table_views as wv
			 INNER JOIN $table_posts as wp ON wv.post_id = wp.ID
			 INNER JOIN $wpdb->term_relationships as tr ON wp.ID = tr.object_id
			 INNER JOIN $wpdb->term_taxonomy as tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			 WHERE $where_views AND tt.taxonomy = 'category' AND tt.term_id = %d
			 GROUP BY wp.ID
			 ORDER BY views DESC
			 LIMIT 10",
			$term_id
		)
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

	ob_start();
	?>
	<h3><?php esc_html_e( 'Top 10 most viewed posts for selected category and period', 'wsh-views-counter' ); ?></h3>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Post', 'wsh-views-counter' ); ?></th><th><?php esc_html_e( 'Publish Date', 'wsh-views-counter' ); ?></th><th><?php esc_html_e( 'Category', 'wsh-views-counter' ); ?></th><th><?php esc_html_e( 'Views', 'wsh-views-counter' ); ?></th></tr></thead>
		<tbody>
		<?php if ( ! empty( $top_posts ) ) : ?>
			<?php foreach ( $top_posts as $post ) : ?>
				<?php
				$terms = get_the_terms( (int) $post->ID, 'category' );
				$cats  = array();
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$cats[] = $term->name;
					}
				}
				?>
				<tr>
					<td><a href="<?php echo esc_url( get_permalink( (int) $post->ID ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $post->post_title ); ?></a></td>
					<td><?php echo esc_html( date_i18n( 'd.m.Y.', strtotime( $post->post_date ) ) ); ?></td>
					<td><?php echo esc_html( ! empty( $cats ) ? implode( ', ', $cats ) : __( 'Uncategorized', 'wsh-views-counter' ) ); ?></td>
					<td><?php echo esc_html( (int) $post->views ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr><td colspan="4"><?php esc_html_e( 'No views found for this category and period.', 'wsh-views-counter' ); ?></td></tr>
		<?php endif; ?>
		</tbody>
	</table>
	<?php
	$html = ob_get_clean();

	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_wsh_views_counter_taxonomy_details', 'wsh_views_counter_ajax_taxonomy_details' );

