# Qimia Intelligence Lab 1.17.0 — traffic-burst protection

Base: Qimia Intelligence Lab 1.16.6.

## Why

A CPU spike to 100% was traced to one minute in which ~66 requests reached
WordPress at once: 36 from `facebookexternalhit` (Meta ad crawler), ~37 on the
ad landing page `/ar/product/stress-less-daily-pack/`, overlapping with
`admin-ajax.php`, `get_refreshed_fragments`, `qil_repeat_context`, Shop,
Checkout and `wp-cron.php`. Average CPU was 13%: the server is not too small,
the work arrived at once and every request ran full PHP.

## What changed (load only — no design, price, stock, cart, checkout or AI change)

| Problem | Change |
|---|---|
| Meta / WhatsApp / Telegram preview crawlers render every ad URL (`?fbclid=…`) in full | New `includes/performance.php`: crawlers get a short-lived copy of the same guest HTML, keyed by path without tracking parameters. One render per URL; parallel crawler requests wait for it. Crawlers are **not blocked**. |
| Crawler bursts over many distinct URLs | Crawler-only render budget (default 30 uncached renders/minute). Above it: `429 Retry-After: 60`. Cached copies are always served. |
| `qil_repeat_context` on every page view | Public inventory labels cached server-side (2 min, product version aware) and per browser tab (2 min). First request deferred until the page has loaded and spread over 0.6–2 s. |
| `qil_personalize` recomputed on every navigation | Server cache: guests share one public result per page context (5 min); signed-in shoppers reuse only their own result, bound to the login session (1 min). Nonces and signed presentation tokens are issued fresh on every response. Guests also keep a per-tab copy; a first-time guest on Home gets the (empty) answer locally without any request. |
| `get_refreshed_fragments` on the first page of every visitor | A small inline gate answers WooCommerce's automatic first-load refresh in the browser when the visitor has no cart cookie. Visitors with a cart and every explicit refresh are untouched. |
| Live search requests per keystroke | Live search starts at 3 characters (1–2 characters use the page's own index), 350 ms debounce, per-page response memory, server results shared between PHP workers (5 min). |
| Product page payload (10 full catalogue records) rebuilt on every uncached view | Cached for 10 minutes under the same price-sensitive identity the homepage payload uses. |
| WP-Cron inside traffic bursts | Crawler and Lab AJAX requests never spawn WP-Cron. Optional "server cron" mode with an automatic fail-safe (visitors take over again if no server run is seen for 20 minutes). WP-Cron overlap lock: two cron passes never run at the same time. |

Settings: **Settings → Qimia Intelligence Lab → Performance**.
Emergency stop for this module only: `define( 'QIL_PERFORMANCE_DISABLE', true );`

## Server steps (recommended)

1. `wp-config.php`: `define( 'DISABLE_WP_CRON', true );`
2. Hosting cron, every 5 minutes:
   `*/5 * * * * cd /path/to/wordpress && flock -n /tmp/qimia-wp-cron.lock php wp-cron.php >/dev/null 2>&1`
3. LiteSpeed Cache → Cache → Advanced → Drop Query String: keep `fbclid`,
   `gclid`, `utm*`, `gad_source`, `srsltid`, `_ga`.

## Files

Added: `includes/performance.php`.
Modified: `qimia-intelligence-lab.php`, `includes/class-qil-admin.php`,
`includes/commerce-continuity.php`, `includes/personalization.php`,
`includes/product.php`, `assets/qil.js`, `assets/qil.min.js`,
`assets/qil-personalization.js`, `assets/qil-personalization.min.js`,
`readme.txt`. CSS, templates and all other files are unchanged.
