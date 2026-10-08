# Verification — Qimia Intelligence Lab 1.19.0

## Scope and baseline

Input: `qimia-intelligence-lab-final-1.18.22.zip` as supplied. Runtime files that
differ from it:

- New: `includes/refill.php`, `assets/qil-refill.css`, `assets/qil-refill.min.css`.
- `qimia-intelligence-lab.php` (version, one `require_once`).
- `includes/class-qil-admin.php` (Refills tab), `includes/member-hub.php` (two
  helpers made public, one link under "Running low?"), `includes/commerce-boost.php`
  (divider follows the Boost switch).
- `assets/qil.css`, `assets/qil.min.css`, `assets/qil-boost.css`,
  `assets/qil-boost.min.css`: blocks appended at the end; the shipped content is a
  byte-identical prefix of each file.

No JavaScript file changed (all 18 are byte-identical to 1.18.22).

## Executed checks

- PHP syntax (PHP 8.3.6): **28 files**, all passed.
- JavaScript syntax (`node --check`): **18 files**, all passed.
- CSS parsing (esbuild): **15 stylesheets**, all passed.
- PHP scenarios over WordPress/WooCommerce doubles, each in a clean process:
  **265 passed, 0 failed** (`verification-1.19.0/php-harness.json`). 93 of them
  cover Qimia Refill: eligibility and interval from label servings; ownership,
  item and status checks; the 12-plan limit; interval/skip/pause/resume/remove;
  the job index; reminder timing (no email on day one, reminder, one follow-up,
  cycle roll, pause after three misses, out-of-stock wait, mail-failure retry);
  English and Arabic emails through WooCommerce's mailer; signed links (tampered,
  foreign-account, expired, malformed, replayed, already reordered, out of stock);
  checkout attribution and paid-order restart (processing → completed counted
  once; signed-out refills matched by billing email only); the account page,
  order received invitation, nonce-checked forms, settings sanitizer, admin tab,
  staging and `QIL_REFILL_DISABLE`; escaping of hostile product names.
- Chromium 141.0.7390.37 (Playwright) against the real templates and assets
  served by the PHP router: **223 passed, 0 failed**
  (`verification-1.19.0/browser.json`). New: the full refill flow on desktop and
  phone — order received → Remind me → My Account → change interval → Refill now
  → checkout with the exact variation; the email link signed out; a tampered link;
  Arabic right-to-left page; no text under 13px, every control ≥ 44px, no
  overflow. Product cards: no card text under 10px and no wrapped card button, on
  English/Arabic × desktop/phone.
- Impeccable detector: 0 findings on `qil-refill.css` and on the appended
  `qil.css` block.
- Font sizes of every visible text run on the rendered homepage (EN/AR,
  desktop/phone) were compared before/after the readability block: none smaller.

## Limits

These are not live WordPress/WooCommerce/WoodMart tests. The doubles model the
WooCommerce calls the plugin makes (orders, cart, session, mailer, notices,
account endpoint); the router's account/order received/checkout pages are
theme-shaped stand-ins, not the production theme. Not tested: real WP-Cron
timing, real email delivery and rendering in mail clients, WooCommerce block
checkout's Store API hook on a live store, payment gateways, HPOS storage on the
production database, Safari/WebKit, or server load. Nothing was installed on the
live store. Product images and texts in the screenshots are test fixtures.

## Install / acceptance check

See `RELEASE-1.19.0.md` → Update.
