// Real drawer setup with isolated API spies; no browser, network or business writes.
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const { createRequire } = require('node:module')
const root = path.resolve(__dirname, '../../../..')
const local = createRequire(path.join(root, 'admin/package.json'))
const vue = local('vue')
const sfc = local('@vue/compiler-sfc')
const ts = local('typescript')
const relative = 'views/recycle_category/components/QuoteImageSyncDrawer.vue'
const file = path.join(root, 'admin/src/addon/hsx_recycle', relative)
const source = fs.readFileSync(file, 'utf8')
for (const relative of ['api/quote_image_sync.ts', 'views/recycle_category/components/QuoteImageSyncDrawer.vue', 'views/recycle_category/recycle_category.vue']) {
    assert.equal(fs.readFileSync(path.join(root, 'admin/src/addon/hsx_recycle', relative), 'utf8'),
        fs.readFileSync(path.join(root, 'niucloud/addon/hsx_recycle/admin', relative), 'utf8'), 'Release copy: ' + relative)
}
const { descriptor, errors } = sfc.parse(source, { filename: file })
assert.deepEqual(errors, [])
const script = sfc.compileScript(descriptor, { id: 'quote-sync-test' })
assert.deepEqual(sfc.compileTemplate({ source: descriptor.template.content, filename: file, id: 'quote-sync-test', compilerOptions: { bindingMetadata: script.bindings } }).errors, [])
for (const style of descriptor.styles) assert.deepEqual(sfc.compileStyle({ source: style.content, filename: file, id: 'quote-sync-test', preprocessLang: style.lang }).errors, [])
const code = ts.transpileModule(script.content, { fileName: file + '.ts', reportDiagnostics: true, compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } })
assert.deepEqual(code.diagnostics, [])
let checks = 0
const check = (title, fn) => { fn(); checks++; console.log('PASS', title) }
const copy = value => JSON.parse(JSON.stringify(value))
const run = (status = 'idle', changes = {}) => ({ run_id: status === 'idle' ? '' : 'R1', status, total: 2, done: 0, updated: 0, unchanged: 0, failed: 0, started_at: 1793149200, finished_at: 0, items: [], message: '', ...changes })
const initial = () => ({ enabled: 1, source_url: 'https://13303016666.huishoubaojia.com/', run_time: '09:30',
    source_catalog: [{ id: '1', name: '苹果有保', group: '手机', url: 'https://13303016666.huishoubaojia.com/1.html' }, { id: '2', name: '华为', group: '平板', url: 'https://13303016666.huishoubaojia.com/2.html' }],
    mappings: [{ source_id: '1', source_name: '手机 / 苹果有保', category_id: 10, enabled: 1 }, { source_id: '2', source_name: '平板 / 华为', category_id: 0, enabled: 1 }],
    category_options: [{ value: 10, label: '苹果在保' }, { value: 20, label: '华为平板' }], queue_enabled: true, status: run(), recent_runs: [] })
const calls = [], events = [], timers = new Map(), watches = [], beforeUnmount = []
let config = initial(), nextTimer = 0, failDiscovery = false, failStatus = false, confirm = true, deferredStatus
const api = {
    async getQuoteImageSyncConfig() { calls.push('config'); return { data: copy(config) } },
    async saveQuoteImageSyncConfig(data) { calls.push(['save', copy(data)]); config = { ...config, ...copy(data) }; return { data: copy(config) } },
    async discoverQuoteImageSources(url) { calls.push(['discover', url]); if (failDiscovery) throw { msg: '来源未响应' }; return { data: { source_url: url, items: copy(config.source_catalog) } } },
    async startQuoteImageSync() { calls.push('start'); config.status = run('queued'); return { data: copy(config.status) } },
    async getQuoteImageSyncStatus() { calls.push('status'); if (deferredStatus) return deferredStatus; if (failStatus) throw { msg: '状态服务未响应' }; return { data: { status: copy(config.status), recent_runs: copy(config.recent_runs), queue_enabled: true } } },
    async cancelQuoteImageSync(id) { calls.push(['cancel', id]); config.status = run('cancelled', { done: 1, updated: 1 }); return { data: { status: copy(config.status), recent_runs: [] } } }
}
const feedback = { success() {}, warning(message) { calls.push(['warning', message]) }, async confirm() { calls.push('confirm'); return confirm } }
const sandbox = { exports: {}, URL, console,
    setTimeout(fn) { const id = ++nextTimer; timers.set(id, fn); return id }, clearTimeout(id) { timers.delete(id) },
    require(id) {
        if (id === 'vue') return { ...vue, watch(getter, fn, options) { watches.push(fn); if (options?.immediate) fn(getter()) }, onBeforeUnmount(fn) { beforeUnmount.push(fn) } }
        if (id.includes('/hsx_components/core')) return { useFeedback: () => feedback }
        if (id.endsWith('/api/quote_image_sync')) return api
        throw Error('Unexpected import: ' + id)
    }
}
vm.runInNewContext(code.outputText, sandbox, { filename: file })
const props = { modelValue: false }
const state = sandbox.exports.default.setup(props, { expose() {}, emit: (...args) => events.push(args) })
const flush = async () => { for (let i = 0; i < 4; i++) await Promise.resolve() }
const show = async value => { props.modelValue = value; watches[0](value); await flush() }
async function main() {
    check('Vue、TypeScript 和样式编译通过', () => assert.ok(state))
    check('抽屉关闭时不加载、不轮询', () => { assert.equal(calls.length, 0); assert.equal(timers.size, 0) })
    await show(true)
    check('打开后回显分类映射，未关联项不计入采集', () => { assert.equal(state.loaded.value, true); assert.equal(state.dirty.value, false); assert.equal(state.enabledMappings.value.length, 1); assert.equal(state.mappingRows.value.length, 2) })
    state.form.mappings[1].category_id = 20
    await state.startRun()
    check('脏配置必须先保存，不能立即采集', () => { assert.equal(state.dirty.value, true); assert.ok(!calls.includes('start')); assert.match(state.runDisabledReason.value, /保存/) })
    await state.saveConfig()
    check('只提交可编辑配置，不回写状态日志', () => { assert.deepEqual(Object.keys(calls.find(call => call[0] === 'save')[1]).sort(), ['enabled', 'mappings', 'run_time', 'source_url']); assert.equal(state.dirty.value, false) })
    await state.startRun()
    check('立即采集只发起一次任务并开始进度轮询', () => { assert.equal(calls.filter(call => call === 'start').length, 1); assert.equal(state.run.value.status, 'queued'); assert.equal(timers.size, 1) })
    await state.startRun()
    check('排队中禁止重复发起', () => assert.equal(calls.filter(call => call === 'start').length, 1))
    await show(false)
    check('关闭立即清理轮询', () => assert.equal(timers.size, 0))
    await show(true)
    check('重新打开恢复后台任务进度', () => { assert.equal(state.run.value.status, 'queued'); assert.equal(timers.size, 1) })
    config.status = run('partial', { done: 2, updated: 1, failed: 1, items: [{ source_id: '2', source_name: '华为', status: 'failed', message: '下载失败，保留旧图' }] })
    config.mappings[0].enabled = 0
    await state.refreshStatus()
    await state.refreshStatus()
    check('部分失败保留逐项原因，只刷新父分类一次', () => { assert.equal(state.run.value.items[0].message, '下载失败，保留旧图'); assert.equal(events.filter(event => event[0] === 'updated').length, 1); assert.equal(timers.size, 0) })
    check('任务结束刷新后端自动暂停的映射，不显示过期启用状态', () => assert.equal(state.form.mappings[0].enabled, 0))
    config.status = run('running')
    await state.refreshStatus()
    failStatus = true
    await state.refreshStatus(); await state.refreshStatus(); await state.refreshStatus()
    check('连续查询失败停止轮询，不误报已完成', () => { assert.equal(state.run.value.status, 'running'); assert.equal(timers.size, 0); assert.match(state.statusError.value, /可能仍在执行/) })
    failStatus = false
    await state.refreshStatus(true)
    check('可手动恢复进度查询', () => { assert.equal(state.statusError.value, ''); assert.equal(timers.size, 1) })
    await state.stopRun()
    check('停止本次确认后携带任务ID，保留自动配置', () => { assert.equal(calls.find(call => Array.isArray(call) && call[0] === 'cancel')[1], 'R1'); assert.equal(state.run.value.status, 'cancelled'); assert.equal(state.form.enabled, 1); assert.equal(timers.size, 0) })
    state.form.source_url = 'https://other.huishoubaojia.com/'
    check('换来源后必须重新读取，避免沿用旧ID映射', () => assert.match(state.validateConfig(), /重新读取/))
    failDiscovery = true
    const previous = JSON.stringify(state.form.mappings)
    await state.discoverSources()
    check('读取失败保留原映射和输入', () => { assert.equal(JSON.stringify(state.form.mappings), previous); assert.equal(state.form.source_url, 'https://other.huishoubaojia.com/'); assert.match(state.operationError.value, /未响应/) })
    failDiscovery = false
    await state.discoverSources()
    check('新来源读出栏目后不自动复用旧目标分类', () => { assert.equal(state.sourceChanged.value, false); assert.ok(state.form.mappings.every(item => item.category_id === 0)) })
    state.form.mappings.forEach(item => { item.category_id = 10; item.enabled = 1 })
    check('阻止两个启用栏目覆盖同一本站分类', () => assert.match(state.validateConfig(), /同一个本站分类/))
    confirm = false
    await state.closeDrawer()
    check('关闭未保存表单可取消，避免丢失配置', () => assert.ok(!events.some(event => event[0] === 'update:modelValue' && event[1] === false)))
    await show(false); config = initial(); await show(true)
    let resolve
    deferredStatus = new Promise(done => { resolve = done })
    const pending = state.refreshStatus()
    await show(false)
    resolve({ data: { status: run('success', { updated: 9, done: 2 }) } }); await pending
    check('关闭后的迟到状态响应不能污染下次打开', () => assert.notEqual(state.run.value.updated, 9))
    beforeUnmount.forEach(fn => fn())
    check('卸载清理全部轮询', () => assert.equal(timers.size, 0))
    console.log('完成：' + checks + ' 项检查；所有接口均为隔离 spy。')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
