const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '../../../..')
const frontend = path.join(root, 'site-uniapp')
const sfc = require(path.join(frontend, 'node_modules/@vue/compiler-sfc'))
const ts = require(path.join(frontend, 'node_modules/typescript'))
const vue = require(path.join(frontend, 'node_modules/vue'))
const plugin = path.join(frontend, 'src/addon/hsx_boyuan')
const transpile = source => ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const helperModule = { exports: {} }
vm.runInNewContext(transpile(fs.readFileSync(path.join(plugin, 'utils/purchase-form.ts'), 'utf8')), { exports: helperModule.exports, module: helperModule, Set })
const helpers = helperModule.exports
assert.equal(helpers.purchaseRouteId('23'), 23)
assert.equal(helpers.purchaseRouteId(23), 23)
for (const invalid of [0, -1, 1.2, '', '0', '01', '-2', '1e3', 'purchase:23', '9007199254740992', ['23'], { id: 23 }, null, undefined]) assert.equal(helpers.purchaseRouteId(invalid), 0)
const source = { source_document_no: 'CG-X / 7', supplier_name: '本站供货方', buyer_name: '本站采购员', lines: [{ source_line_id: 'purchase_line:1', product_name: '手机一', imei: '123456789012345', quantity: 1, amount: '100.00' }, { source_line_id: 'purchase_line:2', product_name: '手机二', sn: 'SN-2', quantity: 1, amount: '200.00' }] }
const completeSelection = { company_id: 'company-1', supplier_id: 'supplier-1', creator_id: 'creator-1', buyer_id: 'buyer-1', lines: source.lines.map((line, index) => ({ source_line_id: line.source_line_id, product_id: 'product-' + index, warehouse_id: 'warehouse-1', amount: '9999', imei: 'forged' })) }
const clean = helpers.purchaseSelection({ ...completeSelection, amount: '9999' }, source)
assert.equal(helpers.purchaseSelectionComplete(clean), true)
assert.deepEqual(Object.keys(clean).sort(), ['buyer_id', 'company_id', 'creator_id', 'lines', 'supplier_id'])
assert.deepEqual(Object.keys(clean.lines[0]).sort(), ['product_id', 'source_line_id', 'warehouse_id'])
assert.equal(helpers.purchaseSelectionComplete(helpers.purchaseSelection({}, source)), false)
assert.equal(helpers.purchaseSelectionComplete({ ...clean, lines: [clean.lines[0], clean.lines[0]] }), false)
assert.equal(helpers.purchaseSelectionComplete({ ...clean, lines: [] }), false)
assert.match(helpers.purchaseStateMessage({ status: 'awaiting_confirmation' }), /尚未发送/)
assert.match(helpers.purchaseStateMessage({ status: 'confirming', side_effect: 'unknown' }), /不能重复建单/)
assert.match(helpers.purchaseStateMessage({ status: 'pending', external_no: 'BY-1' }, { created: true }), /已建单，入库核验中/)
assert.match(helpers.purchaseStateMessage({ status: 'confirming', external_no: 'BY-MANUAL' }), /单号不代表成功/)
assert.equal(helpers.purchaseSelectionComplete({ ...clean, buyer_id: '' }), true, 'buyer is optional in the official contract')
assert.equal(helpers.purchaseSelectionComplete({ ...clean, creator_id: '' }), false, 'creator is mandatory and independent of optional buyer')
assert.match(helpers.purchaseStateMessage({ status: 'succeeded', external_no: 'BY-1' }), /入库串号明细已核对/)
assert.equal(helpers.purchaseInventory(null).status, 'not_checked')
assert.equal(helpers.purchaseInventory({ status: 'stale', total_count: 0, in_stock_count: 0 }).total_count, null)
assert.equal(helpers.purchaseInventory({ status: 'succeeded', total_count: 1, in_stock_count: 1 }).status, 'unavailable', 'success requires quantities and query-time evidence')
assert.equal(helpers.purchaseInventoryLabel(helpers.purchaseInventory({ status: 'failed', total_count: 2, in_stock_count: 1 })), '当前在库有差异')
assert.equal(helpers.purchaseMoney(null), '未取得')
assert.equal(helpers.purchaseMoney('0.00'), '0.00 元')
assert.equal(helpers.purchaseMoney(undefined), '未取得')
assert.equal(helpers.purchaseClosure(null).complete, false)
const stageKeys = ['local_inbound', 'remote_purchase', 'remote_stock', 'local_payable', 'local_payment', 'remote_settlement']
const closureFixture = { schema: 'boyuan.purchase.closure.v1', available: true, complete: true, totals: { amount: '300.00', paid_amount: '100.00', unpaid_amount: '200.00' }, stages: stageKeys.map(key => ({ key, status: 'verified', amount: '300.00', paid_amount: '100.00', unpaid_amount: '200.00' })), warnings: [] }
const blockedClosure = helpers.purchaseClosure({ ...closureFixture, stages: closureFixture.stages.map(stage => ({ ...stage, status: stage.key === 'remote_stock' ? 'unverified' : stage.key === 'remote_settlement' ? 'blocked' : stage.key === 'local_payment' ? 'partial' : 'verified' })) })
assert.equal(blockedClosure.complete, false, 'purchase verification or partial payment is never all-green closure')
assert.equal(blockedClosure.operatorComplete, false)
const doneFixture = { ...closureFixture, complete: false, operator_complete: true, totals: { amount: '300.00', paid_amount: '300.00', unpaid_amount: '0.00' } }
assert.equal(helpers.purchaseClosure(doneFixture).operatorComplete, true, 'operator coverage can finish without remote allocation verification')
assert.equal(helpers.purchaseClosure(doneFixture).complete, false)
assert.equal(helpers.purchaseClosure({ ...doneFixture, stages: doneFixture.stages.filter(stage => stage.key !== 'remote_settlement') }).operatorComplete, false, 'an incomplete view must not show a green completed badge based on one flag')
assert.equal(blockedClosure.stages[4].status, 'partial')
assert.equal(blockedClosure.totals.unpaid_amount, '200.00')
const deniedClosure = helpers.purchaseClosure({ ...closureFixture, stages: closureFixture.stages.map(stage => ({ ...stage, status: stage.key === 'local_payable' ? 'permission_denied' : stage.status })) })
assert.equal(deniedClosure.complete, false)
assert.equal(deniedClosure.totals.amount, null)
assert.equal(deniedClosure.stages[3].amount, null)
assert.equal(helpers.purchaseClosureStatus('permission_denied'), '无查看权限')
assert.equal(helpers.purchaseClosure({ ...closureFixture, available: false }).complete, false)
assert.equal(helpers.purchaseClosure({ ...closureFixture, stages: closureFixture.stages.slice(0, 5) }).complete, false)
assert.match(helpers.purchaseNextStep({ status: 'confirming' }, { editable: false, canVerify: true, complete: true, enabled: true }).title, /查到原单号/)

const componentPath = path.join(plugin, 'components/PurchaseReview.vue')
const mainPath = path.join(plugin, 'pages/workbench/index.vue')
let componentScript
const styleSources = []
for (const file of [componentPath, mainPath]) {
    const content = fs.readFileSync(file, 'utf8')
    const parsed = sfc.parse(content, { filename: file })
    assert.equal(parsed.errors.length, 0)
    const script = sfc.compileScript(parsed.descriptor, { id: 'boyuan-purchase-test' })
    const template = sfc.compileTemplate({ source: parsed.descriptor.template.content, filename: file, id: 'boyuan-purchase-test', compilerOptions: { bindingMetadata: script.bindings } })
    assert.equal(template.errors.length, 0)
    styleSources.push({ filename: file, source: parsed.descriptor.styles[0].content })
    if (file === componentPath) componentScript = script.content
}
const main = fs.readFileSync(mainPath, 'utf8')
assert.doesNotMatch(main, /getLogs|detail\.logs|section === 'logs'/)
assert.match(main, /task\.request\?\.schema === 'boyuan\.purchase\.v154'/)
assert.match(main, /purchaseRouteId\(options\?\.purchase_id\)/)
assert.match(main, /options\?\.taskid/)
assert.match(main, /purchase_enabled: false/)
const extensionService = fs.readFileSync(path.join(root, 'niucloud/addon/hsx_erp/app/service/admin/ErpPurchaseExtensionService.php'), 'utf8')
assert.match(extensionService, /event\('HsxErpPurchaseActions'/)
assert.doesNotMatch(extensionService, /hsx_boyuan/)
const mobileEntry = fs.readFileSync(path.join(root, 'site-uniapp/src/addon/hsx_erp/pages/purchase/detail.vue'), 'utf8')
assert.match(mobileEntry, /action\.mobile_path\}\?purchase_id=\$\{Number\(order\.value\?\.id \|\| purchaseOrderId\.value\)\}/)
assert.doesNotMatch(mobileEntry, /hsx_boyuan/)

const routes = [], calls = [], events = [], modalCalls = [], timerCallbacks = []
let holdModal = false
let current = { task: { id: 7, revision: 3, mode: 'test', connection_key: 'connection-A', status: 'awaiting_confirmation', side_effect: 'none', business_no: 'CG-7', external_no: '' }, source, selection: completeSelection, editable: true, can_submit: true, can_verify: false, can_remember: true, issues: [], labels: { company: { id: 'company-1', name: '测试公司' }, supplier: { id: 'supplier-1', name: '测试供应商' }, buyer: { id: 'buyer-1', name: '测试业务员' }, lines: source.lines.map(line => ({ source_line_id: line.source_line_id, product: { name: '已选商品' }, warehouse: { name: '同一仓库' } })) } }
const clone = value => JSON.parse(JSON.stringify(value))
const api = {
    getPurchase: async id => { calls.push(['view', id]); return { data: clone(current) } },
    getReferences: async data => { calls.push(['references', clone(data)]); return { data: { code: 'OK', data: { items: data.cursor === '1' ? [{ id: 'supplier-2', name: '启用供应商', code: 'S2', enabled: true, extra: { is_supplier: true } }, { id: 'disabled', name: '停用', enabled: false }, { id: 'stale', name: '过期', enabled: true, stale: true }, { id: 'customer', name: '仅客户', enabled: true, extra: { is_cust: true } }] : [{ id: 'supplier-3', name: '下一页供应商', code: 'S3', enabled: true, extra: { is_supplier: true } }], next_cursor: data.cursor === '1' ? '2' : '' } } } },
    savePurchaseDraft: async (id, data) => { calls.push(['draft', id, clone(data)]); current = { ...current, selection: clone(data.selection), task: { ...current.task, revision: current.task.revision + 1 } }; return { data: clone(current) } },
    submitPurchase: async (id, data) => { calls.push(['submit', id, clone(data)]); current = { ...current, task: { ...current.task, revision: current.task.revision + 1, status: 'pending' }, editable: false, can_submit: false }; return { data: clone(current) } },
    verifyPurchase: async (id, data) => { calls.push(['verify', id, clone(data)]); return { data: clone(current) } },
    checkPurchaseInventory: async (id, data) => { calls.push(['inventory', id, clone(data)]); current = { ...current, inventory: { task_id: 71, status: 'pending', query_date: null, checked_at: null, total_count: null, in_stock_count: null, reason: '只读核对已排队' } }; return { data: clone(current) } }
}
const componentModule = { exports: {} }
const lifecycle = { onShow: () => {}, onHide: () => {} }
const fakeVue = { ...vue, onUnmounted: () => {} }
vm.runInNewContext(transpile(componentScript), { exports: componentModule.exports, module: componentModule, require: name => name === 'vue' ? fakeVue : name === '@dcloudio/uni-app' ? lifecycle : name === '../api/index' ? api : name === '../utils/purchase-form' ? helpers : name.endsWith('.vue') ? {} : (() => { throw new Error(name) })(), setInterval: fn => { timerCallbacks.push(fn); return timerCallbacks.length }, clearInterval: () => {}, uni: { navigateTo: opts => { routes.push(opts.url); opts.complete() }, showToast: () => {}, showModal: data => { modalCalls.push(data); if (!holdModal) data.success({ confirm: true }) } }, console })
const props = vue.reactive({ taskId: 7, config: { mode: 'test', enabled: true, paused: false, purchase_enabled: true, profiles: { test: { connection_key: 'connection-A', probe_at: 1 } } } })
const ui = componentModule.exports.default.setup(props, { expose: () => {}, emit: event => events.push(event) })
const flush = async () => { await Promise.resolve(); await Promise.resolve(); await vue.nextTick() }

async function run() {
    for (const style of styleSources) {
        const result = await sfc.compileStyleAsync({ ...style, id: 'data-v-boyuan-ux', scoped: true, preprocessLang: 'scss' })
        assert.equal(result.errors.length, 0)
    }
    assert.match(styleSources.find(style => style.filename === componentPath).source, /prefers-color-scheme: dark/)
    assert.match(styleSources.find(style => style.filename === componentPath).source, /env\(safe-area-inset-bottom\)/)
    await flush()
    assert.equal(ui.editable.value, true)
    assert.equal(ui.remember.value, false)
    assert.equal(ui.canSubmit.value, true)
    assert.equal(ui.labelFor('company_id'), '测试公司')
    assert.equal(ui.visibleTopFields.value.length,0); assert.equal(ui.visibleSourceLines.value.length,0)
    ui.matchedExpanded.value=true; assert.equal(ui.visibleTopFields.value.length,4); ui.matchedExpanded.value=false
    assert.equal(ui.topFields.find(field => field.key === 'creator_id').resource, 'staff')
    assert.equal(ui.topFields.find(field => field.key === 'creator_id').role, undefined, 'creator must not use buyer-only staff filtering')
    ui.selection.value.creator_id = ''
    assert.equal(ui.canSubmit.value, false, 'creator must be selected before submission')
    ui.selection.value.creator_id = 'creator-1'
    ui.openLookup(ui.topFields[1]); await flush()
    assert.equal(ui.choices.value.length, 1)
    assert.equal(ui.choiceCursor.value, '2')
    assert.deepEqual(calls.find(call => call[0] === 'references')[1], { resource: 'parties', role: 'supplier', keyword: '', cursor: '1', limit: 50, enabled_only: true, expected_connection_key: 'connection-A' })
    await ui.loadChoices(true)
    assert.equal(ui.choices.value.length, 2)
    assert.equal(ui.choiceCursor.value, '')
    ui.selectChoice(ui.choices.value[1])
    assert.equal(ui.selection.value.supplier_id, 'supplier-3')
    assert.equal(ui.labelFor('supplier_id'), '下一页供应商')
    await ui.saveDraft()
    const draft = calls.find(call => call[0] === 'draft')[2]
    assert.equal(draft.remember, false)
    assert.equal(draft.revision, 3)
    assert.equal(draft.mode, 'test')
    assert.equal(draft.connection_key, 'connection-A')
    assert.deepEqual(Object.keys(draft.selection.lines[0]).sort(), ['product_id', 'source_line_id', 'warehouse_id'])
    assert.equal(calls.filter(call => call[0] === 'submit').length, 0, 'saving must never submit')
    assert.equal(ui.closureExpanded.value, false)
    assert.equal(ui.detailsExpanded.value, false)
    holdModal = true
    ui.confirmSubmit(); ui.confirmSubmit(); await flush()
    assert.equal(modalCalls.length, 1, 'confirmation is part of the single-submit lock')
    assert.equal(ui.interactionLocked.value, true)
    const draftCalls = calls.filter(call => call[0] === 'draft').length
    await ui.saveDraft()
    assert.equal(calls.filter(call => call[0] === 'draft').length, draftCalls)
    await modalCalls[0].success({ confirm: false }); await flush()
    assert.equal(ui.interactionLocked.value, false)
    assert.equal(calls.filter(call => call[0] === 'submit').length, 0, 'canceling confirmation must preserve draft without sending')
    holdModal = false
    ui.confirmSubmit(); await flush()
    assert.equal(modalCalls.length, 2)
    assert.equal(calls.filter(call => call[0] === 'submit').length, 1)
    assert.equal(ui.editable.value, false)
    assert.equal(ui.labelFor('creator_id'), '档案名称暂不可用', 'readonly fallback cannot suggest an unavailable click action')
    assert.equal(ui.canSubmit.value, false)
    ui.confirmSubmit(); await flush()
    assert.equal(calls.filter(call => call[0] === 'submit').length, 1, 'frozen task must never resubmit')
    current = { ...current, can_verify: true, task: { ...current.task, status: 'created_review', external_no: 'BY-CREATED' }, result: { created: true } }
    timerCallbacks[0](); await flush()
    assert.equal(ui.canVerify.value, true, 'created does not mean the purchase is verified')
    assert.match(ui.nextStep.value.title, /核验.*原单/)
    current = { ...current, task: { ...current.task, status: 'succeeded' }, result: { verified: true, created: true } }
    timerCallbacks[0](); await flush()
    assert.match(ui.stateMessage.value, /入库串号明细已核对/)
    assert.equal(ui.canVerify.value, false)
    assert.equal(ui.canStartInventory.value, false, 'old responses without explicit capability do not expose an inventory action')
    current = { ...current, can_check_inventory: true, inventory: { task_id: null, status: 'not_checked', query_date: null, checked_at: null, total_count: null, in_stock_count: null, reason: '' } }
    await ui.reload()
    assert.equal(ui.canStartInventory.value, true)
    assert.notEqual(ui.nextStep.value.action, 'payable', 'a purchase receipt alone is not a payable')
    assert.equal(ui.inventoryExpanded.value, false, 'current stock is optional unless opened via an inventory task')
    assert.doesNotMatch(ui.nextStep.value.title, /核对本批次当前在库/)
    const unpaidClosure = { ...closureFixture, complete: false, operator_complete: false,
        totals: { amount: '300.00', paid_amount: '0.00', unpaid_amount: '300.00' },
        stages: closureFixture.stages.map(s => ({ ...s, status: ['local_payment', 'remote_settlement'].includes(s.key) ? 'pending' : 'verified' })) }
    current.closure = unpaidClosure
    await ui.reload()
    assert.match(ui.nextStep.value.title, /财务/)
    assert.equal(ui.canViewPayable.value, true)
    ui.openPayable()
    assert.deepEqual(routes, ['/addon/hsx_erp/pages/payable/list?status=&source_no=CG-X%20%2F%207'])
    current.closure = { ...unpaidClosure, stages: unpaidClosure.stages.map(s => ({...s, status: s.key === 'local_payable' ? 'permission_denied' : s.status})) }
    await ui.reload(); ui.openPayable()
    assert.equal(routes.length, 1, 'no route or amount without ERP finance read permission')
    assert.equal(ui.canViewPayable.value, false)
    assert.equal(ui.closure.value.totals.amount, null)
    current.closure = doneFixture
    current.payment = { status: 'attention', task_id: 58, operator_complete: false }
    await ui.reload()
    assert.equal(ui.nextStep.value.action, 'payment')
    assert.equal(ui.isFinished.value, false, 'a pending investigation must not show Done even with a stale completed batch total')
    current.payment = undefined
    current.closure = undefined
    await ui.reload()
    const revisionBeforeInventory = current.task.revision
    await Promise.all([ui.checkInventory(), ui.checkInventory()])
    assert.equal(calls.filter(call => call[0] === 'inventory').length, 1, 'rapid repeated clicks queue only one read-only task')
    const inventoryInput = calls.find(call => call[0] === 'inventory')[2]
    assert.deepEqual(Object.keys(inventoryInput).sort(), ['connection_key', 'mode', 'revision'])
    assert.equal(current.task.revision, revisionBeforeInventory)
    assert.equal(ui.inventoryLabel.value, '已排队')
    assert.equal(ui.canStartInventory.value, false)
    await ui.checkInventory(); await ui.reload()
    assert.equal(calls.filter(call => call[0] === 'inventory').length, 1, 'refresh never queues another inventory task')
    current.inventory = { ...current.inventory, status: 'processing' }
    timerCallbacks[0](); await flush()
    assert.equal(ui.inventoryLabel.value, '核对中')
    current.inventory = { ...current.inventory, status: 'succeeded', query_date: '2026-11-18', checked_at: 1794960000, total_count: 2, in_stock_count: 2, reason: '本批次2台均在库' }
    timerCallbacks[0](); await flush()
    assert.equal(ui.inventoryLabel.value, '本批次当前在库已核对')
    assert.equal(ui.canStartInventory.value, false)
    assert.match(ui.stateMessage.value, /采购记录与本批次当前在库已核对/)
    assert.equal(ui.closure.value.complete, false, 'inventory success is not finance closure')
    current.inventory = { ...current.inventory, status: 'failed', in_stock_count: 1, reason: '本批次1台未在当前库存结果内' }
    await ui.reload()
    assert.equal(ui.inventoryLabel.value, '当前在库有差异')
    assert.equal(ui.canStartInventory.value, true)
    current.inventory = { ...current.inventory, status: 'stale', total_count: 0, in_stock_count: 0 }
    await ui.reload()
    assert.equal(ui.inventory.value.total_count, null, 'expired result must not look like zero inventory')
    current.can_check_inventory = false
    await ui.reload(); await ui.checkInventory()
    assert.equal(calls.filter(call => call[0] === 'inventory').length, 1, 'capability denial prevents inventory requests')
    current = { ...current, can_verify: true, task: { ...current.task, status: 'confirming', external_no: '' }, result: {} }
    await ui.reload()
    ui.externalNo.value = 'BY-KNOWN-7'
    await timerCallbacks[0](); await flush()
    assert.equal(ui.externalNo.value, 'BY-KNOWN-7', 'polling must preserve typed known order number')
    await ui.reload()
    assert.equal(ui.externalNo.value, 'BY-KNOWN-7', 'manual refresh must also preserve a typed known order number')
    await ui.verify()
    const verify = calls.find(call => call[0] === 'verify')[2]
    assert.equal(verify.external_no, 'BY-KNOWN-7')
    assert.equal(Object.hasOwn(verify, 'selection'), false)
    props.config.profiles.test.connection_key = 'connection-B'
    await flush()
    assert.equal(ui.connectionMatches.value, false)
    const previousVerify = calls.filter(call => call[0] === 'verify').length
    await ui.verify()
    assert.equal(calls.filter(call => call[0] === 'verify').length, previousVerify, 'changed connection must not verify the old task')
    props.config.profiles.test.connection_key = 'connection-A'
    ui.applyView({ ...clone(current), editable: true, can_verify: true, can_check_inventory: true, can_operate: false, can_remember: false, task: { ...clone(current.task), status: 'succeeded' } })
    const writeCount = calls.filter(call => ['draft', 'submit', 'verify', 'inventory'].includes(call[0])).length
    assert.equal(ui.editable.value, false)
    assert.equal(ui.canSubmit.value, false)
    assert.equal(ui.canVerify.value, false)
    assert.equal(ui.canStartInventory.value, false)
    await ui.saveDraft(); await ui.submit(); await ui.verify(); await ui.checkInventory()
    assert.equal(calls.filter(call => ['draft', 'submit', 'verify', 'inventory'].includes(call[0])).length, writeCount, 'read-only account cannot invoke restricted actions')
    ui.closureExpanded.value = true; ui.detailsExpanded.value = true
    props.focusInventory = true; props.taskId = 8; await flush()
    assert.equal(ui.inventoryExpanded.value, true, 'inventory notification opens the current stock details')
    assert.equal(ui.closureExpanded.value || ui.detailsExpanded.value, false, 'technical details do not leak into another task')
    current = { ...current, can_operate: true, editable: true, can_submit: true, can_verify: false, task: { ...current.task, id: 8, status: 'awaiting_confirmation', external_no: '' } }
    const pricing = { required: true, status: 'known', name: '实际定价人', local_ref: 'staff:19', mapped_id: '' }
    const form = { ...clone(current), selection: clone(completeSelection), labels: { ...clone(current.labels), buyer: { id: 'buyer-1', name: '博远定价员' } }, pricing_buyer: pricing }
    for (const status of ['missing', 'mixed', 'conflict']) {
        ui.applyView({ ...form, pricing_buyer: { ...pricing, status } })
        assert.equal(ui.canSubmit.value, false)
        ui.openLookup(ui.topFields[3]); assert.equal(ui.lookup.value, null)
    }
    ui.applyView({ ...form, pricing_buyer: { ...pricing, mapped_id: 'buyer-1' } })
    ui.clearBuyer(); ui.openLookup(ui.topFields[3])
    assert.equal(ui.selection.value.buyer_id, 'buyer-1'); assert.equal(ui.lookup.value, null)
    ui.applyView({ ...form, editable: false, pricing_buyer: { ...pricing, receipt_status: 'not_supplied' } })
    assert.equal(ui.buyerReceiptLabel.value, '原单未传采购业务员')
    ui.applyView({ ...form, editable: false, pricing_buyer: { ...pricing, receipt_status: 'matched', returned_name: '回执中的定价员' } })
    assert.equal(ui.buyerReceiptLabel.value, '回执中的定价员')
    ui.applyView({ ...form, editable: false, pricing_buyer: { ...pricing, receipt_status: 'matched', receipt_source: 'purchase_details', returned_name: '核单定价员' } })
    assert.equal(ui.buyerReceiptLabel.value, '核单定价员（已核对博远采购明细）')
    ui.applyView(form); holdModal = true
    const writesBeforePricing = calls.filter(c => c[0] === 'submit').length
    await ui.submit(); ui.confirmSubmit()
    const pricingModal = modalCalls.at(-1)
    assert.match(pricingModal.content, /实际定价人.*博远定价员/)
    await pricingModal.success({ confirm: false }); assert.equal(calls.filter(c => c[0] === 'submit').length, writesBeforePricing)
    const savedOriginal = clone(current)
    ui.confirmSubmit(); await modalCalls.at(-1).success({ confirm: true }); await flush(); holdModal = false
    assert.equal(calls.filter(c => c[0] === 'submit').length, writesBeforePricing + 1)
    assert.equal(calls.filter(c => c[0] === 'submit').at(-1)[2].confirm_pricing_buyer, true)
    current = savedOriginal
    ui.applyView(clone(current))
    ui.selection.value.lines[0].warehouse_id = 'new-warehouse'
    api.submitPurchase = async () => { throw { code: 0, msg: '[WAREHOUSE_COMPANY_MISMATCH] 仓库与公司不符，请重新选择' } }
    await ui.submit()
    assert.equal(ui.awaitingReceipt.value, false)
    assert.equal(ui.editable.value, true)
    assert.match(ui.error.value, /未提交.*仓库与公司不符/)
    assert.equal(ui.selection.value.lines[0].warehouse_id, 'new-warehouse', 'editable preflight rejection preserves unsaved archive adjustments')
    let uncertainWrites = 0
    api.submitPurchase = async () => { uncertainWrites++; throw Error('请求超时') }
    await ui.submit()
    assert.equal(ui.awaitingReceipt.value, true)
    await ui.reload(true)
    assert.match(ui.nextStep.value.title, /尚未收到/)
    assert.equal(ui.canSubmit.value, false, 'receiving an old draft response does not unlock resubmission')
    await ui.submit(); assert.equal(uncertainWrites, 1)
    ui.applyView({ ...clone(current), editable: false, task: { ...current.task, status: 'created_review' } })
    assert.equal(ui.awaitingReceipt.value, false, 'only the original task advancing clears the uncertainty latch')
    console.log('PASS: mobile SFC/styles, financial closure boundaries, confirmation lock/cancel, no-send/frozen draft, input preservation, read-only inventory queue/poll/success/difference/stale and permissions')
}
run().catch(error => { console.error(error); process.exitCode = 1 })
