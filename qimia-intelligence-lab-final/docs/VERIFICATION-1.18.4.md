# Verification — 1.18.4

Method as in VERIFICATION-1.18.0.md: the real plugin over WordPress/WooCommerce
doubles (PHP 8.4, one process per scenario) and Chromium (Playwright) on the real
templates, CSS and JS, English and Arabic, 1440px desktop and 390px phone.

| Suite | Result |
| --- | --- |
| PHP syntax (all PHP files) | 26/26 |
| JavaScript syntax (all JS files) | 16/16 |
| PHP scenarios | 170 passed, 0 failed |
| Chromium | 211 passed, 0 failed |

Details: `docs/verification-1.18.4/php-harness.json`, `docs/verification-1.18.4/browser.json`.

## New checks

Cart page (Chromium)
- Phone: the band sits after the cart totals (ladder, picks, totals and checkout
  first). Desktop: the band stays under the cart table.
- Phone, after WooCommerce replaces the cart form (its own update, simulated with the
  page's fresh markup and `updated_wc_div`): exactly one band, rendered, still after
  the totals.
- Arabic: the ladder, its steps and the cards contain no Arabic-Indic digits.

Flash drop (Chromium, every language and viewport)
- Every card has one discount badge, no role label, no label roll (no animation,
  full opacity).
- The clock label reads "DROP ENDS IN" / "تنتهي المجموعة خلال".
- Arabic: the whole section uses Western digits; the low option reads
  "بقي 3 فقط من Chocolate".

Mini cart (Chromium) and PHP
- Arabic ladder and picks use Western digits ("3 ر.ع").

## Not verified here

A live WordPress + WooCommerce + WoodMart + LiteSpeed install and production load.
`qimia.om` is not reachable from the build environment. After updating: purge
LiteSpeed once, then open the homepage and the cart on a desktop and a phone.
