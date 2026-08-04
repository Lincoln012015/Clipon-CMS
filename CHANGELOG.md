# Changelog

All notable release-facing changes should be documented here.

## 0.12.0

- Fixed Dashboard and Pro Analytics failing to show the latest collected data.
- Improved long-visit tracking so engagement, scroll depth, and conversions continue to be recorded without creating duplicate page views.
- Added complete traffic-source, referrer, first-touch and last-touch attribution, and conversion-funnel reporting for current analytics data.
- Improved analytics storage maintenance with automatic daily compaction, retention support, and complete data clearing.
- Fixed server-side conversions so they appear in current analytics without inflating page-view, visitor, or session totals.

## 0.11.1

- Fixed the **Install Core update** button remaining visible when no newer Core version is available.
- Reduced the downloadable CMS package to the files required for installation, excluding documentation, screenshots, and repository-only files.

## 0.11.0

- Added authenticated one-click Core installation from the CMS administration panel.
- Added mandatory SHA-256 and package-integrity verification before a Core update is installed.
- Added protection against unsafe or corrupted update archives and prevented installing the same or an older version.
- Preserved site content, configuration, uploads, templates, modules, and other runtime data during Core updates.
- Added automatic backup and rollback when an update cannot be completed safely.

## 0.10.3

- Added CMS release packages with published SHA-256 checksums.
- Added a permanent latest-version download link so website download buttons no longer depend on a specific release number.
- Improved update information with consistent version, download URL, changelog, and checksum metadata.

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
