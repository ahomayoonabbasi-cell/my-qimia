# Your Qimia Plus 1.6.2 — name-only header

Lab `1.6.2-health-rc.1`; Health asset version `2.2.2-rc.1`.
Database schema `2.2.1`, Commerce `3.51.7` and Worker protocol `3.51.4` are unchanged.

## Scope
The site header and mobile pages menu render only `Your Qimia` / `كيميا لك`.
No trial, price, Plus-state or remaining-days badge is rendered under that name.
The badge HTML, subtitle metadata, obsolete badge CSS and header status hydration
have been removed at source, rather than hidden using another CSS override.

The lightweight site navigation script retains outside-click/link-click/Escape
handling. Restoring the workspace from browser back/forward cache still asks the
workspace to refresh its own plan. There is no header-initiated membership fetch.
The old read-only AJAX endpoint is retained for compatibility with cached clients.

The name, link URL, active-page underline, language guards, theme colours and
fonts remain. Trial activation, thirty-day duration, pricing, paid access, diary,
profiles, routine, label checks, consent, AI projection and storage installer are
not rewritten. The workspace still shows its own plan information and controls.

## Install
Back up the staging database, plugin directory and WordPress salts. Replace the
existing `qimia-intelligence-lab-8-2` plugin, not a second parallel copy. Keep
Commerce 3.51.7. Open Qimia Health as administrator to check existing storage.
No additional schema update is introduced. Clear cached site/header HTML and
cached/minified CSS/JS for both languages, then reload. Do not delete tables,
reset trial dates or disable security/caching across the entire store.

Only approved staging/local environments and authorised synthetic test accounts
are enabled by the inherited release. A demo remains a demo until the user has
explicitly created a saved workspace; installation never starts a free trial.

## Validation and limitations
See the current 1.6.2 test report supplied alongside the ZIP for the checks actually
run. Prior release reports are historical, not fresh 1.6.2 tests. Local rendering
and test doubles do not establish compatibility with the deployed WordPress,
MySQL, WoodMart, translation plugin, Safari, Worker or payment gateway. No live
site changes, real payments, production load or clinical validation are claimed.

Earlier release notes describe their historical header badge behaviour; this
release supersedes those header-specific instructions only.
