import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { TextControl, Spinner } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from './store';

export default function RelationshipSelector( {
	relType,
	side,
	postId,
} ) {
	const [ query, setQuery ] = useState( '' );
	const debounceRef = useRef( null );

	const { searchPosts, addConnection } = useDispatch( STORE_NAME );

	const connections = useSelect(
		( select ) =>
			select( STORE_NAME ).getConnections( relType, postId, side ),
		[ relType, postId, side ]
	);

	const searchResults = useSelect(
		( select ) =>
			select( STORE_NAME ).getSearchResults(
				relType,
				postId,
				side
			),
		[ relType, postId, side ]
	);

	const isSearching = useSelect(
		( select ) =>
			select( STORE_NAME ).isSearching( relType, postId, side ),
		[ relType, postId, side ]
	);

	const connectedIds = connections.map( ( c ) => c.connected_object.id );

	const handleSearch = useCallback(
		( value ) => {
			setQuery( value );

			if ( debounceRef.current ) {
				clearTimeout( debounceRef.current );
			}

			if ( value.length < 2 ) {
				return;
			}

			debounceRef.current = setTimeout( () => {
				searchPosts(
					relType,
					postId,
					side,
					value,
					connectedIds
				);
			}, 300 );
		},
		[ relType, postId, side, connectedIds, searchPosts ]
	);

	useEffect( () => {
		return () => {
			if ( debounceRef.current ) {
				clearTimeout( debounceRef.current );
			}
		};
	}, [] );

	const handleSelect = useCallback(
		( item ) => {
			addConnection( relType, postId, side, item );
			setQuery( '' );
		},
		[ relType, postId, side, addConnection ]
	);

	const filteredResults = searchResults.filter(
		( r ) => ! connectedIds.includes( r.id )
	);

	return (
		<div className="rootstuff-rel-relationship-selector">
			<TextControl
				placeholder={ __( 'Search to connect…', 'rootstuff-relationships' ) }
				value={ query }
				onChange={ handleSearch }
				__nextHasNoMarginBottom
			/>
			{ isSearching && (
				<div className="rootstuff-rel-relationship-selector__spinner">
					<Spinner />
				</div>
			) }
			{ query.length >= 2 && filteredResults.length > 0 && (
				<ul className="rootstuff-rel-relationship-selector__results">
					{ filteredResults.map( ( item ) => (
						<li key={ item.id }>
							<button
								type="button"
								className="rootstuff-rel-relationship-selector__result-item"
								onClick={ () => handleSelect( item ) }
							>
								<span className="rootstuff-rel-relationship-selector__result-title">
									{ item.title || __( '(no title)', 'rootstuff-relationships' ) }
								</span>
								{ item.status !== 'publish' && (
									<span className="rootstuff-rel-relationship-selector__result-status">
										{ item.status }
									</span>
								) }
							</button>
						</li>
					) ) }
				</ul>
			) }
			{ query.length >= 2 &&
				! isSearching &&
				filteredResults.length === 0 && (
					<p className="rootstuff-rel-relationship-selector__no-results">
						{ __( 'No results found.', 'rootstuff-relationships' ) }
					</p>
				) }
		</div>
	);
}
