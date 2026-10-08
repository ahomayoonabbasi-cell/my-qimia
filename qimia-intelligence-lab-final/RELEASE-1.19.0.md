# Qimia Intelligence Lab 1.19.0

Base: the supplied `qimia-intelligence-lab-final-1.18.22.zip`. Full replacement
package (the folder inside is still `qimia-intelligence-lab-final`); do not install
a second copy.

## 1. Qimia Refill — repeat purchases without a subscription

Supplements run out on a schedule. Refill turns that schedule into the next order.

- **Opt-in, per product.** After an order, the order received page asks "Want a
  reminder before it runs out?" for each consumable in it (protein, creatine,
  pre-workout, vitamins and the other "Running low?" roles, or any product with a
  verified serving count). Accessories are never offered. Shoppers can also start
  one from **My Account → Refills**, which lists their recent purchases.
- **Interval.** Suggested from the same estimate as "Running low?": the label's
  verified serving count × quantity bought ÷ servings a day (pre-workout, aminos
  and electrolytes five a week). Without a verified count: 30 days. The shopper
  picks any interval (14–90 days, or the suggestion) and can change it.
- **Reminder.** A few days (default 4) before the estimated run-out — payment date
  + 2 days for delivery + interval — one email through WooCommerce's own mailer
  (store header/colours), in the shopper's language (English or Arabic, right to
  left). It lists everything due, with today's price and the run-out date.
- **One tap.** "Refill now" is a signed link (valid 30 days) that puts the exact
  product, flavour and size from the last order into the cart at today's
  WooCommerce price and opens checkout (or the cart, in settings). It never
  doubles the cart if opened twice, never adds anything for a different signed-in
  account, and skips products that are out of stock with an honest notice.
  Nothing is charged and no order is created automatically.
- **Follow-up and rest.** One follow-up five days after the run-out if there was
  no refill. Fourteen days after it, the cycle moves on. Three unanswered cycles
  pause the plan, so nobody keeps getting emails they ignore. Out-of-stock
  products wait a day instead of emailing.
- **The cycle restarts itself.** Any paid order (processing or completed) that
  contains the product restarts the plan from its payment date and becomes the new
  source line (quantity included). Orders placed from a refill link or the Refills
  page are counted as refill orders; a signed-out refill counts for the account
  only when the billing email is the account's.
- **My Account → Refills.** Each plan with its next run-out, today's price,
  "Refill now", interval, **Skip next**, **Pause/Resume** and **Remove**;
  "Refill all due" when several are due. When the shopper has bought the same
  product three or more times, the page shows their real rhythm ("You usually
  reorder every 35 days. Use 35 days").
- **Homepage.** "Running low?" now links to Refills ("Get a reminder before it
  runs out"). The link is the same for every visitor, so cached pages stay valid.
- **Admin.** New **Refills** tab (Settings → Qimia Intelligence Lab): active and
  paused plans, running low within 7 days, and for 30 days: plans started,
  reminder emails, link opens, refill orders and refill revenue. Switches: refills,
  order received invitation, reminder emails, lead days, follow-up, and where
  "Refill now" lands.

### Safety and storage

- Stored in user meta only (`_qil_refill_plans`, `_qil_refill_due`) and three
  small options (`qil_refill`, `qil_refill_stats`, `qil_refill_rewrite`). No
  table, no schema change (`QIL_SCHEMA_VERSION` stays 15).
- Built on the existing, verified Buy Again selection (`qil_repeat_selection`):
  the order and item are re-checked as the account's own on every use.
- Forms are nonce-checked per account; plan ids are validated; the email link is
  HMAC-signed with the site's auth salt and expires.
- The reminder job (WP-Cron, hourly, at most 40 accounts and one email per
  account per run, with a lock) only sends from the live store address
  (`qimia.om` / `www.qimia.om` with the storefront switch on). The staging host
  and other copies never email customers, even under a system cron.
- `define( 'QIL_REFILL_DISABLE', true );` in `wp-config.php` stops everything
  (menu, pages, emails, links). `QIL_DISABLE` stops it too.
- The new My Account endpoint (`qimia-refills`) flushes rewrite rules once after
  this update, only if WooCommerce has no rule for it yet.

## 2. Product cards: readability floor

The design audit (Impeccable detector on the rendered homepage) found 650 text
runs under 11px. The cause: the "six-up" step-down rules still applied to the
four-up rails and to phone cards, so fact labels were 7.5px, buttons 9px and
product names 10px on phones. One block appended to `qil.css` / `qil.min.css`
(the shipped files are byte-identical up to it) sets a floor: labels 10.5px in
sentence case, values 12px, names 13px+ (Arabic 15px), buttons 11.5–12px on one
line ("ADD TO / CART" no longer wraps on phones), badges and stock lines 10–11px,
footer links 13px. Measured: no text became smaller anywhere; text runs under
11px fell from 420 to 297 (English desktop) and from 381 to 233 (Arabic phone).
Card structure, order, prices and actions are unchanged.

## 3. Fix

- 1.18.7's "In your cart" mini-cart divider printed even with
  `QIL_BOOST_DISABLE` or with the ladder and stack switched off. It now follows
  the same switch as the picks, so a disabled mini cart is WooCommerce's own again.

## Unchanged

Cashback issuer and amounts, ladder, flash section, stacks, wallet, "Running
low?" estimates, Buy Again, prices, stock, coupons, checkout, search, comparison,
Qimia AI and every JavaScript file. No new request on the homepage; the new
stylesheet `qil-refill(.min).css` loads only on My Account and order received.

## Update

Keep 1.18.22 for rollback. Upload this ZIP (Plugins → Add New → Upload Plugin →
Replace current with uploaded). Clear page/CSS/CDN caches once. Then:

1. Settings → Qimia Intelligence Lab → **Refills**: check the switches.
2. As a customer with a past order: open My Account → Refills, start a plan,
   press Refill now (lands on checkout with the item), Skip, Pause, Remove.
3. Place a test order with a consumable and check the order received invitation.
4. Optional: in WP Crontab (or `wp cron event run qil_refill_tick`), with a plan
   whose run-out is within 4 days, check the reminder email in English and Arabic.

If the Refills menu item 404s, open Settings → Permalinks and press Save once.
