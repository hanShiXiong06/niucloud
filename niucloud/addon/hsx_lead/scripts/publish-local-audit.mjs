import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

// Developer-only artifact installation. Does not contact, upload to or publish a remote host.
const workspace = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const backend = path.join(workspace, 'niucloud')
if (process.argv.length !== 3 || process.argv[2] !== '--local-audit-only') throw Error('Use --local-audit-only after compiling the real H5 and installed admin sources')
const builds = {
  wap: path.join(workspace, 'output/hsx-lead-live-build/h5'),
  admin: path.join(workspace, 'output/hsx-lead-h5-audit-20261106/admin-build'),
}
for (const folder of Object.values(builds)) if (!fs.existsSync(path.join(folder, 'index.html')) || fs.lstatSync(folder).isSymbolicLink()) throw Error('Current local build is missing')
const backup = path.join(backend, 'runtime/hsx_lead_upgrade', `local-static-${new Date().toISOString().replace(/[:.]/g, '-')}`)
fs.mkdirSync(backup, { recursive: true, mode: 0o700 })
for (const [name, source] of Object.entries(builds)) {
  const destination = path.join(backend, 'public', name)
  if (fs.lstatSync(destination).isSymbolicLink()) throw Error('Public path must not be a symbolic link')
  fs.cpSync(destination, path.join(backup, name), { recursive: true })
  // Keep old hashed assets until acceptance; overwrite only files from this successful build.
  for (const entry of fs.readdirSync(source)) if (!entry.startsWith('.')) fs.cpSync(path.join(source, entry), path.join(destination, entry), { recursive: true })
  if (name === 'admin') {
    // Exactly the framework's publish.cjs::solve HTML asset relocation. Do not
    // change import.meta.env.BASE_URL or prefix the actual application routes.
    const html = path.join(destination, 'index.html')
    fs.writeFileSync(html, fs.readFileSync(html, 'utf8').replaceAll('./assets/', '/admin/assets/').replace('./niucloud.ico', '/admin/niucloud.ico'))
  }
  if (name === 'wap') {
    let transformed = 0
    for (const filename of fs.readdirSync(path.join(destination, 'assets'))) {
      if (!/^index-[\w-]+\.js$/.test(filename)) continue
      const file = path.join(destination, 'assets', filename)
      let value = fs.readFileSync(file, 'utf8')
      if (!/router:\{.*?\},darkmode/s.test(value)) continue
      const prefix = 'const match = location.href.match(/\\/wap\\/(\\d*)\\//);'
      // Reuse the framework publish.cjs tenant router transformation, generated artifact only.
      if (!value.includes(prefix)) value = prefix + value.replace(/router:\{.*?\},darkmode/s, 'router:{mode:"history",base:match?`/wap/${match[1]}/`:"/wap/",assets:"assets",routerBase:match?`/wap/${match[1]}/`:"/wap/"},darkmode')
      fs.writeFileSync(file, value); transformed++
    }
    if (!transformed) throw Error('No framework router artifact found; restore backed-up local index before proceeding')
  }
}
const result = { local_artifacts_installed: true, backup, remote_calls: 0, production_release: false }
fs.writeFileSync(path.join(backup, 'result.json'), JSON.stringify(result, null, 2), { mode: 0o600 })
console.log(JSON.stringify(result))
