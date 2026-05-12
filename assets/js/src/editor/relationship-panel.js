import { useEffect } from '@wordpress/element';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect, useDispatch, subscribe } from '@wordpress/data';
import RelationshipSelector from './relationship-selector';
import RelationshipList from './relationship-list';
import { STORE_NAME } from './store';

export default function RelationshipPanel( {
	relType,
	side,
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
			select( STORE_NAME ).isDirty( relType, postId, side ),
		[ relType, postId, side ]
	);

	useEffect( () => {
		if ( postId ) {
			fetchConnections( relType, postId, side );
		}
	}, [ postId, relType, side, fetchConnections ] );

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
				saveConnections( relType, postId, side );
			}

			if ( ! isSavingPost ) {
				wasSaving = false;
			}
		} );

		return unsubscribe;
	}, [ postId, relType, side, isDirty, saveConnections ] );

	if ( ! postId ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name={ `relatewp-rel-${ relType }-${ side }` }
			title={ label }
			className="relatewp-relationship-panel"
		>
			<RelationshipList
				relType={ relType }
				side={ side }
				postId={ postId }
			/>
			<RelationshipSelector
				relType={ relType }
				side={ side }
				postId={ postId }
			/>
		</PluginDocumentSettingPanel>
	);
}
