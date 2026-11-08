<?php
/**
 * Settings: one option, aisa_settings, edited on AI Assistant → Settings.
 * aisa_setting() is the only reader; it merges saved values over the defaults.
 *
 * @package ai-site-assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Known AI crawlers for the robots.txt rules.
 *
 * @return array<string,string> user agent => description
 */
function aisa_ai_bots() {
	return array(
		'GPTBot'             => __( 'OpenAI model training', 'ai-site-assistant' ),
		'OAI-SearchBot'      => __( 'ChatGPT search results', 'ai-site-assistant' ),
		'ChatGPT-User'       => __( 'ChatGPT browsing on a user\'s request', 'ai-site-assistant' ),
		'ClaudeBot'          => __( 'Anthropic model training', 'ai-site-assistant' ),
		'Claude-SearchBot'   => __( 'Claude search results', 'ai-site-assistant' ),
		'Claude-User'        => __( 'Claude browsing on a user\'s request', 'ai-site-assistant' ),
		'PerplexityBot'      => __( 'Perplexity search results', 'ai-site-assistant' ),
		'Google-Extended'    => __( 'Gemini training and grounding (not Google Search)', 'ai-site-assistant' ),
		'Applebot-Extended'  => __( 'Apple Intelligence training', 'ai-site-assistant' ),
		'meta-externalagent' => __( 'Meta AI training', 'ai-site-assistant' ),
		'CCBot'              => __( 'Common Crawl (used by many AI datasets)', 'ai-site-assistant' ),
		'Bytespider'         => __( 'ByteDance model training', 'ai-site-assistant' ),
	);
}

/**
 * Settings fields, grouped by tab.
 *
 * Each entry: key => [label, type, tab, default, extra]. Types: text, password, url, number, float,
 * textarea, select (extra = options), checkbox, post_types, robots. Labels and default texts are
 * translated, so this must not run before init (see aisa_load_textdomain()).
 *
 * @return array<string,array>
 */
function aisa_fields() {
	return array(
		// AI.
		'provider'        => array( __( 'Provider', 'ai-site-assistant' ), 'select', 'ai', 'openai', array( 'options' => aisa_provider_labels() ) ),
		'base_url'        => array( __( 'API base URL', 'ai-site-assistant' ), 'url', 'ai', '', array( 'desc' => __( 'Leave empty for the provider default. Needed for Ollama on your own server (its HTTPS address) and for "Other OpenAI-compatible API" (an address ending in /v1).', 'ai-site-assistant' ) ) ),
		'api_key'         => array( __( 'API key / bearer token', 'ai-site-assistant' ), 'password', 'ai', '', array( 'desc' => __( 'The API key from your provider, or the bearer token your Ollama reverse proxy checks. Can also be set with define( \'AISA_API_KEY\', \'…\' ) in wp-config.php.', 'ai-site-assistant' ) ) ),
		'model'           => array( __( 'Chat model', 'ai-site-assistant' ), 'text', 'ai', '', array( 'desc' => __( 'Leave empty for the provider default (OpenAI: gpt-6-luna, Gemini: gemini-3.8-flash, Claude: claude-haiku-5-5, DeepSeek: deepseek-chat, OpenRouter: openrouter/auto, Ollama: llama3.1:8b). Groq and other APIs need a model name.', 'ai-site-assistant' ) ) ),
		'temperature'     => array( __( 'Temperature', 'ai-site-assistant' ), 'float', 'ai', 0.3, array( 'desc' => __( '0 = most factual, 1 = most creative. 0.2–0.4 suits a website assistant. Models that only accept their own default are sent none.', 'ai-site-assistant' ) ) ),
		'max_tokens'      => array( __( 'Max answer length (tokens)', 'ai-site-assistant' ), 'number', 'ai', 700 ),
		'stream'          => array( __( 'Stream answers word by word', 'ai-site-assistant' ), 'checkbox', 'ai', 1, array( 'desc' => __( 'Turn off if your host or CDN buffers responses and answers arrive all at once anyway.', 'ai-site-assistant' ) ) ),
		'assistant_name'  => array( __( 'Assistant name', 'ai-site-assistant' ), 'text', 'ai', __( 'Assistant', 'ai-site-assistant' ) ),
		'tone'            => array(
			__( 'Tone', 'ai-site-assistant' ),
			'select',
			'ai',
			'match',
			array(
				'options' => array(
					'match'        => __( 'Match the website', 'ai-site-assistant' ),
					'friendly'     => __( 'Friendly', 'ai-site-assistant' ),
					'professional' => __( 'Professional', 'ai-site-assistant' ),
				),
			),
		),
		'instructions'    => array( __( 'Extra instructions', 'ai-site-assistant' ), 'textarea', 'ai', '', array( 'desc' => __( 'House rules added to every prompt, e.g. spelling conventions, products you do or don\'t sell, what to say about pricing.', 'ai-site-assistant' ) ) ),
		'embed_enabled'   => array( __( 'Semantic search (embeddings)', 'ai-site-assistant' ), 'checkbox', 'ai', 0, array( 'desc' => __( 'Adds meaning-based matching on top of keyword search. Reindex after changing any embeddings setting.', 'ai-site-assistant' ) ) ),
		'embed_provider'  => array( __( 'Embeddings API', 'ai-site-assistant' ), 'select', 'ai', 'ollama', array( 'options' => aisa_embed_types() ) ),
		'embed_url'       => array( __( 'Embeddings base URL', 'ai-site-assistant' ), 'url', 'ai', '', array( 'desc' => __( 'Empty = the chat provider\'s URL and key, when it offers this API (Ollama for Ollama; OpenAI, Gemini or OpenRouter for OpenAI-compatible). DeepSeek, Claude and Groq have no embeddings API, so enter another service here, e.g. https://api.openai.com/v1 or your Ollama server.', 'ai-site-assistant' ) ) ),
		'embed_key'       => array( __( 'Embeddings API key / bearer token', 'ai-site-assistant' ), 'password', 'ai', '', array( 'desc' => __( 'Empty = the chat API key, when the chat provider\'s URL is used.', 'ai-site-assistant' ) ) ),
		'embed_model'     => array( __( 'Embeddings model', 'ai-site-assistant' ), 'text', 'ai', '', array( 'desc' => __( 'Empty = the default (Ollama: nomic-embed-text, OpenAI: text-embedding-3-small, Gemini: gemini-embedding-001, OpenRouter: openai/text-embedding-3-small).', 'ai-site-assistant' ) ) ),

		// Knowledge.
		'post_types'      => array( __( 'Content to index', 'ai-site-assistant' ), 'post_types', 'knowledge', array( 'page', 'post' ) ),
		'exclude_ids'     => array( __( 'Exclude post IDs', 'ai-site-assistant' ), 'text', 'knowledge', '', array( 'desc' => __( 'Comma-separated IDs, e.g. a thank-you page. You can also exclude pages from AI Assistant → Knowledge.', 'ai-site-assistant' ) ) ),
		'chunk_size'      => array( __( 'Chunk size (characters)', 'ai-site-assistant' ), 'number', 'knowledge', 2500 ),
		'context_chars'   => array( __( 'Website content sent per question (characters)', 'ai-site-assistant' ), 'number', 'knowledge', 12000, array( 'desc' => __( 'More context gives fuller answers but costs more. Small Ollama models need a context window of at least 8k tokens for the default (set OLLAMA_CONTEXT_LENGTH on the server).', 'ai-site-assistant' ) ) ),
		'daily_reindex'   => array( __( 'Reindex everything daily', 'ai-site-assistant' ), 'checkbox', 'knowledge', 1, array( 'desc' => __( 'Edits to posts are indexed straight away. The daily run also picks up content that blocks pull from elsewhere (lists, counters, settings).', 'ai-site-assistant' ) ) ),

		// Widget.
		'widget_mode'     => array(
			__( 'Placement', 'ai-site-assistant' ),
			'select',
			'widget',
			'both',
			array(
				'options' => array(
					'floating' => __( 'Floating button', 'ai-site-assistant' ),
					'attach'   => __( 'Attach to existing search bars', 'ai-site-assistant' ),
					'both'     => __( 'Both', 'ai-site-assistant' ),
					'none'     => __( 'Off (shortcode [aisa_search] only)', 'ai-site-assistant' ),
				),
			),
		),
		'attach_selector' => array( __( 'Search bar selector', 'ai-site-assistant' ), 'text', 'widget', 'form[role=search] input[type=search], input.wp-block-search__input', array( 'desc' => __( 'CSS selector for the search inputs the AI attaches to.', 'ai-site-assistant' ) ) ),
		'position'        => array(
			__( 'Button position', 'ai-site-assistant' ),
			'select',
			'widget',
			'right',
			array(
				'options' => array(
					'right' => __( 'Bottom right', 'ai-site-assistant' ),
					'left'  => __( 'Bottom left', 'ai-site-assistant' ),
				),
			),
		),
		'margin'          => array( __( 'Margin from edge (px)', 'ai-site-assistant' ), 'number', 'widget', 24 ),
		'scheme'          => array(
			__( 'Colour scheme', 'ai-site-assistant' ),
			'select',
			'widget',
			'auto',
			array(
				'options' => array(
					'auto'  => __( 'Automatic (follows the page background)', 'ai-site-assistant' ),
					'light' => __( 'Light', 'ai-site-assistant' ),
					'dark'  => __( 'Dark', 'ai-site-assistant' ),
				),
			),
		),
		'accent'          => array( __( 'Accent colour', 'ai-site-assistant' ), 'text', 'widget', '', array( 'desc' => __( 'Any CSS colour or gradient, e.g. #e11d48, linear-gradient(135deg, #6366f1, #ec4899) or var(--wp--preset--color--secondary). Empty = the theme\'s primary colour.', 'ai-site-assistant' ) ) ),
		'button_label'    => array( __( 'Button text', 'ai-site-assistant' ), 'text', 'widget', __( 'Ask AI', 'ai-site-assistant' ) ),
		'title'           => array( __( 'Panel title', 'ai-site-assistant' ), 'text', 'widget', __( 'Ask us anything', 'ai-site-assistant' ) ),
		'placeholder'     => array( __( 'Input placeholder', 'ai-site-assistant' ), 'text', 'widget', __( 'Ask a question…', 'ai-site-assistant' ) ),
		'welcome'         => array( __( 'Welcome message', 'ai-site-assistant' ), 'textarea', 'widget', __( 'Hi! I can answer questions about this website. What would you like to know?', 'ai-site-assistant' ) ),
		'starters'        => array( __( 'Starter questions', 'ai-site-assistant' ), 'textarea', 'widget', '', array( 'desc' => __( 'One per line. 3 or 4 work best.', 'ai-site-assistant' ) ) ),
		'fallback'        => array( __( 'Fallback message', 'ai-site-assistant' ), 'textarea', 'widget', __( 'Sorry, I couldn\'t find that on this website. Our team will be happy to help.', 'ai-site-assistant' ) ),
		'disclaimer'      => array( __( 'Disclaimer', 'ai-site-assistant' ), 'text', 'widget', __( 'AI answers can be wrong. Check the linked pages.', 'ai-site-assistant' ) ),
		'cta_label'       => array( __( 'Contact button text', 'ai-site-assistant' ), 'text', 'widget', __( 'Contact us', 'ai-site-assistant' ) ),
		'cta_url'         => array( __( 'Contact button link', 'ai-site-assistant' ), 'text', 'widget', '', array( 'desc' => __( 'Shown when the AI can\'t answer, e.g. /contact-us/. Empty hides the button.', 'ai-site-assistant' ) ) ),

		// AI-friendly.
		'llms_txt'        => array( __( 'Serve /llms.txt and /llms-full.txt', 'ai-site-assistant' ), 'checkbox', 'friendly', 1 ),
		'llms_intro'      => array( __( 'llms.txt introduction', 'ai-site-assistant' ), 'textarea', 'friendly', '', array( 'desc' => __( 'A few sentences about the organisation for AI tools. Empty = the site tagline.', 'ai-site-assistant' ) ) ),
		'markdown'        => array( __( 'Markdown version of each page', 'ai-site-assistant' ), 'checkbox', 'friendly', 1, array( 'desc' => __( 'Adds ?format=markdown to indexed pages and links it from the page head.', 'ai-site-assistant' ) ) ),
		'robots'          => array( __( 'AI crawlers in robots.txt', 'ai-site-assistant' ), 'robots', 'friendly', array() ),

		// Privacy.
		'log'             => array( __( 'Log conversations', 'ai-site-assistant' ), 'checkbox', 'privacy', 1 ),
		'retention'       => array( __( 'Keep conversations for (days)', 'ai-site-assistant' ), 'number', 'privacy', 90 ),
		'rate_limit'      => array( __( 'Questions per visitor per hour', 'ai-site-assistant' ), 'number', 'privacy', 30 ),
		'allowed_domains' => array( __( 'Other allowed domains', 'ai-site-assistant' ), 'textarea', 'privacy', '', array( 'desc' => __( 'One per line, e.g. staging.example.com. Requests from any other site are refused. This site\'s own domain is always allowed.', 'ai-site-assistant' ) ) ),
		'delete_data'     => array( __( 'Delete all data when the plugin is deleted', 'ai-site-assistant' ), 'checkbox', 'privacy', 0 ),
	);
}

/**
 * Settings tabs.
 *
 * @return array<string,string>
 */
function aisa_tabs() {
	return array(
		'ai'        => __( 'AI model', 'ai-site-assistant' ),
		'knowledge' => __( 'Knowledge', 'ai-site-assistant' ),
		'widget'    => __( 'Widget', 'ai-site-assistant' ),
		'friendly'  => __( 'AI-friendly', 'ai-site-assistant' ),
		'privacy'   => __( 'Privacy & limits', 'ai-site-assistant' ),
	);
}

/**
 * Read one setting (or all of them), with defaults applied.
 *
 * @param string|null $key   Setting key.
 * @param bool        $reset Drop the cached copy (after saving).
 * @return mixed
 */
function aisa_setting( $key = null, $reset = false ) {
	static $cache = null;
	if ( null === $cache || $reset ) {
		$saved = get_option( 'aisa_settings', array() );
		$saved = is_array( $saved ) ? $saved : array();
		$cache = array();
		foreach ( aisa_fields() as $k => $def ) {
			$cache[ $k ] = array_key_exists( $k, $saved ) ? $saved[ $k ] : $def[3];
		}
		if ( defined( 'AISA_API_KEY' ) && AISA_API_KEY ) {
			$cache['api_key'] = AISA_API_KEY;
		}
	}
	if ( null === $key ) {
		return $cache;
	}
	return isset( $cache[ $key ] ) ? $cache[ $key ] : null;
}

/**
 * Forget the cached settings after they are saved.
 */
function aisa_reset_settings_cache() {
	aisa_setting( null, true );
}
add_action( 'update_option_aisa_settings', 'aisa_reset_settings_cache' );
add_action( 'add_option_aisa_settings', 'aisa_reset_settings_cache' );

/**
 * Register the option. Not autoloaded: the front end only needs it when the widget prints.
 */
function aisa_settings_init() {
	register_setting(
		'aisa_settings',
		'aisa_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'aisa_sanitize_settings',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'aisa_settings_init' );

/**
 * Sanitize the settings form.
 *
 * @param mixed $input Raw input.
 * @return array
 */
function aisa_sanitize_settings( $input ) {
	$input = is_array( $input ) ? $input : array();
	$old   = get_option( 'aisa_settings', array() );
	$old   = is_array( $old ) ? $old : array();
	$out   = array();
	foreach ( aisa_fields() as $key => $def ) {
		$raw = isset( $input[ $key ] ) ? $input[ $key ] : null;
		switch ( $def[1] ) {
			case 'checkbox':
				$out[ $key ] = empty( $raw ) ? 0 : 1;
				break;
			case 'number':
				$out[ $key ] = max( 0, (int) $raw );
				break;
			case 'float':
				$out[ $key ] = min( 2, max( 0, round( (float) $raw, 2 ) ) );
				break;
			case 'url':
				$out[ $key ] = untrailingslashit( esc_url_raw( trim( (string) $raw ) ) );
				break;
			case 'password':
				// Secrets are never printed back, so an empty field keeps the saved value.
				$raw = trim( (string) $raw );
				if ( ! empty( $input['_clear'][ $key ] ) ) {
					$out[ $key ] = '';
				} elseif ( '' === $raw ) {
					$out[ $key ] = isset( $old[ $key ] ) ? $old[ $key ] : '';
				} else {
					$out[ $key ] = sanitize_text_field( $raw );
				}
				break;
			case 'textarea':
				$out[ $key ] = sanitize_textarea_field( (string) $raw );
				break;
			case 'select':
				$raw         = (string) $raw;
				$out[ $key ] = isset( $def[4]['options'][ $raw ] ) ? $raw : $def[3];
				break;
			case 'post_types':
				$public      = get_post_types( array( 'public' => true ) );
				$out[ $key ] = array_values( array_intersect( array_map( 'sanitize_key', (array) $raw ), $public ) );
				break;
			case 'robots':
				$out[ $key ] = array();
				foreach ( array_keys( aisa_ai_bots() ) as $bot ) {
					$v = isset( $raw[ $bot ] ) ? (string) $raw[ $bot ] : '';
					if ( 'allow' === $v || 'block' === $v ) {
						$out[ $key ][ $bot ] = $v;
					}
				}
				break;
			default:
				$out[ $key ] = sanitize_text_field( (string) $raw );
		}
	}
	if ( 'none' !== $out['widget_mode'] && '' === trim( $out['attach_selector'] ) ) {
		$out['attach_selector'] = $old['attach_selector'] ?? aisa_fields()['attach_selector'][3];
	}
	$out['chunk_size'] = min( 8000, max( 600, $out['chunk_size'] ) );
	$out['max_tokens'] = min( 8000, max( 100, $out['max_tokens'] ) );
	$out['context_chars'] = min( 60000, max( 2000, $out['context_chars'] ) );

	// Content or embedding changes invalidate the index; the dashboard offers a reindex.
	foreach ( array( 'post_types', 'exclude_ids', 'chunk_size', 'embed_enabled', 'embed_provider', 'embed_url', 'embed_model' ) as $k ) {
		if ( isset( $old[ $k ] ) && $old[ $k ] !== $out[ $k ] ) {
			update_option( 'aisa_needs_reindex', 1, false );
			break;
		}
	}
	delete_transient( 'aisa_llms_index' );
	delete_transient( 'aisa_llms_full' );
	return $out;
}

/**
 * Keep the option out of autoload (the Settings API creates it with the default autoload).
 */
function aisa_settings_no_autoload() {
	wp_set_option_autoload( 'aisa_settings', false );
}
add_action( 'add_option_aisa_settings', 'aisa_settings_no_autoload', 20 );

/**
 * Excluded post IDs.
 *
 * @return int[]
 */
function aisa_excluded_ids() {
	return array_values( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) aisa_setting( 'exclude_ids' ) ) ) ) );
}

/**
 * Settings page markup. All tabs sit in one form (tabs only show/hide), so saving never drops a tab.
 */
function aisa_page_settings() {
	$s      = aisa_setting();
	$fields = aisa_fields();
	$active = isset( $_GET['tab'] ) && isset( aisa_tabs()[ sanitize_key( $_GET['tab'] ) ] ) ? sanitize_key( $_GET['tab'] ) : 'ai'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap aisa-admin">
		<h1><?php esc_html_e( 'AI Assistant settings', 'ai-site-assistant' ); ?></h1>
		<?php settings_errors(); ?>
		<nav class="nav-tab-wrapper aisa-tabs">
			<?php foreach ( aisa_tabs() as $tab => $label ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aisa-settings&tab=' . $tab ) ); ?>" class="nav-tab<?php echo $tab === $active ? ' nav-tab-active' : ''; ?>" data-tab="<?php echo esc_attr( $tab ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<form method="post" action="options.php">
			<?php settings_fields( 'aisa_settings' ); ?>
			<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( admin_url( 'admin.php?page=aisa-settings&tab=' . $active ) ); ?>" class="aisa-referer">
			<?php foreach ( aisa_tabs() as $tab => $label ) : ?>
				<table class="form-table aisa-tab-panel" role="presentation" data-panel="<?php echo esc_attr( $tab ); ?>"<?php echo $tab === $active ? '' : ' hidden'; ?>><tbody>
				<?php
				foreach ( $fields as $key => $def ) {
					if ( $def[2] === $tab ) {
						aisa_render_field( $key, $def, $s[ $key ] );
					}
				}
				?>
				</tbody></table>
			<?php endforeach; ?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * One settings row.
 *
 * @param string $key   Field key.
 * @param array  $def   Field definition.
 * @param mixed  $value Current value.
 */
function aisa_render_field( $key, $def, $value ) {
	$id    = 'aisa-' . $key;
	$name  = 'aisa_settings[' . $key . ']';
	$extra = isset( $def[4] ) ? $def[4] : array();
	?>
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $def[0] ); ?></label></th>
		<td>
		<?php
		switch ( $def[1] ) {
			case 'checkbox':
				printf( '<input type="hidden" name="%1$s" value="0"><label><input type="checkbox" id="%2$s" name="%1$s" value="1"%3$s> %4$s</label>', esc_attr( $name ), esc_attr( $id ), checked( $value, 1, false ), esc_html__( 'On', 'ai-site-assistant' ) );
				break;
			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $extra['options'] as $v => $l ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $value, $v, false ), esc_html( $l ) );
				}
				echo '</select>';
				break;
			case 'textarea':
				printf( '<textarea class="large-text" rows="4" id="%s" name="%s">%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
				break;
			case 'password':
				$is_const = 'api_key' === $key && defined( 'AISA_API_KEY' ) && AISA_API_KEY;
				printf(
					'<input class="regular-text" type="password" autocomplete="new-password" id="%s" name="%s" value="" placeholder="%s"%s>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $is_const ? __( 'Set in wp-config.php', 'ai-site-assistant' ) : ( $value ? __( 'Saved. Leave empty to keep it.', 'ai-site-assistant' ) : '' ) ),
					disabled( $is_const, true, false )
				);
				if ( $value && ! $is_const ) {
					printf( ' <label><input type="checkbox" name="aisa_settings[_clear][%s]" value="1"> %s</label>', esc_attr( $key ), esc_html__( 'Remove', 'ai-site-assistant' ) );
				}
				break;
			case 'number':
			case 'float':
				printf( '<input class="small-text" type="number" step="%s" min="0" id="%s" name="%s" value="%s">', 'float' === $def[1] ? '0.05' : '1', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				break;
			case 'post_types':
				foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
					if ( 'attachment' === $pt->name ) {
						continue;
					}
					printf( '<label style="display:block"><input type="checkbox" name="%s[]" value="%s"%s> %s</label>', esc_attr( $name ), esc_attr( $pt->name ), checked( in_array( $pt->name, (array) $value, true ), true, false ), esc_html( $pt->labels->name ) );
				}
				echo '<p class="description">' . esc_html__( 'Snippets (AI Assistant → Snippets) are always included.', 'ai-site-assistant' ) . '</p>';
				break;
			case 'robots':
				echo '<table class="widefat striped aisa-robots"><thead><tr><th>' . esc_html__( 'Crawler', 'ai-site-assistant' ) . '</th><th>' . esc_html__( 'Used for', 'ai-site-assistant' ) . '</th><th>' . esc_html__( 'Rule', 'ai-site-assistant' ) . '</th></tr></thead><tbody>';
				foreach ( aisa_ai_bots() as $bot => $what ) {
					$cur = isset( $value[ $bot ] ) ? $value[ $bot ] : '';
					printf( '<tr><td><code>%s</code></td><td>%s</td><td><select name="%s[%s]">', esc_html( $bot ), esc_html( $what ), esc_attr( $name ), esc_attr( $bot ) );
					foreach ( array( '' => __( 'No rule (follow the default)', 'ai-site-assistant' ), 'allow' => __( 'Allow', 'ai-site-assistant' ), 'block' => __( 'Block', 'ai-site-assistant' ) ) as $v => $l ) {
						printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $cur, $v, false ), esc_html( $l ) );
					}
					echo '</select></td></tr>';
				}
				echo '</tbody></table>';
				break;
			default:
				printf( '<input class="regular-text" type="%s" id="%s" name="%s" value="%s">', 'url' === $def[1] ? 'url' : 'text', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
		}
		if ( ! empty( $extra['desc'] ) ) {
			echo '<p class="description">' . esc_html( $extra['desc'] ) . '</p>';
		}
		?>
		</td>
	</tr>
	<?php
}
