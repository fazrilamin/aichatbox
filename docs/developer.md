# Developer guide

## Filters

| Filter | Arguments | Use |
|---|---|---|
| `aisa_is_indexable` | `bool $ok, WP_Post $post` | Include or exclude posts from the index. |
| `aisa_post_html` | `string $html, WP_Post $post` | Change the rendered HTML before it becomes Markdown. |
| `aisa_post_markdown` | `string $md, WP_Post $post` | Change the Markdown that gets indexed. |
| `aisa_search_results` | `array $results, string $query, array $ctx` | Re-rank, drop or add retrieved chunks. `$ctx['page']` is the visitor's current URL. |
| `aisa_system_prompt` | `string $prompt, string $context` | Change the system prompt. |
| `aisa_client_ip` | `string $ip` | The real visitor IP behind a proxy (Cloudflare's `CF-Connecting-IP` is already handled). |

Example: keep a members-only post type out of the index.

```php
add_filter( 'aisa_is_indexable', function ( $ok, $post ) {
	return 'members_page' === $post->post_type ? false : $ok;
}, 10, 2 );
```

These names and arguments are a public API: they stay stable across minor versions.

## Constants (wp-config.php)

| Constant | Effect |
|---|---|
| `AISA_API_KEY` | The chat API key; overrides the saved setting. |
| `AISA_DISABLE_UPDATES` | `true` = don't check GitHub for new releases. |
| `WP_DEBUG` | Adds the **Mock** chat provider and **Mock** embeddings, which run the whole pipeline without calling an AI. |

## REST API (`/wp-json/aisa/v1/`)

| Route | Who | Body / response |
|---|---|---|
| `POST /chat` | Public | `{ "question": "…", "history": [{"role":"user","content":"…"}…], "session": "…", "page": "<current URL>", "stream": true }`. With `stream`, the reply is Server-Sent Events (`sources`, `delta`, `done`, `error`); otherwise JSON. |
| `POST /feedback` | Public | `{ "id": <conversation id>, "rating": 1 or -1, "session": "…" }` |
| `POST /admin/test` | `manage_options` | Tests the chat and embeddings connections. |
| `GET/POST /admin/reindex` | `manage_options` | Index status; `{ "action": "start" }` starts a full reindex and each POST runs one batch. |

The public routes use no nonce, so they keep working on cached pages. They are protected instead by:

- an Origin/Referer allow-list (this site plus **Other allowed domains**), so requests without either header get 403;
- the hourly per-visitor limit;
- an `hp` honeypot field that must stay empty.

The model marks a hand-off with `[[HANDOFF]]`. The server strips the marker and reports it as a flag, and the widget then shows the contact button.

## Developer CLI

`tools/aisa-cli.php` loads a local WordPress site that has the plugin active:

```
php tools/aisa-cli.php <wordpress dir> stats              # indexed documents and chunk counts
php tools/aisa-cli.php <wordpress dir> reindex            # full reindex
php tools/aisa-cli.php <wordpress dir> md <post_id>       # the Markdown the AI reads for a post
php tools/aisa-cli.php <wordpress dir> chunks <post_id>   # chunk headings and sizes
php tools/aisa-cli.php <wordpress dir> search "question"  # retrieval results, no AI call
php tools/aisa-cli.php <wordpress dir> prompt "question"  # the full system prompt that would be sent
php tools/aisa-cli.php <wordpress dir> set settings.json  # merge settings from a JSON file
php tools/aisa-cli.php <wordpress dir> update             # check GitHub for a newer release
```

Add `--host=example.test:8080` if the site's URL isn't `localhost:8087`.

## How it works

```
save_post ─▶ queue ─▶ (shutdown) render the_content ─▶ HTML → Markdown ─▶ chunks at h1–h3 ─▶ BM25 terms (+ embeddings)
                                                                                                      │
visitor question ─▶ POST /chat ─▶ BM25 (+ cosine, fused by rank) ─▶ numbered excerpts ─▶ AI provider ─▶ answer + sources
```

- **Settings** (`inc/settings.php`): one option, `aisa_settings`.
  - Every field is declared once in `aisa_fields()`; that declaration drives the defaults, the sanitizing and the tabbed form.
  - Read settings with `aisa_setting( 'key' )`.
- **Tables** (`inc/db.php`): `{prefix}aisa_docs` (one row per post: Markdown, summary, content hash), `{prefix}aisa_chunks` (chunk text, BM25 terms, optional float32 embedding), `{prefix}aisa_conversations`. The SQL stays plain, with no FULLTEXT, so it also runs on SQLite.
- **Indexer** (`inc/indexer.php`):
  - Renders each post through `the_content`, so dynamic blocks and shortcodes are included.
  - Converts the result to Markdown with a DOM walker: navigation, forms and scripts are dropped; tables become pipe tables; `<details>` becomes Q&A.
  - Splits at headings and skips posts whose content hash hasn't changed.
  - Full reindexes run in batches, from the dashboard or from WP-Cron.
- **Retrieval** (`inc/retrieval.php`):
  - BM25 in PHP over a cached term index.
  - With embeddings on, reciprocal-rank fusion with cosine similarity.
  - The visitor's current page gets a small boost.
- **Providers** (`inc/providers.php`):
  - Chat uses the OpenAI-compatible `/chat/completions` everywhere: streaming over cURL with SSE parsing, otherwise `wp_remote_post`.
  - A request is retried (at most twice) without a parameter the model rejects (`temperature`, `max_tokens` → `max_completion_tokens`, `stream_options`).
  - Embeddings use Ollama `/api/embed` or OpenAI-compatible `/embeddings`.
- **Chat** (`inc/chat.php`): builds the system prompt from numbered excerpts (one number per source page), then logs the conversation and applies the rate limit.
- **Widget** (`inc/frontend.php`, `assets/js/widget.js`, `assets/css/widget.css`):
  - Vanilla ES5 with no build step, configured by the JSON in `#aisa-root[data-config]`.
  - Answers are HTML-escaped first; then a small Markdown subset is rendered.
- **AI-friendly output** (`inc/ai-friendly.php`): `/llms.txt` and `/llms-full.txt` (a rewrite rule plus a raw-path fallback, cached), `?format=markdown`, and the `robots_txt` filter.
- **Updater** (`inc/updater.php`):
  - The `Update URI` header makes WordPress ask the `update_plugins_github.com` filter, which reads the latest GitHub release.
  - Only a release asset named `ai-site-assistant-<version>.zip` counts.
  - A plugin folder that is a symlink or junction (a dev checkout) is never offered updates.

## Local development

There is no build step and there are no dependencies. You need PHP (7.4+) on the command line and a local WordPress site.

1. Link the plugin into the site instead of copying it:
   - Windows: `cmd /c mklink /J "<site>\wp-content\plugins\ai-site-assistant" "<repo>\ai-site-assistant"`
   - macOS/Linux: `ln -s "<repo>/ai-site-assistant" "<site>/wp-content/plugins/ai-site-assistant"`
2. Turn on `WP_DEBUG` and `WP_DEBUG_LOG` in the site's `wp-config.php`. Then set **Provider** to Mock to test without an API key.
3. Build a release zip: `pwsh tools/build-zip.ps1` (Windows PowerShell: `& tools\build-zip.ps1`). It checks that the two version numbers match, lints every PHP file, refreshes the translation template and writes `dist/ai-site-assistant-<version>.zip`.

Test the chat endpoint with curl (the Origin header is required):

```
curl -H "Origin: http://localhost:8087" -H "Content-Type: application/json" \
     --data '{"question":"What do you do?","stream":true}' \
     http://localhost:8087/wp-json/aisa/v1/chat
```

## Translations

- All strings use the `ai-site-assistant` text domain. Translations load from the plugin's `languages/` folder and from `wp-content/languages/plugins/`.
- `php tools/make-pot.php` regenerates `languages/ai-site-assistant.pot`; the build does this too.
- To translate, open the `.pot` in [Poedit](https://poedit.net/) or Loco Translate and save `ai-site-assistant-<locale>.po`/`.mo` (e.g. `ai-site-assistant-de_DE.mo`) in either folder.
- Settings labels and default texts are translated when they are read, which happens on `init` or later. Don't read settings earlier than that.
- The prompt sent to the AI stays in English on purpose. The model answers in the visitor's language.
