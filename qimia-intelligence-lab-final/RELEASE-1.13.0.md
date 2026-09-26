# Qimia Intelligence Lab 1.13.0 — unified shopping shelves

Base: the supplied **1.12.2 home-cashback-final** package. Replace the existing
plugin; do not install a second copy. Plugin directory and entry point are unchanged.

## Implemented

Home reuses the existing Buy Again placement for one tabbed collection: actual
previous purchases, actually viewed products, and related category selections.
The product-page collection follows Qimia's related products and moves after a
native theme related-products block when one is present. Cart uses its own
restrained collection, maximum four relevant products, after the native cart;
classic WooCommerce and Cart Block render adapters are registered. A duplicate
compact My Qimia product shelf is hidden only after the new shelf has content;
My Qimia account, order and cashback tiles remain visible.

Every new card is rendered by the original QIL `productCard` function. Standard
Add to cart, quantity, variation controls, current price rendering and the exact
Buy Again mutation handler remain the purchase authority. No autonomous cart
additions, payments, flavour substitution or fabricated browsing history.

The selector consumes current shopping context plus optional activity from the
existing QH_Account owner. Search/compare/selection updates refresh the relevant
shelf, with decayed recent interest and a limited impression fatigue penalty.
Current products, existing related cards, cart products, removed cart items and
duplicate parents are excluded. Buy Again uses the actual previous variation,
its saved attributes and its current live price/stock; refunded and invalid
owned order lines are excluded. The server, not the browser, resolves account ID.
The existing three verified filters are applied as an AND condition. Per-serving
repeat prices use the exact purchased option rather than the cheapest sibling.

## Privacy and ownership

Buying again from one's own orders does not require optional activity consent.
Retained browsing and broader behavioral personalization require the existing
My Qimia QH_Account schema, supported methods, permitted environment and the
customer's explicit optional-personalization choice. An absent, disabled or
unpermitted owner degrades to own-order/contextual selections, not a fabricated
history. Guest selections use current context and do not get a new tracker.
This release does not import private health, food, photo or chat text into sales.

Optional signed presentation/click/visibility events go to the existing ledger;
no extra authoritative ledger, table or background job is introduced. No raw
search or chat text is recorded by this adapter. Existing Qimia AI Event Ledger
remains the reporting owner. Visibility reports are best-effort browser intent,
not proof of attention, purchase or incremental revenue. Only a decision already
persisted by that owner can be attached to a cart item. Early clicks, blocked
requests and missing attribution remain unknown. The new purchase-path adapter
never waits for telemetry or writes a ledger decision synchronously.

## Bounded additional work

- One recommendation read in flight per page. Context bursts are coalesced;
  superseded responses are discarded. Loading starts near the shelf. No polling.
- Maximum 48 shortlist candidates, 20 hydrated product records, six displayed
  items per home/product tab and four total cart items.
- Own-order references: at most 30 recent processing/completed orders and 240
  visited lines, 24 compact reference rows, 10-minute private cache. An order's
  WooCommerce `get_items` can itself materialize more lines; this is not a hard
  bound on arbitrary third-party Woo internals. Warm reads still validate exact
  order ownership, attributes, current price and stock.
- Per-account reference rebuilding uses a non-waiting short lock, not a global
  shopper lock. Order changes invalidate references. Public category candidates
  cache IDs only for five minutes; no public customer/price HTML cache.
- Existing optional activity reads at most 100 events within 30 days. A 1,100ms
  soft selection budget can stop further ranking. It is **not a guaranteed full
  HTTP latency limit**; database, catalogue hydration and third-party hooks can
  take longer. Value-verification work has a separate 60-child budget.
- Browser request timeout 8.5 seconds, bounded retry on non-abort read failure;
  telemetry queue at most 50 in-memory entries, batches of 10, bounded retries.
- No LLM calls, remote API dependency or n8n requirement added by this adapter.
  Existing shared card, currency, translation, Woo, My Qimia and AI integrations
  retain their own behavior; this does not remove their independent workload.

## Controls

WordPress Settings -> Qimia Intelligence Lab -> **Shopping Experience**.
The real enable switch defaults on. The related-selection rollout defaults to
100% for signed-in accounts. At 0%, the related-selection tab is suppressed for
that cohort but Buy Again and Recently Viewed remain. Guests are not part of
that account experiment. This is a stable rollout/control assignment, not an
invented conversion uplift report. Saved explicit disabled settings are retained.

Emergency stop: `define('QIL_PERSONALIZATION_DISABLE', true);` in wp-config.php.
Disabling shelves restores the baseline Buy Again fallback; it does not delete
orders, coupons or optional history. Clear page/CDN/asset caches after changing
these controls. `QILPersonalization.diagnostics()` exposes page-local counts and
elapsed server-selection time; it is not a host-wide monitoring dashboard.

## Installation and rollback

Back up files and database. Upload this ZIP as a replacement of the current Qimia
Intelligence Lab, with the same plugin directory. Do not delete the plugin first
or create a second Lab installation. Clear cached HTML and CSS/JS, including any
CDN cache. No new API key, workflow or companion plugin reinstall is required.
Test signed-in/guest, both languages, original variation purchase, own purchase,
cart/checkout and currency switching against the real site before general release.

For rollback, replace this ZIP with the retained 1.12.2-home-cashback-final ZIP
and clear those caches. Existing commerce/health/cashback databases are not
migrated by this release; new small reference transients expire automatically.
Historical optional ledger events remain governed by the existing owner.

## Verification boundary

Read `docs/VERIFICATION-1.13.0.md`. This is a locally tested deliverable, not a
claim of live-site, real-payment or production-load acceptance. Genuine product
eligibility restrictions from destination plugins require that integration;
`qil_personal_product_eligible` is the supported veto and native Woo purchase
validation remains authoritative. Card-facts/stock/currency accuracy still
requires accurate live Woo data. Older items beyond the bounded recent-order
window are not claimed to be a complete purchase archive.
