# Upgrade

## Upgrade Model

Clipon is designed so system files can be replaced while site-owned runtime state remains outside them.

Runtime state:

- `content/`
- `config/`
- `data/`
- `logs/`
- `modules/`
- `templates/`
- `assets/uploads/`

## Before Upgrade

1. Back up runtime directories.
2. Record current CMS version.
3. Record installed module versions.
4. Run smoke checks on the new artifact in staging.
5. Confirm PHP requirements. Node.js is not required for normal CMS runtime; the prebuilt editor bundle is included in the release.
6. Compare the new root `index.php` and `.htaccess` with the deployed copies; they are part of the release and may contain routing or security updates.

## Upgrade Steps

### Automatic Core Upgrade (0.11.0 and newer)

1. Open **Admin → Settings → License & Updates**.
2. Select **Check for updates**.
3. Review the version and changelog, then select **Install Core update**.
4. Keep the browser open while the package is downloaded, verified, and installed.
5. After the page reloads, confirm the displayed CMS version and run public/admin QA.

Automatic installation fails closed unless the release supplies a valid SHA-256 checksum. Before changing system files, the updater also validates the package version, ZIP paths, archive limits, and `clipon/config/integrity_manifest.php`. It rejects the same or an older version.

The updater replaces only the release-managed `clipon/`, root `index.php`, and root `.htaccess`. Site-owned runtime directories remain untouched. The previous Core is retained under `.clipon_backups/core_updates/`. If a failure occurs after replacement starts, the installer restores the previous Core and root files automatically.

The PHP process must be able to write to the installation root and rename the `clipon/` directory during this controlled operation. Normal runtime may keep Core files read-only; grant update permissions only for the upgrade window and remove them afterward. PHP cURL and ZipArchive are required for automatic installation.

### Manual Core Upgrade

1. Put the site into maintenance mode if possible.
2. Remove or move aside the old `clipon/` system directory, then install the new `clipon/` directory as a clean tree. Do not overlay it: an overlay can retain core files removed by the new release.
3. Do not copy the old `clipon/config/` over the new directory. It contains release-owned defaults and integrity metadata, not the site-owned root `config/` runtime directory.
4. Update the root `index.php` and `.htaccess` from the new release when they differ. Preserve only intentional, reviewed hosting-specific changes.
5. Preserve the root runtime directories listed above.
6. Apply module updates if needed.
7. Verify integrity on a clean staging extraction before restoring an installed site's `config/settings.php`: open `/clipon/setup.php?step=2` and confirm that the integrity check passes. Setup is intentionally locked after installation, so this check must be performed on the clean staging copy. A required-file mismatch means the system tree is incomplete or modified; redeploy a clean release rather than editing the manifest.
8. Log in to admin and verify settings.
9. Rebuild the route map if URL/content migrations occurred.
10. Run public/admin QA.

## Integrity Verification

`clipon/config/integrity_manifest.php` contains SHA-256 hashes generated for the release:

- `required` files are security- or boot-critical. Missing files and hash mismatches fail the setup integrity check.
- `optional` files may be absent for a supported layout, but must match their hash when present.

The setup environment-check step on a not-yet-installed staging copy verifies these hashes and reports corrupted or missing required paths. It is not exposed after installation. On mismatch, confirm that the release finished uploading, check for server-side modification or malware, and redeploy system files from a trusted release. Site operators should not regenerate or hand-edit the manifest. Maintainers regenerate it only while producing a new official artifact after intentional source changes.

## Rollback

For an automatically installed update, locate the retained backup reported under `.clipon_backups/core_updates/`. If automatic rollback already succeeded, verify the restored version before taking further action. For manual recovery, restore the backup's `clipon/`, root `index.php`, and root `.htaccess`; restore runtime data only if it was changed separately. Verify admin login, public routes, blog, media, redirects, and analytics.

## Migrations

Some features can run migrations, such as primary language/content migration. Migrations should backup content/config first and write reports under `logs/migrations/`.
