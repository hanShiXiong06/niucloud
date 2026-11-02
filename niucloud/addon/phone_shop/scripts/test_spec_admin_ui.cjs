#!/usr/bin/env node
// Execute the real spec-page SFC with Vue reactivity and isolated in-memory APIs.
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), admin = path.join(root, 'admin')
const vue = require(path.join(admin, 'node_modules/vue')), ts = require(path.join(admin, 'node_modules/typescript'))
const compiler = require(path.join(admin, 'node_modules/@vue/compiler-sfc'))
const filename = path.join(admin, 'src/addon/phone_shop/views/goods/spec_list.vue')
const source = fs.readFileSync(filename, 'utf8'), { descriptor, errors } = compiler.parse(source, { filename })
if (errors.length) throw errors[0]
const script = compiler.compileScript(descriptor, { id: 'spec-admin-test' })
const template = compiler.compileTemplate({ source: descriptor.template.content, filename, id: 'spec-admin-test', isTS: true, compilerOptions: { bindingMetadata: script.bindings, expressionPlugins: ['typescript'] } })
if (template.errors.length) throw template.errors[0]
const transpile = value => ts.transpileModule(value, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const clone = value => JSON.parse(JSON.stringify(value))
let checks = 1
function check(actual, expected, message) { assert.deepEqual(clone(actual), clone(expected), message); ++checks }
const tick = async () => { await Promise.resolve(); await Promise.resolve(); await vue.nextTick() }
function deferred() { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no }); return { promise, resolve, reject } }
const row = () => ({ group_id: 9, category_id: 1, category_ids: '1,2', label: '安卓内存规格', sort: 10, items: [{ item_id: 11, item_value: '8+128', sort: 0 }, { item_id: 12, item_value: '12+256', sort: 30 }] })
function setup(overrides = {}) {
    const calls = [], warnings = [], confirmations = []
    const api = {
        getSpecGroups: async () => ({ data: [row()] }),
        addSpecGroup: async data => { calls.push(['groupAdd', clone(data)]); return { data: 19 } },
        editSpecGroup: async (id, data) => { calls.push(['groupEdit', id, clone(data)]); return { data: true } },
        delSpecGroup: async id => { calls.push(['groupDelete', id]); return {} },
        addSpecItem: async data => { calls.push(['itemAdd', clone(data)]); return { data: 13 } },
        editSpecItem: async (id, data) => { calls.push(['itemEdit', id, clone(data)]); return { data: true } },
        delSpecItem: async id => { calls.push(['itemDelete', id]); return {} },
        ...(overrides.api || {})
    }
    const imports = {
        vue: { ...vue, onMounted: fn => fn() },
        'element-plus': { ElMessage: { warning: message => warnings.push(message) }, ElMessageBox: { confirm: (...args) => { confirmations.push(args); return overrides.confirm ? overrides.confirm(...args) : Promise.resolve() } } },
        '@/addon/phone_shop/api/goods': { getCategoryTree: async () => ({ data: [{ category_id: 1, category_name: '安卓', child_list: [] }] }) },
        '@/addon/phone_shop/api/spec': api
    }
    const module = { exports: {} }
    vm.runInNewContext(transpile(script.content), { module, exports: module.exports, require: key => imports[key], console })
    const state = module.exports.default.setup({}, { expose() {} })
    return { state, api, calls, warnings, confirmations }
}
async function main() {
    let x = setup(); await tick()
    x.state.openGroup()
    check(x.state.groupDialog.form.label, '', 'new group requires a deliberate display name')
    await x.state.saveGroup()
    check(x.calls.length, 0, 'no bound category does not save')
    x.state.groupDialog.catVals = [1, 2]
    await x.state.saveGroup()
    check(x.calls.length, 0, 'empty group label does not save')
    x.state.groupDialog.form.label = '笔记本规格'; x.state.groupDialog.form.sort = 0
    await x.state.saveGroup()
    check(x.calls[0][1], { group_id: 0, label: '笔记本规格', sort: 0, category_ids: '1,2', category_id: 1 }, 'group name uses existing label; no new field')
    check(x.state.groupDialog.visible, false, 'group success closes')
    x.state.openGroup(row()); x.state.groupDialog.form.label = '安卓内存规格（常用）'; x.state.groupDialog.form.sort = 8
    await x.state.saveGroup()
    check(x.calls.at(-1).slice(0, 2), ['groupEdit', 9], 'existing group edits proper ID')
    check(x.calls.at(-1)[2].sort, 8, 'group sort is submitted')

    x.state.openItems(row()); let it = x.state.itemDialog.items[0]
    check(x.state.itemChanged(it), false, 'opening does not dirty items')
    it.sort = 5
    check(x.state.itemChanged(it), true, 'sort edits are detected')
    check(await x.state.saveItem(it), true, 'explicit item save succeeds')
    check(x.calls.at(-1), ['itemEdit', 11, { item_value: '8+128', sort: 5 }], 'sort preserved rather than overwritten with zero')
    check(x.state.itemChanged(it), false, 'saved snapshot updated')
    it.sort = 0; await x.state.saveItem(it)
    check(x.calls.at(-1)[2].sort, 0, 'zero sort preserved')
    it.item_value = ''; const previous = x.calls.length
    check(await x.state.saveItem(it), false, 'empty value blocked')
    check(x.calls.length, previous, 'empty value never posts')
    it.item_value = '8+128'
    for (const invalid of [-1, 1.2, 'bad', 1000000]) { it.sort = invalid; check(await x.state.saveItem(it), false, 'invalid sort blocked: ' + invalid) }
    check(it._saving, false, 'invalid value does not stick save lock')

    x = setup(); await tick(); x.state.openItems(row()); x.state.itemDialog.newValue = '16+512'
    await x.state.addItem()
    check(x.calls[0], ['itemAdd', { group_id: 9, item_value: '16+512', sort: 31 }], 'new item sort follows max configured sort')
    check(x.state.itemDialog.items.at(-1).sort, 31, 'new saved row keeps returned request sort')
    check(x.state.itemChanged(x.state.itemDialog.items.at(-1)), false, 'new row not wrongly marked unsaved')
    check(x.state.itemDialog.newValue, '', 'successful identical input is cleared')

    const waiting = deferred()
    x = setup({ api: { addSpecItem: data => { x.calls.push(['itemAdd', clone(data)]); return waiting.promise } } }); await tick(); x.state.openItems(row()); x.state.itemDialog.newValue = '16+512'
    const adding = x.state.addItem(); x.state.itemDialog.newValue = '32+1T'; await x.state.addItem()
    check(x.calls.length, 1, 'adding guard suppresses duplicate request')
    waiting.resolve({ data: 20 }); await adding
    check(x.state.itemDialog.items.at(-1).item_value, '16+512', 'response row uses original submitted value')
    check(x.state.itemDialog.newValue, '32+1T', 'subsequent input is not wiped by old response')
    check(x.state.itemDialog.adding, false, 'adding lock released')

    x = setup({ api: { editSpecItem: async () => { throw Error('test rejection') } } }); await tick(); x.state.openItems(row()); it = x.state.itemDialog.items[0]; it.sort = 7
    await x.state.closeItems()
    check(x.state.itemDialog.visible, true, 'failed save keeps dialog open')
    check(x.state.itemDialog.saving, false, 'failed save releases whole-dialog lock')
    check(it._saving, false, 'failed save releases row lock')
    check(x.state.itemChanged(it), true, 'failed row retains unsaved snapshot')
    x.api.editSpecItem = async (id, data) => { x.calls.push(['itemEdit', id, clone(data)]); return {} }
    await x.state.closeItems()
    check(x.state.itemDialog.visible, false, 'retry success closes dialog')
    check(x.calls.filter(c => c[0] === 'itemEdit').length, 1, 'already clean row is not posted again')

    let confirmAllowed = false, closed = 0
    x = setup({ confirm: () => confirmAllowed ? Promise.resolve() : Promise.reject('cancel') }); await tick(); x.state.openItems(row()); x.state.itemDialog.items[0].sort = 9
    await x.state.beforeCloseItems(() => closed++)
    check(closed, 0, 'cancel close does not discard draft')
    check(x.state.itemDialog.items[0].sort, 9, 'cancel preserves draft values')
    confirmAllowed = true; await x.state.beforeCloseItems(() => closed++)
    check(closed, 1, 'confirmed discard closes once')
    check(x.calls.length, 0, 'discard confirmation does not write')

    const write = deferred()
    x = setup({ api: { editSpecItem: async () => write.promise } }); await tick(); x.state.openItems(row()); it = x.state.itemDialog.items[0]; it.sort = 5
    const saving = x.state.saveItem(it); await x.state.closeItems(); await x.state.beforeCloseItems(() => closed++)
    check(x.state.itemDialog.visible, true, 'in-flight row prevents closing')
    check(await x.state.saveItem(it), false, 'in-flight row suppresses duplicate save')
    write.resolve({}); await saving
    check(it._saving, false, 'row request completes cleanly')
    console.log(`[通过] ${checks} 项规格后台 UI 隔离断言；真实 SFC / Vue，模拟 API，不连接业务库。`)
}
async function browser() {
    const http = require('node:http'), { chromium } = require('/tmp/node_modules/playwright')
    const built = compiler.compileScript(descriptor, { id: 'spec-browser', inlineTemplate: true, templateOptions: { compilerOptions: { expressionPlugins: ['typescript'] } } })
    const code = ts.transpileModule(built.content, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2020 } }).outputText
        .replace(/from ['"]vue['"]/g, "from '/vue.js'").replace(/from ['"]element-plus['"]/g, "from '/element.js'")
        .replace(/from ['"]@\/addon\/phone_shop\/api\/goods['"]/g, "from '/api.js'").replace(/from ['"]@\/addon\/phone_shop\/api\/spec['"]/g, "from '/api.js'")
    const api = `const groups=[${JSON.stringify(row())}];window.testCalls=[];export const getCategoryTree=async()=>({data:[{category_id:1,category_name:'安卓',child_list:[]},{category_id:2,category_name:'手表',child_list:[]}]});export const getSpecGroups=async()=>({data:structuredClone(groups).map(g=>({...g,items:g.items.sort((a,b)=>a.sort-b.sort||a.item_id-b.item_id)}))});export const editSpecItem=async(id,data)=>{testCalls.push(['itemEdit',id,data]);const item=groups.flatMap(g=>g.items).find(i=>i.item_id===id);Object.assign(item,data);return{data:true}};export const addSpecItem=async data=>({data:30});export const delSpecItem=async()=>({});export const addSpecGroup=async()=>({data:30});export const editSpecGroup=async()=>({data:true});export const delSpecGroup=async()=>({});`
    const server = http.createServer((request, response) => {
        const map = {
            '/vue.js': () => fs.readFileSync(path.join(admin, 'node_modules/vue/dist/vue.esm-browser.js'), 'utf8'),
            '/element-real.js': () => fs.readFileSync(path.join(admin, 'node_modules/element-plus/dist/index.full.mjs'), 'utf8').replace("from 'vue'", "from '/vue.js'"),
            '/element.css': () => fs.readFileSync(path.join(admin, 'node_modules/element-plus/dist/index.css'), 'utf8'),
            '/page.js': () => code, '/api.js': () => api,
            '/element.js': () => "export const ElMessage={warning:()=>{}},ElMessageBox={confirm:async()=>true};"
        }
        if (map[request.url]) { response.setHeader('Content-Type', request.url.endsWith('.css') ? 'text/css' : 'application/javascript'); response.end(map[request.url]()) }
        else { response.setHeader('Content-Type', 'text/html'); response.end(`<html><head><link rel="stylesheet" href="/element.css"><style>body{margin:0;background:#f5f7fa;font-family:Arial,'PingFang SC',sans-serif}.main-container{padding:20px}.flex{display:flex}.items-center{align-items:center}.justify-between{justify-content:space-between}.text-page-title{font-size:20px;font-weight:600}.text-sm{font-size:14px}.text-xs{font-size:12px}.text-gray-400{color:#909399}.mt-1{margin-top:4px}.mt-2{margin-top:8px}.mt-3{margin-top:12px}.mt-4{margin-top:16px}.mb-2{margin-bottom:8px}.gap-2{gap:8px}.flex-1{flex:1}.min-w-0{min-width:0}.mr-1{margin-right:4px}.mb-1{margin-bottom:4px}.ml-2{margin-left:8px}.el-input-number{width:110px;flex-shrink:0}.el-dialog .el-button{margin-left:0}</style></head><body><div id="app"></div><script type="module">import{createApp}from'/vue.js';import ElementPlus from'/element-real.js';import Page from'/page.js';createApp(Page).use(ElementPlus).mount('#app');</script></body></html>`) }
    })
    await new Promise((resolve, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', resolve) })
    let chrome
    try {
        chrome = await chromium.launch({ headless: true, executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' })
        const page = await chrome.newPage({ viewport: { width: 1280, height: 900 } }), errors = []
        page.on('pageerror', error => errors.push(error.message))
        await page.goto(`http://127.0.0.1:${server.address().port}`)
        await page.getByRole('button', { name: '管理规格值', exact: true }).click()
        const dialog = page.getByRole('dialog', { name: '管理规格值 · 安卓内存规格', exact: true })
        await dialog.waitFor()
        check(await dialog.getByPlaceholder('规格值', { exact: true }).count(), 2, 'browser shows dictionary items')
        await dialog.locator('.el-input-number input').first().fill('6')
        await dialog.getByRole('button', { name: '保存', exact: true }).first().click()
        await page.waitForFunction(() => testCalls.length === 1)
        check(await page.evaluate(() => testCalls[0]), ['itemEdit', 11, { item_value: '8+128', sort: 6 }], 'browser sort change posts explicit number')
        await dialog.locator('.el-input-number input').last().fill('0')
        await dialog.getByRole('button', { name: '保存并完成', exact: true }).click()
        await page.waitForFunction(() => testCalls.length === 2)
        check(await page.evaluate(() => testCalls[1][2].sort), 0, 'browser save-and-finish preserves zero')
        await dialog.waitFor({ state: 'hidden' })
        await page.getByRole('button', { name: '管理规格值', exact: true }).click()
        await dialog.waitFor(); await page.waitForTimeout(350)
        check(await dialog.getByPlaceholder('规格值', { exact: true }).first().inputValue(), '12+256', 'browser reopens using server-provided sort order')
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, '1280 page has no horizontal overflow')
        check(await dialog.evaluate(el => el.scrollWidth <= el.clientWidth), true, 'spec modal has no inner overflow')
        check(errors, [], 'real ElementPlus page has no script exceptions')
        await page.screenshot({ path: '/tmp/phone-shop-spec-admin-1280.png', fullPage: true })
        console.log(`[通过] 总 ${checks} 项（含真实 ElementPlus 浏览器）；截图 /tmp/phone-shop-spec-admin-1280.png。`)
    } finally { await chrome?.close(); await new Promise(resolve => server.close(resolve)) }
}
main().then(() => process.argv.includes('--browser') ? browser() : undefined).catch(error => { console.error(error); process.exitCode = 1 })
