<?php

namespace SEOPressPro\Services\Options;

defined( 'ABSPATH' ) or exit( 'Cheatin&#8217; uh?' );

class SitemapOptionPro {

	/**
	 * @since 6.6.0
	 *
	 * @return array
	 */
	public function getOption() {
		return get_option( 'seopress_xml_sitemap_option_name' );
	}

	/**
	 * @since 6.6.0
	 *
	 * @return string|null
	 *
	 * @param string $key
	 */
	protected function searchOptionByKey( $key ) {
		$data = $this->getOption();

		if ( empty( $data ) ) {
			return null;
		}

		if ( ! isset( $data[ $key ] ) ) {
			return null;
		}

		return $data[ $key ];
	}

	/**
	 * @since 6.6.0
	 *
	 * @return string|null
	 */
	public function getSitemapVideoEnable() {
		return $this->searchOptionByKey( 'seopress_xml_sitemap_video_enable' );
	}
}
