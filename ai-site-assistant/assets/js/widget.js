/* AI Assistant & Chatbox widget: a floating button that opens a chat dialog, and/or a results panel
   attached beneath the site's own search bars. Settings arrive in #aisa-root[data-config].
   Themes can open it from any element with [data-aisa-open] or window.aisaOpen( question ). */
( function () {
	'use strict';

	var root = document.getElementById( 'aisa-root' );
	if ( ! root ) {
		return;
	}
	var cfg;
	try {
		cfg = JSON.parse( root.getAttribute( 'data-config' ) );
	} catch ( e ) {
		return;
	}
	var T = cfg.text;
	var STORE = 'aisa-chat';
	var state = { messages: [], busy: false, session: '', open: false, lastQuery: '' };
	var els = {};
	var anchor = null; // Site search input the panel is attached to.
	var opener = null; // Element to return focus to.

	/* Helpers ------------------------------------------------------------ */

	function storage( key, value ) {
		try {
			if ( undefined === value ) {
				return window.sessionStorage.getItem( key );
			}
			window.sessionStorage.setItem( key, value );
		} catch ( e ) {}
		return null;
	}

	function el( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) {
			n.className = cls;
		}
		if ( text ) {
			n.textContent = text;
		}
		return n;
	}

	function icon( name ) {
		var paths = {
			spark: 'M12 2l1.9 5.8L20 10l-6.1 2.2L12 18l-1.9-5.8L4 10l6.1-2.2zM19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z',
			close: 'M6 6l12 12M18 6L6 18',
			reset: 'M4 12a8 8 0 1 0 2.3-5.6M4 4v4h4',
			send: 'M4 12l16-8-6 16-2.5-6.5z',
			up: 'M7 11v9H4v-9zM7 11l4-8a2 2 0 0 1 2 2v4h5.5a2 2 0 0 1 2 2.4l-1.3 6A2 2 0 0 1 17.2 20H7',
			down: 'M17 13V4h3v9zM17 13l-4 8a2 2 0 0 1-2-2v-4H5.5a2 2 0 0 1-2-2.4l1.3-6A2 2 0 0 1 6.8 4H17'
		};
		var filled = 'spark' === name;
		return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="' + ( filled ? 'currentColor' : 'none' ) + '" stroke="' + ( filled ? 'none' : 'currentColor' ) + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="' + paths[ name ] + '"/></svg>';
	}

	function esc( s ) {
		return String( s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function wide() {
		return window.matchMedia( '(min-width: 640px)' ).matches;
	}

	function uuid() {
		if ( window.crypto && window.crypto.randomUUID ) {
			return window.crypto.randomUUID();
		}
		return 'x' + Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 12 );
	}

	// Light or dark, from the page background when set to automatic.
	function scheme() {
		if ( 'light' === cfg.scheme || 'dark' === cfg.scheme ) {
			return cfg.scheme;
		}
		var nodes = [ document.body, document.documentElement ];
		for ( var i = 0; i < nodes.length; i++ ) {
			var m = window.getComputedStyle( nodes[ i ] ).backgroundColor.match( /rgba?\(([^)]+)\)/ );
			if ( m ) {
				var p = m[ 1 ].split( ',' ).map( parseFloat );
				if ( p.length < 4 || p[ 3 ] > 0.1 ) {
					return ( 0.2126 * p[ 0 ] + 0.7152 * p[ 1 ] + 0.0722 * p[ 2 ] ) / 255 < 0.45 ? 'dark' : 'light';
				}
			}
		}
		return 'light';
	}

	/* Markdown (escaped first; only bold, code, links, lists and citations) */

	function inline( s, sources ) {
		s = esc( s );
		s = s.replace( /`([^`]+)`/g, '<code>$1</code>' );
		s = s.replace( /\*\*([^*]+)\*\*/g, '<strong>$1</strong>' );
		s = s.replace( /\[([^\]]+)\]\(([^)\s]+)\)/g, function ( all, text, url ) {
			return /^(https?:\/\/|\/(?!\/)|mailto:|tel:)/i.test( url ) ? '<a href="' + url + '" target="_blank" rel="noopener">' + text + '</a>' : text;
		} );
		s = s.replace( /\[(\d{1,2})\]/g, function ( all, n ) {
			var src = sources && sources[ n - 1 ];
			if ( ! src ) {
				return '';
			}
			return src.url
				? '<sup class="aisa-cite"><a href="' + esc( src.url ) + '" target="_blank" rel="noopener" title="' + esc( src.title ) + '">' + n + '</a></sup>'
				: '<sup class="aisa-cite" title="' + esc( src.title ) + '">' + n + '</sup>';
		} );
		return s;
	}

	function markdown( text, sources ) {
		text = String( text ).replace( /\[\[HANDOFF\]\]/g, '' ).replace( /\[\[[A-Z]*\]?$/, '' );
		var html = '';
		var list = null;
		var para = [];
		var code = null;
		function flush() {
			if ( para.length ) {
				html += '<p>' + para.map( function ( l ) {
					return inline( l, sources );
				} ).join( '<br>' ) + '</p>';
				para = [];
			}
		}
		function closeList() {
			if ( list ) {
				html += '</' + list + '>';
				list = null;
			}
		}
		text.split( '\n' ).forEach( function ( line ) {
			var m;
			if ( /^\s*```/.test( line ) ) {
				if ( null === code ) {
					flush();
					closeList();
					code = [];
				} else {
					html += '<pre><code>' + esc( code.join( '\n' ) ) + '</code></pre>';
					code = null;
				}
				return;
			}
			if ( null !== code ) {
				code.push( line );
				return;
			}
			if ( ( m = line.match( /^\s*[-*•]\s+(.*)/ ) ) || ( m = line.match( /^\s*(\d+)[.)]\s+(.*)/ ) ) ) {
				var type = m.length > 2 ? 'ol' : 'ul';
				flush();
				if ( list !== type ) {
					closeList();
					html += '<' + type + '>';
					list = type;
				}
				html += '<li>' + inline( m[ m.length - 1 ], sources ) + '</li>';
				return;
			}
			if ( ( m = line.match( /^#{1,6}\s+(.*)/ ) ) ) {
				flush();
				closeList();
				html += '<p><strong>' + inline( m[ 1 ], sources ) + '</strong></p>';
				return;
			}
			if ( ! line.trim() ) {
				flush();
				closeList();
				return;
			}
			closeList();
			para.push( line );
		} );
		if ( null !== code ) {
			html += '<pre><code>' + esc( code.join( '\n' ) ) + '</code></pre>';
		}
		flush();
		closeList();
		return html;
	}

	/* State ---------------------------------------------------------------- */

	function load() {
		state.session = storage( 'aisa-session' ) || uuid();
		storage( 'aisa-session', state.session );
		try {
			var saved = JSON.parse( storage( STORE ) || '[]' );
			if ( Array.isArray( saved ) ) {
				state.messages = saved;
			}
		} catch ( e ) {}
	}

	function save() {
		storage( STORE, JSON.stringify( state.messages.filter( function ( m ) {
			return ! m.pending;
		} ).slice( -20 ) ) );
	}

	/* UI ----------------------------------------------------------------- */

	function build() {
		if ( els.wrap ) {
			return;
		}
		var wrap = el( 'div', 'aisa aisa--' + scheme() );
		wrap.id = 'aisa-widget';
		var overlay = el( 'div', 'aisa-overlay' );
		overlay.hidden = true;
		var panel = el( 'div', 'aisa-panel' );
		panel.id = 'aisa-panel';
		panel.setAttribute( 'role', 'dialog' );
		panel.setAttribute( 'aria-labelledby', 'aisa-title' );

		var head = el( 'div', 'aisa-head' );
		var title = el( 'h2', 'aisa-title' );
		title.id = 'aisa-title';
		title.innerHTML = icon( 'spark' ) + '<span>' + esc( T.title ) + '</span>';
		var reset = el( 'button', 'aisa-icon-btn' );
		reset.type = 'button';
		reset.innerHTML = icon( 'reset' );
		reset.setAttribute( 'aria-label', T.newChat );
		reset.title = T.newChat;
		var close = el( 'button', 'aisa-icon-btn' );
		close.type = 'button';
		close.innerHTML = icon( 'close' );
		close.setAttribute( 'aria-label', T.close );
		close.title = T.close;
		head.appendChild( title );
		head.appendChild( reset );
		head.appendChild( close );

		var log = el( 'div', 'aisa-log' );
		var live = el( 'div', 'aisa-sr' );
		live.setAttribute( 'aria-live', 'polite' );

		var form = el( 'form', 'aisa-form' );
		var hp = el( 'input', 'aisa-hp' );
		hp.name = 'aisa_hp';
		hp.tabIndex = -1;
		hp.autocomplete = 'off';
		hp.setAttribute( 'aria-hidden', 'true' );
		var label = el( 'label', 'aisa-sr', T.placeholder );
		label.htmlFor = 'aisa-input';
		var input = el( 'input', 'aisa-input' );
		input.id = 'aisa-input';
		input.type = 'text';
		input.maxLength = 500;
		input.autocomplete = 'off';
		input.placeholder = T.placeholder;
		var send = el( 'button', 'aisa-send' );
		send.type = 'submit';
		send.innerHTML = icon( 'send' );
		send.setAttribute( 'aria-label', T.send );
		form.appendChild( hp );
		form.appendChild( label );
		form.appendChild( input );
		form.appendChild( send );

		var foot = el( 'div', 'aisa-foot' );
		var disclaimer = el( 'span', 'aisa-disclaimer', T.disclaimer );
		var all = el( 'a', 'aisa-all' );
		all.hidden = true;
		foot.appendChild( disclaimer );
		foot.appendChild( all );

		panel.appendChild( head );
		panel.appendChild( log );
		panel.appendChild( form );
		panel.appendChild( foot );
		panel.appendChild( live );
		overlay.appendChild( panel );
		wrap.appendChild( overlay );
		document.body.appendChild( wrap );

		els = { wrap: wrap, overlay: overlay, panel: panel, log: log, live: live, form: form, input: input, send: send, hp: hp, all: all };

		close.addEventListener( 'click', closePanel );
		reset.addEventListener( 'click', function () {
			state.messages = [];
			save();
			render();
			focusInput();
		} );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var q = input.value;
			input.value = '';
			ask( q );
		} );
		overlay.addEventListener( 'mousedown', function ( e ) {
			if ( e.target === overlay ) {
				closePanel();
			}
		} );
		log.addEventListener( 'click', onLogClick );
	}

	function render() {
		var log = els.log;
		log.innerHTML = '';
		if ( ! state.messages.length ) {
			var w = el( 'div', 'aisa-msg aisa-msg--assistant aisa-welcome' );
			w.innerHTML = markdown( T.welcome, [] );
			log.appendChild( w );
			if ( cfg.starters && cfg.starters.length ) {
				var chips = el( 'div', 'aisa-starters' );
				cfg.starters.forEach( function ( q ) {
					var c = el( 'button', 'aisa-chip', q );
					c.type = 'button';
					c.setAttribute( 'data-q', q );
					chips.appendChild( c );
				} );
				log.appendChild( chips );
			}
		}
		state.messages.forEach( function ( m, i ) {
			log.appendChild( bubble( m, i ) );
		} );
		updateAllLink();
		log.scrollTop = log.scrollHeight;
	}

	function citedSources( m ) {
		var cited = {};
		( m.content.match( /\[(\d{1,2})\]/g ) || [] ).forEach( function ( x ) {
			cited[ parseInt( x.slice( 1 ), 10 ) ] = true;
		} );
		var list = ( m.sources || [] ).filter( function ( s ) {
			return cited[ s.n ];
		} );
		return list.length || m.handoff ? list : ( m.sources || [] ).slice( 0, 3 );
	}

	function bubble( m, i ) {
		var b = el( 'div', 'aisa-msg aisa-msg--' + m.role + ( m.error ? ' aisa-msg--error' : '' ) );
		b.setAttribute( 'data-i', i );
		if ( 'user' === m.role ) {
			b.textContent = m.content;
			return b;
		}
		var body = el( 'div', 'aisa-md' );
		b.appendChild( body );
		fillBody( body, m );
		if ( m.pending ) {
			return b;
		}
		var sources = m.error ? [] : citedSources( m );
		if ( sources.length ) {
			var box = el( 'div', 'aisa-sources' );
			box.appendChild( el( 'span', 'aisa-sources__label', T.sources ) );
			var ol = el( 'ol' );
			sources.forEach( function ( s ) {
				var li = el( 'li' );
				li.appendChild( el( 'span', 'aisa-sources__n', String( s.n ) ) );
				if ( s.url ) {
					var a = el( 'a', '', s.title );
					a.href = s.url;
					li.appendChild( a );
				} else {
					li.appendChild( el( 'span', '', s.title + ' · ' + T.faq ) );
				}
				ol.appendChild( li );
			} );
			box.appendChild( ol );
			b.appendChild( box );
		}
		if ( m.handoff && T.ctaUrl ) {
			var cta = el( 'a', 'aisa-cta', T.ctaLabel );
			cta.href = T.ctaUrl;
			b.appendChild( cta );
		}
		if ( m.id && ! m.error ) {
			var fb = el( 'div', 'aisa-fb' );
			if ( m.rating ) {
				fb.appendChild( el( 'span', 'aisa-fb__thanks', T.thanks ) );
			} else {
				[ [ 1, 'up', T.helpful ], [ -1, 'down', T.notHelpful ] ].forEach( function ( r ) {
					var btn = el( 'button', 'aisa-fb__btn' );
					btn.type = 'button';
					btn.innerHTML = icon( r[ 1 ] );
					btn.setAttribute( 'aria-label', r[ 2 ] );
					btn.title = r[ 2 ];
					btn.setAttribute( 'data-rate', r[ 0 ] );
					fb.appendChild( btn );
				} );
			}
			b.appendChild( fb );
		}
		return b;
	}

	function fillBody( body, m ) {
		if ( m.pending && ! m.content ) {
			body.innerHTML = '<span class="aisa-dots" role="status"><span></span><span></span><span></span><span class="aisa-sr">' + esc( T.thinking ) + '</span></span>';
			return;
		}
		body.innerHTML = markdown( m.content, m.sources ) + ( m.pending ? '<span class="aisa-caret" aria-hidden="true"></span>' : '' );
	}

	// Streaming: update only the last bubble, and follow it only if the reader is at the bottom.
	function updateLast() {
		var log = els.log;
		var last = log.lastElementChild;
		var m = state.messages[ state.messages.length - 1 ];
		if ( ! last || ! m ) {
			return;
		}
		var atBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 60;
		fillBody( last.querySelector( '.aisa-md' ), m );
		if ( atBottom ) {
			log.scrollTop = log.scrollHeight;
		}
	}

	function updateAllLink() {
		var show = !! ( anchor && state.lastQuery );
		els.all.hidden = ! show;
		if ( show ) {
			els.all.textContent = T.allResults.replace( '%s', state.lastQuery );
			els.all.href = cfg.search + ( cfg.search.indexOf( '?' ) === -1 ? '?' : '&' ) + 's=' + encodeURIComponent( state.lastQuery );
		}
	}

	function onLogClick( e ) {
		var chip = e.target.closest( '[data-q]' );
		if ( chip ) {
			ask( chip.getAttribute( 'data-q' ) );
			return;
		}
		var rate = e.target.closest( '[data-rate]' );
		if ( rate ) {
			var m = state.messages[ parseInt( rate.closest( '[data-i]' ).getAttribute( 'data-i' ), 10 ) ];
			m.rating = parseInt( rate.getAttribute( 'data-rate' ), 10 );
			save();
			render();
			post( 'feedback', { id: m.id, rating: m.rating, session: state.session } ).catch( function () {} );
		}
	}

	function setBusy( busy ) {
		state.busy = busy;
		els.send.disabled = busy;
		els.panel.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
	}

	function focusInput() {
		if ( anchor ) {
			anchor.focus();
		} else if ( els.input ) {
			els.input.focus();
		}
	}

	/* Open / close ------------------------------------------------------- */

	function place() {
		if ( ! anchor || ! state.open ) {
			return;
		}
		var r = anchor.getBoundingClientRect();
		var vw = document.documentElement.clientWidth;
		var vh = window.innerHeight;
		var w = Math.min( Math.max( r.width, 400 ), 600, vw - 24 );
		var left = Math.min( Math.max( 12, r.left ), vw - w - 12 );
		var below = vh - r.bottom - 20;
		var p = els.panel.style;
		p.left = left + 'px';
		p.width = w + 'px';
		if ( below >= 320 || below >= r.top ) {
			p.top = ( r.bottom + 8 ) + 'px';
			p.bottom = 'auto';
			p.maxHeight = Math.min( 620, below ) + 'px';
		} else {
			p.top = 'auto';
			p.bottom = ( vh - r.top + 8 ) + 'px';
			p.maxHeight = Math.min( 620, r.top - 20 ) + 'px';
		}
	}

	function openPanel( input ) {
		build();
		anchor = input && wide() ? input : null;
		if ( ! state.open ) {
			opener = document.activeElement;
		}
		state.open = true;
		els.wrap.classList.toggle( 'aisa--anchored', !! anchor );
		els.panel.setAttribute( 'aria-modal', anchor ? 'false' : 'true' );
		els.panel.removeAttribute( 'style' );
		els.overlay.hidden = false;
		document.documentElement.classList.toggle( 'aisa-lock', ! anchor );
		if ( anchor ) {
			anchor.setAttribute( 'aria-expanded', 'true' );
			anchor.setAttribute( 'aria-controls', 'aisa-panel' );
			place();
		}
		render();
		if ( ! anchor ) {
			els.input.focus();
		}
	}

	function closePanel() {
		if ( ! state.open ) {
			return;
		}
		state.open = false;
		els.overlay.hidden = true;
		document.documentElement.classList.remove( 'aisa-lock' );
		if ( anchor ) {
			anchor.setAttribute( 'aria-expanded', 'false' );
		} else if ( opener && opener.focus ) {
			opener.focus();
		}
		anchor = null;
	}

	document.addEventListener( 'keydown', function ( e ) {
		if ( ! state.open ) {
			return;
		}
		if ( 'Escape' === e.key ) {
			e.preventDefault();
			closePanel();
			return;
		}
		// Keep Tab inside the dialog (the attached panel is not modal).
		if ( 'Tab' === e.key && ! anchor ) {
			var f = Array.prototype.filter.call( els.panel.querySelectorAll( 'a[href],button:not([disabled]),input:not([tabindex="-1"])' ), function ( n ) {
				return n.offsetParent !== null;
			} );
			if ( ! f.length ) {
				return;
			}
			if ( e.shiftKey && document.activeElement === f[ 0 ] ) {
				e.preventDefault();
				f[ f.length - 1 ].focus();
			} else if ( ! e.shiftKey && document.activeElement === f[ f.length - 1 ] ) {
				e.preventDefault();
				f[ 0 ].focus();
			}
		}
	} );

	// The attached panel closes when you click elsewhere on the page.
	document.addEventListener( 'mousedown', function ( e ) {
		if ( state.open && anchor && ! els.panel.contains( e.target ) && e.target !== anchor ) {
			closePanel();
		}
	} );
	window.addEventListener( 'resize', place );
	window.addEventListener( 'scroll', place, { passive: true } );

	/* Asking --------------------------------------------------------------- */

	function post( path, body, accept ) {
		return window.fetch( cfg.rest + path, {
			method: 'POST',
			credentials: 'omit',
			headers: { 'Content-Type': 'application/json', Accept: accept || 'application/json' },
			body: JSON.stringify( body )
		} );
	}

	function readStream( res, a ) {
		var reader = res.body.getReader();
		var decoder = new window.TextDecoder();
		var buf = '';
		function handle( block ) {
			var ev = 'message';
			var data = '';
			block.split( '\n' ).forEach( function ( line ) {
				if ( 0 === line.indexOf( 'event:' ) ) {
					ev = line.slice( 6 ).trim();
				} else if ( 0 === line.indexOf( 'data:' ) ) {
					data += line.slice( 5 ).trim();
				}
			} );
			if ( ! data ) {
				return;
			}
			var j;
			try {
				j = JSON.parse( data );
			} catch ( e ) {
				return;
			}
			if ( 'sources' === ev ) {
				a.sources = j.sources || [];
			} else if ( 'delta' === ev ) {
				a.content += j.t || '';
				updateLast();
			} else if ( 'done' === ev ) {
				a.id = j.id || 0;
				a.handoff = !! j.handoff;
				a.done = true;
			} else if ( 'error' === ev ) {
				a.error = true;
				a.content = j.message || T.error;
			}
		}
		function pump() {
			return reader.read().then( function ( r ) {
				if ( r.done ) {
					if ( buf.trim() ) {
						handle( buf );
					}
					return;
				}
				buf += decoder.decode( r.value, { stream: true } ).replace( /\r\n/g, '\n' );
				var parts = buf.split( '\n\n' );
				buf = parts.pop();
				parts.forEach( handle );
				return pump();
			} );
		}
		return pump().then( function () {
			if ( ! a.done && ! a.error ) {
				throw new Error( T.error );
			}
		} );
	}

	function ask( q ) {
		q = String( q || '' ).trim().slice( 0, 500 );
		if ( ! q || state.busy ) {
			return;
		}
		if ( ! state.open ) {
			openPanel( null );
		}
		state.lastQuery = q;
		var history = state.messages.filter( function ( m ) {
			return ! m.error && ! m.pending && m.content;
		} ).slice( -6 ).map( function ( m ) {
			return { role: m.role, content: m.content };
		} );
		var a = { role: 'assistant', content: '', sources: [], pending: true };
		state.messages.push( { role: 'user', content: q } );
		state.messages.push( a );
		setBusy( true );
		render();

		post( 'chat', {
			question: q,
			history: history,
			session: state.session,
			page: window.location.href,
			stream: !! cfg.stream,
			hp: els.hp.value
		}, cfg.stream ? 'text/event-stream' : 'application/json' ).then( function ( res ) {
			if ( ! res.ok ) {
				return res.json().catch( function () {
					return {};
				} ).then( function ( j ) {
					throw new Error( j && j.message ? j.message : T.error );
				} );
			}
			var type = res.headers.get( 'content-type' ) || '';
			if ( -1 !== type.indexOf( 'text/event-stream' ) && res.body && res.body.getReader && window.TextDecoder ) {
				return readStream( res, a );
			}
			return res.json().then( function ( j ) {
				a.content = j.answer || '';
				a.sources = j.sources || [];
				a.handoff = !! j.handoff;
				a.id = j.id || 0;
			} );
		} ).catch( function ( e ) {
			a.error = true;
			a.content = ( e && e.message ) || T.error;
		} ).then( function () {
			a.content = a.content.replace( /\[\[HANDOFF\]\]/g, '' ).trim();
			a.pending = false;
			setBusy( false );
			save();
			render();
			var tmp = el( 'div' );
			tmp.innerHTML = markdown( a.content, [] );
			els.live.textContent = tmp.textContent;
		} );
	}

	/* Wiring ------------------------------------------------------------- */

	function bindSearch( input ) {
		if ( input.getAttribute( 'data-aisa' ) ) {
			return;
		}
		input.setAttribute( 'data-aisa', '1' );
		input.setAttribute( 'autocomplete', 'off' );
		input.setAttribute( 'aria-haspopup', 'dialog' );
		input.addEventListener( 'focus', function () {
			if ( wide() && ( ! state.open || anchor !== input ) ) {
				openPanel( input );
			}
		} );
		var submit = function ( e ) {
			var q = input.value.trim();
			if ( ! q ) {
				return;
			}
			e.preventDefault();
			openPanel( input );
			if ( anchor ) {
				input.value = '';
			}
			ask( q );
		};
		if ( input.form ) {
			input.form.addEventListener( 'submit', submit );
		} else {
			input.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key ) {
					submit( e );
				}
			} );
		}
	}

	function bindAll() {
		var selectors = [ '.aisa-inline__input' ];
		if ( ( 'attach' === cfg.mode || 'both' === cfg.mode ) && cfg.selector ) {
			selectors.push( cfg.selector );
		}
		var inputs;
		try {
			inputs = document.querySelectorAll( selectors.join( ',' ) );
		} catch ( e ) {
			inputs = document.querySelectorAll( '.aisa-inline__input' );
		}
		Array.prototype.forEach.call( inputs, function ( input ) {
			if ( ! input.closest( '#aisa-widget' ) ) {
				bindSearch( input );
			}
		} );
	}

	function launcher() {
		if ( 'floating' !== cfg.mode && 'both' !== cfg.mode ) {
			return;
		}
		var b = el( 'button', 'aisa-launcher aisa-launcher--' + ( 'left' === cfg.position ? 'left' : 'right' ) );
		b.type = 'button';
		b.innerHTML = icon( 'spark' ) + '<span>' + esc( T.button ) + '</span>';
		b.setAttribute( 'aria-haspopup', 'dialog' );
		b.style.setProperty( '--aisa-margin', ( cfg.margin || 0 ) + 'px' );
		b.addEventListener( 'click', function () {
			openPanel( null );
		} );
		document.body.appendChild( b );
	}

	document.addEventListener( 'click', function ( e ) {
		var t = e.target.closest && e.target.closest( '[data-aisa-open]' );
		if ( t ) {
			e.preventDefault();
			window.aisaOpen( t.getAttribute( 'data-aisa-open' ) );
		}
	} );

	window.aisaOpen = function ( question ) {
		openPanel( null );
		if ( question ) {
			ask( question );
		}
	};

	load();
	launcher();
	bindAll();
}() );
