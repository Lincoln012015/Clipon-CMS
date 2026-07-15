# Changelog

All notable release-facing changes should be documented here.

## 0.9.2
- remove the custom conversion events layer
- track enabled conversion types directly from JavaScript
- add configurable conversion types
- redesign the conversion type manager
- update analytics documentation

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
