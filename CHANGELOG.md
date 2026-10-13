# Changelog

## 1.2.0 — release candidate, 2026-10-13

- Independent button and greeting scroll percentages (0–100); sticky appearance, greeting delay after joint eligibility, accessible initial hiding and no persistent scroll tracking. Legacy reads stay unchanged; older REST clients preserve current percentages and older imports default to zero.

- Exactly two neutral public defaults: primary and example; separate form catalog/editor; create, duplicate, enable, delete and immutable submission snapshots.
- Private JSON export/import of settings, selected forms and complete bundles, with preview, default copies, explicit replacement, nonce/permissions and atomic revision checks. Existing site definitions survive upgrades.
- One primary form, page bindings, two launcher modes and a single column of contact icons.
- Persistent public request references and replay protection.
- Fixed pending submission A → close → open B: the visible form recovers after success, error or timeout. Closing while sending explicitly says that the request continues.
- Service email intentionally includes contacts, saved answers with snapshot labels, source and protected admin link even when contact-form `include_message` is off. Privacy guidance now describes this behavior.
- Atomic ZIP construction, archive/per-file SHA256 manifests; separate fresh-install and existing `WP-NetChat-main` upgrade packages.

This is a local candidate, not a published release or production acceptance. See [validation checklist](docs/RELEASE_CHECKLIST.md) and [upgrade/rollback](docs/UPGRADE.md).

## Earlier work

1.0.0 is the historical public release. Options API/revision migration, contact intake, email/privacy and subsequent editor work are documented in the existing historical reports. Their old PASS results are not reasserted here.
