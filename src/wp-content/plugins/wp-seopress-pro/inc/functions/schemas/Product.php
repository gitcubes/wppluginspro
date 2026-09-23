<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * SEOPress PRO Product Schema.
 *
 * @package SEOPress PRO
 * @subpackage Schemas
 */

defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

/**
 * Automatic rich snippets products option.
 *
 * @param array $schema_datas The schema datas.
 * @return void
 */
function seopress_automatic_rich_snippets_products_option( $schema_datas ) {

	// If no data.
	if ( 0 != count( array_filter( $schema_datas ) ) ) {
		// Init.
		global $post;
		global $product;

		$products_name = $schema_datas['name'];
		if ( '' == $products_name ) {
			$products_name = the_title_attribute( 'echo=0' );
		}

		$products_description = $schema_datas['description'];
		if ( '' == $products_description ) {
			$products_description = wp_trim_words( esc_html( get_the_excerpt() ), 30 );
		}

		$products_img = $schema_datas['img'];
		if ( '' == $products_img && '' != get_the_post_thumbnail_url( get_the_ID(), 'large' ) ) {
			$products_img = get_the_post_thumbnail_url( get_the_ID(), 'large' );
		}

		$products_price = $schema_datas['price'];

		if ( isset( $product ) && '' == $products_price && method_exists( $product, 'get_price' ) && '' != $product->get_price() ) {
			$products_price = $product->get_price();
		}

		$products_price_valid_date = $schema_datas['price_valid_date'];

		if ( isset( $product ) && '' == $products_price_valid_date && method_exists( $product, 'get_date_on_sale_to' ) && '' != $product->get_date_on_sale_to() ) {
			$products_price_valid_date = $product->get_date_on_sale_to();
			$products_price_valid_date = $products_price_valid_date->date( 'm-d-Y' );
		} else {
			$products_price_valid_date = gmdate( 'Y-12-31', time() + YEAR_IN_SECONDS );
		}

		$products_sku = $schema_datas['sku'];
		if ( isset( $product ) && '' == $products_sku && method_exists( $product, 'get_sku' ) && '' != $product->get_sku() ) {
			$products_sku = $product->get_sku();
		}

		$products_global_ids = $schema_datas['global_ids'];

		if ( isset( $product ) && method_exists( $product, 'get_id' ) ) {
			if ( '' != get_post_meta( $product->get_id(), 'sp_wc_barcode_type_field', true ) && 'none' != get_post_meta( $product->get_id(), 'sp_wc_barcode_type_field', true ) ) {
				$products_global_ids = get_post_meta( $product->get_id(), 'sp_wc_barcode_type_field', true );
			} else {
				$products_global_ids = 'gtin';
			}
		}

		$products_global_ids_value = $schema_datas['global_ids_value'];

		if ( isset( $product ) && method_exists( $product, 'get_id' ) ) {
			if ( '' != get_post_meta( $product->get_id(), 'sp_wc_barcode_field', true ) ) {
				$products_global_ids_value = get_post_meta( $product->get_id(), 'sp_wc_barcode_field', true );
			} elseif ( method_exists( $product, 'get_global_unique_id' ) ) {
					$products_global_ids_value = $product->get_global_unique_id();
			} else {
				$products_global_ids_value = '';
			}
		}

		$products_brand = $schema_datas['brand'];

		$products_currency = $schema_datas['currency'];
		if ( '' == $products_currency && function_exists( 'get_woocommerce_currency' ) && get_woocommerce_currency() ) {
			$products_currency = get_woocommerce_currency();
		} elseif ( '' == $products_currency && function_exists( 'edd_get_currency' ) && edd_get_currency() ) {
			$products_currency = edd_get_currency();
		} elseif ( '' == $products_currency ) {
			$products_currency = 'USD';
		}

		$products_condition = $schema_datas['condition'];
		if ( '' == $products_condition ) {
			$products_condition = seopress_check_ssl() . 'schema.org/NewCondition';
		}

		$products_availability = $schema_datas['availability'];

		if ( '' == $products_availability ) {
			$products_availability = seopress_check_ssl() . 'schema.org/InStock';
		}

		$json = array(
			'@context'    => seopress_check_ssl() . 'schema.org/',
			'@type'       => 'Product',
			'name'        => $products_name,
			'image'       => $products_img,
			'description' => $products_description,
			'sku'         => $products_sku,
		);

		if ( '' != $products_global_ids && $products_global_ids != 'none' && '' != $products_global_ids_value ) {
			$json[ $products_global_ids ] = $products_global_ids_value;
		}

		// Brand.
		if ( '' != $products_brand ) {
			$json['brand'] = array(
				'@type' => 'Brand',
				'name'  => $products_brand,
			);
		}

		if ( isset( $product ) && true === comments_open( get_the_ID() ) ) { // If Reviews is true.
			// Review.
			$args = array(
				'meta_key'    => 'rating',
				'number'      => 1,
				'status'      => 'approve',
				'post_status' => 'publish',
				'parent'      => 0,
				'orderby'     => 'meta_value_num',
				'order'       => 'DESC',
				'post_id'     => get_the_ID(),
				'post_type'   => 'product',
			);

			$comments = get_comments( $args );

			if ( ! empty( $comments ) ) {
				$json['review'] = array(
					'@type'        => 'Review',
					'reviewRating' => array(
						'@type'       => 'Rating',
						'ratingValue' => get_comment_meta( $comments[0]->comment_ID, 'rating', true ),
					),
					'author'       => array(
						'@type' => 'Person',
						'name'  => get_comment_author( $comments[0]->comment_ID ),
					),
				);
			}

			// AggregateRating.
			if ( isset( $product ) && method_exists( $product, 'get_review_count' ) && $product->get_review_count() >= 1 ) {
				$json['aggregateRating'] = array(
					'@type'       => 'AggregateRating',
					'ratingValue' => $product->get_average_rating(),
					'reviewCount' => $product->get_review_count(),
				);
			}
		} elseif ( isset( $schema_datas['positive_notes'] ) || isset( $schema_datas['negative_notes'] ) ) {

			$json['review'] = array(
				'@type'  => 'Review',
				'author' => array(
					'@type' => 'Person',
					'name'  => get_the_author(),
				),

			);
			if ( ! empty( $schema_datas['positive_notes'] ) ) {
				$json['review']['positiveNotes'] = array(
					'@type'           => 'ItemList',
					'itemListElement' => array(
						'@type'    => 'ListItem',
						'position' => 1,
						'name'     => $schema_datas['positive_notes'],
					),
				);

			}

			if ( ! empty( $schema_datas['negative_notes'] ) ) {
				$json['review']['negativeNotes'] = array(
					'@type'           => 'ItemList',
					'itemListElement' => array(
						'@type'    => 'ListItem',
						'position' => 1,
						'name'     => $schema_datas['negative_notes'],
					),
				);

			}
		}

		// Variable product.
		if ( isset( $product ) && method_exists( $product, 'is_type' ) && $product->is_type( 'variable' ) ) {
			$variations = $product->get_available_variations();

			$i = 1;

			foreach ( $variations as $key => $value ) {
				$product_global_ids = $schema_datas['global_ids'];
				$product_barcode    = $schema_datas['global_ids_value'];
				$product_price      = $schema_datas['price'];
				$variation          = wc_get_product( $value['variation_id'] );

				if ( isset( $value['seopress_global_ids'] ) && ! empty( $value['seopress_global_ids'] ) ) {
					$product_global_ids = $value['seopress_global_ids'];
				} else {
					$product_global_ids = 'gtin';
				}
				if ( isset( $value['seopress_barcode'] ) && ! empty( $value['seopress_barcode'] ) ) {
					$product_barcode = $value['seopress_barcode'];
				} elseif ( isset( $variation ) && method_exists( $variation, 'get_global_unique_id' ) && '' != $variation->get_global_unique_id() ) {
					$product_barcode = $variation->get_global_unique_id();
				}

				$variation_price_valid_date = '';
				if ( isset( $variation ) && '' == $variation_price_valid_date && method_exists( $variation, 'get_date_on_sale_to' ) && '' != $variation->get_date_on_sale_to() ) {
					$variation_price_valid_date = $variation->get_date_on_sale_to();
					$variation_price_valid_date = $variation_price_valid_date->date( 'm-d-Y' );
				} elseif ( ! empty( $schema_datas['price_valid_date'] ) ) {
					try {
						$date                       = new \DateTime( $schema_datas['price_valid_date'] );
						$variation_price_valid_date = $date->format( 'm-d-Y' );
					} catch ( \Exception $e ) {
						$variation_price_valid_date = $schema_datas['price_valid_date'];
					}
				} else {
					$variation_price_valid_date = gmdate( 'Y-12-31', time() + YEAR_IN_SECONDS );
				}

				if ( ! empty( $product_global_ids ) && 'none' === $product_global_ids ) {
					if ( ! empty( $products_global_ids ) ) {
						$product_global_ids = $products_global_ids;
					} else {
						$product_global_ids = 'gtin';
					}
				}

				if ( empty( $product_barcode ) ) {
					$product_barcode = $products_global_ids_value;
				}

				$availability = sprintf( '%s%s/InStock', seopress_check_ssl(), 'schema.org' );
				if ( ! $value['is_in_stock'] ) {
					$availability = sprintf( '%s%s/OutOfStock', seopress_check_ssl(), 'schema.org' );
				}

				$sku = $schema_datas['sku'];
				if ( empty( $sku ) || 'none' === $sku || $product->get_sku() === $sku ) {
					$sku = empty( $value['sku'] ) ? $product->get_sku() : $value['sku'];
				}

				$variation_price = $product_price;
				if ( isset( $variation ) && function_exists( 'wc_get_price_including_tax' ) && function_exists( 'wc_get_price_excluding_tax' ) ) {
					if ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
						$variation_price = wc_get_price_including_tax( $variation );
					} else {
						$variation_price = wc_get_price_excluding_tax( $variation );
					}
				}

				$offer = array(
					'@type'           => 'Offer',
					'url'             => $variation->get_permalink(),
					'sku'             => $sku,
					'price'           => is_float( $variation_price ) ? number_format( $variation_price, 2, '.', '' ) : $variation_price,
					'priceCurrency'   => $products_currency,
					'itemCondition'   => $products_condition,
					'availability'    => $availability,
					'priceValidUntil' => $variation_price_valid_date,
				);

				$shipping_details = seopress_get_shipping_schema( $variation );
				if ( ! empty( $shipping_details ) ) {
					$offer['shippingDetails'] = $shipping_details;
				}

				if ( ! empty( $product_barcode ) ) {
					$offer[ $product_global_ids ] = $product_barcode;
				}

				$json['offers'][] = $offer;

				++$i;
			}
		} elseif ( '' != $products_price ) {
			$json['offers'] = array(
				'@type'           => 'Offer',
				'url'             => get_permalink(),
				'priceCurrency'   => $products_currency,
				'price'           => is_float( $products_price ) ? number_format( $products_price, 2, '.', '' ) : $products_price,
				'priceValidUntil' => $products_price_valid_date,
				'itemCondition'   => $products_condition,
				'availability'    => $products_availability,
			);

			$shipping_details = seopress_get_shipping_schema( $product );
			if ( ! empty( $shipping_details ) ) {
				$json['offers']['shippingDetails'] = $shipping_details;
			}
		}

		$json = array_filter( $json );

		$json = apply_filters( 'seopress_schemas_auto_product_json', $json );

		$json = '<script type="application/ld+json">' . wp_json_encode( $json ) . '</script>' . "\n";

		$json = apply_filters( 'seopress_schemas_auto_product_html', $json );

		echo $json;
	}
}

/**
 * Get shipping schema for a WooCommerce product.
 *
 * @param   WC_Product $wc_product The WooCommerce product.
 * @return  array       $shipping_offers  Schema
 */
function seopress_get_shipping_schema( $wc_product ) {
	if ( ! $wc_product ) {
		return array();
	}

	/**
	 * Filter to disable WooCommerce shippingDetails schema generation.
	 *
	 * @since 9.5.0
	 *
	 * @param bool       $enabled    Whether shippingDetails should be generated.
	 * @param WC_Product $wc_product The current WooCommerce product.
	 */
	$enabled = apply_filters( 'seopress_pro_wc_schema_shipping_details_enabled', true, $wc_product );
	if ( ! $enabled ) {
		return array();
	}

	if ( ! method_exists( $wc_product, 'needs_shipping' ) ) {
		return array();
	}

	$needs_shipping = $wc_product->needs_shipping();
	if ( ! $needs_shipping ) {
		return array();
	}

	$shipping_class_id = (int) $wc_product->get_shipping_class_id();
	$currency          = get_woocommerce_currency();

	// Cache computed shipping offers by shipping class (request-level).
	static $shipping_offers_cache = array();
	$cache_key                    = sprintf( '%d|%s', $shipping_class_id, (string) $currency );
	if ( isset( $shipping_offers_cache[ $cache_key ] ) ) {
		return $shipping_offers_cache[ $cache_key ];
	}

	// Persist cache if an object cache is available.
	$object_cache_key = 'wc_shipping_details_' . md5( $cache_key );
	$cached           = wp_cache_get( $object_cache_key, 'seopress_pro' );
	if ( is_array( $cached ) ) {
		$shipping_offers_cache[ $cache_key ] = $cached;
		return $cached;
	}

	// Create an offer for each rate in each zone.
	$shipping_offers = array();

	// Cache zones (and their shipping methods) per request.
	static $zones_cache = null;
	if ( null === $zones_cache ) {
		$zones_cache = WC_Shipping_Zones::get_zones();
	}

	foreach ( $zones_cache as $zone ) {
		$zone_shipping_destination = array();
		$locations                 = $zone['zone_locations'] ?? array();

		foreach ( $locations as $location ) {
			if ( 'country' === $location->type && $location->code ) {
				$zone_shipping_destination[] = array(
					'@type'          => 'DefinedRegion',
					'addressCountry' => $location->code,
				);
			}
			if ( 'postcode' === $location->type && $location->code ) {
				$zone_shipping_destination[] = array(
					'@type'      => 'DefinedRegion',
					'postalCode' => $location->code,
				);
			}
		}

		foreach ( $zone['shipping_methods'] as $method ) {
			$instance = $method->instance_settings;
			$cost     = isset( $instance['cost'] ) ? (float) $instance['cost'] : ( isset( $instance['min_amount'] ) ? (float) $instance['min_amount'] : 0 );
			if ( $shipping_class_id && isset( $instance['type'] ) && 'class' === $instance['type'] ) {
				$cost_key = 'class_cost_' . (int) $shipping_class_id;
				if ( ! empty( $instance[ $cost_key ] ) ) {
					$cost += (float) $instance[ $cost_key ];
				}
			}
			$shipping_offers[] = array(
				'@type'               => 'OfferShippingDetails',
				'shippingDestination' => $zone_shipping_destination,
				'shippingRate'        => array(
					'@type'    => 'MonetaryAmount',
					'value'    => $cost,
					'currency' => $currency,
				),
			);
		}
	}

	/**
	 * Filter the generated WooCommerce shippingDetails schema.
	 *
	 * @since 9.5.0
	 *
	 * @param array      $shipping_offers ShippingDetails schema array.
	 * @param WC_Product $wc_product      The current WooCommerce product.
	 */
	$shipping_offers = apply_filters( 'seopress_pro_wc_schema_shipping_details', $shipping_offers, $wc_product );

	$shipping_offers_cache[ $cache_key ] = $shipping_offers;
	wp_cache_set( $object_cache_key, $shipping_offers, 'seopress_pro', HOUR_IN_SECONDS );

	return $shipping_offers;
}
