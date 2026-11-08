<?php
/**
 * Admin: AI Assistant menu (Dashboard, Knowledge, Snippets, Conversations, Settings),
 * the admin REST routes behind the dashboard buttons, and the conversation export.
 *
 * @package ai-site-assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin menu.
 */
function aisa_admin_menu() {
	add_menu_page( __( 'AI Assistant', 'ai-site-assistant' ), __( 'AI Assistant', 'ai-site-assistant' ), 'manage_options', 'aisa', 'aisa_page_dashboard', 'dashicons-format-chat', 58 );
	add_submenu_page( 'aisa', __( 'AI Assistant', 'ai-site-assistant' ), __( 'Dashboard', 'ai-site-assistant' ), 'manage_options', 'aisa', 'aisa_page_dashboard' );
	add_submenu_page( 'aisa', __( 'Knowledge', 'ai-site-assistant' ), __( 'Knowledge', 'ai-site-assistant' ), 'manage_options', 'aisa-knowledge', 'aisa_page_knowledge' );
	add_submenu_page( 'aisa', __( 'Conversations', 'ai-site-assistant' ), __( 'Conversations', 'ai-site-assistant' ), 'manage_options', 'aisa-conversations', 'aisa_page_conversations' );
	add_submenu_page( 'aisa', __( 'Settings', 'ai-site-assistant' ), __( 'Settings', 'ai-site-assistant' ), 'manage_options', 'aisa-settings', 'aisa_page_settings' );
}
add_action( 'admin_menu', 'aisa_admin_menu' );

/**
 * Whether the current admin screen is one of ours.
 *
 * @return bool
 */
function aisa_is_admin_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return $screen && ( false !== strpos( $screen->id, 'aisa' ) || 'aisa_snippet' === $screen->post_type );
}

/**
 * Admin assets.
 */
function aisa_admin_assets() {
	if ( ! aisa_is_admin_screen() ) {
		return;
	}
	wp_enqueue_style( 'aisa-admin', AISA_URL . 'assets/css/admin.css', array(), AISA_VERSION );
	wp_enqueue_script(
		'aisa-admin',
		AISA_URL . 'assets/js/admin.js',
		array(),
		AISA_VERSION,
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
}
add_action( 'admin_enqueue_scripts', 'aisa_admin_assets' );

/**
 * Data the admin script needs, printed as a data attribute (no wp_localize_script).
 *
 * @return string
 */
function aisa_admin_data_attr() {
	return esc_attr(
		wp_json_encode(
			array(
				'rest'  => esc_url_raw( rest_url( 'aisa/v1/admin/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'text'  => array(
					'testing' => __( 'Testing…', 'ai-site-assistant' ),
					/* translators: %d: number of items */
					'indexed' => __( 'Indexed %d items.', 'ai-site-assistant' ),
					/* translators: 1: documents, 2: chunks, 3: last indexed */
					'stats'   => __( '%1$d documents · %2$d chunks · indexed %3$s', 'ai-site-assistant' ),
				),
			)
		)
	);
}

/**
 * Notice when the index is empty or out of date.
 */
function aisa_admin_notices() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ( 'dashboard' !== $screen->id && 'plugins' !== $screen->id && ! aisa_is_admin_screen() ) ) {
		return;
	}
	if ( isset( $_GET['page'] ) && 'aisa' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return; // The dashboard shows its own status.
	}
	$link = '<a href="' . esc_url( admin_url( 'admin.php?page=aisa' ) ) . '">' . esc_html__( 'Open the AI Assistant dashboard', 'ai-site-assistant' ) . '</a>';
	if ( ! get_option( 'aisa_last_indexed' ) ) {
		echo '<div class="notice notice-info"><p>' . esc_html__( 'AI Assistant & Chatbox has not indexed your content yet, so it cannot answer questions.', 'ai-site-assistant' ) . ' ' . $link . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} elseif ( get_option( 'aisa_needs_reindex' ) ) {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'AI Assistant & Chatbox settings changed what is indexed. Reindex to apply them.', 'ai-site-assistant' ) . ' ' . $link . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
add_action( 'admin_notices', 'aisa_admin_notices' );

/**
 * Settings link on the Plugins screen.
 *
 * @param string[] $links Links.
 * @return string[]
 */
function aisa_plugin_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=aisa-settings' ) ) . '">' . esc_html__( 'Settings', 'ai-site-assistant' ) . '</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( AISA_FILE ), 'aisa_plugin_links' );

/*
 * Admin REST routes.
 */

/**
 * Register the admin routes.
 */
function aisa_register_admin_routes() {
	$can = function () {
		return current_user_can( 'manage_options' );
	};
	register_rest_route(
		'aisa/v1',
		'/admin/test',
		array(
			'methods'             => 'POST',
			'callback'            => 'aisa_rest_admin_test',
			'permission_callback' => $can,
		)
	);
	register_rest_route(
		'aisa/v1',
		'/admin/reindex',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => fn() => rest_ensure_response( aisa_index_status() ),
				'permission_callback' => $can,
			),
			array(
				'methods'             => 'POST',
				'callback'            => function ( $r ) {
					if ( 'start' === $r->get_param( 'action' ) ) {
						aisa_reindex_start();
					}
					aisa_reindex_step( 5 );
					return rest_ensure_response( aisa_index_status() );
				},
				'permission_callback' => $can,
			),
		)
	);
}
add_action( 'rest_api_init', 'aisa_register_admin_routes' );

/**
 * Index progress plus totals.
 *
 * @return array
 */
function aisa_index_status() {
	global $wpdb;
	$state = aisa_index_state();
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	$state['docs']     = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . aisa_table( 'docs' ) );
	$state['chunks']   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . aisa_table( 'chunks' ) );
	$state['embedded'] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . aisa_table( 'chunks' ) . ' WHERE embedding IS NOT NULL' );
	// phpcs:enable
	$last              = (int) get_option( 'aisa_last_indexed' );
	$state['last']     = $last ? sprintf( /* translators: %s: time ago */ __( '%s ago', 'ai-site-assistant' ), human_time_diff( $last ) ) : __( 'never', 'ai-site-assistant' );
	return $state;
}

/**
 * Test the chat provider (and embeddings when on) with a tiny request.
 *
 * @return WP_REST_Response
 */
function aisa_rest_admin_test() {
	$out = array();
	$t0  = microtime( true );
	$res = aisa_chat(
		array(
			array(
				'role'    => 'system',
				'content' => 'This is a connection test. Reply with the single word OK.',
			),
			array(
				'role'    => 'user',
				'content' => 'Test',
			),
		),
		null,
		array(
			'max_tokens'  => 20,
			'temperature' => 0,
			'mock_answer' => 'OK',
		)
	);
	$out['chat'] = is_wp_error( $res )
		? array(
			'ok'      => false,
			'message' => $res->get_error_message(),
		)
		: array(
			'ok'      => true,
			'message' => sprintf( /* translators: 1: reply, 2: model, 3: milliseconds */ __( 'Replied “%1$s” using %2$s in %3$d ms.', 'ai-site-assistant' ), wp_trim_words( $res['text'], 8 ), $res['model'], round( ( microtime( true ) - $t0 ) * 1000 ) ),
		);
	if ( aisa_embed_config() ) {
		$e            = aisa_embed( array( 'connection test' ) );
		$out['embed'] = is_wp_error( $e )
			? array(
				'ok'      => false,
				'message' => $e->get_error_message(),
			)
			: array(
				'ok'      => true,
				'message' => sprintf( /* translators: %d: vector size */ __( 'Embeddings OK (%d dimensions).', 'ai-site-assistant' ), count( $e[0] ) ),
			);
	}
	return rest_ensure_response( $out );
}

/*
 * Dashboard.
 */

/**
 * Dashboard page.
 */
function aisa_page_dashboard() {
	global $wpdb;
	$cfg    = aisa_chat_config();
	$status = aisa_index_status();
	$convs  = aisa_table( 'conversations' );
	$since  = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	$week     = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, SUM(handoff) AS handoff, SUM(CASE WHEN rating < 0 THEN 1 ELSE 0 END) AS down, SUM(CASE WHEN rating > 0 THEN 1 ELSE 0 END) AS up, SUM(tokens) AS tokens FROM $convs WHERE created_at >= %s", $since ), ARRAY_A );
	$unanswer = $wpdb->get_results( "SELECT question, created_at FROM $convs WHERE handoff = 1 OR rating < 0 ORDER BY id DESC LIMIT 8", ARRAY_A );
	// phpcs:enable
	$last_err = get_option( 'aisa_last_error' );
	$warnings = array();
	if ( '' === $cfg['key'] && $cfg['preset']['key'] ) {
		/* translators: %s: provider name */
		$warnings[] = sprintf( __( 'No %s API key is set. Add it under Settings → AI model.', 'ai-site-assistant' ), $cfg['preset']['label'] );
	}
	if ( aisa_setting( 'embed_enabled' ) && ! aisa_embed_config() ) {
		$warnings[] = __( 'Semantic search is on but has no embeddings URL or model, so only keyword search is used. Check Settings → AI model.', 'ai-site-assistant' );
	}
	if ( ! get_option( 'permalink_structure' ) ) {
		$warnings[] = __( 'Plain permalinks are on. /llms.txt still works, but pretty permalinks are recommended.', 'ai-site-assistant' );
	}
	if ( file_exists( ABSPATH . 'robots.txt' ) ) {
		$warnings[] = __( 'A physical robots.txt file exists in the site root, so the AI crawler rules from this plugin are not served. Add them to that file by hand, or delete it.', 'ai-site-assistant' );
	}
	if ( file_exists( ABSPATH . 'llms.txt' ) ) {
		$warnings[] = __( 'A physical llms.txt file exists in the site root and is served instead of the generated one.', 'ai-site-assistant' );
	}
	if ( ! get_option( 'blog_public' ) ) {
		$warnings[] = __( 'Search engines are discouraged (Settings → Reading), so robots.txt blocks every crawler and the AI crawler rules are not added.', 'ai-site-assistant' );
	}
	?>
	<div class="wrap aisa-admin" data-aisa="<?php echo aisa_admin_data_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
		<h1><?php esc_html_e( 'AI Assistant', 'ai-site-assistant' ); ?></h1>
		<?php foreach ( $warnings as $w ) : ?>
			<div class="notice notice-warning inline"><p><?php echo esc_html( $w ); ?></p></div>
		<?php endforeach; ?>

		<div class="aisa-cards">
			<section class="aisa-card">
				<h2><?php esc_html_e( 'AI provider', 'ai-site-assistant' ); ?></h2>
				<p><strong><?php echo esc_html( aisa_provider_labels()[ $cfg['provider'] ] ); ?></strong> · <code><?php echo esc_html( $cfg['model'] ); ?></code></p>
				<p class="description"><?php echo esc_html( $cfg['url'] ); ?></p>
				<p><?php echo aisa_embed_config() ? esc_html__( 'Search: keywords + semantic (embeddings)', 'ai-site-assistant' ) : esc_html__( 'Search: keywords', 'ai-site-assistant' ); ?></p>
				<p><button type="button" class="button" data-aisa-test><?php esc_html_e( 'Test connection', 'ai-site-assistant' ); ?></button></p>
				<div class="aisa-test-result" aria-live="polite"></div>
				<?php if ( is_array( $last_err ) && $last_err['time'] > time() - DAY_IN_SECONDS ) : ?>
					<p class="aisa-error"><?php echo esc_html( sprintf( /* translators: 1: time ago, 2: message */ __( 'Last visitor error %1$s ago: %2$s', 'ai-site-assistant' ), human_time_diff( $last_err['time'] ), $last_err['message'] ) ); ?></p>
				<?php endif; ?>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=aisa-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'ai-site-assistant' ); ?></a></p>
			</section>

			<section class="aisa-card">
				<h2><?php esc_html_e( 'Knowledge', 'ai-site-assistant' ); ?></h2>
				<p class="aisa-stats" data-aisa-stats>
					<?php
					printf(
						/* translators: 1: documents, 2: chunks, 3: last indexed */
						esc_html__( '%1$d documents · %2$d chunks · indexed %3$s', 'ai-site-assistant' ),
						(int) $status['docs'],
						(int) $status['chunks'],
						esc_html( $status['last'] )
					);
					?>
				</p>
				<?php if ( aisa_embed_config() ) : ?>
					<p class="description"><?php echo esc_html( sprintf( /* translators: 1: embedded, 2: total */ __( '%1$d of %2$d chunks have embeddings.', 'ai-site-assistant' ), $status['embedded'], $status['chunks'] ) ); ?></p>
				<?php endif; ?>
				<?php if ( get_option( 'aisa_needs_reindex' ) ) : ?>
					<p class="aisa-error"><?php esc_html_e( 'Settings changed what is indexed. Reindex to apply them.', 'ai-site-assistant' ); ?></p>
				<?php endif; ?>
				<p><button type="button" class="button button-primary" data-aisa-reindex><?php esc_html_e( 'Reindex everything', 'ai-site-assistant' ); ?></button></p>
				<progress class="aisa-progress" max="100" value="0" hidden></progress>
				<div class="aisa-reindex-result" aria-live="polite"></div>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=aisa-knowledge' ) ); ?>"><?php esc_html_e( 'Indexed content and test search', 'ai-site-assistant' ); ?></a> · <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=aisa_snippet' ) ); ?>"><?php esc_html_e( 'Snippets', 'ai-site-assistant' ); ?></a></p>
			</section>

			<section class="aisa-card">
				<h2><?php esc_html_e( 'Last 7 days', 'ai-site-assistant' ); ?></h2>
				<?php if ( aisa_setting( 'log' ) ) : ?>
					<p class="aisa-big"><?php echo (int) $week['n']; ?> <span><?php esc_html_e( 'questions', 'ai-site-assistant' ); ?></span></p>
					<p>
						<?php
						printf(
							/* translators: 1: handed off, 2: thumbs up, 3: thumbs down, 4: tokens */
							esc_html__( '%1$d handed to the contact form · %2$d 👍 · %3$d 👎 · %4$s tokens', 'ai-site-assistant' ),
							(int) $week['handoff'],
							(int) $week['up'],
							(int) $week['down'],
							esc_html( number_format_i18n( (int) $week['tokens'] ) )
						);
						?>
					</p>
					<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=aisa-conversations' ) ); ?>"><?php esc_html_e( 'All conversations', 'ai-site-assistant' ); ?></a></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Conversation logging is off (Settings → Privacy & limits).', 'ai-site-assistant' ); ?></p>
				<?php endif; ?>
			</section>

			<section class="aisa-card">
				<h2><?php esc_html_e( 'AI-friendly output', 'ai-site-assistant' ); ?></h2>
				<ul class="aisa-links">
					<?php if ( aisa_setting( 'llms_txt' ) ) : ?>
						<li><a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener">/llms.txt</a></li>
						<li><a href="<?php echo esc_url( home_url( '/llms-full.txt' ) ); ?>" target="_blank" rel="noopener">/llms-full.txt</a></li>
					<?php endif; ?>
					<?php if ( aisa_setting( 'markdown' ) ) : ?>
						<li><a href="<?php echo esc_url( add_query_arg( 'format', 'markdown', home_url( '/' ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Home page as Markdown', 'ai-site-assistant' ); ?></a></li>
					<?php endif; ?>
					<li><a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener">/robots.txt</a></li>
				</ul>
			</section>
		</div>

		<?php if ( $unanswer ) : ?>
			<h2><?php esc_html_e( 'Questions the assistant could not answer well', 'ai-site-assistant' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Handed to the contact form or rated 👎. Add a snippet or improve the page so the next visitor gets an answer.', 'ai-site-assistant' ); ?></p>
			<table class="widefat striped aisa-unanswered">
				<tbody>
				<?php foreach ( $unanswer as $u ) : ?>
					<tr>
						<td><?php echo esc_html( $u['question'] ); ?></td>
						<td class="aisa-nowrap"><?php echo esc_html( human_time_diff( strtotime( $u['created_at'] . ' UTC' ) ) ); ?></td>
						<td class="aisa-nowrap"><a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=aisa_snippet&post_title=' . rawurlencode( $u['question'] ) ) ); ?>"><?php esc_html_e( 'Add snippet', 'ai-site-assistant' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/*
 * Knowledge.
 */

/**
 * Knowledge page: indexed documents, a no-cost test search and a "what the AI sees" preview.
 */
function aisa_page_knowledge() {
	global $wpdb;
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$preview = isset( $_GET['preview'] ) ? absint( $_GET['preview'] ) : 0;
	$q       = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	$done    = isset( $_GET['done'] ) ? sanitize_key( $_GET['done'] ) : '';
	// phpcs:enable
	?>
	<div class="wrap aisa-admin">
		<h1><?php esc_html_e( 'Knowledge', 'ai-site-assistant' ); ?></h1>
		<?php if ( $done ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( 'reindexed' === $done ? __( 'Reindexed.', 'ai-site-assistant' ) : ( 'excluded' === $done ? __( 'Excluded from the AI.', 'ai-site-assistant' ) : __( 'Included and indexed.', 'ai-site-assistant' ) ) ); ?></p></div>
		<?php endif; ?>

		<?php
		if ( $preview ) {
			aisa_render_preview( $preview );
			echo '</div>';
			return;
		}
		?>

		<h2><?php esc_html_e( 'Test search', 'ai-site-assistant' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Shows which content the assistant would read for a question. No AI is called, so it costs nothing.', 'ai-site-assistant' ); ?></p>
		<form method="get" class="aisa-search-form">
			<input type="hidden" name="page" value="aisa-knowledge">
			<input type="search" name="q" class="regular-text" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'e.g. Where is your operation centre?', 'ai-site-assistant' ); ?>">
			<?php submit_button( __( 'Search', 'ai-site-assistant' ), 'secondary', '', false ); ?>
		</form>
		<?php if ( '' !== $q ) : ?>
			<?php $found = aisa_search( $q, 6 ); ?>
			<?php if ( ! $found ) : ?>
				<p><?php esc_html_e( 'Nothing matched. The assistant would give the fallback message and offer the contact button.', 'ai-site-assistant' ); ?></p>
			<?php else : ?>
				<ol class="aisa-results">
				<?php foreach ( $found as $r ) : ?>
					<li>
						<strong><?php echo esc_html( $r['title'] ); ?></strong><?php echo '' !== $r['heading'] && $r['heading'] !== $r['title'] ? ' › ' . esc_html( $r['heading'] ) : ''; ?>
						<span class="description">(<?php echo esc_html( sprintf( 'keyword score %s', $r['bm25'] ) . ( null !== $r['cosine'] ? sprintf( ', similarity %s', $r['cosine'] ) : '' ) ); ?>)</span>
						<details><summary><?php esc_html_e( 'Text', 'ai-site-assistant' ); ?></summary><pre class="aisa-pre"><?php echo esc_html( $r['content'] ); ?></pre></details>
					</li>
				<?php endforeach; ?>
				</ol>
			<?php endif; ?>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Indexed content', 'ai-site-assistant' ); ?></h2>
		<?php
		$docs = $wpdb->get_results( 'SELECT post_id, post_type, title, url, chunks, words, indexed_at FROM ' . aisa_table( 'docs' ) . ' ORDER BY post_type, title', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		if ( ! $docs ) {
			echo '<p>' . esc_html__( 'Nothing is indexed yet. Use Reindex everything on the dashboard.', 'ai-site-assistant' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Title', 'ai-site-assistant' ) . '</th><th>' . esc_html__( 'Type', 'ai-site-assistant' ) . '</th><th>' . esc_html__( 'Chunks', 'ai-site-assistant' ) . '</th><th>' . esc_html__( 'Words', 'ai-site-assistant' ) . '</th><th>' . esc_html__( 'Indexed', 'ai-site-assistant' ) . '</th><th>' . esc_html__( 'Actions', 'ai-site-assistant' ) . '</th></tr></thead><tbody>';
			foreach ( $docs as $d ) {
				$type = get_post_type_object( $d['post_type'] );
				printf(
					'<tr><td><a href="%s">%s</a>%s</td><td>%s</td><td>%d</td><td>%s</td><td>%s</td><td class="aisa-nowrap"><a href="%s">%s</a> · <a href="%s">%s</a> · <a href="%s">%s</a></td></tr>',
					esc_url( (string) get_edit_post_link( $d['post_id'] ) ),
					esc_html( $d['title'] ),
					$d['url'] ? ' <a href="' . esc_url( $d['url'] ) . '" target="_blank" rel="noopener" class="aisa-ext" aria-label="' . esc_attr__( 'View', 'ai-site-assistant' ) . '">↗</a>' : '',
					esc_html( $type ? $type->labels->singular_name : $d['post_type'] ),
					(int) $d['chunks'],
					esc_html( number_format_i18n( (int) $d['words'] ) ),
					esc_html( human_time_diff( strtotime( $d['indexed_at'] . ' UTC' ) ) ),
					esc_url( admin_url( 'admin.php?page=aisa-knowledge&preview=' . (int) $d['post_id'] ) ),
					esc_html__( 'What the AI sees', 'ai-site-assistant' ),
					esc_url( aisa_doc_action_url( 'reindex', $d['post_id'] ) ),
					esc_html__( 'Reindex', 'ai-site-assistant' ),
					esc_url( aisa_doc_action_url( 'exclude', $d['post_id'] ) ),
					esc_html__( 'Exclude', 'ai-site-assistant' )
				);
			}
			echo '</tbody></table>';
		}

		$excluded = aisa_excluded_ids();
		if ( $excluded ) {
			echo '<h2>' . esc_html__( 'Excluded', 'ai-site-assistant' ) . '</h2><ul class="aisa-links">';
			foreach ( $excluded as $id ) {
				printf( '<li>%s (#%d) · <a href="%s">%s</a></li>', esc_html( get_the_title( $id ) ), (int) $id, esc_url( aisa_doc_action_url( 'include', $id ) ), esc_html__( 'Include', 'ai-site-assistant' ) );
			}
			echo '</ul>';
		}
		?>
	</div>
	<?php
}

/**
 * Markdown and chunks for one document.
 *
 * @param int $post_id Post ID.
 */
function aisa_render_preview( $post_id ) {
	global $wpdb;
	$doc    = aisa_doc( $post_id );
	$chunks = $wpdb->get_results( $wpdb->prepare( 'SELECT chunk_no, heading, content, length, embedding IS NOT NULL AS has_vec FROM ' . aisa_table( 'chunks' ) . ' WHERE post_id = %d ORDER BY chunk_no', $post_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=aisa-knowledge' ) ) . '">← ' . esc_html__( 'Back to Knowledge', 'ai-site-assistant' ) . '</a></p>';
	if ( ! $doc ) {
		echo '<p>' . esc_html__( 'This post is not indexed.', 'ai-site-assistant' ) . '</p>';
		return;
	}
	printf( '<h2>%s</h2><p class="description">%s</p>', esc_html( $doc['title'] ), esc_html( $doc['url'] ? $doc['url'] : __( 'Snippet (no public URL)', 'ai-site-assistant' ) ) );
	echo '<h3>' . esc_html( sprintf( /* translators: %d: number of chunks */ _n( '%d chunk', '%d chunks', count( $chunks ), 'ai-site-assistant' ), count( $chunks ) ) ) . '</h3>';
	foreach ( $chunks as $c ) {
		printf(
			'<details class="aisa-chunk"><summary>#%d %s <span class="description">(%d terms%s)</span></summary><pre class="aisa-pre">%s</pre></details>',
			(int) $c['chunk_no'] + 1,
			esc_html( $c['heading'] ),
			(int) $c['length'],
			$c['has_vec'] ? ', ' . esc_html__( 'embedded', 'ai-site-assistant' ) : '',
			esc_html( $c['content'] )
		);
	}
}

/**
 * Signed URL for a per-document action.
 *
 * @param string $do      reindex|exclude|include.
 * @param int    $post_id Post ID.
 * @return string
 */
function aisa_doc_action_url( $do, $post_id ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=aisa_doc&do=' . $do . '&id=' . (int) $post_id ), 'aisa_doc_' . (int) $post_id );
}

/**
 * Handle per-document actions.
 */
function aisa_handle_doc_action() {
	global $wpdb;
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	check_admin_referer( 'aisa_doc_' . $id );
	if ( ! current_user_can( 'manage_options' ) || ! $id ) {
		wp_die( esc_html__( 'Not allowed.', 'ai-site-assistant' ) );
	}
	$do       = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : '';
	$excluded = aisa_excluded_ids();
	if ( 'exclude' === $do ) {
		$excluded[] = $id;
	} elseif ( 'include' === $do ) {
		$excluded = array_diff( $excluded, array( $id ) );
	}
	if ( 'exclude' === $do || 'include' === $do ) {
		// Save through the settings sanitizer with every other value unchanged.
		$opt                = aisa_setting();
		$opt['exclude_ids'] = implode( ',', array_unique( $excluded ) );
		unset( $opt['api_key'], $opt['embed_key'] ); // Empty secret fields keep their saved values.
		update_option( 'aisa_settings', $opt );
		delete_option( 'aisa_needs_reindex' );
	}
	if ( 'reindex' === $do ) {
		$wpdb->update( aisa_table( 'docs' ), array( 'hash' => '' ), array( 'post_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	aisa_index_post( $id );
	$done = array(
		'reindex' => 'reindexed',
		'exclude' => 'excluded',
		'include' => 'included',
	);
	wp_safe_redirect( admin_url( 'admin.php?page=aisa-knowledge&done=' . ( isset( $done[ $do ] ) ? $done[ $do ] : 'reindexed' ) ) );
	exit;
}
add_action( 'admin_post_aisa_doc', 'aisa_handle_doc_action' );

/*
 * Conversations.
 */

/**
 * Conversations page.
 */
function aisa_page_conversations() {
	require_once AISA_DIR . 'inc/class-aisa-conversations-table.php';
	$table = new AISA_Conversations_Table();
	$table->process_bulk_action();
	$table->prepare_items();
	$export = wp_nonce_url( add_query_arg( array( 'action' => 'aisa_export' ) + $table->filter_args(), admin_url( 'admin-post.php' ) ), 'aisa_export' );
	?>
	<div class="wrap aisa-admin">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Conversations', 'ai-site-assistant' ); ?></h1>
		<a href="<?php echo esc_url( $export ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'ai-site-assistant' ); ?></a>
		<hr class="wp-header-end">
		<?php if ( ! aisa_setting( 'log' ) ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'Conversation logging is off, so new questions are not saved.', 'ai-site-assistant' ); ?></p></div>
		<?php endif; ?>
		<?php $table->views(); ?>
		<form method="get">
			<input type="hidden" name="page" value="aisa-conversations">
			<?php
			$table->search_box( __( 'Search questions', 'ai-site-assistant' ), 'aisa-conv' );
			$table->display();
			?>
		</form>
	</div>
	<?php
}

/**
 * CSV export of the (filtered) conversations.
 */
function aisa_handle_export() {
	check_admin_referer( 'aisa_export' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ai-site-assistant' ) );
	}
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	require_once AISA_DIR . 'inc/class-aisa-conversations-table.php';
	$rows = AISA_Conversations_Table::query( AISA_Conversations_Table::current_filters(), 0, 0 );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="ai-conversations-' . gmdate( 'Y-m-d' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- BOM so Excel reads UTF-8.
	fputcsv( $out, array( 'date_utc', 'question', 'answer', 'sources', 'page', 'model', 'latency_ms', 'tokens', 'handoff', 'rating', 'session' ) );
	foreach ( $rows as $r ) {
		$sources = json_decode( $r['sources'], true );
		fputcsv(
			$out,
			array_map(
				'aisa_csv_cell',
				array( $r['created_at'], $r['question'], $r['answer'], is_array( $sources ) ? implode( ' ', array_filter( $sources ) ) : '', $r['page'], $r['model'], $r['latency_ms'], $r['tokens'], $r['handoff'], $r['rating'], $r['session'] )
			)
		);
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}
add_action( 'admin_post_aisa_export', 'aisa_handle_export' );

/**
 * Neutralise spreadsheet formulas in exported text (visitors write the questions).
 *
 * @param mixed $v Cell.
 * @return string
 */
function aisa_csv_cell( $v ) {
	$v = (string) $v;
	return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@' ), true ) && ! is_numeric( $v ) ) ? "'" . $v : $v;
}
