# Qimia Intelligence Lab 1.18.6

Targeted cart-surface redesign only.

## What changed

- **Mini cart recommendation area:** the block starting with “Reach X cashback with one of these” now renders inside its own soft Tiffany panel with a Tiffany heading and white recommendation cards. This visually separates the helper picks from the shopper's actual cart items so the drawer is easier to read.
- **Cart page placement:** the full **QIMIA CASHBACK LADDER** block (ladder + suggested items) now renders **after** the cart totals and checkout CTA, instead of before them. The checkout path remains first; the ladder becomes a secondary assist block below it.

## Unchanged

- Cashback issuer logic, thresholds and messages.
- Product pricing, discount display and stock logic.
- Flash Sale, My Qimia, search, comparison and AI behaviour.
- Mini-cart/cart AJAX updates (still use WooCommerce's existing fragments only).

## Files changed

- `qimia-intelligence-lab.php`
- `includes/commerce-boost.php`
- `assets/qil-boost.css`
- `assets/qil-boost.min.css`
- `readme.txt`

