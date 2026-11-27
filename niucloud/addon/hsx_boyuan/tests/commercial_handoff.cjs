// Pure read-model regression: no DB, network, real account or payment.
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..')
const ts = require(path.join(root, 'admin/node_modules/typescript'))
const sources = ['niucloud/addon/hsx_boyuan/admin', 'niucloud/addon/hsx_boyuan/site-uniapp', 'admin/src/addon/hsx_boyuan', 'site-uniapp/src/addon/hsx_boyuan'].map(p => fs.readFileSync(path.join(root, p, 'utils/purchase-form.ts'), 'utf8'))
for (const s of sources) assert.equal(s, sources[0], 'all installed and development frontends must agree')
const lib = {}
vm.runInNewContext(ts.transpileModule(sources[0], { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2019 } }).outputText, { exports: lib })
const { purchaseClosure, purchaseNextStep } = lib
const parent = { status:'succeeded', external_no:'PUR-TEST' }
function view(paid='0.00', unpaid='100.00', overrides={}) {
    const keys = ['local_inbound','remote_purchase','remote_stock','local_payable','local_payment','remote_settlement']
    const { stage={}, complete=false, available=true, amount='100.00' } = overrides
    return purchaseClosure({ schema:'boyuan.purchase.closure.v1', available, operator_complete:complete,
        totals:{ amount, paid_amount:paid, unpaid_amount:unpaid },
        stages:keys.map(key => ({ key, status: stage[key] || (key==='remote_stock'?'unverified':key==='remote_settlement'?'blocked':key==='local_payment' && unpaid!=='0.00'?'pending':'verified') })) })
}
const next = (closure, payment={}, extra={}) => purchaseNextStep(parent, { enabled:true, canOperate:true, canCheckInventory:true, closure, payment,...extra })
const original = (state, extra={}) => ({task_id:33, status:state, ...extra})
const owe = view(), paid = view('100.00','0.00'), done = view('100.00','0.00',{complete:true,stage:{remote_settlement:'verified'}})
const submitted = original('registered_review',{operator_complete:true})
assert.equal(next(owe).action,'payable')
assert.match(next(owe).detail,/100.00 元/)
for (const status of ['not_checked','processing','succeeded','failed','stale']) assert.equal(next(owe,{}, {inventory:{status}}).action,'payable','stock query does not steal the finance handoff')
assert.equal(next(paid,{can_capture:true}).action,'payment')
assert.match(next(view('30.00','70.00'),{can_capture:true}).detail,/30.00 元。剩余应付 70.00 元/)
assert.equal(next(view('30.00','70.00'),submitted).action,'payable','registration of first payment does not complete a partially paid batch')
assert.equal(next(paid, original('awaiting_confirmation')).action,'payment')
assert.equal(next(done,submitted).action,'receipt')
for (const state of ['pending','processing']) {
    assert.equal(next(paid,original(state)).tone,'progress'); assert.match(next(paid,original(state)).detail,/无需再次提交/)
}
for (const record of [original('attention'),original('registered_review',{operator_complete:false})]) {
    const r=next(done,record); assert.equal(r.tone,'warning'); assert.match(r.detail,/不要重新同步/)
}
for(const hidden of [next(view('77.77','22.23',{stage:{local_payable:'permission_denied'}})),next(paid,{status:'permission_denied'})]) {
    assert.equal(hidden.action,undefined); assert.doesNotMatch(hidden.detail,/\d|元/)
}
for(const r of [next(purchaseClosure(null)),next(view(null,null)),next(view('100.00','0.00',{available:false})),next(owe,{},{closure:undefined,operatorComplete:true})]){
    assert.equal(r.action,undefined); assert.doesNotMatch(r.title+r.detail,/已同步|无需付款|0\.00 元/)
}
assert.match(next(view('0.00','0.00',{amount:'0.00'})).detail,/无需付款/)
assert.doesNotMatch(next(view('0.00','0.00',{amount:'0.00',stage:{local_payable:'pending'}})).detail,/已付清|无需付款/)
assert.equal(next(paid,submitted).tone,'neutral','a single receipt does not close the batch')
console.log('PASS: commercial purchase handoff across PC/mobile, balances, access, partials, receipts and uncertain results')
