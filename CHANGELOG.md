# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/).

## [1.1.0] - 2026-11-08

### Added
- Provider presets for OpenAI, Google Gemini, Anthropic Claude, Groq and OpenRouter, next to DeepSeek, Ollama and any OpenAI-compatible API.
- Semantic search with any OpenAI-compatible `/embeddings` API (OpenAI, Gemini, OpenRouter and others), not only Ollama. New setting: **Embeddings API**.
- Updates from GitHub Releases: new versions appear under Dashboard → Updates. `AISA_DISABLE_UPDATES` turns this off.
- Translation support: every setting label, admin message and visitor-facing text can be translated, and `languages/ai-site-assistant.pot` is included.
- A Mock embeddings option (with `WP_DEBUG`) for testing semantic search without an AI service.
- A dashboard warning when semantic search is on but has no URL or model, and when the chosen provider has no API key.
- `readme.txt` in the WordPress.org format.

### Changed
- Renamed to **AI Assistant & Chatbox**. The folder (`ai-site-assistant`), settings and data are unchanged, so existing installs update in place.
- Licensed under GPL-2.0-or-later.
- New installs default to the OpenAI preset.
- When a model rejects `temperature`, `max_tokens` or `stream_options` (for example newer Claude and OpenAI reasoning models), the request is retried without it, or with `max_completion_tokens`.
- The widget's styles no longer refer to any particular theme's CSS variables. Theming is through the documented `--aisa-*` variables.

### Fixed
- Widget font sizes and weights were ignored on themes that don't define a body-font variable. They now apply everywhere.

## 1.0.0 - 2026-10-21

### Added
- First release (private, not published on GitHub): AI chat and search answered from the site's own content, the floating widget and search-bar panel, `[aisa_search]`, snippets, the conversation log with CSV export, `/llms.txt`, `/llms-full.txt`, Markdown page versions and AI crawler rules in robots.txt.

[1.1.0]: https://github.com/fazrilamin/aichatbox/releases/tag/v1.1.0
