# Changelog

All notable release-facing changes should be documented here.

## 0.10.0

- Replaced request-time counting with signed, client-confirmed `page_view` events; plain GET, crawler, probe, 404, and 500 requests no longer affect product metrics.
- Added short-lived HMAC page tokens with key rotation, strict event envelopes, origin checks, rate limits, replay protection, and fail-closed diagnostics.
- Added 64-way daily state sharding with stable locks, atomic writes, idempotent event transitions, report caching, and schema v3 compaction.
- Moved tracking into a first-party JavaScript asset with visibility-aware views, bfcache refresh, engagement heartbeats, maximum scroll depth, and conversions.
- Preserved schema v2 report compatibility while adding open and compacted schema v3 reporting.

## 0.9.1

- Canonicalized active public page and blog routes without a trailing slash.
- Added application-level `301` redirects from trailing-slash URL variants while preserving query strings and subdirectory installation paths.
- Added regression coverage for root, nested, localized, encoded, and subdirectory URLs, including safe request-method handling.

## 0.9.0

- Added configurable custom conversion types to Core analytics.
- Added direct PRO conversion tracking by enabled conversion-type key, with server-side validation and privacy-mode-aware deduplication.

## 0.8.1

- Prepared the first public Clipon CMS release and user documentation.
- Added and documented the setup wizard, admin and inline editor, page/blog/media management, redirects, multilingual routing, analytics, and runtime module integration.
- Hardened authentication, sessions, permissions, CSRF enforcement, uploads, path handling, public error output, and runtime-data deployment guidance.
- Added local browser dependency bundles, third-party dependency notices, upgrade/backup guidance, and release integrity checks.
- Standardized the supported CMS runtime on PHP 8.0+.
