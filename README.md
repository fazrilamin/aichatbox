# AI Assistant & Chatbox

**AI search and chat for any WordPress site, answered from your own pages.**

[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)
[![WordPress 6.6+](https://img.shields.io/badge/WordPress-6.6%2B-21759b.svg)](https://wordpress.org/download/)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://www.php.net/)
[![Latest release](https://img.shields.io/github/v/release/fazrilamin/aichatbox)](https://github.com/fazrilamin/aichatbox/releases/latest)

Visitors ask a question in plain language. The plugin finds the most relevant parts of your site, and the AI model you choose writes an answer from that content only, with links to its sources. When the AI can't answer, the visitor gets your contact button.

It also makes the site **AI-friendly**: `/llms.txt`, `/llms-full.txt`, a Markdown version of every page, and per-crawler rules for GPTBot, ClaudeBot, PerplexityBot and others in `robots.txt`.

Works with any theme. No other plugins, no external service of ours, no build step.

## Features

- **Your choice of AI:** OpenAI, Google Gemini, Anthropic Claude, DeepSeek, Groq, OpenRouter, a self-hosted Ollama server, or any OpenAI-compatible API.
- **Answers from your content:**
  - Pages, posts and any public post type are indexed the way visitors see them, including blocks and shortcodes.
  - The model sees numbered excerpts and cites them as [1], [2].
- **Search that understands questions:** keyword ranking (BM25) built in. Semantic search through embeddings (Ollama or any OpenAI-compatible `/embeddings` API) can be added on top.
- **Fits into the site:**
  - A floating "Ask AI" button.
  - An answer panel under your theme's existing search bars.
  - An `[aisa_search]` shortcode.
  - Light and dark mode follow the page automatically.
- **Snippets:** answer questions your website doesn't cover (policies, FAQs) without publishing a page.
- **Conversation log:**
  - Ratings, sources and hand-offs, CSV export, automatic deletion after a retention period.
  - A list of the questions the AI couldn't answer well.
- **Privacy and abuse protection:** IP addresses are stored only as salted hashes, plus an hourly per-visitor limit, a domain allow-list and a honeypot. Suggested privacy-policy text is included.
- **Updates from GitHub:** new releases show up under Dashboard → Updates like any other plugin.
- **Translation-ready:** a `.pot` file is included.

## Requirements

- WordPress 6.6 or newer, PHP 7.4 or newer (tested on WordPress 7.1 and PHP 8.2).
- PHP extensions: cURL (for streamed answers; it falls back to plain responses without it) and DOM (for clean Markdown).
- An account with an AI provider, or your own Ollama server.

## Quick start

1. Download `ai-site-assistant-<version>.zip` from the [latest release](https://github.com/fazrilamin/aichatbox/releases/latest). Use the release asset; the green "Code → Download ZIP" button gives the source, which unpacks to the wrong folder name.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, upload the zip and activate it.
3. Go to **AI Assistant → Settings → AI model**, pick a provider and paste its API key.
4. On **AI Assistant → Dashboard**, click **Test connection**, then **Reindex everything**.
5. On **Settings → Widget**, choose where the widget appears, add 3–4 starter questions and set the contact button link.

Full guide: [docs/installation.md](docs/installation.md).

## Providers at a glance

| Provider | What to enter | Embeddings (semantic search) |
|---|---|---|
| OpenAI | API key | Yes, same key |
| Google Gemini | API key from Google AI Studio | Yes, same key |
| Anthropic Claude | API key | No: use another service for embeddings |
| DeepSeek | API key | No: use another service for embeddings |
| Groq | API key and a model name | No: use another service for embeddings |
| OpenRouter | API key | Yes, same key |
| Ollama | Your server's HTTPS address (+ the token your proxy checks) | Yes, Ollama `/api/embed` |
| Other OpenAI-compatible | Base URL ending in `/v1`, key, model | If the service offers `/embeddings` |

Each preset has a default model you can override. See [docs/configuration.md](docs/configuration.md).

## Documentation

- [Installation and first setup](docs/installation.md)
- [Configuration: every setting explained](docs/configuration.md)
- [Customization: styling, shortcode, opening the chat from your theme](docs/customization.md)
- [Developer guide: hooks, REST API, CLI, architecture](docs/developer.md)
- [Releasing a new version (maintainers)](docs/releasing.md)
- [Changelog](CHANGELOG.md) · [Roadmap](ROADMAP.md)

## FAQ

**Does it work on any WordPress site?**
Yes. Nothing is tied to a particular theme, page builder or company. Site-specific rules ("never quote prices", "we only ship within Europe") go in the **Extra instructions** setting.

**What does it cost?**
The plugin is free. You pay your AI provider for what you use, usually a fraction of a cent per question with small models. Ollama on your own server costs nothing per question.

**Will the AI make things up?**
It is told to answer only from the excerpts it receives, and to give your fallback message and contact button when they don't cover the question. It can still be wrong, so answers link to their sources and the widget shows a disclaimer.

**What data leaves my site?**
For each question: the question, the recent conversation and the matching excerpts of your public content, sent to the AI provider you chose. The plugin also checks GitHub for new releases. Nothing goes anywhere else. See [Privacy](docs/configuration.md#privacy--limits).

**Does it slow down my pages?**
The widget is one small deferred script and stylesheet with no dependencies. Indexing runs after the response is sent, or in the background.

## Contributing

Bug reports, ideas and pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md). Please report security issues privately as described in [SECURITY.md](SECURITY.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

Made by [Fazril Amin](https://faztech.my).
