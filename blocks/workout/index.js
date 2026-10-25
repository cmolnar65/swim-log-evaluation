( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
	'use strict';
	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var createElement = element.createElement;
	var __ = i18n.__;
	var domain = 'chriss-swim-training-progress-evaluation';

	registerBlockType( 'swimlog/workout', {
		edit: function ( props ) {
			var a = props.attributes;
			var controls = createElement(
				InspectorControls, null,
				createElement(
					PanelBody,
					{ title: __( 'Workout settings', domain ), initialOpen: true },
					createElement( TextControl, {
						label: __( 'Workout ID', domain ),
						help: __( 'Enter the numeric workout ID. The workout determines the swimmer; ownership cannot be overridden.', domain ),
						type: 'number', min: 1, value: a.workoutId || '',
						onChange: function ( value ) { props.setAttributes( { workoutId: value ? parseInt( value, 10 ) : 0 } ); }
					} ),
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
			var preview = a.workoutId
				? createElement( ServerSideRender, { block: 'swimlog/workout', attributes: a } )
				: createElement( 'p', null, __( 'Enter a workout ID to preview this block.', domain ) );
			return createElement( 'div', blockEditor.useBlockProps(), controls, preview );
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender );
