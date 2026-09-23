(function ( blocks, element, i18n, editor, components ) {
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = ( editor && editor.InspectorControls ) || wp.blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;

	var postTypes =
		( window.WSH_VC_Pro_Blocks_Data && window.WSH_VC_Pro_Blocks_Data.postTypes ) || [ 'post' ];
	var defaultTitle =
		( window.WSH_VC_Pro_Blocks_Data && window.WSH_VC_Pro_Blocks_Data.defaultTitle ) ||
		'Popular Posts';

	blocks.registerBlockType( 'wsh-vc-pro/popular-posts', {
		title: __( 'WSH Popular Posts PRO', 'wsh-views-counter-pro' ),
		description: __(
			'Display most popular or trending posts based on WSH Views Counter data.',
			'wsh-views-counter-pro'
		),
		icon: 'chart-area',
		category: 'widgets',

		attributes: {
			title: { type: 'string', default: '' },
			mode: { type: 'string', default: 'popular' }, // popular | trending
			postType: { type: 'string', default: 'post' },
			days: { type: 'number', default: 7 },
			limit: { type: 'number', default: 5 }
		},

		edit: function ( props ) {
			var attrs = props.attributes;

			// Ako naslov nije setovan, prikaži default u editoru (user može da promeni).
			if ( ! attrs.title ) {
				attrs.title = defaultTitle;
			}

			return [
				// Sidebar kontrole.
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{
							title: __( 'WSH Popular Posts PRO', 'wsh-views-counter-pro' ),
							initialOpen: true
						},
						el( TextControl, {
							label: __( 'Title', 'wsh-views-counter-pro' ),
							value: attrs.title,
							onChange: function ( value ) {
								props.setAttributes( { title: value } );
							}
						} ),
						el( SelectControl, {
							label: __( 'Mode', 'wsh-views-counter-pro' ),
							value: attrs.mode,
							options: [
								{
									label: __(
										'Most popular (total views)',
										'wsh-views-counter-pro'
									),
									value: 'popular'
								},
								{
									label: __(
										'Trending (last days boost)',
										'wsh-views-counter-pro'
									),
									value: 'trending'
								}
							],
							onChange: function ( value ) {
								props.setAttributes( { mode: value } );
							}
						} ),
						el( SelectControl, {
							label: __( 'Post type', 'wsh-views-counter-pro' ),
							value: attrs.postType,
							options: postTypes.map( function ( slug ) {
								return { label: slug, value: slug };
							} ),
							onChange: function ( value ) {
								props.setAttributes( { postType: value } );
							}
						} ),
						el( RangeControl, {
							label: __( 'Days to look back', 'wsh-views-counter-pro' ),
							value: attrs.days,
							min: 1,
							max: 60,
							onChange: function ( value ) {
								props.setAttributes( { days: value } );
							}
						} ),
						el( RangeControl, {
							label: __( 'Number of posts', 'wsh-views-counter-pro' ),
							value: attrs.limit,
							min: 1,
							max: 20,
							onChange: function ( value ) {
								props.setAttributes( { limit: value } );
							}
						} )
					)
				),

				// Preview u editoru (samo placeholder – pravi sadržaj dolazi sa servera).
				el(
					'div',
					{ className: props.className + ' wsh-vc-pro-popular-block-preview' },
					el( 'h3', {}, attrs.title || defaultTitle ),
					el(
						'p',
						{},
						__(
							'Preview is simplified. Final list will be rendered on the front-end based on live analytics data.',
							'wsh-views-counter-pro'
						)
					)
				)
			];
		},

		// Dynamic block – sadržaj se renderuje u PHP-u.
		save: function () {
			return null;
		}
	} );
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.i18n,
	window.wp.editor || window.wp.blockEditor,
	window.wp.components
);
