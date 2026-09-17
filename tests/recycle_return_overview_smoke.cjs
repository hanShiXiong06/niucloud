// 退回概览定向编译与真实 setup 计算测试，不发布、不连接业务数据库。
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '..')
const compiler = require(path.join(root, 'admin/node_modules/@vue/compiler-sfc'))
const ts = require(path.join(root, 'admin/node_modules/typescript'))
const vue = require(path.join(root, 'admin/node_modules/vue'))
const relative = 'views/stats/components/dashboard/RecycleOverviewBoard.vue'
const filename = path.join(root, 'admin/src/addon/hsx_recycle', relative)
const source = fs.readFileSync(filename, 'utf8')
assert.equal(source, fs.readFileSync(path.join(root, 'niucloud/addon/hsx_recycle/admin', relative), 'utf8'))
const { descriptor, errors } = compiler.parse(source, { filename })
assert.deepEqual(errors, [])
const script = compiler.compileScript(descriptor, { id: 'return-overview' })
const compiled = ts.transpileModule(script.content, {
  fileName: filename + '.ts', reportDiagnostics: true,
  compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.CommonJS }
})
assert.deepEqual(compiled.diagnostics || [], [])
assert.deepEqual(compiler.compileTemplate({
  source: descriptor.template.content, filename, id: 'return-overview',
  compilerOptions: { bindingMetadata: script.bindings, expressionPlugins: ['typescript'] }
}).errors, [])
for (const style of descriptor.styles) {
  assert.deepEqual(compiler.compileStyle({ source: style.content, filename, id: 'data-v-return-overview' }).errors, [])
}
console.log('PASS 开发与插件发布源码一致；Vue、TypeScript、模板及样式编译')

const sandbox = {
  exports: {}, console,
  window: { localStorage: { getItem: () => null } },
  require: name => name === 'vue' ? { ...vue, onMounted() {}, onUnmounted() {} } : {}
}
vm.runInNewContext(compiled.outputText, sandbox)
const props = vue.reactive({ dashboard: { cards: [], ledger: { return_device_count: 2, pending_return_count: 3 } }, trend: {}, dateLabel: '今日', loading: false })
const state = sandbox.exports.default.setup(props, { expose() {}, emit() {} })
const byKey = key => state.ledgerStats.value.find(item => item.filterKey === key)
assert.equal(byKey('pending_return').label, '退回未完成')
assert.equal(byKey('pending_return').value, 3)
assert.equal(byKey('pending_return').unit, '台')
assert.equal(byKey('pending_return').viewMode, 'device_expand')
assert.equal(byKey('pending_return').scope, '当前全部 · 不限日期')
assert.match(byKey('pending_return').hint, /不是退回单数/)
assert.match(byKey('pending_return').hint, /不受顶部日期影响/)
assert.equal(byKey('returned_devices').label, '已退回客户')
assert.equal(byKey('returned_devices').value, 2)
assert.equal(byKey('returned_devices').scope, '所选时间内完成')
props.dashboard.ledger.pending_return_count = 0
props.dashboard.ledger.return_device_count = 5
assert.equal(byKey('pending_return').value, 0)
assert.equal(byKey('returned_devices').value, 5)
props.dashboard.ledger = undefined
assert.equal(byKey('pending_return').value, 0)
assert.equal(byKey('returned_devices').value, 0)
assert.match(source, /<el-popover v-if="item.hint" trigger="click"/)
assert.match(source, /class="ledger-help".*@click.stop/)
assert.match(source, /class="ledger-action" @click="\$emit\('drilldown-ledger', item\)"/)
assert.doesNotMatch(source, /label: "退货待处理"|label: "退货"/)
console.log('PASS 18 项显示口径、更新归零、空数据、触屏说明及独立下钻入口检查')
