#!/usr/bin/env node
// Real mobile quote SFC + helpers, isolated in-memory APIs. No browser, network or business writes.
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '../../../..')
const app = path.join(root, 'site-uniapp')
const base = path.join(app, 'src/addon/hsx_recycle')
const vue = require(path.join(app, 'node_modules/vue'))
const compiler = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
const ts = require(path.join(app, 'node_modules/typescript'))
let checks = 0
const plain = value => value === undefined ? undefined : JSON.parse(JSON.stringify(value))
const eq = (actual, expected, label) => { assert.deepEqual(plain(actual), plain(expected), label); checks++ }
const ok = (actual, label) => { assert.ok(actual, label); checks++ }
const defer = () => { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no }); return { promise, resolve, reject } }
const ids = rows => rows.map(row => row.category_id)
const callsOf = (env, name) => env.calls.filter(call => call[0] === name)
const timers = new Set()
const setTimer = (callback, delay) => { const timer = setTimeout(() => { timers.delete(timer); callback() }, delay); timers.add(timer); return timer }
const clearTimer = timer => { timers.delete(timer); clearTimeout(timer) }
const transpile = (source, filename) => {
    const result = ts.transpileModule(source, { fileName: filename + '.ts', reportDiagnostics: true, compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } })
    eq((result.diagnostics || []).filter(item => item.category === ts.DiagnosticCategory.Error).length, 0, filename + ' TypeScript transpiles')
    return result.outputText
}
function execute(code, filename, dependencies, extra = {}) {
    const module = { exports: {} }
    vm.runInNewContext(code, { module, exports: module.exports, console, setTimeout: setTimer, clearTimeout: clearTimer, ...extra, require(id) {
        if (Object.hasOwn(dependencies, id)) return dependencies[id]
        throw Error('Unexpected import (external operations are blocked): ' + id)
    } }, { filename })
    return module.exports
}
const helperFile = path.join(base, 'utils/quote.ts')
const helpers = execute(transpile(fs.readFileSync(helperFile, 'utf8'), helperFile), helperFile, {})
const filename = path.join(base, 'pages/quote/index.vue')
const source = fs.readFileSync(filename, 'utf8')
const { descriptor, errors } = compiler.parse(source, { filename })
eq(errors, [], 'quote SFC parses')
const script = compiler.compileScript(descriptor, { id: 'mobile-quote-tree-test' })
const template = compiler.compileTemplate({ source: descriptor.template.content, filename, id: 'mobile-quote-tree-test', compilerOptions: { bindingMetadata: script.bindings } })
eq(template.errors, [], 'quote template compiles')
for (const style of descriptor.styles) eq(compiler.compileStyle({ source: style.content, filename, id: 'mobile-quote-tree-test', preprocessLang: style.lang }).errors, [], 'quote styles compile')
const scriptCode = transpile(script.content, filename)
const renderCode = transpile(template.code, filename + ':template')
const render = execute(renderCode, filename + ':template', { vue: { ...vue, resolveComponent: name => name } }).render
const fixture = () => [
    { category_id: 20, site_id: 7, category_name: '安卓', sort: 3, images: '', is_show: 0, need_vip: 0, child_list: [
        { category_id: 21, site_id: 7, category_name: '小米 17', sort: 0, images: 'upload/xiaomi.png', is_show: 1, need_vip: 0 }
    ] },
    { category_id: 10, site_id: 7, category_name: '苹果', sort: 9, images: '', is_show: 1, need_vip: 1, child_list: [
        { category_id: 12, site_id: 7, category_name: 'iPad', sort: 2, images: '', is_show: 1, need_vip: 1, child_list: [
            { category_id: 121, site_id: 7, category_name: 'iPad mini', sort: 0, images: 'upload/ipad.png', is_show: 1, need_vip: 1 }
        ] },
        { category_id: 11, site_id: 7, category_name: 'iPhone 17 Pro', sort: 5, images: 'upload/iphone.png', is_show: 1, need_vip: 1 },
        { category_id: 13, site_id: 7, category_name: 'iPhone Air', sort: 5, images: '', is_show: 0, need_vip: 0 }
    ] },
    { category_id: 30, site_id: 0, category_name: '公共配件', sort: 3, images: 'upload/public.png', is_show: 1, need_vip: 0 }
]
async function flush() { for (let i = 0; i < 8; i++) await vue.nextTick() }
function environment(options = {}) {
    const calls = [], toasts = [], modals = [], lifecycle = { load: [], unload: [], pull: [] }
    const env = { calls, toasts, modals, lifecycle, confirm: true, tree: fixture(), canEdit: options.canEdit ?? true, ...options }
    const api = {
        async getQuoteOptions() { calls.push(['options']); return env.options ? env.options() : { code: 1, data: { categories: plain(env.tree), site_id: 7, can_edit: env.canEdit } } },
        async updateQuoteVisibility(id, payload) {
            calls.push(['visibility', id, plain(payload)])
            return env.visibility ? env.visibility(id, payload) : { code: 1, data: { category_id: id, is_show: payload.is_show, changed: true } }
        },
        async updateQuoteImage(id, payload) {
            calls.push(['image', id, plain(payload)])
            return env.image ? env.image(id, payload) : { code: 1, data: { category_id: id, images: payload.images, changed: true, update_time: 1794409200 } }
        },
        async getQuoteHistory(id, page) { calls.push(['history', id, page]); return { code: 1, data: { data: [{ id: 91, images: 'upload/history.png', create_time: 1794409100 }], total: 1 } } }
    }
    const exports = execute(scriptCode, filename, {
        vue,
        '@dcloudio/uni-app': { onLoad: callback => lifecycle.load.push(callback), onUnload: callback => lifecycle.unload.push(callback), onPullDownRefresh: callback => lifecycle.pull.push(callback) },
        '@/app/api/system': { async uploadImage(payload) { calls.push(['upload', plain(payload)]); return env.upload ? env.upload(payload) : { code: 1, data: { url: 'upload/new-quote.png' } } } },
        '@/utils/common': { img: value => 'https://assets.invalid/' + value },
        '@/addon/hsx_recycle/utils/preview': { previewImages: value => calls.push(['preview', plain(value)]) },
        '@/addon/hsx_recycle/api/quote': api,
        '@/addon/hsx_recycle/utils/quote': helpers
    }, { uni: {
        showToast: input => toasts.push(plain(input)),
        showModal(input) { modals.push({ title: input.title, content: input.content }); if (env.modal) env.modal(input); else input.success({ confirm: env.confirm, cancel: !env.confirm }) },
        chooseImage(input) { calls.push(['choose', { count: input.count, sourceType: plain(input.sourceType), sizeType: plain(input.sizeType) }]); env.choose = input },
        pageScrollTo: input => calls.push(['scroll', plain(input)]),
        stopPullDownRefresh: () => calls.push(['stopPullDownRefresh']),
        enableAlertBeforeUnload: input => calls.push(['enableAlert', plain(input)]),
        disableAlertBeforeUnload: () => calls.push(['disableAlert'])
    } })
    const scope = vue.effectScope()
    env.state = scope.run(() => exports.default.setup({}, { expose() {} }))
    env.row = id => env.state.categories.value.find(row => row.category_id === id)
    env.load = async () => { await env.state.loadOptions(); await flush() }
    env.dispose = () => { lifecycle.unload.forEach(callback => callback()); scope.stop() }
    env.render = () => { const proxy = vue.proxyRefs(env.state); return render(proxy, [], {}, proxy, {}, {}) }
    return env
}
function nodes(vnode, match, result = []) {
    if (!vnode || typeof vnode !== 'object') return result
    if (match(vnode)) result.push(vnode)
    if (Array.isArray(vnode.children)) vnode.children.forEach(child => nodes(child, match, result))
    return result
}
const textOf = vnode => typeof vnode === 'string' ? vnode : typeof vnode?.children === 'string' ? vnode.children : Array.isArray(vnode?.children) ? vnode.children.map(textOf).join('') : ''
const hasClass = (vnode, name) => String(vnode.props?.class || '').split(/\s+/).includes(name)
async function chooseDraft(env) {
    env.state.chooseImage()
    ok(env.choose, 'real chooseImage entry invokes uni image picker')
    env.choose.success({ tempFilePaths: ['/tmp/mobile-quote-draft.png'] })
    env.choose.complete()
    await flush()
}

async function main() {
    const apiCalls = []
    const apiFile = path.join(base, 'api/quote.ts')
    const realApi = execute(transpile(fs.readFileSync(apiFile, 'utf8'), apiFile), apiFile, { '@/utils/request': { default: { put(...args) { apiCalls.push(args); return Promise.resolve({}) } } } })
    await realApi.updateQuoteVisibility(11, { is_show: 0, expected_is_show: 1 })
    eq(apiCalls[0], ['recycle/recycle_category/11/quote-visibility', { is_show: 0, expected_is_show: 1 }, { showErrorMessage: false, showLoading: false }], 'real API targets dedicated visibility endpoint with expected state and quiet errors')

    {
        const env = environment(); await env.load(); const s = env.state
        eq(env.lifecycle.load.length, 1, 'page registers one load hook')
        eq(env.lifecycle.unload.length, 1, 'page registers cleanup hook')
        eq(ids(s.categories.value), [10, 11, 13, 12, 121, 20, 21, 30], 'each level follows descending backend sort, equal sorts keep original order')
        eq(s.expandedIds.value, [], 'tree starts collapsed')
        eq(ids(s.visibleCategories.value), [10, 20, 30], 'initial list contains roots only')
        s.toggleCategory(env.row(10))
        eq(ids(s.visibleCategories.value), [10, 11, 13, 12, 20, 30], 'expanding one root reveals only its immediate children')
        s.toggleCategory(env.row(12))
        eq(ids(s.visibleCategories.value), [10, 11, 13, 12, 121, 20, 30], 'nested expansion respects tree order')
        const expanded = plain(s.expandedIds.value)
        s.toggleCategory(env.row(11))
        eq(s.expandedIds.value, expanded, 'leaf cannot acquire an expansion state')
        s.toggleCategory(env.row(10))
        eq(ids(s.visibleCategories.value), [10, 20, 30], 'collapsing parent hides its whole branch')
        s.toggleAllCategories()
        eq(ids(s.visibleCategories.value), [10, 11, 13, 12, 121, 20, 21, 30], 'expand all reveals every row without changing sorting')
        const renderedRows = nodes(env.render(), vnode => hasClass(vnode, 'quote-category'))
        const leaf = renderedRows.find(vnode => textOf(vnode).includes('iPhone 17 Pro'))
        const parent = renderedRows.find(vnode => textOf(vnode).includes('苹果') && !textOf(vnode).includes('iPhone'))
        ok(leaf, 'actual compiled template renders leaf category')
        ok(parent, 'actual compiled template renders parent category')
        const leafToggle = nodes(leaf, vnode => hasClass(vnode, 'quote-tree-toggle'))[0]
        const parentToggle = nodes(parent, vnode => hasClass(vnode, 'quote-tree-toggle'))[0]
        eq(nodes(leaf, vnode => hasClass(vnode, 'quote-tree-toggle')).length, 0, 'leaf has no clickable expansion control')
        eq(nodes(leafToggle, vnode => vnode.type === 'u-icon').length, 0, 'leaf has no expansion arrow in rendered template')
        eq(nodes(parentToggle, vnode => vnode.type === 'u-icon').length, 1, 'parent has one expansion arrow in rendered template')
        s.toggleAllCategories()
        eq(ids(s.visibleCategories.value), [10, 20, 30], 'collapse all returns to roots')
        s.keyword.value = 'IPAD MINI'; await flush()
        eq(ids(s.filtered.value), [121], 'search ignores spaces and case and counts real matches only')
        eq(ids(s.visibleCategories.value), [10, 12, 121], 'search reveals matched leaf with all ancestors despite collapsed state')
        s.keyword.value = ''; s.changeStatus('ready'); await flush()
        eq(ids(s.visibleCategories.value), [10, 11, 12, 121, 20, 21, 30], 'ready filter retains unquoted ancestors as context')
        s.changeStatus('hidden')
        eq(ids(s.visibleCategories.value), [10, 13, 20], 'hidden filter retains visible ancestor without changing stored switches')
        eq(env.row(10).is_show, 1, 'filtering never mutates category visibility')
        s.changeStatus('all')
        eq(ids(s.visibleCategories.value), [10, 20, 30], 'clearing filters restores stored collapsed state')
        env.dispose()
    }
    {
        const pending = defer(), env = environment({ visibility: () => pending.promise }); await env.load(); const s = env.state, leaf = env.row(11)
        const before = plain(leaf), first = s.toggleVisibility(leaf, false)
        await flush()
        eq(callsOf(env, 'visibility'), [['visibility', 11, { is_show: 0, expected_is_show: 1 }]], 'switch submits desired state and true original persisted state')
        eq(leaf.is_show, 1, 'pending switch does not optimistically mutate row')
        ok(s.switchingIds.value.includes(11), 'pending category is locked against double taps')
        const second = s.toggleVisibility(leaf, false); await flush()
        eq(callsOf(env, 'visibility').length, 1, 'same-category rapid tap cannot submit twice')
        await s.loadOptions()
        eq(callsOf(env, 'options').length, 1, 'refresh cannot replace row state while visibility write is pending')
        pending.resolve({ code: 1, data: { category_id: 11, is_show: 0, changed: true } }); await Promise.all([first, second])
        eq(leaf.is_show, 0, 'confirmed backend visibility is reflected in row')
        eq(s.switchingIds.value, [], 'successful response releases category lock')
        eq(env.modals.length, 0, 'leaf switch uses no blocking confirmation')
        ok(env.toasts.some(item => /隐藏|关闭|不再.*展示/.test(item.title)), 'leaf visibility success receives lightweight feedback')
        eq({ ...plain(leaf), is_show: before.is_show }, before, 'visibility success preserves images, sort, membership and tree metadata')
        eq(callsOf(env, 'image').length + callsOf(env, 'upload').length + callsOf(env, 'history').length, 0, 'visibility never runs image upload/update/history workflow')
        env.dispose()
    }
    {
        const env = environment(); await env.load(); const parent = env.row(10), descendants = env.state.categories.value.filter(row => row.ancestorIds.includes(10)).map(row => [row.category_id, row.is_show])
        env.confirm = false
        await env.state.toggleVisibility(parent, false)
        eq(callsOf(env, 'visibility').length, 0, 'canceling parent-hide confirmation submits nothing')
        eq(parent.is_show, 1, 'canceling parent-hide leaves persisted switch unchanged')
        eq(env.state.switchingIds.value, [], 'canceling confirmation releases switch lock')
        ok(env.modals.some(item => /子分类|下级/.test(item.content)), 'parent-hide confirmation explains impact on descendants')
        env.confirm = true
        await env.state.toggleVisibility(parent, false)
        eq(callsOf(env, 'visibility').at(-1), ['visibility', 10, { is_show: 0, expected_is_show: 1 }], 'parent-hide writes only selected parent with expected state')
        eq(parent.is_show, 0, 'confirmed parent is hidden')
        eq(env.state.categories.value.filter(row => row.ancestorIds.includes(10)).map(row => [row.category_id, row.is_show]), descendants, 'parent hiding does not flip descendant switches')
        env.state.openCategory(env.row(11))
        ok(/父|上级/.test(textOf(env.render())) && /隐藏|未展示|未开启/.test(textOf(env.render())), 'detail renders warning when parent prevents customer visibility')
        env.dispose()
    }
    {
        const wait = defer(), env = environment({ modal: input => { env.pendingModal = input }, visibility: () => wait.promise }); await env.load()
        const first = env.state.toggleVisibility(env.row(10), false)
        await flush()
        await env.state.toggleVisibility(env.row(10), false)
        eq(env.modals.length, 1, 'parent confirmation itself is protected against rapid duplicate taps')
        env.pendingModal.success({ confirm: true }); await flush()
        eq(callsOf(env, 'visibility').length, 1, 'confirming parent starts exactly one write')
        wait.resolve({ code: 1, data: { category_id: 10, is_show: 0 } }); await first
        env.dispose()
    }
    {
        const env = environment({ modal: input => { env.pendingModal = input } }); await env.load()
        const pending = env.state.toggleVisibility(env.row(10), false)
        await flush()
        env.dispose()
        env.pendingModal.success({ confirm: true })
        await pending
        eq(callsOf(env, 'visibility').length, 0, 'leaving page before confirming parent does not submit a late write')
    }
    for (const failure of [
        { label: 'unclassified rejection', invoke: async () => { throw { msg: '分类状态已变更，请刷新核对' } } },
        { label: 'transport timeout', invoke: async () => { throw { errMsg: 'request:fail timeout' } } },
        { label: 'error after database commit', invoke: async () => { throw { code: 0, msg: '缓存清理失败' } } },
        { label: 'wrong category response', invoke: async () => ({ code: 1, data: { category_id: 999, is_show: 0 } }) },
        { label: 'unknown response state', invoke: async () => ({ code: 1, data: { category_id: 11, is_show: 7 } }) },
        { label: 'missing response state', invoke: async () => ({ code: 1, data: { category_id: 11 } }) },
        { label: 'missing success envelope', invoke: async () => ({ data: { category_id: 11, is_show: 0 } }) }
    ]) {
        const env = environment({ visibility: failure.invoke }); await env.load(); const before = plain(env.row(11))
        await env.state.toggleVisibility(env.row(11), false)
        eq(env.row(11), before, failure.label + ': failed/unverified write does not mutate row')
        eq(env.state.switchingIds.value, [], failure.label + ': failure releases lock')
        ok(env.toasts.some(item => /核对|刷新/.test(item.title)), failure.label + ': feedback directs user to verify uncertain persisted result')
        ok(env.state.visibilityUncertainIds.value.includes(11), failure.label + ': unverified category is marked for readback')
        await env.state.toggleVisibility(env.row(11), false)
        eq(callsOf(env, 'visibility').length, 1, failure.label + ': uncertain result cannot be blindly submitted again')
        await env.load()
        eq(env.state.visibilityUncertainIds.value, [], failure.label + ': successful authoritative reload clears uncertain state')
        env.dispose()
    }
    {
        const env = environment({ visibility: async () => { throw { code: 0, msg: '没有分类编辑权限' } } }); await env.load()
        await env.state.toggleVisibility(env.row(11), false)
        eq(env.row(11).is_show, 1, 'explicit backend denial preserves original visibility')
        eq(env.state.visibilityUncertainIds.value, [11], 'generic backend denial requires readback because code 0 may also follow a committed write')
        ok(env.toasts.some(item => item.title.includes('没有分类编辑权限')), 'explicit backend denial keeps actionable server reason')
        env.dispose()
    }
    for (const mode of ['readonly', 'public']) {
        const env = environment({ canEdit: mode !== 'readonly' }); await env.load()
        const row = env.row(mode === 'public' ? 30 : 11), before = plain(row)
        await env.state.toggleVisibility(row, false)
        env.state.openCategory(row)
        await env.state.toggleVisibility(env.state.selected.value, false)
        env.state.chooseImage()
        eq(callsOf(env, 'visibility').length, 0, mode + ': both list and detail visibility writes are blocked')
        eq(callsOf(env, 'choose').length, 0, mode + ': image editing remains blocked')
        eq(row, before, mode + ': category remains unchanged')
        eq(env.state.editable.value, false, mode + ': detail remains read-only')
        env.dispose()
    }
    {
        const upload = defer(), env = environment({ upload: () => upload.promise }); await env.load(); const s = env.state
        s.openCategory(env.row(11))
        await chooseDraft(env)
        eq(callsOf(env, 'choose')[0][1], { count: 1, sourceType: ['album', 'camera'], sizeType: ['original'] }, 'picker retains original long-image flow')
        eq(callsOf(env, 'upload')[0][1], { filePath: '/tmp/mobile-quote-draft.png', name: 'file', timeout: 120000 }, 'upload still uses framework image API and timeout')
        eq(s.selected.value.images, 'upload/iphone.png', 'selecting/uploading draft does not publish it')
        eq(s.draftPath.value, '', 'pending upload is not considered ready to publish')
        upload.resolve({ code: 1, data: { url: 'upload/new-quote.png' } }); await flush()
        eq(s.draftPath.value, 'upload/new-quote.png', 'uploaded image remains a draft until explicit image confirmation')
        s.toggleHistory(); await flush()
        const history = plain(s.historyRows.value), draft = [s.draftLocal.value, s.draftPath.value]
        await s.toggleVisibility(s.selected.value, false)
        eq([s.selected.value.is_show, env.row(11).is_show], [0, 0], 'detail switch synchronizes confirmed state back to list')
        eq([s.draftLocal.value, s.draftPath.value], draft, 'visibility switch preserves selected/uploaded draft')
        eq(s.historyRows.value, history, 'visibility switch preserves image update history')
        eq(callsOf(env, 'image').length, 0, 'visibility switch never publishes pending image')
        env.confirm = false; await s.saveQuote()
        eq(callsOf(env, 'image').length, 0, 'canceling image publication preserves draft without calling API')
        eq(s.draftPath.value, 'upload/new-quote.png', 'canceling image update retains uploaded draft')
        env.confirm = true; await s.saveQuote()
        eq(callsOf(env, 'image').at(-1), ['image', 11, { images: 'upload/new-quote.png', expected_images: 'upload/iphone.png', remark: '移动管理端更新报价图' }], 'existing image publication still submits only image payload and original image concurrency guard')
        eq([s.selected.value.images, env.row(11).images], ['upload/new-quote.png', 'upload/new-quote.png'], 'confirmed image publication updates both detail and list')
        eq([s.selected.value.is_show, env.row(11).is_show], [0, 0], 'upload and publication do not re-enable hidden category')
        eq([env.row(11).sort, env.row(11).need_vip], [5, 1], 'image publication does not change sort or membership rule')
        eq([s.draftLocal.value, s.draftPath.value, s.saving.value], ['', '', false], 'successful image publication clears only draft/submission state')
        ok(env.toasts.some(item => item.title === '报价已更新'), 'existing image success feedback remains intact')
        env.dispose()
    }
    console.log(`[通过] ${checks} 项移动报价树/开关隔离回归；真实 SFC、模板和 helper；无浏览器、网络、数据库或生产写入。`)
}
main().catch(error => { console.error(error); process.exitCode = 1 }).finally(() => { for (const timer of timers) clearTimeout(timer) })
