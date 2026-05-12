import { useEffect } from '@wordpress/element';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect, useDispatch, subscribe } from '@wordpress/data';
import RelationshipSelector from './relationship-selector';
import RelationshipList from './relationship-list';
import { STORE_NAME } from './store';

export default function RelationshipPanel( {
	relType,
	direction,
	label,
	postType,
} ) {
	const postId = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostId(),
		[]
	);

	const { fetchConnections, saveConnections } = useDispatch( STORE_NAME );

	const isDirty = useSelect(
		( select ) =>
			select( STORE_NAME ).isDirty( relType, postId, direction ),
		[ relType, postId, direction ]
	);

	useEffect( () => {
		if ( postId ) {
			fetchConnections( relType, postId, direction );
		}
	}, [ postId, relType, direction, fetchConnections ] );

	useEffect( () => {
		if ( ! postId ) {
			return;
		}

		let wasSaving = false;

		const unsubscribe = subscribe( () => {
			const editor = wp.data.select( 'core/editor' );
			const isSavingPost = editor.isSavingPost();
			const isAutosaving = editor.isAutosavingPost();

			if ( isSavingPost && ! isAutosaving && ! wasSaving && isDirty ) {
				wasSaving = true;
				saveConnections( relType, postId, direction );
			}

			if ( ! isSavingPost ) {
				wasSaving = false;
			}
		} );

		return unsubscribe;
	}, [ postId, relType, direction, isDirty, saveConnections ] );

	if ( ! postId ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name={ `rs-rel-${ relType }-${ direction }` }
			title={ label }
			className="rs-relationship-panel"
		>
			<RelationshipList
				relType={ relType }
				direction={ direction }
				postId={ postId }
			/>
			<RelationshipSelector
				relType={ relType }
				direction={ direction }
				postId={ postId }
			/>
		</PluginDocumentSettingPanel>
	);
}
