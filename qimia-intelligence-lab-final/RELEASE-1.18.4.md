# Qimia Intelligence Lab 1.18.4

A shopper-experience pass over every 1.18 surface, in English and Arabic, on a
desktop and a phone. No price, cashback or stock logic changed.

## Checkout first on phones

On a phone the cart stacks: the "Complete your stack" cards (inside the cart form)
came before the cashback ladder and the totals, pushing "Proceed to checkout" about
two screens down. Where the cart stacks, the ladder, its picks, the totals and the
checkout button now come first and the suggestions follow them. Side by side
(desktop), the band stays under the cart table. When WooCommerce updates the cart,
the new band takes the old one's place: never two, never a stale one.

## Flash drop

- Each card shows one discount badge ("−25%", "Up to −25%"), always in view and in
  the start corner, as on the stack cards. The extra "Flash drop" card label only
  repeated the section title and took turns with the discount.
- The clock says what it counts: "DROP ENDS IN" / "تنتهي المجموعة خلال" (it said
  "NEXT DROP IN"), with the exact end date under it.
- Phones: the kicker ("LIVE DROP · 10 PRODUCTS", "4 ALMOST GONE") never breaks in
  the middle of a phrase.

## One Arabic

- Western digits in every new surface, as the theme and WooCommerce print every
  price in Arabic: the ladder ("أضف 0.101 ر.ع. فقط ← واحصل على كاش باك 3 ر.ع" used
  two digit styles in one sentence), the cart steps, the stack badges, the flash
  clock and counts ("بقي 3 فقط من Chocolate"), the wallet and "Running low?".
  Percentages keep the Arabic sign, like the theme's own badges ("−25٪").
- Copy: "احصل على كاش باك 3 ر.ع بإضافة منتج واحد من هذه" (was the colloquial
  "اوصل إلى"); "وصلت إلى أعلى كاش باك"; the stacks line now reads "وسعر محتوياتها
  منفصلة اليوم"; 11 and 12 products take the singular ("11 منتجاً"); 3–10 servings
  take the plural ("7 حصص"); a sale end more than a day away reads "يومين 04:10:00"
  instead of an abbreviation.

## Files

Modified since 1.18.3: `qimia-intelligence-lab.php` (version), `includes/flash-drop.php`,
`includes/commerce-boost.php`, `includes/cashback-stacks.php`, `includes/member-hub.php`,
`assets/qil-boost.js`, `assets/qil-boost.min.js`, `assets/qil-boost.css`,
`assets/qil-boost.min.css`, `readme.txt`, `BUILD-MANIFEST.json`.

Added: `RELEASE-1.18.4.md`, `docs/VERIFICATION-1.18.4.md`, `docs/verification-1.18.4/*`.

After updating, purge the LiteSpeed cache once (the plugin also queues a purge of
both homepages on its first request after the upgrade).
