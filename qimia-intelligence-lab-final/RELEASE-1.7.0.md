# Release 1.7.0-rc.2 — staging release candidate

rc.2 (after live staging verification): the Arabic WooCommerce account-menu item and the product "Save to my routine" link now use the Lab language context. On /ar/ the translator serves an English-locale site, so `is_rtl()` was false and the label was machine-translated as "My كيمياء".

## 1.7.0-rc.1

Base: 1.6.3-health-rc.1 (installed on qimialab, asset hashes verified). Health module 2.2.2-rc.1 → 2.3.0-rc.1. Database schema unchanged (Lab 13, Health 2.2.1).

## Changed
- **My Qimia navigation**: four destinations — Today · Supplements · Food · Account. Old hashes keep working (`#qh-passport` → Account, `#qh-insights`/`#qh-safety` → Supplements sub-views).
- **Today**: today's supplement plan with Taken / Skip / Undo first; food second; the membership strip and training block no longer occupy the main area (Goal Compass still appears when Plus/trial is active).
- **Account hub**: private cashback coupons (read only when Account is opened; copy, explicit apply through WooCommerce Store API with Woo's exact error, resume unpaid order), orders link, compact membership, preferences, "What data is used?", export/erase.
- **AI opener**: every My Qimia AI button goes through `window.QimiaAIEntry.open()` (Commerce 3.52.0) with a safe fallback.
- **Shopping AI bridge**: `training_type` and the `record_training` topic are no longer projected; `instructions($parts,$context)` appends only when the passed context carries the validated projection; `qimia_ai_customer_context_changed_v1` fires on consent, sharing, profile/routine and erase.
- **Profile form**: training fields are shown only for accounts that already recorded training; the form omits the key otherwise, so the server keeps historical training data.
- **Cashback display**: amounts are read from `QCB2_Core::public_policy()` (2.5.0+) or a direct `QCB2_Core::tier()` probe (2.4.x); `cashback_tiers` is never read; if the issuer is inactive the section is hidden.
- Header / account-menu label: My Qimia / ماي كيميا.
- Minified storefront assets (`qil*.min.*`, enqueued when WP_DEBUG is off) are untouched because their sources did not change; `qh-health.js/.css` have no minified variant and are enqueued directly.

## Not changed
Homepage template, hero, assets, section order, header/footer, SEO tags, URLs, JSON-LD, Health encryption, trial/pass logic, synthetic-only staging gates.
