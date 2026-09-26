# Verification — 1.12.2

## Checks executed for this update

| Check | Result |
| --- | --- |
| PHP syntax, PHP CLI 8.4.23 | 19 files passed |
| JavaScript syntax, Node 22.16.0 | 10 files passed |
| CSS parser, PostCSS | 8 files passed, all byte-identical to 1.12.1 |
| Isolated PHP assertions | 107 passed |
| Browser assertions | 80 passed in 4 cases |
| Source bundle | EN 1440 px; AR 390 px |
| Production bundle | EN 390 px; AR 1440 px |
| Browser uncaught script errors | None in the four fixtures |
| Original files removed | None |
| Original purchase/history/cart sections | Four protected scopes byte-identical |

## Logic coverage

New Arrival day boundaries including 20 days, future dates, custom durations and
disabled labels; preservation of publication age; real restock transitions,
initial publication, unknown old state, backorders and expired cycles; variation
versus parent state; quantity and stock exclusions; strict English/Arabic serving
counts; ambiguous/conflicting count rejection; explicit dietary positive/negative
claims and caffeine contradictions; normalized structured attributes; exact
available variation price/serving pairing; flavour-only count inheritance; size
variation safeguards; value order and combined-filter intersections.

## Browser coverage

Filter selection/deselection, duplicate chip state, exact filter intersection,
ascending verified value independent of evidence tier, restored relevance order,
failed-request retry, stale-response isolation, bounded stock batches covering
more than 64 IDs, unchanged native cart button/quantity DOM, badge expiry without
a reload, lower-corner EN/AR placement and no horizontal viewport overflow.
The fixture used synthetic product records, not live Qimia products or prices.

## Scope integrity

Only five existing runtime files changed: main plugin version/catalogue adapter,
goal engine, inventory continuity adapter, source JS and its production bundle.
One new runtime helper was added: `includes/verified-filters.php`.
All CSS, templates and other existing runtime modules remain byte-identical.
Release notes, readme and the build manifest were updated separately.

The protected purchase-history/exact-selection/context helpers, server cart-write
and ownership/duplicate-request checks, browser Buy Again card helpers, and browser
exact add-to-cart mutation were compared directly against the base archive.

## Limits

These are isolated tests, not a deployed-site guarantee. PHP logic tests used
WordPress/WooCommerce stand-ins. The exact JavaScript bundles were executed in
Chromium with a synthetic DOM and location/network adapter; no site navigation
was performed. Real database queries, theme/plugin integration, PHP 8.2 runtime,
actual WooCommerce multi-currency hooks, production concurrency, checkout and
payments were not tested here. Browser fixtures are not live-site screenshots.
Historical reports for prior releases are retained as historical documents and
are not claimed as newly re-executed test suites for this release.
