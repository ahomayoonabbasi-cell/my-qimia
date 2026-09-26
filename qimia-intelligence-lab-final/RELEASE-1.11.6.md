# Qimia Intelligence Lab 1.11.6

Base package: the supplied 1.11.5 mobile-hero-balance ZIP.

## Mobile changes

The previous release assigned the photograph to grid row 5 while leaving the
AI form on automatic placement. The grid filled the empty row 4 with the form.
This release explicitly assigns the headline, copy, scene, form and following
controls to consecutive rows. Image loading and the presence of Q no longer
change that order.

Q is an absolute decorative item sharing the scene's grid area, rather than a
right-aligned item with fixed 160–180px margins. The existing local geometry
callback now measures the rendered object-fit:contain artwork, centers Q over
the couple and keeps it clear of the right-hand product rail. Its size and float
animation are retained. Where a wide/short viewport leaves insufficient space
above the artwork, only the necessary headroom is reserved. The image itself is
not resized or repositioned inside its original stage; Hero Studio values are
not changed. The AI input follows the complete scene and product rail.

The latest approved three-line mobile title is preserved. The mobile LIVE chip
remains hidden. Desktop style rules, header/My Qimia behavior, templates,
products, cart, checkout, cashback, customer data and service/API code are
unchanged. There are no new libraries, network calls, polling or scroll handlers.

## Verification performed

- PHP syntax: all 17 PHP files passed.
- JavaScript syntax: all 10 JavaScript files passed.
- 82 local browser cases passed: 64 mobile layouts across 16 widths, English and
  Arabic, My Qimia available/unavailable; 6 short-viewport/optional-Q/source-asset
  variants; and 12 exact desktop geometry comparisons with the supplied base.
- Checked: three headline lines, no page overflow, Q above the painted artwork
  and clear of the product rail, AI form after the scene and products, hidden
  LIVE chip, and existing My Qimia header/title availability behavior.
- Checked a mobile -> tablet -> short landscape -> desktop -> mobile resize
  sequence with existing float animations enabled, and native AI input focus.
- Desktop element bounding boxes matched the base at 981, 1024, 1180, 1280,
  1440 and 1920px in both languages, with motion disabled for the comparison.

These are local Chromium layout tests using the actual plugin renderers/assets,
WordPress function stubs and synthetic product cards. They are not live store,
WooCommerce, AI-response, payment or physical iPhone/Safari tests. The bundled
site assets were not replaced. Test harnesses and product fixtures are NOT
included in the plugin ZIP.

## Installation

Back up the currently installed Lab. Upload this ZIP through the WordPress
plugin upload flow and replace Qimia Intelligence Lab in its existing folder.
Do not install a second copy, reset settings, or replace My Qimia. Purge the
homepage HTML cache and CSS/JS optimization cache (and the relevant CDN cache
where applicable), then reload the mobile homepage.

Verify the three headline lines, Q above the couple, all product cards clear,
and AI input below the complete scene before use on the main site. No change
has been made to the existing My Qimia host/access restrictions.

Rollback: replace this plugin with the previous backed-up ZIP and purge the
same caches. No migration or database update is part of this patch.
