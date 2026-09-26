# Verification — 1.18.3

Method as in VERIFICATION-1.18.0.md: the real plugin over WordPress/WooCommerce
doubles (PHP 8.4, one process per scenario) and Chromium (Playwright) on the real
templates, CSS and JS, English and Arabic, 1440px desktop and 390px phone.

| Suite | Result |
| --- | --- |
| PHP syntax (all PHP files) | 26/26 |
| JavaScript syntax (all JS files) | 16/16 |
| PHP scenarios | 170 passed, 0 failed |
| Chromium | 199 passed, 0 failed |

Details: `docs/verification-1.18.3/php-harness.json`, `docs/verification-1.18.3/browser.json`.

## New checks

Layout (Chromium, every language and viewport)
- The space under the flash stage equals the space above it and the gap the homepage
  has between the categories and the goal engine without the drop (68px desktop,
  40px phone; it was 132px and 74px). Also measured at 1920px (68px) and 1024px
  (49px).
- The flash stage uses no blur filter and no `backdrop-filter`; its light drifts only
  under `(hover: hover) and (pointer: fine)`.

Flash drop (PHP)
- A drop product that sells out and one taken out of the flash sale are replaced
  without any scan of the flash category (no category query in the request); the drop
  keeps its 10 products.
- A product moved into a flash sub-category stays.
- Replacements are re-read live: one that sold out after the last scan is skipped.

Cart suggestions (PHP)
- The list is stored under one entry per market and language; a rebuild after a new
  product version overwrites it (no second entry).
- While another worker holds the rebuild lock, the list comes back at once (under
  0.1 s) from the last build, never empty; after the lock is released, the rebuild
  drops a product that sold out.

Shortcodes (PHP)
- Without the Qimia card renderer on the page both print nothing; with it, each is the
  homepage section inside its own Qimia shell.

Each new PHP check was also run against 1.18.2 and failed there, except the ones
that describe unchanged behaviour (sub-category, live replacements, rebuild after a
version change).

## Not verified here

A live WordPress + WooCommerce + WoodMart + LiteSpeed install and production load.
`qimia.om` is not reachable from the build environment. After updating: purge
LiteSpeed once, then open the homepage on a desktop and a phone as a guest.
