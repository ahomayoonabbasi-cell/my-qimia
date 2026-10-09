import {transformSync} from 'esbuild';
import {readFileSync,writeFileSync} from 'node:fs';
for(const extension of ['css','js']){
  const path=new URL(`../qimia-beauty-studio/assets/studio.${extension}`,import.meta.url);
  const {code}=transformSync(readFileSync(path,'utf8'),{loader:extension,minify:true,legalComments:'none',charset:'utf8',target:extension==='js'?'es2020':undefined});
  writeFileSync(new URL(`../qimia-beauty-studio/assets/studio.min.${extension}`,import.meta.url),code);
  console.log(`studio.min.${extension}: ${Buffer.byteLength(code)} bytes`);
}
