# Qimia Beauty Studio — staging only

An additive editorial Beauty page and homepage entry for `https://qimialab.qimia.om`. Version 1.0.3. It enhances the existing Qimia Beauty catalogue without importing or changing products, prices, stock, orders, account data, or checkout.

## Experience

- Cream/plum art direction, original editorial images, lightweight entrance/reveal/parallax motion, pause control and reduced-motion support.
- English and Arabic/RTL content, six featured categories, full native category filter, and a two-step routine navigator.
- Server-rendered search, category, brand, stock, sort and collection filters; progressively enhanced with abortable same-origin requests and browser history. Standard GET navigation is the fallback.
- Native Qimia product cards, live prices and stock, comparison and AI entry. Bestseller results use the existing sales query, with honest empty states.
- Homepage Beauty portal without changing the supplement hero or checkout flow.

The two supplied spreadsheets informed category emphasis only. No spreadsheet rows, barcodes, prices, stock or product images are included in this plugin.

## Isolation and deployment

The request host **and** the WordPress `home` and `siteurl` hosts must all be exactly `qimialab.qimia.om`. Other hosts register no hooks. `QBS_DISABLE` can also disable the experience. This plugin must not be promoted by changing the guard as part of this staging work.

Tested alongside Qimia Beauty 2.2.5 and Qimia Intelligence Lab 1.19.0 on staging. Their installed versions are newer than the base versions in this repository; deploy **only this plugin**, never the repository's older Qimia core plugins. The shortcode override is registered after the native Beauty owner loads. All new styles/scripts are limited to the homepage and Beauty page.

Upload the ZIP containing only `qimia-beauty-studio/` through staging WordPress Plugins. Deactivate **Qimia Beauty Studio — Staging** to restore the native shortcode and homepage presentation. There are no migrations or data changes to undo.

## Build and checks

From the repository root:

```sh
npm ci --ignore-scripts
node tools/build-beauty-studio.mjs
php tests/php/beauty-studio.php
php tests/php/beauty-studio.php qimia.om
php tests/php/beauty-studio.php qimialab.qimia.om qimia.om
```

The checks cover host isolation, input sanitation, EN/AR rendering, one H1, empty results, native docks after an empty initial search, and absence of public write endpoints. Browser acceptance checks cover live search/reset, categories, stock empty state, motion pause, Arabic rendering, mobile layout and the existing AI opening on Safari. No paid order, payment or AI message is submitted by these checks.

## Assets and references

`hero`, `skin` and `makeup` are original AI-generated decorative artwork, exported as responsive WebP images in 640/960/1440 sizes. The person is fictional; unbranded category props do not depict or authenticate catalogue products. Actual product cards retain native product photographs.

Design research: [Rhode](https://www.rhodeskin.com/), [Charlotte Tilbury best sellers](https://www.charlottetilbury.com/us/products/best-sellers), [Sephora](https://www.sephora.com/). These informed editorial hierarchy and shopping discovery; no brand imagery, text, code or identity was copied.

Implementation references: [web.dev animation performance](https://web.dev/articles/animations-and-performance/), [MDN reduced motion](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@media/prefers-reduced-motion), [WordPress script loading](https://developer.wordpress.org/reference/functions/wp_enqueue_script/).

New minified CSS is about 27 KB and JavaScript about 5.4 KB before compression. The hero's 960 px WebP is about 32 KB. These are bundle sizes, not a Core Web Vitals or site-speed guarantee; the existing theme and plugins remain part of page cost.
