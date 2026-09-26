# Qimia Intelligence Lab 1.17.3 — crawler burst hardening

Base: 1.17.2 (as deployed on qimia.om). Presentation, prices, stock, cart,
checkout, My Qimia, cashback and AI contracts are unchanged. CSS and
templates are byte-identical to 1.17.2.

## Why

The live log of 2026-09-25 showed the CPU spike at 10:55 UTC (14:55 Oman)
under 1.17.2: about 60 `facebookexternalhit` requests for
`/ar/product/stress-less-daily-pack/` in 12 seconds, all answered with a full
render (5–10 s each), and not a single crawler 429. Cause: when a crawler
render could not be stored, 1.17.2 left a "skip" marker, and every crawler
request that saw the marker rendered the page with no lock and no wait. Its
render budget was a read-then-write transient, which a simultaneous burst
reads as the same value, so it did not trigger either.

## Changes (`includes/performance.php`, plus two JS guards)

| Problem | 1.17.3 |
|---|---|
| Pages that could not be stored were rendered by every parallel crawler request | One crawler render per URL at a time for every page. A page that cannot be stored is rendered at most once every 10 s; the rest of the burst gets a crawler-only `429 Retry-After: 60`. |
| Followers paid the whole WordPress bootstrap (~0.6 s) just to receive a 429 or a cached copy | The render lock is per URL and is checked while plugins load. Cached copies are served before WooCommerce and the theme load (`X-QIL-Crawler-Cache: HIT-EARLY`) via an alias that points to the exact market copy last served to the same network block (/48 IPv6, /24 IPv4, plus Cloudflare country), and only while the product version is unchanged. |
| Render budget not enforced during a burst | Atomic per-minute counter (one SQL increment) when no Redis/Memcached is present. |
| A Woo session opened during a cookieless crawler render discarded the copy | The session id is ignored for crawler copies. Currency, country, decimals, tax/pricing context, exchange-rate settings and user are still compared; any change still discards the copy. |
| `/page` and `/page/` rendered separately | Share one key and one lock. |
| Crawler hung up, so the copy was never stored | The render finishes (`ignore_user_abort`) and fills the cache. |
| No way to see why copies were not stored | Performance tab lists the last 25 reasons (e.g. `market-changed:pricing`, `status-404`, `incomplete`). |
| `admin-ajax.php` POST after almost every page view (47 bytes, 0.5–1.1 s) — not from this plugin | Performance tab samples 1 in 5 admin-ajax requests and lists the `action` names, so the plugin behind it can be identified. Nothing is blocked. |
| Googlebot / Bingbot / GPTBot etc. triggered `qil_personalize` and `qil_repeat_context` on every crawled page | Skipped in the browser for known crawler user agents. Shoppers, including Instagram and Facebook in-app browsers, are unchanged. |

Response header `X-QIL-Crawler-Cache`: `HIT-EARLY`, `HIT`, `MISS`, `SKIP`, `BUSY`.

## Measured locally (PHP built-in server, 10 workers, 30 parallel requests)

| Case | 1.17.2 | 1.17.3 |
|---|---|---|
| Non-storable page, first burst | 30 full renders, CPU 18.2 s | 1 render + 29 × 429, CPU 14.1 s → 8.6 s with early answers |
| Same page, second burst | 6 renders + 24 × 429 | 0 renders, 30 × 429 (early), CPU 5.3 s |
| Cacheable page, warm | 30 × HIT at wp_loaded, CPU 12.5 s | 30 × HIT-EARLY, CPU 5.3 s |
| SA vs OM crawler | separate copies | separate copies (SAR / OMR verified), also on the early path |

Browser checks: no JavaScript errors; add to cart, cart count and personalization
unchanged for shoppers; Googlebot page view makes no `qil_*` AJAX calls.

## Emergency stop

`define( 'QIL_PERFORMANCE_DISABLE', true );` in `wp-config.php` switches off the
whole performance module (crawler cache, cron guard, fragments gate).
