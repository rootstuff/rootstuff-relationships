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
			placeholder: __( 'Related Content', 'relatewp' ),
		},
	],
];

export default function Edit( { attributes, setAttributes } ) {
	const { relType } = attributes;
	const [ relationships, setRelationships ] = useState( [] );

	useEffect( () => {
		apiFetch( { path: '/relatewp/v1/relationships' } )
			.then( ( data ) => setRelationships( data || [] ) )
			.catch( () => {} );
	}, [] );

	const options = [
		{ label: __( '— Select relationship —', 'relatewp' ), value: '' },
		...relationships.map( ( rel ) => ( {
			label: `${ rel.labels.from } / ${ rel.labels.to } (${ rel.key })`,
			value: rel.key,
		} ) ),
	];

	const blockProps = useBlockProps( {
		className: 'relatewp-related-content',
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
					label={ __( 'Related Content', 'relatewp' ) }
					instructions={ __( 'Select a relationship to display connected content.', 'relatewp' ) }
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
				<PanelBody title={ __( 'Relationship', 'relatewp' ) }>
					<SelectControl
						label={ __( 'Relationship type', 'relatewp' ) }
						value={ relType }
						options={ options }
						onChange={ ( value ) => setAttributes( { relType: value } ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...innerBlocksProps } />
			<p className="relatewp-related-content__placeholder">
				{ __( 'Related content will appear here on the frontend.', 'relatewp' ) }
			</p>
		</div>
	);
}
