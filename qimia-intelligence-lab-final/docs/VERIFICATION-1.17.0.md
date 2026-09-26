# Verification — Qimia Intelligence Lab 1.17.0

Scope: load reduction for traffic bursts. No design, price, stock, cart,
checkout, AI, cashback or account logic was changed.

Test site: WordPress 6.8.2, WooCommerce 9.9.5, PHP 8.4, SQLite, Twenty
Twenty-Five, 10 products. This is a local site, not the production host,
theme (WoodMart) or LiteSpeed stack. Full data: `verification-1.17.0/results.json`.

Verified locally, against 1.16.6 on the same site:

- PHP lint and `node --check` pass for every changed file; zero PHP notices
  with `WP_DEBUG` on.
- Minified bundles are rebuilt with the exact toolchain of 1.16.6 (esbuild for
  `qil.min.js`, terser for `qil-personalization.min.js`, both byte-identical
  when applied to the 1.16.6 sources).
- `qil_personalize` (guest and signed-in, cache miss and hit), `qil_repeat_context`
  (inventory-only and signed-in Buy Again) and REST search return the same data
  as 1.16.6, apart from timestamps and per-request tokens.
- Browser flow (new guest from an ad link → product → product → back → Home →
  search → add to cart → product): `get_refreshed_fragments` 1 → 0 on landing;
  revisits send no `qil_repeat_context` / `qil_personalize`; Home shelf renders
  the same Discover / Sign-in tabs and 10 cards with no server request; search
  "wh" sends nothing, "whey" typed quickly sends one request; cart count 0 → 1
  → 1 exactly as before.
- 30 parallel `facebookexternalhit` requests to one product with different
  `fbclid`: 1 full render + 29 cached copies; server CPU 26.6 s → 5.9 s,
  wall time 7.1 s → 2.0 s.
- Crawler render budget: over the limit, uncached crawler URLs receive 429 with
  Retry-After 60; cached URLs and normal visitors are unaffected.
- Crawlers with any cookie, logged-in users, the Facebook in-app browser, HEAD
  misses, 404 and redirect responses are never served or stored as copies.
- WP-Cron: not spawned by crawler or Lab AJAX requests; server mode suppresses
  visitor cron only while a server run was seen in the last 20 minutes; a second
  concurrent cron run exits before init; a stale lock is cleared.
- Performance settings tab renders and saves through options.php.

No production deployment, live checkout or production load test was performed.
