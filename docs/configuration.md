# Configuration

Everything is under **AI Assistant → Settings**. The tabs only show and hide parts of one form, so **Save** keeps every tab's values. Key fields show "Saved. Leave empty to keep it." Leave them empty to keep the saved key, or tick **Remove** to clear it.

## AI model

| Setting | Notes |
|---|---|
| Provider | A preset that fills in the base URL and default model. |
| API base URL | Empty = the preset's URL. Needed for Ollama on your own server and for "Other OpenAI-compatible API" (an address ending in `/v1`). |
| API key / bearer token | Or `define( 'AISA_API_KEY', '…' );` in `wp-config.php`, which wins over the saved value. |
| Chat model | Empty = the preset's default (below). Groq and "Other" need one. |
| Temperature | 0.2–0.4 suits a website assistant. If a model only accepts its default (some newer Claude and OpenAI reasoning models), the plugin retries without it. |
| Max answer length | In tokens. If a model wants `max_completion_tokens` instead of `max_tokens`, the plugin retries with that. |
| Stream answers | Words appear as they are written. Turn off if your host or CDN buffers responses. |
| Assistant name, Tone | Used in the system prompt. "Match the website" copies the tone and spelling of your content. |
| Extra instructions | House rules added to every prompt. |

### Preset defaults

| Provider | Base URL | Default model |
|---|---|---|
| OpenAI | `https://api.openai.com/v1` | `gpt-6-luna` |
| Google Gemini | `https://generativelanguage.googleapis.com/v1beta/openai` | `gemini-3.8-flash` |
| Anthropic Claude | `https://api.anthropic.com/v1` | `claude-haiku-5-5` |
| DeepSeek | `https://api.deepseek.com` | `deepseek-chat` |
| Groq | `https://api.groq.com/openai/v1` | none: enter one |
| OpenRouter | `https://openrouter.ai/api/v1` | `openrouter/auto` |
| Ollama | `http://localhost:11434` | `llama3.1:8b` |

Providers rename and retire models often. If **Test connection** reports HTTP 404, enter a current model name from your provider's model list in **Chat model**.

### Semantic search (embeddings)

Keyword search works well when visitors use the same words as your pages. Semantic search also matches meaning ("opening hours" ↔ "when are you open"). It needs an embeddings service:

| Setting | Notes |
|---|---|
| Semantic search | On/off. Reindex after changing any embeddings setting (the dashboard reminds you). |
| Embeddings API | **Ollama** (`/api/embed`) or **OpenAI-compatible** (`/embeddings`: OpenAI, Gemini, OpenRouter, Mistral, Ollama's `/v1` and others). |
| Embeddings base URL | Empty = the chat provider's URL, when it offers this API. DeepSeek, Claude and Groq have none, so enter another service here, e.g. `https://api.openai.com/v1`. |
| Embeddings API key | Empty = the chat key, when the chat provider's URL is used. |
| Embeddings model | Empty = `nomic-embed-text` (Ollama), `text-embedding-3-small` (OpenAI), `gemini-embedding-001` (Gemini), `openai/text-embedding-3-small` (OpenRouter). |

Examples:

- **Chat with Claude, embeddings from OpenAI:** Embeddings API = OpenAI-compatible, base URL `https://api.openai.com/v1`, an OpenAI key, model `text-embedding-3-small`.
- **Everything on Ollama:** Provider = Ollama, Embeddings API = Ollama, leave the embeddings URL and key empty, and run `ollama pull nomic-embed-text` on the server.

The dashboard shows how many chunks have embeddings, and **Test connection** checks the embeddings service too.

## Knowledge

| Setting | Notes |
|---|---|
| Content to index | Public post types. Snippets are always included. |
| Exclude post IDs | Comma-separated. Pages can also be excluded from **AI Assistant → Knowledge**. |
| Chunk size | Characters per chunk (600–8000). Content is split at headings. |
| Website content sent per question | Characters of excerpts per question (2000–60000). More gives fuller answers but costs more. |
| Reindex everything daily | Picks up content that blocks pull in from elsewhere. |

**AI Assistant → Knowledge** lists what is indexed. There you can test a search, see "What the AI sees" for any page, and exclude, include or reindex single pages.

## Widget

| Setting | Notes |
|---|---|
| Placement | Floating button, attach to search bars, both, or off (shortcode only). |
| Search bar selector | CSS selector of the inputs to attach to. The default covers standard WordPress search forms and the Search block. |
| Button position, Margin | Bottom right or left, distance from the edge. |
| Colour scheme | Automatic follows the page background; or force light or dark. |
| Accent colour | Any CSS colour, gradient or `var(--token)`. Empty = the theme's primary colour. |
| Texts | Button, title, placeholder, welcome, starter questions (one per line), fallback, disclaimer, contact button text and link. |

On screens 640 px and wider, the attached mode opens an answer panel under the search bar. On phones it opens the chat dialog.

More styling options: [customization.md](customization.md).

## AI-friendly

| Setting | Notes |
|---|---|
| Serve /llms.txt and /llms-full.txt | `/llms.txt` is an outline of the site in the [llmstxt.org](https://llmstxt.org) format; `/llms-full.txt` holds all indexed content as Markdown. A physical `llms.txt` file in the site root takes precedence. |
| llms.txt introduction | Empty = the site tagline. |
| Markdown version of each page | `?format=markdown` on any indexed page, linked from the page head. |
| AI crawlers in robots.txt | Allow, Block or no rule for each AI crawler. Rules are added to WordPress's virtual robots.txt. A physical `robots.txt` file overrides it (the dashboard warns you), and nothing is added while **Settings → Reading → Discourage search engines** is on. |

## Privacy & limits

| Setting | Notes |
|---|---|
| Log conversations | Questions, answers, sources, page, model, timing, rating and a salted IP hash. |
| Keep conversations for | Days; older logs are deleted daily. 0 = keep forever. |
| Questions per visitor per hour | Per IP hash. |
| Other allowed domains | The chat endpoint refuses requests from other sites; add staging or alternate domains here. |
| Delete all data when the plugin is deleted | Off by default. |

What is sent to the AI provider for each question: the question, the last six messages of the conversation, and the matching excerpts of your content. Suggested privacy-policy text is added to **Settings → Privacy → Policy Guide**.

The plugin also asks `api.github.com` about new releases at most twice a day. Add `define( 'AISA_DISABLE_UPDATES', true );` to `wp-config.php` to turn that off.

## Conversations

**AI Assistant → Conversations** lists logged questions with filters, the full answer, sources and ratings, and a CSV export. The dashboard shows the last 7 days and the questions that were handed off or rated down, each with an **Add snippet** link.
