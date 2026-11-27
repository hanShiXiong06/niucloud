#!/usr/bin/env node
// 真实 PC SFC / 查询 hook 隔离回归；只使用内存请求，不连接数据库或线上。
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), admin = path.join(root, 'admin'), base = path.join(admin, 'src/addon/hsx_recycle')
const ts = require(path.join(admin, 'node_modules/typescript'))
const vue = require(path.join(admin, 'node_modules/vue'))
const compiler = require(path.join(admin, 'node_modules/@vue/compiler-sfc'))
const { renderToString } = require(path.join(admin, 'node_modules/@vue/server-renderer'))
let passed = 0
const plain = value => value === undefined ? undefined : JSON.parse(JSON.stringify(value))
const equal = (a, b, label) => { assert.deepEqual(plain(a), plain(b), label); passed++ }
const ok = (value, label) => { assert.ok(value, label); passed++ }
const deferred = () => { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no }); return { resolve, reject, promise } }
const member = (id, name = '客户' + id) => ({ member_id: id, nickname: name, mobile: '13800000000' })
const denied = () => { throw Error('真实外部调用已禁用') }
async function flush() { for (let i = 0; i < 8; i++) { await Promise.resolve(); await vue.nextTick() } }
function execute(source, filename, dependencies) {
  const result = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 }, reportDiagnostics: true })
  equal((result.diagnostics || []).filter(item => item.category === ts.DiagnosticCategory.Error).length, 0, filename + ' TS 编译')
  const module = { exports: {} }
  vm.runInNewContext(result.outputText, { module, exports: module.exports, console: { ...console, warn() {}, error() {} }, require(name) {
    if (Object.prototype.hasOwnProperty.call(dependencies, name)) return dependencies[name]
    if (name.endsWith('.vue')) return { default: {} }
    throw Error('未知依赖：' + name)
  } }, { filename })
  return module.exports
}
function setupSfc(relative, inputProps, dependencies = {}) {
  const filename = path.join(base, relative), source = fs.readFileSync(filename, 'utf8')
  const { descriptor, errors } = compiler.parse(source, { filename })
  equal(errors, [], relative + ' SFC 解析')
  const script = compiler.compileScript(descriptor, { id: relative })
  const template = compiler.compileTemplate({ id: relative, filename, source: descriptor.template.content, compilerOptions: { bindingMetadata: script.bindings } })
  equal(template.errors, [], relative + ' 模板编译')
  const lifecycle = [], emitted = []
  const component = execute(script.content, filename, { vue: { ...vue, onBeforeUnmount(callback) { lifecycle.push(callback) } }, '@element-plus/icons-vue': { Search: {}, Refresh: {}, Plus: {}, User: {} }, ...dependencies }).default
  const values = {}
  for (const [name, definition] of Object.entries(component.props || {})) if (definition && typeof definition === 'object' && Object.prototype.hasOwnProperty.call(definition, 'default')) values[name] = definition.default
  const props = vue.reactive({ ...values, ...inputProps }), scope = vue.effectScope()
  const state = scope.run(() => component.setup(props, { expose() {}, emit(name, ...args) { emitted.push([name, ...plain(args)]); if (name === 'update:modelValue') props.modelValue = args[0] } }))
  const render = execute(template.code, filename + ':render', { vue }).render
  return { state, props, emitted, source, template: descriptor.template.content, render, dispose() { lifecycle.forEach(callback => callback()); scope.stop() } }
}
function selector(options = {}) {
  const calls = [], notices = []
  const c = setupSfc('components/member-select/index.vue', { modelValue: '', ...options.props }, {
    'element-plus': { ElMessage: { error: message => notices.push(message), success() {} } },
    '@/app/api/member': {
      getMemberInfo(id) { calls.push(['detail', id]); return options.detail ? options.detail(id) : Promise.resolve({ data: member(id) }) },
      getMemberList(params) { calls.push(['list', plain(params)]); return options.list ? options.list(params) : Promise.resolve({ data: { data: [member(7)], total: 1 } }) },
      getMemberNo: async () => ({ data: 'M100' }), addMember: denied
    },
    '@/utils/common': { img: value => value },
  })
  return { ...c, calls, notices }
}
const control = name => vue.defineComponent({ name, inheritAttrs: false, setup(_props, { slots, attrs }) { return () => vue.h('div', { 'data-control': name, ...attrs }, slots.default?.()) } })
const panelMock = vue.defineComponent({ props: ['modelValue'], setup(props, { slots }) { return () => vue.h('section', [slots.extra?.(), !props.modelValue ? slots.default?.() : null]) } })
const foldMock = vue.defineComponent({ props: ['modelValue'], setup(props, { slots }) { return () => vue.h('section', { 'data-fold': props.modelValue ? 'open' : 'closed' }, props.modelValue ? slots.default?.() : null) } })
async function panelHtml(c) {
  const state = vue.proxyRefs(c.state), app = vue.createSSRApp({ setup: () => () => c.render({}, [], c.props, state, {}, {}) })
  for (const name of ['el-button', 'el-form', 'el-form-item', 'el-input', 'el-select', 'el-option', 'el-input-number', 'el-date-picker']) app.component(name, control(name))
  app.config.warnHandler = () => {}
  return renderToString(app)
}
async function main() {
  {
    const c = selector({ props: { modelValue: 7 } }); await flush()
    equal(c.state.selectedMemberLabel.value, '客户7 · 13800000000', '已有客户反显')
    c.state.searchParams.keyword = '旧检索'; c.state.searchParams.page = 4; c.state.selectorVisible.value = true
    c.props.modelValue = ''; await flush()
    equal([c.state.selectedMember.value, c.state.selectedMemberLabel.value, c.state.searchParams.keyword, c.state.searchParams.page, c.state.memberList.value, c.state.total.value, c.state.selectorVisible.value], [null, '', '', 1, [], 0, false], '父表单一次重置同时清空客户反显与弹窗内部状态')
    equal(c.emitted.length, 0, '父级重置不额外emit change造成重复查询')
    c.state.searchParams.keyword = '只有弹窗搜索，未选客户'; c.state.searchParams.page = 3; c.state.selectorVisible.value = true
    c.props.resetKey = 1; await flush()
    equal([c.state.searchParams.keyword, c.state.searchParams.page, c.state.selectorVisible.value], ['', 1, false], 'ID原本为空时重置键仍能清弹窗')
    ok(c.source.includes('@clear="clearSelection"') && !c.source.includes('@clear.stop'), 'ElInput clear是无事件参数自定义事件，不调用stopPropagation')
    c.dispose()
  }
  {
    const old = deferred(), c = selector({ props: { modelValue: 7 }, detail: () => old.promise })
    c.props.modelValue = ''; await flush(); old.resolve({ data: member(7, '旧客户') }); await flush()
    equal([c.state.selectedMember.value, c.state.selectedMemberLabel.value], [null, ''], '清空后旧详情响应不能重新回填')
    c.dispose()
  }
  for (const rejectOld of [false, true]) {
    const a = deferred(), b = deferred(), c = selector({ props: { modelValue: 7 }, detail: id => id === 7 ? a.promise : b.promise })
    c.props.modelValue = 8; await flush(); b.resolve({ data: member(8, '新客户') }); await flush()
    equal(c.state.selectedMember.value.member_id, 8, '新客户先返回')
    if (rejectOld) a.reject(Error('旧请求错误')); else a.resolve({ data: member(7, '旧客户') })
    await flush(); equal(c.state.selectedMember.value.member_id, 8, '旧成功或失败均不能覆盖新客户')
    c.dispose()
  }
  {
    const old = deferred(), c = selector({ props: { modelValue: 7 }, detail: () => old.promise })
    c.state.clearSelection(); await flush(); old.resolve({ data: member(7) }); await flush()
    equal(c.emitted.filter(item => item[0] === 'change'), [['change', null, null]], '点击清除只发一次清空事件')
    equal([c.props.modelValue, c.state.selectedMember.value], [null, null], '清除后旧响应不复活')
    c.dispose()
  }
  {
    const old = deferred(), c = selector({ list: () => old.promise })
    c.state.openSelector(); c.state.searchParams.keyword = '旧请求'; c.props.resetKey = 1; await flush()
    old.resolve({ data: { data: [member(7)], total: 9 } }); await flush()
    equal([c.state.memberList.value, c.state.total.value, c.state.loading.value], [[], 0, false], '重置后旧客户列表响应也失效')
    c.dispose()
  }
  {
    const old = deferred(), c = selector({ list: () => old.promise })
    c.state.openSelector(); c.props.resetKey = 1; await flush(); old.reject(Error('旧列表失败')); await flush()
    equal(c.notices, [], '重置后旧列表失败不弹出过期错误')
    equal([c.state.loading.value, c.state.memberList.value], [false, []], '旧列表失败不留下加载状态')
    c.dispose()
  }
  {
    const a = deferred(), b = deferred(), c = selector({ list: params => params.keyword === '新' ? b.promise : a.promise })
    c.state.openSelector(); c.state.searchParams.keyword = '新'; c.state.searchMembers()
    b.resolve({ data: { data: [member(8)], total: 1 } }); await flush(); a.resolve({ data: { data: [member(7)], total: 1 } }); await flush()
    equal(c.state.memberList.value.map(item => item.member_id), [8], '客户弹窗按最后一次搜索展示')
    c.dispose()
  }
  {
    const old = deferred(), c = selector({ props: { modelValue: 7 }, detail: () => old.promise })
    c.dispose(); old.resolve({ data: member(7) }); await flush()
    equal(c.state.selectedMember.value, null, '卸载后不写入旧详情')
  }
  {
    const c = setupSfc('views/recycle_order/components/RecycleOrderSearchPanel.vue', { isMobile: false, mobileSearchVisible: false, advancedSearchForm: { member_id: '', status: [], create_time_range: [] }, orderStatusMap: {} }, { '@/addon/hsx_components/core': { HsxSearchPanel: panelMock, HsxFold: foldMock }, '@/addon/hsx_recycle/components/member-select/index.vue': { default: control('member-select') } })
    const compact = await panelHtml(c)
    equal((compact.match(/>查询</g) || []).length, 1, '基础筛选只显示一组查询')
    equal((compact.match(/>重置</g) || []).length, 1, '基础筛选只显示一组重置')
    ok(compact.indexOf('设备IMEI') < compact.indexOf('>查询<'), '基础查询按钮排在输入项之后')
    c.state.advancedOpen.value = true
    const expanded = await panelHtml(c)
    equal((expanded.match(/>查询</g) || []).length, 1, '展开高级后仍只一组查询')
    ok(expanded.indexOf('打款时间') < expanded.indexOf('>查询<'), '高级查询按钮在全部高级项末尾')
    c.state.panelCollapsed.value = true
    const collapsed = await panelHtml(c)
    equal((collapsed.match(/>查询</g) || []).length, 1, '整面板折叠仍可查询已选条件')
    c.state.handleReset(); equal(c.state.memberResetKey.value, 1, '重置同时发出客户组件resetKey')
    equal(c.emitted.at(-1), ['reset-search'], '重置只派发一次父级搜索重置')
    ok(c.source.includes('flex: 0 1 var(--search-item-width)') && !c.source.includes('flex: 1 1 220px'), '宽屏控件不再无限拉伸')
    ok(c.source.includes('flex-wrap: wrap') && c.source.includes('@media (max-width: 640px)'), '固定宽度可自动换行且小屏回退整行')
    ok(!c.source.includes('.search-advanced-grid :deep(.el-form-item) { display: flex; flex-direction: column'), '不再覆盖通用组件左右排列偏好')
    c.dispose()
  }
  {
    const filename = path.join(base, 'hooks/useRecycleOrderQuery.ts')
    const { useRecycleOrderQuery } = execute(fs.readFileSync(filename, 'utf8'), filename, { vue })
    const pagination = vue.ref({ page: 1, limit: 15, total: 0 }), requests = [], old = deferred(), fresh = deferred()
    const q = useRecycleOrderQuery({ pagination, setPagination: value => Object.assign(pagination.value, value), fetchList(params) { requests.push(plain(params)); return params.member_id ? old.promise : fresh.promise }, onError: denied })
    q.advancedSearchForm.member_id = 7; const first = q.getList(1)
    q.resetAdvancedSearch()
    fresh.resolve({ data: { data: [{ id: 20 }], total: 1, status_counts: { all: 1 } } }); await flush()
    old.resolve({ data: { data: [{ id: 10, member_id: 7 }], total: 5 } }); await first; await flush()
    equal(q.list.value, [{ id: 20 }], '列表重置后的结果不被旧客户查询覆盖')
    equal(requests, [{ page: 1, limit: 15, member_id: 7 }, { page: 1, limit: 15 }], '重置请求不再携带客户ID')
    equal([q.loading.value, pagination.value.total], [false, 1], '重置后的分页和loading属于最新请求')
  }
  for (const relative of ['components/member-select/index.vue', 'views/recycle_order/components/RecycleOrderSearchPanel.vue', 'hooks/useRecycleOrderQuery.ts']) {
    equal(fs.readFileSync(path.join(base, relative), 'utf8'), fs.readFileSync(path.join(root, 'niucloud/addon/hsx_recycle/admin', relative), 'utf8'), '发布镜像一致：' + relative)
  }
  console.log(`[通过] ${passed} 项 PC 订单搜索/客户重置隔离回归；未连接数据库、线上或实际打印。`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
