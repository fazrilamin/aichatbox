=== AI Assistant & Chatbox ===
Contributors: fazrilamin
Tags: ai, chatbot, search, llms.txt, openai
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI search and chat that answers visitors from your own pages, plus llms.txt, Markdown pages and AI crawler rules.

== Description ==

AI Assistant & Chatbox adds an "Ask AI" chat and AI-powered search to any WordPress site. Answers come only from your own published content and cite the pages they used. When the AI can't answer, visitors get your contact button.

* Works with OpenAI, Google Gemini, Anthropic Claude, DeepSeek, Groq, OpenRouter, Ollama (self-hosted) or any OpenAI-compatible API.
* Indexes pages, posts and any public post type the way visitors see them, including blocks and shortcodes.
* Keyword search (BM25), with optional semantic search through embeddings (Ollama or any OpenAI-compatible /embeddings API).
* A floating chat button, a results panel under your theme's search bars, and a [aisa_search] shortcode.
* Snippets: private answers to questions your site doesn't cover.
* Conversation log with ratings, hand-offs and CSV export; automatic deletion after a retention period.
* AI-friendly output: /llms.txt, /llms-full.txt, a Markdown version of each page and per-crawler rules in robots.txt.
* Privacy: IP addresses are stored only as salted hashes, plus an hourly per-visitor limit, a domain allow-list and a honeypot.
* Translation-ready.

Documentation: https://github.com/fazrilamin/aichatbox

= Third-party services =

This plugin sends the visitor's question, the recent conversation and matching excerpts of your site's content to the AI provider you configure, to generate answers (and, with semantic search on, to create embeddings). Nothing is sent until you choose a provider and enter its details. Each provider has its own terms and privacy policy:

* OpenAI: https://openai.com/policies/
* Google Gemini: https://ai.google.dev/gemini-api/terms
* Anthropic: https://www.anthropic.com/legal
* DeepSeek: https://www.deepseek.com/
* Groq: https://groq.com/terms-of-use/
* OpenRouter: https://openrouter.ai/terms
* Ollama runs on a server you control.

The plugin checks https://api.github.com for new releases of itself (at most twice a day; like every WordPress request, it carries a user agent with the WordPress version and site address). Define AISA_DISABLE_UPDATES as true in wp-config.php to turn this off.

== Installation ==

1. Download ai-site-assistant-<version>.zip from https://github.com/fazrilamin/aichatbox/releases.
2. Go to Plugins → Add New → Upload Plugin, choose the zip, then activate.
3. Go to AI Assistant → Settings → AI model, choose a provider and enter its API key.
4. On AI Assistant → Dashboard, click Test connection, then Reindex everything.
5. On Settings → Widget, choose where the widget appears and set the contact button link.

== Frequently Asked Questions ==

= Does it work with any theme? =

Yes. The widget is self-contained, follows the page's light or dark background and uses the theme's primary colour when the theme provides one.

= What does it cost? =

The plugin is free. You pay your AI provider for usage, or nothing if you run Ollama yourself.

= Will the AI make things up? =

It is instructed to answer only from your content and to say so when it can't. Answers cite their sources, and you can add rules under Extra instructions.

== Changelog ==

= 1.1.0 =
* Renamed to AI Assistant & Chatbox and released under GPL-2.0-or-later.
* Added provider presets for OpenAI, Google Gemini, Anthropic Claude, Groq and OpenRouter.
* Semantic search now also works with any OpenAI-compatible /embeddings API, not only Ollama.
* Requests are retried without a parameter the model rejects (temperature, max_tokens).
* Updates are now delivered from GitHub Releases.
* Translation-ready: all settings and messages can be translated; a .pot file is included.
* The widget no longer depends on any particular theme's CSS variables, and its font sizes now apply on every theme.

= 1.0.0 =
* First release.
