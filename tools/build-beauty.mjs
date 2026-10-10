// Rebuilds Qimia Beauty's minified assets with esbuild (UTF-8, es2020).
// experience.min.* reproduce the shipped 2.4.0 files byte for byte; beauty.min.* and
// beauty-native-cards.min.* shipped from another minifier and rebuild equivalent.
//   node tools/build-beauty.mjs                -> experience.css/js
//   node tools/build-beauty.mjs beauty.js ...  -> named sources only
import {transformSync} from 'esbuild';
import {readFileSync,writeFileSync} from 'node:fs';
const sources=process.argv.slice(2).length?process.argv.slice(2):['experience.css','experience.js'];
for(const source of sources){
  const loader=source.endsWith('.css')?'css':'js';
  const path=new URL(`../qimia-beauty/assets/${source}`,import.meta.url);
  const target=source.replace(/\.(css|js)$/,'.min.$1');
  const {code,warnings}=transformSync(readFileSync(path,'utf8'),{loader,minify:true,legalComments:'none',charset:'utf8',target:loader==='js'?'es2020':undefined});
  if(warnings.length)throw new Error(JSON.stringify(warnings));
  writeFileSync(new URL(`../qimia-beauty/assets/${target}`,import.meta.url),code);
  console.log(`${target}: ${Buffer.byteLength(code)} bytes`);
}
