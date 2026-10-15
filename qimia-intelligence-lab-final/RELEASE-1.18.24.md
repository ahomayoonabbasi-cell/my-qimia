# Qimia Intelligence Lab 1.18.24

Baseline: 1.18.23 in this repository.
This is a design release: the storefront reads more easily on phones and explains
what happens when a shopper taps. No new feature, setting, table, task, file or
request. Colours, fonts, weights and the WoodMart theme are unchanged.

## Product cards

1. **Flat cards with one frame.** The card keeps its white image ground and a single
   hairline border; the inner frame and the resting shadow are gone. On a computer
   the border darkens on hover, and grids (not rails, which would clip it) lift
   with a soft shadow. Keyboard focus shows the same border.
2. **Readable at every size.** No card text is smaller than 11px, on phones and on
   the six-up desktop rails. Narrow cards show 13px titles (Arabic 14px) on two
   lines and 18px prices.
3. **Labels that fit the card.** Buy and ask buttons use 12px sentence-case labels.
   When a card is narrow the label switches to a short form ("Add", "Buy",
   "Options", "View", "Qimia AI") and drops its icon, measured on the card's own
   width, so buttons stay on one line at 320-390px in English and Arabic. Each
   short form is a part of the full label, and screen readers hear the full action
   with the product name ("Discuss with Qimia AI: …", "Buy again: …"). Whatever a
   button shows (its short or full label, or "Adding…") is part of that name, so
   voice-control users can say the words they see.
4. **Badges on phones** sit side by side (discount at the start, role at the end)
   instead of taking turns. The desktop label roll is unchanged.
5. **"Not listed"** facts stay visible, in a quieter grey, on Intelligence Lab and
   Beauty cards alike. A Beauty product that is unavailable says so once, on its
   image badge.
6. **Arabic digits:** discounts read "−25٪" in one piece, and prices, counts and
   offer details built in the browser use the Western digits the store prices use.

## Feedback when a shopper acts

7. **Press:** buttons, chips, compare and rail arrows shrink slightly under the finger.
8. **Adding:** while WooCommerce adds the item, the button's light sweep runs;
   then a check replaces the label for 1.4 seconds and the bag count bumps once.
   While the drawer is open it is the confirmation, so the toast stays hidden.
9. **Compare:** the icon turns when a product is chosen, and shakes once when a
   third product is refused. The toast fades out instead of vanishing.
10. **Cards enter** with a short rise and fade, once, as their grid or rail scrolls
    into view. Cards that are already on screen, or were shown before, never wait.

## Phones and chrome

11. **Quick View and compare** open as bottom sheets with the add button kept in reach.
12. **Search:** a 16px field (no zoom on iPhone), a clear focus ring, an opaque
    result panel, and a tint over the page below the bar.
13. **Rails:** arrows stay visible with a 44px touch target; phone collections drop
    their extra panel; the loading skeleton no longer repaints every frame.
14. **One heading system** for the chapters (size, tracking, Arabic line height),
    a 16px phone page edge, and 11-12px for the remaining small text: top bar, hero
    chips, goal tiles, flash, stacks, wallet, mini cart, ladder and footer.
15. **Lighter hero on phones:** no blur behind near-white chips, a flat float for
    the orb, and the AI drawing moves as one layer. Sections below the last menu
    link render only when they come near the screen.

## Fixes

16. **Buy again in "Your choices":** with personalization on, its Buy again buttons
    did nothing in 1.18.22 (the click handler was only attached for the older rail).
    They now add the item like the account rail, and personalization still records
    the reorder click.
17. **Stacks:** a saving such as "0.980 OMR" wraps between amount and currency in
    a narrow phone card instead of being cut off.

## Motion rules

Every animation changes only opacity, position, scale or rotation, so nothing
shifts the layout. With "reduce motion" switched on, nothing moves: cards are
shown at once, and the check, the compare state and the focus ring still change
instantly.

## Preserved

Every public hook, event (`qil:cards-ready`, `qimia:search-rendered`), the
`QILCards` API, compare, the AI open handler, WooCommerce's add-to-cart classes and
attributes, the WoodMart drawer and checkout are unchanged. No PHP template
changed; only the version number. The new names (`data-mobile-label`,
`data-qil-missing`, `qil-just-added`, `data-qil-reveal`) are private to the cards.
`qil.min.js` is rebuilt with terser 5.51.2 and the stylesheets with esbuild 0.25.10,
as in 1.18.23.
