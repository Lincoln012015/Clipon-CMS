# Security Policy

## Supported Versions

| Version | Supported |
| --- | --- |
| 0.8.x | Yes |
| < 0.8 | No |

Only the latest patch release in the supported `0.8.x` line is guaranteed to receive security fixes. Upgrade before reporting an issue that is already resolved in a newer patch.

## Reporting A Vulnerability

Do not open a public issue with exploit details, credentials, license keys, private URLs, logs, database files, or server secrets.

Report security issues through [GitHub's private vulnerability reporting form](https://github.com/Lincoln012015/Clipon-CMS/security/advisories/new). Include:

- affected version or commit;
- affected component: CMS core, admin, modules, or documentation;
- reproduction steps;
- impact and affected data;
- any suggested mitigation.

## Security Boundaries

- Admin pages and admin APIs require an authenticated session, except for explicitly public endpoints such as analytics event tracking.
- State-changing CMS APIs use CSRF tokens.
- Runtime data is stored locally in guarded flat files.
