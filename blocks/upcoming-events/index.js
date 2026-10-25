( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
	'use strict';

	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var RangeControl = components.RangeControl;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;
	var createElement = element.createElement;
	var __ = i18n.__;
	var domain = 'chriss-swim-training-progress-evaluation';

	registerBlockType( 'swimlog/upcoming-events', {
		edit: function ( props ) {
			var a = props.attributes;
			var useSiteDefault = ! a.count;
			var controls = createElement(
				InspectorControls,
				null,
				createElement(
					PanelBody,
					{ title: __( 'Upcoming Events settings', domain ), initialOpen: true },
					createElement( TextControl, {
						label: __( 'Swimmer user ID override', domain ),
						help: __( 'Leave blank to use the page or post author. There is no logged-in-viewer fallback.', domain ),
						type: 'number', min: 1, value: a.user || '',
						onChange: function ( value ) { props.setAttributes( { user: value ? parseInt( value, 10 ) : 0 } ); }
					} ),
					createElement( ToggleControl, {
						label: __( 'Use site default event count', domain ),
						checked: useSiteDefault,
						onChange: function ( checked ) { props.setAttributes( { count: checked ? 0 : 5 } ); }
					} ),
					! useSiteDefault ? createElement( RangeControl, {
						label: __( 'Maximum events to display', domain ),
						min: 1, max: 50, value: a.count || 5,
						onChange: function ( value ) { props.setAttributes( { count: value || 1 } ); }
					} ) : null,
					createElement( SelectControl, {
						label: __( 'Heading', domain ), value: a.titleMode || 'default',
						options: [
							{ label: __( 'Default heading', domain ), value: 'default' },
							{ label: __( 'Hide heading', domain ), value: 'hidden' },
							{ label: __( 'Custom heading', domain ), value: 'custom' }
						],
						onChange: function ( value ) { props.setAttributes( { titleMode: value } ); }
					} ),
					a.titleMode === 'custom' ? createElement( TextControl, {
						label: __( 'Custom heading text', domain ), value: a.customTitle || '',
						onChange: function ( value ) { props.setAttributes( { customTitle: value } ); }
					} ) : null
				)
			);
			return createElement(
				'div', blockEditor.useBlockProps(), controls,
				createElement( ServerSideRender, { block: 'swimlog/upcoming-events', attributes: a } )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender );
