# Privacy

This document describes the CMS behavior. Site owners remain responsible for their legal notices, consent text, retention policy, and compliance obligations.

## Data Stored By The CMS

Depending on enabled features, Clipon CMS can store:

- the Core administrator account and password hash; additional users, restricted roles, and granular permissions when an optional user-management module is active;
- page and blog content;
- media filenames and alt metadata;
- route maps and redirects;
- analytics counters and event data;
- configured custom conversion events and, when triggered, their event key, conversion type, normalized page path, timestamp, UTM values, and referrer host;
- cookie consent state in browser cookies;
- license/update state;
- logs for auth, CSRF, update, migration, and application behavior.

## Analytics Modes

`privacy_basic`:

- no analytics visitor cookie for anonymous public requests;
- no PHP session cookie for anonymous public analytics collection;
- daily unique counting through a short-lived daily hash;
- raw IP and raw User-Agent are not stored in analytics reports.

`full_with_consent`:

- active only after cookie banner acceptance;
- sets first-party visitor/consent cookies;
- enables session-level metrics such as exit pages, bounce rate, and conversion dedupe.

When PRO custom conversion events are configured, they use the active analytics privacy mode. Privacy/basic mode does not create an analytics visitor cookie and deduplicates an event using the page-view identifier. Full-with-consent mode can use session state and suppresses repeated instances of the same event and path within a five-minute window. Custom event records do not store the raw referrer URL; only its normalized host is retained.

## Cookies

Relevant cookies can include:

- admin PHP/session cookies;
- CSRF/session-related admin state;
- `clipon_cookie_consent`;
- analytics visitor cookie in full-consent mode.

## External Requests

The CMS makes the following outbound requests when the corresponding feature runs:

- **License verification and activation** sends the configured license key, site domain, PHP version, CMS version, check mode, and installed module version map to `https://server.clipon-cms.com`.
- **Core update checks** send the site domain, PHP version, current CMS version, check mode, and a hash representing the installed module directory state to `https://server.clipon-cms.com`.
- **GeoIP database updates** download aggregated country IP ranges from `https://www.ipdeny.com`.

License and update requests occur during explicit admin checks and may also run through the CMS's scheduled/guardian checks. They require outbound HTTPS and use PHP cURL when available, with URL streams as a fallback when enabled. The CMS does not send visitor analytics records to the license/update service.

## Local Storage

CMS data is stored on the site server in runtime directories such as `content/`, `config/`, `data/`, `logs/`, and `assets/uploads/`.

Visitor IP addresses are resolved locally against the downloaded GeoIP data and are not sent to that provider by the GeoIP resolver.

## Retention

Analytics retention is configurable in Settings. Clipon does not automatically rotate application logs or delete backups. Site operators should configure server-level log rotation and a retention schedule appropriate to their operational and legal requirements, and should periodically remove expired backups from storage.

## Suggested Site Policy Topics

Site owners should disclose:

- what analytics mode is enabled;
- what cookies are used;
- whether PRO analytics is enabled;
- that license/update checks send site and runtime metadata to `server.clipon-cms.com` when those features are used;
- how long analytics/logs are retained;
- how users can request data removal if applicable;
- contact details for privacy questions.
