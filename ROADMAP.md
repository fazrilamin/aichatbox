# Roadmap

Ideas for future versions. Nothing here is promised. To discuss one, add a 👍 or a comment on its issue, or open a [feature request](https://github.com/fazrilamin/aichatbox/issues/new?template=feature_request.yml).

## Answers and knowledge

- **More content sources:** PDFs and other media-library documents, external URLs or sitemaps, and custom fields.
- **Product awareness:** price, stock and variations for shop post types, exposed through filters rather than ties to one plugin.
- **Multilingual sites:** index each language separately and answer from the visitor's language version.
- **Scaling the index:** an optional external vector store for sites with tens of thousands of pages.
- **Better follow-up questions:** rewrite "and how much is it?" into a standalone query before searching.

## Conversations and leads

- **Lead capture on hand-off:** an optional name/email form when the AI hands over, sent by email or stored as a lead (opt-in, with privacy notes).
- **Analytics:** charts of questions per day, top topics, unanswered questions and hand-off rate.
- **Human takeover:** forward a conversation to email or a chat tool.

## Site owners

- **A block** for the inline search bar, and a widget block for the chat button.
- **Settings import/export** for moving a setup between sites.
- **WP-CLI commands** (`wp aisa reindex`, `wp aisa search`).
- **CAPTCHA option** (Cloudflare Turnstile, hCaptcha) for sites that get abuse beyond the rate limit.
- **Usage limits:** a monthly token or cost cap per site.

## Project

- Automated tests (PHPUnit for indexing and retrieval, Playwright for the widget).
- Coding-standards check (PHPCS with WordPress rules) in CI.
- Submission to the WordPress.org plugin directory.
- Translations into more languages.
