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
assert.match(pcEntry, /action\.query\?\.purchase_no === detail\.data\?\.purchase_no[^\n]+\{ purchase_no: action\.query\.purchase_no \} : \{ purchase_id: Number\(detail\.data\?\.id\) \}/)
assert.doesNotMatch(pcEntry, /hsx_boyuan/)
assert.doesNotMatch(fs.readFileSync(filename, 'utf8'), /getLogs|detail\.logs|boyuan\/logs/)
const helper = { exports: {} }
vm.runInNewContext(transpile(fs.readFileSync(path.join(app, 'src/addon/hsx_boyuan/utils/purchase-form.ts'), 'utf8')), { exports: helper.exports, module: helper, Set })
const calls = [], mounted = [], timers = [], messages = []
const receipt = {exports:{}}
const receiptSource = fs.readFileSync(path.join(app,'src/addon/hsx_boyuan/utils/manual-task-receipt.ts'),'utf8')
assert.equal(receiptSource,fs.readFileSync(path.join(root,'niucloud/addon/hsx_boyuan/admin/utils/manual-task-receipt.ts'),'utf8'))
vm.runInNewContext(transpile(receiptSource),{exports:receipt.exports,module:receipt})
let leaveGuard, holdConfirmation = false, confirmationResolve, confirmationReject, confirmationCount = 0, lastConfirm
const config = { mode: 'test', enabled: true, purchase_enabled: true, paused: false, profiles: { test: { connection_key: 'A', probe_at: 1 } } }
let current = { active_connection:true, write_enabled:true, task: { id: 7, business_no: 'CG-7', status: 'awaiting_confirmation', revision: 1, mode: 'test', connection_key: 'A', external_no: '', side_effect: 'none' }, source: { lines: [{ source_line_id: 'purchase_line:1', product_name: '测试手机', imei: '123456789012345', amount: '99', quantity: 1 }] }, selection: { company_id: 'C', supplier_id: 'S', creator_id: 'CR', buyer_id: '', lines: [{ source_line_id: 'purchase_line:1', product_id: 'P', warehouse_id: 'W' }] }, editable: true, can_submit: false, can_verify: false, can_remember: false, labels: { company: { name: '公司' } }, issues: [] }
const clone = value => JSON.parse(JSON.stringify(value))
const api = {
    getConfig: async () => { calls.push(['config']); throw Error('没有查看连接的权限') },
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
vm.runInNewContext(transpile(script.content), { exports: component.exports, module: component, require: name => name === 'vue' ? { ...vue, onMounted: fn => mounted.push(fn), onUnmounted: () => {} } : name === 'vue-router' ? { useRoute: () => ({ query: { purchase_id: '23' } }), useRouter: () => router, onBeforeRouteLeave: fn => { leaveGuard = fn } } : name === '@/stores/modules/user' ? {default:()=>user} : name === 'element-plus' ? { ElMessage: { success: text => messages.push(text), info: text => messages.push(text), warning: text => messages.push(text) }, ElMessageBox: { confirm: (text, title, options) => { lastConfirm = {text,title,options}; confirmationCount++; return holdConfirmation ? new Promise((resolve, reject) => { confirmationResolve = resolve; confirmationReject = reject }) : Promise.resolve(true) } } } : name === '../utils/manual-task-receipt' ? receipt.exports : name === '../api/purchase' ? api : name === '../utils/purchase-form' ? helper.exports : name.endsWith('.vue') ? {} : (() => { throw new Error(name) })(), setInterval: fn => { timers.push(fn); return timers.length }, clearInterval: () => {}, console })
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
    assert.equal(ui.visibleTopFields.value.length,0);assert.equal(ui.visibleSourceLines.value.length,0)
    ui.matchedExpanded.value=true; assert.equal(ui.visibleTopFields.value.length,4); ui.matchedExpanded.value=false
    ui.applyView({...clone(current),active_connection:true,write_enabled:false});assert.equal(ui.canSubmit.value,false)
    const pricingCase=clone(current)
    pricingCase.pricing_buyer={required:true,status:'known',name:'实际定价员工',local_ref:'staff:42',mapped_id:''}
    ui.applyView(clone(pricingCase))
    assert.equal(ui.canSubmit.value,false,'a cashier or creator does not count as purchaser')
    assert.ok(ui.visibleTopFields.value.some(f=>f.key==='buyer_id'),'the missing buyer must not be hidden')
    pricingCase.selection.buyer_id='B1'; pricingCase.labels.buyer={id:'B1',name:'博远定价员工'}
    pricingCase.pricing_buyer.mapped_id='B2'; ui.applyView(clone(pricingCase)); assert.equal(ui.canSubmit.value,false)
    pricingCase.pricing_buyer.mapped_id='B1'; ui.applyView(clone(pricingCase)); assert.equal(ui.canSubmit.value,true); assert.equal(ui.buyerLocked.value,true)
    ui.applyView({...pricingCase,editable:false,pricing_buyer:{...pricingCase.pricing_buyer,receipt_status:'matched',receipt_source:'purchase_details',returned_name:'明细中的定价员'}})
    assert.equal(ui.buyerReceiptLabel.value,'明细中的定价员（已核对博远采购明细）')
    for(const status of ['missing','mixed','conflict']){
        ui.applyView({...pricingCase,pricing_buyer:{...pricingCase.pricing_buyer,status}})
        assert.equal(ui.canSubmit.value,false,'a preselected employee cannot override '+status)
    }
    ui.applyView(clone(current))
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
    assert.deepEqual(navigation, [{name:'hsx_erp_payable',query:{source_no:'CG-7',imei:'123456789012345'}}])
    const settled=clone(financeCase)
    settled.closure.operator_complete=true;settled.closure.totals={amount:'99.00',paid_amount:'99.00',unpaid_amount:'0.00'};settled.closure.stages.forEach(s=>s.status='verified')
    settled.payment={task_id:70,status:'registered_review',operator_complete:true,allocation_verified:false}
    ui.applyView(settled);assert.equal(ui.nextStep.value.action,'receipt');assert.match(ui.nextStep.value.title,/¥99.00/);assert.match(ui.nextStep.value.detail,/无需再次付款.*核销：待核对/);assert.equal(ui.canOpenPayable.value,false)
    ui.applyView(financeCase)
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
    const slice={...clone(current),inventory:{...clone(current.inventory),can_process_current:true}}
    ui.applyView(slice);assert.equal(ui.canStartInventory.value,true);assert.equal(ui.inventoryActionLabel.value,'继续核对本批库存')
    ui.applyView({...clone(slice),inventory:{...slice.inventory,status:'processing'}});assert.equal(ui.canStartInventory.value,false)
    ui.applyView({...clone(slice),can_check_inventory:false});assert.equal(ui.canStartInventory.value,false)
    ui.applyView(clone(current));assert.equal(ui.canStartInventory.value,false)
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
    ui.error.value=''
    const originalStockCheck=api.checkPurchaseInventory
    ui.applyView({...clone(slice),task:{...slice.task,id:ui.taskId.value}}); let stockClicks=0
    api.checkPurchaseInventory=async(id)=>{ stockClicks++;return {data:{...clone(slice),task:{...slice.task,id:ui.taskId.value},inventory:{...slice.inventory,status:'succeeded',query_date:'2026-11-18',checked_at:1794960000,total_count:4,in_stock_count:4}}} }
    await ui.checkInventory();await ui.checkInventory();assert.equal(stockClicks,1);assert.match(messages.at(-1),/4 台设备已核对在库/)
    api.checkPurchaseInventory=originalStockCheck
    const pending = {...clone(current),active_connection:true,write_enabled:true,can_operate:true,can_process_current:true,editable:false,task:{...clone(current.task),id:ui.taskId.value,status:'pending',side_effect:'none',revision:14}}
    ui.applyView({...clone(pending),can_process_current:false});assert.equal(ui.currentAction.value,'')
    ui.applyView({...clone(pending),task:{...pending.task,side_effect:'unknown'}});assert.equal(ui.currentAction.value,'')
    ui.applyView(pending);assert.equal(ui.currentAction.value,'submit')
    let resumeBody
    api.submitPurchase=async(id,body)=>{ resumeBody=clone(body);return {data:{...clone(pending),task:{...pending.task,id,revision:15,status:'succeeded',external_no:'BY-15'}}} }
    await ui.processCurrent();assert.deepEqual(resumeBody,{revision:14,mode:pending.task.mode,connection_key:pending.task.connection_key,intent:'process_current'})
    assert.match(messages.at(-1),/已核实入库.*BY-15/);assert.equal(ui.currentAction.value,'')
    const checking={...clone(pending),task:{...pending.task,status:'confirming',side_effect:'unknown'}}
    ui.applyView(checking);assert.equal(ui.currentAction.value,'verify')
    let checkBody;api.verifyPurchase=async(id,body)=>{checkBody=clone(body);return {data:{...clone(checking),task:{...checking.task,id:id+123,status:'succeeded',external_no:'WRONG'}}}}
    await ui.processCurrent();assert.equal(checkBody.intent,'process_current');assert.equal(ui.view.value.task.status,'confirming');assert.match(ui.error.value,/刷新原单/);assert.ok(!messages.some(text=>text.includes('WRONG')))
    api.submitPurchase=originalSubmit
    const template = parsed.descriptor.template.content
    // Exercise the real confirm handler, not just the button state.
    const buyerView={...clone(pricingCase), pricing_buyer:{required:true,status:'known',local_ref:'staff:42',name:'实际定价员工',mapped_id:''}}
    let buyerSubmit=null
    api.submitPurchase=async(id,body)=>{buyerSubmit=clone(body);return {data:{...clone(buyerView),task:{...buyerView.task,revision:buyerView.task.revision+1,status:'created_review',external_no:'BY-TEST'}}}}
    ui.applyView(clone(buyerView))
    holdConfirmation=true; const cancelledBuyer=ui.confirmSubmit(); await flush()
    assert.match(lastConfirm.text,/实际定价员工.*博远定价员工/);assert.match(lastConfirm.options.confirmButtonText,/确认人员对应/)
    confirmationReject(new Error('cancel')); await cancelledBuyer;holdConfirmation=false;assert.equal(buyerSubmit,null)
    await ui.confirmSubmit();assert.equal(buyerSubmit.confirm_pricing_buyer,true);assert.equal(buyerSubmit.selection.buyer_id,'B1')
    buyerView.pricing_buyer.mapped_id='B1';ui.applyView(clone(buyerView));buyerSubmit=null
    await ui.confirmSubmit();assert.equal(buyerSubmit.confirm_pricing_buyer,undefined);assert.equal(buyerSubmit.selection.buyer_id,'B1')
    api.submitPurchase=originalSubmit
    assert.match(template,/editable \? visibleTopFields.length : detailsExpanded/)
    assert.match(template,/editable \? visibleSourceLines.length : detailsExpanded/)
    assert.ok(!calls.some(c=>c[0]==='config'),'purchase review does not read management settings for a regular employee')
    assert.match(template,/<template v-if="detailsExpanded">[\s\S]*?class="inventory-panel"/)
    assert.doesNotMatch(template.split('</header>')[0], /@click="checkInventory"/)
    assert.match(template,/v-if="inventory.status === 'failed'"[^>]*title="本批设备的库存查询发现异常"/)
    assert.match(template,/v-if="canVerify" class="verify"/)
    assert.ok(messages.some(text => text.includes('尚未发送')))
    assert.match(parsed.descriptor.template.content, /v-else-if="!loading && !error && !originalReview"/)
    const old={account_id:'P',mode:'production',revision:9,connection_key:'Z',event_id:'EV1',purchase_no:'PO1',imei:'001234567890123',amount:'1.00'}
    const fresh=component.exports.default.setup({}, {expose(){}})
    api.capturePurchaseSource=async()=>({data:{original_review:clone(old)}})
    let prepared=0
    api.preparePurchaseOriginal=async(id,scope)=>{assert.equal(id,23);assert.deepEqual(clone(scope),old);prepared++;return {data:{task_id:12,task_ids:[12]}}}
    await fresh.refresh()
    assert.equal(fresh.taskId.value,0);assert.equal(fresh.originalReview.value.imei,old.imei);assert.equal(prepared,0,'opening old source is not consent to prepare or send')
    await Promise.all([fresh.prepareOriginal(),fresh.prepareOriginal()])
    assert.equal(prepared,1);assert.equal(fresh.taskId.value,12);assert.equal(fresh.originalReview.value,null)
    assert.match(parsed.descriptor.styles[0].content, /--el-bg-color/)
    assert.match(parsed.descriptor.styles[0].content, /max-height: 740px/)
    console.log('PASS: PC SFC/styles, collapsed closure, confirmation lock/cancel, safe errors, ERP entry, paginated archives, no-send/frozen submit, input preservation, read-only inventory queue/poll/success/difference/stale and permissions')
}
run().catch(error => { console.error(error); process.exitCode = 1 })
