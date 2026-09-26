# Verification — Qimia Intelligence Lab 1.14.0

## Source reviewed

The supplied 1.13.4 package was used as the base. The package already contained
the approved 1.13.4 personal-shelf placement and its existing My Qimia/WooCommerce
integration code.

## Checks performed

1. All 20 PHP runtime/template files pass `php -l`.
2. All 12 JavaScript files, including the new fallback, pass `node --check`.
3. The packaged canonical `assets/qimia-shopping-context.js` is byte-identical
   to My Qimia 3.1.0's canonical asset (SHA-256
   `891e5ec699115c66ae4babaf1b3e1b62633d403ebe5ccbf5fa885564b70b4ba5`).
4. A JavaScript harness verifies canonical search/select/compare handoff, bounded
   compare IDs, recent-view passthrough and fallback context event dispatch.
5. Two isolated PHP enqueue harnesses verify that My Qimia 3.1.0 owns the runtime
   `qimia-shopping-context` source when available and that Lab uses its fallback
   source when My Qimia is unavailable.
6. An isolated PHP contract harness verifies that the My Qimia recent-view filter
   deduplicates by product, preserves newest timestamps and rejects invalid/future
   rows before Lab uses them.
7. Static checks verify that QIL personalization no longer contains the old
   `qil-recent-products-v1` session key or writes a second recent-view session
   store, and that it consumes My Qimia recent views and refreshes on exact
   `qimia:recent-product-view` events.
8. A source/build diff confirms no CSS, Home template, product-card renderer,
   cashback module, checkout/staging guard or inventory/filter file changed.

## Runtime files changed

- `qimia-intelligence-lab.php` — version only.
- `readme.txt` — release/install notes.
- `assets/qil-personalization.js` — consume canonical recent views; remove second
  Lab-owned recent session store.
- `assets/qimia-shopping-context.js` — canonical My Qimia 3.1.0 mirror.
- `assets/qil-shopping-context-fallback.js` — Lab-only current-task fallback.
- `includes/customer-experience.php` — deterministic provider ownership/enqueue.
- `includes/personalization.php` — consume the My Qimia exact persistent-view
  contract when available.

## Boundaries

These are source, syntax and isolated contract tests. They are not a live
qimia.om database/login test, real WooCommerce checkout/payment test, Safari/iOS
device test, Cloudflare cache/load test, production concurrency test or end-to-end
Qimia AI model test. No claim is made that an LLM can be mathematically guaranteed
never to produce an error; this release narrows context ownership and data-source
ambiguity before the final Qimia AI agent build.
