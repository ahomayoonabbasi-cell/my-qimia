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

## Claude Code design toolkit

Project skills in `.claude/skills/` load in every Claude Code session on this
repository (cloud or local); `.mcp.json` adds the Playwright browser.

| What | Source (commit) | License |
| --- | --- | --- |
| `emil-design-eng`, `animate`, `review-animations`, `improve-animations`, `find-animation-opportunities`, `animation-vocabulary`, `apple-design`, `mobile-native`, `break-ui`, `prototype` | [emilkowalski/skills](https://github.com/emilkowalski/skills) (`e8a175d`) — the Swift, Expo, Sonner and React library-picker skills are left out | MIT |
| `impeccable` + `.claude/agents/impeccable-*.md` | [pbakaus/impeccable](https://github.com/pbakaus/impeccable) (`778c8a7`, engine 0.1.11) | Apache-2.0 |
| `design-taste-frontend`, `redesign-existing-projects` | [Leonxlnx/taste-skill](https://github.com/Leonxlnx/taste-skill) (`b482f7a`) | MIT |
| `figma-use`, `figma-design-to-code`, `figma-generate-design`, `figma-create-new-file`, `figma-generate-library` | [figma/mcp-server-guide](https://github.com/figma/mcp-server-guide) (`f049329`, plugin 2.2.127) | Figma Developer Terms |
| Playwright MCP (`.mcp.json` → `tools/playwright-mcp.mjs`) | `@playwright/mcp@0.0.83` from npm | Apache-2.0 |

- Impeccable's detector engine downloads itself on first use into `~/.impeccable/`;
  `.claude/settings.json` sets `IMPECCABLE_NO_TELEMETRY`. Its automatic edit hooks are
  not installed; `/impeccable` asks for a detector run at the end instead.
- `tools/playwright-mcp.mjs` uses the cloud image's Chromium, headless, without the
  sandbox under root and through `HTTPS_PROXY` (with `NO_PROXY` as bypass list), so the
  local test server (`php -S 127.0.0.1:8766 tests/browser/server.php`) and allowed
  sites both open. Snapshots land in the ignored `.playwright-mcp/`.
- Figma: connect the Figma connector at claude.ai (Settings → Connectors); cloud
  sessions cannot reach `mcp.figma.com` directly. Locally without the connector:
  `claude mcp add --transport http figma https://mcp.figma.com/mcp`.
- To update a skill, copy its folder again from the upstream commit you want and
  change the commit in this table.
