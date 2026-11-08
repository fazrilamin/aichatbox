<?php
/**
 * Builds ai-site-assistant/languages/ai-site-assistant.pot from the plugin's PHP files, with no
 * dependencies (WP-CLI's `wp i18n make-pot` gives the same result). Not shipped in the zip.
 *
 * Usage: php tools/make-pot.php
 *
 * Extracts __, _e, _x, _ex, _n, _nx, esc_html__, esc_html_e, esc_html_x, esc_attr__, esc_attr_e and
 * esc_attr_x calls for the ai-site-assistant domain, plus "translators:" comments and the plugin
 * header. The file is rewritten only when the strings change, so it doesn't churn in version control.
 *
 * @package ai-site-assistant
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$aisa_root   = dirname( __DIR__ ) . '/ai-site-assistant';
$aisa_domain = 'ai-site-assistant';
$aisa_out    = $aisa_root . '/languages/' . $aisa_domain . '.pot';

// Function => [argument roles]: t = text, p = plural, c = context, n = number, d = domain.
$aisa_funcs = array(
	'__'         => array( 't', 'd' ),
	'_e'         => array( 't', 'd' ),
	'esc_html__' => array( 't', 'd' ),
	'esc_html_e' => array( 't', 'd' ),
	'esc_attr__' => array( 't', 'd' ),
	'esc_attr_e' => array( 't', 'd' ),
	'_x'         => array( 't', 'c', 'd' ),
	'_ex'        => array( 't', 'c', 'd' ),
	'esc_html_x' => array( 't', 'c', 'd' ),
	'esc_attr_x' => array( 't', 'c', 'd' ),
	'_n'         => array( 't', 'p', 'n', 'd' ),
	'_nx'        => array( 't', 'p', 'n', 'c', 'd' ),
);

/**
 * Value of a PHP string literal token.
 *
 * @param string $lit Literal with quotes.
 * @return string
 */
function aisa_pot_unquote( $lit ) {
	$body = substr( $lit, 1, -1 );
	return "'" === $lit[0] ? strtr( $body, array( "\\'" => "'", '\\\\' => '\\' ) ) : stripcslashes( $body );
}

/**
 * Quote a string for a .pot file.
 *
 * @param string $s String.
 * @return string
 */
function aisa_pot_quote( $s ) {
	return '"' . strtr(
		$s,
		array(
			'\\' => '\\\\',
			'"'  => '\\"',
			"\n" => '\\n',
			"\t" => '\\t',
		)
	) . '"';
}

$aisa_entries = array(); // key => [msgctxt, msgid, msgid_plural, refs[], comments[]].

/**
 * Add one string.
 *
 * @param array  $entries  Entries (by reference).
 * @param string $ctx      Context ('' for none).
 * @param string $text     Text.
 * @param string $plural   Plural ('' for none).
 * @param string $ref      file:line reference.
 * @param string $comment  Extracted comment ('' for none).
 */
function aisa_pot_add( &$entries, $ctx, $text, $plural, $ref, $comment ) {
	$key = $ctx . "\x04" . $text;
	if ( ! isset( $entries[ $key ] ) ) {
		$entries[ $key ] = array( $ctx, $text, $plural, array(), array() );
	}
	if ( '' !== $ref && ! in_array( $ref, $entries[ $key ][3], true ) ) {
		$entries[ $key ][3][] = $ref;
	}
	if ( '' !== $comment && ! in_array( $comment, $entries[ $key ][4], true ) ) {
		$entries[ $key ][4][] = $comment;
	}
}

// Plugin header strings, as WP-CLI extracts them.
$aisa_main = (string) file_get_contents( $aisa_root . '/ai-site-assistant.php' );
$aisa_ver  = preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $aisa_main, $m ) ? $m[1] : '';
foreach ( array( 'Plugin Name', 'Plugin URI', 'Description', 'Author', 'Author URI' ) as $aisa_h ) {
	if ( preg_match( '/^\s*\*\s*' . preg_quote( $aisa_h, '/' ) . ':\s*(.+)$/m', $aisa_main, $m ) ) {
		aisa_pot_add( $aisa_entries, '', trim( $m[1] ), '', '', $aisa_h . ' of the plugin' );
	}
}

$aisa_files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $aisa_root, FilesystemIterator::SKIP_DOTS ) );
$aisa_paths = array();
foreach ( $aisa_files as $aisa_file ) {
	if ( 'php' === strtolower( $aisa_file->getExtension() ) ) {
		$aisa_paths[] = str_replace( '\\', '/', substr( $aisa_file->getPathname(), strlen( $aisa_root ) + 1 ) );
	}
}
sort( $aisa_paths );

foreach ( $aisa_paths as $aisa_rel ) {
	$aisa_tokens  = token_get_all( (string) file_get_contents( $aisa_root . '/' . $aisa_rel ) );
	$aisa_comment = null; // [text, end line].
	$aisa_count   = count( $aisa_tokens );
	for ( $i = 0; $i < $aisa_count; $i++ ) {
		$t = $aisa_tokens[ $i ];
		if ( ! is_array( $t ) ) {
			continue;
		}
		if ( T_COMMENT === $t[0] || T_DOC_COMMENT === $t[0] ) {
			if ( preg_match( '/translators:\s*(.*?)\s*(?:\*\/)?$/s', $t[1], $m ) ) {
				$text         = trim( preg_replace( '/\s*\n\s*\*?\s*/', ' ', $m[1] ) );
				$aisa_comment = array( 'translators: ' . $text, $t[2] + substr_count( $t[1], "\n" ) );
			}
			continue;
		}
		if ( T_STRING !== $t[0] || ! isset( $aisa_funcs[ $t[1] ] ) ) {
			continue;
		}
		// Skip method calls and definitions (->__(), function __()).
		$prev = $i - 1;
		while ( $prev >= 0 && is_array( $aisa_tokens[ $prev ] ) && T_WHITESPACE === $aisa_tokens[ $prev ][0] ) {
			--$prev;
		}
		if ( $prev >= 0 && is_array( $aisa_tokens[ $prev ] ) && in_array( $aisa_tokens[ $prev ][0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) {
			continue;
		}
		// Collect the top-level arguments; only plain string literals are usable.
		$j = $i + 1;
		while ( $j < $aisa_count && is_array( $aisa_tokens[ $j ] ) && T_WHITESPACE === $aisa_tokens[ $j ][0] ) {
			++$j;
		}
		if ( $j >= $aisa_count || '(' !== $aisa_tokens[ $j ] ) {
			continue;
		}
		$args  = array();
		$cur   = array();
		$depth = 0;
		for ( ++$j; $j < $aisa_count; $j++ ) {
			$a = $aisa_tokens[ $j ];
			if ( '(' === $a || '[' === $a ) {
				++$depth;
			} elseif ( ')' === $a || ']' === $a ) {
				if ( 0 === $depth ) {
					$args[] = $cur;
					break;
				}
				--$depth;
			} elseif ( ',' === $a && 0 === $depth ) {
				$args[] = $cur;
				$cur    = array();
				continue;
			}
			if ( is_array( $a ) && in_array( $a[0], array( T_WHITESPACE, T_COMMENT ), true ) ) {
				continue;
			}
			$cur[] = $a;
		}
		$vals = array();
		foreach ( $aisa_funcs[ $t[1] ] as $k => $role ) {
			$arg = isset( $args[ $k ] ) ? $args[ $k ] : array();
			if ( 'n' === $role ) {
				continue;
			}
			$vals[ $role ] = ( 1 === count( $arg ) && is_array( $arg[0] ) && T_CONSTANT_ENCAPSED_STRING === $arg[0][0] ) ? aisa_pot_unquote( $arg[0][1] ) : null;
		}
		if ( ! isset( $vals['d'] ) || $aisa_domain !== $vals['d'] || ! isset( $vals['t'] ) || '' === $vals['t'] ) {
			continue;
		}
		$comment = ( $aisa_comment && $aisa_comment[1] >= $t[2] - 2 ) ? $aisa_comment[0] : '';
		if ( '' !== $comment ) {
			$aisa_comment = null;
		}
		aisa_pot_add( $aisa_entries, isset( $vals['c'] ) ? $vals['c'] : '', $vals['t'], isset( $vals['p'] ) ? (string) $vals['p'] : '', $aisa_rel . ':' . $t[2], $comment );
	}
}

$aisa_body = '';
foreach ( $aisa_entries as $aisa_e ) {
	list( $ctx, $text, $plural, $refs, $comments ) = $aisa_e;
	$aisa_body .= "\n";
	foreach ( $comments as $c ) {
		$aisa_body .= '#. ' . $c . "\n";
	}
	if ( $refs ) {
		$aisa_body .= '#: ' . implode( ' ', $refs ) . "\n";
	}
	if ( false !== strpos( $text, '%' ) && preg_match( '/%(\d+\$)?[sd]/', $text ) ) {
		$aisa_body .= "#, php-format\n";
	}
	if ( '' !== $ctx ) {
		$aisa_body .= 'msgctxt ' . aisa_pot_quote( $ctx ) . "\n";
	}
	$aisa_body .= 'msgid ' . aisa_pot_quote( $text ) . "\n";
	if ( '' !== $plural ) {
		$aisa_body .= 'msgid_plural ' . aisa_pot_quote( $plural ) . "\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n";
	} else {
		$aisa_body .= "msgstr \"\"\n";
	}
}

// Keep the old file (and its creation date) when nothing but the date would change.
$aisa_old = is_file( $aisa_out ) ? (string) file_get_contents( $aisa_out ) : '';
$aisa_sep = strpos( $aisa_old, "\n\n" );
if ( false !== $aisa_sep && substr( $aisa_old, $aisa_sep + 1 ) === $aisa_body && false !== strpos( $aisa_old, '"Project-Id-Version: AI Assistant & Chatbox ' . $aisa_ver . '\n"' ) ) {
	echo 'pot unchanged (' . count( $aisa_entries ) . " strings)\n";
	exit( 0 );
}

$aisa_head = implode(
	"\n",
	array(
		'# Copyright (C) ' . gmdate( 'Y' ) . ' Fazril Amin',
		'# This file is distributed under the GPL-2.0-or-later.',
		'msgid ""',
		'msgstr ""',
		'"Project-Id-Version: AI Assistant & Chatbox ' . $aisa_ver . '\n"',
		'"Report-Msgid-Bugs-To: https://github.com/fazrilamin/aichatbox/issues\n"',
		'"POT-Creation-Date: ' . gmdate( 'Y-m-d H:i:s' ) . '+00:00\n"',
		'"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\n"',
		'"Last-Translator: FULL NAME <EMAIL@ADDRESS>\n"',
		'"Language-Team: LANGUAGE <LL@li.org>\n"',
		'"MIME-Version: 1.0\n"',
		'"Content-Type: text/plain; charset=UTF-8\n"',
		'"Content-Transfer-Encoding: 8bit\n"',
		'"X-Domain: ' . $aisa_domain . '\n"',
	)
) . "\n";

if ( ! is_dir( dirname( $aisa_out ) ) ) {
	mkdir( dirname( $aisa_out ), 0755, true );
}
file_put_contents( $aisa_out, $aisa_head . $aisa_body );
echo 'wrote ' . $aisa_out . ' (' . count( $aisa_entries ) . " strings)\n";
