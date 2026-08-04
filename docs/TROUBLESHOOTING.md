# Troubleshooting

## Blank Page Or 500 Error

- Check PHP error logs.
- Confirm PHP 8.0+.
- Confirm required files exist under `clipon/`.
- Confirm runtime directories are readable/writable.

## Cannot Log In

- Check `logs/auth.log`.
- Confirm sessions can be written by PHP.
- Confirm login throttle state is not locked unexpectedly.
- Confirm server time is correct.

## CSRF Errors

- Reload the admin page and retry.
- Confirm cookies are not blocked for the admin domain.
- Check `logs/csrf_failures.log`.
- Confirm reverse proxy/domain settings are stable.

## Uploads Fail

- Confirm `assets/uploads/` is writable.
- Confirm file extension and MIME type are allowed.
- Check PHP upload size limits.

## Routes Or Redirects Are Wrong

- Save the affected page/post again to rebuild route map.
- Check `config/route_map.php`.
- Confirm multilingual slugs are unique per type/language.
- Check redirect loops in admin Redirects.

## Analytics Not Recording

- Confirm request is a GET HTML request with a normal browser User-Agent.
- Check bot allowlist/denylist.
- Confirm analytics mode and cookie consent configuration.
- Check write permissions for `data/analytics/`, especially `state/`, `cache/`, and `rate/`.
- Inspect the current UTC day under `data/analytics/state/YYYY-MM-DD/`. Current data is expected there before compaction; the absence of a root daily file for today is not an error.
- Delete only `data/analytics/cache/YYYY-MM-DD.php` to force a safe report rebuild. Do not delete an open state directory.
- Confirm the public page loads `clipon/assets/js/analytics.js` and that `track_event.php` accepts the initial `page_view` before later events.
- If long-lived tabs stop recording, inspect token-renewal requests. Renewal normally runs every eight minutes and preserves the existing page view.

If Dashboard has current data but a PRO report stops at an older date, verify that `pro_analytics` is active and uses the shared `AnalyticsDayReader`. Reports must read open v3 state before compacted v3 or legacy v2 data. A closed day is compacted automatically on the first report request after the grace period.

## License Or Update Errors

- Confirm license key is configured.
- Confirm server can reach configured license/update endpoints.
- Check `config/updates.php`.
