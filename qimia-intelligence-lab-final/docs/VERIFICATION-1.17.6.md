# Verification — 1.17.6

Scope: repeat-context CPU reduction only.

## Passed

- PHP syntax: 22/22 runtime PHP files.
- JavaScript syntax: 14/14 JavaScript files.
- Production asset and readable source both contain the no-surface guard for `data-qil-repeat-section`.
- Legacy guest pages do not start `qil_repeat_context`.
- The former 80 ms automatic repeat-context start is absent.
- DOM mutation refresh is local-only and does not call `loadRepeatContext(true)`.
- Full Buy Again request body omits the visible-product inventory list.
- In-flight repeat-context calls are deduplicated rather than queued.
- QIL version bumped to 1.17.6 so cached 1.17.5 JavaScript is not reused.

## Intentionally unchanged

Qimia AI, unified personalization logic, search, compare, add-to-cart, checkout, cashback, membership, templates, CSS and PHP commerce logic.
