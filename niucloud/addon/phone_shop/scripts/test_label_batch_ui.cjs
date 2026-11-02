#!/usr/bin/env node
// 真实标签页 SFC 脚本/模板 + 内存 API；不连接业务库。--browser 另启 localhost 隔离页面。
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), admin = path.join(root, 'admin')
const ts = require(path.join(admin, 'node_modules/typescript')), vue = require(path.join(admin, 'node_modules/vue'))
const compiler = require(path.join(admin, 'node_modules/@vue/compiler-sfc'))
const filename = path.join(admin, 'src/addon/phone_shop/views/goods/label_list.vue')
const source = fs.readFileSync(filename, 'utf8'), { descriptor, errors } = compiler.parse(source, { filename })
if (errors.length) throw errors[0]
const lang = { ...JSON.parse(fs.readFileSync(path.join(admin, 'src/addon/phone_shop/lang/zh-cn/goods.label_list.json'), 'utf8')), search: '查询', reset: '重置', warning: '提示', confirm: '确定', cancel: '取消', emptyData: '暂无数据', operation: '操作', edit: '编辑', delete: '删除' }
const tr = key => lang[key] || key
const js = content => ts.transpileModule(content, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020, esModuleInterop: true } }).outputText
const script = compiler.compileScript(descriptor, { id: 'label-batch-test' })
const template = compiler.compileTemplate({ source: descriptor.template.content, filename, id: 'label-batch-test', isTS: true, compilerOptions: { bindingMetadata: script.bindings, expressionPlugins: ['typescript'] } })
if (template.errors.length) throw template.errors[0]
let checks = 1
const clone = value => JSON.parse(JSON.stringify(value))
function check(value, expected, reason) { assert.deepEqual(clone(value), clone(expected), reason); checks++ }
const tick = async () => { await Promise.resolve(); await Promise.resolve(); await vue.nextTick(); await new Promise(resolve => setTimeout(resolve, 0)) }
const deferred = () => { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no }); return { promise, resolve, reject } }
function setup(overrides = {}) {
    const calls = [], notices = [], confirmations = []
    const api = {
        getLabelPageList: async params => { calls.push(['GET', clone(params)]); return { code: 1, data: { data: [{ label_id: 1, status: 1 }], total: 1 } } },
        getLabelGroupList: async () => ({ data: [] }),
        modifyLabelStatus: async (params, options) => { calls.push(['PUT', clone(params), clone(options || {})]); return { code: 1, data: { requested_count: params.label_ids?.length || 1, changed_count: 0, status: params.status } } },
        deleteLabel: async () => ({ code: 1 }), modifyLabelSort: async () => ({ code: 1 }), copyLabel: async () => ({ code: 1 }),
        ...overrides.api,
    }
    const ElMessage = message => notices.push(message); ElMessage.success = message => notices.push({ type: 'success', message })
    const elements = { ElMessage, ElMessageBox: { confirm: (...args) => { confirmations.push(args); return overrides.confirm ? overrides.confirm(...args) : Promise.resolve('confirm') } } }
    const modules = {
        vue, '@/lang': { t: tr }, '@/addon/phone_shop/api/goods': api, 'element-plus': elements,
        'vue-router': { useRoute: () => ({ meta: { title: '商品标签' } }), useRouter: () => ({ push() {} }) },
        '@/utils/common': { debounce: fn => fn, img: value => value, setTablePageStorage() {}, getTablePageStorage: () => ({ page: 1, limit: 10 }) },
    }
    const exports = {}, module = { exports }
    vm.runInNewContext(js(script.content), { exports, module, require: key => modules[key] || { default: {} }, console, setTimeout, clearTimeout })
    const state = module.exports.default.setup({}, { expose() {} })
    return { state, calls, notices, confirmations, api }
}
async function logic() {
    let x = setup(); await tick()
    check(x.calls[0][1].status, '', '默认查询全部状态')
    check(x.state.labelTable.loading, false, '初始请求结束')
    x.state.labelTable.searchParam.status = 0; x.state.loadLabelList(); await tick()
    check(x.calls.at(-1)[1].status, 0, '关闭状态0不能被省略')
    x.state.labelTable.searchParam.status = 1; x.state.loadLabelList(); await tick()
    check(x.calls.at(-1)[1].status, 1, '开启状态1透传')
    x.state.resetForm({ resetFields() { x.state.labelTable.searchParam.status = ''; x.state.labelTable.searchParam.label_name = '' } }); await tick()
    check(x.calls.at(-1)[1].status, '', '重置恢复全部状态')
    x.state.labelTable.searchParam.status = undefined; x.state.loadLabelList(); await tick()
    check(Object.hasOwn(x.calls.at(-1)[1], 'status'), false, '清空选择不形成关闭过滤')
    x.state.handleSelectionChange([{ label_id: 2 }, { label_id: 1 }, { label_id: 2 }, { label_id: 0 }])
    check(x.state.selectedLabelIds.value, [2, 1], '勾选有效ID去重')
    let clearCount = 0; x.state.labelListTableRef.value = { clearSelection() { clearCount++ } }
    x.state.loadLabelList(1); await tick()
    check(x.state.selectedLabelIds.value, [], '重新查询/翻页前清空选择')
    check(clearCount > 0, true, '同步清除Element表格选中态')
    const before = x.calls.length; await x.state.batchStatusEvent(0)
    check(x.calls.length, before, '无勾选不提交')
    x.state.handleSelectionChange([{ label_id: 2 }, { label_id: 9 }]); await x.state.batchStatusEvent(0); await tick()
    const put = x.calls.find(row => row[0] === 'PUT')
    check(put[1], { label_ids: [2, 9], status: 0 }, '只提交勾选ID和目标状态')
    check(put[2], { showSuccessMessage: false }, '批量避免重复成功提示')
    check(x.confirmations.length, 1, '批量需确认一次')
    check(x.notices.some(row => row.type === 'success' && row.message.includes('2') && row.message.includes('0')), true, '0 changed也显示已确认而非报错')
    check(x.state.batchBusy.value, false, '成功后解除提交锁')
    check(x.state.selectedLabelIds.value, [], '成功刷新后不保留旧选择')
    x = setup({ confirm: () => Promise.reject('cancel') }); await tick(); x.state.handleSelectionChange([{ label_id: 1 }]); await x.state.batchStatusEvent(1)
    check(x.calls.filter(row => row[0] === 'PUT').length, 0, '取消确认不提交')
    check(x.state.batchBusy.value, false, '取消解除提交锁')
    check(x.state.batchError.value, '', '取消不作为错误展示')
    const waiting = deferred(); x = setup({ confirm: () => waiting.promise }); await tick(); x.state.handleSelectionChange([{ label_id: 1 }])
    const first = x.state.batchStatusEvent(1); const second = x.state.batchStatusEvent(0)
    check(x.state.batchBusy.value, true, '确认期间即上锁')
    check(x.confirmations.length, 1, '快速重复点击不弹两次确认')
    waiting.resolve('confirm'); await Promise.all([first, second]); await tick()
    check(x.calls.filter(row => row[0] === 'PUT').length, 1, '快速重复点击只提交一次')
    x = setup({ api: { modifyLabelStatus: async () => { throw { msg: '测试拒绝' } } } }); await tick(); x.state.handleSelectionChange([{ label_id: 1 }]); await x.state.batchStatusEvent(0)
    check(x.state.batchError.value, '测试拒绝', '服务端原因可见')
    check(x.state.batchBusy.value, false, '失败解除锁允许核对重试')
    check(x.state.selectedLabelIds.value, [1], '失败保留选择供核对')
    x.api.modifyLabelStatus = async params => ({ code: 1, data: { requested_count: params.label_ids.length, changed_count: 1, status: params.status } })
    await x.state.batchStatusEvent(1); await tick()
    check(x.state.batchError.value, '', '重试清除上次错误')
    check(x.state.batchBusy.value, false, '失败后重试成功')
    x = setup({ api: { modifyLabelStatus: async () => ({ code: 1 }) } }); await tick(); x.state.handleSelectionChange([{ label_id: 1 }]); await x.state.batchStatusEvent(1)
    check(x.notices.some(row => row.type === 'success'), false, '旧接口缺少计数不弹成功')
    check(x.state.batchError.value, tr('batchStatusUnknown'), '缺少计数提示核对结果')
    check(x.state.batchBusy.value, false, '缺少计数也解除提交锁')
    x = setup({ api: { modifyLabelStatus: async () => ({ code: 1, data: { requested_count: 2, changed_count: 3, status: 0 } }) } }); await tick(); x.state.handleSelectionChange([{ label_id: 1 }]); await x.state.batchStatusEvent(1)
    check(x.notices.some(row => row.type === 'success'), false, '矛盾计数/状态不弹成功')
    check(x.state.batchError.value, tr('batchStatusUnknown'), '矛盾计数/状态提示核对')
    check(x.state.batchBusy.value, false, '矛盾计数解除提交锁')
    x = setup({ api: { modifyLabelStatus: async () => { throw Error('single failure') } } }); await tick()
    x.state.modifyLabelStatusEvent(1, 0); await tick()
    check(x.state.isRepeat.value, false, '单条切换失败也解除原防重锁')
    x.api.modifyLabelStatus = async () => ({ code: 1 }); x.state.modifyLabelStatusEvent(1, 1); await tick()
    check(x.state.isRepeat.value, false, '单条失败后可再次处理')
    const pages = []
    x = setup({ api: { getLabelPageList: async params => { pages.push(clone(params)); return { code: 1, data: { data: params.page === 1 ? [{ label_id: 1 }] : [], total: 10 } } } } }); await tick()
    x.state.loadLabelList(2); await tick(); await tick()
    check(pages.slice(-2).map(row => row.page), [2, 1], '筛选/关闭导致末页清空时回到有效页')
    check(x.state.labelTable.page, 1, '当前分页与有效页面一致')
    check(x.state.labelTable.data.length, 1, '回到有效页后展示数据')
    const older = deferred(), newer = deferred(); let n = 0
    x = setup(); await tick(); x.api.getLabelPageList = () => (++n === 1 ? older.promise : newer.promise)
    x.state.loadLabelList(1); x.state.loadLabelList(1)
    newer.resolve({ data: { data: [{ label_id: 8 }], total: 1 } }); await tick()
    older.resolve({ data: { data: [{ label_id: 7 }], total: 1 } }); await tick()
    check(x.state.labelTable.data[0].label_id, 8, '过期GET结果不覆盖新筛选')
    const apiCode = fs.readFileSync(path.join(admin, 'src/addon/phone_shop/api/goods.ts'), 'utf8')
    check(/function modifyLabelStatus[\s\S]*?request\.put\(`phone_shop\/goods\/label\/status`/.test(apiCode), true, '复用原PUT状态接口')
    check(/@selection-change="handleSelectionChange"/.test(source), true, '真实模板已接上勾选事件')
    check(/prop="status"/.test(source) && /:value="0"/.test(source), true, '真实模板含关闭筛选')
    console.log(`[通过] ${checks} 项标签批量UI隔离断言；真实SFC脚本/模板，模拟API，不连接业务库。`)
}
async function browser() {
    const http = require('node:http'), { chromium } = require('/tmp/node_modules/playwright')
    const esm = content => ts.transpileModule(content, { compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2020 } }).outputText
    const built = compiler.compileScript(descriptor, { id: 'label-browser', inlineTemplate: true, templateOptions: { compilerOptions: { expressionPlugins: ['typescript'] } } })
    const code = esm(built.content).replace(/from ['"]vue['"]/g, "from '/vue.js'")
        .replace(/from ['"]@\/lang['"]/g, "from '/lang.js'").replace(/from ['"]@\/addon\/phone_shop\/api\/goods['"]/g, "from '/api.js'")
        .replace(/from ['"]element-plus['"]/g, "from '/element.js'").replace(/from ['"]vue-router['"]/g, "from '/router.js'")
        .replace(/from ['"]@\/utils\/common['"]/g, "from '/common.js'").replace(/from ['"]@\/addon\/phone_shop\/views\/goods\/components\/label-edit.vue['"]/g, "from '/edit.js'")
    const css = descriptor.styles.map(style => style.content).join('\n')
    const fixtures = Array.from({ length: 14 }, (_, index) => ({ label_id: index + 1, label_name: `标签${index + 1}`, status: index % 2, style_type: 'diy', color_json: { text_color: '#337ecc', bg_color: '#ecf5ff' }, group: { group_name: '默认分组' }, memo: '', sort: 0, create_time: '2026-10-30 12:00:00' }))
    const api = `const rows=${JSON.stringify(fixtures)};window.testCalls=[];export const getLabelGroupList=async()=>({data:[{group_id:1,group_name:'默认分组'}]});export const getLabelPageList=async params=>{testCalls.push(['GET',params]);const filtered=rows.filter(row=>params.status===''||params.status==null||row.status===Number(params.status));return{code:1,data:{data:filtered.slice((params.page-1)*params.limit,params.page*params.limit),total:filtered.length}}};export const modifyLabelStatus=async(params,options)=>{testCalls.push(['PUT',params,options]);let changed=0;for(const row of rows)if((params.label_ids||[params.label_id]).includes(row.label_id)&&row.status!==params.status){row.status=params.status;changed++}return{code:1,data:{requested_count:params.label_ids?.length||1,changed_count:changed,status:params.status}}};export const deleteLabel=async()=>({code:1}),modifyLabelSort=deleteLabel,copyLabel=deleteLabel;`
    const server = http.createServer((request, response) => {
        response.setHeader('Content-Type', request.url.endsWith('.css') ? 'text/css' : 'application/javascript')
        const map = {
            '/vue.js': () => fs.readFileSync(path.join(admin, 'node_modules/vue/dist/vue.esm-browser.js'), 'utf8'),
            '/element-real.js': () => fs.readFileSync(path.join(admin, 'node_modules/element-plus/dist/index.full.mjs'), 'utf8').replace("from 'vue'", "from '/vue.js'"),
            '/element.css': () => fs.readFileSync(path.join(admin, 'node_modules/element-plus/dist/index.css'), 'utf8'),
            '/page.js': () => code,
            '/api.js': () => api,
            '/lang.js': () => `const lang=${JSON.stringify(lang)};export const t=key=>lang[key]||key;`,
            '/router.js': () => "export const useRoute=()=>({meta:{title:'商品标签'}}),useRouter=()=>({push(){}});",
            '/common.js': () => "export const debounce=fn=>fn,img=value=>value,setTablePageStorage=()=>{},getTablePageStorage=()=>({page:1,limit:10});",
            '/edit.js': () => "export default{setup(){return()=>null}};",
            '/element.js': () => "export const ElMessageBox={confirm:async()=>true};export const ElMessage=()=>{};ElMessage.success=()=>{};",
        }
        if (map[request.url]) response.end(map[request.url]())
        else { response.setHeader('Content-Type', 'text/html'); response.end(`<html><head><link rel="stylesheet" href="/element.css"><style>body{margin:0;background:#f5f7fa;font-family:Arial,'PingFang SC',sans-serif}.main-container{padding:20px}.flex{display:flex}.justify-between{justify-content:space-between}.items-center{align-items:center}.text-page-title{font-size:20px;font-weight:600}.table-search-wrap{margin:10px 0}.el-select{width:180px}.el-input{width:180px}.el-table .el-input{width:90px}.mb-\\[10px\\]{margin-bottom:10px}${css}</style></head><body><div id="app"></div><script type="module">import{createApp}from'/vue.js';import ElementPlus from'/element-real.js';import Page from'/page.js';createApp(Page).use(ElementPlus).mount('#app');</script></body></html>`) }
    })
    await new Promise((resolve, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', resolve) })
    let chrome
    try {
        chrome = await chromium.launch({ headless: true, executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' }); const page = await chrome.newPage({ viewport: { width: 1280, height: 900 } })
        const errors = []; page.on('pageerror', error => errors.push(error.message))
        await page.goto(`http://127.0.0.1:${server.address().port}`)
        await page.getByRole('button', { name: '批量开启', exact: true }).waitFor()
        check(await page.getByRole('button', { name: '批量关闭', exact: true }).isDisabled(), true, '浏览器未选择时批量按钮禁用')
        const status = page.locator('.el-form-item').filter({ has: page.locator('label').filter({ hasText: /^状态$/ }) }).locator('.el-select')
        await status.click(); await page.locator('.el-select-dropdown__item').filter({ hasText: /^关闭$/ }).click(); await page.getByRole('button', { name: '查询', exact: true }).click()
        await page.waitForFunction(() => testCalls.at(-1)[1].status === 0)
        check(await page.locator('.el-table__body tr').count(), 7, '浏览器关闭筛选只展示关闭标签')
        await page.locator('.el-table__body tr').first().locator('.el-checkbox').click()
        check(await page.getByRole('button', { name: '批量开启', exact: true }).isEnabled(), true, '浏览器勾选启用批量动作')
        await page.getByRole('button', { name: '批量开启', exact: true }).click()
        await page.waitForFunction(() => testCalls.some(call => call[0] === 'PUT'))
        const put = await page.evaluate(() => testCalls.find(call => call[0] === 'PUT')[1])
        check(put.label_ids.length, 1, '浏览器仅对选中一个标签设置')
        check(put.status, 1, '浏览器批量开启发送1')
        await page.waitForFunction(() => document.querySelectorAll('.el-table__body tr').length === 6)
        await page.getByRole('button', { name: '重置', exact: true }).click()
        await page.waitForFunction(() => testCalls.at(-1)[1].status === '')
        check(await page.locator('.el-table__body tr').count(), 10, '浏览器重置恢复全部状态首屏')
        await page.waitForTimeout(350)
        check(await page.locator('.el-table__body .el-tag').filter({ hasText: /^开启$/ }).count(), 6, '浏览器真实行启用状态正确显示')
        check(errors, [], '真实ElementPlus组件运行无脚本异常')
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, '1280宽页面无横向溢出')
        await page.screenshot({ path: '/tmp/phone-shop-label-batch-1280.png', fullPage: true })
        console.log(`[通过] 总 ${checks} 项（含真实 ElementPlus 浏览器检查）；截图 /tmp/phone-shop-label-batch-1280.png；仅模拟API。`)
    } finally { await chrome?.close(); await new Promise(resolve => server.close(resolve)) }
}
logic().then(() => process.argv.includes('--browser') ? browser() : undefined).catch(error => { console.error(error); process.exitCode = 1 })
