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
    verifyPurchase: async (id, input) => { calls.push(['verify', id, clone(input)]); return { data: clone(current) } },
    checkPurchaseInventory: async (id, input) => { calls.push(['inventory', id, clone(input)]); current = { ...current, inventory: { task_id: 71, status: 'pending', query_date: null, checked_at: null, total_count: null, in_stock_count: null, reason: '只读查询已排队' } }; return { data: clone(current) } }
}
const component = { exports: {} }
const user = vue.reactive({rules:['hsx_erp_payable_items_payment']})
const menu = vue.ref(true), navigation = []
const router = {hasRoute: name => menu.value && name === 'hsx_erp_payable', push: loc => { navigation.push(JSON.parse(JSON.stringify(loc))); return Promise.resolve() }}
vm.runInNewContext(transpile(script.content), { exports: component.exports, module: component, require: name => name === 'vue' ? { ...vue, onMounted: fn => mounted.push(fn), onUnmounted: () => {} } : name === 'vue-router' ? { useRoute: () => ({ query: { purchase_id: '23' } }), useRouter: () => router, onBeforeRouteLeave: fn => { leaveGuard = fn } } : name === '@/stores/modules/user' ? {default:()=>user} : name === 'element-plus' ? { ElMessage: { success: text => messages.push(text) }, ElMessageBox: { confirm: () => { confirmationCount++; return holdConfirmation ? new Promise((resolve, reject) => { confirmationResolve = resolve; confirmationReject = reject }) : Promise.resolve(true) } } } : name === '../api/purchase' ? api : name === '../utils/purchase-form' ? helper.exports : name.endsWith('.vue') ? {} : (() => { throw new Error(name) })(), setInterval: fn => { timers.push(fn); return timers.length }, clearInterval: () => {}, console })
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
    assert.equal(ui.canVerify.value, true)
    assert.equal(ui.canOpenPayable.value, false)
    current = { ...current, task: { ...current.task, status: 'succeeded' }, result: { created: true, verified: true } }
    timers[0](); await flush()
    assert.match(ui.stateMessage.value, /入库串号明细已核对/)
    assert.equal(ui.canVerify.value, false)
    assert.equal(ui.canStartInventory.value, false)
    current = { ...current, can_check_inventory: true, inventory: { task_id: null, status: 'not_checked', query_date: null, checked_at: null, total_count: null, in_stock_count: null, reason: '' } }
    await ui.refresh()
    assert.equal(ui.canStartInventory.value, true)
    // A finance detail link is read-only navigation and must belong to the same purchase and current role.
    const financeCase = clone(current)
    financeCase.closure = {schema:'boyuan.purchase.closure.v1',available:true, operator_complete:false, totals:{amount:'99.00',paid_amount:'0.00',unpaid_amount:'99.00'}, stages:['local_inbound','remote_purchase','remote_stock','local_payable','local_payment','remote_settlement'].map(key=>({key,status:['local_inbound','remote_purchase','local_payable'].includes(key)?'verified':'pending'}))}
    financeCase.can_operate = false  // purchasing and finance are independent permissions
    ui.applyView(financeCase)
    assert.equal(ui.nextStep.value.action, 'payable')
    assert.equal(ui.canOpenPayable.value, true)
    await ui.openPayable()
    assert.deepEqual(navigation, [{name:'hsx_erp_payable',query:{source_no:'CG-7'}}])
    menu.value = false; assert.equal(ui.canOpenPayable.value,false); await ui.openPayable()
    menu.value = true; user.rules=[]; assert.equal(ui.canOpenPayable.value,false); await ui.openPayable()
    user.rules=['hsx_erp_payable_items_payment']
    financeCase.closure.stages.find(s=>s.key==='local_payable').status='permission_denied'
    ui.applyView(clone(financeCase)); assert.equal(ui.canOpenPayable.value,false); await ui.openPayable()
    assert.equal(navigation.length,1,'never navigate without both installed routes and the appropriate permission')
    ui.applyView(clone(current))
    await Promise.all([ui.checkInventory(), ui.checkInventory()])
    assert.equal(calls.filter(call => call[0] === 'inventory').length, 1)
    assert.deepEqual(Object.keys(calls.find(call => call[0] === 'inventory')[2]).sort(), ['connection_key', 'mode', 'revision'])
    assert.equal(ui.inventoryLabel.value, '已排队')
    await ui.checkInventory(); await ui.refresh()
    assert.equal(calls.filter(call => call[0] === 'inventory').length, 1, 'read-only refresh does not requeue')
    current.inventory = { ...current.inventory, status: 'processing' }
    timers[0](); await flush()
    assert.equal(ui.inventoryLabel.value, '核对中')
    current.inventory = { ...current.inventory, status: 'succeeded', query_date: '2026-11-18', checked_at: 1794960000, total_count: 1, in_stock_count: 1 }
    timers[0](); await flush()
    assert.equal(ui.inventoryLabel.value, '本批次当前在库已核对')
    assert.equal(ui.canStartInventory.value, false)
    assert.equal(ui.closure.value.complete, false)
    current.inventory = { ...current.inventory, status: 'failed', in_stock_count: 0, reason: '1台未在当前库存结果内' }
    await ui.refresh()
    assert.equal(ui.inventoryLabel.value, '当前在库有差异')
    current.inventory = { ...current.inventory, status: 'stale', total_count: 0, in_stock_count: 0 }
    await ui.refresh()
    assert.equal(ui.inventory.value.in_stock_count, null)
    current.can_check_inventory = false
    await ui.refresh(); await ui.checkInventory()
    assert.equal(calls.filter(call => call[0] === 'inventory').length, 1)
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
    ui.detailsExpanded.value = true
    await ui.switchTask(8)
    assert.equal(ui.detailsExpanded.value, false, 'different purchase batches start collapsed')
    assert.equal(ui.taskId.value, 8)
    assert.equal(ui.view.value.task.id, 8)
    ui.applyView({ ...clone(current), editable: true, can_verify: true, can_check_inventory: true, can_operate: false, can_remember: false, task: { ...clone(current.task), id: 8, status: 'succeeded' } })
    const writeCount = calls.filter(call => ['draft', 'submit', 'verify', 'inventory'].includes(call[0])).length
    assert.equal(ui.editable.value, false)
    assert.equal(ui.canVerify.value, false)
    assert.equal(ui.canStartInventory.value, false)
    await ui.saveDraft(); await ui.confirmSubmit(); await ui.verify(); await ui.checkInventory()
    assert.equal(calls.filter(call => ['draft', 'submit', 'verify', 'inventory'].includes(call[0])).length, writeCount)
    current = {...clone(current), editable:true, task:{...current.task,id:8,status:'awaiting_confirmation'}}
    ui.applyView(clone(current))
    const originalSubmit = api.submitPurchase
    api.submitPurchase=async()=>{throw {code:0,msg:'[WAREHOUSE_COMPANY_MISMATCH] 仓库与公司不符，请重新选择'}}
    await ui.confirmSubmit()
    assert.equal(ui.awaitingReceipt.value,false)
    assert.equal(ui.editable.value,true,'server-rejected archive choices can be corrected without leaving the draft')
    assert.match(ui.error.value,/未提交.*仓库与公司不符/)
    for(const error of [
        {code:0,msg:'[PURCHASE_FROZEN] 此前已提交'}, {code:0,msg:'[CONNECTION_CHANGED] 账套已切换'},
        {code:0,msg:'当前状态未知，原因可能是 [ARCHIVE_STALE]'}, {errMsg:'request:fail timeout'},
        new Error('[PRODUCT_SERIAL_LENGTH] timeout'), {code:1,msg:'[ARCHIVE_STALE] stale'}, null
    ]) assert.equal(helper.exports.purchaseSelectionProblem(error),null,'only a typed rejection from before queueing allows editing')
    let uncertainWrites=0
    api.submitPurchase=async()=>{uncertainWrites++;throw Error('请求超时')}
    await ui.confirmSubmit()
    assert.equal(ui.awaitingReceipt.value,true)
    await ui.refresh(true)
    assert.equal(ui.canSubmit.value,false,'polling a stale draft must not offer submitting again')
    assert.match(ui.nextStep.value.title,/尚未收到/)
    await ui.confirmSubmit();assert.equal(uncertainWrites,1)
    ui.applyView({...clone(current),editable:false,task:{...current.task,status:'created_review'}})
    assert.equal(ui.awaitingReceipt.value,false)
    api.submitPurchase=originalSubmit
    const template = parsed.descriptor.template.content
    assert.match(template,/v-if="editable \|\| detailsExpanded"/)
    assert.match(template,/<template v-if="detailsExpanded">[\s\S]*?class="inventory-panel"/)
    assert.doesNotMatch(template.split('</header>')[0], /@click="checkInventory"/)
    assert.match(template,/v-if="inventory.status === 'failed'"[^>]*title="本批设备的库存查询发现异常"/)
    assert.match(template,/v-if="canVerify" class="verify"/)
    assert.ok(messages.some(text => text.includes('尚未发送')))
    assert.match(parsed.descriptor.template.content, /v-else-if="!loading && !error"/)
    assert.match(parsed.descriptor.styles[0].content, /--el-bg-color/)
    assert.match(parsed.descriptor.styles[0].content, /max-height: 740px/)
    console.log('PASS: PC SFC/styles, collapsed closure, confirmation lock/cancel, safe errors, ERP entry, paginated archives, no-send/frozen submit, input preservation, read-only inventory queue/poll/success/difference/stale and permissions')
}
run().catch(error => { console.error(error); process.exitCode = 1 })
