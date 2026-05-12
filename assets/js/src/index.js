import { registerPlugin } from '@wordpress/plugins';
import RelationshipPanel from './editor/relationship-panel';
import './editor/store';

const panels = window.rsRelationships?.panels || [];

if ( panels.length > 0 ) {
	registerPlugin( 'rootstuff-relationships', {
		render: () => (
			<>
				{ panels.map( ( panel ) => (
					<RelationshipPanel
						key={ `${ panel.relType }-${ panel.direction }` }
						relType={ panel.relType }
						direction={ panel.direction }
						label={ panel.label }
						postType={ panel.postType }
					/>
				) ) }
			</>
		),
	} );
}
