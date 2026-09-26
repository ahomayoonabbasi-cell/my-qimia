# Qimia Intelligence Lab 1.17.2 — CPU / request finalization

Base: 1.17.1. Presentation and commerce contracts are unchanged.

## Changes
- Meta/preview cold-miss followers never fall through into a duplicate WordPress render. One worker renders; followers serve the finished copy or receive crawler-only 429 while the first worker is still running.
- Crawler render lock extended to 45 seconds; normal shoppers and in-app browsers are not affected.
- `qil_get_catalogue()` public cache default raised from 30 to 90 seconds. Its key already contains product/term/market/pricing versions, so edits still move immediately to a new cache key.
- Catalogue cold misses use three bounded 5-second owner windows before the absolute availability fail-safe instead of duplicating after ~600 ms.
- Guest personalization uses the same single-flight principle with three bounded 2.5-second owner windows before its absolute availability fail-safe.
- Where the unified personalization shelf is present, its existing response now hydrates Buy Again inventory into the shared card/inventory registry. The separate initial `qil_repeat_context` request is therefore omitted. Pages without that shelf keep the existing repeat-context behavior.
- Buy Again unavailability refresh uses personalization OR repeat-context, never both.

## Explicitly unchanged
- CSS and templates.
- Product card markup/visual design.
- WooCommerce cart, checkout, gateway, price and stock authority.
- My Qimia, cashback and AI contracts.
- `wc-cart-fragments` dependency; the existing empty-cart first-load browser gate remains.
