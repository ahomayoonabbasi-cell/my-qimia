# Qimia Intelligence Lab 1.17.6

CPU-only repeat-context optimization based on production access-log behavior.

Runtime changes are intentionally limited to the main QIL storefront JavaScript plus the plugin version used for browser cache busting:

- Shop, category, search and brand pages do not call `qil_repeat_context` merely because product cards are present.
- Public New Arrival / Back in Stock labels continue to come from the existing server-rendered WooCommerce hooks and expire locally in the browser.
- Legacy Buy Again requests are made only when a Buy Again surface exists and the frontend response is authenticated.
- Legacy Buy Again starts during browser idle time rather than 80 ms after first paint.
- An in-flight repeat-context request is never followed by an automatically queued duplicate.
- MutationObserver updates only reposition/re-render existing inventory locally; they do not trigger another `wc-ajax` request.
- Full Buy Again reads no longer submit all visible product IDs for a redundant inventory re-check; exact previous-item inventory remains part of the private Buy Again payload.

No Qimia AI, search, compare, personalization ranking, cart, checkout, membership, cashback, styling, templates or product data logic was changed.
