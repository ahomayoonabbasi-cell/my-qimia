# Qimia Intelligence Lab 1.18.5

Base: the supplied 1.18.4 package. This is a targeted presentation/cart fix, not a feature redesign.

## Changes

1. The dedicated homepage renders the existing flash-sale section once **after the goal engine / Show more products**, then the optional member area and the personal shelves. Placement is server-rendered, not a JavaScript move. The hero, category rail, product-selection rules, stock bars, prices, clock and card renderer are unchanged.
2. The end of the goal section fades from white into the same ice colour used behind the flash sale and member/personal area. Spacing below Show more is compact. The dark flash stage remains dark. These overrides are scoped to `#qimia-lab .qil-home-flow`; standalone shortcodes keep their existing layout. There are no new blur effects or background animations.
3. Successful adds on a full cart page remain inline. QIL no longer explicitly forces the theme's after-add drawer action there. A bounded, event-triggered guard also handles a theme action independently loaded by WoodMart or a combined script. It watches only existing drawer/backdrop class attributes, disconnecting within 1.5 seconds, on explicit bag open, or on pagehide. It does not stop the `added_to_cart` event, issue a second add, or issue a second cart/totals-refresh request. The shared backdrop is preserved when another side panel is open. Normal auto-open behaviour outside the cart and explicit bag opening are retained.

Source and distributed `.min` assets are synchronized; the new version changes their cache URLs. No settings, database schema, stock, discounts, cashback issuance, search, comparison or AI logic were changed.

## Update

Keep a backup of the existing plugin. Upload this ZIP through WordPress Plugins > Add New > Upload Plugin, then replace the installed copy. The internal directory is still `qimia-intelligence-lab-final`; do not run two copies side by side. Clear the site's cached HTML and CSS/JavaScript bundles once, and any CDN copies of those pages/assets. Reload the homepage and cart without old browser assets.

No Elementor edits, additional CSS snippet, new scheduled task, or workflow change is required for this patch.

## Verification and limits

PHP syntax: 26 files. JavaScript syntax: 16 files. CSS parsing: 12 files. All passed.
The current local suites passed 64 PHP fixture checks and 261 Chromium browser checks (94 cart-event checks and 167 layout checks). Both source and distributed assets were exercised.

These are isolated tests of actual plugin code using controlled markup/data and WordPress/theme dependency doubles. They are **not** a live WordPress/WooCommerce/WoodMart checkout, Safari test or production load test. No live site was modified. Verify with an actual cart on a staging copy or after a backed-up update, including a suggested-product add, totals refresh, manual bag opening, and checkout navigation.

See `docs/VERIFICATION-1.18.5.md` and its JSON evidence. Earlier release reports included in the package remain historical; they are not additional tests rerun for 1.18.5.
