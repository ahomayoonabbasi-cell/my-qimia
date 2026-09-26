# Qimia Intelligence Lab 1.13.4

Base: `1.13.3-no-header-my-qimia-button`. This release changes only the unified
personal shopping shelf and its exact Home placement. It does not change the
header/button removal, cashback, hero, verified filters, inventory labels,
checkout, normal product cards, My Qimia data, or the public collection logic.

## Home shelf

- Home order is now Goal matcher -> private personal shelf -> “What Qimia
  shoppers choose most.”
- Useful tabs are generated only when they contain eligible products: Buy again,
  Recently viewed, More from your categories, Related to your interests.
- Buy again remains exact order/variation continuity and uses live stock/price.
- Recently viewed merges the existing permitted Event Ledger, WooCommerce's own
  recently-viewed cookie when present, and a current-tab `sessionStorage` list.
  Current-tab storage is capped to 12 public product IDs, account/session-bound
  by the existing WordPress nonce fragment, and is not a durable customer
  profile. An explicit personalization denial clears this current-tab list.
- Category continuity uses public product taxonomy from verified viewed products
  and the customer's own prior purchases. It does not ingest health, food-photo,
  private chat, age, body data, or inferred medical needs.
- Current task/search/compare intent remains a separate higher-precision layer.
  Products are not duplicated between visible tabs.

## Performance boundaries

The existing single in-flight request remains. The public category pool is still
one bounded WooCommerce query (up to 36 public IDs, 5-minute public ID cache),
ranking stays at 48 candidates, and full card hydration is capped at 24 records.
Each tab displays at most six products; the existing desktop rail shows five at
once and the sixth remains in the same carousel. Prices, stock and variation
eligibility are never taken from that public ID cache.

## Layout

The personal shelf uses the same QIL card renderer and therefore the same card
font sizes, Arabic/English typography, buttons, labels and responsive card CSS as
other Qimia rails. Only the personal section rhythm is adjusted: its redundant
collection-block top margin is removed, the goal-to-shelf gap is reduced, and
the following public collections receive a controlled standard top gap.

## Verification

All 20 PHP and 11 JavaScript files pass syntax checks. Two isolated PHP contract
harnesses passed: (1) exact recently-viewed + category continuity, and (2)
verified Buy Again + category continuity from an owned order. Static ordering and
CSS-balance checks also passed. These are local tests with test doubles, not a
live qimia.om database, live payment, Safari-device, CDN, or production load test.
