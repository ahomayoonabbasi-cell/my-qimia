# Your Qimia Plus · 1.6.1 staging maintenance release

Lab `1.6.1-health-rc.1` · Health `2.2.1-rc.1` · Commerce `3.51.7` unchanged · gateway `3.51.4` unchanged.

## What changed
- Centralized six-table, versioned QH_Installer lifecycle: admin-only upgrades after replacement, read-only schema diagnostics, explicit repair, transient failure backoff, atomic lock, InnoDB verification. Old readiness option flags alone are not accepted.
- No table drops, data migration to Commerce, automatic trial start, profile reset, page publication or product publication during an automatic upgrade.
- Trial activation fails closed if eligibility or billing cannot be read. Existing trial/payment records are retained.
- Today has one compact membership strip; full plan detail stays in Profile. Unsupported/loading/error/demo-disabled states do not advertise an actionable trial button.
- Header status on the workspace is driven by the same response as the dashboard; outside it the existing private status request remains. Error text is neutral, not a fictitious trial entitlement.
- Canonical supplement card grid replaces contradictory desktop/mobile declarations. The compact mobile plan uses a dedicated full-width action row to avoid compressed text.
- Existing nutrition, diary, routine, check-in, reviewer, consent, AI projection and price/stock ownership are retained.

## Upgrade
Back up the staging database, plugin folder and security salts. Replace the existing `qimia-intelligence-lab-8-2` folder using WordPress upload/replace. Do not activate parallel copies. Open Qimia Health as an administrator. Check all six storage diagnostics. Explicit `Verify / repair storage` handles repair; do not delete tables or profiles to resolve a setup message. Leave Commerce 3.51.7 active.

Account storage remains opt-in and limited to authorised synthetic test accounts. `Interactive demo` means a saved workspace has not been created for that account; it is not a storage failure. Creating a profile does not start a trial. Trial activation requires the user to confirm the trial form separately.

## Preserved commercial terms
30 x 24 hours free, once per WordPress account, no automatic debit. Then OMR 3 base price/calendar month with manual renewal by default; real WooCommerce Subscriptions and a compatible gateway are required for recurring payments. Old OMR 8 pricing is not invented. Diary/export/erasure remain available after the trial. Health erasure does not reset trial eligibility; account deletion and Woo order privacy are distinct.

## Validation boundaries
The attached report distinguishes current local tests from deployment-specific acceptance. Actual PHP/encryption and SQLite are exercised, with test doubles for WordPress, WooCommerce, MySQL metadata and dbDelta. UI tests use actual release CSS/JS in a local header fixture; native URL navigation and HTTP are simulated. No browser policy was changed. Real MySQL/WordPress, existing translation plugin, Safari/iOS, Worker/model requests, gateway/subscription renewals, high-load operation and professional security/clinical/legal validation remain outside this local validation.

## Rollback
Restore the previous Lab ZIP only. Storage is intentionally not erased. Do not overwrite live WooCommerce orders with a staging database. `define('QH_DISABLE', true);` disables the Health module. Keep security salts stable for existing encrypted records.
