# Contributing

Thanks for helping. Bug reports, ideas, translations and pull requests are all welcome.

## Reporting bugs and requesting features

- **Bugs:** open an issue with the bug report template. Include your WordPress, PHP and plugin versions, your AI provider, and any errors from the dashboard or `wp-content/debug.log`. **Never paste API keys.**
- **Features:** check [ROADMAP.md](ROADMAP.md) and existing issues first, then open a feature request.
- **Security issues:** don't open a public issue. See [SECURITY.md](SECURITY.md).

## Development setup

See [docs/developer.md](docs/developer.md#local-development). In short: link `ai-site-assistant/` into a local WordPress site, turn on `WP_DEBUG`, and use the Mock provider to test without an API key.

## Coding guidelines

- **PHP:** compatible with PHP 7.4, [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) style, procedural.
  - Every function, option, table, hook and REST route uses the `aisa_` prefix or the `aisa/v1` namespace.
  - Each file starts with an `ABSPATH` guard and a docblock.
  - Escape output and sanitize input. Use `$wpdb->prepare()` for SQL with values.
  - Keep SQL plain (no FULLTEXT or engine-specific features) so it also runs on SQLite.
- **JavaScript:** vanilla ES5 with no build step and no dependencies. Data reaches scripts through `data-*` attributes (no `wp_localize_script`).
- **CSS:** scope widget rules under `#aisa-widget` and use the `--aisa-*` custom properties.
- **Generic by design:** no code tied to a particular theme, plugin, page builder or company. Site-specific behaviour belongs in settings or filters.
- **Translations:** wrap every user-visible string in `__()`, `esc_html__()` and similar, with the `ai-site-assistant` text domain, and add a `/* translators: */` comment for placeholders. Don't call translation functions before `init`. Run `php tools/make-pot.php` after changing strings.
- **Settings:** add new settings to `aisa_fields()` in `inc/settings.php`; that one declaration gives you the default, the sanitizing and the form row. If the setting changes what is indexed, add it to the reindex list in `aisa_sanitize_settings()`.
- **New options, transients or tables:** add them to `uninstall.php` too.
- **Schema changes:** bump `AISA_DB_VERSION`.
- **Public filters** (`aisa_is_indexable`, `aisa_post_html`, `aisa_post_markdown`, `aisa_search_results`, `aisa_system_prompt`, `aisa_client_ip`): keep their names and arguments stable.

## Pull requests

1. Fork the repository and create a branch from `main`.
2. Keep each pull request to one change, and describe what it does and how you tested it.
3. Make sure `php -l` passes on every PHP file (CI checks PHP 7.4 and 8.3).
4. If you change behaviour, update the docs (`README.md`, `docs/`) and add a line under an "Unreleased" heading in `CHANGELOG.md`.
5. Test on a real WordPress site:
   - **Indexing:** reindex and check **Knowledge → What the AI sees** for a few pages.
   - **Chat:** a few questions, with and without streaming.
   - **Widget:** desktop and phone widths, light and dark themes.
   - **Admin:** every admin screen renders without PHP notices.

By contributing, you agree that your contribution is licensed under GPL-2.0-or-later.
