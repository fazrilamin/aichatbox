<?php
/**
 * Custom tables (documents, chunks, conversations), upgrades and the daily housekeeping cron.
 * DDL stays plain so it also runs on the SQLite integration used for local testing.
 *
 * @package ai-site-assistant
 */

defined( 'ABSPATH' ) || exit;

define( 'AISA_DB_VERSION', 1 );

/**
 * Table name helper.
 *
 * @param string $name docs|chunks|conversations.
 * @return string
 */
function aisa_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'aisa_' . $name;
}

/**
 * Create or update the tables.
 */
function aisa_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$docs    = aisa_table( 'docs' );
	$chunks  = aisa_table( 'chunks' );
	$convs   = aisa_table( 'conversations' );

	dbDelta(
		"CREATE TABLE $docs (
		post_id bigint(20) unsigned NOT NULL,
		post_type varchar(20) NOT NULL DEFAULT '',
		title text NOT NULL,
		url varchar(500) NOT NULL DEFAULT '',
		summary text NOT NULL,
		markdown longtext NOT NULL,
		hash char(32) NOT NULL DEFAULT '',
		chunks int(11) NOT NULL DEFAULT 0,
		words int(11) NOT NULL DEFAULT 0,
		indexed_at datetime NOT NULL,
		PRIMARY KEY  (post_id)
		) $charset;"
	);
	dbDelta(
		"CREATE TABLE $chunks (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		post_id bigint(20) unsigned NOT NULL DEFAULT 0,
		chunk_no int(11) NOT NULL DEFAULT 0,
		heading text NOT NULL,
		content longtext NOT NULL,
		terms longtext NOT NULL,
		length int(11) NOT NULL DEFAULT 0,
		embedding longtext NULL,
		PRIMARY KEY  (id),
		KEY post_id (post_id)
		) $charset;"
	);
	dbDelta(
		"CREATE TABLE $convs (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		session varchar(64) NOT NULL DEFAULT '',
		question text NOT NULL,
		answer longtext NOT NULL,
		sources text NOT NULL,
		page varchar(500) NOT NULL DEFAULT '',
		model varchar(100) NOT NULL DEFAULT '',
		latency_ms int(11) NOT NULL DEFAULT 0,
		tokens int(11) NOT NULL DEFAULT 0,
		handoff tinyint(1) NOT NULL DEFAULT 0,
		rating tinyint(1) NOT NULL DEFAULT 0,
		ip_hash varchar(64) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY created_at (created_at),
		KEY session (session)
		) $charset;"
	);
	update_option( 'aisa_db_version', AISA_DB_VERSION, false );
}

/**
 * Upgrades. "Replace current with uploaded" never runs the activation hook, so check on every load.
 */
function aisa_maybe_upgrade() {
	if ( (int) get_option( 'aisa_db_version' ) !== AISA_DB_VERSION ) {
		aisa_install_tables();
	}
	if ( get_option( 'aisa_rewrite_version' ) !== AISA_VERSION ) {
		update_option( 'aisa_rewrite_version', AISA_VERSION, false );
		flush_rewrite_rules( false );
	}
	if ( ! wp_next_scheduled( 'aisa_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'aisa_daily' );
	}
}
add_action( 'init', 'aisa_maybe_upgrade', 99 );

/**
 * Daily housekeeping: purge old conversations, optionally queue a full reindex.
 */
function aisa_daily() {
	global $wpdb;
	$days = (int) aisa_setting( 'retention' );
	if ( $days > 0 ) {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . aisa_table( 'conversations' ) . ' WHERE created_at < %s', $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}
	if ( aisa_setting( 'daily_reindex' ) ) {
		aisa_reindex_start();
		wp_schedule_single_event( time(), 'aisa_index_batch' );
	}
}
add_action( 'aisa_daily', 'aisa_daily' );
