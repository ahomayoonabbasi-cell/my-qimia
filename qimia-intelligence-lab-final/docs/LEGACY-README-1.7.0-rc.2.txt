=== Qimia Intelligence Lab ===
Contributors: qimia
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.7.0-rc.2
License: GPL-2.0-or-later

Complete replacement package, not a patch or a parallel plugin.
Your Qimia Plus maintenance release for the approved staging site only.

== Release 1.7.0-rc.2 (staging release candidate) ==
rc.2: Arabic account-menu and product-link labels follow the Lab language context
(the translator serves /ar/ on an English-locale site, so is_rtl() was false).

Supplement-first My Qimia (Health module 2.3.0-rc.1): four destinations
(Today · Supplements · Food · Account); supplements first on Today; a private
Account hub with the customer's own cashback coupons (copy / apply / resume),
orders link, compact membership and "What data is used?".
Shopping-AI bridge: no training type or training-coaching topic is shared;
bridge text is gated by the context Commerce actually used in the request;
consent/sharing/erase changes signal Commerce to invalidate cached context.
Homepage cashback amounts now come only from the active cashback issuer;
a stale saved cashback_tiers value can no longer change the display.
Header label: My Qimia / ماي كيميا. No homepage layout, SEO, schema or
database change. Pair with Commerce 3.52.0-rc.1 and Cashback 2.5.0-rc.1.

== Release 1.6.3 ==
Read-only homepage cashback section after product collections, before brands.
Reads QCB2 campaign tiers (including the configurable 50–60 band) and site FX.
Exact OMR / approximate whole-unit foreign display; native AR/EN routing.
No new JavaScript, AJAX, jobs, orders, coupons, email or Health changes.
The header remains name-only. See RELEASE-1.6.3.md.

== Release 1.6.2 ==
Name-only Your Qimia header link in both languages and desktop/mobile navigation.
Removes the header membership badge, two-line spacing and its network request.
30-day trials, OMR 3 plans, private dashboard and Commerce bridge are unchanged.
Database schema remains 2.2.1; no new data migration or trial reset is introduced.
See RELEASE-1.6.2.md for this focused update and installation notes.

== Release 1.6.1 ==
Versioned storage upgrade and explicit repair; compact membership status;
valid trial activation states; coordinated header; unified routine card grid.
See RELEASE-1.6.1.md and QIMIA-HEALTH-README.md for install, limits and privacy.
Commerce 3.51.7 remains the paired, independent assistant. Gateway unchanged.

== Important ==
Install on qimialab.qimia.om with synthetic test accounts only.
Replace qimia-intelligence-lab-8-2; never activate two Lab variants together.
The existing storefront code is retained; Health has no production switch.
Back up database, plugin files and WordPress salts before replacing.
Open Qimia Health as an administrator after upgrade and check diagnostics.
Do not delete profile, diary or membership tables to fix a setup error.
No automatic trial, account creation, order or payment on installation.

== Local acceptance ==
Passed local checks are described in the accompanying test report; not a
claim of error-free operation on untested hosting, WordPress/MySQL, Safari,
translation, Worker or payment combinations. Not a 50,000-user load test.
