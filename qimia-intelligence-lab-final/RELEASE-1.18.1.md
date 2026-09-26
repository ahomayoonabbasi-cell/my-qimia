# Qimia Intelligence Lab 1.18.1

Follow-up to 1.18.0 from store feedback: the flash drop missing on desktop, empty
space in cards, and one theme for every card and button.

## Flash drop always shows (desktop and mobile)

Root cause found in 1.18.0: while the flash-category scan was being rebuilt (cold
cache after install, or after a product change), other requests waited 3 seconds
and then received an **empty** drop, which was cached for 5 minutes. Any homepage
rendered in those minutes had no flash section, and LiteSpeed kept that copy
(desktop and mobile are cached separately) until the next purge, up to 72 hours
later. Fixed at every level:

- Normal renders no longer scan the category at all: only the drop's own 8–12
  products are re-read. The scan runs only to choose a new drop or to replace a
  product that sold out.
- If another worker is scanning, the last complete scan is used, never "no drop".
  An empty result caused by a momentary lock is never cached, and a page rendered
  in that moment is marked non-cacheable.
- The drop's products are never dropped by the scan's variation budget.
- Cached homepages are purged (WP-Cron, at most once every 5 minutes) when a new
  drop starts, when a drop product's stock, price or sale changes, and once after a
  plugin upgrade, so the counts on cached pages stay real.
- The section carries its own data inside its HTML, so it no longer depends on
  inline page data that an optimizer (LiteSpeed JS defer/delay) may run late, and
  it waits for the theme's card renderer by event and short polling instead of the
  `load` event. It is never hidden because page data arrived late.

## Stock first

- Each flash card shows its real stock on the image: "Only 3 left", "27 left", and
  for variable products the option that is running low: "Only 2 left in Chocolate"
  (options with 5 or fewer). Counts come from WooCommerce's managed stock.
- Products that are almost gone lead the drop, and the header says how many
  ("4 ALMOST GONE"). The feed carries the low option too (`lowStock`).

## One theme for every card and button

- Flash drop, cashback stacks, "Best ways to use your cashback" and the cart
  page's "Complete your stack" all use the homepage's own product card (the same
  renderer, badges, price, facts, Add to cart, compare and Qimia AI buttons).
- Four cards per view on a desktop, three on a tablet, two on a phone, and the
  theme's carousel arrows for the rest (three per view in the narrower cart column).
- Stacks keep their facts inside the card's own slots: a gold "+3 OMR cashback"
  badge, "Inside: Protein + Creatine", "You save", and "Separately".
- Mini cart and cart-totals picks use the exact homepage Add to cart button
  (gradient, pill, shadow, hover and light sweep, the theme's cart icon); "Buy
  again" is the theme button too. The cashback ladder box is unchanged.

## Less empty space

- Phones: product cards now size to their content (still equal within each rail)
  instead of reserving a fixed 268px body. The gap above the buttons drops from
  about 35px to 7px on most cards. This applies to every product rail on the site.
- The theme's cart shelf no longer keeps the homepage's 72–108px section padding
  at the bottom of the cart page.
- Long labels end in an ellipsis instead of being clipped on both sides.

## Files

Modified since 1.18.0: `qimia-intelligence-lab.php`, `includes/flash-drop.php`,
`includes/commerce-boost.php`, `includes/cashback-stacks.php`,
`includes/member-hub.php`, `assets/qil.js`, `assets/qil.min.js`,
`assets/qil-personalization.js`, `assets/qil-personalization.min.js`,
`assets/qil-boost.js`, `assets/qil-boost.min.js`, `assets/qil-boost.css`,
`assets/qil-boost.min.css`, `readme.txt`, `BUILD-MANIFEST.json`.

Added: `RELEASE-1.18.1.md`, `docs/VERIFICATION-1.18.1.md`,
`docs/verification-1.18.1/*`.

After updating, purge the LiteSpeed cache once (the plugin also queues a purge of
both homepages on its first request after the upgrade).
