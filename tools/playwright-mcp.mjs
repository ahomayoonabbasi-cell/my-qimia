// Starts the Playwright MCP server that .mcp.json registers for Claude Code, so
// Claude can open the storefront, click through it and take screenshots.
// Claude Code cloud sessions run as root with no display, reach the web only
// through HTTPS_PROXY and ship Chromium at /opt/pw-browsers/chromium (not the
// revision this Playwright expects); anywhere else @playwright/mcp keeps its
// defaults. A PLAYWRIGHT_MCP_* variable that is already set always wins.
//   node tools/playwright-mcp.mjs [extra @playwright/mcp flags]
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';

const env = { ...process.env };
const proxy = env.HTTPS_PROXY || env.https_proxy;
const noProxy = env.NO_PROXY || env.no_proxy;
const cloudChromium = '/opt/pw-browsers/chromium';
const defaults = {
	PLAYWRIGHT_MCP_EXECUTABLE_PATH: existsSync(cloudChromium) ? cloudChromium : '',
	PLAYWRIGHT_MCP_HEADLESS: process.platform === 'linux' && !env.DISPLAY && !env.WAYLAND_DISPLAY ? 'true' : '',
	// Chromium refuses its sandbox under root (--no-sandbox; the README's PLAYWRIGHT_MCP_NO_SANDBOX is not read).
	PLAYWRIGHT_MCP_SANDBOX: process.getuid?.() === 0 ? 'false' : '',
	PLAYWRIGHT_MCP_PROXY_SERVER: proxy || '',
	// Playwright sends loopback through the proxy too, so the local test server needs the bypass list.
	PLAYWRIGHT_MCP_PROXY_BYPASS: proxy && noProxy ? noProxy : '',
};
for (const [name, value] of Object.entries(defaults)) if (value && !env[name]) env[name] = value;

const windows = process.platform === 'win32';
const server = spawn(windows ? 'npx.cmd' : 'npx', ['-y', '@playwright/mcp@0.0.83', ...process.argv.slice(2)], { env, stdio: 'inherit', shell: windows });
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => server.kill(signal));
server.on('exit', (code, signal) => process.exit(signal ? 1 : code ?? 0));
