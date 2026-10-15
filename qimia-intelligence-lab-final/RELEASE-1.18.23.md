# Qimia Intelligence Lab 1.18.23

Baseline: the exact `qimia-intelligence-lab-final-1.18.22.zip` supplied by the owner.
This is a fixes-only release: no new feature, setting, table, task or request.

## Crash fixes

1. **Currency:** stored exchange rates may be a JSON string or an already decoded
   array; both are read, so the storefront no longer stops with a fatal error.
2. **Interests:** a shopper profile with no interest list no longer raises a type
   error while building the customer context.
3. **Mini-cart switch:** turning off the cashback ladder and stack picks (or
   `QIL_BOOST_DISABLE`) now also leaves WooCommerce's own mini-cart list untouched.

## Fixes with Qimia Beauty installed

4. **Search order:** the search dropdown keeps the strict ranking from
   `qil_search_product_ids`; the catalogue no longer reorders it by popularity.
5. **Arabic search:** `/ar/?s=…` shows the search results page, not the Arabic homepage.
6. **Goal cards cache:** the cache key includes the market identity, so record
   filters such as Beauty's split it like every other record cache.
7. **Phone buy bar:** the bottom padding and the hidden theme sticky button apply only
   where the product world renders (`body.qil-product-buybar`), not on product pages
   where `qil_product_intelligence_enabled` is off.
8. **Buy again:** `qil_personal_product_eligible` now receives a fourth argument,
   `$repeat`, that is true for a reorder of the shopper's own purchase. A plugin can
   keep vetoing recommendations and still allow reorders; two-argument callbacks
   behave as before.
9. **Cards outside the Lab root:** sale and flash label motion and the Quick View
   prefetch now work for cards rendered into any `.qil-shell` (brand pages, Beauty
   shelves) and after `QILCards.refresh()`.

## Preserved

Colors, theme, copy, card markup, compare, AI and every public hook are unchanged.
`includes/sections.php` is byte-identical to 1.18.22. `qil.min.js` is rebuilt with
the same minifier as the shipped file (terser 5.51.2); `qil-storefront.min.css`
carries only the two selector changes above.
