# Qimia Intelligence Lab 1.12.1

Source: the supplied `qimia-intelligence-lab-8-2-3-95(1).zip` (plugin version 1.12.0).
This is a replacement Lab package. The internal plugin directory is deliberately
unchanged so the update does not create a second Lab installation.

## Requested changes only

- **Separate Buy Again sections:** the homepage and product-page lower band each
  have their own initially hidden section. The original Qimia collection layout,
  product-card renderer, typography and purchase-button treatment are reused.
  No alternate product-card theme is introduced. The existing native purchase
  button, normal card purchase actions, variation selector and quantity input
  are never converted into Buy Again controls.
- **Exact previous purchases:** only currently repeatable items from the signed-in
  customer's Processing/Completed orders are shown. Different purchased flavours
  can have separate cards. A sold-out flavour is excluded, not substituted.
  Fully refunded line items and unavailable products/selections are excluded.
  Each new Buy Again action requests one unit of that exact previous selection
  at the current price. The existing server-side ownership, nonce, stock,
  variation and duplicate-request checks are preserved.
- **Label position:** New Arrival and Back in Stock are at the lower-left of the
  image in English and lower-right in Arabic, including narrow mobile layouts.
  Existing verified/vegan badges remain in their old position; where one is
  present, the new label group sits just above it to avoid overlap.

The sections remain hidden for guests, accounts without eligible history and
failed private history requests. No customer history is embedded in public,
cacheable page HTML. Existing bounded history retrieval is retained: the latest
30 successful-status orders, up to 24 exact selections and up to 8 visible cards
per section. This is not an unlimited lifetime-order browser.

## Preserved scope

Hero/header, YOUR QIMIA entry, My Qimia, cashback, AI connections, product details,
existing ordinary Add to cart controls and payment logic were not redesigned.
The exact-repeat cart endpoint and the inventory truth/age functions are
byte-identical to the supplied version. All unrelated original files are
byte-identical; BUILD-MANIFEST.json records the complete patch/file hashes.

## Installation

1. Back up the current Lab files and database.
2. Upload this ZIP through the WordPress plugin uploader and replace the existing
   Qimia Intelligence Lab installation. Do not activate a second Lab copy.
3. Keep the separate My Qimia/AI/cashback plugins unchanged.
4. Clear cached HTML and CSS/JavaScript for the homepage and product pages in the
   caches already used on the site. This release increments the asset version.
5. Verify once as a guest and once as a customer with a previous in-stock purchase
   in both storefront languages. Check an ordinary purchase and a new Buy Again
   action. The new action adds one unit; it does not place or pay for an order.

## Verification boundary

Local isolated PHP checks and Chromium DOM/browser checks passed. These use
controlled WooCommerce/WordPress fixtures and mocked network responses, not the
merchant's live site or production database. Live WoodMart/Elementor integration,
site-specific caching/currency extensions, Safari/iOS and a production checkout
have not been certified by these local checks. Details are in
`docs/VERIFICATION-1.12.1.md`.
