# Qimia Intelligence Lab 1.11.3 — optional mobile/desktop header entry

Full replacement of the supplied `qimia-intelligence-lab-1.11.2-title-entry.zip`.
The plugin folder is unchanged. The My Qimia 2.7.1 companion is not modified.

## Scope

- Show the existing My Qimia link in the mobile header. At up to 480 CSS pixels,
  it is a 44 × 44px circular account icon; its full English/Arabic accessible name
  remains available through `aria-label`, visually hidden text and `title`.
  Wider mobile/tablet headers keep a compact named pill. Desktop dimensions and
  appearance are unchanged.
- Reuse `.qil-button-primary` and the existing native Add to cart sheen. No new
  animation, library, JavaScript handler, request, cookie or tracking is added.
  Existing touch/keyboard feedback, reduced-motion handling and no-lift rules stay.
- When the unchanged public workspace resolver returns no destination, the header
  renderer returns an empty string. There is no disabled placeholder, empty link
  or reserved CTA cell in either header layout. This includes a missing/disabled
  companion, blocked host and unavailable workspace page.
- Narrow-header gaps/sizing adapt around the optional entry. Menu, logo, search,
  language, currency and cart remain visible in one row. The separate product/shop
  header's very narrow off state is also kept within the viewport.
- The approved hero, Q image and position, headline entry and lower My Qimia box
  are unchanged. Off-state story buttons and static title behavior are unchanged.

## Preserved connections and permissions

Public availability still uses the original `qil_myqimia_url()` resolver and
My Qimia environment/page checks. This is not a customer entitlement check:
a guest can use the existing login/return route when the workspace is available.
The patch does not allow new hosts, change optional consent or enable the private
workspace on production. An unavailable companion on `qimia.om` causes the header
entry to be omitted, as on any unavailable host.

## Installation and cache

1. Back up the current Lab ZIP and database.
2. Replace the existing Lab plugin with this ZIP, keeping the same plugin folder.
   Do not uninstall/reset settings or create a parallel Lab installation.
3. Leave the My Qimia companion and other plugins unchanged.
4. Purge cached HTML/page cache and CSS/JS after the update. Also purge cached HTML
   after activating/deactivating My Qimia, changing its environment switch or
   publishing/unpublishing its workspace. Already cached HTML cannot rerun PHP.
5. Check a phone-sized and a desktop-sized header, English and Arabic, as a guest
   and signed in. Confirm the actual My Qimia login/return URL and cart on staging.
6. To roll back, replace Lab with the preceding 1.11.2 ZIP and purge cache again.

## Local verification

- 17 PHP files passed `php -l`; all 10 unchanged JavaScript files passed syntax checks.
- 368 Chromium layout cases: 23 widths (320–1920px), EN/AR, ready/missing,
  homepage/standalone header, and source/minified base CSS. No tested header-control
  overlap or viewport overflow, and no extra header row. All My Qimia touch targets
  were at least 44px square. Currency/language controls remained visible.
- 16 PHP availability fixtures: EN/AR across ready, absent, disabled, blocked,
  missing/draft/trashed page and filter-disabled states. Unavailable header markup
  was empty. The story renderer and resolver output matched 1.11.2 exactly.
- 12 interaction cases: native sheen background and 0.55s transition matched
  Add to cart; hover displacement was 0px. Touch press, focus and keyboard click
  dispatch retained the expected destination. External navigation was suppressed
  in the local fixture; a live login was not performed.
- 44 baseline comparisons found identical hero, Q, title, supporting text and
  lower-story geometry in EN/AR, ready/missing, across 11 widths. Decorative
  animation was frozen for these comparisons.
- Reduced-motion and no-JavaScript states were checked.
- Only header renderer/CSS and version identifiers changed in runtime code.
  All images, hero markup/CSS, commerce/AI/loading JavaScript, pricing, stock,
  checkout, cashback and private-workspace code remain byte-identical to the input.

These are local component tests with WordPress/WooCommerce stubs and Chromium,
not a live WordPress, physical Safari/iOS, account, checkout, email or AI test.
Existing site/theme overrides and cached pages still require a staging check.
