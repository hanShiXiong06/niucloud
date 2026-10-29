// 本地回归：真实组件脚本 + 模板事件绑定；不启动业务、不调用接口、不写数据库。
// node niucloud/addon/hsx_recycle/scripts/test_order_device_price_input.cjs
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const { createRequire } = require('node:module')
const root = path.resolve(__dirname, '../../../..')
const local = createRequire(path.join(root, 'uni-app/package.json'))
const compiler = local('@vue/compiler-sfc')
const ts = local('typescript')
const vue = local('vue')
const relative = 'pages/order/components/DeviceListManager.vue'
const filename = path.join(root, 'niucloud/addon/hsx_recycle/uni-app', relative)
const source = fs.readFileSync(filename, 'utf8')
assert.equal(source, fs.readFileSync(path.join(root, 'uni-app/src/addon/hsx_recycle', relative), 'utf8'), '发布源码和运行源码一致')
const { descriptor, errors } = compiler.parse(source, { filename })
assert.deepEqual(errors, [])
const script = compiler.compileScript(descriptor, { id: 'device-price-test' })
const transpiled = ts.transpileModule(script.content, {
    fileName: filename + '.ts', reportDiagnostics: true,
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 }
})
assert.deepEqual(transpiled.diagnostics, [])
assert.deepEqual(compiler.compileTemplate({ source: descriptor.template.content, filename, id: 'device-price-test',
    compilerOptions: { bindingMetadata: script.bindings }
}).errors, [])
for (const style of descriptor.styles) assert.deepEqual(compiler.compileStyle({
    source: style.content, filename, id: 'device-price-test', preprocessLang: style.lang
}).errors, [])
console.log('PASS 两份源码一致，Vue 模板、TypeScript 语法和 SCSS 编译通过')

const ast = local('@vue/compiler-dom').parse(descriptor.template.content)
const elements = []
function walk(node, ancestors = []) {
    if (node.type === 1) elements.push({ node, ancestors })
    for (const child of node.children || []) walk(child, node.type === 1 ? [...ancestors, node] : ancestors)
}
walk(ast)
const directive = (node, name, arg) => node.props.find(prop => prop.type === 7 && prop.name === name && (!arg || prop.arg?.content === arg))
const attribute = (node, name) => node.props.find(prop => prop.type === 6 && prop.name === name)?.value?.content
const input = model => elements.find(({ node }) => node.tag === 'input' && directive(node, 'model')?.exp?.content === model)
const serial = input('newDevice.user_sn')
const price = input('newDevice.initial_price')
assert.ok(serial && price)
assert.equal(directive(serial.node, 'bind', 'confirm-type').exp.content, "enablePricing ? 'next' : 'done'")
const serialConfirm = directive(serial.node, 'on', 'confirm').exp.content
const priceConfirm = directive(price.node, 'on', 'confirm').exp.content
assert.equal(serialConfirm, 'focusEstimatedPrice', '串号确认不能提交设备')
assert.equal(priceConfirm, 'confirmAdd')
assert.equal(directive(price.node, 'bind', 'focus').exp.content, 'priceFocused')
assert.equal(directive(price.node, 'on', 'blur').exp.content, 'priceFocused = false')
for (const node of [...price.ancestors, price.node]) {
    const condition = directive(node, 'if') || directive(node, 'show')
    assert.ok(!condition || condition.exp.content === 'enablePricing', '价格仅受配置开关控制，不能折叠')
    assert.notEqual(node.tag, 'button', '价格标题不是折叠按钮')
}
assert.doesNotMatch(source, /showEstimatedPrice|toggleEstimatedPrice/)
assert.match(source, /请输入报价员预估价格/)
assert.match(source, /quoteUsers.length <= 5/)
assert.match(source, /<picker v-else/)
console.log('PASS 价格常显选填，串号下一项和价格完成的模板绑定正确')

let scanCallback
const toasts = []
const sandbox = {
    exports: {}, setTimeout, clearTimeout,
    uni: {
        showToast: value => toasts.push(value),
        scanCode: options => { scanCallback = options.success },
        createSelectorQuery: () => {
            const query = { in: () => query, select: () => query, boundingClientRect: () => query, exec() {} }
            return query
        }
    },
    require: id => {
        if (id === 'vue') return vue
        if (id.endsWith('.vue')) return { default: {} }
        if (id === '../../../api/order') return {
            getDeviceModelDictChildren() { throw Error('本测试不得请求型号接口') },
            searchDeviceModelDictOptions() { throw Error('本测试不得请求型号接口') }
        }
        throw Error('Unexpected import: ' + id)
    }
}
vm.runInNewContext(transpiled.outputText, sandbox, { filename })
const component = sandbox.exports.default
let state
const originalSetup = component.setup
component.setup = (props, context) => { state = originalSetup(props, context); return state }
component.render = () => null
const renderer = vue.createRenderer({
    createElement: tag => ({ tag, children: [] }), createText: text => ({ text }), createComment: text => ({ text }),
    insert: (node, parent) => { node.parent = parent; parent.children.push(node) },
    remove: node => { if (node.parent) node.parent.children.splice(node.parent.children.indexOf(node), 1) },
    setText: (node, text) => { node.text = text }, setElementText: (node, text) => { node.text = text },
    parentNode: node => node.parent, nextSibling: () => null, patchProp() {}
})
const devices = vue.ref([])
const count = vue.ref(1)
const enablePricing = vue.ref(true)
const quoteUsers = vue.ref([])
const writes = []
const app = renderer.createApp({ setup: () => () => vue.h(component, {
    devices: devices.value, count: count.value,
    enablePricing: enablePricing.value, quoteUsers: quoteUsers.value,
    'onUpdate:devices': value => { writes.push(value); devices.value = value },
    'onUpdate:count': value => { count.value = value }
}) })

async function main() {
    app.mount({ children: [] })
    try {
        state.handleBatchAdd()
        state.newDevice.value.model = '测试手机'
        state.newDevice.value.user_sn = 'ABC123'
        state.handleUserSnInput()
        assert.equal(writes.length, 0, '输入串号不添加')
        await state[serialConfirm]()
        assert.equal(state.showAddDialog.value, true)
        assert.equal(state.priceFocused.value, true, '串号确认进入价格框')
        assert.equal(writes.length, 0, '串号键盘确认不添加')
        state.newDevice.value.initial_price = '4600.50'
        assert.equal(writes.length, 0, '输入价格不自动提交')
        await state[priceConfirm]()
        await vue.nextTick()
        assert.equal(writes.length, 1)
        assert.equal(devices.value[0].initial_price, '4600.50')
        assert.equal(count.value, 1)
        assert.equal(state.showAddDialog.value, false)
        assert.equal(state.priceFocused.value, false)
        await state.confirmAdd()
        assert.equal(writes.length, 1, '关闭后重复确认不再插入')
        console.log('PASS 串号确认只切焦点，价格完成保存金额并关闭，不重复添加')

        state.handleBatchAdd()
        assert.equal(state.newDevice.value.initial_price, '', '再次打开清空上次价格')
        await state.confirmAdd()
        assert.equal(writes.length, 1)
        assert.equal(state.showAddDialog.value, true)
        assert.ok(state.fieldErrors.value.model)
        state.newDevice.value.model = '第二台手机'
        state.newDevice.value.user_sn = '12'
        await state.confirmAdd()
        assert.equal(writes.length, 1)
        assert.ok(state.fieldErrors.value.user_sn)
        state.newDevice.value.user_sn = 'XYZ789'
        await state.confirmAdd()
        await vue.nextTick()
        assert.equal(writes.length, 2)
        assert.equal(devices.value[1].initial_price, '', '预估价格空值原样保留，不强填或转为零')
        assert.equal(count.value, 2)
        console.log('PASS 必填校验保留，价格留空可直接点添加，继续添加不继承上次金额')

        state.handleBatchAdd()
        state.newDevice.value.model = '扫码手机'
        state.scanUserSn()
        scanCallback({ result: '123456789012345' })
        assert.equal(state.newDevice.value.user_sn, '123456789012345')
        assert.equal(state.showAddDialog.value, true)
        assert.equal(writes.length, 2, '扫码回填不自动添加')
        state.closeAddDialog()
        assert.equal(writes.length, 2, '取消不添加')
        state.handleBatchAdd()
        const focusPending = state[serialConfirm]()
        state.closeAddDialog()
        state.handleBatchAdd()
        await focusPending
        assert.equal(state.priceFocused.value, false, '关闭重开后忽略旧弹窗的延迟聚焦')
        assert.equal(writes.length, 2)
        console.log('PASS 扫码不自动添加、取消不保存、关闭重开不串焦点')

        state.closeAddDialog()
        quoteUsers.value = [{ uid: 11, name: '张报价' }, { uid: 12, name: '李报价' }]
        await vue.nextTick()
        state.handleBatchAdd()
        state.newDevice.value.model = '报价测试'
        state.newDevice.value.user_sn = 'ABC888'
        state.newDevice.value.initial_price = '0'
        await vue.nextTick()
        assert.equal(state.needsQuoteUser.value, true, '主动填 0 也视为填写价格')
        await state.confirmAdd()
        assert.equal(writes.length, 2)
        assert.ok(state.fieldErrors.value.initial_price_uid)
        state.selectQuoteUser(11)
        state.newDevice.value.initial_price_name = '伪造姓名'
        await state.confirmAdd()
        await vue.nextTick()
        assert.equal(devices.value[2].initial_price_uid, 11)
        assert.equal(devices.value[2].initial_price_name, '张报价')
        state.handleBatchAdd()
        state.newDevice.value.model = '空价测试'
        state.newDevice.value.user_sn = 'ABC889'
        await state.confirmAdd()
        await vue.nextTick()
        assert.equal(devices.value[3].initial_price_uid, 0)
        console.log('PASS 有价格须选报价员，留空不要求；不使用输入的姓名')

        state.handleBatchAdd()
        state.newDevice.value.model = '开关测试'
        state.newDevice.value.user_sn = 'ABC890'
        state.newDevice.value.initial_price = '100'
        await vue.nextTick()
        state.selectQuoteUser(11)
        quoteUsers.value = Array.from({ length: 6 }, (_, i) => ({ uid: i + 20, name: '员工' + i }))
        await vue.nextTick()
        assert.equal(state.newDevice.value.initial_price_uid, 0, '移除人员后清空旧选择')
        const picker = elements.find(({ node }) => node.tag === 'picker' && directive(node, 'bind', 'range')?.exp?.content === 'quotePickerOptions')
        const change = directive(picker.node, 'on', 'change').exp.content
        new Function('selectQuoteUser', 'quotePickerOptions', '$event', change)(state.selectQuoteUser, state.quotePickerOptions.value, { detail: { value: 3 } })
        assert.equal(state.newDevice.value.initial_price_uid, 22)
        enablePricing.value = false
        await vue.nextTick()
        assert.equal(state.newDevice.value.initial_price, '')
        assert.equal(state.newDevice.value.initial_price_uid, 0)
        await state.confirmAdd()
        await vue.nextTick()
        assert.equal(devices.value[4].initial_price, '')
        assert.equal(devices.value[4].initial_price_uid, 0)
        console.log('PASS 超过五人下拉选择；关闭价格开关清空当前输入，不带过期报价员')
    } finally {
        app.unmount()
    }
}
main().catch(error => { console.error(error); process.exitCode = 1 })
