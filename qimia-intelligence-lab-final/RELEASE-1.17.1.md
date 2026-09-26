# Qimia Intelligence Lab 1.17.1 — combined performance release

Base: 1.17.0 (traffic-burst protection) + the safe parts of 1.16.7.

## Taken from 1.16.7
- Short-lived `qil_get_catalogue()` cache with a cold-build lock.
- Per-serving price cache for variable products.
- Search result-ID cache.
- Targeted goal membership lookup (no full-catalogue scan), invalidated on
  category / tag / purpose-attribute / publication changes. Verified identical
  to the 1.16.6 full scan, including child categories, tags, custom purpose
  attributes and hidden products.

## Not taken from 1.16.7
- Removing the forced `wc-cart-fragments` enqueue: the dedicated homepage
  mini-cart and QIL's cart recovery depend on it. 1.17.x instead skips only
  WooCommerce's automatic first-load refresh for visitors without a cart.
- Removing `qil_repeat_context` entirely: it keeps New Arrival / Back in Stock
  labels truthful on cached pages. 1.17.x caches and defers it instead.

## Multi-country / multi-currency correctness
Every cache that holds a price or availability is keyed by one market identity
(`qil_perf_market_identity()`): currency, country, price decimals, role/tax
context, the geo-currency engine's settings (exchange rates), user and Woo
session. Consequences:
- A crawler or visitor resolved to SAR/Saudi Arabia never receives an OMR/Oman
  copy (and a different country with the same currency gets its own copy).
- Changing an exchange rate or tax rule invalidates immediately.
- Shoppers with an account or Woo session (cart) only reuse their own entries,
  kept in a memory object cache; they are never written to the database.
- The crawler copy is chosen after WooCommerce and the currency engine resolve
  the visitor (at `wp_loaded`), and a copy is discarded if the currency,
  country or session changed while it rendered.
- Browser-side copies are scoped by currency and country.
- Crawler copy lifetime default reduced to 5 minutes.
