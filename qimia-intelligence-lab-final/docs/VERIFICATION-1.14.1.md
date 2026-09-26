# Verification — Qimia Intelligence Lab 1.14.1

## Scope

Arabic/RTL product-card equal-height correction only. English card behavior and
all data/commerce logic are intentionally outside the change set.

## Checks performed

1. All PHP runtime/template files pass `php -l`.
2. All JavaScript files pass `node --check`.
3. Production and readable CSS both contain the same RTL-only equal-height rule.
4. A headless Chromium layout harness renders deliberately uneven Arabic card
   content at desktop and mobile widths and confirms all direct product cards in
   one rail have an identical computed height.
5. Static diff confirms no JavaScript, WooCommerce, cashback, personalization,
   inventory, AI-context, hero or template runtime file changed beyond the
   plugin version/cache-bust metadata.

## Boundaries

These are local source, syntax and browser-layout checks. They do not replace a
live qimia.om visual check under the site's exact WoodMart/Elementor/CDN stack.
