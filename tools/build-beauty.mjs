import {transformSync} from 'esbuild';
import {readFileSync,writeFileSync} from 'node:fs';
for(const extension of ['css','js']){
  const path=new URL(`../qimia-beauty/assets/experience.${extension}`,import.meta.url);
  const {code,warnings}=transformSync(readFileSync(path,'utf8'),{loader:extension,minify:true,legalComments:'none',charset:'utf8',target:extension==='js'?'es2020':undefined});
  if(warnings.length)throw new Error(JSON.stringify(warnings));
  writeFileSync(new URL(`../qimia-beauty/assets/experience.min.${extension}`,import.meta.url),code);
  console.log(`experience.min.${extension}: ${Buffer.byteLength(code)} bytes`);
}
