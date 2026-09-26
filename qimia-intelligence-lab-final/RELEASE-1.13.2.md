# Qimia Intelligence Lab 1.13.2

Base: `qimia-intelligence-lab-1.13.1-five-card-carousel.zip`. Replace the existing
plugin; keep its folder and activation. This is not a second plugin.

## Only two requested changes

1. The existing mobile/tablet page menu (through its existing 1100px breakpoint)
   uses a consistent inherited font, 14px size, 600 weight, 1.5 line height,
   normal spacing and no forced uppercase on each link and category button.
   Nested textual labels inherit the same typography. Arabic and English use
   the existing font stacks and direction. No font files or libraries added.
2. The existing personal shelf defaults to its real `again` group whenever that
   group is present. This also handles a private refresh adding purchases after
   contextual suggestions. An explicit click/keyboard tab choice is retained
   during subsequent refreshes, unless the tab is no longer eligible or the
   account binding changes. No extra shelf or fabricated Buy Again group.

## Deliberately unchanged

The existing backend still validates ownership of linked completed/processing
orders, exact product/variation, refunds, visibility, stock, selected filters and
current price. A customer needs an eligible available previous purchase; missing
historical variation choices are not guessed and guest email is not used to
claim an order. The existing bounded recent-order source is not expanded.

The five-card desktop layout, overflow carousel, mobile product layout, original
card renderer, normal product buttons, cart limits, cashback, hero, labels,
filters, account permissions and database schema are byte-identical to the base.
Only two assets and the plugin version metadata change at runtime. The new
frontend state is page-local; no database, cookie, timer or request is added.

## Verification and deployment

32/32 PHP contract scenarios, 26/26 browser scenarios and syntax checks of all
20 PHP and 11 JavaScript files passed locally. Read `docs/VERIFICATION-1.13.2.md`
for test doubles and environment boundaries. This is not a live-site, payment,
Safari, font-download or production load test.

Back up, replace the existing plugin ZIP, then purge page, asset and CDN caches.
Existing settings are retained. Rollback is the previous 1.13.1 ZIP; no data
migration is performed by this release.
