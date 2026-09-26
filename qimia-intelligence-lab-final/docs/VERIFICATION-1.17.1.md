# Verification — Qimia Intelligence Lab 1.17.1

Local site: WordPress 6.8.2, WooCommerce 9.9.5, PHP 8.4, SQLite, a test
geo-currency engine (SA → SAR at 9.75, others → OMR; WooCommerce geolocation
from CF-IPCountry). Not the production host, theme or LiteSpeed stack.

- PHP lint / node --check pass; zero PHP notices with WP_DEBUG.
- `qil_personalize` (guest + signed-in, miss + hit), `qil_repeat_context`
  (inventory + Buy Again) and REST search are identical to 1.16.6 apart from
  timestamps, tokens and the resolved country.
- Markets: crawler OM → OMR 11.00, SA → SAR 107.25, AE → separate OMR copy;
  cache hits stay in their own market; visitors, search and personalization
  likewise. Changing the SAR rate to 10 gave SAR 110.00 immediately in the
  crawler copy, search and personalization.
- A shopper with a cart (Woo session): no personalization or catalogue entry
  written to the database.
- Goals: membership identical to the 1.16.6 full scan for all 8 goals;
  adding/removing a category updates membership immediately.
- Browser flow, Home shelf, add to cart (0 → 1 → 1): as 1.17.0.
- 30 parallel Meta crawler requests: 28 cached copies + 2 renders; CPU
  20.5 s (1.16.6) → 14.8 s on this light test theme. The skipped work is the
  template/page-builder render, so the saving grows with page weight.
- Cron guard, overlap lock and crawler render budget: as 1.17.0.

No production deployment, live checkout or production load test was performed.
