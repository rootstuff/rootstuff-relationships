import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

const STORE_NAME = 'rootstuff/relationships';

const DEFAULT_STATE = {
	connections: {},
	searching: {},
	searchResults: {},
	saving: {},
	dirty: {},
};

function buildKey( relType, objectId, direction ) {
	return `${ relType }:${ objectId }:${ direction }`;
}

const store = createReduxStore( STORE_NAME, {
	reducer( state = DEFAULT_STATE, action ) {
		switch ( action.type ) {
			case 'SET_CONNECTIONS': {
				const key = buildKey(
					action.relType,
					action.objectId,
					action.direction
				);
				return {
					...state,
					connections: {
						...state.connections,
						[ key ]: action.connections,
					},
					dirty: { ...state.dirty, [ key ]: false },
				};
			}

			case 'ADD_CONNECTION': {
				const key = buildKey(
					action.relType,
					action.objectId,
					action.direction
				);
				const existing = state.connections[ key ] || [];
				if ( existing.find( ( c ) => c.connected_object.id === action.item.id ) ) {
					return state;
				}
				return {
					...state,
					connections: {
						...state.connections,
						[ key ]: [
							...existing,
							{
								connected_object: action.item,
								sort_order: existing.length,
							},
						],
					},
					dirty: { ...state.dirty, [ key ]: true },
				};
			}

			case 'REMOVE_CONNECTION': {
				const key = buildKey(
					action.relType,
					action.objectId,
					action.direction
				);
				const list = ( state.connections[ key ] || [] ).filter(
					( c ) => c.connected_object.id !== action.itemId
				);
				return {
					...state,
					connections: { ...state.connections, [ key ]: list },
					dirty: { ...state.dirty, [ key ]: true },
				};
			}

			case 'SET_SEARCHING': {
				const key = buildKey(
					action.relType,
					action.objectId,
					action.direction
				);
				return {
					...state,
					searching: { ...state.searching, [ key ]: action.value },
				};
			}

			case 'SET_SEARCH_RESULTS': {
				const key = buildKey(
					action.relType,
					action.objectId,
					action.direction
				);
				return {
					...state,
					searchResults: {
						...state.searchResults,
						[ key ]: action.results,
					},
				};
			}

			case 'SET_SAVING': {
				const key = buildKey(
					action.relType,
					action.objectId,
					action.direction
				);
				return {
					...state,
					saving: { ...state.saving, [ key ]: action.value },
				};
			}

			case 'MARK_CLEAN': {
				const key = buildKey(
					action.relType,
					action.objectId,
					action.direction
				);
				return {
					...state,
					dirty: { ...state.dirty, [ key ]: false },
				};
			}

			default:
				return state;
		}
	},

	actions: {
		setConnections( relType, objectId, direction, connections ) {
			return {
				type: 'SET_CONNECTIONS',
				relType,
				objectId,
				direction,
				connections,
			};
		},

		addConnection( relType, objectId, direction, item ) {
			return { type: 'ADD_CONNECTION', relType, objectId, direction, item };
		},

		removeConnection( relType, objectId, direction, itemId ) {
			return {
				type: 'REMOVE_CONNECTION',
				relType,
				objectId,
				direction,
				itemId,
			};
		},

		fetchConnections( relType, objectId, direction ) {
			return async ( { dispatch } ) => {
				try {
					const data = await apiFetch( {
						path: `/rootstuff-rel/v1/connections/${ relType }/${ objectId }?direction=${ direction }`,
					} );
					dispatch.setConnections(
						relType,
						objectId,
						direction,
						data.connections || []
					);
				} catch ( error ) {
					// eslint-disable-next-line no-console
					console.error( 'RS Relationships: failed to fetch connections', error );
				}
			};
		},

		searchPosts( relType, objectId, direction, query, exclude = [] ) {
			return async ( { dispatch } ) => {
				dispatch( {
					type: 'SET_SEARCHING',
					relType,
					objectId,
					direction,
					value: true,
				} );
				try {
					const params = new URLSearchParams( {
						rel_type: relType,
						direction,
						s: query,
						per_page: '10',
					} );
					exclude.forEach( ( id ) => params.append( 'exclude[]', String( id ) ) );

					const results = await apiFetch( {
						path: `/rootstuff-rel/v1/search?${ params.toString() }`,
					} );
					dispatch( {
						type: 'SET_SEARCH_RESULTS',
						relType,
						objectId,
						direction,
						results,
					} );
				} catch ( error ) {
					// eslint-disable-next-line no-console
					console.error( 'RS Relationships: search failed', error );
				} finally {
					dispatch( {
						type: 'SET_SEARCHING',
						relType,
						objectId,
						direction,
						value: false,
					} );
				}
			};
		},

		saveConnections( relType, objectId, direction ) {
			return async ( { dispatch, select } ) => {
				const key = buildKey( relType, objectId, direction );
				const connections = select.getConnections( relType, objectId, direction );
				const connectedIds = connections.map( ( c ) => c.connected_object.id );

				dispatch( {
					type: 'SET_SAVING',
					relType,
					objectId,
					direction,
					value: true,
				} );

				try {
					await apiFetch( {
						path: `/rootstuff-rel/v1/connections/${ relType }/${ objectId }/sync`,
						method: 'POST',
						data: {
							direction,
							connected_ids: connectedIds,
						},
					} );
					dispatch( { type: 'MARK_CLEAN', relType, objectId, direction } );
				} catch ( error ) {
					// eslint-disable-next-line no-console
					console.error( 'RS Relationships: save failed', error );
				} finally {
					dispatch( {
						type: 'SET_SAVING',
						relType,
						objectId,
						direction,
						value: false,
					} );
				}
			};
		},
	},

	selectors: {
		getConnections( state, relType, objectId, direction ) {
			const key = buildKey( relType, objectId, direction );
			return state.connections[ key ] || [];
		},

		isSearching( state, relType, objectId, direction ) {
			const key = buildKey( relType, objectId, direction );
			return state.searching[ key ] || false;
		},

		getSearchResults( state, relType, objectId, direction ) {
			const key = buildKey( relType, objectId, direction );
			return state.searchResults[ key ] || [];
		},

		isSaving( state, relType, objectId, direction ) {
			const key = buildKey( relType, objectId, direction );
			return state.saving[ key ] || false;
		},

		isDirty( state, relType, objectId, direction ) {
			const key = buildKey( relType, objectId, direction );
			return state.dirty[ key ] || false;
		},
	},
} );

register( store );

export default store;
export { STORE_NAME };
