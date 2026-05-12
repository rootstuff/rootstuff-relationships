import { useCallback } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from './store';

export default function RelationshipList( { relType, direction, postId } ) {
	const { removeConnection, saveConnections } = useDispatch( STORE_NAME );

	const connections = useSelect(
		( select ) =>
			select( STORE_NAME ).getConnections( relType, postId, direction ),
		[ relType, postId, direction ]
	);

	const isSaving = useSelect(
		( select ) =>
			select( STORE_NAME ).isSaving( relType, postId, direction ),
		[ relType, postId, direction ]
	);

	const isDirty = useSelect(
		( select ) =>
			select( STORE_NAME ).isDirty( relType, postId, direction ),
		[ relType, postId, direction ]
	);

	const handleRemove = useCallback(
		( itemId ) => {
			removeConnection( relType, postId, direction, itemId );
		},
		[ relType, postId, direction, removeConnection ]
	);

	const handleSave = useCallback( () => {
		saveConnections( relType, postId, direction );
	}, [ relType, postId, direction, saveConnections ] );

	if ( connections.length === 0 ) {
		return (
			<p className="rs-relationship-list__empty">
				{ __( 'No connections yet.', 'rootstuff-relationships' ) }
			</p>
		);
	}

	return (
		<div className="rs-relationship-list">
			<ul className="rs-relationship-list__items">
				{ connections.map( ( connection ) => {
					const obj = connection.connected_object;
					return (
						<li
							key={ obj.id }
							className="rs-relationship-list__item"
						>
							<span className="rs-relationship-list__item-title">
								{ obj.title ||
									__( '(no title)', 'rootstuff-relationships' ) }
							</span>
							<Button
								icon="no-alt"
								label={ __(
									'Remove connection',
									'rootstuff-relationships'
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
					className="rs-relationship-list__save"
				>
					{ isSaving
						? __( 'Saving…', 'rootstuff-relationships' )
						: __( 'Save Connections', 'rootstuff-relationships' ) }
				</Button>
			) }
			{ isSaving && <Spinner /> }
		</div>
	);
}
