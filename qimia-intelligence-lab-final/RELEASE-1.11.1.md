# Qimia Intelligence Lab 1.11.1 — unified My Qimia entry points

Base: the supplied `qimia-intelligence-lab-1.11.0-home-cta-fix.zip`.
This is an update to the existing Lab plugin, not another plugin or a workspace replacement.

## Presentation changes

- The hero, desktop header and public explainer links use the existing `qil-button qil-button-primary` classes. The existing shared CSS that also styles `qil-buy` owns the gradient, top rim, shadow and left-to-right `::after` sheen. No new sheen, keyframes or button animation was created. The accumulated custom My Qimia button styles, including vertical hover transforms, were removed.
- The hero copy and a compact 44px-minimum-height My Qimia link share a non-wrapping flex row in both languages. The text can wrap naturally inside its own column; the button does not become a second row or a full-width block.
- Compact default copy: “Shop to join My Qimia — your routine, orders & cashback, together.” Arabic: “انضم إلى ماي كيميا مع شرائك — روتينك وطلباتك وكاش باكك معاً.” Only exact previously bundled Elementor lead defaults are normalized; custom copy is preserved. No saved setting is written.
- On mobile/tablet up to 980px, the existing Q shares the eyebrow/headline grid area rather than reserving an empty row. The three headline strings and existing artwork remain. The two-column layout is enabled only when the existing Show Orb setting is enabled. Desktop hero geometry is unchanged.
- The desktop header Ask Qimia AI action becomes a My Qimia anchor using the same existing URL resolver as the other entry points. The new anchor has no AI-opener attribute. The header entry is hidden at mobile/tablet widths; search, language/currency, cart and navigation remain. Other existing AI controls and their handlers are unchanged.
- The existing explainer button uses the same primary-button treatment. Collection padding no longer reserves space for a hidden personal panel when the public explainer immediately follows it.
- The plugin and enqueue version is bumped to 1.11.1 to invalidate the previous asset URLs.

## Unchanged scope

The independent My Qimia 2.7.1 plugin is not modified or bundled. Its permissions, domain restrictions, records, workspace routes and integration filter remain unchanged. The URL resolver and its existing account fallback are unchanged. No migrations, external calls, dependencies, tracking or new JavaScript are introduced. All original JavaScript, artwork, shared base CSS/minified CSS, cashback code, catalogue/product/API code and other non-presentation implementations remain byte-identical to the supplied ZIP. The only change to the main plugin PHP is the two version identifiers.

## Local verification

- PHP syntax: all 17 PHP files passed the installed PHP parser.
- JavaScript syntax: all 10 JavaScript files passed `node --check`; their contents are unchanged.
- CSS: all 8 stylesheet files passed top-level parsing with tinycss2.
- Browser layout: 68 cases passed in Chromium: English and Arabic, source and production/minified base styles, at widths 320, 360, 375, 390, 414, 430, 600, 768, 980, 981, 1024, 1100, 1101, 1180, 1280, 1440 and 1920 pixels.
- The layout checks covered side-by-side text/button positioning, minimum button height, viewport overflow, header controls and breakpoint visibility, headline/Q separation, three unbroken default mobile headline lines, shared link destinations, the hidden private panel and inherited reduced-motion behavior.
- Six hover checks compared all three entry points in both languages against the existing `qil-buy` pseudo-element properties. The original sheen matched and the buttons' measured vertical movement was 0px.
- Fourteen additional renderer/chrome checks passed: hidden orb, preserved custom copy, old bundled lead defaults, absent workspace-provider account fallback, and header visibility when homepage-only CSS is absent.

These are local component renderings using the actual section PHP with WordPress helpers stubbed, the real bundled styles/images, and the installed browser/fonts. They are not a live WordPress/WooCommerce installation, authentication test, Safari/iOS test, API/payment test, or production certification. No live website was changed during preparation.

## Install over the current split setup

1. Replace the current Lab with this ZIP in the same plugin folder: `qimia-intelligence-lab-8-2-3`.
2. Keep the existing independent My Qimia plugin active and unchanged. Do not install a second Lab or recreate the account/workspace page.
3. Clear the frontend/page cache and check the homepage in English and Arabic on mobile and desktop. Confirm the three links reach the existing My Qimia/account destination.

Rollback: replace Lab with the previous ZIP. This update writes no settings or data, so there is no UI-specific data migration to reverse.
