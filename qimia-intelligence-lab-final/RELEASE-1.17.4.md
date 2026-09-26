# Qimia Intelligence Lab 1.17.4 — Production Performance Final

Base: the supplied 1.17.3 plus the proven 1.17.3 performance hardening.
Storefront CSS, templates, product logic, pricing, stock, cart, checkout, My Qimia, cashback and AI contracts are unchanged.

## CPU / request hardening

- True early single-flight for Meta/preview crawler bursts, before WooCommerce/theme/page-builder work.
- One render per normalized URL and edge country; `utm_*`, `fbclid`, `gclid` and other tracking-only parameters do not create new crawler work.
- Maximum two simultaneous cold crawler renders across different URLs. Duplicate cold followers receive crawler-only `429 Retry-After: 60`; warm followers receive the cached copy.
- Market-aware full crawler cache key excludes the ephemeral Woo session id but preserves currency, country, pricing/tax context, exchange-rate settings, user state, product version and plugin version.
- Non-cacheable/failed crawler pages use a cooldown marker so they cannot stampede the origin.
- Atomic per-minute crawler render budget when no persistent object cache is available.
- Search/index bots keep the same server-rendered HTML for SEO, while shopper-only Qimia JS, personalization, repeat-context and Woo cart-fragment requests are suppressed.
- Stale bot calls to `admin-ajax.php` or `wc-ajax` are answered with `204` before Woo/theme callbacks.
- Direct Hostinger/server WP-Cron heartbeat suppresses duplicate request-triggered `_wp_cron`; overlap lock prevents concurrent cron passes. If the server cron disappears, the 20-minute fail-safe restores normal request spawning.
- `admin-ajax.php` diagnostics are sampled only 1 in 25 requests, capped at 200 samples/day.

## SEO safety

- No `noindex`, redirect, canonical, sitemap, product schema, title/meta or content changes were introduced on production.
- Google/Bing/Apple/OAI and other index crawlers still receive ordinary public server-rendered pages. Only private/interactivity AJAX and shopper-only scripts are skipped for bots.
- Facebook/Instagram in-app browsers are treated as real shoppers and are not classified as preview crawlers.

## Operational note

A real server cron is already visible in production logs. Defining `DISABLE_WP_CRON` in `wp-config.php` remains the cleanest WordPress setup, but the plugin also detects a live server-cron heartbeat and suppresses duplicate visitor spawning automatically.
