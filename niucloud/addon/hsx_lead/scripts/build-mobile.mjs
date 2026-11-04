import fs from 'node:fs'
import path from 'node:path'
import { createRequire } from 'node:module'
import { spawnSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'

// Isolated compilation of real installation sources. Never runs publish.cjs or an installer.
const plugin = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const workspace = path.resolve(plugin, '../../..')
const require = createRequire(path.join(workspace, 'uni-app/package.json'))
const JSON5 = require('json5')
const platform = process.argv[2] || 'mp-weixin'
if (!['mp-weixin', 'h5'].includes(platform)) throw new Error('Usage: node scripts/build-mobile.mjs [mp-weixin|h5]')
const area = path.join(workspace, 'output/hsx-lead-live-build')
const input = path.join(area, 'input'), output = path.join(area, platform)
const marker = path.join(input, '.hsx-lead-generated')
if (fs.existsSync(input)) {
    if (!fs.existsSync(marker)) throw new Error('Refusing to overwrite an unmarked build input')
    fs.rmSync(input, { recursive: true, force: true })
}
fs.mkdirSync(input, { recursive: true })
fs.writeFileSync(marker, 'Generated build input only; no business data\n')
fs.cpSync(path.join(workspace, 'uni-app/src'), input, { recursive: true, filter: file => path.basename(file) !== 'node_modules' })
fs.cpSync(path.join(plugin, 'uni-app'), path.join(input, 'addon/hsx_lead'), { recursive: true })
// The framework HTML entry normally sits above src; this isolated root is src itself.
fs.writeFileSync(path.join(input, 'index.html'), fs.readFileSync(path.join(workspace, 'uni-app/index.html'), 'utf8').replace('src="/src/main.js"', 'src="/main.js"'))
const pages = JSON5.parse(fs.readFileSync(path.join(input, 'pages.json'), 'utf8'))
const dictionary = fs.readFileSync(path.join(plugin, 'package/uni-app-pages.php'), 'utf8').match(/<<<EOT\s*([\s\S]*?)\s*EOT/)
if (!dictionary) throw new Error('Plugin page dictionary not found')
const registered = JSON5.parse('[\n' + dictionary[1] + '\n]')
if (registered.length !== 1 || registered[0].root !== 'addon/hsx_lead') throw new Error('Unexpected plugin page dictionary')
pages.subPackages = [...(pages.subPackages || []).filter(item => item.root !== 'addon/hsx_lead'), registered[0]]
fs.writeFileSync(path.join(input, 'pages.json'), JSON.stringify(pages, null, 2))
const manifestPath = path.join(input, 'manifest.json')
const manifest = JSON5.parse(fs.readFileSync(manifestPath, 'utf8'))
manifest['mp-weixin'] ||= {}
manifest['mp-weixin'].setting = { ...manifest['mp-weixin'].setting, minified: true, minifyWXSS: true, minifyWXML: true }
fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2))
fs.symlinkSync(path.join(workspace, 'uni-app/node_modules'), path.join(input, 'node_modules'), 'dir')
const config = path.join(area, 'vite.config.mjs')
fs.writeFileSync(config, `import {defineConfig} from 'vite';import uniExport from '@dcloudio/vite-plugin-uni';import WindiCSS from 'vite-plugin-windicss';import MiniProgramTailwind from '@dcasia/mini-program-tailwind-webpack-plugin/rollup';const uni=uniExport.default||uniExport;const plugins=[uni(),WindiCSS({scan:{dirs:[${JSON.stringify(input)}],fileExtensions:['vue','js','ts']}})];if(process.env.UNI_PLATFORM==='mp-weixin')plugins.push(MiniProgramTailwind());export default defineConfig({root:${JSON.stringify(input)},plugins,resolve:{dedupe:['vue']},build:{minify:'terser',sourcemap:false}});\n`)
const dependencyLink = path.join(area, 'node_modules')
if (!fs.existsSync(dependencyLink)) fs.symlinkSync(path.join(workspace, 'uni-app/node_modules'), dependencyLink, 'dir')
// Ensure integration checks compile the actual framework transports, not a stale prototype adapter.
for (const file of ['utils/request.ts', 'components/pay/pay.vue']) {
    if (!fs.readFileSync(path.join(input, file)).equals(fs.readFileSync(path.join(workspace, 'uni-app/src', file)))) throw new Error('Framework transport differs: ' + file)
}
const result = spawnSync(process.execPath, [path.join(workspace, 'uni-app/node_modules/@dcloudio/vite-plugin-uni/bin/uni.js'), 'build', '-p', platform, '--config', config], {
    cwd: path.join(workspace, 'uni-app'), stdio: 'inherit',
    env: { ...process.env, UNI_CLI_CONTEXT: path.join(workspace, 'uni-app'), UNI_INPUT_DIR: input, UNI_OUTPUT_DIR: output, UNI_PLATFORM: platform, NODE_ENV: 'production', NODE_OPTIONS: '--max-old-space-size=4096' },
})
if (result.error || result.status !== 0) throw new Error('Real-source compilation failed; no deployment or upload was attempted')
if (platform === 'mp-weixin') {
    const checked = spawnSync(process.execPath, [path.join(plugin, 'scripts/check-wxss.mjs'), '--dir', output], { stdio: 'inherit' })
    if (checked.error || checked.status !== 0) throw new Error('Native WXSS check failed')
}
console.log(JSON.stringify({ compiled: true, platform, output, source: 'Current framework and plugin sources with real request/payment adapters', installed: false, deployed: false, uploaded: false }))
