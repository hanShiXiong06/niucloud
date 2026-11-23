const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '../../../..')
const app = path.join(root, 'site-uniapp')
const vue = require(path.join(app, 'node_modules/vue'))
const sfc = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
const ts = require(path.join(app, 'node_modules/typescript'))
const transpile = value => ts.transpileModule(value, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const clone = value => JSON.parse(JSON.stringify(value))
const moduleFor = (file, imports, extras = {}) => { const target = { exports: {} }; vm.runInNewContext(transpile(fs.readFileSync(file, 'utf8')), { module: target, exports: target.exports, require: name => imports[name] || (() => { throw new Error(name) })(), ...extras }); return target.exports }
const helper = moduleFor(path.join(app, 'src/addon/hsx_boyuan/utils/purchase-form.ts'), {})
const timers = []
const payment = moduleFor(path.join(app, 'src/addon/hsx_boyuan/utils/purchase-payment.ts'), { vue: { ...vue, onUnmounted: () => {} }, './purchase-form': helper }, { setInterval: callback => { timers.push(callback); return timers.length }, clearInterval: () => {} })
const pcPayment = moduleFor(path.join(root,'admin/src/addon/hsx_boyuan/utils/purchase-payment.ts'), {vue:{...vue,onUnmounted:()=>{}},'./purchase-form':helper},{setInterval:()=>1,clearInterval:()=>{}})
const baseConfig = { revision: 2, mode: 'test', enabled: true, purchase_enabled: true, paused: false, uat_payment_enabled: true, profiles: { test: { protocol: 'openapi_v153', endpoint: 'https://open-api-uat.139erp.com/api', gateway_path: '/gateway.do', connection_key: 'FIXTURE-UAT', account_id: 'FIXTURE-ACCOUNT', probe_at: 1800000000, contract_confirmed: true } } }
const parent = { task: { id: 7, revision: 8, mode: 'test', connection_key: 'FIXTURE-UAT', status: 'succeeded' }, can_operate: true, payment: { uat_only: true, enabled: true, can_capture: true, task_id: null, ap_evidence: { verified: false, status: 'rule_confirmed', formation_expected: true, balance_verified: false, reason: '规则成立，但余额/核销未核验' } } }
const baseView = { task: { id: 70, revision: 1, mode: 'test', connection_key: 'FIXTURE-UAT', kind: 'purchase_payment', parent_purchase_task_id: 7, status: 'awaiting_confirmation', external_no: '' }, parent_purchase_task_id: 7, source: { source_task_id: 7, source_document_no: 'FICTIONAL-CG', external_purchase_no: 'FICTIONAL-BY-CG', settlement_id: 80, settlement_no: 'FICTIONAL-FK-80', confirmed_at: 1800000000, amount: '100.00', currency: 'CNY', company_id: 'C1', company_code: '99000000001', supplier_id: 'S1', scope: 'single_purchase_batch' }, labels: { company: { id: 'C1', name: '虚构公司一' }, supplier: { id: 'S1', name: '虚构供应商一' }, creator: null, account: null }, selection: { creator_id: '', account_id: '' }, editable: true, can_submit: false, can_submit_experiment: false, can_verify: false, ap_evidence: parent.payment.ap_evidence, issues: ['实单应付及核销未核验'], result: { payment_registered: false, allocation_applied_verified: false, bank_transfer_verified: false } }
const candidates = [{ settlement_id: 80, settlement_no: 'FICTIONAL-FK-80', confirmed_at: 1800000000, amount: '100.00', eligible: true }, { settlement_id: 81, settlement_no: 'FICTIONAL-FK-81', confirmed_at: 1800000000, amount: null, eligible: false, reason: '归属不符' }]
const accounts = [{ id: 'A1', name: '虚构同公司账户', enabled: true, stale: false, extra: { gsdm: '99000000001' } }, { id: 'A2', name: '其他公司账户', enabled: true, extra: { gsdm: '99000000002' } }, { id: 'A3', name: '未知归属账户', enabled: true, extra: {} }, { id: 'A4', name: '双归属矛盾', enabled: true, extra: { company_id: 'C1', gsdm: '99000000002' } }]
function fixture(client=payment) {
    const props = vue.reactive({ config: clone(baseConfig), purchase: clone(parent) }), calls = [], notices = []
    let current = clone(baseView), throwSubmit = false
    const api = {
        getPurchasePaymentCandidates: async (id, input) => { calls.push(['candidates', id, clone(input)]); return { data: { items: input.page === 1 ? candidates : [candidates[0], { ...candidates[0], settlement_id: 82 }], total: 3, has_more: input.page === 1, next_page: input.page === 1 ? 2 : null } } },
        capturePurchasePayment: async (id, input) => { calls.push(['capture', id, clone(input)]); return { data: clone(current) } },
        getPaymentTask: async id => { calls.push(['get', id]); return { data: clone(current) } },
        getReferences: async input => { calls.push(['references', clone(input)]); return { data: { code: 'OK', data: { items: input.resource === 'capital_accounts' ? accounts : [{ id: 'U1', name: '虚构制单人', enabled: true, extra: {} }], next_cursor: '' } } } },
        savePaymentDraft: async (id, input) => { calls.push(['draft', id, clone(input)]); current = { ...current, selection: clone(input.selection), labels: { ...current.labels, account: { id: 'A1', name: '虚构同公司账户' }, creator: { id: 'U1', name: '虚构制单人' } }, can_submit_experiment: true, task: { ...current.task, revision: current.task.revision + 1 } }; return { data: clone(current) } },
        submitPaymentTask: async (id, input) => { calls.push(['submit', id, clone(input)]); if (throwSubmit) throw new Error('fake response lost'); current = { ...current, editable: false, can_submit_experiment: false, task: { ...current.task, status: 'pending', revision: current.task.revision + 1 } }; return { data: clone(current) } }
    }
    const ui = client.usePurchasePayment(props, api, value => notices.push(value), () => {})
    return { props, calls, notices, ui, api, get current() { return current }, set current(value) { current = value }, failSubmit: () => { throwSubmit = true } }
}
const flush = async () => { for (let index = 0; index < 12; index++) await Promise.resolve(); await vue.nextTick() }
// Render both shipping templates. Only UI library components are shallow; visibility, labels,
// status and events come from the actual templates and composable. No network or writes.
async function paymentDialogHtml(parsed, file, ui, props) {
    const compiled = sfc.compileTemplate({ source: parsed.descriptor.template.content, filename: file, id: 'commercial-pay', compilerOptions: { isCustomElement: tag => ['view','text','scroll-view','u-loading-icon'].includes(tag) } })
    assert.deepEqual(compiled.errors, [])
    const mod = { exports: {} }
    vm.runInNewContext(transpile(compiled.code), { exports: mod.exports, module: mod, require: () => vue })
    const project = vue.createSSRApp({ render: mod.exports.render, setup: () => ({ p: vue.reactive(ui), showEntry: false, canEnable: false, config: props.config, purchase: props.purchase, enableVisible: false, enableBusy: false, enableAccount: '', enableAck: false, enableError: '', PAYMENT_EXPERIMENT_WARNING: payment.PAYMENT_EXPERIMENT_WARNING, paymentApLabel: payment.paymentApLabel, purchaseMoney: helper.purchaseMoney, purchaseCheckedAt: helper.purchaseCheckedAt, close: () => {}, submit: () => {}, enableUat: () => {} }) })
    for (const tag of ['el-dialog','u-popup','el-button','u-button','el-alert','el-table','el-table-column','el-checkbox','u-checkbox','el-input','u-search']) {
        project.component(tag, { name: 'TestControl', inheritAttrs: false, setup: (_, { attrs, slots }) => () => {
            if ((tag === 'el-dialog' && !(attrs.modelValue || attrs['model-value'])) || (tag === 'u-popup' && !attrs.show) || tag === 'el-table') return null
            if (tag === 'el-dialog' || tag === 'u-popup') return vue.h('article', [slots.default?.(), slots.footer?.()])
            return vue.h('span', [attrs.title, attrs.label, slots.default?.()])
        } })
    }
    project.directive('loading', { getSSRProps: () => ({}) })
    return require(path.join(app, 'node_modules/@vue/server-renderer')).renderToString(project)
}
async function run() {
    // A click on a specific ERP payable must never pick the latest sibling voucher.
    const exact = fixture(pcPayment)
    exact.props.target = { settlementId: 80, taskId: 0 }
    exact.props.purchase.payment.task_id = 999
    await exact.ui.open()
    assert.deepEqual(exact.calls.map(c => c[0]), ['candidates'])
    assert.equal(exact.calls[0][2].settlement_id, 80)
    assert.deepEqual(Array.from(exact.ui.candidates.value, r=>r.settlement_id), [80])
    assert.equal(exact.ui.exactCandidate.value.settlement_no, 'FICTIONAL-FK-80', 'confirmed source appears directly instead of asking clerk to choose it again')
    const exactReceipt = fixture(pcPayment)
    exactReceipt.props.target = {settlementId:80, taskId:0}; exactReceipt.props.readOnly = true; exactReceipt.props.purchase.payment.can_capture = false
    exactReceipt.api.getPurchasePaymentCandidates=async(id,input)=>{exactReceipt.calls.push(['candidates',id,clone(input)]);return {data:{items:[{...candidates[0],task_id:70,eligible:false}],total:1}}}
    exactReceipt.current={...clone(baseView),task:{...baseView.task,status:'registered_review',external_no:'FIXTURE-PAY-70'},result:{payment_registered:true}}
    await exactReceipt.ui.open()
    assert.deepEqual(exactReceipt.calls.map(c=>c[0]),['candidates','get'],'open the precise existing receipt read-only, even when syncing is paused')
    assert.equal(exactReceipt.ui.candidateMode.value,false);assert.equal(exactReceipt.ui.registrationDone.value,true);assert.equal(exactReceipt.ui.canSubmit.value,false)
    const noMatch=fixture(pcPayment)
    noMatch.props.target={settlementId:999,taskId:0};await noMatch.ui.open()
    assert.equal(noMatch.ui.exactCandidate.value,null);assert.match(noMatch.ui.error.value,/未找到唯一匹配/);assert.deepEqual(noMatch.calls.map(c=>c[0]),['candidates'])
    await exact.ui.capture({...candidates[0], settlement_id: 999})
    assert.ok(!exact.calls.some(c => c[0] === 'capture'))
    exact.props.target = { settlementId: 999, taskId: baseView.task.id }
    await exact.ui.open(); assert.match(exact.ui.error.value,/不匹配/)
    assert.equal(exact.ui.editable.value,false)
    const readOnly = fixture(pcPayment); readOnly.props.readOnly = true
    readOnly.ui.apply(baseView); assert.equal(readOnly.ui.editable.value,false); assert.equal(readOnly.ui.canCapture.value,false)
    const locked=fixture(pcPayment), frozen={...clone(baseView),can_process_current:true,current_account_code:'FIXTURE-ACCOUNT',editable:false,task:{...baseView.task,status:'pending',attempts:0,side_effect:'none'}}
    locked.ui.apply(frozen)
    assert.equal(locked.ui.canProcessCurrent.value,true)
    locked.api.submitPaymentTask=async (id,input)=>{ locked.calls.push(['process',id,clone(input)]); throw Error('fixture lost response') }
    await Promise.all([locked.ui.processCurrent(),locked.ui.processCurrent()])
    assert.deepEqual(locked.calls,[['process',70,{revision:1,mode:'test',connection_key:'FIXTURE-UAT',intent:'process_current',account_code:'FIXTURE-ACCOUNT',settlement_id:80,purchase_task_id:7}]])
    locked.ui.apply(frozen);await locked.ui.processCurrent();assert.equal(locked.calls.length,1,'unknown outcome cannot cause repeat sends')
    locked.ui.apply({...frozen,task:{...frozen.task,mode:'production',revision:2}});assert.equal(locked.ui.canProcessCurrent.value,false)
    assert.equal(payment.paymentAccountInScope(accounts[0], baseView.source), true)
    for (const row of accounts.slice(1)) assert.equal(payment.paymentAccountInScope(row, baseView.source), false)
    assert.match(payment.paymentApLabel(parent.payment.ap_evidence), /形成规则已确认.*未核验/)
    const f = fixture(), p = f.ui
    await p.open(); assert.equal(p.candidateMode.value, true)
    await p.loadCandidates(true); assert.equal(p.candidates.value.length, 3)
    await p.capture(candidates[1]); assert.equal(f.calls.filter(call => call[0] === 'capture').length, 0)
    await p.capture(candidates[0]); assert.equal(p.editable.value, true, 'real server source_task_id/top parent shape must be recognized')
    assert.deepEqual(Object.keys(f.calls.find(call => call[0] === 'capture')[2]).sort(), ['connection_key', 'mode', 'revision', 'settlement_id'])
    p.openLookup('account_id'); await flush(); assert.equal(p.choices.value.length, 1, 'same-company gsdm only; 31 unrelated accounts cannot become selectable')
    p.choose(accounts[1]); assert.equal(p.selection.value.account_id, '')
    p.choose(accounts[0]); p.openLookup('creator_id'); await flush(); p.choose(p.choices.value[0])
    p.selection.value.amount = '999999.00'
    await p.save(); assert.equal(p.canSubmit.value, false, 'experiment capability cannot silently replace standard submission')
    const draft = f.calls.find(call => call[0] === 'draft')[2]
    assert.deepEqual(Object.keys(draft.selection).sort(), ['account_id', 'creator_id'])
    assert.equal(Object.hasOwn(draft, 'amount'), false)
    let confirms = 0, resolveConfirm
    await p.submit(async () => { confirms++; return true }); assert.equal(confirms, 0)
    p.experimentAck.value = true
    const pending = p.submit(text => { confirms++; assert.match(text, /虚构公司一/); assert.match(text, /虚构供应商一/); assert.match(text, /FICTIONAL-BY-CG/); assert.match(text, /100.00 元/); assert.match(text, /不是银行转账/); assert.match(text, /仅此公司/); return new Promise(resolve => { resolveConfirm = resolve }) })
    await p.submit(async () => true); assert.equal(confirms, 1); assert.equal(p.locked.value, true)
    const drafts = f.calls.filter(call => call[0] === 'draft').length; await p.save(); assert.equal(f.calls.filter(call => call[0] === 'draft').length, drafts)
    resolveConfirm(false); await pending; assert.equal(f.calls.filter(call => call[0] === 'submit').length, 0)
    await p.submit(async () => true)
    const submit = f.calls.find(call => call[0] === 'submit')[2]
    assert.equal(submit.confirm_registration, true); assert.equal(submit.confirm_unverified_ap, true)
    assert.deepEqual(Object.keys(submit).sort(), ['confirm_registration', 'confirm_unverified_ap', 'connection_key', 'mode', 'revision', 'selection'])
    assert.equal(p.editable.value, false); await p.submit(async () => true); assert.equal(f.calls.filter(call => call[0] === 'submit').length, 1)
    f.current = { ...f.current, operator_complete: true, issues: [], task: { ...f.current.task, status: 'registered_review', external_no: 'FICTIONAL-PAY-1' }, result: { payment_registered: true, allocation_applied_verified: false, financial_posting_verified: false, bank_transfer_verified: false } }
    await p.refresh(); assert.match(p.resultMessage.value, /无需.*操作/); assert.doesNotMatch(p.resultMessage.value, /已核销|待核查|需要核对/); assert.equal(p.apEvidence.value.verified, false); assert.equal(Object.hasOwn(p, 'verify'), false)
    assert.equal(p.editable.value, false); assert.equal(p.canSubmit.value, false)
    assert.equal(p.detailsOpen.value, false, 'a new receipt starts compact')
    await p.submit(async () => true); assert.equal(f.calls.filter(call => call[0] === 'submit').length, 1, 'viewing completed receipt never re-registers this settlement')
    assert.doesNotMatch(payment.paymentResultMessage({ ...f.current, operator_complete: false }), /无需.*操作/, 'inconsistent receipt cannot report an operator success')
    assert.doesNotMatch(payment.paymentResultMessage({ ...f.current, task: { ...f.current.task, status: 'attention' } }), /无需.*操作/, 'conflicting receipt must remain a financial exception')
    const unknown = fixture(); unknown.ui.visible.value = true; unknown.ui.apply({ ...clone(baseView), selection: { creator_id: 'U1', account_id: 'A1' }, labels: { ...baseView.labels, account: { id: 'A1', name: '测试账户' }, creator: { id: 'U1', name: '制单人' } }, can_submit_experiment: true }); unknown.ui.experimentAck.value = true; unknown.failSubmit(); await unknown.ui.submit(async () => true)
    assert.equal(unknown.ui.uncertain.value, true); assert.equal(unknown.ui.canSubmit.value, false); assert.equal(unknown.ui.canCapture.value, false)
    await unknown.ui.refresh(); assert.equal(unknown.ui.editable.value, false, 'unknown cannot be re-enabled by stale awaiting-confirmation read')
    await unknown.ui.submit(async () => true); assert.equal(unknown.calls.filter(call => call[0] === 'submit').length, 1)
    const prod = fixture(); prod.props.config.mode = 'production'; prod.props.purchase.task.mode = 'production'; prod.ui.apply({ ...clone(baseView), can_submit: true, can_submit_experiment: true }); prod.ui.experimentAck.value = true
    assert.equal(prod.ui.canCapture.value, false); assert.equal(prod.ui.editable.value, false); assert.equal(prod.ui.canSubmit.value, false)
    await prod.ui.capture(candidates[0]); await prod.ui.save(); await prod.ui.submit(async () => true); assert.equal(prod.calls.length, 0, 'production has no financial writes even with forged capability flags')
    const live=fixture(pcPayment); live.props.config.mode='production'; Object.assign(live.props.purchase,{active_connection:true,write_enabled:true})
    Object.assign(live.props.purchase.task,{mode:'production',connection_key:'FORMAL-KEY'})
    Object.assign(live.props.purchase.payment,{production_context:true,uat_only:false}); live.ui.visible.value=true
    const pv=clone(baseView);Object.assign(pv.task,{mode:'production',connection_key:'FORMAL-KEY'});pv.selection={creator_id:'U1',account_id:'A1'};pv.labels.creator={id:'U1',name:'制单人'};pv.labels.account={id:'A1',name:'原付款账户'};pv.can_submit=true;live.ui.apply(pv)
    assert.equal(live.ui.canCapture.value,true);assert.equal(live.ui.editable.value,true);assert.equal(live.ui.canSubmit.value,true)
    live.ui.apply({...pv,can_submit:false,can_submit_experiment:true});live.ui.experimentAck.value=true
    assert.equal(live.ui.needsExperiment.value,false);assert.equal(live.ui.canSubmit.value,false,'formal task cannot use UAT experimental approval')
    live.props.purchase.write_enabled=false;live.ui.apply(pv);assert.equal(live.ui.canCapture.value,false);assert.equal(live.ui.canSubmit.value,false,'pausing the connection permits viewing only')
    const readonly = fixture(); readonly.props.purchase.can_operate = false; readonly.ui.visible.value = true; readonly.ui.apply({ ...clone(baseView), can_submit: true })
    assert.equal(readonly.ui.canCapture.value, false); assert.equal(readonly.ui.editable.value, false); assert.equal(readonly.ui.canSubmit.value, false)
    const mixed = fixture()
    mixed.props.purchase.payment = { ...mixed.props.purchase.payment, status: 'registered_review', task_id: 70, operator_complete: true, can_capture: true }
    assert.equal(mixed.ui.entryIsNew.value, true, 'a previous registered payment does not hide the next eligible payment in this batch')
    await mixed.ui.open(); assert.equal(mixed.ui.candidateMode.value, true)
    await mixed.ui.capture({ ...candidates[0], task_id: 70 }); assert.equal(mixed.calls.some(c => c[0] === 'capture'), false, 'existing drafts opened for read use their original id')
    mixed.current = clone(f.current); await mixed.ui.open(70); assert.equal(mixed.ui.registrationDone.value, true, 'an explicitly linked receipt never opens a different payment')
    const retry = fixture(); retry.props.purchase.payment.task_id = 70
    const getTask = retry.api.getPaymentTask; retry.api.getPaymentTask = async () => { throw new Error('读取暂时失败') }
    await retry.ui.open(70); assert.equal(retry.ui.editable.value, false); assert.match(retry.ui.error.value, /读取暂时失败/)
    retry.api.getPaymentTask = getTask; await retry.ui.refresh(); assert.equal(retry.ui.editable.value, true, 'initial read failure can be retried without a new draft')
    retry.api.getPaymentTask = async () => { throw new Error('权限或数据可能已变化') }; await retry.ui.refresh(); assert.equal(retry.ui.editable.value, false, 'failed re-read cannot submit previously displayed draft')
    for (const file of [path.join(root, 'admin/src/addon/hsx_boyuan/components/PurchasePaymentReview.vue'), path.join(app, 'src/addon/hsx_boyuan/components/PurchasePaymentReview.vue')]) {
        const source = fs.readFileSync(file, 'utf8'), parsed = sfc.parse(source, { filename: file }); assert.equal(parsed.errors.length, 0)
        const script = sfc.compileScript(parsed.descriptor, { id: 'boyuan-pay-test' }); assert.equal(sfc.compileTemplate({ source: parsed.descriptor.template.content, filename: file, id: 'boyuan-pay-test', compilerOptions: { bindingMetadata: script.bindings } }).errors.length, 0)
        assert.equal((await sfc.compileStyleAsync({ source: parsed.descriptor.styles[0].content, filename: file, id: 'data-v-payment', scoped: true, preprocessLang: 'scss' })).errors.length, 0)
        assert.doesNotMatch(source, /v-model[^\n>]*(amount|money|supplier_id|company_id)/)
        assert.match(source, /博远应付形成/)
        p.visible.value = true; p.detailsOpen.value = false
        const receipt = await paymentDialogHtml(parsed, file, p, f.props)
        for (const label of ['已同步到博远','FICTIONAL-PAY-1','100.00 元','查看明细','完成']) assert.ok(receipt.includes(label), label)
        for (const label of ['博远应付形成：','FICTIONAL-FK-80','同步到博远</span>','选择制单人','确认UAT']) assert.ok(!receipt.includes(label), 'successful receipt should not expose technical controls: ' + label)
        p.detailsOpen.value = true
        const details = await paymentDialogHtml(parsed, file, p, f.props); assert.match(details, /FICTIONAL-FK-80/); assert.match(details, /采购核销由博远处理/)
        const uncertainHtml = await paymentDialogHtml(parsed, file, unknown.ui, unknown.props)
        assert.match(uncertainHtml, /不要重新同步/); assert.doesNotMatch(uncertainHtml, />同步到博远</)
    }
    const pcPath = path.join(root, 'admin/src/addon/hsx_boyuan/components/PurchasePaymentReview.vue'), pcParsed = sfc.parse(fs.readFileSync(pcPath, 'utf8'), { filename: pcPath }), pcScript = sfc.compileScript(pcParsed.descriptor, { id: 'enable-uat-test' })
    const enableModule = { exports: {} }, enableCalls = [], enableEvents = []
    let permissionDenied = true
    const enableConfig = { ...clone(baseConfig), uat_payment_enabled: false }
    const enableProps = vue.reactive({ purchase: { ...clone(parent), payment: { ...clone(parent.payment), enabled: false, can_capture: false } }, config: enableConfig })
    const enableApi = { getConfig: async () => ({ data: clone(enableConfig) }), saveConfig: async input => { enableCalls.push(clone(input)); if (permissionDenied) throw { msg: '没有配置保存权限' }; return { data: { ...clone(enableConfig), uat_payment_enabled: true } } } }
    vm.runInNewContext(transpile(pcScript.content), { exports: enableModule.exports, module: enableModule, require: name => name === 'vue' ? { ...vue, onUnmounted: () => {} } : name === 'element-plus' ? { ElMessage: { info: () => {} }, ElMessageBox: { confirm: async () => true } } : name === '../api/purchase' ? enableApi : name === '../utils/purchase-form' ? helper : name === '../utils/purchase-payment' ? payment : (() => { throw new Error(name) })() })
    const enableUi = enableModule.exports.default.setup(enableProps, { expose: () => {}, emit: (...args) => enableEvents.push(args) })
    assert.equal(enableUi.canEnable.value, true)
    enableUi.enableVisible.value = true; enableUi.enableAccount.value = 'FIXTURE-ACCOUNT'; enableUi.enableAck.value = true
    await enableUi.enableUat()
    assert.match(enableUi.enableError.value, /配置保存权限/)
    assert.equal(enableUi.enableAccount.value, 'FIXTURE-ACCOUNT', 'permission failure preserves typed account confirmation')
    assert.equal(enableUi.enableAck.value, true)
    assert.equal(enableUi.enableVisible.value, true)
    permissionDenied = false; await enableUi.enableUat()
    assert.deepEqual(Object.keys(enableCalls[1]).sort(), ['confirm_account', 'mode', 'revision', 'uat_payment_enabled'], 'PC config enable sends a minimal patch without secrets or global gate mutations')
    assert.equal(enableEvents.find(event => event[0] === 'config-changed')[1].uat_payment_enabled, true)
    assert.equal(enableUi.enableVisible.value, false)
    enableProps.config.mode = 'production'; assert.equal(enableUi.canEnable.value, false)
    const mobileWorkbench = fs.readFileSync(path.join(app, 'src/addon/hsx_boyuan/pages/workbench/index.vue'), 'utf8')
    assert.match(mobileWorkbench, /uat_payment_enabled: false/)
    assert.match(mobileWorkbench, /uat_payment_enabled: draft\.value\.mode === 'test'/)
    console.log('PASS: payment views, confirmed-only vouchers, fixed amounts, scoped formal registration, no UAT override on formal, pause/readback, unknown no-resubmit, receipt distinct from allocation.')
}
run().catch(error => { console.error(error); process.exitCode = 1 })
