import { registerPlugin } from '@wordpress/plugins';
import RelationshipPanel from './editor/relationship-panel';
import './editor/store';

const panels = window.relateWP?.panels || [];

if ( panels.length > 0 ) {
	registerPlugin( 'relatewp', {
		render: () => (
			<>
				{ panels.map( ( panel ) => (
					<RelationshipPanel
						key={ `${ panel.relType }-${ panel.side }` }
						relType={ panel.relType }
						side={ panel.side }
						label={ panel.label }
						postType={ panel.postType }
						sortable={ panel.sortable }
					/>
				) ) }
			</>
		),
	} );
}
