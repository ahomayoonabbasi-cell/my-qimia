# Verification — 1.18.0

Scope: the six new sales surfaces (cart cashback ladder, Complete your stack, flash
drop, cashback wallet, Running low, cashback stacks) and the two pre-existing fixes
listed in RELEASE-1.18.0.md.

## Summary

| Suite | Result |
| --- | --- |
| PHP syntax (all PHP files) | 26/26 |
| JavaScript syntax (all JS files) | 16/16 |
| PHP scenarios (real plugin code, isolated processes) | 136 passed, 0 failed |
| Chromium (real templates, CSS and JS) | 133 passed, 0 failed |

Details: `docs/verification-1.18.0/php-harness.json` and
`docs/verification-1.18.0/browser.json`.

## How it was tested

- **PHP scenarios** run the real plugin (all 1.17.6 code plus the new files) over
  WordPress/WooCommerce test doubles: products, variations, carts, coupons, orders,
  sessions, options, transients, cron, REST and the cashback issuer's
  `QCB2_Core::public_policy()` contract (bands ≤20 → 2, ≤30 → 3, ≤40 → 4, ≤50 → 5,
  ≤60 → 6, >60 → 8 OMR). Every scenario runs in its own PHP 8.4 process so static
  memos never leak between cases.
- **Chromium** (Playwright 1.56.1) drives a PHP built-in-server router that renders the
  real `templates/home.php`, the real mini cart and a classic cart page from the same
  plugin code, serves the real minified CSS/JS, and answers the plugin's real
  `wc-ajax` and REST endpoints. WooCommerce's add-to-cart/fragments scripts are a
  stand-in with the same DOM contract; WoodMart's side-cart rules (340 px / 300 px
  drawer, list, image, paragraph and button rules) are loaded after the plugin CSS,
  as WoodMart enqueues late, so theme rules win every tie.
- Matrix: guest EN/AR × desktop 1440 px / mobile 390 px; signed-in desktop/mobile;
  mini cart EN desktop/mobile and AR mobile; cart page EN desktop/mobile and AR
  desktop; reduced motion; a drop that ends while on screen.

## What the checks prove

Cashback ladder
- Band, earned amount and next amount for boundary values (20 → 2, 20.001 → 3,
  60 → 6, 60.001 → top band 8, 0.5, 27.6, 45.25).
- Property test on 15,000 random carts (OMR with 3 and 2 decimals, SAR): adding the
  amount shown always reaches the promised band; one unit less never does (OMR).
- Foreign currencies: whole units rounded up and marked approximate.
- Three picks, each alone reaching the next band; related products first; cart,
  hidden and sold-out products never picked; variable picks open Quick View.
- One tap on a pick adds it through WooCommerce AJAX and the ladder moves on
  (Chromium: 19.900 → add creatine → "Add 4.121 more → get 4 OMR").
- Ladder and picks fit the 340 px and 300 px WoodMart drawers; Arabic is RTL with
  Arabic-Indic cashback amounts, also after a fragment refresh.

Complete your stack
- Rule map (Whey → Creatine, Pre-workout, Multivitamin; Creatine → Whey;
  Fat burner → Multivitamin; Magnesium → Ashwagandha, then daily wellness).
- 2–4 complements, never a product already offered by the ladder (hook-order safe),
  "Add to my order" adds without leaving the cart, no WooCommerce "View cart" link.
- No horizontal overflow at 390 px (the card scroller cannot widen the cart form).

Flash drop
- Only real reductions (≥ 5 %, image, in stock); percent from WooCommerce prices;
  "up to" for mixed variations; stock from managed quantities; real ends only.
- 10 distinct products per 72-hour Oman window, stable within the window, next window
  leads with new products, sold-out products replaced, rotation scheduled for the
  real end and purging exactly `/` and `/ar/`; unrelated settings never reshuffle it.
- Chromium: 10 shared product cards, clock idle while off screen and counting down to
  the real end when visible, per-product end shown only when earlier than the drop's
  end, honest "THIS DROP HAS ENDED" state, aurora paused off screen and removed for
  reduced motion, zero extra requests for guests.
- REST feed with Instagram UTM links, captions in EN/AR, Qimia AI context.

Cashback wallet and Running low
- Wallet reads only the shopper's own unused, unexpired fixed-amount cashback; codes
  never leave the server; strict mode accepts issuer-marked coupons only; another
  customer's coupon is refused even if a filter supplies it.
- Apply flow: bad nonce refused; empty cart → pending → applied with the first product;
  below minimum → pending with the minimum stated → applied when reached; existing
  notices kept.
- Chromium: "You have 9 OMR cashback", best credit with its real expiry, credits listed
  (4 OMR · 5 days left, 3 OMR · 20 days left, 2 OMR · Min. order 10 OMR), top bar swap,
  every pick states its reason in full, exactly one private request, Buy again adds the
  exact previous item and the pending credit applies with it.
- Running low: servings × quantity ÷ per-day from the paid date + 2 days; exact flavour
  and variation; never for products without verified servings; not when already in
  the cart.

Cashback stacks
- Contents from bundle plugins, grouped products or the Growth map; "Separately" only
  when every component is known; a stack's own sale shown as "Was"; cashback tier from
  the issuer (24.9 → 3, 34.9 → 4 OMR); Arabic rows read price first.

Safety and performance
- No product name can inject markup into any new surface.
- `QIL_BOOST_DISABLE`: the mini cart is exactly WooCommerce's and no new section renders.
- Product pool bounded at 280 rows; harness cold build 13.6 ms and warm mini-cart render
  0.06 ms (doubles only — real database and WooCommerce costs are not included).

## Not verified here

- A live WordPress + WooCommerce + WoodMart install, LiteSpeed Cache, the real cashback
  issuer plugin and the translator plugin (their public contracts were doubled).
  `qimia.om` is not reachable from the build environment.
- Production load, CPU and real page timings.

Recommended after deployment: open the homepage as a guest and as a customer with
cashback, open the mini cart with one product under 20 OMR, visit the cart page on a
phone, check the Growth tab preview, and confirm the flash drop rotates at the window
end (WP-Cron).
