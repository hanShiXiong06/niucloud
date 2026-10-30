import { readFile, readdir, mkdir, writeFile, rename, rm } from 'node:fs/promises'
import { createHash, randomUUID } from 'node:crypto'
import { spawnSync } from 'node:child_process'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

// Explicit paths only: unrelated dirty worktrees, .env and user attachments cannot enter this archive.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const output = path.join(root, 'output/hsx-lead-1.1.0-release-20261030')
const roots = ['niucloud/addon/hsx_lead', 'admin/src/addon/hsx_lead', 'uni-app/src/addon/hsx_lead']
const files = []
async function visit(relative) {
    for (const item of await readdir(path.join(root, relative), { withFileTypes: true })) {
        if (['.vite','node_modules','.DS_Store'].includes(item.name)) continue
        const name = path.posix.join(relative, item.name)
        if (item.isSymbolicLink()) throw new Error('Symlink rejected: '+name)
        if (item.isDirectory()) await visit(name)
        else if (item.isFile()) files.push(name)
    }
}
for (const folder of roots) await visit(folder)
files.sort()
const manifest = []
for (const name of files) {
    if (/\.env(?:\.|$)|cURL\.txt|\.pem$|\.key$|\.zip$|\.log$/i.test(name)) throw new Error('Unexpected release file: '+name)
    const content = await readFile(path.join(root,name))
    if (/eyJ[A-Za-z0-9_-]{40,}\.[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}/.test(content.toString('utf8'))) throw new Error('Unexpected inline authentication token: '+name)
    manifest.push({ path: name, bytes: content.length, sha256: createHash('sha256').update(content).digest('hex') })
}
await mkdir(output,{recursive:true})
await writeFile(path.join(output,'manifest.json'),JSON.stringify({version:'1.1.0',built_at:new Date().toISOString(),framework_files:[],files:manifest},null,2)+'\n')
const archive = path.join(output,'hsx-lead-1.1.0-source.zip')
const temporary = path.join(output,'.package-'+randomUUID()+'.zip')
// zip reads a bounded file list from stdin, never the entire workspace.
const result=spawnSync('zip',['-q','-X',temporary,'-@'],{cwd:root,input:files.join('\n')+'\n',encoding:'utf8'})
if(result.status!==0) { await rm(temporary,{force:true}); throw new Error('Archive creation failed') }
await rename(temporary,archive)
const zip=await readFile(archive)
const hash=createHash('sha256').update(zip).digest('hex')
await writeFile(path.join(output,'SHA256SUMS'),hash+'  '+path.basename(archive)+'\n')
console.log(JSON.stringify({archive,bytes:zip.length,source_files:files.length,sha256:hash,framework_files:0}))
