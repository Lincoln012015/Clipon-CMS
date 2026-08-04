# Storage

Clipon CMS uses flat-file storage. There is no CMS core dependency on MySQL.

## Guarded JSON Files

Most structured files are JSON payloads written through `JsonStorage`. When saved with a `.php` extension, files include:

```php
<?php die(); ?>
```

This blocks direct web reads while allowing the CMS to strip the guard and decode JSON.

## Runtime Directories

- `content/pages/` - page content.
- `content/blog/` - blog post content.
- `content/history/` - saved versions.
- `config/` - settings, route maps, directories, conversions, update state.

## Analytics storage

Open-day analytics is stored under `data/analytics/state/YYYY-MM-DD/` in 64 deterministic shards (`00.php`–`3f.php`). Each shard has a stable companion lock and is updated with a temporary file plus atomic rename. `data/analytics/cache/YYYY-MM-DD.php` is a disposable 60-second report cache and is never a source of truth. Rate-limit buckets live separately under `data/analytics/rate/`.

After the UTC day and grace period close, the first report request lazily compacts the day into `data/analytics/YYYY-MM-DD.php` as schema v3 and records an input checksum manifest. A scheduled job may invoke the same idempotent compactor after the grace period, but is not required. The daily snapshot is written and verified before the state is marked closed.

Dashboard and PRO reports use one shared day reader with this precedence:

1. open schema-v3 shard state;
2. compacted schema-v3 daily snapshot;
3. legacy schema-v2 daily/monthly data.

This prevents an open day from disappearing and avoids counting the same day from two formats. Older schema-v2 files remain readable. Do not delete an open or uncompacted state directory; cache files may be deleted safely.

Analytics retention removes expired legacy/daily snapshots, schema-v3 state directories, and report caches. **Clear analytics** also removes schema-v3 state, caches, rate-limit buckets, analytics locks, and analytics system logs; it does not leave live v3 data behind.
- `data/analytics/` - daily analytics files and locks.
- `data/geoip/` - GeoIP datasets/status.
- `data/blog_tags.php` - localized tag dictionary.
- `data/blog_index.php` - generated blog index.
- `logs/` - operational logs.
- `assets/uploads/` - uploaded media.

## Atomicity And Locks

`JsonStorage` writes through a temporary file and atomic rename. Analytics storage uses update locks for daily data. Media operations use root containment checks.

## Migration Expectations

Future migrations should:

- preserve guarded file format;
- avoid direct string edits where `JsonStorage` can be used;
- backup content/config before destructive changes;
- rebuild route map after URL-affecting page/blog changes;
- keep old runtime state out of release artifacts unless intentionally migrating a site.
