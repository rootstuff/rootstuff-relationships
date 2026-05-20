/**
 * Rootstuff Relationships classic-editor fallback metabox.
 *
 * Progressively enhances each `.rootstuff-relationships-mb` root with a two-zone UI:
 *   - Connected items (with remove + optional reorder)
 *   - Available items (with live search + add buttons)
 *
 * State is mirrored to a flat list of hidden inputs inside `.rootstuff-relationships-mb__hidden`,
 * which the existing PHP `save()` handler reads back on form submit.
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function el( tag, props, children ) {
		var node = document.createElement( tag );
		if ( props ) {
			Object.keys( props ).forEach( function ( k ) {
				if ( k === 'class' ) {
					node.className = props[ k ];
				} else if ( k === 'text' ) {
					node.textContent = props[ k ];
				} else if ( k === 'html' ) {
					node.innerHTML = props[ k ];
				} else if ( k.indexOf( 'on' ) === 0 && typeof props[ k ] === 'function' ) {
					node.addEventListener( k.slice( 2 ), props[ k ] );
				} else if ( props[ k ] !== null && props[ k ] !== undefined ) {
					node.setAttribute( k, props[ k ] );
				}
			} );
		}
		if ( children ) {
			children.forEach( function ( c ) {
				if ( c ) {
					node.appendChild( c );
				}
			} );
		}
		return node;
	}

	function init( root ) {
		var dataEl     = root.querySelector( '.rootstuff-relationships-mb__data' );
		var mount      = root.querySelector( '.rootstuff-relationships-mb__mount' );
		var hiddenWrap = root.querySelector( '.rootstuff-relationships-mb__hidden' );
		if ( ! dataEl || ! mount || ! hiddenWrap ) {
			return;
		}

		var data;
		try {
			data = JSON.parse( dataEl.textContent );
		} catch ( e ) {
			return;
		}

		var labels    = data.labels || {};
		var fieldName = data.fieldName;
		var sortable  = !! data.sortable;
		var connected = ( data.connected || [] ).map( function ( i ) {
			return { id: +i.id, title: String( i.title || '' ) };
		} );
		var available = ( data.candidates || [] ).map( function ( i ) {
			return { id: +i.id, title: String( i.title || '' ) };
		} );

		var countEl, connectedList, availableList, searchInput;

		function syncHidden() {
			hiddenWrap.innerHTML = '';
			connected.forEach( function ( item ) {
				var input = document.createElement( 'input' );
				input.type  = 'hidden';
				input.name  = fieldName;
				input.value = String( item.id );
				hiddenWrap.appendChild( input );
			} );
		}

		function renderConnected() {
			connectedList.innerHTML = '';
			connected.forEach( function ( item, index ) {
				var actions = el( 'span', { 'class': 'rootstuff-relationships-mb__item-actions' } );

				if ( sortable ) {
					var upBtn = el( 'button', {
						type: 'button',
						'class': 'rootstuff-relationships-mb__btn rootstuff-relationships-mb__btn--up',
						title: labels.moveUp || 'Move up',
						'aria-label': labels.moveUp || 'Move up',
						html: '\u2191',
						onclick: function () { move( index, -1 ); }
					} );
					if ( index === 0 ) {
						upBtn.setAttribute( 'disabled', 'disabled' );
					}
					actions.appendChild( upBtn );

					var downBtn = el( 'button', {
						type: 'button',
						'class': 'rootstuff-relationships-mb__btn rootstuff-relationships-mb__btn--down',
						title: labels.moveDown || 'Move down',
						'aria-label': labels.moveDown || 'Move down',
						html: '\u2193',
						onclick: function () { move( index, 1 ); }
					} );
					if ( index === connected.length - 1 ) {
						downBtn.setAttribute( 'disabled', 'disabled' );
					}
					actions.appendChild( downBtn );
				}

				var removeBtn = el( 'button', {
					type: 'button',
					'class': 'rootstuff-relationships-mb__btn rootstuff-relationships-mb__btn--remove',
					title: labels.remove || 'Remove',
					'aria-label': labels.remove || 'Remove',
					html: '\u00d7',
					onclick: function () { remove( index ); }
				} );
				actions.appendChild( removeBtn );

				connectedList.appendChild(
					el( 'li', { 'data-id': item.id }, [
						el( 'span', { 'class': 'rootstuff-relationships-mb__item-title', text: item.title } ),
						actions
					] )
				);
			} );
			countEl.textContent = String( connected.length );
		}

		function renderAvailable() {
			availableList.innerHTML = '';
			var filter = ( searchInput.value || '' ).toLowerCase().trim();
			var shown  = 0;

			available.forEach( function ( item ) {
				if ( filter && item.title.toLowerCase().indexOf( filter ) === -1 ) {
					return;
				}
				shown++;

				var addBtn = el( 'button', {
					type: 'button',
					'class': 'rootstuff-relationships-mb__btn rootstuff-relationships-mb__btn--add',
					title: labels.add || 'Add',
					'aria-label': labels.add || 'Add',
					html: '+',
					onclick: function () { add( item.id ); }
				} );

				availableList.appendChild(
					el( 'li', { 'data-id': item.id }, [
						el( 'span', { 'class': 'rootstuff-relationships-mb__item-title', text: item.title } ),
						addBtn
					] )
				);
			} );

			if ( filter && shown === 0 ) {
				availableList.appendChild(
					el( 'li', { 'class': 'rootstuff-relationships-mb__empty', text: labels.noResults || 'No matches.' } )
				);
			}
		}

		function add( id ) {
			var idx = -1;
			for ( var i = 0; i < available.length; i++ ) {
				if ( available[ i ].id === id ) { idx = i; break; }
			}
			if ( idx === -1 ) { return; }
			var item = available.splice( idx, 1 )[ 0 ];
			connected.push( item );
			syncHidden();
			renderConnected();
			renderAvailable();
		}

		function remove( index ) {
			var item = connected.splice( index, 1 )[ 0 ];
			if ( ! item ) { return; }
			var insertAt = 0;
			while (
				insertAt < available.length &&
				available[ insertAt ].title.toLowerCase() < item.title.toLowerCase()
			) {
				insertAt++;
			}
			available.splice( insertAt, 0, item );
			syncHidden();
			renderConnected();
			renderAvailable();
		}

		function move( index, delta ) {
			var target = index + delta;
			if ( target < 0 || target >= connected.length ) { return; }
			var tmp = connected[ index ];
			connected[ index ]  = connected[ target ];
			connected[ target ] = tmp;
			syncHidden();
			renderConnected();
		}

		countEl = el( 'span', { 'class': 'rootstuff-relationships-mb__count', text: String( connected.length ) } );

		connectedList = el( 'ul', {
			'class': 'rootstuff-relationships-mb__list rootstuff-relationships-mb__list--connected',
			'data-empty': labels.empty || 'No connections yet.'
		} );

		var connectedSection = el( 'div', { 'class': 'rootstuff-relationships-mb__connected' }, [
			el( 'div', { 'class': 'rootstuff-relationships-mb__section-head' }, [
				el( 'span', { 'class': 'rootstuff-relationships-mb__section-title', text: labels.connected || 'Connected' } ),
				countEl
			] ),
			connectedList
		] );

		searchInput = el( 'input', {
			type: 'search',
			'class': 'rootstuff-relationships-mb__search',
			placeholder: labels.search || 'Search\u2026',
			oninput: function () { renderAvailable(); }
		} );

		availableList = el( 'ul', {
			'class': 'rootstuff-relationships-mb__list rootstuff-relationships-mb__list--available',
			'data-empty': labels.poolEmpty || 'Nothing available to connect.'
		} );

		var addSection = el( 'div', { 'class': 'rootstuff-relationships-mb__add' }, [
			el( 'label', { 'class': 'rootstuff-relationships-mb__add-label', text: labels.addLabel || 'Add new' } ),
			searchInput,
			availableList
		] );

		if ( data.atLimit && labels.poolLimit ) {
			addSection.appendChild(
				el( 'p', { 'class': 'rootstuff-relationships-mb__limit-note', text: labels.poolLimit } )
			);
		}

		mount.innerHTML = '';
		mount.appendChild(
			el( 'div', { 'class': 'rootstuff-relationships-mb__inner' }, [ connectedSection, addSection ] )
		);

		renderConnected();
		renderAvailable();
	}

	ready( function () {
		var roots = document.querySelectorAll( '.rootstuff-relationships-mb' );
		for ( var i = 0; i < roots.length; i++ ) {
			init( roots[ i ] );
		}
	} );
} )();
