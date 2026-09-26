# Qimia Lab 1.6.3 — homepage cashback

## Scope
A presentation-only section immediately after the homepage product collections and before the brand rail. The six cards, email explanation, validity and expandable terms use the existing white/Tiffany theme and the site's native English/Arabic context. No header badge is reintroduced.

## Authoritative inputs
- Existing `QCB2_Core::cfg()` and `tier_amounts()` when available; saved `qcb2_settings` otherwise.
- For the audited older QCB2 contract: 2 / 3 / 4 / 5 / configured `gap_50_60` (the owner's default is 5) / 6 OMR. Current `cashback_tiers` supersedes the legacy gap.
- Order bounds remain >0–20, >20–30, >30–40, >40–50, >50–60, and >60 OMR. The issuer—not this section—evaluates orders.
- Current currency from `get_woocommerce_currency()`; FX from `QCB2_Core::rates()` or the site's `qimia_ugc_settings` table. Existing `qcb2_currency_rates` adapters are respected. No hardcoded market exchange rates.
- No cashback configuration/invalid rules: no customer-facing invented offer. Missing selected-currency rate: explicitly show OMR, not a fabricated conversion.
- New-coupon validity matches the audited v2.4.x issuer and supplied screenshot: through 11:59 PM Oman time on day +14 after issue, not 14*24 hours. Existing coupons retain their original expiry.

## Display and caching
Foreign bounds/rewards are rounded HALF_UP to whole units and marked approximate. OMR keeps configured precision (up to 3 decimals) rather than changing a configured fractional reward. Whole-unit presentation is never written to coupons or orders. Email/checkout remain authoritative for exact value, currency and expiry; a historical order can use its own saved exchange rate.

The installed header already switches currency/language by full same-page navigation. This section renders with that same server-side context; there is no second currency picker, cookie writer, new JavaScript, AJAX, polling, catalogue scan or worker.

On addition/update of `qcb2_settings` or `qimia_ugc_settings`, only `/` and `/ar/` on the configured site are submitted to the documented `litespeed_purge_url` action. Other cache layers (e.g. independently cached Cloudflare HTML), custom shortcode pages and custom FX adapters may need their own explicit purge. The entire shop is not purged or excluded from cache.

## Install
Replace the existing `qimia-intelligence-lab-8-2` plugin with this complete ZIP on staging first. Do not activate parallel Lab versions. Keep the existing cashback bridge/workflow and Commerce 3.51.7 unchanged. This is not an issuer upgrade. The cashback bridge must be configured on the test site (or have its existing configuration saved there); the source is not recreated from a guessed screenshot value.

Clear the EN and AR homepage HTML cache after installing and changing campaign settings. Existing currency cache variation remains owned by the installed currency plugin. Optionally place `[qimia_cashback_rules]` in another WordPress/Elementor shortcode widget; do not duplicate it on the automatic Lab homepage.

## Preserved
All Health files, header/menu files, existing CSS/JS, WooCommerce/AI/cart/search/coupon/email logic, subscription and trial records, and schema versions remain byte-for-byte unchanged. Only the Lab loader/version, one homepage insertion and the readme change, plus the new isolated presentation files.

## Acceptance checks
Switch OMR/SAR/AED/QAR/KWD/BHD via the actual site header; compare displayed whole-unit estimates with the site's configured rates. Switch English/Arabic on the same page. Change 50–60 in the actual cashback settings and regenerate the homepage cache. Confirm that no coupon, email, subscription or order is created by viewing the homepage. Keep real email/payment disabled on staging.

Local tests execute the actual new PHP module, the retrieved QCB2 rules/FX class and the existing language resolver with WordPress doubles. Chromium tests use actual generated HTML + original/new CSS in an isolated frame, synthetic test FX and fallback fonts. Live WordPress, actual saved options, translator/LSCache/Cloudflare behavior and real Safari are not verified by these tests. No guarantee of universal error-free operation or production capacity is implied.

## Implementation references
- https://developer.wordpress.org/reference/functions/wp_enqueue_style/
- https://developer.wordpress.org/reference/hooks/update_option_option/
- https://docs.litespeedtech.com/lscache/lscwp/api/
