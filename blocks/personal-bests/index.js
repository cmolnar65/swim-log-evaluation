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

	registerBlockType( 'swimlog/personal-bests', {
		edit: function ( props ) {
			var a = props.attributes;
			var controls = createElement(
				InspectorControls,
				null,
				createElement(
					PanelBody,
					{ title: __( 'Personal Bests settings', domain ), initialOpen: true },
					createElement( TextControl, {
						label: __( 'Swimmer user ID override', domain ),
						help: __( 'Leave blank to use the page or post author. There is no logged-in-viewer fallback.', domain ),
						type: 'number', min: 1, value: a.user || '',
						onChange: function ( value ) { props.setAttributes( { user: value ? parseInt( value, 10 ) : 0 } ); }
					} ),
					createElement( SelectControl, {
						label: __( 'Course', domain ), value: a.course || '',
						options: [
							{ label: __( 'Configured default', domain ), value: '' },
							{ label: __( 'Meters', domain ), value: 'm' },
							{ label: __( 'Yards', domain ), value: 'yd' }
						],
						onChange: function ( value ) { props.setAttributes( { course: value } ); }
					} ),
					createElement( SelectControl, {
						label: __( 'Stroke', domain ), value: a.stroke || '',
						options: [
							{ label: __( 'All strokes with personal bests', domain ), value: '' },
							{ label: __( 'Freestyle', domain ), value: 'FR' },
							{ label: __( 'Breaststroke', domain ), value: 'BR' },
							{ label: __( 'Backstroke', domain ), value: 'BACK' },
							{ label: __( 'Butterfly', domain ), value: 'FLY' },
							{ label: __( 'Mixed', domain ), value: 'MIXED' }
						],
						onChange: function ( value ) { props.setAttributes( { stroke: value } ); }
					} ),
					createElement( SelectControl, {
						label: __( 'Distance', domain ), value: String( a.distance || 0 ),
						options: [
							{ label: __( 'All distances', domain ), value: '0' },
							{ label: '50', value: '50' }, { label: '100', value: '100' },
							{ label: '200', value: '200' }, { label: '500', value: '500' },
							{ label: '800', value: '800' }, { label: '1000', value: '1000' },
							{ label: '1500', value: '1500' }, { label: '1650', value: '1650' },
							{ label: '2000', value: '2000' }, { label: '2500', value: '2500' },
							{ label: '3300', value: '3300' }, { label: '5000', value: '5000' }
						],
						onChange: function ( value ) { props.setAttributes( { distance: parseInt( value, 10 ) || 0 } ); }
					} ),
					createElement( SelectControl, {
						label: __( 'Personal best set', domain ), value: a.best || 'LB',
						options: [
							{ label: __( 'Lifetime bests', domain ), value: 'LB' },
							{ label: __( 'Age-group bests', domain ), value: 'AG' }
						],
						onChange: function ( value ) { props.setAttributes( { best: value } ); }
					} ),
					a.best === 'AG' ? createElement( SelectControl, {
						label: __( 'Age group', domain ), value: a.ageGroupMode || 'post-date',
						options: [
							{ label: __( 'Age group at this post/page date', domain ), value: 'post-date' },
							{ label: __( 'Current age group', domain ), value: 'current' },
							{ label: __( 'Specific age group', domain ), value: 'custom' }
						],
						onChange: function ( value ) { props.setAttributes( { ageGroupMode: value } ); }
					} ) : null,
					a.best === 'AG' && a.ageGroupMode === 'custom' ? createElement( TextControl, {
						label: __( 'Specific age group', domain ),
						help: __( 'Enter a valid bracket such as 60-64.', domain ),
						value: a.ageGroup || '',
						onChange: function ( value ) { props.setAttributes( { ageGroup: value } ); }
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
				createElement( ServerSideRender, { block: 'swimlog/personal-bests', attributes: a } )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender );
