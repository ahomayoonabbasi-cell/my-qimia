# Verification — 1.13.1

## Executed here

- PHP contract tests: 52/52 passed. Actual `QIL_Personalization` and actual exact-repeat/inventory code from the package; synthetic WordPress/WooCommerce products, orders, customer/consent state, storage, catalogue and price adapters.
- Browser tests: 46/46 assertion scenarios passed across local batches in Chromium 144. Actual packaged minified `qil.min.js`/`qil.min.css`, personalization JS/CSS and shopping-context script. Actual PHP-generated personal shelf HTML and synthetic payloads. Browser navigation is prohibited by the test environment's policy; this was left unchanged. Assets were inlined into an offline document, fetch was explicitly mocked, and synthetic SVG images were supplied through a local test handler. This is not an HTTP/server end-to-end test.
- Syntax: all 20 PHP and 11 JavaScript package files passed `php -l` / `node --check`.
- Visual inspection: English and Arabic desktop fixtures and Arabic mobile fixture. Images/products/accounts are test fixtures, not customer records or live catalogue observations.
- Package integrity: ZIP extraction and file hashes checked against the tested working files. `BUILD-MANIFEST.json` lists changed, added and unchanged files relative to 1.13.0.

## Assertions covered

Desktop Home/Product in English/Arabic at 1025, 1100, 1280, 1440, 1920px: exactly five full cards when six exist; one horizontal row; no document overflow; sixth reachable with Next; Previous restores start; opposite RTL scroll direction and correct edge-disabled buttons. Counts 1/4/5/6 do not add filler or duplicate cards. Narrow widths 320/390/600 retain two columns; 768/980 retain three; 1024 retains four. Other rails and original product quantity/variation/button/checkout fixture controls remain unchanged. PDP has one personal section below related products. Cart remains capped at four. Tabs, keyboard focus, scroll reset, empty results and read failures were checked.

PHP checks include owned qualifying orders only, account isolation, refunds, stock and live price changes over an existing cache, unavailable/disabled/incomplete exact variations, no flavor substitution, explicit consent boundaries, combined verified filters, eligible value ordering before cutoff, variation view recency, exclusion of current/related/cart/removed products, no cross-tab duplicate parents, bounded source reads, cache locking/invalidation and signed presentation ownership/session boundaries. Inventory regression checks include a 20-day-old new product and variation-specific restock state.

Client behavior tests cover a 30-change burst with one in-flight recommendation read, one bounded automatic retry, pending refresh after a short hidden interval, immediate private shelf clearing on account transition, and actual shared Buy Again button double-click protection. Browser purchase responses were mocked; these were not real purchases.

## Reproduced before / corrected after

The same PHP suite on 1.13.0 passed 49/52, exposing the variation timestamp and two value-before-cutoff defects. The hidden-tab test also reproduced an unresumed pending refresh before the JS correction. All these specific regressions passed after correction. Development-time test runner navigation restrictions, time-limited batches and fixture-format issues were handled in the local harness, not by disabling security or modifying unrelated production code.

## Not executed / not established

No live deployment, real WooCommerce database, gateway capture, email delivery, actual Woodmart integration, real customer account, production currency plugin, CDN cache traversal, Safari/iOS browser or production concurrent-load test was performed. No guarantee of zero defects, zero server overhead, or absence of every possible production bottleneck is made. Native card/rail source and unrelated runtime files are unchanged, but local fixture coverage is not a substitute for checking the installation itself.

Current limits remain 48 candidates, 20 hydrated products, six rows per Home/Product tab, four Cart rows, and the existing bounded order source. No request is made for additional catalogue pages on carousel arrow clicks.

Machine-readable results are under `docs/verification-1.13.1/`. Prior release verification documents remain historical records, not new tests of this release.
