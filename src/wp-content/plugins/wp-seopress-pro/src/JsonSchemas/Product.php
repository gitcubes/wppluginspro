<?php

namespace SEOPressPro\JsonSchemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SEOPress\Helpers\RichSnippetType;
use SEOPress\Models\GetJsonData;
use SEOPressPro\Models\JsonSchemaValue;

class Product extends JsonSchemaValue implements GetJsonData {
	const NAME = 'product';

	const ALIAS = array( 'products' );

	protected function getName() {
		return self::NAME;
	}

	/**
	 * @since 4.7.0
	 *
	 * @return array
	 */
	protected function getKeysForSchemaManual() {
		return array(
			'type'               => '_seopress_pro_rich_snippets_type',
			'name'               => array(
				'value'   => '_seopress_pro_rich_snippets_product_name',
				'default' => '%%post_title%%',
			),
			'description'        => array(
				'value'   => '_seopress_pro_rich_snippets_product_description',
				'default' => '%%post_excerpt%%',
			),
			'image'              => array(
				'value'   => '_seopress_pro_rich_snippets_product_img',
				'default' => '%%post_thumbnail_url%%',
			),
			'price'              => array(
				'value'   => '_seopress_pro_rich_snippets_product_price',
				'default' => '%%wc_get_price%%',
			),
			'priceValidDate'     => array(
				'value'   => '_seopress_pro_rich_snippets_product_price_valid_date',
				'default' => '%%wc_price_valid_date%%',
			),
			'sku'                => array(
				'value'   => '_seopress_pro_rich_snippets_product_sku',
				'default' => '%%wc_sku%%',
			),
			'brand'              => '_seopress_pro_rich_snippets_product_brand',
			'globalIds'          => '_seopress_pro_rich_snippets_product_global_ids',
			'globalIdsValue'     => '_seopress_pro_rich_snippets_product_global_ids_value',
			'priceCurrency'      => '_seopress_pro_rich_snippets_product_price_currency',
			'condition'          => array(
				'value'   => '_seopress_pro_rich_snippets_product_condition',
				'default' => 'NewCondition',
			),
			'availability'       => '_seopress_pro_rich_snippets_product_availability',
			'positiveNotes'      => '_seopress_pro_rich_snippets_product_positive_notes',
			'negativeNotes'      => '_seopress_pro_rich_snippets_product_negative_notes',
			'energy_consumption' => '_seopress_pro_rich_snippets_product_energy_consumption',
		);
	}

	/**
	 * @since 4.6.0
	 *
	 * @return array
	 *
	 * @param array $keys
	 * @param array $data
	 */
	protected function getVariablesByKeysAndData( $keys, $data = array() ) {
		$variables = parent::getVariablesByKeysAndData( $keys, $data );

		if ( 'none' === $variables['globalIds'] ) {
			$variables['globalIds'] = '';
		}
		if ( 'none' === $variables['priceCurrency'] ) {
			$variables['priceCurrency'] = '';
		}

		return $variables;
	}

	/**
	 * @since 4.6.0
	 *
	 * @param array $context
	 *
	 * @return array
	 */
	public function getJsonData( $context = null ) {
		$data = $this->getArrayJson();

		$typeSchema = isset( $context['type'] ) ? $context['type'] : RichSnippetType::MANUAL;

		$variables = $this->getVariablesByType( $typeSchema, $context );

		if ( isset( $variables['globalIds'], $variables['globalIdsValue'] ) && ! empty( $variables['globalIds'] ) && ! empty( $variables['globalIdsValue'] ) ) {
			$data[ $variables['globalIds'] ] = $variables['globalIdsValue'];
		}

		if ( isset( $variables['brand'], $context['post']->ID ) && ! empty( $variables['brand'] ) ) {
			$term_list = wp_get_post_terms( $context['post']->ID, $variables['brand'], array( 'fields' => 'names' ) );

			if ( ! empty( $term_list ) && ! is_wp_error( $term_list ) ) {
				$variables['brand'] = $term_list[0];
			} else {
				unset( $variables['brand'] );
			}
		}

		// brand
		if ( isset( $variables['brand'] ) ) {
			$contextWithVariables              = $context;
			$contextWithVariables['variables'] = array(
				'name' => $variables['brand'],
			);
			$contextWithVariables['type']      = RichSnippetType::SUB_TYPE;
			$schema                            = seopress_get_service( 'JsonSchemaGenerator' )->getJsonFromSchema( Brand::NAME, $contextWithVariables, array( 'remove_empty' => true ) );
			if ( count( $schema ) > 1 ) {
				$data['brand'] = $schema;
			}
		}

		if ( isset( $variables['energy_consumption'] ) ) {
			$data['hasEnergyConsumptionDetails'] = array(
				'@type'                       => 'EnergyConsumptionDetails',
				'hasEnergyEfficiencyCategory' => $variables['energy_consumption'],
				'energyEfficiencyScaleMin'    => 'https://schema.org/EUEnergyEfficiencyCategoryG',
				'energyEfficiencyScaleMax'    => 'https://schema.org/EUEnergyEfficiencyCategoryA3Plus',
			);
		}

		// Just for WooCommerce
		if ( isset( $context['product'] ) && null !== $context['product'] && isset( $context['post']->ID ) && comments_open( $context['post']->ID ) ) {
			$args = array(
				'meta_key'    => 'rating',
				'number'      => 1,
				'status'      => 'approve',
				'post_status' => 'publish',
				'parent'      => 0,
				'orderby'     => 'meta_value_num',
				'order'       => 'DESC',
				'post_id'     => $context['post']->ID,
				'post_type'   => 'product',
			);

			$comments = get_comments( $args );

			if ( ! empty( $comments ) ) {
				$contextWithVariables              = $context;
				$contextWithVariables['variables'] = array(
					'ratingValue'  => get_comment_meta( $comments[0]->comment_ID, 'rating', true ),
					'ratingAuthor' => get_comment_author( $comments[0]->comment_ID ),
				);
				$contextWithVariables['type']      = RichSnippetType::SUB_TYPE;
				$schema                            = seopress_get_service( 'JsonSchemaGenerator' )->getJsonFromSchema( Review::NAME, $contextWithVariables, array( 'remove_empty' => true ) );
				if ( count( $schema ) > 1 ) {
					$data['review'] = $schema;
				}
			}

			if ( function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $context['post']->ID );

				if ( method_exists( $product, 'get_review_count' ) && $product->get_review_count() >= 1 ) {
					$contextWithVariables              = $context;
					$contextWithVariables['variables'] = array(
						'ratingValue' => $product->get_average_rating(),
						'reviewCount' => $product->get_review_count(),
					);
					$contextWithVariables['type']      = RichSnippetType::SUB_TYPE;
					$schema                            = seopress_get_service( 'JsonSchemaGenerator' )->getJsonFromSchema( AggregateRating::NAME, $contextWithVariables, array( 'remove_empty' => true ) );
					if ( count( $schema ) > 1 ) {
						$data['aggregateRating'] = $schema;
					}
				}
			}
		}
		// Like article review
		elseif ( isset( $variables['positiveNotes'] ) || isset( $variables['negativeNotes'] ) ) {
			$contextWithVariables              = $context;
			$contextWithVariables['variables'] = array(
				'ratingAuthor'  => '%%post_author%%',
				'positiveNotes' => isset( $variables['positiveNotes'] ) ? $variables['positiveNotes'] : array(),
				'negativeNotes' => isset( $variables['negativeNotes'] ) ? $variables['negativeNotes'] : array(),
			);
			$contextWithVariables['type']      = RichSnippetType::SUB_TYPE;
			$schema                            = seopress_get_service( 'JsonSchemaGenerator' )->getJsonFromSchema( Review::NAME, $contextWithVariables, array( 'remove_empty' => true ) );
			if ( count( $schema ) > 1 ) {
				$data['review'] = $schema;
			}
		}

		if ( isset( $variables['price'] ) ) {
			$contextWithVariables              = $context;
			$contextWithVariables['variables'] = array(
				'url'             => '%%post_url%%',
				'priceCurrency'   => isset( $variables['priceCurrency'] ) ? $variables['priceCurrency'] : '',
				'price'           => isset( $variables['price'] ) ? $variables['price'] : '',
				'priceValidUntil' => isset( $variables['priceValidDate'] ) ? $variables['priceValidDate'] : '',
				'itemCondition'   => isset( $variables['condition'] ) ? sprintf( '%sschema.org/%s', seopress_check_ssl(), $variables['condition'] ) : sprintf( '%sschema.org/%s', seopress_check_ssl(), $variables['NewCondition'] ),
				'availability'    => isset( $variables['availability'] ) ? sprintf( '%sschema.org/%s', seopress_check_ssl(), $variables['availability'] ) : '',
			);

			// Get woocommerce currency if it is not set
			if ( empty( $contextWithVariables['variables']['priceCurrency'] ) ) {
				if ( isset( $context['product'] ) && null !== $context['product'] && is_a( $context['product'], 'WC_Product' ) ) {
					$contextWithVariables['variables']['priceCurrency'] = get_woocommerce_currency();
				}
			}

			$contextWithVariables['type'] = RichSnippetType::SUB_TYPE;
			$schema                       = seopress_get_service( 'JsonSchemaGenerator' )->getJsonFromSchema( Offer::NAME, $contextWithVariables, array( 'remove_empty' => true ) );

			if ( count( $schema ) > 1 ) {
				$data['offers'] = $schema;
				// Woocommerce shipping details
				$shipping_details_schema = $this->get_woocommerce_shipping_details_schema( $context );
				if ( ! empty( $shipping_details_schema ) ) {
					$data['offers']['shippingDetails'] = $shipping_details_schema;
				}
			}
		}

		$data = seopress_get_service( 'VariablesToString' )->replaceDataToString( $data, $variables );

		return apply_filters( 'seopress_pro_get_json_data_product', $data, $context );
	}

	/**
	 * @since 4.6.0
	 *
	 * @param  $data
	 *
	 * @return array
	 */
	public function cleanValues( $data ) {
		if ( isset( $data['review'] ) && isset( $data['review']['@context'] ) ) {
			unset( $data['review']['@context'] );
		}

		return parent::cleanValues( $data );
	}

	/**
	 * @since 7.4.0
	 *
	 * Returns shippingDetails schema for a Woocommerce product
	 *
	 * @param   array $context
	 * @return  array  $schema
	 */
	public function get_woocommerce_shipping_details_schema( $context ) {
		$schema = array();
		if ( isset( $context['product'] ) && null !== $context['product'] ) {
			$product = $context['product'];
			if ( is_a( $product, 'WC_Product' ) && $product->needs_shipping() ) {
				/**
				 * Filter to disable WooCommerce shippingDetails schema generation.
				 *
				 * @since 9.5.0
				 *
				 * @param bool       $enabled Whether shippingDetails should be generated.
				 * @param WC_Product $product The current WooCommerce product.
				 * @param array      $context The schema generation context.
				 */
				$enabled = apply_filters( 'seopress_pro_wc_schema_shipping_details_enabled', true, $product, $context );
				if ( ! $enabled ) {
					return array();
				}

				$shipping_class_id = (int) $product->get_shipping_class_id();
				$currency          = get_woocommerce_currency();

				// Cache computed shipping offers by shipping class (request-level).
				static $shipping_details_cache = array();
				$cache_key                     = sprintf( '%d|%s', $shipping_class_id, (string) $currency );
				if ( isset( $shipping_details_cache[ $cache_key ] ) ) {
					return $shipping_details_cache[ $cache_key ];
				}

				// Persist cache if an object cache is available.
				$object_cache_key = 'wc_shipping_details_schema_' . md5( $cache_key );
				$cached           = wp_cache_get( $object_cache_key, 'seopress_pro' );
				if ( is_array( $cached ) ) {
					$shipping_details_cache[ $cache_key ] = $cached;
					return $cached;
				}

				// Cache zones (and their shipping methods) per request.
				static $zones_cache = null;
				if ( null === $zones_cache ) {
					$zones_cache = \WC_Shipping_Zones::get_zones();
				}

				foreach ( $zones_cache as $zone ) {
					$destinationSchema = $this->get_woocommerce_shipping_destination_schema( $zone, $context );
					foreach ( $zone['shipping_methods'] as $method ) {
						$contextWithVariables              = $context;
						$contextWithVariables['variables'] = array(
							'shippingAmount'      => $this->get_woocommerce_shipping_amount( $method, $product ),
							'currency'            => $currency,
							'shippingDestination' => $destinationSchema,
						);
						$schema[]                          = seopress_get_service( 'JsonSchemaGenerator' )->getJsonFromSchema( OfferShippingDetails::NAME, $contextWithVariables, array( 'remove_empty' => false ) );
					}
				}

				/**
				 * Filter the generated WooCommerce shippingDetails schema.
				 *
				 * @since 9.5.0
				 *
				 * @param array      $schema  ShippingDetails schema array.
				 * @param WC_Product $product The current WooCommerce product.
				 * @param array      $context The schema generation context.
				 */
				$schema = apply_filters( 'seopress_pro_wc_schema_shipping_details', $schema, $product, $context );

				$shipping_details_cache[ $cache_key ] = $schema;
				wp_cache_set( $object_cache_key, $schema, 'seopress_pro', HOUR_IN_SECONDS );
			}
		}
		return $schema;
	}

	/**
	 * @since 7.4.0
	 *
	 * @param   array $zone     WC zone data
	 * @param   array $context
	 * @return  array  $schema   Array of DefinedRegion schemas
	 */
	public function get_woocommerce_shipping_destination_schema( $zone, $context ) {
		$schema    = array();
		$locations = $zone['zone_locations'] ?? array();
		foreach ( $locations as $location ) {
			if ( $location->type === 'country' && $location->code ) {
				$context['variables']['addressCountry'] = $location->code;
				$schema[]                               = seopress_get_service( 'JsonSchemaGenerator' )->getJsonFromSchema( DefinedRegion::NAME, $context, array( 'remove_empty' => true ) );
			}
			if ( $location->type === 'postcode' && $location->code ) {
				$context['variables']['postalCode'] = $location->code;
				$schema[]                           = seopress_get_service( 'JsonSchemaGenerator' )->getJsonFromSchema( DefinedRegion::NAME, $context, array( 'remove_empty' => true ) );
			}
		}
		return $schema;
	}

	/**
	 * @since 7.4.0
	 *
	 * @param   WC_Shipping_Method $method
	 * @param   WC_Product         $product
	 * @return  string              $cost
	 */
	public function get_woocommerce_shipping_amount( $method, $product ) {
		$shipping_class_id = (int) $product->get_shipping_class_id();
		$instance          = $method->instance_settings;
		$cost              = isset( $instance['cost'] ) ? (float) $instance['cost'] : ( isset( $instance['min_amount'] ) ? (float) $instance['min_amount'] : 0 );
		if ( $shipping_class_id && isset( $instance['type'] ) && $instance['type'] === 'class' ) {
			$cost_key = 'class_cost_' . (int) $shipping_class_id;
			if ( ! empty( $instance[ $cost_key ] ) ) {
				$cost += (float) $instance[ $cost_key ];
			}
		}
		return $cost;
	}
}
