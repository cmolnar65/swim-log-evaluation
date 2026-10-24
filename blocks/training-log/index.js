( function ( blocks, blockEditor, components, element, i18n, ServerSideRender ) {
	'use strict';
	var registerBlockType = blocks.registerBlockType, InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody, SelectControl = components.SelectControl, TextControl = components.TextControl;
	var createElement = element.createElement, __ = i18n.__, domain = 'chriss-swim-training-progress-evaluation';
	var months = [
		{ label: __( 'Current month', domain ), value: 0 },
		{ label: __( 'January', domain ), value: 1 }, { label: __( 'February', domain ), value: 2 },
		{ label: __( 'March', domain ), value: 3 }, { label: __( 'April', domain ), value: 4 },
		{ label: __( 'May', domain ), value: 5 }, { label: __( 'June', domain ), value: 6 },
		{ label: __( 'July', domain ), value: 7 }, { label: __( 'August', domain ), value: 8 },
		{ label: __( 'September', domain ), value: 9 }, { label: __( 'October', domain ), value: 10 },
		{ label: __( 'November', domain ), value: 11 }, { label: __( 'December', domain ), value: 12 }
	];
	registerBlockType( 'swimlog/training-log', {
		edit: function ( props ) {
			var a = props.attributes, mode = a.mode || 'monthly';
			var controls = createElement( InspectorControls, null,
				createElement( PanelBody, { title: __( 'Training Log settings', domain ), initialOpen: true },
					createElement( TextControl, { label: __( 'Swimmer user ID override', domain ), help: __( 'Leave blank to use the page or post author. There is no logged-in-viewer fallback.', domain ), type: 'number', min: 1, value: a.user || '', onChange: function ( v ) { props.setAttributes( { user: v ? parseInt( v, 10 ) : 0 } ); } } ),
					createElement( SelectControl, { label: __( 'Log view', domain ), value: mode, options: [ { label: __( 'Monthly workout log', domain ), value: 'monthly' }, { label: __( 'Annual monthly totals', domain ), value: 'annual' } ], onChange: function ( v ) { props.setAttributes( { mode: v } ); } } ),
					mode === 'monthly' ? createElement( SelectControl, { label: __( 'Month', domain ), value: a.month || 0, options: months, onChange: function ( v ) { props.setAttributes( { month: parseInt( v, 10 ) || 0 } ); } } ) : null,
					createElement( TextControl, { label: mode === 'annual' ? __( 'Year', domain ) : __( 'Year (blank = current year)', domain ), help: mode === 'annual' ? __( 'Enter a year from 1900 through 2100.', domain ) : __( 'Leave blank to use the current year.', domain ), type: 'number', min: 1900, max: 2100, value: a.year || '', onChange: function ( v ) { props.setAttributes( { year: v ? parseInt( v, 10 ) : 0 } ); } } ),
					createElement( SelectControl, { label: __( 'Heading', domain ), value: a.titleMode || 'default', options: [ { label: __( 'Default heading', domain ), value: 'default' }, { label: __( 'Hide heading', domain ), value: 'hidden' }, { label: __( 'Custom heading', domain ), value: 'custom' } ], onChange: function ( v ) { props.setAttributes( { titleMode: v } ); } } ),
					a.titleMode === 'custom' ? createElement( TextControl, { label: __( 'Custom heading text', domain ), value: a.customTitle || '', onChange: function ( v ) { props.setAttributes( { customTitle: v } ); } } ) : null
				)
			);
			return createElement( 'div', blockEditor.useBlockProps(), controls, createElement( ServerSideRender, { block: 'swimlog/training-log', attributes: a } ) );
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender );
