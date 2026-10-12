import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

const here = path.dirname(fileURLToPath(import.meta.url))
const admin = path.resolve(here, '../../../../../admin')
const require = createRequire(path.join(admin, 'package.json'))
const { createServer, build } = await import(path.join(admin, 'node_modules/vite/dist/node/index.js'))
const { default: vue } = await import(require.resolve('@vitejs/plugin-vue'))
const config = {
    configFile: false,
    root: here,
    plugins: [vue(), { name: 'jdy-fixture-api', enforce: 'pre', resolveId(source, importer) {
        if (source === '../../api' && importer?.includes('hsx_kingdee_jdy/admin/views/workbench')) return path.join(here, 'mock.ts')
    } }],
    resolve: { alias: [
        { find: /^vue$/, replacement: path.join(admin, 'node_modules/vue/dist/vue.esm-bundler.js') },
        { find: /^vue-router$/, replacement: require.resolve('vue-router') },
        { find: /^element-plus$/, replacement: require.resolve('element-plus') },
        { find: 'element-plus/dist', replacement: path.join(admin, 'node_modules/element-plus/dist') },
        { find: /^@element-plus\/icons-vue$/, replacement: require.resolve('@element-plus/icons-vue') }
    ], dedupe: ['vue'] },
    css: { postcss: { plugins: [] } },
    server: { host: '127.0.0.1', port: 5198, strictPort: true, fs: { allow: [path.resolve(here, '../../../../..')] } },
    build: { outDir: '/tmp/hsx-jdy-ui-build', emptyOutDir: true }
}
if (process.argv.includes('--build')) await build(config)
else { const server = await createServer(config); await server.listen(); server.printUrls() }
