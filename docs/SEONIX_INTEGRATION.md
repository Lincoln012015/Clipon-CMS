# Seonix Integration

The free `seonix` module connects a Seonix Custom API channel to Clipon CMS. Install it under `modules/seonix/`, sign in as an administrator, and open **Seonix** in the navigation.

1. Generate a token and copy it immediately.
2. Enable the integration and choose Draft or Publish immediately.
3. Create a Custom API/Custom Webhook channel in Seonix.
4. Copy the Publish URL, Delete URL template, and token from Clipon.
5. Publish a test article. Draft is the recommended initial mode.

Clipon upserts articles by `external_id`, maps `ua` to `uk` by default, sanitizes incoming HTML, creates tags, downloads HTTPS cover/OG images to `assets/uploads/seonix/`, and rebuilds the route map. Remote deletion is disabled until explicitly enabled. Markdown-only payloads are not supported in version 1; Seonix must send `content_html`.

The response uses the Seonix `external_id` as `id` and includes the public URL, update timestamp, and non-fatal media warnings. Recent request metadata is available on the module screen; tokens and article bodies are not logged.
