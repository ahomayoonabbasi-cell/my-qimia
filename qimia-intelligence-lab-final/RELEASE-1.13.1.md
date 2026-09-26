# Qimia Intelligence Lab 1.13.1

Base: qimia-intelligence-lab-1.13.0-unified-shopping.zip. Replace the existing Lab plugin; keep its internal directory and entry file.

## Scope

The personal Home and Product shelves show five complete cards at viewport widths of 1025px and above. Extra items remain on the existing native scroll-snap track, with existing Previous/Next controls. At five or fewer cards there is no overflow navigation. The personal shelf still uses the same shared card renderer, stock labels, prices, product choices and native purchase buttons. Arabic uses the existing RTL scroll direction. There is no autoplay or new carousel library.

The 1.13.0 limits are intentionally unchanged: six eligible rows per Home/Product tab and four suggestions in the Cart. This release does not expose a full purchase archive or increase catalogue queries just to fill five slots. A customer with fewer eligible items sees only those items. Mobile and tablet breakpoints, other collection rails, and the Cart shelf retain their existing layout rules.

## Narrow review corrections

1. Preserve the newest actual view timestamp when a variation ID is normalized to its parent card. A genuine later view is no longer compared to a default zero timestamp after a previous purchase.
2. For the active value-per-serving filter, sort the already bounded, hydrated/validated shortlist before applying the visible row limit. An eligible lower-cost seventh item is no longer dropped merely because it was outside the first six. Buy Again uses the exact prior purchasable variation's verified cost. Cart applies the same value ordering to its combined, four-item shortlist. This is not a global cheapest-product guarantee across products outside the existing bounded shortlist.
3. Resume one pending shelf refresh after returning from a briefly hidden tab. A context or currency change queued while hidden is not left indefinitely unapplied. Existing debounce, stale-response rejection and one-read-in-flight guards remain.

No candidate/hydration/order history caps were raised. No new database table, timer polling, AI call, external service, or order/payment mutation was added. The value path may evaluate more members of the same existing bounded shortlist before its display cutoff; it is not a claim of literally zero additional CPU work.

## Unchanged

Core product renderer and minified bundle, main CSS and minified CSS, exact repeat-purchase authority, inventory label implementation, verified-filter criteria, templates, home cashback, hero, header, My Qimia integration, order/payment logic and shopping-context contract are byte-identical to 1.13.0. Only the personal shelf PHP/JS/CSS, version/readme and release metadata changed.

## Verification and install

52 PHP logic scenarios, 46 browser scenarios and syntax checks of 20 PHP / 11 JavaScript files passed locally. See docs/VERIFICATION-1.13.1.md for the fixture boundary; live WordPress, payment, Safari and production concurrency were not tested.

Back up files/database. Upload this ZIP as a replacement of the existing plugin; do not create a parallel Lab installation. Purge page/CDN and CSS/JS caches. No new settings or database migration are required. Rollback is the previous 1.13.0 ZIP.
