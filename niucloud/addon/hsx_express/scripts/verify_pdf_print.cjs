'use strict'
// Offline component/permission/lifecycle tests. No accounts, courier calls or physical printing.
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), deps = path.join(root, 'admin/node_modules')
const ts = require(path.join(deps, 'typescript')), sfc = require(path.join(deps, '@vue/compiler-sfc'))
let checks = 0
const check = (value, message) => { assert.ok(value, message); checks++ }
const componentFile = 'niucloud/addon/hsx_components/admin/components/HsxPdfPrint/index.vue'
const code = fs.readFileSync(path.join(root, componentFile), 'utf8')
const { descriptor } = sfc.parse(code)
const compiled = sfc.compileScript(descriptor, { id: 'pdf-qa' }).content
const js = ts.transpileModule(compiled, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const fixture = () => ({ blob: new Blob(['%PDF-1.4\nmock'], { type: 'application/octet-stream' }), filename: '测试.pdf' })
function instance(loader = async () => fixture(), extra = {}) {
    const refs = [], watchers = [], unmount = [], ticks = [], timers = new Map(), urls = [], released = []
    const vue = { defineComponent: v => v, ref: value => { const r = { value }; refs.push(r); return r }, watch: (source, callback) => watchers.push({ source, callback }), onBeforeUnmount: callback => unmount.push(callback), nextTick: callback => { ticks.push(callback); return Promise.resolve() } }
    const props = { loader, contextKey: 1, disabled: false, title: '打印面单', ...extra }
    const context = { exports: {}, Blob, navigator: { pdfViewerEnabled: true }, URL: { createObjectURL: blob => { urls.push(blob); return 'blob:local-' + urls.length }, revokeObjectURL: url => released.push(url) }, setTimeout: fn => { const id = timers.size + 1; timers.set(id, fn); return id }, clearTimeout: id => timers.delete(id), require: name => name === 'vue' ? vue : {} }
    vm.runInNewContext(js, context)
    const state = context.exports.default.setup(props, { expose() {} })
    return { state, props, context, watchers, unmount, timers, urls, released, flushTicks: () => { while (ticks.length) ticks.shift()() } }
}

// Exercise the real Vue patch queue, not just mocked refs. Dialog content remains
// mounted during its leave transition, so closing patches the iframe v-if first.
async function verifyVueCloseLifecycle(source) {
    const vue = require(path.join(deps, 'vue')), errors = [], released = []
    const remove = node => { if (node.parent) { node.parent.children.splice(node.parent.children.indexOf(node), 1); node.parent = null } }
    const node = tag => ({ tag, parent: null, children: [], props: {}, remove() { remove(this) } })
    const renderer = vue.createRenderer({
        createElement: tag => node(tag), createText: text => ({ ...node('#text'), text }), createComment: text => ({ ...node('#comment'), text }),
        setText: (node, text) => { node.text = text }, setElementText: (node, text) => { node.text = text },
        parentNode: node => node.parent, nextSibling: node => node.parent?.children[node.parent.children.indexOf(node) + 1] || null,
        insert(child, parent, anchor) {
            assert.ok(parent, 'Vue must not patch against a manually detached iframe parent')
            remove(child); child.parent = parent
            parent.children.splice(anchor ? parent.children.indexOf(anchor) : parent.children.length, 0, child)
        }, remove, patchProp: (node, key, before, after) => { node.props[key] = after }
    })
    const descriptor = sfc.parse(source).descriptor
    const script = sfc.compileScript(descriptor, { id: 'vue-lifecycle' })
    const template = sfc.compileTemplate({ source: descriptor.template.content, filename: componentFile, id: 'vue-lifecycle', compilerOptions: { bindingMetadata: script.bindings } })
    const dialog = vue.defineComponent({ props: ['modelValue'], setup: (props, { slots }) => () => vue.h('section', { 'data-visible': props.modelValue }, [slots.default?.(), slots.footer?.()]) })
    const button = vue.defineComponent({ props: ['action'], setup: (props, { slots }) => () => vue.h('button', { onClick: props.action }, slots.default?.()) })
    const context = { exports: {}, Blob, navigator: { pdfViewerEnabled: true }, URL: { createObjectURL: () => 'blob:vue-preview', revokeObjectURL: url => released.push(url) }, setTimeout, clearTimeout, require: name => name === 'vue' ? vue : { default: name.includes('HsxDialog') ? dialog : button } }
    const options = { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }
    vm.runInNewContext(ts.transpileModule(script.content + '\n' + template.code, options).outputText, context)
    let state
    const root = node('root')
    const component = context.exports.default, originalSetup = component.setup
    component.render = context.exports.render
    component.setup = (props, context) => { state = originalSetup(props, context); return state }
    const app = renderer.createApp(component, { loader: async () => fixture(), contextKey: 1 })
    app.component('el-button', button); app.component('el-alert', button); app.directive('loading', {})
    const find = (tag, element = root) => element.tag === tag ? element : element.children.map(child => find(tag, child)).find(Boolean)
    app.config.errorHandler = error => errors.push(error)
    app.mount(root)
    await state.open(); await vue.nextTick()
    check(!!find('iframe'), 'real Vue mounts PDF iframe')
    state.visible.value = false; await vue.nextTick(); await vue.nextTick()
    check(errors.length === 0, 'real Vue close completes without patch error')
    check(find('section').props['data-visible'] === false && !find('iframe'), 'real Vue closes dialog and removes preview itself')
    check(released.length === 1 && !state.frame.value, 'real Vue clears iframe ref then releases URL')
    await state.open(); await vue.nextTick()
    check(find('section').props['data-visible'] === true && !!find('iframe'), 'real Vue can reopen PDF after closing')
    app.unmount(); await vue.nextTick()
    check(root.children.length === 0 && released.length === 2, 'real Vue unmount releases reopened preview')
}
async function main() {
    let loads = 0, prints = 0, removes = 0
    const a = instance(async () => { loads++; return fixture() })
    await a.state.open()
    check(loads === 1 && a.state.visible.value && a.state.loading.value, 'click fetches original PDF once and displays loading')
    check(a.urls.length === 1 && a.urls[0].type === 'application/pdf', 'octet-stream response normalized for inline viewing')
    a.state.frame.value = { contentWindow: { focus() {}, print() { prints++ } }, remove() { removes++ } }
    a.state.loaded({ target: a.state.frame.value })
    check(prints === 1 && a.state.ready.value && !a.state.loading.value, 'PDF load opens print dialog once')
    a.state.loaded({ target: a.state.frame.value })
    check(prints === 1, 'duplicate load event does not print twice')
    a.state.requestPrint()
    check(prints === 2 && loads === 1, 'manual retry uses same PDF without a new order or download')
    check(a.released.length === 0, 'printing/cancellation does not prematurely revoke PDF')
    a.state.release()
    check(a.released.length === 0 && removes === 0 && !a.state.ready.value && !a.state.pdfUrl.value, 'closing leaves DOM removal to Vue and cancels preview immediately')
    a.flushTicks()
    check(a.released.length === 1 && removes === 0, 'closing releases PDF only after Vue has patched the iframe')
    a.state.requestPrint(); check(prints === 2, 'closed preview cannot print')

    const reopened = instance(); await reopened.state.open()
    reopened.state.close()
    check(!reopened.state.visible.value, 'footer close explicitly closes dialog')
    reopened.state.release(); await reopened.state.open(); reopened.flushTicks()
    check(reopened.released.length === 1 && reopened.released[0] === 'blob:local-1' && reopened.state.pdfUrl.value === 'blob:local-2', 'delayed cleanup never revokes the reopened preview URL')

    const rejected = instance(async () => { throw { msg: '原单 PDF 已过期' } })
    await rejected.state.open()
    check(rejected.state.error.value === '原单 PDF 已过期' && !rejected.state.loading.value && !rejected.urls.length, 'server error remains readable; no viewer or print')
    const invalid = instance(async () => ({ blob: new Blob(['{"msg":"未登录"}']) }))
    await invalid.state.open(); check(!!invalid.state.error.value && invalid.urls.length === 0, 'JSON/HTML response never prints')
    const unsupported = instance()
    unsupported.context.navigator.pdfViewerEnabled = false
    await unsupported.state.open(); check(unsupported.state.error.value.includes('Chrome') && !unsupported.urls.length, 'unsupported browser gets setup instructions')
    const disabled = instance(async () => { throw Error('must not fetch') }, { disabled: true })
    await disabled.state.open(); check(!disabled.state.visible.value, 'disabled button cannot fetch PDF')

    let resolve
    const stale = instance(() => new Promise(done => { resolve = done }))
    const pending = stale.state.open()
    stale.state.visible.value = false; stale.state.release(); resolve(fixture()); await pending
    check(stale.urls.length === 0, 'late response after closing does not reopen or print')
    const switched = instance(() => new Promise(done => { resolve = done }))
    const switching = switched.state.open()
    switched.props.contextKey = 2; switched.watchers[1].callback(2); resolve(fixture()); await switching
    check(!switched.state.visible.value && switched.urls.length === 0, 'switching package invalidates pending PDF')
    const unmounted = instance(() => new Promise(done => { resolve = done }))
    const unmounting = unmounted.state.open()
    unmounted.unmount[0](); resolve(fixture()); await unmounting
    check(unmounted.urls.length === 0, 'unmount invalidates pending PDF')
    const disabledDuringLoad = instance(() => new Promise(done => { resolve = done }))
    const disabling = disabledDuringLoad.state.open()
    disabledDuringLoad.props.disabled = true; disabledDuringLoad.watchers[2].callback(true); resolve(fixture()); await disabling
    check(!disabledDuringLoad.state.visible.value && disabledDuringLoad.urls.length === 0, 'revoked permission closes preview and ignores pending response')

    const slow = instance(); await slow.state.open()
    ;[...slow.timers.values()][0]()
    check(!slow.state.loading.value && slow.state.hint.value.includes('加载较慢'), 'viewer timeout ends spinner and explains next step')
    slow.state.frame.value = { contentWindow: { focus() {}, print() { throw Error('blocked') } }, remove() {} }
    slow.state.loaded({ target: slow.state.frame.value }); slow.state.requestPrint()
    check(slow.state.hint.value.includes('打印图标'), 'browser restriction offers in-viewer printing')
    slow.state.release()

    const mappings = [
        [componentFile, 'admin/src/addon/hsx_components/components/HsxPdfPrint/index.vue'],
        ['niucloud/addon/phone_shop/admin/views/order/components/provider-waybill-panel.vue', 'admin/src/addon/phone_shop/views/order/components/provider-waybill-panel.vue'],
        ['niucloud/addon/hsx_express/admin/views/tasks/index.vue', 'admin/src/addon/hsx_express/views/tasks/index.vue']
    ]
    for (const [source, mirror] of mappings) {
        const text = fs.readFileSync(path.join(root, source), 'utf8')
        check(text === fs.readFileSync(path.join(root, mirror), 'utf8'), 'source/runtime match: ' + source)
        const { descriptor, errors } = sfc.parse(text)
        check(!errors.length, 'SFC parse: ' + source)
        check(!!sfc.compileScript(descriptor, { id: 'test', inlineTemplate: true }).content, 'SFC compile: ' + source)
    }
    for (const [file, checkName] of [
        ['niucloud/addon/phone_shop/admin/utils/electronic-sheet-provider.ts', 'canDownloadProviderPdf'],
        ['niucloud/addon/hsx_express/admin/utils/sf.ts', 'canDownloadSfPdf']
    ]) {
        const source = fs.readFileSync(path.join(root, file), 'utf8')
        const context = { exports: {}, Blob, URL }
        vm.runInNewContext(ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText, context)
        const valid = { task_id: 1, id: 1, print_type: 'PDF', state: 'ready', label_state: 'ready', can_download: true, provider_key: 'hsx_express_sf_direct', pdf_download_path: 'hsx_express/tasks/1/pdf' }
        check(context.exports[checkName](valid), file + ' ready task permits original PDF')
        for (const state of ['cancelled', 'cancelling', 'cancel_unknown']) check(!context.exports[checkName]({ ...valid, state }), 'no printing for ' + state)
        check(!context.exports[checkName]({ ...valid, label_state: 'expired' }), 'expired PDF hidden')
        check(!context.exports[checkName]({ ...valid, can_download: false }), 'server permission honored')
    }
    const fixtureBase64 = fs.readFileSync(path.join(__dirname, 'fixtures/print-preview-pdf.base64'), 'utf8').trim()
    check(Buffer.from(fixtureBase64, 'base64').toString().startsWith('%PDF-'), 'browser fixture is a valid PDF header')
    check(Buffer.from(fixtureBase64, 'base64').toString().includes('/Count 1'), 'fixture contains one actual page')
    check(!/window\.open|\.download\s*=|localStorage|sessionStorage|afterprint.*success/.test(code), 'printing does not require popup/download or persist sensitive PDF')
    check(!/frame\.value\?\.remove\(/.test(code), 'close never manually removes Vue-owned iframe (insertBefore regression)')
    await verifyVueCloseLifecycle(code)
    console.log(`PASS: ${checks} PDF print checks; no real API calls or physical printing.`)
}
main().catch(err => { console.error(err); process.exitCode = 1 })
