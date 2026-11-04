import { mkdir, writeFile, copyFile } from 'node:fs/promises'
import { createRequire } from 'node:module'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

// Deterministic code-native icons. This is an authoring script, not a runtime dependency.
const plugin=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..')
let sharp
try { sharp=createRequire(import.meta.url)('sharp') }
catch(error){
    if(!process.env.HSX_IMAGE_NODE_MODULES)throw new Error('Authoring only: install sharp in your Node environment or set HSX_IMAGE_NODE_MODULES. Prebuilt icons are already included.')
    sharp=createRequire(path.join(path.dirname(process.env.HSX_IMAGE_NODE_MODULES),'package.json'))('sharp')
}
const shapes={
    deliveries:'<path d="M8 5H6a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="8" y="2" width="8" height="5" rx="1.5"/><path d="m8 14 2.5 2.5L16 11"/>',
    stores:'<path d="M4 10v10a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V10M9 21v-7h6v7M3 10l2-7h14l2 7"/><path d="M3 10a3 3 0 0 0 4.5 2.6A3 3 0 0 0 12 12.6a3 3 0 0 0 4.5 0A3 3 0 0 0 21 10"/>',
    wallet:'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M17 5V3H5a2 2 0 0 0-2 2M21 10h-5a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h5"/><circle cx="17.5" cy="13" r=".6" fill="currentColor" stroke="none"/>',
    profile:'<circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
}
const dest=path.join(plugin,'resource/diy/tabbar')
await mkdir(dest,{recursive:true})
for(const [name,body]of Object.entries(shapes))for(const selected of [false,true]){
    const color=selected?'#3478cc':'#737e8c',filename=name+(selected?'-selected':'')
    const svg=`<svg xmlns="http://www.w3.org/2000/svg" width="81" height="81" viewBox="0 0 28 28"><g transform="translate(2 1)" fill="none" stroke="${color}" color="${color}" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round">${body}</g></svg>`
    await writeFile(path.join(dest,filename+'.svg'),svg+'\n')
    await sharp(Buffer.from(svg)).png().toFile(path.join(dest,filename+'.png'))
}
// Optional local copy is bounded to the public directory of this exact plugin.
if(process.argv.includes('--copy-local-public')){
    // plugin is backend/addon/hsx_lead: public is backend/public, not repository/public.
    const actual=path.resolve(plugin,'../../public/addon/hsx_lead/diy/tabbar')
    await mkdir(actual,{recursive:true})
    for(const name of Object.keys(shapes))for(const selected of [false,true]){
        const file=name+(selected?'-selected':'')+'.png'
        await copyFile(path.join(dest,file),path.join(actual,file))
    }
}
console.log('Built 8 transparent PNG navigation icons (81 x 81) with SVG sources; no external assets.')
