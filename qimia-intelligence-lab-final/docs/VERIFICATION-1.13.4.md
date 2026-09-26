# Verification — Qimia Intelligence Lab 1.13.4

## Scope checked

1. `templates/home.php` places the personal shelf immediately after the Goal
   matcher and immediately before the public collections section whose heading
   is “What Qimia shoppers choose most.”
2. `QIL_Personalization::context()` bounds recent hints to 12 numeric IDs.
3. A guest/current-session mock with one recent product in category `creatine`
   returned a `recent` group containing exactly that product and a separate
   six-item `categories` group, without fabricating a precise-interest group.
4. A signed-in mock with one owned completed-order product returned exact
   `again` for that product and six different same-category candidates; the
   owned product did not leak into the category tab.
5. Category candidates and precise-intent candidates share one bounded catalogue
   pool; full product hydration is capped at 24.
6. JavaScript has a current-tab recent fallback plus the existing Woo cookie
   hint. Explicit denial clears current-tab recents and the client continues to
   suppress non-Buy-Again personal groups for that page.
7. Product cards still render through `QILCards.render`; no product-card font,
   price, button or badge selector is overridden by the new CSS.
8. All PHP and JavaScript files pass their language syntax checks; the changed
   CSS has balanced braces.

## Counts

- PHP syntax: 20/20 files
- JavaScript syntax: 11/11 files
- PHP behavioral harnesses: 2/2
- Behavioral assertions inside harnesses: 6/6
- Home placement assertion: 1/1
- CSS structural check: 1/1

## Boundaries

No live production deployment, live checkout/payment, real Hostinger concurrency,
Cloudflare-cache, or device-Safari test was run here. The package preserves an
emergency rollback path to 1.13.3 because there is no schema/data migration.
