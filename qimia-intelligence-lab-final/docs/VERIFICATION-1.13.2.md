# Verification — 1.13.2

## Actual checks

- PHP: 32/32 scenarios executing the supplied, unchanged order eligibility and
  personalization engine with WordPress/WooCommerce contract doubles. Own paid
  orders, account isolation, guest/no-history, refunds, exact unavailable or
  altered variations, current stock/price, active filters and bounded source
  behavior were checked. These are not tests against a live Woo database.
- Browser: 26/26 scenarios running the real CSS, full production minified card
  script, header script and modified personal-tab script in local Chromium.
  English/Arabic at 320, 390, 768, 1100 and 1440px; conflicting theme button fonts;
  all menu entries/nested labels; Buy Again default and delayed arrival; deliberate
  and keyboard tab choices; account change; no history; five-card carousel;
  product/cart counts; coalescing refreshes; exact order/item action; unchanged
  unrelated controls against the 1.13.1 assets were checked.
- Syntax: all 20 PHP and 11 JavaScript files passed their CLI syntax checks.
- Package: output archive is re-opened and every file is compared with the tested
  working tree. Existing unrelated runtime files are compared byte-for-byte to
  the base. SHA-256 file hashes are in BUILD-MANIFEST.json (excluding itself).

## Browser environment boundary

HTTP navigation in this container's Chromium was blocked by administrator policy.
The successful run used an offline about:blank document, injected the real local
assets and supplied an explicit in-memory fetch double. Products, order responses,
page markup and CSS conflict rules are test fixtures. This checks DOM/style/tab
behavior, not actual HTTP credentials, production theme CSS, cache layers or the
live website. Arabic text/direction and computed font-family inheritance were
checked with locally available fonts; the website's font downloads and product
image loading were not verified. Screenshots are local fixtures, not live pages.

A first Arabic assertion expected Latin price digits; the actual existing card
renderer correctly localized them to Arabic digits. The test expectation was
corrected without changing the production renderer. The successful final test
results are included below `docs/verification-1.13.2/`.

## Not executed

Real account/order lookup on the user's installation, live theme/plugin conflicts,
production CDN/cache/login transitions, actual cart/payment, real Safari and
concurrent server-load tests. No universal zero-error or zero-bottleneck claim.

The release adds no query, history scan, request, polling, library or persistence;
it is a scoped CSS change and page-local tab preference. Existing limits remain.
