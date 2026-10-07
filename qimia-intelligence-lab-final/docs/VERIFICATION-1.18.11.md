# Verification — Qimia Intelligence Lab 1.18.11

## Scope and baseline

Input: `qimia-intelligence-lab-1.18.9(1).zip`.
SHA-256: `d11196692c9a4c59137313b6843f4241d23ab7c02508d757957618df4cffd38b`.

Only four runtime files differ: the plugin version entry, `includes/commerce-boost.php`,
`assets/qil-boost.css` and its distributed minified counterpart. Readme/release/
verification/manifest files are also updated. The PHP diff is limited to compact
header presentation and an optional mini-cart pick layout; no commerce calculation
or AJAX hook changed. The actual-cart item CSS section is unchanged.

## Executed checks

- PHP syntax: **26 files**, all passed (PHP 8.4.23 CLI).
- JavaScript syntax: **16 files**, all passed. Every JS file is byte-identical to the supplied baseline.
- CSS parsing: **12 stylesheets**, all passed.
- The modified source and `.min.css` have equivalent PostCSS syntax trees.
  Minification removes comments/inter-node whitespace, not CSS value or selector
  tokens. Browser layout was checked against both distributed and source CSS.
- PHP controlled fixtures: **3592 assertions passed**, zero failed.
  Actual QIL_Boost/QIL_Cashback classes ran with isolated WordPress/WooCommerce/
  issuer doubles. Current/next reward boundaries, empty states, top band, OMR
  precision, six GCC currency formatters plus an unavailable currency and both
  languages were exercised. Rates and policies were synthetic test inputs, not
  claims about current store settings. Full-cart ladder/pick HTML and underlying
  ladder state matched the baseline in these scenarios. Native link attributes,
  quick-view records, prices and full names were compared before/after.
- Chromium **144.0.7559.96**: **1922 assertions passed**, zero failed.
  Controlled mini-cart fixtures use the actual PHP output and shipped plugin
  CSS/JavaScript, with theme-like structure/styles. Widths: 280, 320, 340, 390,
  430 CSS pixels and a 340px drawer on a 1440px desktop viewport; home/inner
  states; English/Arabic; source/minified styles. Checks cover visible coin,
  header edge alignment, label/amount bounds, title width, contained images,
  separated action row, touch targets, zero horizontal overflow, native item
  appearance, consistent shipping colors and unchanged progress width.
- Existing Options and Added handlers were exercised with controlled QILCards /
  jQuery event doubles. Replacement-fragment fixtures retain the layout without
  introducing new requests. Shipping hidden/success states and no styling leak
  onto a non-mini-cart shipping bar were checked.
- Local English/Arabic previews were inspected visually.

## Limits

These are **not** live WordPress/WooCommerce/WoodMart integration tests. The
complete production theme and its active inline CSS were unavailable to the
container (network/DNS fetch failed). Theme-shaped fixtures can verify the new
component layout and reproduce narrow columns; they cannot certify every live
plugin/style interaction. Native checkout/payment, actual AJAX network responses,
server CPU and Safari/WebKit were not tested. Nothing was installed on production.
No universal error-free claim is made.

The native hook structure was referenced from the official WooCommerce template:
https://raw.githubusercontent.com/woocommerce/woocommerce/trunk/plugins/woocommerce/templates/cart/mini-cart.php
The native shipping component was checked against XTemos documentation:
https://xtemos.com/docs-topic/free-shipping-progress-bar/
These references do not establish the production theme/template version.

## Install / acceptance check

Replace the existing Lab ZIP; clear cached CSS/page/CDN assets once. Confirm the
coin and current reward are visible, mobile product names have their own row,
Add/Options works, actual items remain separate, the shipping bar stays dark on
home and inner pages, and its successful-shipping message still updates. Keep
the supplied 1.18.9 ZIP for rollback; settings and customer data were not changed.
