# Verification — 1.18.1

Scope: the flash drop's reliability on cached homepages (desktop and mobile), stock
per option, one theme card and button everywhere, and less empty space in cards and
at the bottom of the cart. Method as in VERIFICATION-1.18.0.md: the real plugin over
WordPress/WooCommerce doubles (PHP 8.4, one process per scenario) and Chromium
(Playwright 1.56.1) on the real templates, CSS and JS, with WoodMart's side-cart
rules loaded after the plugin CSS.

| Suite | Result |
| --- | --- |
| PHP syntax (all PHP files) | 26/26 |
| JavaScript syntax (all JS files) | 16/16 |
| PHP scenarios | 159 passed, 0 failed |
| Chromium | 173 passed, 0 failed |

Details: `docs/verification-1.18.1/php-harness.json`, `docs/verification-1.18.1/browser.json`.

## New checks in this release

Flash drop reliability
- Another worker holds the scan lock on a cold cache: the waiting request gives up
  after about 3 s, prints no flash section, marks the page non-cacheable, and caches
  no empty drop.
- The lock is held but an earlier scan exists: that scan answers and the homepage
  still shows the drop.
- The scan's variation budget is spent: the drop still re-reads its own products
  (the variable product keeps its low-option count).
- Stock change of a drop product (or one of its variations) queues one purge of
  `/` and `/ar/`; a product outside the drop does not; a burst waits 5 minutes after
  the last purge. First request after an upgrade queues one purge, only once.
- Chromium, desktop and mobile: the drop still renders when the inline page data is
  removed (an optimizer delayed it) and when qil-boost.js runs before qil.js.

Stock first
- 216 (two flavours, 4 and 3 left): "Only 3 left in Chocolate" on the card, in
  English and Arabic; simple products carry no option line.
- Almost-gone products come first; the kicker counts them ("4 ALMOST GONE").

One theme
- Flash, stacks, wallet picks and the cart band are the theme's product cards with
  the theme's Add to cart (`.qil-buy`): 4 per view on a 1440px desktop, 2 on a 390px
  phone (3 in the cart column), carousel arrows for the rest.
- Drawer and cart-panel Add buttons compute the same background gradient and text
  colour as the theme's `.qil-buy`, with a pill radius, shadow and the light sweep.
- "Buy again" is the theme button; the theme card on the cart page adds to the order
  without leaving it (no WooCommerce "View cart" link left behind).
- Stack cards: gold "+3 OMR cashback" badge, "Inside: Protein + Creatine",
  "You save: 0.980", "Separately 25.880".
- Embedded JSON can never close its script tag or inject markup (product names with
  `</script>` and `<img onerror>`).

Space
- Phones: the gap above the buttons is 7px on cards that set their rail's height
  (was about 35px on every card); rails stay aligned.
- Wallet pick reasons fit on one line on a phone.

## Not verified here

A live WordPress + WooCommerce + WoodMart + LiteSpeed install, the real cashback
issuer and translator plugins, and production load. `qimia.om` is not reachable from
the build environment. After updating: purge LiteSpeed once, then open the homepage on
a desktop and a phone as a guest.
