# Qimia Beauty 2.4.2 — integrated staging release

This updates the supplied Qimia Beauty 2.2.5 plugin in place. It replaces the separate Beauty Studio proposal. Deploy only `qimia-beauty/` to `qimialab.qimia.om`; the other plugins in this repository are older reference versions and must not be deployed.

The staging Beauty landing page and home Beauty portal inherit Qimia Intelligence's ink, Tiffany, mint, gradients and typeface. Mobile renders the hero image before its copy. Motion is light CSS/IntersectionObserver animation with device reduced-motion support and no animation library or autoplay video. Images have responsive WebP sources; the experience assets are 42.2 KB CSS and 5.4 KB JavaScript minified (8.9 KB and 2.3 KB when gzipped). These are asset sizes, not a measured page-speed score.

The catalogue keeps native Woo products, prices, stock, variations, cart, Qimia comparison and AI owners. It supports department, subcategory, brand, search, sort, actual bestsellers, new arrivals, offers and stock. Existing optional attribute facets appear when populated. Empty results never manufacture products or popularity.

Beauty product pages have two separate components: Beauty Facts (department-specific specifications, full original INCI and provenance) and Your Qimia Guide (verified benefits, directions, care, PAO and product questions). Arabic uses Arabic content, explicit empty states and LTR INCI. Parent records never assert that one shade's formula applies to every variation. The existing six-department / 54-subcategory topology and enrichment field schema are retained. Makeup includes shade, undertone, finish and coverage; skin includes skin type, concern, texture and labelled SPF; hair, body, fragrance and tools have their own fields.

The two supplied product spreadsheets inform navigation and component requirements only. No spreadsheet products, fabricated prices, stock or ingredients were imported. Oral products such as Priorin remain supplements.

## Version 2.4.2

Design pass made together with Qimia Intelligence Lab 1.18.24. No new features, settings, product data, files or requests. Colours, fonts and page markup are unchanged.

- Text is easier to read. No Beauty text is smaller than 11px, on phones or on desktop. Small headings share one style (11px; Arabic 12px with no letter spacing), and the home product names are 12px (Arabic 13px).
- On phones, the home section shows its copy before the product edit, and the six departments fit in a three-column grid.
- On phones, the /beauty/ worlds are one row you swipe. The search box and filters use 16px text, so iPhones no longer zoom in when you tap them. The collections row no longer cuts off "Reset filters". On phones narrower than about 384px, Category and Brand stack, so "All beauty" and "All brands" show in full. The two halves of the line under the hero wrap into even lines instead of leaving one word alone. Sub-navigation links and routine tabs are 44px tall.
- Filters already apply as you change them, so the "Find products" button is hidden while the page script runs. It is still the button that Enter presses, and it shows again when focused with a keyboard or when JavaScript is off.
- The search field is the underline field again; the storefront input box no longer draws a frame around it. Beauty product cards use the Intelligence Lab card (flat, one radius).
- In Arabic, outward arrows and hover nudges point the reading way, and headings have no negative letter spacing.
- Motion: hover lifts only run with a mouse or trackpad, so they no longer stay stuck after a tap. Buttons and tiles press in slightly when tapped. World tiles and home departments arrive one after another, the hero headline is visible from the first frame, and a thin bar under the filters shows while results reload. The photo and product parallax only run on screens wider than 700px. With reduced motion turned on in the device settings, none of this animates.
- Performance: sections below the fold are hidden without starting a transition, the hero stamp no longer blurs the photo behind it, and the closing section of /beauty/ is only drawn when it comes near the screen.
- The production Beauty styles (the home section and Beauty pages without the staging experience) get the same 11px floor, and the Beauty links in the header search are full 44px touch targets.
- Product card caches start fresh after the update.

## Version 2.4.1

Fixes for running alongside Qimia Intelligence Lab 1.18.23. No new features, settings or product data changes.

- Beauty products no longer show up in the Intelligence Lab supplement goal "Hair, skin & beauty". The Beauty top category no longer counts as supplement evidence, and Beauty cards carry no supplement goal data. Collagen, biotin and hair/skin/nails supplements are unchanged.
- Search keeps out-of-stock Beauty products when several match, keeps Beauty's own order and leaves out products hidden from search. Product names no longer get "· Out of stock" added to them; stock is shown separately.
- An ambiguous "hair" search no longer counts Beauty tools, such as brushes, as hair supplements.
- On a Beauty-only search such as "shampoo", the results heading and search box show what the shopper typed.
- Beauty product pages now print the Intelligence Lab compare dock and message area, so "Ask Qimia AI" and compare messages are visible there.
- The Arabic home page (/ar/) loads the Beauty home styles and script even when it is not the site's front page.
- The production home section shows Arabic product names from the existing translation memory, prints names with automatic text direction, and leaves out the "THE BEAUTY EDIT" panel when there are no products to show.
- Product card caches start fresh after the update.

## Version 2.4

The supplied 2.2.5 homepage product edit is merged back into the integrated design: three real product cutouts, real stock states and the original six pastel department cards with inline SVG artwork. The edit uses the existing bounded sales-order query; it says best sellers only when all three products have recorded sales, otherwise it remains the Qimia Beauty Edit. Quick links reach best sellers, new arrivals, offers and the routine finder. Arabic product names use the installed Qimia translation memory in one read-only batch, with no translation job or AI call during page rendering.

The visible motion toggle and decorative Unicode glyphs are removed. Original inline SVG marks, a dedicated sun-care illustration and line icons replace them without icon fonts, dependencies or asset requests. Motion remains finite or scroll-driven; device reduced-motion preferences are respected. Beauty Facts and the Qimia Guide now share the menu's Tiffany/mint palette, light surfaces, fine borders and native gradient buttons. Verified multi-line usage instructions render with numbered steps.

## Automation

`n8n/qimia-beauty-staging.json` is a portable, credential-free workflow. A separate workflow was installed in the existing n8n project:

https://n8n.srv1872518.hstgr.cloud/workflow/iFEW3PiF4QteRJ8M

Name: **Qimia Beauty | Ingredients + Qimia Guide | STAGING**.

A live manual review run on existing staging product 6246 succeeded in 36.706 seconds on 10 October 2026. It read the product and verified native Beauty ancestry, researched the exact label and produced an EN/AR review draft. No PUT node ran. The workflow is manual, not scheduled; manual-only workflows do not need publication. Existing supplement workflows were not edited or called. Existing Qimia Woo and OpenAI credential references were bound only in the private n8n installation, not exported into this repository; authenticated staging reads succeeded.

Review mode ends at a draft with field-level source evidence. Apply mode requires a reviewed exact-product draft, matching translations/variant scope, allowed source host and an unchanged product snapshot. It selectively writes only `qimia_product_class` and `qimia_beauty_details`, then checks readback against commerce and supplement-owned fields. It rejects production origins, oral products, variant-specific formulas on parents, missing evidence, mixed sources and stale drafts. No live metadata write was needed for this design task; the apply path is covered by local fixtures. A new review is required before applying because product details may have changed since the sample run.

## Validation

- All 20 PHP source files parsed successfully with PHP 8.3 WASM.
- English/Arabic rendering tests: one H1, image-first markup, input sanitation, empty catalogue, native docks, separate product knowledge, supplement exclusion, missing-translation behavior and INCI direction.
- 33 n8n fixture tests: exact target, metadata-only writes, evidence/language validation, stale state, idempotency, readback and protected supplement/commerce fields.
- Staging UI: 2.4 package update confirmation; Beauty/home/EN/AR product detail; 360 px Safari mobile image-first layout; routine-finder tabs and empty results; reset to 10 products; serum search returns the one scalp serum; native comparison selects two products and clears correctly. The preceding 2.3 validation also confirmed zero actual bestsellers and zero in-stock products.
- Full runtime ZIP manifest includes `includes/products.json`; no migration, taxonomy rewrite, product import or checkout occurred.

## Build and rollback

Run `npm run build:beauty`, `php tests/php/beauty.php`, `php tests/php/beauty-integration.php` (card, compare, AI and search contracts), `php tests/php/beauty-qil-live.php` (Beauty and Intelligence Lab loaded together) and `node tests/n8n/beauty.mjs` in a development environment. Runtime does not require Node.

Replace the existing plugin using the provided ZIP. To reverse the presentation release, upload the original supplied 2.2.5 plugin ZIP. No database migration was run, so rollback does not require changing product data. Do not deploy the retired `qimia-beauty-studio` plugin.
