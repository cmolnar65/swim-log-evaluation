( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
	'use strict';
	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var createElement = element.createElement;
	var __ = i18n.__;
	var domain = 'chriss-swim-training-progress-evaluation';

	registerBlockType( 'swimlog/calendar', {
		edit: function ( props ) {
			var a = props.attributes;
			var controls = createElement(
				InspectorControls, null,
				createElement(
					PanelBody, { title: __( 'Calendar settings', domain ), initialOpen: true },
					createElement( TextControl, {
						label: __( 'Swimmer user ID override', domain ),
						help: __( 'Leave blank to use the page or post author. There is no logged-in-viewer fallback. The calendar remains private to the swimmer or an authorized administrator.', domain ),
						type: 'number', min: 1, value: a.user || '',
						onChange: function ( value ) { props.setAttributes( { user: value ? parseInt( value, 10 ) : 0 } ); }
					} )
				)
			);
			return createElement( 'div', blockEditor.useBlockProps(), controls,
				createElement( ServerSideRender, { block: 'swimlog/calendar', attributes: a } )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender );
