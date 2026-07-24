# Configuration

Clipon CMS stores site-owned configuration outside the system directory.

## Main Config Files

In a release install:

- `config/settings.php` - site settings, languages, analytics mode, cookie banner, pagination, and the conversion-type catalog.
- `config/route_map.php` - route map and redirects.
- `config/directories.php` - page directory tree.
- `config/blog_directories.php` - blog directory tree.
- `config/media_meta.php` - media metadata; per-language alt text is used when the optional Multilang module is active.
- `config/conversions.php` - generated URL-to-conversion-type assignments for pages.
- `config/updates.php` - update/license sync cache.

Some legacy/runtime files may also exist under `clipon/config/`, such as login throttle state.

Most structured files are JSON payloads written by `JsonStorage` with a `<?php die(); ?>` guard.

## Site Settings

Settings are managed through `/clipon/admin/settings.php`. Important fields:

- site name, description, email, and canonical site URL;
- language list and primary language;
- analytics retention;
- cookie banner and analytics mode;
- conversion types;
- blog pagination styles and labels;
- powered-by visibility/theme;
- license key and update state.

## Analytics

Analytics modes:

- `privacy_basic` - default; no visitor cookie for anonymous public requests.
- `full_with_consent` - full analytics after cookie banner acceptance.

If the cookie banner is disabled, public requests fall back to privacy/basic mode even when full mode is configured.

### Conversion configuration

Conversions use two separate configuration layers:

- `conversion_types` in `config/settings.php` is the catalog of available types. Each entry has a stable `key` and an `enabled` flag; enabled types appear in the page conversion selector.
- `config/conversions.php` assigns public page URLs to selected type keys. It is generated from page records when a page is created, updated, deleted, copied, or when page configuration triggers a map rebuild. A fresh install may not contain this file until the first such operation.

The generated file is a guarded JSON payload, for example:

```php
<?php die(); ?>
{
    "pages": {
        "/thank-you": "lead",
        "/checkout/complete": "purchase"
    },
    "updated_at": "2026-07-11T12:00:00+00:00"
}
```

Manage these values through Settings and page configuration. Do not maintain the generated URL map manually.

Custom conversion types are stored alongside the built-in type catalog in `conversion_types`. A custom item has a stable `key`, a display `label`, an `enabled` flag, and `custom: true`. Keys are normalized to lowercase letters, digits, underscores, and hyphens. Up to 40 custom types may be stored.

When the `pro_analytics` module is available, frontend code can record any enabled conversion type directly with `window.cliponAnalytics.trackConversion('type_key')`. Type keys are managed through **Settings > Analytics** and remain stable even when their administrator-facing labels change. Unknown or disabled keys are rejected by the tracking endpoint.

For the browser-side tracking call and deduplication behavior, see [ANALYTICS_GUIDE.md](ANALYTICS_GUIDE.md#direct-conversion-tracking-pro).

## Modules

Installed modules live in `modules/` in the deployed site and are loaded at runtime. Module metadata is stored in each module's `manifest.php`. This CMS-only repository provides the module runtime interfaces and gates, but does not bundle installable modules or a module development guide.

## License And Updates

CMS update and license clients use the production service URLs defined in `clipon/lib/CoreUpdater.php` and `clipon/lib/License.php`; no public build-time URL substitution is performed.

Runtime update cache is stored in `config/updates.php`. It is not the source of truth for the current CMS version, which is `clipon/config/version.php`.

### Analytics event security

The analytics pipeline stores `analytics_signing_keys` as a `kid => secret` map and `analytics_active_kid` as the key used for newly issued page tokens. CMS 0.10.0 generates and saves a random 256-bit key on first use. Keep the previous key in the map during rotation until all ten-minute tokens issued with it have expired. Never publish these values.

Bot-filter allowlist entries must use an explicit `ua:<fragment>` or `path:<path-or-prefix>` form. Empty and overly broad entries such as `/`, `mozilla`, or `chrome` are discarded. Allowlisting bypasses only heuristic bot/probe matching; token expiry, replay protection, origin checks, and rate limiting still apply.

## Runtime-Only State

Do not copy local runtime state between unrelated installs unless intentionally migrating a site:

- `content/`
- `config/settings.php`
- `config/route_map.php`
- `config/updates.php`
- `data/analytics/`
- `data/geoip/`
- `logs/`
- `assets/uploads/`

For migration and backup, see [BACKUP_AND_RESTORE.md](BACKUP_AND_RESTORE.md).
