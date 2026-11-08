# Customization

## Colours and fonts

The widget picks up the page's light or dark background and the block theme's primary colour (`--wp--preset--color--primary`) by itself. For a single accent colour or gradient, use **Settings → Widget → Accent colour**.

For more control, override the widget's CSS custom properties in your theme's stylesheet or in **Appearance → Customize → Additional CSS**:

| Variable | Used for |
|---|---|
| `--aisa-accent` | Floating button, send button, highlights (colour or gradient) |
| `--aisa-font`, `--aisa-font-display` | Body text and the panel title (default: the theme's font) |
| `--aisa-font-mono` | Inline code |
| `--aisa-bg`, `--aisa-surface` | Panel background, answer and input surfaces |
| `--aisa-text`, `--aisa-muted` | Text colours |
| `--aisa-line` | Borders |
| `--aisa-user` | The visitor's message bubbles |
| `--aisa-link` | Links and source citations |
| `--aisa-shadow`, `--aisa-backdrop` | Panel shadow and the dimmed page behind the dialog |

The colour variables are set per scheme, so override them with the same selectors:

```css
/* Accent and fonts: everywhere the widget appears. */
#aisa-widget,
.aisa-launcher,
.aisa-inline {
	--aisa-accent: linear-gradient(135deg, #6366f1, #ec4899);
	--aisa-font: "Inter", sans-serif;
}

/* Dark scheme colours. */
#aisa-widget.aisa--dark {
	--aisa-bg: #0b0d17;
	--aisa-surface: #151827;
	--aisa-user: #23263a;
	--aisa-link: #a5b4fc;
}

/* Light scheme colours. */
#aisa-widget.aisa--light {
	--aisa-surface: #f3f4f8;
}
```

Every widget rule is scoped under `#aisa-widget`, so your theme's button, list and link styles don't leak in. When the theme's own CSS loads after the plugin's, the same selectors win. Otherwise, add `body` in front of them.

## Inline search bar: `[aisa_search]`

```
[aisa_search placeholder="Ask about our services…" button="Ask"]
```

This is a normal search form, so without JavaScript it still works as the site search. With JavaScript, questions open the AI answer panel. It works even when **Placement** is set to Off.

## Attaching to your theme's search bars

With **Placement** set to "Attach to existing search bars" or "Both", the widget hooks into every input that matches **Search bar selector**. The default is:

```
form[role=search] input[type=search], input.wp-block-search__input
```

If your theme's search input doesn't match, add its selector, separated by a comma. On screens 640 px and wider the answer opens in a panel below the input; on smaller screens it opens the chat dialog. The panel also links to the normal search results for the same words.

## Opening the chat from your theme

Any element with `data-aisa-open` opens the chat when clicked. Give it a value to ask that question right away:

```html
<button type="button" data-aisa-open>Ask our AI</button>
<a href="#" data-aisa-open="What are your opening hours?">Opening hours?</a>
```

From JavaScript:

```js
window.aisaOpen();                          // open the chat
window.aisaOpen( 'Do you ship to Canada?' ); // open it and ask
```

The widget script loads on every page unless **Placement** is Off. With Placement Off, it loads only on pages with the `[aisa_search]` shortcode.

## Snippets

**AI Assistant → Snippets** holds answers the website doesn't give: policies, internal facts, frequent questions. The title is the question and the content is the answer. Snippets are indexed like pages, can be cited as sources ("From our FAQ"), and appear in `/llms-full.txt`. They have no page of their own on the site.

## Changing what the AI reads or says

See the filters in [developer.md](developer.md#filters): exclude posts, clean up the HTML or Markdown before indexing, re-rank search results, or change the system prompt.
