# Release 1.2.0 candidate checklist

Scroll addition acceptance remains open until the frozen source/packages pass real SQL and both hosted browser matrices. Check initial server hiding/tab order, below/at/above 25/50/100%, independent sticky thresholds, greeting delay at joint eligibility, dismissal/open-menu/form suppression, reload/history restoration, no-scroll page, dynamic height/resize, MutationObserver fallback without ResizeObserver, desktop/mobile rules, reduced motion/speed0 and explicit CTA/API before thresholds. Preserve pending success/error/20-second timeout scenarios. Verify admin save/reload/invalid feedback at 320/390/1440, authenticated older REST request, normalized export/legacy import, raw legacy read/CAS, stale proofs and atomic rollback. Source lint or prepared tests alone do not close this checklist.

Distribution/transfer revision: public defaults are exactly two neutral forms. Private owner schemas and export files stay outside public source, hosted data and release assets. JSON transfer supports selected forms, settings and atomic bundle with preview, add/copy by default and explicit replacement. Current SQL/security/rollback and browser-transfer tests are prepared; NOT_RUN until observed execution. Earlier local results below apply to the earlier candidate and do not accept this new runtime revision.

Publication is pending a separate owner decision. Local checks ran on 2026-10-13 against WordPress 7.1.3 / PHP 8.3.33: JS32, WordPress104, lead/mail/privacy63, service247 (including full-answer email), editor130 and lifecycle33 assertions passed. PHP lint passed. Isolated ZIP smoke passed clean install, reinstall, reactivation, default uninstall retention, opt-in purge and the actual historical v1.0.0 archive upgrade preserving WP-NetChat-main basename and legacy JSON/contact settings. The final browser suite passed seven synthetic service paths and pending A→close→B success, error and real 20-second timeout. Restored configuration matched its original SHA256, the temporary session was revoked and synthetic requests were removed. These results do not establish minimum-version compatibility or production acceptance.

| Check | Scope/status |
|---|---|
| PHP 7.2 / WordPress 5.0 minimum pair | NOT_RUN locally; runtime unavailable, no images/dependencies downloaded |
| Existing lab WordPress 7.1.3 / PHP 8.3.33 | Observed live; local checks passed |
| `Tested up to: 7.1` | Declared major/minor ceiling; not an acceptance of every environment |
| JS normalization, PHP lint, storage/permissions/validation/replay/full service mail | PASS on the observed local runtime |
| Pending A→close→B success/error/20-second timeout | PASS in headless Edge with synthetic data |
| ZIP clean install, reinstall, deactivate/uninstall retention | PASS in isolated smoke |
| Actual public `WP-NetChat-main/` v1.0.0 → matching candidate | PASS: basename/JSON/contact/request retention |
| Real phones, screen reader, production theme/cache/SMTP | NOT_RUN; separate product acceptance |
| Hosted WordPress 5.0 / PHP 7.2 and WordPress 7.1.3 / PHP 8.3 | Runtime workflow prepared; NOT_RUN until actual Actions execution |
| GitHub CI | Prepared; status must reference the actual run/commit before publication |

## Package and public Git

- Validate fresh-install and matching upgrade ZIP roots, version/header/stable tag, archive SHA256 and every manifest entry hash.
- Include runtime files only: entry point/uninstall/readme/LICENSE, PHP modules/preset JSON, approved assets/languages. Exclude governance, secrets, editor workspace files, lab, tests/results and development scripts from ZIP.
- Public repository may include source, public docs, tests and CI. Use an explicit reviewed file list for staging; never `git add .`. Ignore rules do not remove previously tracked secrets.
- Do not commit `AGENTS.md`, `contur-admin/`, credentials, private session output, workspace files or release test results.
- Preserve existing uncommitted changes and old dist ZIPs; review candidate independently. No push, tag, release or production installation is part of this checklist's execution.

Commands: `pwsh -File tests/run.ps1` (server/package only); `node tests/service-forms-e2e.mjs` with bundled Playwright and outputs under `dist/release-1.2.0/browser`. The general `-Browser` runner is intentionally blocked because other suites still write historical result paths.

Hosted workflow `Release compatibility` builds both packages on Windows and passes those exact artifacts to each runtime pair. `tests/compatibility-ci.sh` verifies archive/per-entry/source hashes, exact core/PHP versions and WordPress checksums; runs the five existing integration suites and isolated ZIP smoke with the hash-verified public v1.0.0 asset. No hosted runtime PASS is claimed before the real run. Existing local results remain distinct from hosted results and production acceptance.
