# Qimia Beauty 2.3.2 — integrated staging release

This updates the supplied Qimia Beauty 2.2.5 plugin in place. It replaces the separate Beauty Studio proposal. Deploy only `qimia-beauty/` to `qimialab.qimia.om`; the other plugins in this repository are older reference versions and must not be deployed.

The staging Beauty landing page and home Beauty portal inherit Qimia Intelligence's ink, Tiffany, mint, gradients and typeface. Mobile renders the hero image before its copy. Motion is light CSS/IntersectionObserver animation with a pause control, reduced-motion support and no animation library or autoplay video. Images have responsive WebP sources; new CSS is 28.8 KB and JavaScript 5.5 KB uncompressed/minified.

The catalogue keeps native Woo products, prices, stock, variations, cart, Qimia comparison and AI owners. It supports department, subcategory, brand, search, sort, actual bestsellers, new arrivals, offers and stock. Existing optional attribute facets appear when populated. Empty results never manufacture products or popularity.

Beauty product pages have two separate components: Beauty Facts (department-specific specifications, full original INCI and provenance) and Your Qimia Guide (verified benefits, directions, care, PAO and product questions). Arabic uses Arabic content, explicit empty states and LTR INCI. Parent records never assert that one shade's formula applies to every variation. The existing six-department / 54-subcategory topology and enrichment field schema are retained. Makeup includes shade, undertone, finish and coverage; skin includes skin type, concern, texture and labelled SPF; hair, body, fragrance and tools have their own fields.

The two supplied product spreadsheets inform navigation and component requirements only. No spreadsheet products, fabricated prices, stock or ingredients were imported. Oral products such as Priorin remain supplements.

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
- Staging UI: Beauty/home/EN/AR product detail, 360 px Safari mobile image-first layout; real filter results (10 total, zero actual bestsellers and zero in-stock), reset, package update confirmation.
- Full runtime ZIP manifest includes `includes/products.json`; no migration, taxonomy rewrite, product import or checkout occurred.

## Build and rollback

Run `npm run build:beauty`, `php tests/php/beauty.php` and `node tests/n8n/beauty.mjs` in a development environment. Runtime does not require Node.

Replace the existing plugin using the provided ZIP. To reverse the presentation release, upload the original supplied 2.2.5 plugin ZIP. No database migration was run, so rollback does not require changing product data. Do not deploy the retired `qimia-beauty-studio` plugin.
