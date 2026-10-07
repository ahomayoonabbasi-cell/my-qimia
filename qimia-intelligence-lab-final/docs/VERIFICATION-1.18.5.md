# Verification — 1.18.5

## Scope and environment

The exact supplied 1.18.4 archive was extracted and compared byte-for-byte with this build. Seven pre-existing files changed before adding release evidence: `templates/home.php`, the main plugin PHP file, `assets/qil.js`, `assets/qil.min.js`, `assets/qil-boost.css`, `assets/qil-boost.min.css`, and `readme.txt`. `BUILD-MANIFEST.json` was then updated. No original file was removed. All other original runtime files are byte-identical, including price/stock, flash-sale selection, cashback, member, search, comparison and AI modules.

Tests ran under PHP 8.4.23, Node 22.16.0, and Chromium 144.0.7559.96 via Playwright. The local browser used an isolated document, empty fixture cookies, synthetic product data and no live endpoints. It is not the user's authenticated browser.

## Current results

| Suite | Result | Evidence |
| --- | --- | --- |
| PHP syntax | 26/26 files passed | `verification-1.18.5/static-results.json` |
| JavaScript syntax | 16/16 files passed | `verification-1.18.5/static-results.json` |
| CSS parse | 12/12 files passed | `verification-1.18.5/static-results.json` |
| PHP template/enqueue fixtures | 64 checks passed, 0 failed | `verification-1.18.5/php-results.json` |
| Cart browser fixtures | 94 checks passed, 0 failed | `verification-1.18.5/cart-results.json` |
| Homepage browser fixtures | 167 checks passed, 0 failed | `verification-1.18.5/home-results.json` |

## What the checks cover

**PHP:** execution of the actual home template with section doubles, in English/Arabic, with or without a flash drop and visible member area; one flash position after the goal results and before the personal shelf; live noindex behaviour unchanged. The actual cart-enqueue block was executed with WordPress/theme function doubles, checking cart/non-cart pages, registered/unregistered handles, retained core cart/fragment scripts, retained manual drawer modules, and the existing fragment-configuration preservation filter.

**Cart:** both `qil.js` and `qil.min.js`; simulated theme handlers before and after QIL, plus a delayed theme callback; present, missing and hidden drawers; native and fallback paths; `isCart`, body-class and Cart Block detection; the Blocks event without jQuery; event bubbling to a simulated document-level Woo listener and one totals update per success; no extra refresh event with supplied fragments; another mobile/login/search panel's shared overlay preserved; unrelated modal overlay untouched; explicit bag opening; repeated additions after cart markup replacement; bounded observer cleanup and pagehide cleanup; other-page mini-cart behaviour retained; no-shell early exit.

**Layout:** the real flash PHP section renderer with a controlled payload and the real product-card/flash JavaScript and stylesheets. Widths 1440, 900, 390 and 320 px; English and Arabic; hidden/visible member shell; standalone embedding; a single section; continuous outer background and white-to-ice fade; no document horizontal overflow; existing four/three/two-card breakpoints; matched card heights; working carousel movement; no unexpected fixture fetch; matching source/minified layout geometry. Desktop and mobile/RTL screenshots were visually reviewed. Placeholder names/images were used, not live catalogue data.

## Performance scope

The relocation is server-side and CSS-only for layout. The new cart guard is created only after a successful cart-page addition and is short-lived; it is not a polling loop, body-wide subtree observer or background request. Tests verified cleanup and no new fetch in the isolated fixtures. These statements do not establish a measured production CPU improvement or a peak-concurrency guarantee.

## Not verified

No deployment, authenticated live store, real WooCommerce order/payment, third-party theme runtime, cache/CDN production configuration, Safari/WebKit, mobile device hardware, PHP 8.2 runtime or load test was available. Actual WooCommerce owns network updates; fixtures checked that its expected event path remains available but did not execute a real backend cart calculation. Previously included versioned verification reports remain historical.
