# Qimia split release — installation and scope

Date: 2026-09-20
Source: qimia-intelligence-lab-8-2-3-1.11.0-rc.2.zip

## Packages and responsibilities

**Qimia Intelligence Lab 1.11.0** replaces the supplied Lab folder
`qimia-intelligence-lab-8-2-3`. It owns the public storefront, its header, hero,
public My Qimia entry points, homepage explanation and catalogue-results loading UI.

**My Qimia 2.7.1** is a new plugin folder `qimia-my-qimia`. It owns the existing
workspace template, scripts, APIs, privacy/access/entitlement logic, food diary,
routines and private account read model. The old runtime names remain for compatibility.

The Lab no longer contains or includes the workspace runtime. Connections use an
optional `qimia_myqimia_url` filter and `qil_home_personal_area` action. Existing
shopping-context events and the shared script handle remain. There is no separate
customer database, account migration or duplicate cashback issuer.

## Important environment limitation

The supplied My Qimia code is staging-only. The existing exact-host and environment
checks have deliberately NOT been loosened. It remains allowed on
`qimialab.qimia.om` and the source's permitted local/development hosts. This split
alone does not enable the private workspace on `qimia.om`. On an unsupported host,
the new Lab entry point falls back to the existing WooCommerce account route.
The Lab's own live/staging setting and sandbox protections remain unchanged.

## Replacement order

1. Back up the database and the existing plugin directory/ZIP. Keep the prior Lab
   package available for rollback. Perform the replacement on the approved staging
   site before changing the live storefront.
2. In WordPress Plugins, upload and activate **qimia-my-qimia-2.7.1.zip** FIRST.
   While the old Lab is active, the companion intentionally defers to its embedded
   workspace and shows an administrator notice. It does not register a second owner.
3. Upload **qimia-intelligence-lab-1.11.0-separated.zip** and use WordPress's
   replacement option for the existing Qimia Intelligence Lab plugin. Keep its folder
   and entrypoint unchanged. Do not run two Lab variants under renamed folders.
4. Keep both new plugins active. Do not create a second My Qimia page or reset any
   `qh_*` options, table contents, customer records, consents, coupons or access grants.
5. Clear the site's cached CSS/JS and page cache after replacement. Existing asset
   version parameters have been updated. Any optimization/CDN layer must serve the
   new versions, not the old combined files.
6. Check the existing My Qimia page and header/home links as a guest and a signed-in
   test customer. Verify access limitations, saved routine/diary, orders and issued
   coupons. Test Arabic/English and the active currencies on the actual theme stack.
7. Click Performance, change goals quickly, exercise Retry and pagination, and check
   the hero and introduction immediately before the brands section on a phone.

## Administration

Lab keeps `Settings > Qimia Intelligence Lab`, with Storefront & Hero and Connections.
My Qimia has `Settings > MY QIMIA`, with its own Overview, My Qimia, Food Photo Analysis
and Access & Settings tabs. Existing My Qimia admin links redirect to the owning
plugin. Hero Studio option names, sanitizers and media controls are unchanged.

## What is not changed

The product catalogue endpoint, pricing/currency/stock rules, product schema code,
payment flow, checkout ownership, cashback issuance/emails/reminders, AI credentials,
provider request code, access policies and sensitive-data consent rules are not
rewritten. The original workspace installer still runs its existing lifecycle;
this release adds no new schema version or data-copy migration. Ordinary page views
do not run an image-generation or new analysis request for the new decorative section.

The My Qimia artwork and the hero/couple images are existing bundled assets. The
couple's Hero Studio settings are not changed. No additional UI framework, video,
font file, tracking endpoint or recurring background job is introduced.

## Goal-results behavior

The first-page loading state begins synchronously on a goal/filter request. Existing
cards are hidden, not replaced by an index-only preview. Only a response accepted by
the existing goal/language/filter/page/currency contract is rendered. A superseded
response cannot overwrite a newer selection. Failure gives a Retry control instead
of silently substituting index matches. Pagination preserves already accepted cards
and retries the failed page. A changed catalogue revision restarts from page one.
There is no artificial minimum loading delay or new transport request.

Both `assets/qil.js` and the production filename `assets/qil.min.js` contain the
same verified implementation. The production filename is intentionally distributed
without a new minifier transformation; no separate, stale runtime is shipped.

## Rollback

Restore the previous Lab ZIP to the same folder. The new My Qimia companion detects
its embedded runtime and defers, so a second workspace runtime is not loaded. The
companion can then be deactivated. Do not delete workspace tables, user data or
settings during rollback. Restore a database backup only through the site's normal
verified backup procedure if it is actually required.

## Test limits

The local browser fixtures use real plugin renderers/assets with mocked WordPress
functions and catalogue transport, not a connected WooCommerce installation. They
verify loading, layout and response-order behavior, not real stock, prices, login,
provider latency, checkout success, database writes or authorization end-to-end.
PHP lint used PHP 8.4; the metadata requires PHP 8.1 and the target site's PHP 8.2
runtime has not been executed here. Chromium was used, not Safari/iOS. No live
site was changed, and error-free production behavior is not guaranteed.
