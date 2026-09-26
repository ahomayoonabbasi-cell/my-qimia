// Rebuilds the minified storefront assets that ship next to their sources.
// esbuild, --minify, es2020 — the settings that reproduce the shipped 1.17.6
// qil.min.js. The shipped qil-personalization.min.js keeps Arabic text as UTF-8
// instead of \u escapes, so that file is written the same way.
//   node tools/build-assets.mjs            -> assets changed in 1.18.0
//   node tools/build-assets.mjs qil.js ... -> named sources only
import { transformSync } from 'esbuild';
import { readFileSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const assets = join(dirname(fileURLToPath(import.meta.url)), '..', 'qimia-intelligence-lab-final', 'assets');
const defaults = ['qil.js', 'qil-personalization.js', 'qil-boost.js', 'qil-boost.css'];
const utf8 = new Set(['qil-personalization.js']);
const sources = process.argv.slice(2).length ? process.argv.slice(2) : defaults;

for (const source of sources) {
	const loader = source.endsWith('.css') ? 'css' : 'js';
	const target = source.replace(/\.(css|js)$/, '.min.$1');
	const input = readFileSync(join(assets, source), 'utf8');
	const { code } = transformSync(input, {
		loader, minify: true, legalComments: 'none',
		target: loader === 'js' ? 'es2020' : undefined,
		charset: utf8.has(source) ? 'utf8' : 'ascii',
	});
	writeFileSync(join(assets, target), code);
	console.log(`${source} (${Buffer.byteLength(input)} B) -> ${target} (${Buffer.byteLength(code)} B)`);
}
