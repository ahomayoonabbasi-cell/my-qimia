# Qimia Intelligence Lab 1.18.2

Design follow-up to 1.18.1 from the live store. Presentation only: no data, price,
cashback or cache logic changed.

- **Stacks:** the 1.18.0 cashback badge is back ("CASHBACK +3 OMR", dark with a
  gold line) in the image's top corner opposite the theme's discount label, so the
  two never overlap (mirrored in Arabic). The discount label keeps the theme's own
  look and corner and no longer rolls with a role label.
- **Flash drop:** the stock line ("Only 3 left in Chocolate", with its bar) sits
  under the product name on every card, at one height, instead of on the image.
  A card without a known count shows "In stock" with no bar.
- **Flash drop section:** the stage casts no shadow and the section fades from the
  page colour above to the white of the next section, so there is no two-tone edge.
  The signed-in wallet section starts in the same white as the section above it, and
  the stacks section uses the page colour of its neighbours.

Files: `qimia-intelligence-lab.php` (version), `assets/qil-boost.js`,
`assets/qil-boost.min.js`, `assets/qil-boost.css`, `assets/qil-boost.min.css`,
`readme.txt`, `BUILD-MANIFEST.json`; added `RELEASE-1.18.2.md`,
`docs/VERIFICATION-1.18.2.md`, `docs/verification-1.18.2/*`.
