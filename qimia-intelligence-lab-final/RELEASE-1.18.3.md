# Qimia Intelligence Lab 1.18.3

Final review of the 1.18 sales surfaces before the live store: one layout fix from
the store, then speed and robustness. No price, cashback or product logic changed.

## Space under the flash drop

The flash drop's own bottom padding (64px) was added to the next section's top
padding, which left about 132px of empty page under the dark stage on a desktop (74px
on a phone), twice the space above it. The section now keeps the homepage's own
rhythm: the same space above the stage as the goal engine keeps above its heading,
and nothing below it, where the next section brings its own. Above and below the
stage are now equal and identical to the gap the homepage had before the drop
existed: 68px on a wide desktop, 49px on a small laptop, 40px on a phone.

## Faster

- **Flash drop, server:** a page render no longer scans the flash category. When a
  drop product sells out, leaves the sale or is taken out of the flash category, it
  is replaced from the last full scan, and each replacement is re-read live
  (price, discount, stock). The full scan runs only when a new window starts. The
  options of a variable product are read in two queries instead of one per option.
- **Cart suggestions, server:** the product list behind the cashback ladder and
  "Complete your stack" is one cache entry per market and language, overwritten in
  place. Previously every order (a stock change) left one more copy behind until
  WordPress cleaned expired entries. While one request rebuilds the list, the others
  use the last one (at most an hour old) at once, instead of waiting a third of a
  second and showing no suggestions.
- **Flash stage, browser:** the soft light behind the stage no longer uses a 70px
  blur filter on a layer larger than the stage (about 20 MB of graphics memory on a
  phone, redrawn while it moved). It is made of soft gradients, drifts only on
  screens with a mouse while the stage is on screen, and stays still on phones. The
  countdown box and the wallet's credit chips drop `backdrop-filter`, which re-blurred
  the moving background on every frame. The look is unchanged.

## Robustness

- The `[qimia_flash_drop]` and `[qimia_cashback_stacks]` shortcodes print their
  section inside its own Qimia shell wherever the Qimia card renderer runs, and
  nothing elsewhere (before, a page without it showed an unstyled section that never
  filled).
- A product removed from the flash-sale category leaves the current drop at once,
  also mid-window (sub-categories still count, as in the scan).

## Files

Modified since 1.18.2: `qimia-intelligence-lab.php` (version), `includes/flash-drop.php`,
`includes/commerce-boost.php`, `includes/cashback-stacks.php`, `assets/qil-boost.css`,
`assets/qil-boost.min.css`, `readme.txt`, `BUILD-MANIFEST.json`.

Added: `RELEASE-1.18.3.md`, `docs/VERIFICATION-1.18.3.md`, `docs/verification-1.18.3/*`.

After updating, purge the LiteSpeed cache once (the plugin also queues a purge of
both homepages on its first request after the upgrade).
