# Verification — 1.18.2

Presentation release; method as in VERIFICATION-1.18.0.md.

| Suite | Result |
| --- | --- |
| PHP syntax (all PHP files) | 26/26 |
| JavaScript syntax (all JS files) | 16/16 |
| PHP scenarios | 159 passed, 0 failed |
| Chromium | 191 passed, 0 failed |

New Chromium checks (English and Arabic, desktop 1440px and phone 390px):

- Every flash card has its stock line directly under the product name, none on the
  image, and all stock lines have one height.
- Stack cards carry the "CASHBACK +3 OMR" badge and no role label. On a discounted
  stack the discount sits in the start corner, still (no animation, full opacity),
  the cashback in the end corner, and their boxes do not intersect.
- The flash stage has no outer shadow; its section's background ends in white and
  the next section is white.
- The signed-in wallet section's background starts in white and the section above
  it is white.

Not verified here: a live WordPress/WooCommerce/WoodMart/LiteSpeed install.
