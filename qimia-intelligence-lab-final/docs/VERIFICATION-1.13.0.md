# Verification — Qimia Intelligence Lab 1.13.0

Date: 2026-09-21. Baseline: qimia-intelligence-lab-1.12.2-home-cashback-final.zip.

## Executed checks

| Check | Result | Actual scope |
|---|---|---|
| PHP logic scenarios | 68 / 68 passed | Actual new selector and unchanged continuity / dietary / value code, with local WordPress/WooCommerce/ledger doubles and synthetic products/orders. |
| Browser integration scenarios | 28 / 28 passed | Actual source bundle and a repeated final run using the packaged compact bundle; actual card renderer and CSS, controlled inline fixture DOM, locally mocked transport. |
| PHP syntax | 20 / 20 files passed | PHP CLI 8.4.23. |
| JavaScript syntax | 11 / 11 files passed | Node 22.16.0, including the final production bundle. |
| Languages/layout | EN + AR, 390/768/1440px | Home, product and cart; no fixture page overflow, same card actions, proper RTL and below-related placement. |
| Package integrity | CRC / content hashes checked | Recorded in BUILD-MANIFEST.json / generated verification data; original root directory retained. |

Browser: Chromium 144.0.7559.96, Playwright. Browser navigation restrictions were
not changed; DOM, scripts and styles were injected into inline component pages.
This is **not a full WordPress/WoodMart/Elementor site navigation test**. Native
WooCommerce Cart Block lifecycle was not executed end-to-end; its PHP render
adapter, one-emission behavior and client cart surface were tested separately.

The final minified-bundle run is not counted as 28 extra distinct scenarios;
there are 28 unique browser scenarios. These are scenario totals, not a claim
of 28 individual assertions or complete test coverage. JSON details accompany
this report in `docs/verification-1.13.0/`.

The logic suite checks account ownership and isolation, guest/consent boundaries,
real viewed-vs-impression semantics, exact flavour and current price, parent
reassignment, stock loss, refunds, dietary contradictions, exact per-serving
math/order, AND filters, duplicates/current/cart/removed exclusions, shortlist
limits, cached-reference invalidation, non-waiting account locks, signed tokens,
forged purchase-event rejection, old-account token rejection, no synchronous
fabricated conversion and classic/block single cart-shell output.

The browser suite checks 18 language/width/surface combinations plus delayed
account change, 30 rapidly changing contexts (one read at a time), stale-response
rejection, near-viewport lazy loading, one retry and stopped retries on service
failure, existing purchase/checkout controls, explicit consent revocation,
no-consent zero telemetry, active filter synchronization, exact one-unit Buy
Again using its existing idempotency handler, actually visible-tab impressions
and simulated bfcache account reset. The bfcache scenario is simulated, not a
Safari bfcache test. Fixtures use a mocked fetch for commerce mutations; **no
real order, payment, email or coupon was created**.

## Explicitly not established

No live deployment, live customer access, real payment/refund lifecycle, gateway
integration, production database/load test, 1,000-concurrent-user claim, actual
Safari/WebKit, CDN-cache routing, Woo currency plugin conversion, country/plugin
restrictions or statistical uplift evaluation was performed. The PHP runtime
was 8.4.23, not every supported PHP/Woo version. Existing third-party hooks and
independent My Qimia summary/AI/cart traffic remain outside this module's request
coalescing. Optional-owner API compatibility was inspected in the supplied My
Qimia 2.9.2 / AI reference packages and exercised through interface doubles;
it was not authenticated against the live installation.

There is no assertion that the site cannot fail or slow down. These are bounded
additional selection paths, failure isolation and local regression checks. The
soft ranking budget cannot make a slow Woo/database/translation/currency hook
complete faster. A working guest/context shelf does not prove optional retained
history is authorized or that a paid-order conversion was attributed.

## Regression-preserved areas

The actual cashback templates/controller/CSS, home template, hero, navigation,
base CSS, verified-filters and goal-engine files match the supplied baseline byte
for byte. Main JS has only scoped adapter integration and the rebuilt compact
bundle; original card styling/purchase behavior is reused. The existing
commerce-continuity file changes only its shelf-dispatch entry; the original
stock-label and exact purchase mutation functions are unchanged. Product
integration relocates the new shelf after related products. New container/tab
styles do not override original card images/prices/buttons.

The manifest lists every changed/new file. Old release documents retain their
historical scope and must not be treated as new 1.13.0 test evidence.
