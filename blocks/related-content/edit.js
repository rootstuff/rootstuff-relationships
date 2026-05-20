import { useEffect, useState } from '@wordpress/element';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	Placeholder,
	ButtonGroup,
	Button,
	RangeControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { list, grid, page } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

const TEMPLATE = [
	[
		'core/heading',
		{
			level: 2,
			placeholder: __( 'Related Content', 'rootstuff-relationships' ),
		},
	],
];

const LAYOUT_OPTIONS = [
	{ value: 'list', label: __( 'List', 'rootstuff-relationships' ), icon: list },
	{ value: 'grid', label: __( 'Grid', 'rootstuff-relationships' ), icon: grid },
	{ value: 'inline', label: __( 'Inline', 'rootstuff-relationships' ), icon: page },
];

export default function Edit( { attributes, setAttributes } ) {
	const { relType, layout, columns, showThumbnail, showExcerpt } = attributes;
	const [ relationships, setRelationships ] = useState( [] );

	useEffect( () => {
		apiFetch( { path: '/rootstuff-relationships/v1/relationships' } )
			.then( ( data ) => setRelationships( data || [] ) )
			.catch( () => {} );
	}, [] );

	const options = [
		{ label: __( '— Select relationship —', 'rootstuff-relationships' ), value: '' },
		...relationships.map( ( rel ) => ( {
			label: `${ rel.labels.from } / ${ rel.labels.to } (${ rel.key })`,
			value: rel.key,
		} ) ),
	];

	const blockProps = useBlockProps( {
		className: `rootstuff-rel-related-content rootstuff-rel-layout-${ layout }`,
	} );

	const innerBlocksProps = useInnerBlocksProps(
		{},
		{ template: TEMPLATE }
	);

	if ( ! relType ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon="networking"
					label={ __( 'Related Content', 'rootstuff-relationships' ) }
					instructions={ __( 'Select a relationship to display connected content.', 'rootstuff-relationships' ) }
				>
					<SelectControl
						value={ relType }
						options={ options }
						onChange={ ( value ) => setAttributes( { relType: value } ) }
						__nextHasNoMarginBottom
					/>
				</Placeholder>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Relationship', 'rootstuff-relationships' ) }>
					<SelectControl
						label={ __( 'Relationship type', 'rootstuff-relationships' ) }
						value={ relType }
						options={ options }
						onChange={ ( value ) => setAttributes( { relType: value } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				<PanelBody title={ __( 'Layout', 'rootstuff-relationships' ) }>
					<div style={ { display: 'flex', flexDirection: 'column', gap: '16px' } }>
						<ButtonGroup aria-label={ __( 'Layout', 'rootstuff-relationships' ) }>
							{ LAYOUT_OPTIONS.map( ( opt ) => (
								<Button
									key={ opt.value }
									icon={ opt.icon }
									label={ opt.label }
									isPressed={ layout === opt.value }
									onClick={ () => setAttributes( { layout: opt.value } ) }
								/>
							) ) }
						</ButtonGroup>
						{ layout === 'grid' && (
							<>
								<RangeControl
									label={ __( 'Columns', 'rootstuff-relationships' ) }
									value={ columns }
									onChange={ ( value ) => setAttributes( { columns: value } ) }
									min={ 2 }
									max={ 4 }
									__nextHasNoMarginBottom
								/>
								<ToggleControl
									label={ __( 'Show thumbnail', 'rootstuff-relationships' ) }
									checked={ showThumbnail }
									onChange={ ( value ) => setAttributes( { showThumbnail: value } ) }
									__nextHasNoMarginBottom
								/>
							</>
						) }
						{ layout !== 'inline' && (
							<ToggleControl
								label={ __( 'Show excerpt', 'rootstuff-relationships' ) }
								checked={ showExcerpt }
								onChange={ ( value ) => setAttributes( { showExcerpt: value } ) }
								__nextHasNoMarginBottom
							/>
						) }
					</div>
				</PanelBody>
			</InspectorControls>
			<div { ...innerBlocksProps } />
			<p className="rootstuff-rel-related-content__placeholder">
				{ __( 'Related content will appear here on the frontend.', 'rootstuff-relationships' ) }
			</p>
		</div>
	);
}
