// Run with: node niucloud/addon/hsx_erp_integration/tests/build-resolve.mjs
import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { realpathSync } from 'node:fs'
import { dirname, resolve, relative, sep } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'
const here = dirname(fileURLToPath(import.meta.url))
const admin = resolve(here, '../../../../admin')
const req = createRequire(resolve(admin, 'package.json'))
const { resolveConfig } = await import(pathToFileURL(req.resolve('vite/package.json').replace(/package\.json$/, 'dist/node/index.js')))
const config = await resolveConfig({ root: admin, configFile: resolve(admin, 'vite.config.ts') }, 'build', 'production')
const resolveId = config.createResolver()
for (const view of ['adapter', 'connection', 'mapping', 'task', 'failure']) {
    const from = realpathSync(resolve(here, '../admin/views/integration', view + '.vue'))
    for (const spec of ['element-plus/es', 'element-plus/es/components/table/style/css', 'vue', 'vue-router', 'pinia']) {
        const target = await resolveId(spec, from)
        assert.ok(target, view + ': ' + spec)
        assert.ok(relative(admin, target).startsWith('node_modules' + sep), 'not resolved from admin: ' + target)
    }
}
console.log('PASS: 5 pages × 5 shared runtime/style imports resolve from the host admin.')
