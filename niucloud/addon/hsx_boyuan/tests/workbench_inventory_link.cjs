const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '../../../..')
const app = path.join(root, 'site-uniapp')
const compiler = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
const ts = require(path.join(app, 'node_modules/typescript'))
const vue = require(path.join(app, 'node_modules/vue'))
const transpile = source => ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const helper = { exports: {} }
vm.runInNewContext(transpile(fs.readFileSync(path.join(app, 'src/addon/hsx_boyuan/utils/purchase-form.ts'), 'utf8')), { module: helper, exports: helper.exports })
const filename = path.join(app, 'src/addon/hsx_boyuan/pages/workbench/index.vue')
const parsed = compiler.parse(fs.readFileSync(filename, 'utf8'), { filename })
assert.equal(parsed.errors.length, 0)
const script = compiler.compileScript(parsed.descriptor, { id: 'boyuan-inventory-link' })
const calls = [], toasts = [], lifecycle = {}
const child = { id: 71, parent_purchase_task_id: 7, kind: 'purchase_inventory_verify', status: 'attention', request: { schema: 'boyuan.purchase.inventory.v1', source_task_id: 7 } }
const fixtureTasks = { 71: child, 72: { ...child, id: 72, parent_purchase_task_id: 0 }, 73: { ...child, id: 73, parent_purchase_task_id: 73 }, 7: { id: 7, kind: 'purchase', status: 'succeeded', request: { schema: 'boyuan.purchase.v154' } }, 8: { id: 8, kind: 'purchase', status: 'blocked', request: { operation_id: 'BRIDGE-OLD' } } }
fixtureTasks[74] = { id: 74, kind: 'purchase_payment', parent_purchase_task_id: 7, status: 'attention', request: { schema: 'boyuan.purchase.payment.v1', source_task_id: 7 } }
fixtureTasks[75] = { id: 75, kind: 'purchase_payment', status: 'registered_review', request: { schema: 'boyuan.purchase.payment.v1', source_task_id: 7 } }
const config = { mode: 'test', enabled: true, paused: false, profiles: { test: { protocol: 'openapi_v153', connection_key: 'A' } } }
const api = {
    getConfig: async () => ({ data: config }),
    getTasks: async () => ({ data: { data: [child], total: 1, counts: [{ status: 'attention', total: 1 }, { status: 'registered_review', total: 2 }] } }),
    getTask: async id => { calls.push(['getTask', id]); return { data: fixtureTasks[id] } },
    taskAction: async (id, input) => { calls.push(['taskAction', id, input]); return { data: {} } }
}
const moduleFixture = { exports: {} }
vm.runInNewContext(transpile(script.content), {
    exports: moduleFixture.exports, module: moduleFixture,
    require: name => name === 'vue' ? { ...vue, onUnmounted: () => {} } : name === '@dcloudio/uni-app' ? { onLoad: fn => { lifecycle.onLoad = fn }, onShow: fn => { lifecycle.onShow = fn }, onHide: () => {} } : name === '../../api/index' ? api : name === '../../utils/purchase-form' ? helper.exports : name.endsWith('.vue') ? {} : (() => { throw new Error('Unexpected import ' + name) })(),
    setInterval: () => 1, clearInterval: () => {}, uni: { showToast: data => toasts.push(data.title), showModal: data => data.success({ confirm: true }) }
})
const ui = moduleFixture.exports.default.setup({}, { expose: () => {} })
async function run() {
    assert.equal(ui.kindName('purchase_inventory_verify'), '当前在库核验')
    assert.equal(ui.purchaseStateLabel('attention', 'purchase_inventory_verify'), '核验需处理')
    lifecycle.onLoad({ task_id: '71' })
    await ui.refresh()
    assert.equal(ui.needsAttention('registered_review'), false, 'a matched, previously saved receipt is not an operator task')
    assert.equal(ui.needsAttention('attention'), true, 'an uncertain outcome still needs investigation')
    assert.equal(ui.attentionCount.value, 1, 'registered receipts do not inflate the attention count')
    assert.equal(ui.completedCount.value, 2, 'both previously registered receipts are counted as done')
    assert.equal(ui.purchaseTaskId.value, 7, 'notification task_id=child must open its parent purchase review')
    assert.equal(ui.focusInventory.value, true, 'entering via the inventory notification expands inventory')
    assert.equal(ui.detail.value, null, 'inventory child must never render the generic bridge-action detail')
    assert.deepEqual(Array.from(ui.purchaseTaskIds.value), [7])
    ui.purchaseTaskId.value = 0
    await ui.openTask(71)
    assert.equal(ui.purchaseTaskId.value, 7, 'list click uses the same safe parent resolution')
    assert.equal(ui.taskSelectable({ ...child, batch_action: 'retry', mode: 'test', connection_key: 'A' }), false)
    ui.detail.value = child
    await ui.act('retry')
    assert.equal(calls.filter(call => call[0] === 'taskAction').length, 0, 'even stale child detail cannot invoke a legacy bridge action')
    assert.equal(ui.purchaseTaskId.value, 7)
    assert.equal(ui.kindName('purchase_payment'), '付款记录同步')
    assert.equal(ui.purchaseStateLabel('awaiting_confirmation', 'purchase_payment'), '待同步')
    assert.match(ui.purchaseStateLabel('registered_review', 'purchase_payment'), /已登记|已完成|已办/)
    for (const id of [74, 75]) { await ui.openTask(id); assert.equal(ui.purchaseTaskId.value, 7); assert.equal(ui.originalPaymentTaskId.value, id, 'old payment notification opens its own receipt even after a later settlement'); assert.equal(ui.detail.value, null); assert.equal(ui.focusInventory.value, false) }
    for (const id of [72, 73]) {
        await ui.openTask(id)
        assert.equal(ui.detail.value, null)
        assert.equal(ui.purchaseTaskId.value, 0)
    }
    assert.equal(toasts.filter(message => message.includes('父采购关联')).length, 2)
    await ui.openTask(7)
    assert.equal(ui.purchaseTaskId.value, 7, 'direct purchase tasks still work')
    await ui.openTask(8)
    assert.equal(ui.detail.value.id, 8, 'unrelated legacy bridge detail is preserved')
    console.log('PASS: inventory child notification deep link/list click resolve the parent, business labels, no child bridge actions/batch, malformed associations blocked and legacy purchase detail preserved')
}
run().catch(error => { console.error(error); process.exitCode = 1 })
