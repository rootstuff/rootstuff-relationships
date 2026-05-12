import { useCallback, useRef, useState } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { dragHandle } from '@wordpress/icons';
import { STORE_NAME } from './store';

export default function RelationshipList( { relType, side, postId, sortable } ) {
	const { removeConnection, saveConnections, reorderConnections } =
		useDispatch( STORE_NAME );

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

	const [ dragIndex, setDragIndex ] = useState( null );
	const [ overIndex, setOverIndex ] = useState( null );
	const dragNode = useRef( null );

	const handleDragStart = useCallback( ( e, index ) => {
		dragNode.current = e.currentTarget;
		setDragIndex( index );
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData( 'text/plain', String( index ) );
		requestAnimationFrame( () => {
			if ( dragNode.current ) {
				dragNode.current.classList.add( 'relatewp-relationship-list__item--dragging' );
			}
		} );
	}, [] );

	const handleDragOver = useCallback( ( e, index ) => {
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		if ( index !== overIndex ) {
			setOverIndex( index );
		}
	}, [ overIndex ] );

	const handleDrop = useCallback( ( e, toIndex ) => {
		e.preventDefault();
		if ( dragIndex !== null && dragIndex !== toIndex ) {
			reorderConnections( relType, postId, side, dragIndex, toIndex );
		}
		setDragIndex( null );
		setOverIndex( null );
		if ( dragNode.current ) {
			dragNode.current.classList.remove( 'relatewp-relationship-list__item--dragging' );
			dragNode.current = null;
		}
	}, [ dragIndex, relType, postId, side, reorderConnections ] );

	const handleDragEnd = useCallback( () => {
		setDragIndex( null );
		setOverIndex( null );
		if ( dragNode.current ) {
			dragNode.current.classList.remove( 'relatewp-relationship-list__item--dragging' );
			dragNode.current = null;
		}
	}, [] );

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
				{ connections.map( ( connection, index ) => {
					const obj = connection.connected_object;
					const isOver = overIndex === index && dragIndex !== index;

					let itemClass = 'relatewp-relationship-list__item';
					if ( isOver ) {
						itemClass += dragIndex < index
							? ' relatewp-relationship-list__item--drop-below'
							: ' relatewp-relationship-list__item--drop-above';
					}

					return (
						<li
							key={ obj.id }
							className={ itemClass }
							draggable={ !! sortable }
							onDragStart={ sortable ? ( e ) => handleDragStart( e, index ) : undefined }
							onDragOver={ sortable ? ( e ) => handleDragOver( e, index ) : undefined }
							onDrop={ sortable ? ( e ) => handleDrop( e, index ) : undefined }
							onDragEnd={ sortable ? handleDragEnd : undefined }
						>
							{ sortable && (
								<span className="relatewp-relationship-list__drag-handle">
									{ dragHandle }
								</span>
							) }
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
								size="small"
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
