<?php
/**
 * Indexer: renders each post the way visitors see it (blocks, patterns, shortcodes),
 * converts it to Markdown, splits it into chunks at headings and stores them for retrieval.
 *
 * Edits are indexed at the end of the request that saved them; a full reindex runs in
 * batches, driven by the dashboard (or WP-Cron for the daily run).
 *
 * @package ai-site-assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post types the index covers: the chosen public types plus snippets.
 *
 * @return string[]
 */
function aisa_indexable_types() {
	$types   = array_values( array_intersect( (array) aisa_setting( 'post_types' ), get_post_types( array( 'public' => true ) ) ) );
	$types[] = 'aisa_snippet';
	return $types;
}

/**
 * Whether a post belongs in the index.
 *
 * @param WP_Post $post Post.
 * @return bool
 */
function aisa_is_indexable( $post ) {
	$ok = $post instanceof WP_Post
		&& 'publish' === $post->post_status
		&& '' === $post->post_password
		&& in_array( $post->post_type, aisa_indexable_types(), true )
		&& ! in_array( (int) $post->ID, aisa_excluded_ids(), true );
	return (bool) apply_filters( 'aisa_is_indexable', $ok, $post );
}

/**
 * IDs of everything that should be indexed.
 *
 * @return int[]
 */
function aisa_indexable_ids() {
	$ids = get_posts(
		array(
			'post_type'        => aisa_indexable_types(),
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'has_password'     => false,
			'post__not_in'     => aisa_excluded_ids(),
			'suppress_filters' => true,
		)
	);
	return array_values( array_filter( array_map( 'intval', $ids ), fn( $id ) => aisa_is_indexable( get_post( $id ) ) ) );
}

/**
 * Render a post's content to Markdown.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function aisa_post_markdown( $post ) {
	$prev            = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
	$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	setup_postdata( $post );
	$html = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	$GLOBALS['post'] = $prev; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	if ( $prev instanceof WP_Post ) {
		setup_postdata( $prev );
	}
	$html = apply_filters( 'aisa_post_html', $html, $post );
	return (string) apply_filters( 'aisa_post_markdown', aisa_html_to_markdown( $html ), $post );
}

/**
 * Plain title (no HTML entities or curly-quote entities).
 *
 * @param WP_Post $post Post.
 * @return string
 */
function aisa_post_title( $post ) {
	return trim( html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ) );
}

/**
 * Convert rendered HTML to compact Markdown.
 *
 * @param string $html HTML.
 * @return string
 */
function aisa_html_to_markdown( $html ) {
	$html = (string) $html;
	if ( '' === trim( $html ) ) {
		return '';
	}
	if ( ! class_exists( 'DOMDocument' ) ) {
		$html = preg_replace( '#<(script|style|svg|noscript)\b.*?</\1>#is', '', $html );
		$html = preg_replace( '#</(p|div|h[1-6]|li|tr|section|details|summary)>|<br\s*/?>#i', "$0\n", $html );
		return aisa_tidy_markdown( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
	}
	$doc  = new DOMDocument();
	$prev = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>', LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	$body = $doc->getElementsByTagName( 'body' )->item( 0 );
	return $body ? aisa_tidy_markdown( aisa_md_children( $body ) ) : '';
}

/**
 * Normalise whitespace. List indentation is carried as \x01 until the final pass so that
 * trimming stray leading spaces from text nodes never flattens nested lists.
 *
 * @param string $md    Markdown.
 * @param bool   $final Final pass.
 * @return string
 */
function aisa_tidy_markdown( $md, $final = true ) {
	$md = str_replace( array( "\r\n", "\r", "\xC2\xA0" ), array( "\n", "\n", ' ' ), $md );
	$md = preg_replace( '/[ \t]+/u', ' ', $md );
	$md = preg_replace( '/^ +| +$/m', '', $md );
	$md = preg_replace( "/\n{3,}/", "\n\n", $md );
	if ( $final ) {
		$md = str_replace( "\x01", ' ', $md );
	}
	return trim( $md );
}

/**
 * Markdown for all children of a node.
 *
 * @param DOMNode $node Node.
 * @return string
 */
function aisa_md_children( $node ) {
	$out = '';
	foreach ( $node->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$out .= aisa_md_node( $child );
	}
	return $out;
}

/**
 * Inline text of a node on one line.
 *
 * @param DOMNode $node Node.
 * @return string
 */
function aisa_md_inline( $node ) {
	return trim( preg_replace( '/\s+/u', ' ', str_replace( "\x01", '', aisa_md_children( $node ) ) ) );
}

/**
 * Markdown for one node.
 *
 * @param DOMNode $n Node.
 * @return string
 */
function aisa_md_node( $n ) {
	// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	if ( XML_TEXT_NODE === $n->nodeType ) {
		return preg_replace( '/\s+/u', ' ', $n->nodeValue );
	}
	if ( XML_ELEMENT_NODE !== $n->nodeType ) {
		return '';
	}
	$tag = strtolower( $n->nodeName );
	if ( in_array( $tag, array( 'script', 'style', 'noscript', 'svg', 'canvas', 'template', 'form', 'nav', 'button', 'input', 'select', 'textarea', 'iframe', 'video', 'audio', 'object', 'head' ), true ) ) {
		return '';
	}
	if ( $n->hasAttribute( 'hidden' ) || 'true' === $n->getAttribute( 'aria-hidden' ) || false !== strpos( ' ' . $n->getAttribute( 'class' ) . ' ', ' screen-reader-text ' ) ) {
		return '';
	}
	switch ( $tag ) {
		case 'h1':
		case 'h2':
		case 'h3':
		case 'h4':
		case 'h5':
		case 'h6':
			$t = preg_replace( '/\[([^\]]*)\]\([^)]*\)/', '$1', aisa_md_inline( $n ) );
			return '' === $t ? '' : "\n\n" . str_repeat( '#', (int) $tag[1] ) . ' ' . $t . "\n\n";
		case 'br':
			return "\n";
		case 'hr':
			return "\n\n";
		case 'img':
			$alt = trim( $n->getAttribute( 'alt' ) );
			return '' === $alt ? '' : ' ' . $alt . ' ';
		case 'a':
			$t    = aisa_md_inline( $n );
			$href = trim( $n->getAttribute( 'href' ) );
			if ( '' === $t ) {
				return '';
			}
			if ( '' === $href || '#' === $href[0] || 0 === stripos( $href, 'javascript:' ) || $t === $href ) {
				return ' ' . $t . ' ';
			}
			if ( '/' === $href[0] && ( ! isset( $href[1] ) || '/' !== $href[1] ) ) {
				$href = home_url( $href );
			}
			return ' [' . $t . '](' . $href . ') ';
		case 'strong':
		case 'b':
			$t = aisa_md_inline( $n );
			return '' === $t ? '' : ' **' . $t . '** ';
		case 'code':
			return '`' . trim( $n->textContent ) . '`';
		case 'pre':
			return "\n\n```\n" . trim( $n->textContent ) . "\n```\n\n";
		case 'ul':
		case 'ol':
			return aisa_md_list( $n, 'ol' === $tag );
		case 'li':
			return "\n- " . aisa_md_inline( $n ) . "\n";
		case 'table':
			return aisa_md_table( $n );
		case 'details':
			$q    = '';
			$rest = '';
			foreach ( $n->childNodes as $c ) {
				if ( XML_ELEMENT_NODE === $c->nodeType && 'summary' === strtolower( $c->nodeName ) ) {
					$q = aisa_md_inline( $c );
				} else {
					$rest .= aisa_md_node( $c );
				}
			}
			return "\n\n" . ( '' !== $q ? '**' . $q . "**\n\n" : '' ) . $rest . "\n\n";
		case 'dt':
			return "\n\n**" . aisa_md_inline( $n ) . "**\n";
		case 'dd':
			return aisa_md_children( $n ) . "\n";
		case 'blockquote':
			$inner = aisa_tidy_markdown( aisa_md_children( $n ), false );
			return "\n\n> " . str_replace( "\n", "\n> ", $inner ) . "\n\n";
		case 'p':
		case 'div':
		case 'section':
		case 'article':
		case 'main':
		case 'header':
		case 'footer':
		case 'aside':
		case 'figure':
		case 'figcaption':
		case 'address':
		case 'summary':
		case 'dl':
		case 'tr':
			return "\n\n" . aisa_md_children( $n ) . "\n\n";
		case 'td':
		case 'th':
		case 'span':
			// Spans are often badges or chips laid out with flex and no whitespace between them.
			return ' ' . aisa_md_children( $n ) . ' ';
		default:
			return aisa_md_children( $n );
	}
	// phpcs:enable
}

/**
 * Markdown list. Nested lists come back unindented and the parent item indents them.
 *
 * @param DOMNode $n       ul/ol element.
 * @param bool    $ordered Numbered list.
 * @return string
 */
function aisa_md_list( $n, $ordered ) {
	$out = '';
	$i   = 1;
	foreach ( $n->childNodes as $li ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( XML_ELEMENT_NODE !== $li->nodeType || 'li' !== strtolower( $li->nodeName ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			continue;
		}
		$content = aisa_tidy_markdown( aisa_md_children( $li ), false );
		if ( '' === $content ) {
			continue;
		}
		$content = preg_replace( "/\n{2,}/", "\n", $content );
		$marker  = $ordered ? ( $i++ ) . '.' : '-';
		$out    .= $marker . ' ' . str_replace( "\n", "\n\x01\x01", $content ) . "\n";
	}
	return '' === $out ? '' : "\n\n" . $out . "\n";
}

/**
 * Markdown table.
 *
 * @param DOMElement $n table element.
 * @return string
 */
function aisa_md_table( $n ) {
	$rows = array();
	foreach ( $n->getElementsByTagName( 'tr' ) as $tr ) {
		if ( $tr->hasAttribute( 'hidden' ) ) {
			continue;
		}
		$cells = array();
		foreach ( $tr->childNodes as $c ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( XML_ELEMENT_NODE === $c->nodeType && in_array( strtolower( $c->nodeName ), array( 'td', 'th' ), true ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$cells[] = str_replace( '|', '/', aisa_md_inline( $c ) );
			}
		}
		if ( array_filter( $cells, 'strlen' ) ) {
			$rows[] = $cells;
		}
	}
	if ( ! $rows ) {
		return '';
	}
	$w   = max( array_map( 'count', $rows ) );
	$out = "\n\n";
	foreach ( $rows as $i => $r ) {
		$out .= '| ' . implode( ' | ', array_pad( $r, $w, '' ) ) . " |\n";
		if ( 0 === $i ) {
			$out .= '|' . str_repeat( ' --- |', $w ) . "\n";
		}
	}
	return $out . "\n";
}

/**
 * Split Markdown into chunks of about $size characters, breaking at headings.
 *
 * @param string $md   Markdown.
 * @param int    $size Target size.
 * @return array<int,array{0:string,1:string}> [heading, text]
 */
function aisa_chunk_markdown( $md, $size ) {
	$sections = array();
	$cur      = array( '', '' );
	foreach ( explode( "\n", $md ) as $line ) {
		if ( preg_match( '/^#{1,3} (.+)$/', $line, $m ) ) {
			if ( '' !== trim( $cur[1] ) ) {
				$sections[] = $cur;
			}
			$cur = array( trim( $m[1] ), $line . "\n" );
			continue;
		}
		$cur[1] .= $line . "\n";
	}
	if ( '' !== trim( $cur[1] ) ) {
		$sections[] = $cur;
	}

	$chunks = array();
	$buf    = '';
	$head   = '';
	foreach ( $sections as $s ) {
		$t = trim( $s[1] );
		if ( strlen( $t ) > $size ) {
			if ( '' !== $buf ) {
				$chunks[] = array( $head, $buf );
				$buf      = '';
			}
			foreach ( aisa_split_long( $t, $size ) as $piece ) {
				$chunks[] = array( $s[0], $piece );
			}
			continue;
		}
		if ( '' !== $buf && strlen( $buf ) + strlen( $t ) + 2 > $size ) {
			$chunks[] = array( $head, $buf );
			$buf      = '';
		}
		if ( '' === $buf ) {
			$head = $s[0];
		}
		$buf .= ( '' === $buf ? '' : "\n\n" ) . $t;
	}
	if ( '' !== $buf ) {
		$chunks[] = array( $head, $buf );
	}
	return $chunks;
}

/**
 * Split one long section by lines. Each continuation repeats the section heading and,
 * inside a table, the table's header row so the columns still make sense.
 *
 * @param string $text Section text.
 * @param int    $size Target size.
 * @return string[]
 */
function aisa_split_long( $text, $size ) {
	$lines = array();
	foreach ( explode( "\n", $text ) as $line ) {
		foreach ( explode( "\n", strlen( $line ) > $size ? wordwrap( $line, $size, "\n", true ) : $line ) as $l ) {
			$lines[] = $l;
		}
	}
	$heading = preg_match( '/^#{1,6} /', $lines[0] ) ? $lines[0] : '';
	$pieces  = array();
	$buf     = '';
	$has     = false;
	$thead   = '';
	foreach ( $lines as $i => $line ) {
		if ( $i > 0 && preg_match( '/^\|( --- \|)+$/', $line ) ) {
			$thead = $lines[ $i - 1 ] . "\n" . $line;
		}
		if ( $has && strlen( $buf ) + strlen( $line ) + 1 > $size ) {
			$pieces[] = rtrim( $buf );
			$buf      = '' !== $heading ? $heading . "\n" : '';
			if ( '' !== $thead && 0 === strpos( $line, '|' ) && false === strpos( $thead, $line ) ) {
				$buf .= $thead . "\n";
			}
			$has = false;
		}
		$buf .= $line . "\n";
		if ( '' !== trim( $line ) && $line !== $heading ) {
			$has = true;
		}
	}
	if ( $has ) {
		$pieces[] = rtrim( $buf );
	}
	return $pieces;
}

/**
 * Index (or re-index) one post.
 *
 * @param int $post_id Post ID.
 * @return string|WP_Error indexed|unchanged|removed, or an error (the text index is still saved).
 */
function aisa_index_post( $post_id ) {
	global $wpdb;
	$post = get_post( $post_id );
	if ( ! $post || ! aisa_is_indexable( $post ) ) {
		aisa_remove_post( $post_id );
		return 'removed';
	}
	$is_snippet = 'aisa_snippet' === $post->post_type;
	$title      = aisa_post_title( $post );
	$url        = $is_snippet ? '' : (string) get_permalink( $post );
	$md         = aisa_post_markdown( $post );
	$embed      = aisa_embed_config();
	$size       = (int) aisa_setting( 'chunk_size' );
	$hash       = md5( implode( "\n", array( $title, $url, $md, $size, $embed ? $embed['type'] . '|' . $embed['url'] . '|' . $embed['model'] : '' ) ) );

	$docs   = aisa_table( 'docs' );
	$chunks = aisa_table( 'chunks' );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$old = $wpdb->get_var( $wpdb->prepare( "SELECT hash FROM $docs WHERE post_id = %d", $post->ID ) );
	if ( $old === $hash ) {
		return 'unchanged';
	}

	if ( $is_snippet ) {
		$parts = array( array( $title, "Q: $title\nA: $md" ) );
	} else {
		$parts = aisa_chunk_markdown( $md, $size );
	}

	$vectors = null;
	$error   = null;
	if ( $embed && $parts ) {
		$vectors = aisa_embed( array_map( fn( $p ) => $title . "\n" . $p[1], $parts ) );
		if ( is_wp_error( $vectors ) ) {
			$error   = $vectors;
			$vectors = null;
			$hash    = ''; // Retry the embeddings next time.
		}
	}

	$wpdb->delete( $chunks, array( 'post_id' => $post->ID ), array( '%d' ) );
	foreach ( $parts as $i => $p ) {
		$terms = aisa_terms( $title . ' ' . $title . ' ' . $p[0] . ' ' . $p[1] );
		$wpdb->insert(
			$chunks,
			array(
				'post_id'   => $post->ID,
				'chunk_no'  => $i,
				'heading'   => $p[0],
				'content'   => $p[1],
				'terms'     => wp_json_encode( $terms ),
				'length'    => array_sum( $terms ),
				'embedding' => $vectors ? aisa_pack_vector( $vectors[ $i ] ) : null,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	$summary = has_excerpt( $post ) ? wp_strip_all_tags( get_the_excerpt( $post ) ) : aisa_summary_from_markdown( $md );
	$wpdb->replace(
		$docs,
		array(
			'post_id'    => $post->ID,
			'post_type'  => $post->post_type,
			'title'      => $title,
			'url'        => $url,
			'summary'    => $summary,
			'markdown'   => $md,
			'hash'       => $hash,
			'chunks'     => count( $parts ),
			'words'      => count( preg_split( '/\s+/u', $md, -1, PREG_SPLIT_NO_EMPTY ) ),
			'indexed_at' => current_time( 'mysql', true ),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
	);
	// phpcs:enable
	aisa_index_changed();
	return $error ? $error : 'indexed';
}

/**
 * One-line summary: the first real paragraph of a page.
 *
 * @param string $md Markdown.
 * @return string
 */
function aisa_summary_from_markdown( $md ) {
	foreach ( preg_split( "/\n{2,}/", $md ) as $para ) {
		$para = trim( preg_replace( '/\[([^\]]*)\]\([^)]*\)/', '$1', $para ) );
		$para = trim( str_replace( array( '**', '`' ), '', $para ) );
		if ( strlen( $para ) >= 60 && ! preg_match( '/^(#|\||- |\d+\. |>)/', $para ) ) {
			return wp_trim_words( preg_replace( '/\s+/u', ' ', $para ), 30, '…' );
		}
	}
	return '';
}

/**
 * Remove a post from the index.
 *
 * @param int $post_id Post ID.
 */
function aisa_remove_post( $post_id ) {
	global $wpdb;
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$a = $wpdb->delete( aisa_table( 'chunks' ), array( 'post_id' => (int) $post_id ), array( '%d' ) );
	$b = $wpdb->delete( aisa_table( 'docs' ), array( 'post_id' => (int) $post_id ), array( '%d' ) );
	// phpcs:enable
	if ( $a || $b ) {
		aisa_index_changed();
	}
}

/**
 * Drop caches built from the index.
 */
function aisa_index_changed() {
	delete_transient( 'aisa_bm25' );
	delete_transient( 'aisa_llms_index' );
	delete_transient( 'aisa_llms_full' );
}

/**
 * Remember a changed post; it is indexed once the request has finished.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 */
function aisa_on_post_saved( $post_id, $post ) {
	global $aisa_changed_posts;
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! $post instanceof WP_Post ) {
		return;
	}
	if ( ! in_array( $post->post_type, aisa_indexable_types(), true ) ) {
		return;
	}
	if ( empty( $aisa_changed_posts ) ) {
		$aisa_changed_posts = array();
		add_action( 'shutdown', 'aisa_index_changed_posts', 9999 );
	}
	$aisa_changed_posts[ $post_id ] = $post_id;
}
add_action( 'wp_after_insert_post', 'aisa_on_post_saved', 10, 2 );
add_action( 'trashed_post', 'aisa_remove_post' );
add_action( 'before_delete_post', 'aisa_remove_post' );

/**
 * Index the posts saved during this request, after the response has been sent where possible.
 */
function aisa_index_changed_posts() {
	global $aisa_changed_posts;
	if ( empty( $aisa_changed_posts ) ) {
		return;
	}
	if ( function_exists( 'fastcgi_finish_request' ) ) {
		fastcgi_finish_request();
	}
	foreach ( $aisa_changed_posts as $id ) {
		aisa_index_post( $id );
	}
	$aisa_changed_posts = array();
}

/**
 * Index progress.
 *
 * @return array{total:int,done:int,started:int,finished:int,errors:string[]}
 */
function aisa_index_state() {
	$s = get_option( 'aisa_index_state', array() );
	return wp_parse_args(
		is_array( $s ) ? $s : array(),
		array(
			'total'    => 0,
			'done'     => 0,
			'started'  => 0,
			'finished' => 0,
			'errors'   => array(),
		)
	);
}

/**
 * Start a full reindex: queue every indexable post and drop documents that no longer qualify.
 *
 * @return array Index state.
 */
function aisa_reindex_start() {
	global $wpdb;
	$ids   = aisa_indexable_ids();
	$known = array_map( 'intval', $wpdb->get_col( 'SELECT post_id FROM ' . aisa_table( 'docs' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	foreach ( array_diff( $known, $ids ) as $gone ) {
		aisa_remove_post( $gone );
	}
	$state = array(
		'total'    => count( $ids ),
		'done'     => 0,
		'started'  => time(),
		'finished' => $ids ? 0 : time(),
		'errors'   => array(),
	);
	update_option( 'aisa_index_queue', $ids, false );
	update_option( 'aisa_index_state', $state, false );
	delete_option( 'aisa_needs_reindex' );
	return $state;
}

/**
 * Index the next few queued posts.
 *
 * @param int $n Batch size.
 * @return array Index state.
 */
function aisa_reindex_step( $n = 5 ) {
	$state = aisa_index_state();
	if ( $state['finished'] || get_transient( 'aisa_index_lock' ) ) {
		return $state;
	}
	set_transient( 'aisa_index_lock', 1, 120 );
	$queue = (array) get_option( 'aisa_index_queue', array() );
	$batch = array_splice( $queue, 0, $n );
	update_option( 'aisa_index_queue', $queue, false ); // Saved first, so a fatal error never loops on the same post.
	foreach ( $batch as $id ) {
		$r = aisa_index_post( $id );
		if ( is_wp_error( $r ) && count( $state['errors'] ) < 20 ) {
			$state['errors'][] = get_the_title( $id ) . ': ' . $r->get_error_message();
		}
	}
	$state['done'] = min( $state['total'], $state['done'] + count( $batch ) );
	if ( ! $queue ) {
		$state['finished'] = time();
		update_option( 'aisa_last_indexed', time(), false );
	}
	update_option( 'aisa_index_state', $state, false );
	delete_transient( 'aisa_index_lock' );
	return $state;
}

/**
 * WP-Cron batch runner for the daily reindex.
 */
function aisa_index_batch_cron() {
	$state = aisa_reindex_step( 10 );
	if ( ! $state['finished'] ) {
		wp_schedule_single_event( time() + 5, 'aisa_index_batch' );
	}
}
add_action( 'aisa_index_batch', 'aisa_index_batch_cron' );
