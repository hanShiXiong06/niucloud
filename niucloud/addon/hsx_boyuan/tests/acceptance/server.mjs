import { createRequire } from 'node:module'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { readFileSync } from 'node:fs'
import { spawn, execFile } from 'node:child_process'
const here = dirname(fileURLToPath(import.meta.url)), root = resolve(here, '../../../../..')
const require = createRequire(resolve(root, 'admin/package.json'))
const { createServer } = await import(resolve(root, 'admin/node_modules/vite/dist/node/index.js'))
const vue = (await import(require.resolve('@vitejs/plugin-vue'))).default
const php = process.env.BOYUAN_PHP || 'php'
const statePath = process.env.HSX_BOYUAN_ACCEPTANCE_STATE
if (!statePath) throw new Error('Set HSX_BOYUAN_ACCEPTANCE_STATE to setup.php output')
const state = JSON.parse(readFileSync(statePath, 'utf8'))
const args = ['-d', 'error_reporting=22527', '-d', 'display_errors=0', '-d', 'disable_functions=curl_exec,curl_multi_exec,fsockopen,stream_socket_client']
const backend = spawn(php, [...args, '-S', '127.0.0.1:5196', resolve(here, 'router.php')], { env: process.env, stdio: ['ignore', 'ignore', 'pipe'] })
process.on('exit', () => backend.kill())
backend.stderr.on('data', chunk => { if (String(chunk).includes('Failed to listen')) process.stderr.write(chunk) })
const loopbackGuard = { name: 'isolated-api-origin', configureServer(server) { server.middlewares.use((req, res, next) => {
    if (req.url?.startsWith('/api') && req.headers.origin && req.headers.origin !== 'http://127.0.0.1:5199') { res.statusCode = 403; res.end('Local acceptance origin required'); return }
    next()
}) } }
const server = await createServer({ root: here, publicDir: resolve(here, '../../resource/site-app'), configFile: false, plugins: [loopbackGuard, vue({ template: { compilerOptions: { isCustomElement: tag => ['view','text','scroll-view'].includes(tag) } } })], resolve: { alias: {
    '@bridge-page': resolve(root, 'site-uniapp/src/addon/hsx_boyuan/pages/workbench/index.vue'),
    '@/utils/request': resolve(here, 'request.ts'), '@dcloudio/uni-app': resolve(here, 'lifecycle.ts'),
    vue: resolve(root, 'site-uniapp/node_modules/vue/dist/vue.esm-bundler.js'), '@element-plus/icons-vue': require.resolve('@element-plus/icons-vue'),
}, dedupe: ['vue'] }, server: { host: '127.0.0.1', port: 5199, strictPort: true, fs: { allow: [root, here] }, proxy: { '/api': { target: 'http://127.0.0.1:5196', headers: { 'X-Acceptance-Key': state.key } } } } })
let working = false
const tick = () => { if (working) return; working = true; execFile(php, [...args, resolve(here, 'tick.php')], { env: process.env }, error => { working = false; if (error) process.stderr.write('Acceptance worker failed; inspect isolated test service.\n') }) }
const timer = setInterval(tick, 2200)
await server.listen(); console.log('Boyuan isolated acceptance: http://127.0.0.1:5199/ (no external writes)')
for (const signal of ['SIGINT','SIGTERM']) process.on(signal, async () => { clearInterval(timer); backend.kill(); await server.close(); process.exit(0) })
backend.on('error', error => { console.error(error.message); process.exitCode = 1 })
