# Changelog

All notable release-facing changes should be documented here.

## 0.10.3

- Added the automated Core release pipeline that publishes the sanitized public source, immutable GitHub tag, ZIP archive, and SHA-256 checksum from one deterministic export.
- Integrated stable releases with the protected license-server Release API and added end-to-end verification of public metadata, the CMS update API, and the latest-download redirect.
- Replaced version-specific website and Core download URLs with the permanent `https://server.clipon-cms.com/download/latest` endpoint.
- Added regression coverage for release workflow helpers, stable download links, and preservation of Core update version, package URL, changelog, and SHA-256 metadata.

## 0.10.2

- Fixed Dashboard and Pro Analytics synchronization by using live schema v3 state as the authoritative source for open days instead of stale daily snapshots.
- Unified analytics date validation, UTC reporting windows, and the default 30-day period across Dashboard and Pro Analytics.
- Added quick analytics filters for today, yesterday, the last 3 days, the last 7 days, and the last 30 days.
- Added regression coverage to ensure Dashboard and Pro Analytics return matching live page-view and unique-visitor totals.

## 0.10.1

- Added German translation.
- Fixed a bug that caused outdated button styles to be used.

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
