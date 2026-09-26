# Verification — Qimia Intelligence Lab 1.17.2

Static/build verification performed in the build container. Production Hostinger/LiteSpeed load testing is not available here.

- All PHP files: `php -l` pass.
- All JavaScript files: `node --check` pass.
- Production and source QIL bundles both contain the same request-collapse behavior.
- CSS files and templates are byte-identical to 1.17.1.
- No new cron, polling loop, database table or external dependency was added.
- Crawler lock followers no longer execute the previous duplicate-render fall-through.
- Catalogue cache remains version/market/pricing keyed and now waits longer before failover.
- Personalization-owned pages skip only the redundant initial repeat-context read; pages without a personalization shelf retain the prior endpoint path.

Live checkout, production load, CDN behavior and host-specific PHP-worker limits still require post-deploy observation.
