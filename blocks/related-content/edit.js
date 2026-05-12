import { useEffect, useState } from '@wordpress/element';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
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

export default function Edit( { attributes, setAttributes } ) {
	const { relType } = attributes;
	const [ relationships, setRelationships ] = useState( [] );

	useEffect( () => {
		apiFetch( { path: '/rootstuff-rel/v1/relationships' } )
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
		className: 'rs-related-content',
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
			</InspectorControls>
			<div { ...innerBlocksProps } />
			<p className="rs-related-content__placeholder">
				{ __( 'Related content will appear here on the frontend.', 'rootstuff-relationships' ) }
			</p>
		</div>
	);
}
