# Qimia Intelligence Lab 1.17.5

Focused homepage search-image release.

## Changed
- English `/` and Arabic `/ar/` use the visible hero couple as the Rank Math Facebook/Twitter image candidate.
- Rank Math WebPage/CollectionPage JSON-LD points `image` and `primaryImageOfPage` to the same visible couple image.
- Homepage robots metadata is only extended with `max-image-preview:large`; existing index/follow directives are preserved.
- A stable `rel=image_src` hint points to the same image.
- The digital-world hero backdrop is marked decorative (`aria-hidden`, empty alt) and the couple image has bilingual alt text.

## Explicitly unchanged
No title, description, canonical, hreflang, sitemap, URL, layout, CSS, shopper JavaScript, WooCommerce, pricing, stock, cart, checkout, My Qimia, cashback, AI or crawler-performance behavior was changed.

Google ultimately chooses search-result thumbnails; this release only gives it a much stronger, consistent primary-image signal.
