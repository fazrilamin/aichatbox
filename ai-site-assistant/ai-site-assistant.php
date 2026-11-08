<?php
/**
 * Plugin Name: AI Assistant & Chatbox
 * Plugin URI: https://github.com/fazrilamin/aichatbox
 * Description: AI search and chat for your website, answered from your own pages with OpenAI, Gemini, Claude, DeepSeek, Groq, OpenRouter, Ollama or any OpenAI-compatible API. Also makes the site AI-friendly: llms.txt, llms-full.txt, Markdown versions of pages and AI crawler rules in robots.txt. Includes snippets, starter questions and a conversation log.
 * Version: 1.1.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Fazril Amin
 * Author URI: https://faztech.my
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-site-assistant
 * Domain Path: /languages
 * Update URI: https://github.com/fazrilamin/aichatbox
 *
 * @package ai-site-assistant
 */

defined( 'ABSPATH' ) || exit;

define( 'AISA_VERSION', '1.1.0' );
define( 'AISA_FILE', __FILE__ );
define( 'AISA_DIR', plugin_dir_path( __FILE__ ) );
define( 'AISA_URL', plugin_dir_url( __FILE__ ) );
define( 'AISA_GITHUB_REPO', 'fazrilamin/aichatbox' ); // owner/repo that publishes releases.

require_once AISA_DIR . 'inc/settings.php';
require_once AISA_DIR . 'inc/db.php';
require_once AISA_DIR . 'inc/providers.php';
require_once AISA_DIR . 'inc/snippets.php';
require_once AISA_DIR . 'inc/indexer.php';
require_once AISA_DIR . 'inc/retrieval.php';
require_once AISA_DIR . 'inc/chat.php';
require_once AISA_DIR . 'inc/frontend.php';
require_once AISA_DIR . 'inc/ai-friendly.php';
require_once AISA_DIR . 'inc/admin.php';
require_once AISA_DIR . 'inc/updater.php';

/**
 * Load translations from /languages (WordPress only auto-loads them for WordPress.org plugins).
 * Runs first on init: settings labels and default texts are translated, and nothing reads them earlier.
 */
function aisa_load_textdomain() {
	load_plugin_textdomain( 'ai-site-assistant', false, dirname( plugin_basename( AISA_FILE ) ) . '/languages' );
}
add_action( 'init', 'aisa_load_textdomain', 0 );

register_activation_hook(
	__FILE__,
	function () {
		aisa_install_tables();
		aisa_register_snippets();
		aisa_add_rewrites();
		flush_rewrite_rules();
		if ( ! wp_next_scheduled( 'aisa_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'aisa_daily' );
		}
	}
);
register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'aisa_daily' );
		wp_clear_scheduled_hook( 'aisa_index_batch' );
		flush_rewrite_rules();
	}
);
