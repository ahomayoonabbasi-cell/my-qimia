# Qimia Intelligence Lab 1.18.0

Sales release: six new surfaces that raise order value and repeat purchase, built on
the store's real WooCommerce data, the existing cashback issuer and the existing
Qimia card, quick-view and Buy Again systems. Every surface can be switched off in
**Settings → Qimia Intelligence Lab → Growth**, and
`define( 'QIL_BOOST_DISABLE', true );` in `wp-config.php` stops all of them at once
(WooCommerce output is then byte-for-byte what 1.17.6 printed).

## 1. Cashback ladder in the mini cart and on the cart page

- "Add **2.400 OMR** more → get **4 OMR** cashback" above the mini-cart items and in
  the cart totals. Bands, amounts and inclusive upper limits come from the cashback
  issuer (`QCB2_Core::public_policy()`), the same source as the homepage cashback
  section — nothing is hard-coded.
- The amount is exact in integer currency units: adding the amount shown always
  reaches the promised band and one unit less never does (proved on 15,000 random
  carts in OMR with 3 and 2 decimals and in SAR). Other currencies round up to whole
  units. Shipping is left out of the cart value, so "add X" is always on the safe side.
- Under the message: up to three related, in-stock products that reach the next band
  with the least overshoot (complements first, then shared category and best sellers).
  Simple products add in one tap through WooCommerce's own AJAX add to cart; variable
  products open the existing Quick View in place.
- The cart page shows the whole ladder (2 · 3 · 4 · 5 · 6 · 8 OMR) with the current
  and next band lit, and a progress meter.
- No new request: it is printed inside WooCommerce's mini-cart and cart-totals
  templates and refreshed by the fragments WooCommerce already requests.

## 2. Complete your stack (cart page)

- "Made to pair with your Creatine" band under the cart table with 2–4 complements
  and one-tap **Add to my order** (no product page, same checkout).
- Rules: Creatine → Whey / Pre-workout · Whey → Creatine · Fat burner →
  Multivitamin / Protein · Magnesium → Ashwagandha / Daily wellness, plus sensible
  defaults for the other roles. Developers can change the map with `qil_stack_rules`.
- Products already in the cart, and those already offered by the ladder, are never
  repeated on the same page.

## 3. Flash drop — "FLASH SALE — 72 HOURS"

- 8–12 products (default 10) from the flash-sale category, chosen once per window and
  stored, so every visitor and every cached page sees the same drop. Drops rotate every
  72 hours (Oman time) and skip the previous drop's products when possible.
- Real facts only: WooCommerce's current and regular price, the real discount, real
  managed stock ("Only 7 left"), and the real end of the drop. A product's own sale end
  is shown only when it is earlier than the drop's end. When a drop ends on screen the
  section says so; it never resets or fakes a timer.
- Homepage section with the shared product card, a clock that ticks only while visible,
  and "Ask Qimia AI about this drop" (opens Qimia AI with the drop's products as context).
- Instagram: EN/AR captions and UTM links (`utm_source=instagram`,
  `utm_campaign=flash-drop-YYYYMMDD`) in the Growth tab, plus a JSON feed at
  `/wp-json/qimia-lab/v1/flash-drop` (`?qil_locale=ar` for Arabic) for automations.
- Optional: show only the current drop on the Flash Sale category page.
- Rotation runs on WP-Cron and purges only `/` and `/ar/` (and the category page when
  the exclusive option is on).

## 4. Cashback wallet (signed-in shoppers, homepage)

- "You have 9 OMR cashback", "4 OMR is waiting for you — one per order, expires in
  5 days", each credit listed with its days left and minimum order, and **Shop with
  cashback** instead of a coupon code. Coupon codes never reach the browser.
- One tap applies the best credit (soonest expiry); with an empty cart or below the
  minimum spend it is kept and applied automatically when the cart qualifies (14 days).
- "Best ways to use your 4 OMR": products the shopper viewed or compared, and
  complements to past purchases, each with its reason.
- The top bar becomes "4 OMR cashback waiting for you · Shop with cashback".
- Coupon detection: issuer-marked coupons, and single-use fixed-amount coupons
  restricted to the shopper's email with an expiry (Growth tab can require issuer
  marks only). The cashback plugin can also supply its coupons through
  `qil_cashback_wallet_coupons`; ownership is always re-checked.

## 5. Running low? — reorder engine

- "Restock your routine in one tap": the exact product, flavour and size from a past
  order with one **Buy again** button (the existing, verified Buy Again endpoint).
- Estimate = the label's verified serving count × quantity ÷ servings per day (1; five
  a week for pre-workout, aminos and electrolytes) from the payment date plus two days
  for delivery. Shown from 7 days before until 21 days after the estimated run-out.
  Products without a verified serving count are never estimated.

## 6. Cashback stacks (homepage)

- Stacks from `staks-offer` (and similar slugs) as clear value cards: what is inside,
  the price, "Separately" (sum of the same products' current prices, only when all
  components are known) or "Was" (the stack's own regular price), "You save", and the
  cashback tier it earns ("+3 OMR").
- No invented discounts: when neither reference exists, only the price and cashback
  are shown.
- Growth tab assistant: stacks against the 24.9 / 34.9 / 44.9 / 54.9 / 64.9 OMR price
  points and ready blueprints (Muscle Starter, Recovery Pack, Daily Essentials) from
  the store's best sellers. Prices are changed in WooCommerce, never by the plugin.
- Contents are read from grouped products, WPC / WooCommerce / YITH bundles, or a
  "stackID: productID, productID x2" map in the Growth tab.

## Performance model

- Guests: zero extra requests on the homepage (flash drop and stacks are part of the
  page; measured in Chromium). Cart surfaces ride on WooCommerce's fragments.
- Signed-in shoppers: one private request in browser idle time for wallet + running low.
- Product pool: at most 280 in-stock products per market and language, public data
  only, rebuilt after 15 minutes or when WooCommerce's product version changes, one
  build at a time (others wait briefly or render nothing).
- New assets: `qil-boost.min.js` 21.9 KB (7.7 KB gzip), `qil-boost.min.css` 32.0 KB
  (6.3 KB gzip). Animations use transform/opacity, run only while on screen and stop
  for `prefers-reduced-motion`.

## Fixes found during verification (pre-existing in 1.17.6)

- `qil.js` never attached `X-Qimia-Language` to WooCommerce's jQuery AJAX: the guard
  checked `jQuery.ajaxSend`, which does not exist (the method lives on `jQuery.fn`).
  The header now flows as the code comment intended, same-origin only (URL-resolved).
  Fragment refreshes without the header now take the language from the Referer.
- The personal cart/product shelves reserved a full screen of empty space under the
  cart: their `min-height: 0` override lost to `:is(#qimia-lab,.qil-shell)`
  (ID specificity). The override now carries the same specificity.

## Files

Modified: `qimia-intelligence-lab.php`, `includes/class-qil-admin.php`,
`templates/home.php`, `templates/cashback.php` (section id only), `assets/qil.js`,
`assets/qil.min.js`, `assets/qil-personalization.js`, `assets/qil-personalization.min.js`,
`assets/qil-personalization.css`, `assets/qil-personalization.min.css`, `readme.txt`,
`BUILD-MANIFEST.json`.

Added: `includes/commerce-boost.php`, `includes/flash-drop.php`,
`includes/member-hub.php`, `includes/cashback-stacks.php`, `assets/qil-boost.js`,
`assets/qil-boost.min.js`, `assets/qil-boost.css`, `assets/qil-boost.min.css`,
`RELEASE-1.18.0.md`, `docs/VERIFICATION-1.18.0.md`, `docs/verification-1.18.0/*`.

Unchanged: cashback issuing, checkout, Qimia AI gateway, search, compare, crawler
cache and all other templates.
