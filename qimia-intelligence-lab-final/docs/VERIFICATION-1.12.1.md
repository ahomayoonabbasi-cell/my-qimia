# Verification — 1.12.1

Date: 2026-09-20. Tests ran against the delivered plugin source and generated
production assets, in a local controlled environment.

## Passed

- PHP syntax: 18 PHP files (`php -l`).
- JavaScript syntax: 10 JavaScript files (`node --check`).
- CSS syntax: 8 stylesheets parsed with PostCSS.
- Isolated history/inventory logic: 40 assertions. Covered exact purchased
  variations/attributes, distinct flavours, same-selection deduplication,
  exclusions for unavailable/refunded/unpublished selections, limits, customer
  ownership, Arabic shell rendering, and inventory-label date boundaries.
- Isolated product-document integration: 28 assertions across English/Arabic and
  standard-boundary/fallback fixtures. Removing only the inserted shell restores
  the original HTML byte-for-byte. Original forms/gallery/JSON remain unchanged;
  the new section is outside the original form and existing lower sections remain.
- Isolated real cart-endpoint function: 16 scenarios. Covered exact variation and
  simple-product additions at quantity one, any-attribute selections, guest,
  nonce, ownership, item/status, availability, changed attributes, fully refunded
  item, cross-site request, repeat request and active request-lock handling.
- Chromium browser/DOM: 264 assertions across 8 cases: source and production
  assets at English 1440/375px and Arabic 1440/320px. Covered separate populated
  sections, hidden guest/empty/failure states, 8-card cap, exact selected price,
  ordinary cards/catalogue not mutated, original native button DOM identity,
  unchanged original purchase form, shared computed button appearance,
  bottom-corner label placement, no verified-badge collision, horizontal overflow,
  exact request IDs, rapid-click deduplication and absence of script errors.
- Visual review: local English desktop/mobile and Arabic narrow-mobile section
  screenshots inspected. Placeholder product content was used, not live stock.
- Scope: original exact-choice validation, Buy Again cart endpoint and inventory
  truth logic were compared byte-for-byte and remain unchanged.

## Not claimed

No live WordPress/WooCommerce database, active WoodMart/Elementor page, real
customer account, live payment, live currency plugin, Safari/iOS device or
production load test was used. Network responses in browser tests were mocked.
The tests do not establish production compatibility for every third-party
extension, cached page variant or historical order-data anomaly.

The current release supersedes the UI behaviour described in historical 1.12.0
release notes; those files are retained as history, not as current test results.
