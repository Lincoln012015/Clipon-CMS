# Backup And Restore

## What To Back Up

Back up these runtime directories before deploys, updates, migrations, and manual server changes:

- `content/`
- `config/`
- `data/`
- `logs/`
- `modules/`
- `templates/`
- `assets/uploads/`

## Backup Command Example

Create archives outside the web document root. For example, from a deployed site root, write directly to an operator-controlled backup directory:

```bash
tar -czf /var/backups/clipon-runtime-backup-$(date +%Y%m%d-%H%M%S).tar.gz content config data logs modules templates assets/uploads
```

Replace `/var/backups` with a writable location that is not web-accessible, then restrict the archive to the backup operator. Never leave `.tar.gz`, `.zip`, database dumps, or temporary backup copies in the site root.

## Restore

1. Put the site into maintenance mode if possible.
2. Restore the runtime directories.
3. Verify ownership and permissions.
4. Clear broken partial files if any interrupted write left temporary files.
5. Log in to admin and rebuild route-affecting content if needed by saving a page/blog item.
6. Verify public pages, blog, media, redirects, and analytics.

## Before Major Updates

- Build and test the release artifact.
- Backup runtime directories.
- Record current CMS version.
- Record installed module versions.
- Keep a copy of the previous `clipon/` system directory for rollback.
