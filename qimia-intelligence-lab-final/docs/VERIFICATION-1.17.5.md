# Verification — 1.17.5

- Scope: homepage primary image signals only.
- English route `/` and Arabic route `/ar/` share the visible hero couple as the primary search/social image.
- Rank Math title/description/canonical/hreflang filters are not registered by this release.
- Existing robots directives are preserved; only `max-image-preview:large` is set on the two home routes.
- Product/category/account/checkout routes are not modified by the new SEO image filters.
- Hero CSS and all shopper JavaScript are byte-identical to 1.17.4.
- Homepage layout is unchanged; only semantic attributes on existing hero images changed.
- PHP lint and JavaScript syntax verification must pass before packaging.
