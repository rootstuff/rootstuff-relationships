import { useCallback } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from './store';

export default function RelationshipList( { relType, side, postId } ) {
	const { removeConnection, saveConnections } = useDispatch( STORE_NAME );

	const connections = useSelect(
		( select ) =>
			select( STORE_NAME ).getConnections( relType, postId, side ),
		[ relType, postId, side ]
	);

	const isSaving = useSelect(
		( select ) =>
			select( STORE_NAME ).isSaving( relType, postId, side ),
		[ relType, postId, side ]
	);

	const isDirty = useSelect(
		( select ) =>
			select( STORE_NAME ).isDirty( relType, postId, side ),
		[ relType, postId, side ]
	);

	const handleRemove = useCallback(
		( itemId ) => {
			removeConnection( relType, postId, side, itemId );
		},
		[ relType, postId, side, removeConnection ]
	);

	const handleSave = useCallback( () => {
		saveConnections( relType, postId, side );
	}, [ relType, postId, side, saveConnections ] );

	if ( connections.length === 0 ) {
		return (
			<p className="relatewp-relationship-list__empty">
				{ __( 'No connections yet.', 'relatewp' ) }
			</p>
		);
	}

	return (
		<div className="relatewp-relationship-list">
			<ul className="relatewp-relationship-list__items">
				{ connections.map( ( connection ) => {
					const obj = connection.connected_object;
					return (
						<li
							key={ obj.id }
							className="relatewp-relationship-list__item"
						>
							<span className="relatewp-relationship-list__item-title">
								{ obj.title ||
									__( '(no title)', 'relatewp' ) }
							</span>
							<Button
								icon="no-alt"
								label={ __(
									'Remove connection',
									'relatewp'
								) }
								isSmall
								isDestructive
								onClick={ () => handleRemove( obj.id ) }
							/>
						</li>
					);
				} ) }
			</ul>
			{ isDirty && (
				<Button
					variant="primary"
					isBusy={ isSaving }
					disabled={ isSaving }
					onClick={ handleSave }
					className="relatewp-relationship-list__save"
				>
					{ isSaving
						? __( 'Saving…', 'relatewp' )
						: __( 'Save Connections', 'relatewp' ) }
				</Button>
			) }
			{ isSaving && <Spinner /> }
		</div>
	);
}
