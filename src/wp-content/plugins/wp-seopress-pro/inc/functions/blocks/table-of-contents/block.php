<?php // phpcs:ignore
/**
 * SEOPress PRO Table of Contents block.
 *
 * @package SEOPress PRO
 * @subpackage Blocks
 */

defined( 'ABSPATH' ) || exit( 'Please don&rsquo;t call the plugin directly. Thanks :)' );

/**
 * Table of Contents Block class.
 */
class SEOPRESS_PRO_Table_of_Contents_Block {

	/**
	 * Block name.
	 *
	 * @var string
	 */
	public $name = 'wpseopress/table-of-contents';

	/**
	 * Register necessary hooks
	 */
	public function register_hooks() {
		add_filter( 'template_include', array( $this, 'maybe_hook_render_block_data' ) );
	}

	/**
	 * Checks whether the page template or post_content has the TOC block.
	 * If so, hook onto render_block_data to insert HTML anchors.
	 * On the template_include hook, we have the template content if using a block themes
	 *
	 * @param  string $template   Template file to load.
	 * @return  string  $template
	 */
	public function maybe_hook_render_block_data( $template ) {
		if ( $this->page_has_block() ) {
			add_filter( 'render_block_data', array( $this, 'headings_block_data' ), 10, 3 );
		}
		return $template;
	}

	/**
	 * Returns whether the current page includes the TOC block
	 *
	 * @return  bool  $has_block
	 */
	public function page_has_block() {
		global $post, $_wp_current_template_content;

		$theme_has_block   = false;
		$content_has_block = false;

		if ( wp_is_block_theme() && null !== $_wp_current_template_content && is_string( $_wp_current_template_content ) ) {
			$theme_has_block = $this->has_block( $this->name, $_wp_current_template_content );
		}
		if ( ! is_null( $post ) && ! is_null( $post->post_content ) ) {
			$content_has_block = $this->has_block( $this->name, $post->post_content );
		}
		return $theme_has_block || $content_has_block;
	}


	/**
	 * Returns whether a given block exists within a serialized content string
	 *
	 * @param   string $block_name  Block to search.
	 * @param   string $content     Content to search.
	 * @return  bool    $has_block
	 */
	public function has_block( $block_name, $content = '' ) {
		if ( has_block( $block_name, $content ) ) {
			return true;
		}

		if ( has_block( 'core/block', $content ) ) {
			$blocks = parse_blocks( $content );
			return $this->search_block( $block_name, $blocks );
		}

		return false;
	}

	/**
	 * Returns whether a given block exists within an array of blocks
	 *
	 * @param   string $block_name  Block to search.
	 * @param   array  $blocks      Blocks to search.
	 * @return  bool    $has_block
	 */
	public function search_block( $block_name, $blocks = array() ) {
		foreach ( $blocks as $block ) {
			if ( isset( $block['innerBlocks'] ) && ! empty( $block['innerBlocks'] ) ) {
				return $this->search_block( $block_name, $block['innerBlocks'] );
			} elseif ( 'core/block' === $block['blockName'] && ! empty( $block['attrs']['ref'] ) && has_block( $block_name, $block['attrs']['ref'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Table of Contents Block display callback
	 *
	 * @param   array    $attributes  Block attributes.
	 * @param   string   $content     Inner block content.
	 * @param   WP_Block $block       Actual block.
	 * @return  string    $html
	 */
	public function render( $attributes, $content, $block ) {
		global $post;
		$wrapper_attrs = get_block_wrapper_attributes();

		$blocks          = ! is_null( $post ) && ! is_null( $post->post_content ) ? parse_blocks( $post->post_content ) : array();
		$headings        = $this->get_headings( $blocks, $attributes['levels'] ?? array( 1, 2, 3, 4, 5, 6 ) );
		$nested_headings = $this->nest_headings( $headings );

		$list_tag = 'ul' === $attributes['listTag'] ? 'ul' : 'ol';

		$title_tag = 'h2';
		if ( ! empty( $attributes['titleLevel'] ) ) {
			$title_tag = (int) $attributes['titleLevel'] ? sprintf( 'h%d', (int) $attributes['titleLevel'] ) : ( in_array( $attributes['titleLevel'], array( 'p', 'div' ), true ) ? $attributes['titleLevel'] : 'h2' );
		}

		$title_html = '';
		if ( ! empty( $attributes['title'] ) ) {
			$title_html = sprintf( '<%1$s>%2$s</%1$s>', $title_tag, wp_kses_post( $attributes['title'] ) );
		}

		$html = ! empty( $nested_headings ) ? sprintf( '<nav %s>%s%s</nav>', $wrapper_attrs, $title_html, $this->headings_list( $nested_headings, $list_tag ) ) : '';
		return $html;
	}


	/**
	 * Parses blocks to extract wanted headings
	 *
	 * @param   array $blocks    Blocks to parse.
	 * @param   array $included  Heading levels to include.
	 * @return  array  $headings  Filtered list of blocks.
	 */
	function get_headings( $blocks = array(), $included = array() ) {
		$headings = array();
		foreach ( $blocks as $block ) {
			if ( $block['blockName'] === 'core/heading' && isset( $block['innerContent'] ) ) {
				$level = $block['attrs']['level'] ?? 2;
				if ( in_array( $level, $included, true ) ) {
					$block      = $this->headings_block_data( $block );
					$headings[] = $block;
				}
			}
			$headings = array_merge( $headings, $this->get_headings( $block['innerBlocks'] ?? array(), $included ) );
		}
		return $headings;
	}

	/**
	 * Nests headings to reflect hierarchy
	 *
	 * @param  array $headings        Headings blocks to nest.
	 * @return  array  $nestedHeadings  Nested blocks.
	 */
	function nest_headings( $headings = array() ) {
		$nested_headings = array();
		foreach ( $headings as $index => $heading ) {
			$level      = $heading['attrs']['level'] ?? 2;
			$root_level = $headings[0]['attrs']['level'] ?? 2;
			$class_name = $heading['attrs']['className'] ?? '';
			$include    = ! in_array( 'seopress-toc-hidden', explode( ' ', $class_name ), true );
			if ( $level <= $root_level ) {
				$next_level = $headings[ $index + 1 ]['attrs']['level'] ?? 2;
				if ( $next_level > $root_level ) {
					$end_index = count( $headings );
					for ( $i = $index + 1; $i <= $end_index; $i++ ) {
						$i_level = $headings[ $i ]['attrs']['level'] ?? 2;
						if ( $i_level <= $root_level ) {
							$end_index = $i;
						}
					}
					$children            = array_slice( $headings, $index + 1, $end_index - ( $index + 1 ) );
					$heading['children'] = $this->nest_headings( $children );
					if ( $include ) {
						$nested_headings[] = $heading;
					}
				} else {
					$heading['children'] = array();
					if ( $include ) {
						$nested_headings[] = $heading;
					}
				}
			}
		}
		return $nested_headings;
	}

	/**
	 * Recursively build HTML list of headings
	 *
	 * @param   array  $headings  Nested headings to build the list from.
	 * @param   string $list_tag  List tag to use.
	 * @return  string  $html      HTML list.
	 */
	function headings_list( $headings = array(), $list_tag = 'ul' ) {
		$html = array_reduce(
			$headings,
			function ( $list, $heading ) use ( $list_tag ) {
				$child_list = ! empty( $heading['children'] ) ? $this->headings_list( $heading['children'], $list_tag ) : '';
				$anchor     = $heading['attrs']['anchor'] ?? '';
				$element    = $anchor
				? sprintf( '<a href="#%s">%s</a>', esc_attr( $anchor ), wp_strip_all_tags( $heading['innerHTML'] ) )
				: sprintf( '<span>%s</span>', wp_strip_all_tags( $heading['innerHTML'] ) );
				$list      .= sprintf( '<li>%s%s</li>', $element, $child_list );
				return $list;
			},
			''
		);
		return sprintf( '<%1$s>%2$s</%1$s>', $list_tag, $html );
	}

	/**
	 * Filters core/heading blocks to add an anchor attribute and relevant id html attribute.
	 *
	 * @param   array $block         Block being rendered.
	 * @param   array $source_block  Original block.
	 * @param   array $parent_block  Parent block, for context.
	 * @return  array  $block         Modified block with HTML ID attribute, and anchor block attribute
	 */
	function headings_block_data( $block, $source_block = array(), $parent_block = array() ) {
		if ( 'core/heading' === $block['blockName'] && ! isset( $block['attrs']['anchor'] ) ) {
			$level                    = $block['attrs']['level'] ?? 2;
			$data                     = $this->add_heading_anchor( $block['innerHTML'], $level );
			$block['innerHTML']       = $data['html'] ?? '';
			$block['attrs']['anchor'] = $data['anchor'] ?? '';
			if ( ! empty( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
				$block['innerContent'] = array_map(
					function ( $inner_content ) use ( $level ) {
						return $this->add_heading_anchor( $inner_content, $level )['html'] ?? '';
					},
					$block['innerContent']
				);
			}
		}
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = array_map(
				function ( $inner_block ) {
					return $this->headings_block_data( $inner_block );
				},
				$block['innerBlocks']
			);
		}
		return $block;
	}


	/**
	 * Adds an ID attribute to heading tags in the provided HTML.
	 *
	 * @param string $html The HTML content to modify.
	 * @param int    $level The heading level.
	 * @return string The modified HTML content with ID attributes added to the Heading tags
	 */
	function add_heading_anchor( $html, $level = 2 ) {
		$anchor = '';
		if ( class_exists( 'DomDocument' ) ) {
			try {
				libxml_use_internal_errors( true );
				$dom = new DomDocument();
				@$dom->loadHTML( '<?xml version="1.0" encoding="UTF-8"?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
				$tag     = sprintf( 'h%d', (int) $level );
				$element = @$dom->getElementsByTagName( $tag )->item( 0 );
				$anchor  = $element ? $element->getAttribute( 'id' ) : '';
				if ( $element && ! $anchor ) {
					$anchor = sanitize_title( $element->textContent ?? '' );
					$element->setAttribute( 'id', $anchor );
					$html = @$dom->saveHTML( $dom->documentElement );
				}
				libxml_clear_errors();
			} catch ( Exception $e ) {
				error_log( $e->getMessage() );
			}
		}
		return array(
			'html'   => $html,
			'anchor' => $anchor,
		);
	}
}
