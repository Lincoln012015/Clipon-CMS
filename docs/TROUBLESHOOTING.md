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
- Check `data/analytics/` write permissions.

## License Or Update Errors

- Confirm license key is configured.
- Confirm server can reach configured license/update endpoints.
- Check `config/updates.php`.
