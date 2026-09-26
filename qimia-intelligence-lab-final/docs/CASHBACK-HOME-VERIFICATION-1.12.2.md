# Homepage cashback — verified 1.12.2 package

## Exact scope

Base: qimia-intelligence-lab-1.12.2-labels-filters-final.zip.
Donor: qimia-intelligence-lab-8-2-3-94(1).zip (internal version 1.11.6).

The donor's includes/cashback.php, templates/cashback.php and
assets/qil-cashback.css were already byte-identical to the 1.12.2 files.
They were copied exactly. The existing single QIL_Cashback::render() call in
templates/home.php remains after the personal-area hook and before the
My Qimia explainer. No second section was added.

All 67 original 1.12.2 files, including its build manifest, remain
byte-identical. Only this report and its JSON verification record were added.
The original build manifest describes the preceding 1.12.2 release;
CASHBACK-HOME-VERIFICATION-1.12.2.json describes this verified repack.
The plugin version and installation directory remain unchanged.

No changes to Buy Again, inventory labels, filters, product cards, cart
buttons, homepage hero, headers, My Qimia, settings or coupon issuance.
No new scripts, queries, scheduled work, order processing or email sending.

## Important visibility dependency

The original display module reads the active cashback issuer's public
policy. If the issuer is missing, throws, or exposes an unsupported policy,
it intentionally returns no section rather than inventing reward amounts.
This repack preserves that behavior. Repacking is NOT a fix for an inactive
or incompatible cashback issuer. No production diagnosis was performed.

## Checks performed for this package

- PHP 8.4.23 syntax validation: all 19 PHP files pass.
- 208 local assertions, including original-file integrity, exact donor/target
  render equivalence, six-tier output, currency fallbacks, language direction,
  single homepage placement, legacy policy support and unavailable-policy behavior.
- 56 local Chromium assertions across English and Arabic at widths
  320, 390, 768 and 1440: visible section, six tiers, text direction,
  no horizontal overflow, native terms open/close and no JavaScript errors.
- Cashback CSS parsed successfully with PostCSS.
- Live WordPress/WooCommerce, the production issuer and production load
  were not tested. Test reward amounts/rates were synthetic fixtures, not
  production facts. Fixtures are not shipped in this plugin.

## Installation

Back up first, replace the existing Lab plugin with this ZIP, and purge
cached homepage HTML and assets. Do not install or activate a second Lab copy.
The cashback issuer remains responsible for the real policy and coupons.
