<?php
/**
 * Developer CLI for a WordPress site that has the plugin active. Not shipped in the zip.
 *
 * Usage: php tools/aisa-cli.php <wordpress dir> <command> [arg] [--host=localhost:8087]
 *   stats              indexed documents and chunk counts
 *   reindex            full reindex (same batches as the dashboard)
 *   md <post_id>       the Markdown the AI reads for a post
 *   chunks <post_id>   chunk headings and sizes
 *   search "<query>"   retrieval results (no AI call)
 *   prompt "<query>"   the full system prompt that would be sent
 *   set <file.json>    merge settings from a JSON file (PowerShell 5.1 mangles JSON arguments)
 *   update             check GitHub for a newer release (skips the cache)
 *
 * @package ai-site-assistant
 */

$host_arg = 'localhost:8087';
foreach ( $argv as $i => $a ) {
	if ( 0 === strpos( $a, '--host=' ) ) {
		$host_arg = substr( $a, 7 );
		unset( $argv[ $i ] );
	}
}
$argv = array_values( $argv );
if ( count( $argv ) < 3 ) {
	fwrite( STDERR, "Usage: php aisa-cli.php <wordpress dir> <command> [arg] [--host=localhost:8087]\n" );
	exit( 1 );
}
$_SERVER['HTTP_HOST']   = $host_arg;
$_SERVER['SERVER_NAME'] = preg_replace( '/:\d+$/', '', $host_arg );
$_SERVER['REQUEST_URI'] = '/';
require rtrim( $argv[1], '/\\' ) . '/wp-load.php';
if ( ! function_exists( 'aisa_setting' ) ) {
	fwrite( STDERR, "AI Site Assistant is not active on that site.\n" );
	exit( 1 );
}

global $wpdb;
$cmd = $argv[2];
$arg = isset( $argv[3] ) ? $argv[3] : '';
switch ( $cmd ) {
	case 'stats':
		foreach ( $wpdb->get_results( 'SELECT post_id, post_type, title, chunks, words FROM ' . aisa_table( 'docs' ) . ' ORDER BY post_type, post_id', ARRAY_A ) as $d ) {
			printf( "%5d %-12s %-60s chunks=%-3d words=%d\n", $d['post_id'], $d['post_type'], mb_substr( $d['title'], 0, 60 ), $d['chunks'], $d['words'] );
		}
		echo 'chunks: ' . $wpdb->get_var( 'SELECT COUNT(*) FROM ' . aisa_table( 'chunks' ) ) . "\n";
		break;
	case 'reindex':
		$s = aisa_reindex_start();
		while ( ! $s['finished'] ) {
			$s = aisa_reindex_step( 10 );
		}
		echo wp_json_encode( $s ) . "\n";
		break;
	case 'md':
		$d = aisa_doc( (int) $arg );
		echo $d ? $d['markdown'] . "\n" : "Not indexed.\n";
		break;
	case 'chunks':
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT chunk_no, heading, LENGTH(content) AS len, length FROM ' . aisa_table( 'chunks' ) . ' WHERE post_id = %d ORDER BY chunk_no', (int) $arg ), ARRAY_A ) as $c ) {
			printf( "#%-3d %-60s chars=%-5d terms=%d\n", $c['chunk_no'], mb_substr( $c['heading'], 0, 60 ), $c['len'], $c['length'] );
		}
		break;
	case 'search':
		foreach ( aisa_search( $arg, 6 ) as $r ) {
			printf( "bm25=%-6s %-45s > %s\n", $r['bm25'], mb_substr( $r['title'], 0, 45 ), mb_substr( $r['heading'], 0, 60 ) );
		}
		break;
	case 'prompt':
		list( $messages, $sources ) = aisa_build_messages( $arg, array(), aisa_search( $arg, 6 ) );
		echo $messages[0]['content'] . "\n\nSources: " . wp_json_encode( $sources, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
		break;
	case 'set':
		$new = json_decode( (string) file_get_contents( $arg ), true );
		if ( ! is_array( $new ) ) {
			fwrite( STDERR, "Could not read JSON from $arg\n" );
			exit( 1 );
		}
		$opt = get_option( 'aisa_settings', array() );
		update_option( 'aisa_settings', array_merge( is_array( $opt ) ? $opt : array(), $new ) );
		echo "Saved.\n";
		break;
	case 'update':
		echo 'repo: ' . AISA_GITHUB_REPO . ', installed: ' . AISA_VERSION . ', updates offered here: ' . ( aisa_updates_enabled() ? 'yes' : 'no (disabled or linked folder)' ) . "\n";
		$r = aisa_github_release( true );
		echo $r ? wp_json_encode( $r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" : "No usable release (none published, no ai-site-assistant-<version>.zip asset, or GitHub unreachable).\n";
		break;
	default:
		fwrite( STDERR, "Unknown command: $cmd\n" );
		exit( 1 );
}
