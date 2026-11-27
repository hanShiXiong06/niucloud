import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'
import { readFileSync } from 'node:fs'
import { spawn, execFileSync } from 'node:child_process'
import path from 'node:path'
import net from 'node:net'

const here = path.dirname(fileURLToPath(import.meta.url))
const admin = path.resolve(here, '../../../../../admin')
const require = createRequire(path.join(admin, 'package.json'))
const { createServer, build } = await import(path.join(admin, 'node_modules/vite/dist/node/index.js'))
const { default: vue } = await import(require.resolve('@vitejs/plugin-vue'))
const php = process.env.HSX_PHP_BIN || '/Users/a123/Library/PhpWebStudy/env/php/bin/php'
const statePath = `/tmp/hsx-jdy-acceptance-${process.getuid()}/state.json`
const env = { ...process.env, HSX_JDY_ACCEPTANCE_STATE: statePath }
const phpArgs = ['-d', 'display_errors=0', '-d', 'allow_url_fopen=0', '-d', 'disable_functions=curl_exec,curl_multi_exec,fsockopen,stream_socket_client']
const host = '127.0.0.1:5201'
let state
const config = {
    configFile: false, root: here,
    plugins: [vue(), { name: 'local-acceptance-boundary', configureServer(server) {
        server.middlewares.use((req, res, next) => {
            if (req.headers.host !== host) { res.writeHead(403).end('Local host only'); return }
            if (req.url?.startsWith('/api/')) {
                const origin = req.headers.origin
                if ((origin && origin !== `http://${host}`) || req.headers['x-acceptance-ui'] !== '1'
                    || (req.headers['sec-fetch-site'] && !['same-origin', 'none'].includes(req.headers['sec-fetch-site']))) {
                    res.writeHead(403).end('Same-origin acceptance requests only'); return
                }
                if (req.method === 'POST' && !String(req.headers['content-type']).startsWith('application/json')) { res.writeHead(415).end(); return }
            }
            next()
        })
    } }],
    resolve: { alias: [
        { find: '@/utils/request', replacement: path.join(here, 'request.ts') },
        { find: /^vue$/, replacement: path.join(admin, 'node_modules/vue/dist/vue.esm-bundler.js') },
        { find: /^vue-router$/, replacement: require.resolve('vue-router') },
        { find: /^element-plus$/, replacement: require.resolve('element-plus') },
        { find: 'element-plus/', replacement: path.join(admin, 'node_modules/element-plus/') },
        { find: /^@element-plus\/icons-vue$/, replacement: require.resolve('@element-plus/icons-vue') }
    ], dedupe: ['vue'] },
    css: { postcss: { plugins: [] } },
    server: { host: '127.0.0.1', port: 5201, strictPort: true, cors: false,
        fs: { allow: [here, path.resolve(here, '../../admin'), path.join(admin, 'node_modules')] },
        proxy: { '/api': { target: 'http://127.0.0.1:5202', changeOrigin: true, configure(proxy) {
            proxy.on('proxyReq', req => req.setHeader('X-Acceptance-Key', state.key))
        } } }
    },
    build: { outDir: '/tmp/hsx-jdy-acceptance-build', emptyOutDir: true }
}
if (process.argv.includes('--build')) {
    await build(config)
} else {
    for (const port of [5201, 5202]) {
        await new Promise((resolve, reject) => { const socket = net.createServer(); socket.once('error', reject); socket.listen(port, '127.0.0.1', () => socket.close(resolve)) })
    }
    execFileSync(php, [...phpArgs, path.join(here, 'setup.php')], { env, stdio: ['ignore', 'ignore', 'inherit'] })
    state = JSON.parse(readFileSync(statePath, 'utf8'))
    const backend = spawn(php, [...phpArgs, '-S', '127.0.0.1:5202', path.join(here, 'router.php')], { env, cwd: here, stdio: ['ignore', 'inherit', 'inherit'] })
    const server = await createServer(config)
    const stop = async () => { backend.kill('SIGTERM'); await server.close(); process.exit(0) }
    process.on('SIGINT', stop); process.on('SIGTERM', stop)
    backend.on('exit', code => { if (code && code !== 0) process.exit(code) })
    await server.listen()
    server.printUrls()
    console.log('Isolated ERP acceptance; no external HTTP; no business database writes.')
}
