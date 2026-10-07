# Verification — Qimia Intelligence Lab 1.18.9

## Baseline and scope

Input: `qimia-intelligence-lab-1.18.8.zip` from this conversation.
The change is limited to the compact mini-cart cashback header and the inherited
Added-badge/remove-button overlap in the actual mini-cart lines.
All JavaScript, homepage/template, pricing, inventory, issuer and AI files are
byte-identical to that baseline. Within `commerce-boost.php`, the existing
ladder calculation is unchanged; a separate compact renderer is selected for
the mini cart. The full cart renderer produces byte-identical output in the
controlled scenarios below.

## Executed checks

- PHP syntax: 26 files passed.
- JavaScript syntax: 16 files passed; none changed.
- CSS parser: 12 files passed.
- Source/distributed CSS: PostCSS AST equality and source/minified rendering
  checks passed; value syntax was preserved, rather than stripped with regex.
- PHP behavioural assertions: 1776 / 1776 passed. Real plugin
  classes ran with local doubles for WordPress/WooCommerce/issuer dependencies.
  Scenarios cover empty/zero, each inclusive band boundary and its successor,
  the highest tier, the current-vs-next amount, OMR at 2 and 3 decimals, six GCC
  currencies, unknown-rate suppression and English/Arabic. Rates and policies
  in these tests are synthetic fixtures, not verified live-store settings.
- Chromium 144.0.7559.96: 233 / 233 assertions
  passed. Controlled mini-cart DOM at 280, 320, 340, 390, 430 and 768 CSS pixels,
  English/Arabic and source/minified CSS. Header edge alignment, square corners,
  primary amount emphasis, compact height, horizontal overflow, large formatted
  values, disabled compact shimmer and server-markup replacement were checked.
- Rendered local English/Arabic mini-cart screenshots were visually reviewed.
  The actual-cart badge is now in the quantity row, not the Remove corner.

## Important limits

This is NOT a live WordPress/WooCommerce/Woodmart session test. Native theme DOM
and responses in the browser tests are controlled fixtures. Fragment replacement
was simulated; no real purchase, coupon issuance, wallet redemption or payment
was performed. The public homepage could not be fetched from this environment.
Safari/WebKit and production server load were not tested. These results are not
a guarantee for every active plugin/theme combination.

The WooCommerce mini-cart hook sequence was checked against the official source:
https://raw.githubusercontent.com/woocommerce/woocommerce/trunk/plugins/woocommerce/templates/cart/mini-cart.php
That source does not establish which template version the production theme uses.

## Deployment

Replace the existing Qimia Intelligence Lab plugin with the complete 1.18.9 ZIP.
Clear cached CSS in the active page/CDN cache and refresh the browser. Check the
mini cart in English and Arabic: one real item, adding/removing across a cashback
boundary, the highest tier, the empty state, and Checkout/Remove accessibility.
No settings migration or cashback-workflow change is included.
