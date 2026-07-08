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
