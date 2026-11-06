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
const outputMarker = path.join(area, `.hsx-lead-output-${platform}.json`)
fs.mkdirSync(area, { recursive: true })
if (fs.realpathSync(area) !== area) throw new Error('Refusing a symlinked generated build area')
if (fs.existsSync(output)) {
    if (fs.lstatSync(output).isSymbolicLink() || !fs.existsSync(outputMarker)) throw new Error(`Refusing to clean unmarked build output: ${output}; preserve it and explicitly record its provenance first`)
    const owner = JSON.parse(fs.readFileSync(outputMarker, 'utf8'))
    if (owner.schema_version !== 'hsx_lead.generated_build.v1' || owner.platform !== platform || owner.output !== output) throw new Error('Generated output ownership marker does not match this exact platform directory')
    // Only this task-owned, precisely marked platform output is disposable.
    // Other builds, generated input, deployment targets and business data are not.
    fs.rmSync(output, { recursive: true, force: true })
}
fs.writeFileSync(outputMarker, JSON.stringify({ schema_version: 'hsx_lead.generated_build.v1', platform, output }, null, 2))
if (fs.existsSync(input)) {
    if (!fs.existsSync(marker)) throw new Error('Refusing to overwrite an unmarked build input')
    fs.rmSync(input, { recursive: true, force: true })
}
fs.mkdirSync(input, { recursive: true })
fs.writeFileSync(marker, 'Generated build input only; no business data\n')
fs.cpSync(path.join(workspace, 'uni-app/src'), input, { recursive: true, filter: file => path.basename(file) !== 'node_modules' })
fs.cpSync(path.join(plugin, 'uni-app'), path.join(input, 'addon/hsx_lead'), { recursive: true })
// Overlay copying does not propagate deletions. Never revive a retired plugin
// file from an older mounted source tree in this generated, owned input.
for (const retired of [
    'components/cooperation/CoopPlanComparison.vue',
    'components/cooperation/CoopPlansEntryCard.vue',
    'utils/cooperation-plans.ts',
    ...[1, 2, 3, 4].map(rank => `static/cooperation/levels/level-v${rank}.png`),
]) fs.rmSync(path.join(input, 'addon/hsx_lead', retired), { force: true })
// Mirror the framework's generated DIY registration in this isolated build only.
// The real installer discovers components/diy/*/index.vue; never edit its shared source here.
const diyGroupPath = path.join(input, 'addon/components/diy/group/index.vue')
let diyGroup = fs.readFileSync(diyGroupPath, 'utf8')
for (const [name, tag, folder] of [
    ['LeadCooperationEntry', 'diy-lead-cooperation-entry', 'lead-cooperation-entry'],
    ['LeadRoleEntry', 'diy-lead-role-entry', 'lead-role-entry'],
    ['LeadCooperationHero', 'diy-lead-cooperation-hero', 'lead-cooperation-hero'],
    ['LeadBusinessNavigation', 'diy-lead-business-navigation', 'lead-business-navigation'],
    ['LeadProfileIdentity', 'diy-lead-profile-identity', 'lead-profile-identity'],
    ['LeadProfileTools', 'diy-lead-profile-tools', 'lead-profile-tools'],
    ['LeadProfileBenefits', 'diy-lead-profile-benefits', 'lead-profile-benefits'],
    ['LeadProfilePromotion', 'diy-lead-profile-promotion', 'lead-profile-promotion'],
]) {
    if (diyGroup.includes(`component.componentName == '${name}'`)) continue
    const anchor = `                <template v-if="component.componentName == 'ActiveCube'">`
    if (!diyGroup.includes(anchor)) throw new Error('Framework DIY registration anchor changed; refusing an incomplete homepage build')
    const binding='diy'+name
    diyGroup = diyGroup.replace(anchor, `                <template v-if="component.componentName == '${name}'">\n                    <${tag} ref="${binding}Ref" :component="component" :global="data.global" :index="index" @update:componentIsShow="component.componentIsShow = $event" />\n                </template>\n${anchor}`)
    diyGroup = diyGroup.replace(/(<script[^>]*>)/, `$1\nimport ${binding} from '@/addon/hsx_lead/components/diy/${folder}/index.vue'\n`)
}
fs.writeFileSync(diyGroupPath, diyGroup)
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
fs.writeFileSync(config, `import {defineConfig} from 'vite';import uniExport from '@dcloudio/vite-plugin-uni';import WindiCSS from 'vite-plugin-windicss';import MiniProgramTailwind from '@dcasia/mini-program-tailwind-webpack-plugin/rollup';const uni=uniExport.default||uniExport;const plugins=[uni(),WindiCSS({scan:{dirs:[${JSON.stringify(input)}],fileExtensions:['vue','js','ts']}})];if(process.env.UNI_PLATFORM==='mp-weixin')plugins.push(MiniProgramTailwind());export default defineConfig({root:${JSON.stringify(input)},envDir:${JSON.stringify(path.join(workspace,'uni-app'))},plugins,resolve:{dedupe:['vue']},build:{minify:'terser',sourcemap:false}});\n`)
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
