# Verification — Qimia Intelligence Lab 1.14.2

Scope: default active tab only for the unified personalization shelf.

Verified locally:

- Plugin PHP syntax: all PHP files pass `php -l`.
- JavaScript syntax: all JavaScript files pass `node --check`.
- `Related to your interests` is the first preferred eligible group.
- Existing tab DOM order is not changed.
- Manual tab selection remains protected by `activeByUser` during refreshes.
- Fallback order remains Buy Again, Recently Viewed on Home, then first valid group.
- No CSS files or server-side recommendation generation files changed in this release.

No production deployment, live checkout, or production load test was performed.
