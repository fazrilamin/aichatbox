/* AI Assistant & Chatbox admin: settings tabs, Test connection, and the batched Reindex with a progress bar. */
( function () {
	'use strict';

	// Settings tabs: all panels are in one form, tabs only show/hide them.
	var tabs = document.querySelectorAll( '.aisa-tabs [data-tab]' );
	Array.prototype.forEach.call( tabs, function ( tab ) {
		tab.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var name = tab.getAttribute( 'data-tab' );
			Array.prototype.forEach.call( tabs, function ( t ) {
				t.classList.toggle( 'nav-tab-active', t === tab );
			} );
			Array.prototype.forEach.call( document.querySelectorAll( '.aisa-tab-panel' ), function ( p ) {
				p.hidden = p.getAttribute( 'data-panel' ) !== name;
			} );
			var referer = document.querySelector( '.aisa-referer' );
			if ( referer ) {
				referer.value = tab.getAttribute( 'href' );
			}
			if ( window.history.replaceState ) {
				window.history.replaceState( null, '', tab.getAttribute( 'href' ) );
			}
		} );
	} );

	var wrap = document.querySelector( '.aisa-admin[data-aisa]' );
	if ( ! wrap ) {
		return;
	}
	var cfg = JSON.parse( wrap.getAttribute( 'data-aisa' ) );
	var text = cfg.text || {};

	// Fill %1$s / %1$d style placeholders (as in the PHP translations) with the given values.
	function fmt( str ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( str ).replace( /%(?:(\d+)\$)?[sd]/g, function ( m, n ) {
			return String( args[ n ? n - 1 : i++ ] );
		} );
	}

	function call( path, method, body ) {
		return window.fetch( cfg.rest + path, {
			method: method,
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: body ? JSON.stringify( body ) : undefined
		} ).then( function ( res ) {
			return res.json().then( function ( j ) {
				if ( ! res.ok ) {
					throw new Error( j && j.message ? j.message : 'HTTP ' + res.status );
				}
				return j;
			} );
		} );
	}

	function line( box, ok, text ) {
		var p = document.createElement( 'p' );
		p.className = ok ? 'aisa-ok' : 'aisa-error';
		p.textContent = ( ok ? '✓ ' : '✗ ' ) + text;
		box.appendChild( p );
	}

	var test = wrap.querySelector( '[data-aisa-test]' );
	if ( test ) {
		test.addEventListener( 'click', function () {
			var box = wrap.querySelector( '.aisa-test-result' );
			box.textContent = text.testing;
			test.disabled = true;
			call( 'test', 'POST', {} ).then( function ( j ) {
				box.textContent = '';
				line( box, j.chat.ok, j.chat.message );
				if ( j.embed ) {
					line( box, j.embed.ok, j.embed.message );
				}
			} ).catch( function ( e ) {
				box.textContent = '';
				line( box, false, e.message );
			} ).then( function () {
				test.disabled = false;
			} );
		} );
	}

	var reindex = wrap.querySelector( '[data-aisa-reindex]' );
	if ( reindex ) {
		var bar = wrap.querySelector( '.aisa-progress' );
		var result = wrap.querySelector( '.aisa-reindex-result' );
		var stats = wrap.querySelector( '[data-aisa-stats]' );
		var show = function ( s ) {
			bar.hidden = false;
			bar.value = s.total ? Math.round( ( s.done / s.total ) * 100 ) : 100;
			result.textContent = s.done + ' / ' + s.total;
			stats.textContent = fmt( text.stats, s.docs, s.chunks, s.last );
		};
		var step = function ( body ) {
			return call( 'reindex', 'POST', body ).then( function ( s ) {
				show( s );
				if ( ! s.finished ) {
					return new Promise( function ( resolve ) {
						setTimeout( resolve, 150 );
					} ).then( function () {
						return step( {} );
					} );
				}
				return s;
			} );
		};
		reindex.addEventListener( 'click', function () {
			reindex.disabled = true;
			result.textContent = '';
			step( { action: 'start' } ).then( function ( s ) {
				result.textContent = '';
				line( result, ! s.errors.length, fmt( text.indexed, s.total ) );
				s.errors.forEach( function ( err ) {
					line( result, false, err );
				} );
			} ).catch( function ( e ) {
				line( result, false, e.message );
			} ).then( function () {
				reindex.disabled = false;
			} );
		} );
	}
}() );
