( function ( wp ) {
	'use strict';
	const { registerBlockType } = wp.blocks;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl, RangeControl, SelectControl } = wp.components;
	const { Fragment, createElement: el } = wp.element;
	const { __ } = wp.i18n;

	registerBlockType( 'kursflow/kursliste', {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps();

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'kursflow Einstellungen', 'kursflow' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Branche (optional)', 'kursflow' ),
							value: attributes.branche,
							onChange: function ( v ) {
								setAttributes( { branche: v } );
							},
							help: __( 'Filter z.B. fahrschule, yoga, tanz – leer = alle.', 'kursflow' ),
						} ),
						el( RangeControl, {
							label: __( 'Maximale Anzahl (0 = alle)', 'kursflow' ),
							value: attributes.limit,
							onChange: function ( v ) {
								setAttributes( { limit: v } );
							},
							min: 0,
							max: 50,
							step: 1,
						} ),
						el( SelectControl, {
							label: __( 'Layout', 'kursflow' ),
							value: attributes.layout,
							options: [
								{ label: __( 'Liste', 'kursflow' ), value: 'liste' },
								{ label: __( 'Grid', 'kursflow' ), value: 'grid' },
								{ label: __( 'Kompakt', 'kursflow' ), value: 'kompakt' },
							],
							onChange: function ( v ) {
								setAttributes( { layout: v } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					el(
						'div',
						{ className: 'kursflow-block-placeholder' },
						el( 'strong', {}, __( 'kursflow Kursliste', 'kursflow' ) ),
						el( 'p', {},
							attributes.branche
								? __( 'Branche: ', 'kursflow' ) + attributes.branche
								: __( 'Alle Branchen', 'kursflow' )
						),
						el( 'p', {},
							__( 'Layout: ', 'kursflow' ) + attributes.layout +
							' · ' +
							( attributes.limit > 0
								? __( 'Limit: ', 'kursflow' ) + attributes.limit
								: __( 'Kein Limit', 'kursflow' ) )
						),
						el( 'p', { className: 'kursflow-block-hint' },
							__( 'Vorschau erscheint im Frontend (Live-Daten von kursflow.de).', 'kursflow' )
						)
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
