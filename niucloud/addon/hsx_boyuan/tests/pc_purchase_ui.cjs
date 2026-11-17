const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '../../../..')
const app = path.join(root, 'admin')
const sfc = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
const ts = require(path.join(app, 'node_modules/typescript'))
const vue = require(path.join(app, 'node_modules/vue'))
const transpile = source => ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const filename = path.join(app, 'src/addon/hsx_boyuan/views/purchase.vue')
const parsed = sfc.parse(fs.readFileSync(filename, 'utf8'), { filename })
assert.equal(parsed.errors.length, 0)
const script = sfc.compileScript(parsed.descriptor, { id: 'boyuan-pc' })
assert.equal(sfc.compileTemplate({ source: parsed.descriptor.template.content, filename, id: 'boyuan-pc', compilerOptions: { bindingMetadata: script.bindings } }).errors.length, 0)
const pcEntry = fs.readFileSync(path.join(root, 'admin/src/addon/hsx_erp/views/erp/purchase/list.vue'), 'utf8')
assert.match(pcEntry, /router\.push\(\{ path: action\.pc_route, query: \{ purchase_id: Number\(detail\.data\?\.id\) \} \}\)/)
assert.doesNotMatch(pcEntry, /hsx_boyuan/)
assert.doesNotMatch(fs.readFileSync(filename, 'utf8'), /getLogs|detail\.logs|boyuan\/logs/)
const helper = { exports: {} }
vm.runInNewContext(transpile(fs.readFileSync(path.join(app, 'src/addon/hsx_boyuan/utils/purchase-form.ts'), 'utf8')), { exports: helper.exports, module: helper, Set })
const calls = [], mounted = [], timers = [], messages = []
let leaveGuard, holdConfirmation = false, confirmationResolve, confirmationReject, confirmationCount = 0
const config = { mode: 'test', enabled: true, purchase_enabled: true, paused: false, profiles: { test: { connection_key: 'A', probe_at: 1 } } }
let current = { task: { id: 7, business_no: 'CG-7', status: 'awaiting_confirmation', revision: 1, mode: 'test', connection_key: 'A', external_no: '', side_effect: 'none' }, source: { lines: [{ source_line_id: 'purchase_line:1', product_name: '测试手机', imei: '123456789012345', amount: '99', quantity: 1 }] }, selection: { company_id: 'C', supplier_id: 'S', creator_id: 'CR', buyer_id: '', lines: [{ source_line_id: 'purchase_line:1', product_id: 'P', warehouse_id: 'W' }] }, editable: true, can_submit: false, can_verify: false, can_remember: false, labels: { company: { name: '公司' } }, issues: [] }
const clone = value => JSON.parse(JSON.stringify(value))
const api = {
    getConfig: async () => ({ data: clone(config) }),
    capturePurchaseSource: async id => { calls.push(['capture', id]); return { data: { task_id: 7, task_ids: [7, 8], view: clone(current) } } },
    getPurchase: async id => { calls.push(['view', id]); return { data: { ...clone(current), task: { ...clone(current.task), id } } } },
    getReferences: async input => { calls.push(['references', clone(input)]); return { data: { code: 'OK', data: { items: [{ id: 'S2', name: '第二个供应商', enabled: true, extra: { is_supplier: true } }], next_cursor: input.cursor === '1' ? '2' : '' } } } },
    savePurchaseDraft: async (id, input) => { calls.push(['draft', id, clone(input)]); current.selection = clone(input.selection); current.task.revision++; return { data: clone(current) } },
    submitPurchase: async (id, input) => { calls.push(['submit', id, clone(input)]); current = { ...current, editable: false, task: { ...current.task, status: 'pending', revision: current.task.revision + 1 } }; return { data: clone(current) } },
    verifyPurchase: async (id, input) => { calls.push(['verify', id, clone(input)]); return { data: clone(current) } }
}
const component = { exports: {} }
vm.runInNewContext(transpile(script.content), { exports: component.exports, module: component, require: name => name === 'vue' ? { ...vue, onMounted: fn => mounted.push(fn), onUnmounted: () => {} } : name === 'vue-router' ? { useRoute: () => ({ query: { purchase_id: '23' } }), onBeforeRouteLeave: fn => { leaveGuard = fn } } : name === 'element-plus' ? { ElMessage: { success: text => messages.push(text) }, ElMessageBox: { confirm: () => { confirmationCount++; return holdConfirmation ? new Promise((resolve, reject) => { confirmationResolve = resolve; confirmationReject = reject }) : Promise.resolve(true) } } } : name === '../api/purchase' ? api : name === '../utils/purchase-form' ? helper.exports : (() => { throw new Error(name) })(), setInterval: fn => { timers.push(fn); return timers.length }, clearInterval: () => {}, console })
const ui = component.exports.default.setup({}, { expose: () => {} })
const flush = async () => { for (let index = 0; index < 20; index++) await Promise.resolve(); await vue.nextTick() }
async function run() {
    const style = await sfc.compileStyleAsync({ filename, source: parsed.descriptor.styles[0].content, id: 'data-v-boyuan-pc', scoped: true, preprocessLang: 'scss' })
    assert.equal(style.errors.length, 0)
    mounted[0](); await flush()
    assert.deepEqual(calls[0], ['capture', 23])
    assert.equal(ui.taskIds.value.length, 2, 'all captured batches must remain accessible')
    assert.equal(ui.canSubmit.value, true, 'unsaved complete local form may submit even if previous server completeness was false')
    assert.equal(ui.remember.value, false)
    assert.equal(ui.topFields.find(field => field.key === 'creator_id').role, undefined)
    ui.selection.value.creator_id = ''
    assert.equal(ui.canSubmit.value, false)
    ui.selection.value.creator_id = 'CR'
    ui.openLookup(ui.topFields[1]); await flush()
    assert.equal(ui.choiceCursor.value, '2')
    await ui.loadChoices(true)
    assert.equal(ui.choices.value.length, 1, 'pagination deduplicates stable external ids')
    assert.equal(ui.choiceCursor.value, '')
    ui.selectChoice(ui.choices.value[0])
    assert.equal(ui.selection.value.supplier_id, 'S2')
    assert.equal(ui.dirty.value, true)
    assert.equal(await leaveGuard(), true)
    await ui.saveDraft()
    assert.equal(calls.filter(call => call[0] === 'submit').length, 0)
    assert.equal(calls.find(call => call[0] === 'draft')[2].remember, false)
    assert.equal(ui.closureExpanded.value, false)
    assert.equal(ui.detailsExpanded.value, false)
    holdConfirmation = true
    const priorConfirmations = confirmationCount
    const canceledSubmit = ui.confirmSubmit()
    ui.confirmSubmit(); await flush()
    assert.equal(confirmationCount, priorConfirmations + 1, 'repeat click cannot create another confirmation')
    assert.equal(ui.interactionLocked.value, true)
    assert.equal(await leaveGuard(), false, 'cannot leave while a submit confirmation is active')
    const savedCount = calls.filter(call => call[0] === 'draft').length
    await ui.saveDraft()
    assert.equal(calls.filter(call => call[0] === 'draft').length, savedCount)
    confirmationReject(new Error('cancel'))
    await canceledSubmit
    assert.equal(ui.interactionLocked.value, false)
    assert.equal(calls.filter(call => call[0] === 'submit').length, 0)
    holdConfirmation = false
    await ui.confirmSubmit()
    assert.equal(calls.filter(call => call[0] === 'submit').length, 1)
    assert.equal(ui.editable.value, false)
    await ui.confirmSubmit()
    assert.equal(calls.filter(call => call[0] === 'submit').length, 1)
    current = { ...current, can_verify: true, task: { ...current.task, status: 'created_review', external_no: 'BY-CREATED' }, result: { created: true } }
    timers[0](); await flush()
    assert.equal(ui.stateLabel.value, '已建单待核验')
    current = { ...current, task: { ...current.task, status: 'succeeded' }, result: { created: true, verified: true } }
    timers[0](); await flush()
    assert.match(ui.stateMessage.value, /库存余额与博远财务未验证/)
    assert.equal(ui.canVerify.value, false)
    current = { ...current, can_verify: true, task: { ...current.task, status: 'confirming', external_no: '' }, result: {} }
    await ui.refresh()
    ui.externalNo.value = 'BY-KNOWN'
    await ui.refresh(true)
    assert.equal(ui.externalNo.value, 'BY-KNOWN', 'polling cannot erase a manually typed known order')
    await ui.refresh()
    assert.equal(ui.externalNo.value, 'BY-KNOWN', 'manual refresh cannot erase a manually typed known order')
    await ui.verify()
    const verify = calls.find(call => call[0] === 'verify')[2]
    assert.equal(verify.external_no, 'BY-KNOWN')
    assert.equal(Object.hasOwn(verify, 'selection'), false)
    await ui.switchTask(8)
    assert.equal(ui.taskId.value, 8)
    assert.equal(ui.view.value.task.id, 8)
    ui.applyView({ ...clone(current), editable: true, can_verify: true, can_operate: false, can_remember: false, task: { ...clone(current.task), id: 8, status: 'awaiting_confirmation' } })
    const writeCount = calls.filter(call => ['draft', 'submit', 'verify'].includes(call[0])).length
    assert.equal(ui.editable.value, false)
    assert.equal(ui.canVerify.value, false)
    await ui.saveDraft(); await ui.confirmSubmit(); await ui.verify()
    assert.equal(calls.filter(call => ['draft', 'submit', 'verify'].includes(call[0])).length, writeCount)
    assert.ok(messages.some(text => text.includes('尚未发送')))
    assert.match(parsed.descriptor.template.content, /v-else-if="!loading && !error"/)
    assert.match(parsed.descriptor.styles[0].content, /--el-bg-color/)
    assert.match(parsed.descriptor.styles[0].content, /max-height: 740px/)
    console.log('PASS: PC SFC, collapsed closure/evidence, confirmation lock/cancel, safe error state, ERP entry, multiple batches, optional buyer, paginated archives, no-send save, frozen submit, manual/polling input preservation and read-only permissions')
}
run().catch(error => { console.error(error); process.exitCode = 1 })
