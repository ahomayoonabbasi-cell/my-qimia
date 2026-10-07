# Qimia Intelligence Lab 1.18.11

Baseline: the exact `qimia-intelligence-lab-1.18.9(1).zip` supplied for this request.
This is a full replacement package, not an additional plugin or CSS add-on.

## Mini-cart changes

1. **Recommendations:** only mini-cart picks receive a dedicated two-row HTML
   layout. Product image, name (up to three lines) and pairing note sit above;
   the complete price/discount/From display and native Add/Options/View action sit
   below. Button width no longer consumes the product-title column. The existing
   Tiffany recommendation panel remains distinct from actual cart contents.
2. **Cashback header:** the original inline circular coin/check graphic is back.
   The current cart's reward stays prominent and gold. Payment eligibility and
   next-order-credit terms remain visible. The compact header stays rectangular
   and edge-to-edge; no fixed heights or negative margins.
3. **Free shipping:** the native WoodMart mini-cart bar has a scoped dark
   background, readable light text and Tiffany amount/progress styling on both
   home and inner pages. Its messages, threshold calculations, hidden state and
   dynamic progress width are not changed.

## Preserved

Actual added cart rows and Added labels from 1.18.9; the full-cart ladder and
recommendation renderer; recommendation selection/ranking; the cashier's prices,
discounts, availability, currencies and cashback bands; all AJAX handlers and
native purchase link data. No new JavaScript, stylesheet request, database
migration, task, query, third-party dependency or polling loop is introduced.
Homepage/Flash Sale/search/AI/checkout files remain byte-identical to the supplied
baseline. The version bump changes existing asset cache URLs as usual.

## Verification and deployment

See `docs/VERIFICATION-1.18.11.md` for the tests actually run and their limits.
Replace the existing plugin with this ZIP. Clear the existing page/CSS/CDN cache
once, then hard-refresh. Do not install a second copy, reset plugin settings or
change the cashback workflow. On the store, check English/Arabic, a simple and a
variable suggestion, Remove, shipping success, and Checkout. The release has not
been deployed by the assistant. Keep 1.18.9 available for rollback.
