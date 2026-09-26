# Qimia Intelligence Lab 1.11.2 — headline entry

Based on `qimia-intelligence-lab-1.11.1-unified-cta.zip`.
This is a full replacement of the same Lab plugin folder. My Qimia 2.7.1 is unchanged.

## UI changes

- The existing third hero headline, `YOUR QIMIA.` / `كيميا الخاصة بك.`, becomes the My Qimia link when the workspace is available. Its original text, font size, weight and gradient remain in use. A fine underline identifies the link without adding another pill or icon that could wrap the headline.
- The separate hero My Qimia button is removed. The short supporting copy remains below the headline. The existing mobile Q grid, artwork, goal loading, search and other homepage sections are preserved.
- The link uses the existing `.qil-button-primary` sheen, the same pseudo-element, gradient, easing and 0.55-second transition as Add to cart. It is rendered as headline typography, not as a filled button. It never lifts vertically.
- Keyboard focus, normal link navigation and a minimum 44px mobile target are supported. A passive pointer-down handler starts the same CSS sheen for touch/pen browsers that defer their native `:active` state. It does not cancel a click, delay navigation, add a second tap, fetch data or grant access. Reduced-motion preferences keep the existing disabled-sheen behavior.

## Optional My Qimia / off state

Public availability requires the existing My Qimia environment guard to allow the host and `qh_page_url()` to resolve a published workspace. This is not a per-customer entitlement check: guests still follow the existing login/workspace route when it is available.

When the companion is missing, disabled, blocked on the current host or has no published workspace:

- The third headline stays in place as ordinary gradient text, with no link or fake account-page fallback.
- The header and explainer entries become genuine disabled buttons, keeping their established styling and space.
- The explainer displays an English/Arabic unavailable notice. Bundled hero copy becomes ordinary shopping copy rather than promising unavailable My Qimia access. Custom merchant copy is preserved.

**The existing companion 2.7.1 remains restricted to its approved staging/local environments. This Lab update does not enable the private workspace on `qimia.om` or change any authorization/synthetic-data restrictions. On that production host, unavailable entries intentionally stay inactive.**

## Installation and rollback

1. Back up the current Lab ZIP and database. Test this replacement on staging first.
2. Replace the currently installed Lab with this ZIP. Do not install a second Lab folder. Keep your existing My Qimia companion unchanged.
3. Purge cached HTML and CSS/JS in the active cache/CDN. Also purge cached HTML whenever the companion is toggled, `QH_DISABLE` changes, or the workspace page is published/unpublished: already cached markup cannot re-run a PHP availability check.
4. Check the hero, desktop header and lower explainer in English/Arabic, as a guest and signed in. Check one real login/return flow and the existing cart/checkout on the intended host.
5. To roll back, replace the same Lab folder with the preceding 1.11.1 ZIP and purge cached HTML/assets. No customer records, options or tables are created/deleted by these changes.

## Checks performed in this build

- PHP lint: all 17 Lab PHP files, using the installed PHP 8.4 CLI.
- JavaScript syntax: all 10 Lab JavaScript files, using Node.
- Both edited CSS files parsed without syntax errors.
- 102 local layout cases: 17 widths (320–1920px), two languages, and ready/missing/host-blocked states. No horizontal viewport overflow; active/inactive headline geometry remained consistent.
- Original third-line font size, gradient and text wrapping compared at all 17 widths in both languages. No additional text wrapping was introduced.
- 20 resolver/CTA fixture cases covering both languages and ready, missing, disabled, blocked, missing/draft/trashed page, empty filter, malformed filter and unsafe-scheme states.
- Ten isolated cases using the exact environment/page functions extracted from the unchanged companion: staging, production, mismatched hosts, local development, kill switch and page publication.
- The headline, header and story entries retained the same shared sheen; measured vertical movement on hover was 0px. Click destinations, keyboard Enter, touch press and reduced motion were checked.
- Source/minified CSS, no-orb and no-JavaScript presentation, and custom/saved Elementor copy were checked.
- Byte comparison confirmed unchanged images, base/minified button CSS, homepage section order, all commerce/AI/loading JavaScript and business modules. Only `qh-chrome.js` gains the small cosmetic touch handler. The main Lab file changes only its version identifiers.

These are local component/stub and Chromium checks, not a full WordPress installation test, a physical iPhone/Safari test or a live login/payment/email/AI integration certification. Existing site-specific theme overrides, cached pages, actual account access and live checkout still require a staging smoke test.
