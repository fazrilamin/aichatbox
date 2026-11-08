<?php
/**
 * Uninstall: removes tables, options and snippets only when "Delete all data" is ticked.
 *
 * @package ai-site-assistant
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$aisa_settings = get_option( 'aisa_settings', array() );
if ( empty( $aisa_settings['delete_data'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'docs', 'chunks', 'conversations' ) as $aisa_t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'aisa_' . $aisa_t ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
}
foreach ( array( 'aisa_settings', 'aisa_db_version', 'aisa_rewrite_version', 'aisa_index_state', 'aisa_index_queue', 'aisa_last_indexed', 'aisa_needs_reindex', 'aisa_last_error' ) as $aisa_o ) {
	delete_option( $aisa_o );
}
foreach ( array( 'aisa_bm25', 'aisa_llms_index', 'aisa_llms_full', 'aisa_index_lock', 'aisa_update_info' ) as $aisa_o ) {
	delete_transient( $aisa_o );
}
$aisa_snippets = get_posts(
	array(
		'post_type'      => 'aisa_snippet',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $aisa_snippets as $aisa_id ) {
	wp_delete_post( $aisa_id, true );
}
wp_clear_scheduled_hook( 'aisa_daily' );
wp_clear_scheduled_hook( 'aisa_index_batch' );
