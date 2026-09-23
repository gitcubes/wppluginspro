<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
defined( 'ABSPATH' ) || die( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

/**
 * ACP Column for the target keywords meta key.
 */
class ACP_Column_Sp_Target_Kw extends AC\Column\Meta implements \ACP\Editing\Editable, \ACP\Sorting\Sortable, \ACP\Export\Exportable, \ACP\Search\Searchable {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_type( 'column-sp_target_kw' );
		$this->set_group( 'seopress' );
		$this->set_label( __( 'Target keywords', 'wp-seopress-pro' ) );
	}

	/**
	 * Get the meta key.
	 *
	 * @return string
	 */
	public function get_meta_key() {
		return '_seopress_analysis_target_kw';
	}

	/**
	 * Get the value.
	 *
	 * @param int $post_id The post ID.
	 * @return string
	 */
	public function get_value( $post_id ) {
		return esc_html( $this->get_raw_value( $post_id ) );
	}

	/**
	 * Get the editing service.
	 *
	 * @return ACP\Editing\Service\Basic
	 */
	public function editing() {
		return new ACP\Editing\Service\Basic(
			new ACP\Editing\View\Text(),
			new ACP\Editing\Storage\Post\Meta( $this->get_meta_key() )
		);
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
