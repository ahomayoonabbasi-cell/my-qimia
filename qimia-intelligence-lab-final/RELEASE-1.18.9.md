# Qimia Intelligence Lab 1.18.9

Base: the exact conversation package `qimia-intelligence-lab-1.18.8.zip`.
Replace the existing plugin; do not install a second copy.

## Mini-cart changes

- The current cart's cashback is the large gold amount in the compact header.
  The next tier's reward is secondary, so the two amounts are not confused.
- The header is full-width, rectangular and in normal flow immediately below
  the native mini-cart title. Negative side margins, rounded bottom corners,
  the header shadow and compact progress animation are removed.
- The copy explicitly says payment is required and this is next-order credit.
  It does not present the estimate as an issued coupon or available wallet balance.
- Existing issuer-backed state and currency formatting are reused. Empty or
  unavailable states still render no reward; the top band has no extra-spend prompt.
- The existing Added badge moves into the quantity row to avoid the Remove
  button. Actual-item label colors now have scoped fallbacks outside `.qil-boost`.
  The Tiffany recommendation panel remains separate and unchanged.

## Unchanged

Cashback calculation, thresholds, issuer/coupon logic, rates, product prices,
stock, discounts, recommendation selection, add/remove events, checkout,
cart-page ladder position, full cart ladder output, homepage, Flash Sale,
search, comparison, AI and My Qimia are unchanged. All JavaScript files are
byte-identical to 1.18.8. No added requests, polling, cron jobs or dependencies.

## Verification and deployment

See `docs/VERIFICATION-1.18.9.md` for tests and limitations.
After replacing the installed plugin, clear its cached CSS and refresh the
mini cart. No database migration or cashback workflow change is required.
