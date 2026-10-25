( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
	'use strict';

	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var RangeControl = components.RangeControl;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var createElement = element.createElement;
	var __ = i18n.__;

	registerBlockType( 'swimlog/latest-workouts', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var controls = createElement(
				InspectorControls,
				null,
				createElement(
					PanelBody,
					{ title: __( 'Latest Workouts settings', 'chriss-swim-training-progress-evaluation' ), initialOpen: true },
					createElement( RangeControl, {
						label: __( 'Workout count', 'chriss-swim-training-progress-evaluation' ),
						min: 1,
						max: 20,
						value: attributes.count || 1,
						onChange: function ( value ) { props.setAttributes( { count: value || 1 } ); }
					} ),
					createElement( TextControl, {
						label: __( 'Swimmer user ID override', 'chriss-swim-training-progress-evaluation' ),
						help: __( 'Leave blank to use the page or post author. There is no logged-in-viewer fallback.', 'chriss-swim-training-progress-evaluation' ),
						type: 'number',
						min: 1,
						value: attributes.user || '',
						onChange: function ( value ) { props.setAttributes( { user: value ? parseInt( value, 10 ) : 0 } ); }
					} ),
					createElement( SelectControl, {
						label: __( 'Heading', 'chriss-swim-training-progress-evaluation' ),
						value: attributes.titleMode || 'default',
						options: [
							{ label: __( 'Default heading', 'chriss-swim-training-progress-evaluation' ), value: 'default' },
							{ label: __( 'Hide heading', 'chriss-swim-training-progress-evaluation' ), value: 'hidden' },
							{ label: __( 'Custom heading', 'chriss-swim-training-progress-evaluation' ), value: 'custom' }
						],
						onChange: function ( value ) { props.setAttributes( { titleMode: value } ); }
					} ),
					attributes.titleMode === 'custom' ? createElement( TextControl, {
						label: __( 'Custom heading text', 'chriss-swim-training-progress-evaluation' ),
						value: attributes.customTitle || '',
						onChange: function ( value ) { props.setAttributes( { customTitle: value } ); }
					} ) : null
				)
			);

			return createElement(
				'div',
				blockEditor.useBlockProps(),
				controls,
				createElement( ServerSideRender, {
					block: 'swimlog/latest-workouts',
					attributes: attributes
				} )
			);
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender );
