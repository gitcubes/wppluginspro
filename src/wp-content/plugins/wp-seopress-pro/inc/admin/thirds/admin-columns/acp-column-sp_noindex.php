<?php //phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
defined( 'ABSPATH' ) || die( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

use AC\Type\ToggleOptions;
use AC\Helper\Select\Option;

/**
 * ACP Column for the noindex meta key.
 */
class ACP_Column_Sp_Noindex extends AC\Column\Meta implements \ACP\Editing\Editable, \ACP\Sorting\Sortable, \ACP\Export\Exportable, \ACP\Search\Searchable {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_type( 'column-sp_noindex' );
		$this->set_group( 'seopress' );
		$this->set_label( __( 'noindex?', 'wp-seopress-pro' ) );
	}

	/**
	 * Get the meta key.
	 *
	 * @return string
	 */
	public function get_meta_key() {
		return '_seopress_robots_index';
	}

	/**
	 * Get the value.
	 *
	 * @param int $post_id The post ID.
	 * @return string
	 */
	public function get_value( $post_id ) {
		$value = $this->get_raw_value( $post_id );
		if ( 'yes' === $value ) {
			return '<span class="dashicons dashicons-hidden"></span>';
		}

		return '<span class="dashicons dashicons-visibility"></span>';
	}

	/**
	 * Get the editing service.
	 *
	 * @return ACP\Editing\Service\Basic
	 */
	public function editing() {
		return new ACP\Editing\Service\Basic(
			new ACP\Editing\View\Toggle(
				ToggleOptions::create_from_array(
					array(
						'yes' => 'Yes',
						''    => 'no',
					)
				)
			),
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
	 * @return ACP\Search\Comparison\Meta\Checkmark
	 */
	public function search() {
		return new ACP\Search\Comparison\Meta\Checkmark( $this->get_meta_key(), $this->get_meta_type() );
	}
}
