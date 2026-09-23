<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
defined( 'ABSPATH' ) || die( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

/**
 * ACP Column for the GSC CTR meta key.
 */
class ACP_Column_Sp_GSC_CTR extends AC\Column\Meta implements \ACP\Sorting\Sortable, \ACP\Export\Exportable, \ACP\Search\Searchable {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_type( 'column-sp_gsc_ctr' );
		$this->set_group( 'seopress' );
		$this->set_label( __( 'CTR', 'wp-seopress-pro' ) );
	}

	/**
	 * Get the meta key.
	 *
	 * @return string
	 */
	public function get_meta_key() {
		return '_seopress_search_console_analysis_ctr';
	}

	/**
	 * Get the value.
	 *
	 * @param int $post_id The post ID.
	 * @return string
	 */
	public function get_value( $post_id ) {
		$value = esc_html( $this->get_raw_value( $post_id ) );

		if ( empty( $value ) ) {
			$value = 0;
		}

		return $value;
	}

	/**
	 * Get the sorting model.
	 *
	 * @return ACP\Sorting\Model\MetaFactory
	 */
	public function sorting() {
		return ( new ACP\Sorting\Model\MetaFactory() )
			->create( 'post', $this->get_meta_key() );
	}

	/**
	 * Get the export model.
	 *
	 * @return ACP\Export\Model\Meta
	 */
	public function export() {
		return new ACP\Export\Model\Meta( new AC\MetaType( $this->get_meta_type() ), $this->get_meta_key() );
	}

	/**
	 * Get the search comparison.
	 *
	 * @return ACP\Search\Comparison\Meta\Text
	 */
	public function search() {
		return new ACP\Search\Comparison\Meta\Text( $this->get_meta_key(), $this->get_meta_type() );
	}
}
