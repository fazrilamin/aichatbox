<?php
/**
 * Retrieval: BM25 keyword ranking over the chunks (pure PHP, works on any database),
 * optionally fused with embedding similarity when embeddings are on.
 *
 * @package ai-site-assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Words too common to rank on (English and Malay).
 *
 * @return array<string,bool>
 */
function aisa_stopwords() {
	static $set = null;
	if ( null === $set ) {
		$words = 'a an and are as at be but by can could do does did for from had has have how i if in into is it its me my of on or our so than that the their them then there these they this to was we were what when where which who why will with would you your yours about also any all just more most not no only other some such very via vs ' .
			'ada adakah akan apa bagaimana bagi boleh dalam dan dari dengan di ini itu ke kami kamu kita mana pada saya sebagai serta tidak untuk yang';
		$set   = array_fill_keys( explode( ' ', $words ), true );
	}
	return $set;
}

/**
 * Light stemming and spelling normalisation so "centres", "center" and "centre" match.
 *
 * @param string $t Token.
 * @return string
 */
function aisa_stem( $t ) {
	static $map = array(
		'center'       => 'centre',
		'centers'      => 'centre',
		'datacenter'   => 'datacentre',
		'color'        => 'colour',
		'optimize'     => 'optimise',
		'organization' => 'organisation',
	);
	if ( isset( $map[ $t ] ) ) {
		return $map[ $t ];
	}
	$l = strlen( $t );
	if ( $l > 4 && 'ies' === substr( $t, -3 ) ) {
		return substr( $t, 0, -3 ) . 'y';
	}
	if ( $l > 3 && 's' === substr( $t, -1 ) && ! preg_match( '/(ss|us|is)$/', $t ) ) {
		return substr( $t, 0, -1 );
	}
	return $t;
}

/**
 * Tokenise text for ranking.
 *
 * @param string $text Text.
 * @return string[]
 */
function aisa_tokens( $text ) {
	$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
	preg_match_all( '/[\p{L}\p{N}]+(?:[.\-\'’][\p{L}\p{N}]+)*/u', $text, $m );
	$stop = aisa_stopwords();
	$out  = array();
	foreach ( $m[0] as $t ) {
		$t = str_replace( array( "'", '’' ), '', $t );
		if ( isset( $stop[ $t ] ) || ( strlen( $t ) < 2 && ! ctype_digit( $t ) ) ) {
			continue;
		}
		$out[] = aisa_stem( $t );
		if ( false !== strpos( $t, '-' ) ) {
			$out[] = aisa_stem( str_replace( '-', '', $t ) );
			foreach ( explode( '-', $t ) as $part ) {
				if ( strlen( $part ) > 1 && ! isset( $stop[ $part ] ) ) {
					$out[] = aisa_stem( $part );
				}
			}
		}
	}
	return $out;
}

/**
 * Term frequencies of a text.
 *
 * @param string $text Text.
 * @return array<string,int>
 */
function aisa_terms( $text ) {
	return array_count_values( aisa_tokens( $text ) );
}

/**
 * BM25 statistics for the whole index, cached until the index changes.
 *
 * @return array{n:int,avgdl:float,df:array<string,int>,docs:array<int,array>}
 */
function aisa_bm25_index() {
	global $wpdb;
	$idx = get_transient( 'aisa_bm25' );
	if ( is_array( $idx ) ) {
		return $idx;
	}
	$rows  = $wpdb->get_results( 'SELECT id, post_id, terms, length FROM ' . aisa_table( 'chunks' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	$docs  = array();
	$df    = array();
	$total = 0;
	foreach ( (array) $rows as $r ) {
		$terms = json_decode( $r['terms'], true );
		$terms = is_array( $terms ) ? $terms : array();
		$docs[ (int) $r['id'] ] = array( (int) $r['post_id'], (int) $r['length'], $terms );
		$total                 += (int) $r['length'];
		foreach ( $terms as $t => $unused ) {
			$df[ $t ] = isset( $df[ $t ] ) ? $df[ $t ] + 1 : 1;
		}
	}
	$n   = count( $docs );
	$idx = array(
		'n'     => $n,
		'avgdl' => $n ? $total / $n : 0,
		'df'    => $df,
		'docs'  => $docs,
	);
	set_transient( 'aisa_bm25', $idx, DAY_IN_SECONDS );
	return $idx;
}

/**
 * Find the chunks that best answer a query.
 *
 * @param string $query Query.
 * @param int    $k     Number of chunks.
 * @param array  $ctx   Context: page (URL the visitor is on).
 * @return array<int,array{id:int,post_id:int,title:string,url:string,post_type:string,heading:string,content:string,score:float}>
 */
function aisa_search( $query, $k = 6, $ctx = array() ) {
	global $wpdb;
	$idx = aisa_bm25_index();
	if ( ! $idx['n'] ) {
		return array();
	}

	// Keyword ranking.
	$q   = array_unique( aisa_tokens( $query ) );
	$idf = array();
	foreach ( $q as $t ) {
		if ( isset( $idx['df'][ $t ] ) ) {
			$df        = $idx['df'][ $t ];
			$idf[ $t ] = log( 1 + ( $idx['n'] - $df + 0.5 ) / ( $df + 0.5 ) );
		}
	}
	$k1   = 1.2;
	$b    = 0.75;
	$bm25 = array();
	if ( $idf ) {
		foreach ( $idx['docs'] as $id => $d ) {
			$s = 0.0;
			foreach ( $idf as $t => $w ) {
				if ( isset( $d[2][ $t ] ) ) {
					$tf = $d[2][ $t ];
					$s += $w * ( $tf * ( $k1 + 1 ) ) / ( $tf + $k1 * ( 1 - $b + $b * $d[1] / max( 1, $idx['avgdl'] ) ) );
				}
			}
			if ( $s > 0 ) {
				$bm25[ $id ] = $s;
			}
		}
		arsort( $bm25 );
	}

	// Semantic ranking.
	$cos = array();
	if ( aisa_embed_config() ) {
		$qv = aisa_embed( array( $query ) );
		if ( ! is_wp_error( $qv ) ) {
			$qv   = aisa_unpack_vector( aisa_pack_vector( $qv[0] ) );
			$rows = $wpdb->get_results( 'SELECT id, embedding FROM ' . aisa_table( 'chunks' ) . ' WHERE embedding IS NOT NULL', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			foreach ( (array) $rows as $r ) {
				$v = aisa_unpack_vector( $r['embedding'] );
				if ( count( $v ) === count( $qv ) ) {
					$dot = 0.0;
					foreach ( $v as $i => $x ) {
						$dot += $x * $qv[ $i ];
					}
					$cos[ (int) $r['id'] ] = $dot;
				}
			}
			arsort( $cos );
		}
	}

	// Reciprocal-rank fusion (or plain BM25 order). The page the visitor is on gets a nudge worth
	// about one rank: fused scores sit close together, so anything bigger outranks better matches.
	$score = array();
	foreach ( array( $bm25, $cos ) as $list ) {
		$rank = 0;
		foreach ( array_slice( $list, 0, 40, true ) as $id => $unused ) {
			$score[ $id ] = ( isset( $score[ $id ] ) ? $score[ $id ] : 0 ) + 1 / ( 60 + ++$rank );
		}
	}
	if ( ! $score ) {
		return array();
	}
	$page_post = ! empty( $ctx['page'] ) ? url_to_postid( $ctx['page'] ) : 0;
	if ( $page_post ) {
		foreach ( $score as $id => $s ) {
			if ( $idx['docs'][ $id ][0] === $page_post ) {
				$score[ $id ] = $s * 1.02;
			}
		}
	}
	arsort( $score );
	$top = array_slice( $score, 0, max( 1, (int) $k ), true );

	$ids  = implode( ',', array_map( 'intval', array_keys( $top ) ) );
	$rows = $wpdb->get_results( 'SELECT c.id, c.post_id, c.heading, c.content, d.title, d.url, d.post_type FROM ' . aisa_table( 'chunks' ) . ' c INNER JOIN ' . aisa_table( 'docs' ) . " d ON d.post_id = c.post_id WHERE c.id IN ($ids)", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	$by   = array();
	foreach ( (array) $rows as $r ) {
		$by[ (int) $r['id'] ] = $r;
	}
	$out = array();
	foreach ( $top as $id => $s ) {
		if ( isset( $by[ $id ] ) ) {
			$r     = $by[ $id ];
			$out[] = array(
				'id'        => (int) $id,
				'post_id'   => (int) $r['post_id'],
				'title'     => $r['title'],
				'url'       => $r['url'],
				'post_type' => $r['post_type'],
				'heading'   => $r['heading'],
				'content'   => $r['content'],
				'score'     => round( $s * 1000, 2 ),
				'bm25'      => isset( $bm25[ $id ] ) ? round( $bm25[ $id ], 2 ) : 0,
				'cosine'    => isset( $cos[ $id ] ) ? round( $cos[ $id ], 3 ) : null,
			);
		}
	}
	return apply_filters( 'aisa_search_results', $out, $query, $ctx );
}
