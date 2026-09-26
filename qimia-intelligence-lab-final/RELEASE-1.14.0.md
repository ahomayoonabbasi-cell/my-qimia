# Qimia Intelligence Lab 1.14.0

Base: Qimia Intelligence Lab 1.13.4 supplied on 2026-09-21.

This release is the storefront-side bridge for the My Qimia 3.1.0 agent-context
contract. It deliberately does not move AI reasoning into the Lab and does not
change WooCommerce commerce authority.

## Canonical context ownership

When My Qimia 3.1.0+ is active, the shared `qimia-shopping-context` WordPress
handle is registered from My Qimia's own asset before its priority-90 storefront
adapter runs. Lab consumes that provider instead of competing with a second
implementation.

If My Qimia is absent or older, Lab uses a bounded current-task fallback. That
fallback carries search/selection/compare IDs only and preserves the pre-existing
`qimia:shopping-context` refresh behavior. It does not create an account profile.

The packaged `assets/qimia-shopping-context.js` is byte-identical to the My Qimia
3.1.0 canonical provider so an old cached direct asset request cannot reintroduce
a divergent contract.

## Recent product views

Lab no longer writes its own `qil-recent-products-v1:*` sessionStorage profile.
For current-tab recent views it reads, in order:

1. My Qimia / `QimiaShopping.recentViews()` when available.
2. My Qimia browser context directly when available.
3. The shared shopping-context recent-view adapter.
4. WooCommerce's browser-local recently-viewed cookie as a fallback hint.

Persistent exact product views for signed-in permitted accounts are consumed
through the read-only `qimia_my_qimia_recent_views_v1` contract. Generic Event
Ledger activity remains a compatibility/relevance source, but only exact
`product_view` rows become Recent View entries.

Search, recommendation, compare, cart and purchase semantics remain separate.
A carted/purchased/recommended product is not silently relabeled as viewed.

## Ownership boundaries preserved

- WooCommerce: product/variation existence, live price, stock, cart and orders.
- My Qimia: account permissions, exact recent views and Event Ledger retention.
- Qimia Intelligence Lab: storefront presentation, recommendation surfaces and
  signed storefront recommendation attribution.
- Cashback remains outside this context bridge.
- Qimia AI/Cloudflare gateway code is not changed by this release.

The signed QIL presentation token and existing best-effort recommendation-event
endpoint are intentionally retained: they write to the My Qimia-owned ledger and
provide validated storefront attribution without adding a second analytics table.
Analytics failure still cannot block add-to-cart.

## Unchanged storefront behavior

The 1.13.4 Home shelf position, card renderer, Buy Again rules, product page
placement, Cart shelf, hero, navigation, verified filters, New Arrival/Back in
Stock labels, cashback presentation, checkout and staging safety guards are not
redesigned in this release.

No database schema migration is required. `QIL_SCHEMA_VERSION` remains 13.

## Deployment

Replace the existing Qimia Intelligence Lab plugin; do not install a parallel
copy. Use My Qimia 3.1.0+ for the canonical agent-context bridge. Clear page/CDN
and asset caches after replacement.
