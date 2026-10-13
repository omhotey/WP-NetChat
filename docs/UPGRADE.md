# Upgrade and rollback — 1.2.0 candidate

Scroll settings `button_scroll_percent` and `hint_scroll_percent` default to integer 0. Ordinary reads of older stored settings supply effective defaults without updating option bytes or revisions. Older REST clients omitting these keys keep the current values; an explicit save/import uses existing authentication and revision checks. Older portable JSON imports supply 0. Export includes both normalized values. A form-only import leaves settings intact. Public/private packages use the same runtime; no existing form/submission data is reset for this feature.

Publication and installation on production need a separate owner decision. Review RELEASE_CHECKLIST.md first; PHP 7.2 / WordPress 5.0 remain declared minima but were not tested locally in this gate.

## Choose the matching package

Inspect the actual installed plugin basename in WordPress before uploading. The historical public v1.0.0 asset has root `WP-NetChat-main/` (not `netchat/`). Never infer the root from the ZIP filename.

| Installed basename | Candidate ZIP | Entry point |
|---|---|---|
| Fresh installation / `netchat/netchat.php` | `netchat-wp.zip` | `netchat/netchat.php` |
| `WP-NetChat-main/netchat.php` | `netchat-upgrade-WP-NetChat-main.zip` | `WP-NetChat-main/netchat.php` |

Another basename requires a reviewed matching package; do not rename the folder automatically or activate two copies. Both packages contain identical runtime files with different root names. Verify archive SHA256 and every ZIP entry against the corresponding `.manifest.json`.

## Before and after upgrade

Public packages contain exactly two neutral defaults (primary and example), created only when no saved form registry exists. Upgrades preserve the existing registry/settings/bindings and do not reinstall deleted forms. Transfer your own definitions privately using [JSON export/import](DATA_TRANSFER.md); never attach a private bundle to a public issue or release. A private package may contain the owner's existing definitions with identical common runtime; its first definition is selected primary only on fresh initialization if the public primary ID is absent. Existing primary selection survives upgrades.

1. The site owner makes their normal database/files backup, separately from this task. Retain the installed folder, database and `wp-content/netchat-data.json`; no automatic backup is created by the plugin.
2. Record basename, current version, enabled contacts, form IDs/bindings/primary, sample existing request IDs and configured recipients/documents. Keep this inventory private.
3. On a matching staging environment, replace the same plugin basename through WordPress; keep a single active copy. The legacy JSON importer reads `WP_CONTENT_DIR/netchat-data.json` independent of plugin folder name, preserves the source and migrates settings to Options API.
4. Verify contacts, settings/revision, saved requests/snapshots, primary form, page bindings and one synthetic request/retry. Verify real consent documents, retention and recipients before enabling intake. New installations start with intake disabled.
5. Check WP-Cron, outbound mail transport, error/retry states and privacy export/erase. `wp_mail=true` means handed to mail transport, not delivered. Service notifications include full saved answers regardless of the legacy contact-message switch; deletion does not recall email or hosting backups.

## Rollback

Deactivation and ordinary uninstall preserve data. Never set `NETCHAT_DELETE_DATA=true` during upgrade or rollback: it explicitly deletes plugin options, request tables and legacy JSON on uninstall.

Stop intake before a rollback decision; restore only the reviewed plugin code into the same basename and preserve current data. Returning to 1.0.0 cannot read the newer SQL requests/forms and may expose stale JSON configuration; it is not a safe automatic rollback. Retain new records and coordinate a version-compatible recovery rather than replacing the database with an old snapshot. Stop on schema incompatibility. Runtime rollback and production recovery were not performed by this gate.
