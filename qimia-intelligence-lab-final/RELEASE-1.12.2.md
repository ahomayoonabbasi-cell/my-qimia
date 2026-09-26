# Qimia Intelligence Lab 1.12.2

Source: `qimia-intelligence-lab-1.12.1-buy-again-fix.zip` (version 1.12.1).
Replace the existing Lab plugin. The original internal plugin directory is retained.

## Scope

Only inventory-label accuracy and the three existing verified-filter controls are
updated. No CSS file, template, hero, header, My Qimia, cashback integration, native
purchase action or exact-item Buy Again action has been edited. The original
purchase-history query, ownership checks, exact-selection validation, cart writes
and duplicate-request protection are byte-identical to version 1.12.1.

## Inventory labels

- New Arrival defaults to 30 elapsed days from the first recorded publication
  date. A published, available, purchasable product added 20 days ago qualifies.
  Saving an edit does not reset its age. Existing products use their stored
  publication date until it is preserved on an edit. Previously saved custom
  durations remain unchanged; the existing settings accept 0–90 days, with 0 off.
- Back in Stock defaults to 14 elapsed days after an observed, persisted
  `outofstock -> instock` transition. Ordinary quantity increases while already
  in stock, unknown initial metadata, draft imports and initial publication are
  not fabricated restocks. Leaving in-stock clears the preceding restock cycle.
- A variation uses its parent's age and its own recorded restock. One flavour's
  return does not label every sibling or falsely mark the parent as restocked;
  a parent must have its own actual stock-status transition.
- Stock state, quantity, purchasability, publication and image eligibility remain
  guarded. Deadline attributes and one next-deadline browser timer remove expired
  badges without waiting for another page load. Fresh responses carry server time.
- Existing bottom-left English / bottom-right Arabic styling is untouched.
  Public availability checks are serialized in batches of at most 64 rendered
  product IDs; products after the first batch are no longer silently skipped.

Restocks from before tracking was installed are not guessed. Direct SQL writes
that bypass WordPress/WooCommerce metadata hooks cannot be observed. Dates changed
before this tracking code existed cannot be reconstructed from missing history.

## Verified filters

- Attribute names are normalized across case, spaces and separators. Explicit
  dietary categories/tags, structured attributes, and clear product-specific
  statements can supply evidence. Unknown data stays unknown; conflicting or
  explicit negative claims exclude the product rather than guessing eligibility.
- Stimulant-free is not inferred from caffeine-free alone. Known positive caffeine
  amounts and conflicting labelled stimulant amounts veto a stimulant-free claim.
- Vegan-friendly requires an explicit vegan claim. Vegetarian, plant-based,
  dairy-free or an absence of animal wording alone is not proof of vegan status.
  This is catalogue-evidence filtering, not an independent ingredient certification.
- Value per serving requires an exact, positive serving count. Grams, capsule
  counts, ranges, ambiguous or conflicting counts are not treated as servings.
  For variable products, the calculation pairs the current eligible variation's
  display price with its serving count; sold-out/disabled choices are skipped.
  Parent count inheritance is limited to flavour-only variations, not size packs.
  The original main price and cart price behavior is unchanged.
- When Value per serving is selected, eligible results sort by their verified
  cost per serving ascending. The existing relevance order remains when it is off.
  Selecting multiple chips applies their intersection. Duplicate controls keep
  matching active/ARIA states. English/Arabic explanatory titles are provided.
- Existing delayed-request cancellation, stale-response rejection, retry controls,
  market-specific cache isolation and private REST response handling are retained.

## Performance boundaries

No AI call, cron job, product-table migration or catalogue-wide background scan is
added. Existing lazy goal retrieval and its 60-second isolated card cache are used;
value calculations run only for the value-filter request and its cache entry.
Stock badges use the existing context endpoint, bounded serial batches and a
single deadline timer instead of repeated polling. Production load was not tested.

## Installation

1. Back up the current plugin and database.
2. Upload this ZIP and replace the existing Qimia Intelligence Lab plugin.
   Do not install it as a second Lab plugin or delete customer/order data.
3. Purge the cached page HTML and CSS/JS assets in the site's existing cache/CDN.
4. Verify a 20-day-old product, a known future observed restock, and the three
   filters in English/Arabic. Check the configured label durations when customized.
5. Confirm a regular product/cart action and an exact Buy Again selection on the
   real store. These checks need the store's own theme, data and integrations.

## Verification

Local checks passed: 19 PHP syntax files, 10 JavaScript syntax files, 8 CSS parses,
107 isolated PHP assertions and 80 browser assertions over four EN/AR
mobile/desktop cases. Both source and production JavaScript bundles were exercised.
The browser used the exact bundle with a test location/network adapter because
URL navigation is disabled in this container. WordPress/WooCommerce were mocked
for logic tests. No live WordPress installation, payment or production load was tested.
See `docs/VERIFICATION-1.12.2.md` and `BUILD-MANIFEST.json`.
