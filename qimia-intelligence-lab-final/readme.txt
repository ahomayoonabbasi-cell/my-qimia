=== Qimia Intelligence Lab ===
Contributors: qimia
Requires at least: 6.2
Requires PHP: 8.1
Stable tag: 1.18.0
License: GPL-2.0-or-later

Bilingual Qimia storefront, separated from the My Qimia workspace.
Full replacement of the supplied Lab plugin, not a second Lab installation.

== Release 1.18.0 ==
Sales release. Mini cart and cart page: an exact cashback ladder from the issuer's
own bands ("Add 2.400 OMR more → get 4 OMR cashback") with up to three related
products that reach the next band in one tap, and a rule-based "Complete your stack"
band with "Add to my order" (Creatine → Whey / Pre-workout, Whey → Creatine, Fat
burner → Multivitamin / Protein, Magnesium → Ashwagandha / Daily wellness). Homepage:
a rotating "FLASH SALE — 72 HOURS" drop of 8–12 products with real prices, discounts,
stock and end time (plus Instagram links and a JSON feed), a cashback wallet for
signed-in shoppers ("You have 4 OMR cashback" → Shop with cashback, codes never
shown), "Running low?" reorders of the exact product, flavour and size, and cashback
stacks with their contents, real value and cashback tier. No extra request for
guests; cart surfaces ride on WooCommerce fragments. Also fixes the page-language
header on WooCommerce AJAX and an empty screen-tall gap under the cart. Settings →
Qimia Intelligence Lab → Growth; define QIL_BOOST_DISABLE to switch everything off.
See RELEASE-1.18.0.md and docs/VERIFICATION-1.18.0.md.

== Release 1.17.6 ==
CPU-only repeat-context optimization. Shop, category, search and brand browsing no
longer starts a private qil_repeat_context request when no Buy Again surface is
present. Legacy Buy Again loads only for signed-in users, starts in browser idle
time, never queues a duplicate in-flight request, and DOM mutations only re-render
existing inventory locally instead of starting another PHP request. Full Buy Again
reads no longer ask the server to re-check every product already rendered on the
page. Personalization, search, compare, cart, checkout, inventory labels and UI are
otherwise unchanged. See RELEASE-1.17.6.md and docs/VERIFICATION-1.17.6.md.

== Release 1.17.5 ==
Homepage search-image targeting only. The visible hero couple is now the primary
image candidate for the English and Arabic homepages through the existing Rank
Math OpenGraph and JSON-LD output, with max-image-preview:large. The decorative
digital backdrop is explicitly non-content, while the visible couple keeps a real
HTML image with bilingual alt text. Titles, descriptions, canonicals, hreflang,
sitemaps, robots index/follow state, design, commerce, AI and performance behavior
are unchanged. See RELEASE-1.17.5.md and docs/VERIFICATION-1.17.5.md.

== Release 1.17.4 ==
Crawler burst hardening: one crawler render per URL for every page (also pages
that cannot be stored), cached copies and 429s answered before WordPress fully
loads, atomic render budget, session-safe crawler copies, crawler UAs skip the
Lab's AJAX calls, and diagnostics for unstored copies and admin-ajax actions.
See RELEASE-1.17.4.md.

== Release 1.17.2 ==
CPU finalization only. Meta preview requests for one cold URL are collapsed to one
WordPress render; followers wait for that copy and never start a duplicate render.
Catalogue and personalization cold misses use longer single-flight waits and the
version-keyed catalogue cache lasts 90 seconds. On pages where the unified
personalization shelf already returns Buy Again plus inventory, the redundant
qil_repeat_context page-load request is removed and its inventory is reused.
No CSS, templates, product appearance, checkout, pricing or AI behavior changed.
See RELEASE-1.17.2.md and docs/VERIFICATION-1.17.2.md.

== Release 1.17.1 ==
Combined performance release: 1.17.0 plus the catalogue, per-serving, search-ID and
targeted goal-membership caches from 1.16.7. Every price/availability cache is keyed
by currency, country, tax context, exchange-rate settings, user and Woo session;
account/session data never reaches the database cache. See RELEASE-1.17.1.md.

== Release 1.17.0 ==
Traffic-burst protection only; no design, price, stock, cart, checkout or AI change.
Meta / WhatsApp / Telegram preview crawlers share one cached guest copy per page
(ad tracking parameters ignored), one render per URL and a crawler render budget
(429 Retry-After only above it). qil_repeat_context and qil_personalize results are
cached (public for guests, per login session for accounts; tokens always fresh) and
the first inventory read is deferred. WooCommerce's first-load fragment refresh is
answered in the browser when there is no cart. Live search starts at 3 characters
with a 350 ms debounce. Crawler and Lab AJAX requests never spawn WP-Cron; optional
server-cron mode with fail-safe and an overlap lock. Settings → Qimia Intelligence
Lab → Performance. See RELEASE-1.17.0.md and docs/VERIFICATION-1.17.0.md.

== Release 1.15.3 ==
Fit the goal and collections titles on one line across mobile and desktop.
Only the two heading styles are changed.

== Release 1.15.2 ==
Focused presentation update: clearer selected glass tabs, prominent bilingual goal
headings and lightweight transparent cashback artwork. Shopping, recommendations,
cashback policy and AI behavior are unchanged.

== Release 1.15.1 ==
Integrated native AI comparison, stable account-aware shelves, public guest selections,
glass tabs, consistent mobile cards, native swipe carousels, storefront card frames,
and Tiffany nutrition frames. No companion refinement plugin is required.
Existing commerce, account, AI and menu integration contracts are preserved.

== Release 1.14.2 ==
Personalized shelf default-tab correction only. When the `Related to your interests`
group has eligible products, it is now the initially selected tab on desktop and
mobile. Existing tab order is unchanged. A shopper's manual tab choice is still
preserved during background refreshes. If Related is unavailable, the existing
Buy Again / Recently Viewed fallback remains. No product, price, stock, cart, AI,
My Qimia, cashback, Arabic-card sizing or WooCommerce logic changed.
See RELEASE-1.14.2.md and docs/VERIFICATION-1.14.2.md.

== Release 1.14.1 ==
Arabic product-card height normalization only. On RTL storefront product rails,
cards now stretch to the tallest card in the same rail on desktop and mobile,
so longer Arabic/mixed Arabic-English copy cannot leave uneven card bottoms.
English card sizing, product data, prices, labels, purchase flow, personalization,
AI context, cashback, hero and WooCommerce logic are unchanged.
See RELEASE-1.14.1.md and docs/VERIFICATION-1.14.1.md.

== Release 1.14.0 ==
Agent-context bridge release for My Qimia 3.1.0. My Qimia now owns the canonical
browser recent-view context when present; Lab no longer keeps a second
sessionStorage recent-product profile. Exact persistent product views are read
through the My Qimia read-only agent contract, while current search/compare task
context remains separate. WooCommerce still owns price, stock, variation, cart
and order truth. The existing recommendation presentation/attribution safeguards,
Home shelf layout, cards, cashback, hero, filters and staging protections are
unchanged. Lab retains a current-task fallback when My Qimia is unavailable.
See RELEASE-1.14.0.md and docs/VERIFICATION-1.14.0.md.

== Release 1.13.2 ==
Mobile/tablet page-menu typography only: all menu labels, including the category
button, inherit one existing font with uniform size, weight, spacing and casing.
The same personal shelf defaults to eligible Buy Again when own purchases exist;
a shopper's deliberate tab choice remains selected during background refreshes.
No changes to order eligibility, card renderer, five-card carousel, other buttons,
cashback, hero, filters, stock labels, backend queries or database schema.
See RELEASE-1.13.2.md and docs/VERIFICATION-1.13.2.md for local-only test scope.

== Release 1.13.1 ==
Five full desktop cards in personal Home/Product shelves, with the existing
native carousel for overflow; Cart and narrow breakpoints remain unchanged.
Correct variation-view timestamps, value sorting before display limits, and
pending refresh recovery after a short hidden-tab interval. No new dependency,
polling or candidate-limit expansion. Native card skin and purchase controls
are unchanged. See RELEASE-1.13.1.md and docs/VERIFICATION-1.13.1.md.

== Release 1.13.0 ==
Unified native-theme shopping shelves for Home, Product and Cart. Exact own-order
Buy Again; permission-scoped actual views and related category selections;
private bounded hydration, shared existing card renderer, no new AI dependency.
Product placement is below related products. Cart recommendations are limited
to four items and do not replace checkout. The 1.12.2 cashback, label and verified
filter implementations remain unchanged. Shopping Experience settings provide
an enable switch and stable related-selection rollout. Read RELEASE-1.13.0.md
and docs/VERIFICATION-1.13.0.md for boundaries, installation and local test scope.

== Release 1.12.2 ==
Inventory labels and verified filters only. New Arrival defaults to 30 days
(20-day-old products qualify; previously saved custom durations are preserved).
Observed Back in Stock retains a 14-day default, clears old stock cycles and
never guesses missing history. Badges expire while the page remains open.
Stimulant-free and vegan filters use explicit catalogue evidence; missing or
conflicting facts are excluded. Value per serving uses an available variant's
actual price/count pair and sorts ascending. Combined filter chips stay in sync.
All CSS, templates and original native/exact Buy Again purchase actions are
unchanged. Purge cached HTML and assets after replacing the existing plugin.
Read RELEASE-1.12.2.md and docs/VERIFICATION-1.12.2.md for test limitations.

== Release 1.12.1 ==
Buy Again is confined to separate homepage and product-page sections. Cards
reuse the existing Qimia renderer and button styling; normal product cards,
native WooCommerce buttons, variation selectors and quantity inputs are not
replaced. Only exact, currently available previous selections are listed.
New Arrival and Back in Stock labels use the lower-left image corner in English
and lower-right in Arabic, without covering the existing verified badge.
Read RELEASE-1.12.1.md and docs/VERIFICATION-1.12.1.md for scope and checks.

== Release 1.12.0 ==
Truthful New Arrival (45-day default) and observed Back in Stock (14-day
window), plus one-unit, exact-previous-selection Buy Again for signed-in
customers. Same Qimia button treatment; private no-store history hydration;
existing options/guest flow preserved. No synthetic stock history or auto-pay.
Adjust durations in the existing Lab settings. Read RELEASE-1.12.0.md for
installation, limitations and the deployment test checklist.

== Release 1.11.6 ==
Mobile hero only: explicit grid tracks put the AI form below the complete visual
and product rail. Q floats above the painted couple image, clear of the product
cards. The existing geometry callback measures the original object-fit slot;
it makes no service calls and leaves the couple dimensions/settings unchanged.
The approved three-line headline, hidden mobile LIVE signal, My Qimia links and
desktop geometry are preserved. Supersedes the mobile placement rules in 1.11.4
and 1.11.5. Purge the homepage HTML and CSS/JS cache after replacement.
Read RELEASE-1.11.6.md for verification scope and installation.

== Release 1.11.3 ==
Show the existing My Qimia header link on mobile, with a compact 44px account
icon on narrow phones and the original named pill at wider widths. Its native
Add to cart sheen, no-lift behavior and workspace destination are retained.
Unavailable My Qimia renders no header button at all on any screen size.
The approved hero, title link and lower story section are unchanged.
Read RELEASE-1.11.3.md for scope, cache handling and local test limitations.

== Release 1.11.2 ==
YOUR QIMIA becomes the hero entry, using the existing Add to cart sheen.
The separate hero button is removed; optional-workspace availability is checked.
Missing/disabled/host-blocked My Qimia leaves a static title and disabled entries.
No production permissions or customer entitlement checks are relaxed.
Read RELEASE-1.11.2.md for installation, cache handling and local verification.

== Release 1.11.1 ==
Unified My Qimia entry points: existing Add to cart sheen, no hover lift,
compact text/button row, mobile Q beside the headline, and desktop My Qimia CTA.
Existing split setup: replace Lab only; keep My Qimia 2.7.1 unchanged.
Read RELEASE-1.11.1.md for this UI update and its local test scope.

== Release 1.11.0 ==
My Qimia is now supplied by the independent My Qimia 2.7.1 plugin.
Existing workspace identifiers, records, URLs and authorization remain in use.
Mobile: remove the redundant My Qimia header row, raise/fade the hero backdrop,
enlarge and raise the floating Q, and restyle the existing workspace CTA.
Homepage: add the bilingual My Qimia walkthrough immediately before brands.
Goal results: immediate loader, accepted response committed together, explicit
retry on failure, stale response rejection, and preserved pagination results.
No change to the cashback issuer, AI provider, payment or catalogue API logic.

== Installation ==
Read RELEASE-1.14.0.md. Back up the database and current plugin files.
Replace the existing Lab plugin with this ZIP; do not install a parallel Lab.
For the canonical agent-context bridge, use My Qimia 3.1.0 or newer. Lab remains
safe without it and falls back to current-task continuity plus Woo recent hints.
This update does not enable My Qimia on a new production host and does not alter
checkout, payment, cashback issuance or Cloudflare/AI gateway configuration.
Purge cached HTML/CSS/JS after replacing Lab or changing the companion version.
Legacy release documents describe earlier combined/split packages.

== Verification ==
See docs/VERIFICATION-1.14.0.md for this release. Local component checks are not
a live WordPress/WooCommerce, Safari/iOS, login, payment, email, CDN, production
load or AI-service certification. Earlier release documents remain in the package
for their historical scope.
