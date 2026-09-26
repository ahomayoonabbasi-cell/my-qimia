# Qimia Intelligence Lab 1.12.0 — truthful labels and exact-item Buy Again

Release date: 2026-09-20. Replaces the supplied Lab 1.11.6. The ZIP keeps its original plugin folder and main PHP file identity. Install as a replacement, never as a parallel Lab plugin.

## Product labels

New Arrival / وصل حديثاً: available, visible, published, purchasable products with a real product/parent image and positive price. The default window is 45 days from publication. Existing products use their stored publication date; first publication is recorded for new items and the old date is preserved before subsequent edits. Editing price/title or republishing does not reset a recorded date.

Back in Stock / متوفر من جديد: the default window is 14 days from an observed `_stock_status` transition from `outofstock` to `instock`. Existing stock is not retrospectively assumed to have returned. Initial imports, unknown previous state, unchanged status, and backorder-to-stock changes do not create a restock label. Drafts and unavailable products are not promoted. A variation uses its own restock event; one flavour returning does not mark the entire parent as restocked unless the parent's own status actually transitions.

The existing Lab settings page contains **Product labels & Buy Again**. Both windows accept 0–90 days; 0 disables the relevant label. Direct SQL stock writes bypassing WordPress/WooCommerce metadata hooks cannot be observed. Historical stock movements before installation are not reconstructed.

## Exact-item repeat purchase

Signed-in customers see Buy again / اشترِ مجدداً on eligible product cards and a compact homepage rail. Read scope: their own latest 30 Processing/Completed orders, up to 24 unique parent defaults and eight homepage cards. No email-based guest-order guessing, customer history database or new account is introduced.

The default is the latest purchased selection for a parent product. The old order line, parent, variation and every stored attribute must still match; there is no automatic replacement flavour, size or wildcard default. Fully refunded lines and invalid/deleted/disabled selections are excluded. A still-valid but unavailable previous flavour is not silently replaced with an older one. The existing options flow remains available.

A click adds **one unit**, not the previous quantity or the entire order, at WooCommerce's current price. It does not submit an order, charge a payment, change stock manually, or force checkout. Exact variant price and selection are shown together; normal parent pricing remains available in Change options/Compare. Existing add-to-cart validation, order-again item-data filters, stock limits and mini-cart events remain in the path.

The new action reuses the existing Qimia gradient, border, shadow, radius, typography and left-to-right sheen. It has no hover lift and a minimum 44-pixel touch target. A native variable card containing an explicit variation selector is not overridden.

## Privacy and failure behavior

Public cached HTML carries only an empty personal-section shell. Current-account history and the action nonce are delivered through same-origin, no-store WooCommerce AJAX requests. No order history is put into localStorage/sessionStorage, the public catalogue, AI context, or marketing telemetry. Private controls are cleared on logout/pagehide and refreshed after a restored page or a sufficiently long hidden-tab interval.

The mutation requires a logged-in session, nonce, current order ownership and an eligible selection rechecked server-side. The client does not submit a trusted price/product/quantity. Rapid repeated clicks are blocked; ambiguous network retries reuse a request key. A compact 10-minute receipt and short per-session lock reduce duplicate additions. A replay renders current cart fragments, not stale cached HTML. A failed response never displays success. After a connection error the customer is asked to check the cart before retrying; this is not an unlimited exactly-once distributed transaction guarantee.

There is no new cron task, external service or continuous polling. Public inventory IDs are bounded at 64 per request. Later catalogue insertions use inventory-only refreshes without reading order history again. Account changes and logged-in rendering depend on the live installation's cache/cookie configuration; test the deployment checklist below.

## Preserved scope

The approved hero, header assets, My Qimia workspace/plugin, customer-experience module, AI connection, cashback module, payment/checkout rules, promotions engine, SEO integrations and inventory values were not rewritten. Existing optional My Qimia hooks remain. The added purchase adapter reads canonical WooCommerce orders; it does not replace My Qimia's records or services.

## Installation and deployment checks

Back up the database and current plugin directory. In WordPress, upload this ZIP and replace the existing Qimia Intelligence Lab. Keep the separate My Qimia plugin unchanged. Do not delete the old plugin first or install a second copy. Purge cached homepage/HTML and CSS/JS in the site's active caching layers after replacement.

Before production rollout, test in staging with a real signed-in customer who has an eligible simple-product order and a variable-product order. Confirm that a single click adds the correct one-unit flavour/size, the current price and cart fragments agree, and normal Add to cart / Change options / checkout still work. Test an unavailable previous option, a guest window and a second account. Confirm cached anonymous HTML never exposes the first customer's choices. Use a dedicated staging product to test stock transitions; do not change real store inventory solely to create labels.

The new endpoints are `wc-ajax=qil_repeat_context` and `wc-ajax=qil_buy_again`. They must remain same-origin and uncacheable under any custom Cloudflare/worker rules. If the private endpoint fails, ordinary Add to cart/options remain available. Product add-on/minimum-quantity plugins, WoodMart fragments, currency conversion, the My Qimia companion and the live POS sync need deployment-specific integration checks.

## Verification scope

All 28 PHP/JavaScript files passed syntax checks. The new server module passed 63 isolated logic assertions and 21 endpoint scenarios using WordPress/WooCommerce test doubles. Browser checks used the actual source and production JS/CSS in a local inline DOM with mock responses, not a live site: 72 checks passed on each asset set, covering desktop 1440px, mobile 375px, Arabic RTL 320px, guests, API failure, exact-price display, native-card restoration, duplicate clicks, ambiguous retries, option selection and private-state clearing. The screenshot fixtures deliberately use placeholder products, not a real catalogue.

No live WordPress installation, real checkout/payment, Cloudflare cache, Safari/iOS device, email flow, AI service, POS import or production load test was performed. Passing isolated checks does not certify those integrations. See the accompanying verification report and BUILD-MANIFEST.json for the precise package changes.
