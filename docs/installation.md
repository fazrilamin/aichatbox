# Installation and first setup

## 1. Install

1. Download `ai-site-assistant-<version>.zip` from the [latest release](https://github.com/fazrilamin/aichatbox/releases/latest).
   - Use the zip attached to the release, not GitHub's "Source code" zip. The source zip unpacks to a folder like `aichatbox-1.1.0`, which WordPress treats as a different plugin and which can't be updated.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the zip, click **Install Now**, then **Activate**.
3. A new **AI Assistant** menu appears in the admin sidebar.

To update by hand later, upload the newer zip the same way and choose **Replace current with uploaded**. Usually you won't need to: new releases appear under **Dashboard → Updates**.

## 2. Connect an AI provider

Go to **AI Assistant → Settings → AI model**.

1. Choose a **Provider**.
2. Paste the **API key** (or, for Ollama, the bearer token your proxy checks).
   - To keep the key out of the database, add `define( 'AISA_API_KEY', 'your-key' );` to `wp-config.php` instead.
3. Leave **API base URL** and **Chat model** empty to use the provider's defaults, or enter your own.
4. Save.

| Provider | Where to get a key |
|---|---|
| OpenAI | platform.openai.com → API keys |
| Google Gemini | aistudio.google.com → Get API key |
| Anthropic Claude | console.anthropic.com → API keys |
| DeepSeek | platform.deepseek.com → API keys |
| Groq | console.groq.com → API keys (also enter a **Chat model**) |
| OpenRouter | openrouter.ai → Keys |
| Ollama | Your own server, see below |

Check your provider's data-use and retention terms before sending visitor questions to it.

## 3. Index your content

1. Go to **AI Assistant → Dashboard**.
2. Click **Test connection**. You should see "Replied OK using … in … ms."
3. Click **Reindex everything** and wait for the progress bar to finish.

From then on, posts are reindexed as soon as they are saved. A daily reindex also picks up content that blocks pull in from elsewhere.

Check what the AI sees under **AI Assistant → Knowledge**: run test searches, preview a page's Markdown, and exclude pages you don't want used.

## 4. Set up the widget

Go to **Settings → Widget**:

- **Placement:** the floating button, the answer panel under your existing search bars, or both. Choose **Off** to use only the `[aisa_search]` shortcode.
- **Starter questions:** 3–4 questions visitors can click.
- **Contact button link:** shown when the AI can't answer, or when a visitor asks for a quote or a person, e.g. `/contact/`.
- Texts: button, title, placeholder, welcome message, fallback message and disclaimer.

Open your site in a private window and ask a few questions.

## 5. Write house rules (optional)

**Settings → AI model → Extra instructions** is added to every prompt. Use it for things like:

- "We only ship within the EU."
- "Never quote prices; say a sales advisor will confirm them."
- "Use British spelling."

Answers to questions your pages don't cover go in **AI Assistant → Snippets** (one question and answer per snippet). Snippets are shown to visitors and included in `/llms-full.txt`, so keep them suitable for the public.

## Running your own Ollama server

Ollama has no login of its own, so never expose port 11434 to the internet directly:

- Put it behind Caddy or nginx with HTTPS.
- Have the proxy require `Authorization: Bearer <token>`, and enter that token as the API key in the plugin.
- Allow only your WordPress server's IP address.

Set `OLLAMA_CONTEXT_LENGTH=8192` or higher on the server. The default context window is too small and cuts the website content off.

Example Caddyfile:

```
ai.example.com {
    # Replace with your WordPress server's public IP and a long random token.
    @allowed {
        remote_ip 203.0.113.10
        header Authorization "Bearer CHANGE-ME-LONG-RANDOM-TOKEN"
    }
    handle @allowed {
        reverse_proxy 127.0.0.1:11434
    }
    handle {
        respond 401
    }
}
```

Then set **Provider** to Ollama, **API base URL** to `https://ai.example.com`, the **API key** to the token, and **Chat model** to a model you have pulled (for example `qwen2.5:14b-instruct`).

## Uninstalling

Deactivating keeps all data. Deleting the plugin removes its tables, settings and snippets only if **Settings → Privacy & limits → Delete all data when the plugin is deleted** is ticked.
