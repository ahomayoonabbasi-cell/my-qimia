# Verification — Qimia Intelligence Lab 1.17.4

- PHP syntax: all 22 PHP files passed `php -l`.
- JavaScript syntax: all 14 JavaScript files passed `node --check`.
- Shopper JavaScript assets are byte-identical to the supplied 1.17.3 build.
- All CSS assets and storefront templates are byte-identical to the supplied 1.17.3 build.
- The only shopper/runtime PHP changes outside `includes/performance.php` are crawler-only guards that do not match normal browsers, Instagram in-app browsers, Facebook in-app browsers, cart, checkout or logged-in shoppers.
- Meta preview handling acquires the per-URL lock while the Qimia plugin is loading and allows at most two cold preview renders across different URLs.
- The full crawler cache key ignores a fresh Woo session but retains currency/country/pricing/rate/product-version context.
- Search/index bots keep ordinary server-rendered pages and do not run Qimia personalization/repeat-context or Woo cart-fragment AJAX.
- WP-Cron suppression covers WordPress request spawn paths on init/shutdown/wp_loaded while a direct server-cron heartbeat is healthy; direct `wp-cron.php` runs are not suppressed.
- A minimal bootstrap harness verified that a Meta owner takes the early lock before later Qimia functions are declared, while an ordinary shopper takes no crawler lock.

Production deployment and live Hostinger load behavior are intentionally marked untested until installed on the live server.
