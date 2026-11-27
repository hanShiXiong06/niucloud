const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const resolve = name => require(require.resolve(name, { paths: [process.cwd(), path.resolve(process.cwd(), 'admin')] }))
const ts = resolve('typescript')
const { parse, compileScript, compileTemplate } = resolve('@vue/compiler-sfc')
const source = fs.readFileSync(path.join(__dirname, 'desktop-layout.ts'), 'utf8')
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText
const context = { exports: {} }
vm.runInNewContext(compiled, context)
const { erpMobileDestination, erpTableHeight, erpHeaderPreferenceKey, readErpHeaderCollapsed, writeErpHeaderCollapsed } = context.exports
const routes = ['workbench', 'purchase', 'sale', 'purchase_return', 'sale_return', 'stock', 'stocktake', 'payable', 'receivable']
for (const route of routes) {
    const url = `/site/hsx_erp/${route}`
    const target = route === 'workbench' ? 'dashboard/index' : `${route}/list`
    for (const ua of ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)', 'Mozilla/5.0 (Linux; Android 14) Mobile Safari']) {
        assert.equal(erpMobileDestination(url, ua), `/adminapp/addon/hsx_erp/pages/${target}`)
    }
    for (const ua of ['Mozilla/5.0 (iPad; CPU OS 18_0)', 'Mozilla/5.0 (Macintosh; Intel Mac OS X)', 'Mozilla/5.0 (Linux; Android 14) Safari', 'Mozilla/5.0 (Windows NT 10.0)']) {
        assert.equal(erpMobileDestination(url, ua), null)
    }
}
assert.equal(erpMobileDestination('/site/hsx_erp/config', 'iPhone'), null)
assert.equal(erpMobileDestination('/site/phone_shop/goods', 'iPhone'), null)
assert.equal(erpTableHeight(900, 400), 442)
assert.equal(erpTableHeight(720, 530), 240)
assert.equal(erpTableHeight(1024, 430, 68), 526)
assert.equal(erpTableHeight(720, -20), 662)
assert.equal(erpTableHeight(768, 200) - erpTableHeight(768, 420), 220, 'freed header space goes to table')

const preferences = new Map()
const storage = { getItem: key => preferences.get(key) ?? null, setItem: (key, value) => preferences.set(key, value) }
const key = erpHeaderPreferenceKey('stock', 100005, 75)
assert.equal(readErpHeaderCollapsed(storage, key, 1366, 768), true, 'low-height first visit')
assert.equal(readErpHeaderCollapsed(storage, key, 1024, 1366), true, 'iPad first visit')
assert.equal(readErpHeaderCollapsed(storage, key, 1920, 1080), false, 'roomy first visit')
writeErpHeaderCollapsed(storage, key, false)
assert.equal(readErpHeaderCollapsed(storage, key, 1366, 768), false, 'saved expanded overrides compact default')
writeErpHeaderCollapsed(storage, key, true)
assert.equal(readErpHeaderCollapsed(storage, key, 1920, 1080), true, 'saved collapsed survives reload on large screen')
for (const other of [erpHeaderPreferenceKey('sale', 100005, 75), erpHeaderPreferenceKey('stock', 100006, 75), erpHeaderPreferenceKey('stock', 100005, 76)]) {
    assert.notEqual(other, key, 'preferences isolated by page, site and user')
    assert.equal(readErpHeaderCollapsed(storage, other, 1920, 1080), false)
}
preferences.set(key, 'corrupt')
assert.equal(readErpHeaderCollapsed(storage, key, 1366, 768), true)
const blockedStorage = { getItem() { throw Error('blocked') }, setItem() { throw Error('quota') } }
assert.equal(readErpHeaderCollapsed(blockedStorage, key, 1920, 1080), false)
assert.doesNotThrow(() => writeErpHeaderCollapsed(blockedStorage, key, true))
assert.equal(readErpHeaderCollapsed(undefined, key, 1280, 720), true)

const components = ['ErpDesktopPage', 'ErpDataTable', 'ErpRoleFocus', 'ErpFinanceOverview', 'ErpFinanceSourceMeta', 'ErpSettlementCards', 'ErpWorkspaceHeader']
for (const relative of [...routes.map(route => `views/erp/${route}/${route === 'workbench' ? 'index' : 'list'}.vue`), ...components.map(name => `components/${name}.vue`)]) {
    const filename = path.resolve(__dirname, '..', relative)
    const text = fs.readFileSync(filename, 'utf8')
    const { descriptor, errors } = parse(text, { filename })
    assert.equal(errors.length, 0, `${relative}: SFC parse`)
    const script = compileScript(descriptor, { id: relative })
    const template = compileTemplate({ id: relative, filename, source: descriptor.template.content, compilerOptions: { bindingMetadata: script.bindings } })
    assert.equal(template.errors.length, 0, `${relative}: template compile`)
    for (const style of descriptor.styles) assert.doesNotMatch(style.content, /(?:background(?:-color)?|color):\s*#(?:fff(?:fff)?|f8fafc|111827|64748b)\b/i, `${relative}: theme-aware surfaces and text`)
    assert.doesNotMatch(descriptor.template.content, /\b(?:bg-white|to-white|text-(?:gray|slate)-\d+)\b/, `${relative}: theme-aware utility colors`)
    if (relative.startsWith('views/') && !relative.includes('workbench')) {
        assert.match(text, /<ErpDesktopPage/)
        assert.match(text, /<ErpDataTable/)
        assert.match(text, /<ErpWorkspaceHeader page=/)
        assert.ok(text.indexOf('</ErpWorkspaceHeader>') < text.indexOf('<el-tabs'), 'status tabs remain accessible')
        assert.match(text, /erp-pagination/)
    }
}
const read = relative => fs.readFileSync(path.resolve(__dirname, '..', relative), 'utf8')
assert.match(read('components/ErpFinanceSourceMeta.vue'), /<el-tooltip v-if="!compact" :content="`来源单号/)
assert.match(read('components/ErpFinanceOverview.vue'), /<HsxFold title="单据与来源"/)
assert.doesNotMatch(read('components/ErpFinanceOverview.vue'), /default-open/)
for (const page of ['payable', 'receivable']) {
    const text = read(`views/erp/${page}/list.vue`)
    assert.match(text, /<ErpFinanceOverview/)
    assert.match(text, /<HsxFold class="erp-finance-records"/)
    assert.match(text, /el-table-column type="expand"/)
}
assert.match(read('views/erp/workbench/index.vue'), /watch\(\(\) => \[systemStore.dark, systemStore.theme\]/)
assert.match(read('components/ErpWorkspaceHeader.vue'), /v-show="!collapsed"/, 'keep filters mounted when collapsed')
assert.match(read('components/ErpWorkspaceHeader.vue'), /:aria-expanded="!collapsed"/)
assert.match(read('views/erp/purchase/list.vue'), /<template #toolbar>[\s\S]*?erp-mode-switch[\s\S]*?<\/template>/)
console.log('ERP desktop layout: route mappings, table geometry, header persistence/isolation/fallback, 16 SFCs, theme and finance checks passed')
