<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filter Engine (FREE + PRO enhancements)
 *
 * Returns target IDs for:
 * - parent products (simple + variable parents) (product_id)
 * - variations (variation_id + parent_id) if enabled
 *
 * Supported filters:
 * - categories: [term_id, ...]
 * - tags: [term_id, ...]
 * - attribute_tax: 'pa_color', 'pa_size', etc.
 * - attribute_terms: [term_id, ...]   (PRO: multi-select)
 * - attribute_term: 'red' (slug) or '123' (term_id) (legacy fallback)
 * - logic: 'and'|'or' (PRO: relation between groups)
 * - include_variable_children: 1/0
 *
 * Notes:
 * - Default behavior is AND between filter groups.
 * - If a filter group is empty, it is ignored.
 */
class WSH_WCBPM_Filter_Engine {

	/**
	 * Main method: find products for bulk operation.
	 *
	 * @param array $filters
	 * @return array {
	 *   @type array $products   List of product IDs (simple + variable parents)
	 *   @type array $variations List of arrays: [ 'variation_id' => int, 'parent_id' => int ]
	 * }
	 */
	public static function find_targets( $filters ) {

		$filters = is_array( $filters ) ? $filters : array();

		$cat_ids = isset( $filters['categories'] ) ? self::to_int_array( $filters['categories'] ) : array();
		$tag_ids = isset( $filters['tags'] ) ? self::to_int_array( $filters['tags'] ) : array();

		$attr_tax = isset( $filters['attribute_tax'] ) ? sanitize_text_field( (string) $filters['attribute_tax'] ) : '';

		// PRO: multi terms (term_id array)
		$attr_terms = array();
		if ( isset( $filters['attribute_terms'] ) ) {
			$attr_terms = self::to_int_array( $filters['attribute_terms'] );
		}

		// Legacy fallback: attribute_term (slug or ID)
		$attr_term_legacy = isset( $filters['attribute_term'] ) ? sanitize_text_field( (string) $filters['attribute_term'] ) : '';

		// PRO: AND/OR logic toggle (defaults to AND)
		$logic    = isset( $filters['logic'] ) ? sanitize_text_field( (string) $filters['logic'] ) : 'and';
		$relation = ( 'or' === strtolower( $logic ) ) ? 'OR' : 'AND';

		$include_variations = ! empty( $filters['include_variable_children'] );

		// Validate attribute taxonomy early (avoid WP warnings / invalid queries)
		if ( '' !== $attr_tax && ! taxonomy_exists( $attr_tax ) ) {
			// If taxonomy is invalid, ignore attribute filter completely (safe default)
			$attr_tax = '';
			$attr_terms = array();
			$attr_term_legacy = '';
		}

		// If terms were not provided (new UI), try legacy resolution (old UI)
		if ( '' !== $attr_tax && empty( $attr_terms ) && '' !== $attr_term_legacy ) {

			$term_id = self::resolve_term_id( $attr_tax, $attr_term_legacy );

			// If user specified attribute filter but it cannot be resolved -> no results.
			if ( $term_id <= 0 ) {
				return array(
					'products'   => array(),
					'variations' => array(),
				);
			}

			$attr_terms = array( (int) $term_id );
		}

		$args = array(
			'post_type'              => 'product',
			'post_status'            => array( 'publish', 'private' ),
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$tax_query = array();

		if ( ! empty( $cat_ids ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $cat_ids,
				'operator' => 'IN',
			);
		}

		if ( ! empty( $tag_ids ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_tag',
				'field'    => 'term_id',
				'terms'    => $tag_ids,
				'operator' => 'IN',
			);
		}

		// Attribute filter (single or multiple terms)
		if ( '' !== $attr_tax && ! empty( $attr_terms ) ) {
			$tax_query[] = array(
				'taxonomy' => $attr_tax,
				'field'    => 'term_id',
				'terms'    => $attr_terms,
				'operator' => 'IN',
			);
		}

		if ( ! empty( $tax_query ) ) {
			// If there are multiple filter groups, apply relation AND/OR
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = $relation;
			}
			$args['tax_query'] = $tax_query;
		}

		$product_ids = get_posts( $args );
$product_ids = self::to_int_array( $product_ids );

// Ako ne uključujemo varijacije, vrati samo pronađene proizvode.
if ( ! $include_variations ) {
	return array(
		'products'   => $product_ids,
		'variations' => array(),
	);
}

// Ako uključujemo varijacije:
// - simple proizvodi ostaju u products
// - variable parent proizvodi se izbacuju iz products
// - njihove varijacije idu u variations
$simple_product_ids = array();

foreach ( $product_ids as $product_id ) {
	$product = wc_get_product( $product_id );

	if ( ! $product ) {
		continue;
	}

	if ( $product->is_type( 'variable' ) ) {
		continue;
	}

	$simple_product_ids[] = (int) $product_id;
}

$variations = self::find_variations_for_parents( $product_ids );

return array(
	'products'   => $simple_product_ids,
	'variations' => $variations,
);
	}

	/**
	 * Find variations for a list of parent product IDs.
	 *
	 * @param array $parent_ids
	 * @return array List of [ 'variation_id' => int, 'parent_id' => int ]
	 */
	protected static function find_variations_for_parents( $parent_ids ) {

		$parent_ids = self::to_int_array( $parent_ids );
		if ( empty( $parent_ids ) ) {
			return array();
		}

		$args = array(
			'post_type'              => 'product_variation',
			'post_status'            => array( 'publish', 'private' ),
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'post_parent__in'        => $parent_ids,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$variation_ids = get_posts( $args );
		$variation_ids = self::to_int_array( $variation_ids );

		if ( empty( $variation_ids ) ) {
			return array();
		}

		$out = array();
		foreach ( $variation_ids as $vid ) {
			$pid = (int) wp_get_post_parent_id( $vid );
			if ( $pid > 0 ) {
				$out[] = array(
					'variation_id' => (int) $vid,
					'parent_id'    => (int) $pid,
				);
			}
		}

		return $out;
	}

	/**
	 * Resolve a term slug or term ID to a valid term_id for a taxonomy.
	 * (Legacy support)
	 *
	 * @param string $taxonomy
	 * @param string $value Slug or numeric ID
	 * @return int term_id or 0
	 */
	protected static function resolve_term_id( $taxonomy, $value ) {

		$taxonomy = (string) $taxonomy;
		$value    = trim( (string) $value );

		if ( '' === $taxonomy || '' === $value || ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}

		// Numeric term ID.
		if ( ctype_digit( $value ) ) {
			$term = get_term_by( 'id', (int) $value, $taxonomy );
			return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
		}

		// Slug.
		$term = get_term_by( 'slug', sanitize_title( $value ), $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}

		// Fallback: name.
		$term = get_term_by( 'name', $value, $taxonomy );
		return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
	}

	/**
	 * Normalize a value or array into a clean integer array.
	 *
	 * @param mixed $arr
	 * @return int[]
	 */
	protected static function to_int_array( $arr ) {

		if ( ! is_array( $arr ) ) {
			$arr = array( $arr );
		}

		$out = array();
		foreach ( $arr as $v ) {
			if ( is_string( $v ) ) {
				$v = trim( $v );
			}
			if ( '' === $v || null === $v ) {
				continue;
			}
			$out[] = (int) $v;
		}

		return array_values(
			array_unique(
				array_filter( $out, function ( $n ) {
					return $n > 0;
				} )
			)
		);
	}
}
