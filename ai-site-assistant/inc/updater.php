<?php
/**
 * Updates from GitHub Releases. The plugin header's Update URI points at github.com, so WordPress asks
 * the update_plugins_github.com filter (not WordPress.org) whether a newer version exists. A release
 * counts only when it has an ai-site-assistant-<version>.zip asset: GitHub's own source zip unpacks
 * to a differently named folder and would install a second copy instead of updating this one.
 *
 * @package ai-site-assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether this install should be offered updates.
 *
 * Off when AISA_DISABLE_UPDATES is true, or when the plugin folder is a symlink or junction (a
 * development checkout): an update deletes the folder, which would delete the linked source.
 *
 * @return bool
 */
function aisa_updates_enabled() {
	if ( ( defined( 'AISA_DISABLE_UPDATES' ) && AISA_DISABLE_UPDATES ) || '' === (string) AISA_GITHUB_REPO ) {
		return false;
	}
	// AISA_DIR is already resolved (PHP resolves links in __FILE__), so look at the folder WordPress
	// sees under wp-content/plugins and check whether it resolves somewhere else.
	$slug      = dirname( plugin_basename( AISA_FILE ) );
	$installed = untrailingslashit( WP_PLUGIN_DIR ) . '/' . $slug;
	if ( '.' === $slug || is_link( $installed ) ) {
		return false;
	}
	$real = realpath( $installed );
	$base = realpath( WP_PLUGIN_DIR );
	return $real && $base && strtolower( wp_normalize_path( $real ) ) === strtolower( wp_normalize_path( $base . '/' . $slug ) );
}

/**
 * The latest published GitHub release, cached for 12 hours (1 hour after a failed request).
 *
 * @param bool $force Skip the cache.
 * @return array{version:string,package:string,url:string,notes:string,published:string}|null
 */
function aisa_github_release( $force = false ) {
	$cached = get_transient( 'aisa_update_info' );
	if ( ! $force && is_array( $cached ) && array_key_exists( 'release', $cached ) ) {
		return $cached['release'];
	}
	$res     = wp_remote_get(
		'https://api.github.com/repos/' . AISA_GITHUB_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/vnd.github+json' ),
		)
	);
	$code    = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	$release = null;
	if ( 200 === $code ) {
		$json    = json_decode( wp_remote_retrieve_body( $res ), true );
		$version = is_array( $json ) && isset( $json['tag_name'] ) ? ltrim( (string) $json['tag_name'], 'vV' ) : '';
		$package = '';
		foreach ( isset( $json['assets'] ) ? (array) $json['assets'] : array() as $asset ) {
			$name = isset( $asset['name'] ) ? (string) $asset['name'] : '';
			$link = isset( $asset['browser_download_url'] ) ? (string) $asset['browser_download_url'] : '';
			if ( preg_match( '/^ai-site-assistant-[0-9][0-9A-Za-z.\-]*\.zip$/', $name ) && 0 === strpos( $link, 'https://github.com/' ) ) {
				$package = $link;
				break;
			}
		}
		if ( preg_match( '/^[0-9]+(\.[0-9]+)*([.\-][0-9A-Za-z.\-]+)?$/', $version ) && '' !== $package ) {
			$release = array(
				'version'   => $version,
				'package'   => $package,
				'url'       => isset( $json['html_url'] ) ? esc_url_raw( $json['html_url'] ) : 'https://github.com/' . AISA_GITHUB_REPO . '/releases',
				'notes'     => isset( $json['body'] ) ? (string) $json['body'] : '',
				'published' => isset( $json['published_at'] ) ? (string) $json['published_at'] : '',
			);
		}
	}
	// 200 (with or without a usable release) and 404 (no release yet) are answers; anything else is a failure.
	$ttl = ( 200 === $code || 404 === $code ) ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS;
	set_transient( 'aisa_update_info', array( 'release' => $release ), $ttl );
	return $release;
}

/**
 * Answer WordPress's update check for this plugin.
 *
 * @param array|false $update      Update data from earlier filters.
 * @param array       $plugin_data Plugin header data.
 * @param string      $plugin_file Plugin basename.
 * @return array|false
 */
function aisa_update_plugins_github( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( AISA_FILE ) !== $plugin_file || ! aisa_updates_enabled() ) {
		return $update;
	}
	// "Check again" on Dashboard → Updates also skips this plugin's cache.
	$force   = is_admin() && isset( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$release = aisa_github_release( $force );
	if ( ! $release ) {
		return $update;
	}
	return array(
		'slug'         => 'ai-site-assistant',
		'version'      => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'requires'     => '6.6',
		'requires_php' => '7.4',
	);
}
add_filter( 'update_plugins_github.com', 'aisa_update_plugins_github', 10, 3 );

/**
 * The "View details" box on the Plugins and Updates screens.
 *
 * @param false|object|array $result Result from earlier filters.
 * @param string             $action plugins_api action.
 * @param object             $args   Request arguments.
 * @return false|object|array
 */
function aisa_plugins_api( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! isset( $args->slug ) || 'ai-site-assistant' !== $args->slug ) {
		return $result;
	}
	$release = aisa_updates_enabled() ? aisa_github_release() : null;
	$repo    = 'https://github.com/' . AISA_GITHUB_REPO;
	return (object) array(
		'name'          => 'AI Assistant & Chatbox',
		'slug'          => 'ai-site-assistant',
		'version'       => $release ? $release['version'] : AISA_VERSION,
		'author'        => '<a href="https://faztech.my">Fazril Amin</a>',
		'homepage'      => $repo,
		'requires'      => '6.6',
		'requires_php'  => '7.4',
		'last_updated'  => $release ? $release['published'] : '',
		'download_link' => $release ? $release['package'] : '',
		'sections'      => array(
			'description' => '<p>' . esc_html__( 'AI search and chat for your website, answered from your own pages. Also makes the site AI-friendly with llms.txt, Markdown versions of pages and AI crawler rules in robots.txt.', 'ai-site-assistant' ) . '</p><p><a href="' . esc_url( $repo ) . '">' . esc_html__( 'Documentation on GitHub', 'ai-site-assistant' ) . '</a></p>',
			'changelog'   => $release && '' !== trim( $release['notes'] )
				? wpautop( esc_html( $release['notes'] ) )
				: '<p><a href="' . esc_url( $repo . '/releases' ) . '">' . esc_html__( 'Release notes on GitHub', 'ai-site-assistant' ) . '</a></p>',
		),
	);
}
add_filter( 'plugins_api', 'aisa_plugins_api', 10, 3 );

/**
 * Forget the cached release after any plugin update, so the next check sees the new state.
 */
function aisa_clear_update_cache() {
	delete_transient( 'aisa_update_info' );
}
add_action( 'upgrader_process_complete', 'aisa_clear_update_cache' );
