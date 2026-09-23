<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV Export (FREE)
 *
 * - Export template CSV (header only + sample row)
 * - Export current targets based on selected filters:
 *   columns: product_id, parent_id, type, sku, name, regular_price, sale_price
 *
 * Dependencies:
 * - WooCommerce (wc_get_product)
 * - WSH_WCBPM_Filter_Engine
 */
class WSH_WCBPM_CSV_Export {

	/**
	 * Init (for compatibility with plugin boot)
	 */
	public static function init() {
		// FREE: nothing needed on init.
	}

	/**
	 * Export template CSV.
	 */
	public static function export_template() {
		self::send_csv_headers( 'wsh-bulk-price-template.csv' );

		$fh = fopen( 'php://output', 'w' );
		if ( ! $fh ) {
			exit;
		}

		// Excel-friendly UTF-8 BOM
		self::write_utf8_bom( $fh );

		$header = array(
			'product_id',
			'SKU',
			'regular_price',
			'sale_price',
		);

		// Use semicolon delimiter for many EU Excel locales
		fputcsv( $fh, $header, ';' );

		// sample row
		fputcsv( $fh, array( '123', 'S12K', '99.99', '89.99' ), ';' );

		fclose( $fh );
		exit;
	}

	/**
	 * Export current targets for given filters.
	 *
	 * @param array $filters Filters same structure as UI sends.
	 */
	public static function export_current_for_targets( $filters ) {

		if ( ! function_exists( 'wc_get_product' ) ) {
			wp_die( esc_html__( 'WooCommerce is not active.', 'wsh-wcbpm' ) );
		}

		if ( ! class_exists( 'WSH_WCBPM_Filter_Engine' ) ) {
			wp_die( esc_html__( 'Filter engine is not loaded.', 'wsh-wcbpm' ) );
		}

		$filters = is_array( $filters ) ? $filters : array();

		$targets = WSH_WCBPM_Filter_Engine::find_targets( $filters );

		self::send_csv_headers( 'wsh-bulk-price-current.csv' );

		$fh = fopen( 'php://output', 'w' );
		if ( ! $fh ) {
			exit;
		}

		// Excel-friendly UTF-8 BOM
		self::write_utf8_bom( $fh );

		$header = array(
			'product_id',
			'parent_id',
			'type',
			'sku',
			'name',
			'regular_price',
			'sale_price',
		);
		fputcsv( $fh, $header, ';' );

		// Products (simple + variable parents)
		if ( ! empty( $targets['products'] ) && is_array( $targets['products'] ) ) {
			foreach ( $targets['products'] as $pid ) {
				$pid = (int) $pid;
				if ( $pid <= 0 ) {
					continue;
				}

				$p = wc_get_product( $pid );
				if ( ! $p ) {
					continue;
				}

				$row = array(
					$pid,
					0,
					$p->get_type(),
					$p->get_sku(),
					$p->get_name(),
					self::csv_price( $p->get_regular_price() ),
					self::csv_price( $p->get_sale_price() ),
				);

				fputcsv( $fh, $row, ';' );
			}
		}

		// Variations
		if ( ! empty( $targets['variations'] ) && is_array( $targets['variations'] ) ) {
			foreach ( $targets['variations'] as $v ) {
				$vid = isset( $v['variation_id'] ) ? (int) $v['variation_id'] : 0;
				$pid = isset( $v['parent_id'] ) ? (int) $v['parent_id'] : 0;

				if ( $vid <= 0 ) {
					continue;
				}

				$var = wc_get_product( $vid );
				if ( ! $var ) {
					continue;
				}

				$name = $var->get_name();
				if ( method_exists( $var, 'get_formatted_variation_attributes' ) ) {
					$attrs = $var->get_formatted_variation_attributes( true );
					if ( $attrs ) {
						$name .= ' - ' . wp_strip_all_tags( $attrs );
					}
				}

				$row = array(
					$vid,
					$pid,
					'variation',
					$var->get_sku(),
					$name,
					self::csv_price( $var->get_regular_price() ),
					self::csv_price( $var->get_sale_price() ),
				);

				fputcsv( $fh, $row, ';' );
			}
		}

		fclose( $fh );
		exit;
	}

	/**
	 * Normalize price for CSV (no locale formatting, no floats).
	 *
	 * @param mixed $price
	 * @return string
	 */
	protected static function csv_price( $price ) {
		$price = is_string( $price ) ? trim( $price ) : (string) $price;
		if ( '' === $price ) {
			return '';
		}

		// Returns a normalized decimal string using dot, with store decimals
		return wc_format_decimal( $price, wc_get_price_decimals(), false );
	}

	/**
	 * Write UTF-8 BOM for Excel compatibility.
	 *
	 * @param resource $fh
	 * @return void
	 */
	protected static function write_utf8_bom( $fh ) {
		if ( is_resource( $fh ) ) {
			// UTF-8 BOM
			fwrite( $fh, "\xEF\xBB\xBF" );
		}
	}

	/**
	 * Output headers for CSV download.
	 *
	 * @param string $filename
	 */
	protected static function send_csv_headers( $filename ) {
		if ( headers_sent() ) {
			return;
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
	}
}
