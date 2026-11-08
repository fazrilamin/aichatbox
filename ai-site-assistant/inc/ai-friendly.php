<?php
/**
 * AI-friendly output: /llms.txt (site outline), /llms-full.txt (all content as Markdown),
 * ?format=markdown on every indexed page, and per-crawler rules appended to robots.txt.
 * Everything is built from the index, so it matches what the assistant answers from.
 *
 * @package ai-site-assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rewrite rules for the llms files.
 */
function aisa_add_rewrites() {
	add_rewrite_rule( '^llms\.txt$', 'index.php?aisa_llms=index', 'top' );
	add_rewrite_rule( '^llms-full\.txt$', 'index.php?aisa_llms=full', 'top' );
}
add_action( 'init', 'aisa_add_rewrites' );

/**
 * Public query var.
 *
 * @param string[] $vars Query vars.
 * @return string[]
 */
function aisa_query_vars( $vars ) {
	$vars[] = 'aisa_llms';
	return $vars;
}
add_filter( 'query_vars', 'aisa_query_vars' );

/**
 * Which llms file was requested, if any. Also matches the raw path, so it works with
 * plain permalinks and before the rewrite rules have been flushed.
 *
 * @return string index|full|''
 */
function aisa_llms_requested() {
	$which = (string) get_query_var( 'aisa_llms' );
	if ( '' !== $which ) {
		return in_array( $which, array( 'index', 'full' ), true ) ? $which : '';
	}
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
	$path = trim( $path, '/' );
	$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	if ( '' !== $home && 0 === strpos( $path, $home . '/' ) ) {
		$path = substr( $path, strlen( $home ) + 1 );
	}
	$map = array(
		'llms.txt'      => 'index',
		'llms-full.txt' => 'full',
	);
	return isset( $map[ $path ] ) ? $map[ $path ] : '';
}

/**
 * One indexed document.
 *
 * @param int $post_id Post ID.
 * @return array|null
 */
function aisa_doc( $post_id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . aisa_table( 'docs' ) . ' WHERE post_id = %d', $post_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
}

/**
 * Markdown for one document, titled and with its source URL.
 *
 * @param array $doc Document row.
 * @return string
 */
function aisa_doc_markdown( $doc ) {
	$md = trim( $doc['markdown'] );
	if ( 0 !== strpos( $md, '# ' ) ) {
		$md = '# ' . $doc['title'] . "\n\n" . $md;
	}
	return $md . "\n\nSource: " . $doc['url'] . "\n";
}

/**
 * Indexed public documents, front page first, then by type and title.
 *
 * @return array[]
 */
function aisa_public_docs() {
	global $wpdb;
	$rows  = (array) $wpdb->get_results( 'SELECT * FROM ' . aisa_table( 'docs' ) . " WHERE url <> '' ORDER BY title", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	$front = (int) get_option( 'page_on_front' );
	usort(
		$rows,
		function ( $a, $b ) use ( $front ) {
			$fa = (int) $a['post_id'] === $front ? 0 : 1;
			$fb = (int) $b['post_id'] === $front ? 0 : 1;
			$ta = 'page' === $a['post_type'] ? 0 : ( 'post' === $a['post_type'] ? 1 : 2 );
			$tb = 'page' === $b['post_type'] ? 0 : ( 'post' === $b['post_type'] ? 1 : 2 );
			return array( $fa, $ta, $a['post_type'], $a['title'] ) <=> array( $fb, $tb, $b['post_type'], $b['title'] );
		}
	);
	return $rows;
}

/**
 * Snippets (question → answer).
 *
 * @return array[]
 */
function aisa_snippet_docs() {
	global $wpdb;
	return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT title, markdown FROM ' . aisa_table( 'docs' ) . ' WHERE post_type = %s ORDER BY title', 'aisa_snippet' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
}

/**
 * Site name and introduction header shared by both llms files.
 *
 * @return string
 */
function aisa_llms_header() {
	$intro = trim( (string) aisa_setting( 'llms_intro' ) );
	$intro = '' !== $intro ? $intro : get_bloginfo( 'description' );
	$out   = '# ' . html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ) . "\n\n";
	if ( '' !== $intro ) {
		$out .= '> ' . preg_replace( '/\s+/', ' ', html_entity_decode( $intro, ENT_QUOTES, 'UTF-8' ) ) . "\n\n";
	}
	return $out;
}

/**
 * /llms.txt: the site outline in the llmstxt.org format.
 *
 * @return string
 */
function aisa_llms_index() {
	$cached = get_transient( 'aisa_llms_index' );
	if ( is_string( $cached ) ) {
		return $cached;
	}
	$out     = aisa_llms_header();
	$current = '';
	foreach ( aisa_public_docs() as $d ) {
		if ( $d['post_type'] !== $current ) {
			$obj     = get_post_type_object( $d['post_type'] );
			$out    .= ( '' === $current ? '' : "\n" ) . '## ' . ( $obj ? $obj->labels->name : ucfirst( $d['post_type'] ) ) . "\n\n";
			$current = $d['post_type'];
		}
		$out .= '- [' . $d['title'] . '](' . $d['url'] . ')' . ( '' !== $d['summary'] ? ': ' . $d['summary'] : '' ) . "\n";
	}
	$out .= "\n## Optional\n\n- [Full content](" . home_url( '/llms-full.txt' ) . '): every page above as Markdown in one file' . ( aisa_snippet_docs() ? ', plus answers to common questions' : '' ) . "\n";
	if ( aisa_setting( 'markdown' ) ) {
		$out .= "\nEach page is also available as Markdown: add ?format=markdown to its URL.\n";
	}
	set_transient( 'aisa_llms_index', $out, DAY_IN_SECONDS );
	return $out;
}

/**
 * /llms-full.txt: every indexed page as Markdown, then the snippets.
 *
 * @return string
 */
function aisa_llms_full() {
	$cached = get_transient( 'aisa_llms_full' );
	if ( is_string( $cached ) ) {
		return $cached;
	}
	$out = aisa_llms_header();
	foreach ( aisa_public_docs() as $d ) {
		$out .= "---\n\n" . preg_replace( '/^(#+) /m', '#$1 ', aisa_doc_markdown( $d ) ) . "\n";
	}
	$snippets = aisa_snippet_docs();
	if ( $snippets ) {
		$out .= "---\n\n## Frequently asked questions\n\n";
		foreach ( $snippets as $s ) {
			$out .= '### ' . $s['title'] . "\n\n" . trim( $s['markdown'] ) . "\n\n";
		}
	}
	set_transient( 'aisa_llms_full', $out, DAY_IN_SECONDS );
	return $out;
}

/**
 * Serve the llms files and Markdown page versions.
 */
function aisa_template_redirect() {
	$which = aisa_llms_requested();
	if ( '' !== $which ) {
		if ( ! aisa_setting( 'llms_txt' ) ) {
			return;
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		if ( 'full' === $which ) {
			header( 'X-Robots-Tag: noindex' );
		}
		echo 'full' === $which ? aisa_llms_full() : aisa_llms_index(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	if ( aisa_setting( 'markdown' ) && isset( $_GET['format'] ) && 'markdown' === $_GET['format'] && is_singular() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$doc = aisa_doc( get_queried_object_id() );
		if ( ! $doc || '' === $doc['url'] ) {
			return;
		}
		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Link: <' . esc_url_raw( $doc['url'] ) . '>; rel="canonical"' );
		echo aisa_doc_markdown( $doc ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
add_action( 'template_redirect', 'aisa_template_redirect', 0 );

/**
 * Point AI tools at the Markdown version of the current page.
 */
function aisa_markdown_link() {
	if ( ! aisa_setting( 'markdown' ) || ! is_singular() ) {
		return;
	}
	$doc = aisa_doc( get_queried_object_id() );
	if ( $doc && '' !== $doc['url'] ) {
		printf( '<link rel="alternate" type="text/markdown" href="%s">' . "\n", esc_url( add_query_arg( 'format', 'markdown', $doc['url'] ) ) );
	}
}
add_action( 'wp_head', 'aisa_markdown_link', 5 );

/**
 * Append the AI crawler rules to the virtual robots.txt.
 *
 * @param string $output Robots.txt.
 * @param bool   $public Whether the site is public.
 * @return string
 */
function aisa_robots_txt( $output, $public ) {
	if ( ! $public ) {
		return $output;
	}
	$add = '';
	foreach ( (array) aisa_setting( 'robots' ) as $bot => $rule ) {
		$add .= "\nUser-agent: " . $bot . "\n" . ( 'block' === $rule ? "Disallow: /\n" : "Allow: /\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n" );
	}
	if ( aisa_setting( 'llms_txt' ) ) {
		$add .= "\n# Summary of this website for AI tools: " . home_url( '/llms.txt' ) . "\n";
	}
	return '' === $add ? $output : rtrim( $output ) . "\n" . $add;
}
add_filter( 'robots_txt', 'aisa_robots_txt', 99, 2 );
