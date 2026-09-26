# Qimia Intelligence Lab

WordPress/WooCommerce storefront plugin for qimia.om (English and Arabic).

| Path | What it is |
| --- | --- |
| `qimia-intelligence-lab-final/` | The plugin exactly as it ships (the folder name inside the zip). |
| `dist/` | Installable zips (Plugins → Add New → Upload Plugin). |
| `tests/php/` | Scenarios that run the real plugin code over WordPress/WooCommerce doubles, one PHP process each. |
| `tests/browser/` | Chromium checks of the real homepage, mini cart and cart page, served by a PHP router over the same doubles. |
| `tools/build-assets.mjs` | Rebuilds the shipped `.min.js` / `.min.css` files from their sources. |

Release notes and verification reports live inside the plugin folder
(`RELEASE-<version>.md`, `docs/VERIFICATION-<version>.md`).

## Working on it

```sh
npm ci                  # esbuild + jQuery for the tooling
npm run build           # after editing qil.js, qil-personalization.js or qil-boost.js/.css
npm run test:php        # PHP 8.1+
npm run test:browser    # needs Playwright with Chromium (PLAYWRIGHT_PATH / CHROMIUM_PATH if not installed locally)
```

Package a release from the repository root:

```sh
zip -r -X -9 dist/qimia-intelligence-lab-<version>.zip qimia-intelligence-lab-final
```

`tests/`, `tools/` and `node_modules/` are never part of the plugin zip.
