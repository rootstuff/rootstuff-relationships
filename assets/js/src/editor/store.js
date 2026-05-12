import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

const STORE_NAME = 'relatewp/store';

const DEFAULT_STATE = {
	connections: {},
	searching: {},
	searchResults: {},
	saving: {},
	dirty: {},
};

function buildKey( relType, objectId, side ) {
	return `${ relType }:${ objectId }:${ side }`;
}

const store = createReduxStore( STORE_NAME, {
	reducer( state = DEFAULT_STATE, action ) {
		switch ( action.type ) {
			case 'SET_CONNECTIONS': {
				const key = buildKey(
					action.relType,
					action.objectId,
					action.side
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
					action.side
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
					action.side
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
					action.side
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
					action.side
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
					action.side
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
					action.side
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
		setConnections( relType, objectId, side, connections ) {
			return {
				type: 'SET_CONNECTIONS',
				relType,
				objectId,
				side,
				connections,
			};
		},

		addConnection( relType, objectId, side, item ) {
			return { type: 'ADD_CONNECTION', relType, objectId, side, item };
		},

		removeConnection( relType, objectId, side, itemId ) {
			return {
				type: 'REMOVE_CONNECTION',
				relType,
				objectId,
				side,
				itemId,
			};
		},

		fetchConnections( relType, objectId, side ) {
			return async ( { dispatch } ) => {
				try {
					const params = side ? `?side=${ side }` : '';
					const data = await apiFetch( {
						path: `/relatewp/v1/connections/${ relType }/${ objectId }${ params }`,
					} );
					dispatch.setConnections(
						relType,
						objectId,
						side,
						data.connections || []
					);
				} catch ( error ) {
					// eslint-disable-next-line no-console
					console.error( 'RelateWP: failed to fetch connections', error );
				}
			};
		},

		searchPosts( relType, objectId, side, query, exclude = [] ) {
			return async ( { dispatch } ) => {
				dispatch( {
					type: 'SET_SEARCHING',
					relType,
					objectId,
					side,
					value: true,
				} );
				try {
					const params = new URLSearchParams( {
						rel_type: relType,
						side,
						s: query,
						per_page: '10',
					} );
					exclude.forEach( ( id ) => params.append( 'exclude[]', String( id ) ) );

					const results = await apiFetch( {
						path: `/relatewp/v1/search?${ params.toString() }`,
					} );
					dispatch( {
						type: 'SET_SEARCH_RESULTS',
						relType,
						objectId,
						side,
						results,
					} );
				} catch ( error ) {
					// eslint-disable-next-line no-console
					console.error( 'RelateWP: search failed', error );
				} finally {
					dispatch( {
						type: 'SET_SEARCHING',
						relType,
						objectId,
						side,
						value: false,
					} );
				}
			};
		},

		saveConnections( relType, objectId, side ) {
			return async ( { dispatch, select } ) => {
				const connections = select.getConnections( relType, objectId, side );
				const connectedIds = connections.map( ( c ) => c.connected_object.id );

				dispatch( {
					type: 'SET_SAVING',
					relType,
					objectId,
					side,
					value: true,
				} );

				try {
					await apiFetch( {
						path: `/relatewp/v1/connections/${ relType }/${ objectId }/sync`,
						method: 'POST',
						data: {
							side,
							connected_ids: connectedIds,
						},
					} );
					dispatch( { type: 'MARK_CLEAN', relType, objectId, side } );
				} catch ( error ) {
					// eslint-disable-next-line no-console
					console.error( 'RelateWP: save failed', error );
				} finally {
					dispatch( {
						type: 'SET_SAVING',
						relType,
						objectId,
						side,
						value: false,
					} );
				}
			};
		},
	},

	selectors: {
		getConnections( state, relType, objectId, side ) {
			const key = buildKey( relType, objectId, side );
			return state.connections[ key ] || [];
		},

		isSearching( state, relType, objectId, side ) {
			const key = buildKey( relType, objectId, side );
			return state.searching[ key ] || false;
		},

		getSearchResults( state, relType, objectId, side ) {
			const key = buildKey( relType, objectId, side );
			return state.searchResults[ key ] || [];
		},

		isSaving( state, relType, objectId, side ) {
			const key = buildKey( relType, objectId, side );
			return state.saving[ key ] || false;
		},

		isDirty( state, relType, objectId, side ) {
			const key = buildKey( relType, objectId, side );
			return state.dirty[ key ] || false;
		},
	},
} );

register( store );

export default store;
export { STORE_NAME };
