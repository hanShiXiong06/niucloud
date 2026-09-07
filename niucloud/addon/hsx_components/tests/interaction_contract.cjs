// 无网络、无数据库：使用真实 Vue 渲染器验证交互，不依赖截图或文本匹配代替行为测试。
const fs = require('node:fs')
const path = require('node:path')
const assert = require('node:assert/strict')
const { createRequire } = require('node:module')
const root = path.resolve(__dirname, '../../../..')
const req = createRequire(path.join(root, 'admin/package.json'))
const vue = req('vue'), compiler = req('@vue/compiler-sfc'), ts = req('typescript')
let count = 0
const scopedFiles = require('./interaction_scope.cjs')
function checkSources() {
    let files = 0
    for (const [addon, paths] of Object.entries(scopedFiles)) for (const relative of paths) {
        const file = path.join(root, 'admin/src/addon', addon, relative)
        const source = fs.readFileSync(file, 'utf8')
        assert.equal(source, fs.readFileSync(path.join(root, 'niucloud/addon', addon, 'admin', relative), 'utf8'), `插件源码不同步: ${addon}/${relative}`)
        if (file.endsWith('.scss')) { files++; continue }
        let script = source
        if (file.endsWith('.vue')) {
            const { descriptor, errors } = compiler.parse(source, { filename: file })
            assert.deepEqual(errors, [], file)
            const compiled = descriptor.scriptSetup || descriptor.script ? compiler.compileScript(descriptor, { id: file }) : { content: '', bindings: {} }
            script = compiled.content
            const template = compiler.compileTemplate({ source: descriptor.template.content, filename: file, id: file, compilerOptions: { bindingMetadata: compiled.bindings, expressionPlugins: ['typescript'] } })
            assert.deepEqual(template.errors, [], file)
        }
        const result = ts.transpileModule(script, { reportDiagnostics: true, compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } })
        assert.equal(result.diagnostics.filter(d => d.category === ts.DiagnosticCategory.Error).length, 0, file)
        files++
    }
    console.log('PASS shared UI syntax and plugin mirrors: ' + files + ' files')
}
function check(value, message) { assert.ok(value, message); count++ }
function load(relative, mocks = {}) {
    const file = path.join(root, 'admin/src/addon/hsx_components', relative)
    let source = fs.readFileSync(file, 'utf8')
    if (file.endsWith('.vue')) {
        const { descriptor, errors } = compiler.parse(source, { filename: file })
        assert.deepEqual(errors, [])
        source = compiler.compileScript(descriptor, { id: relative, inlineTemplate: true }).content
    }
    const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    const mod = { exports: {} }
    new Function('require', 'module', 'exports', code)(name => mocks[name] || req(name), mod, mod.exports)
    return mod.exports
}
function node(type, text = '') { return { type, text, props: {}, style: {}, children: [], parent: null } }
const renderer = vue.createRenderer({
    createElement: type => node(type), createText: text => node('#text', text), createComment: text => node('#comment', text),
    setText: (el, text) => { el.text = text }, setElementText: (el, text) => { el.text = text; el.children = [] },
    patchProp: (el, key, prev, next) => { el.props[key] = next },
    insert(el, parent, anchor) { if (el.parent) this.remove(el); el.parent = parent; const at = parent.children.indexOf(anchor); parent.children.splice(at < 0 ? parent.children.length : at, 0, el) },
    remove(el) { const list = el.parent?.children; if (list) list.splice(list.indexOf(el), 1); el.parent = null },
    parentNode: el => el.parent, nextSibling: el => el.parent?.children[el.parent.children.indexOf(el) + 1] || null,
})
const all = el => [el, ...el.children.flatMap(all)]
const byClass = (el, name) => all(el).find(row => String(row.props.class || '').split(' ').includes(name))
function mount(component, initial, slots = {}, components = {}) {
    const props = vue.reactive(initial), rootNode = node('root')
    const instance = vue.ref()
    const app = renderer.createApp({ render: () => vue.h(component, { ...props, ref: instance }, slots) })
    app.component('el-icon', { render() { return vue.h('i', {}, this.$slots.default?.()) } })
    for (const [name, value] of Object.entries(components)) app.component(name, value)
    app.directive('loading', {})
    app.mount(rootNode)
    return { props, rootNode, instance, unmount: () => app.unmount() }
}
async function main() {
    checkSources()
    const Notice = load('components/HsxNotice/index.vue').default
    const n = mount(Notice, { title: '说明', description: '详细说明' })
    check(byClass(n.rootNode, 'hsx-notice__detail').style.display === 'none', '普通说明默认折叠')
    byClass(n.rootNode, 'hsx-notice__toggle').props.onClick(); await vue.nextTick()
    check(byClass(n.rootNode, 'hsx-notice__detail').style.display !== 'none', '按钮展开详情')
    check(byClass(n.rootNode, 'hsx-notice__toggle').props['aria-expanded'] === true, '展开状态提供无障碍语义')
    byClass(n.rootNode, 'hsx-notice__close').props.onClick(); await vue.nextTick()
    check(!byClass(n.rootNode, 'hsx-notice'), '普通提示可以关闭')
    n.props.title = '新问题'; await vue.nextTick()
    check(!!byClass(n.rootNode, 'hsx-notice'), '不同问题重新显示，不继承关闭状态')
    check(byClass(n.rootNode, 'hsx-notice__detail').style.display === 'none', '新问题恢复默认折叠')
    n.props.closable = false; n.props.type = 'error'; await vue.nextTick()
    check(!byClass(n.rootNode, 'hsx-notice__close'), '阻断提醒不能关闭')
    check(byClass(n.rootNode, 'hsx-notice').props.role === 'alert', '错误使用即时提醒语义')
    n.unmount()
    const controlled = mount(Notice, { title: '受控提示', modelValue: true, 'onUpdate:modelValue': value => { controlled.props.modelValue = value } }, { actions: () => vue.h('button', { id: 'retry' }, '重试') })
    check(all(controlled.rootNode).some(x => x.props.id === 'retry'), '操作按钮不藏在折叠内容内')
    byClass(controlled.rootNode, 'hsx-notice__close').props.onClick(); await vue.nextTick()
    check(controlled.props.modelValue === false, '关闭同步父级状态')
    controlled.props.modelValue = true; await vue.nextTick()
    check(!!byClass(controlled.rootNode, 'hsx-notice'), '父级可重新打开提示')
    controlled.unmount()
    const Fold = load('components/HsxFold/index.vue').default
    let mounted = 0
    const Child = { setup() { mounted++; return () => vue.h('input', { value: '保留输入' }) } }
    const f = mount(Fold, { title: '更多筛选', resetKey: 1 }, { default: () => vue.h(Child) })
    const input = all(f.rootNode).find(x => x.type === 'input')
    check(byClass(f.rootNode, 'hsx-fold__body').style.display === 'none', '折叠区默认关闭')
    for (let i = 0; i < 3; i++) { byClass(f.rootNode, 'hsx-fold__trigger').props.onClick(); await vue.nextTick() }
    check(mounted === 1 && all(f.rootNode).includes(input), '开合保留表单实例和输入，不重复挂载')
    check(byClass(f.rootNode, 'hsx-fold__trigger').props['aria-expanded'] === true, '折叠按钮反映展开状态')
    f.props.resetKey = 2; await vue.nextTick()
    check(byClass(f.rootNode, 'hsx-fold__body').style.display === 'none', '切换设备重置阅读状态')
    f.unmount()
    const Title = load('components/HsxTitle/index.vue').default
    const heading = mount(Title, { title: '库存中心', subtitle: '操作说明', collapsibleSubtitle: true }, { extra: () => vue.h('button', { id: 'create' }, '创建') })
    check(byClass(heading.rootNode, 'hsx-title__subtitle').style.display === 'none', '页头辅助说明默认收起')
    check(all(heading.rootNode).some(x => x.props.id === 'create'), '页头核心操作不随说明收起')
    byClass(heading.rootNode, 'hsx-title__help').props.onClick(); await vue.nextTick()
    check(byClass(heading.rootNode, 'hsx-title__subtitle').style.display !== 'none', '页头说明可按需展开')
    heading.unmount()
    const Button = { setup(props, { attrs, slots }) { return () => vue.h('button', attrs, slots.default?.()) } }
    const savedWindow = global.window
    const storage = new Map()
    global.window = { localStorage: { getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value) } }
    const layoutHook = load('hooks/useSearchLayout.ts')
    const preference = layoutHook.useSearchLayout()
    check(preference.layout.value === 'horizontal' && preference.labelPosition.value === 'right', '搜索默认左右排列')
    preference.layout.value = 'vertical'
    check(layoutHook.useSearchLayout().labelPosition.value === 'top', '多个搜索面板共享排列偏好')
    check(load('hooks/useSearchLayout.ts').useSearchLayout().layout.value === 'vertical', '重新加载仍记住一次选择')
    preference.layout.value = 'invalid'
    check(preference.layout.value === 'vertical', '非法布局值不能破坏偏好')
    global.window.localStorage.setItem = () => { throw new Error('blocked') }
    preference.layout.value = 'horizontal'
    check(preference.layout.value === 'horizontal', '存储不可用不阻断当前页面切换')
    global.window = savedWindow
    const Panel = load('components/HsxSearchPanel/index.vue', { '../HsxTitle/index.vue': { default: Title }, '../../hooks/useSearchLayout': layoutHook }).default
    const panel = mount(Panel, { title: '筛选条件', collapsible: true, modelValue: false, 'onUpdate:modelValue': value => { panel.props.modelValue = value } }, { default: () => vue.h('input', { value: '已填写的串号' }) }, { 'el-button': Button })
    const searchInput = all(panel.rootNode).find(x => x.type === 'input')
    all(panel.rootNode).find(x => x.type === 'button' && x.props['aria-expanded'] !== undefined).props.onClick(); await vue.nextTick()
    check(panel.props.modelValue && byClass(panel.rootNode, 'hsx-search-panel__body').style.display === 'none', '搜索区域受控折叠')
    check(all(panel.rootNode).includes(searchInput) && searchInput.props.value === '已填写的串号', '折叠保留搜索输入和实例')
    panel.props.modelValue = false; await vue.nextTick()
    check(byClass(panel.rootNode, 'hsx-search-panel__body').style.display !== 'none', '搜索区可以由父级重新展开')
    panel.unmount()

    const Container = { setup(props, { slots }) { return () => vue.h('section', {}, slots.default?.()) } }
    const Input = { setup(props, { attrs }) { return () => vue.h('input', { value: attrs.modelValue }) } }
    const RecycleSearch = load('../hsx_recycle/views/recycle_order/components/RecycleOrderSearchPanel.vue', {
        '@/addon/hsx_components/core': { HsxSearchPanel: Panel, HsxFold: Fold },
        '@/addon/hsx_recycle/components/member-select/index.vue': { default: Input }
    }).default
    const filters = vue.reactive({ express_no: 'SF123', member_id: '', user_mobile: '', device_imei: '', order_no: 'R123', status: ['4'], amount_min: 0, delivery_type: [], create_time_range: [] })
    let mobileToggles = 0
    const recycle = mount(RecycleSearch, { isMobile: false, mobileSearchVisible: true, advancedSearchForm: filters, orderStatusMap: {}, 'onToggle-mobile-search': () => { mobileToggles++ } }, {}, {
        'el-form': Container, 'el-form-item': Container, 'el-input': Input, 'el-input-number': Input, 'el-select': Container, 'el-option': Container, 'el-date-picker': Input, 'el-button': Button
    })
    const beforeFilters = JSON.stringify(filters)
    for (let i = 0; i < 2; i++) { byClass(recycle.rootNode, 'hsx-fold__trigger').props.onClick(); await vue.nextTick() }
    check(JSON.stringify(filters) === beforeFilters, '回收高级筛选开合不清空订单、状态及零金额条件')
    check(byClass(recycle.rootNode, 'hsx-search-panel__summary').text.includes('4 项'), '筛选摘要正确计入数字0，不计空字符串和空数组')
    recycle.props.isMobile = true; recycle.props.mobileSearchVisible = false; await vue.nextTick()
    check(byClass(recycle.rootNode, 'hsx-search-panel__body').style.display === 'none', '窄屏遵循父级搜索可见状态')
    all(recycle.rootNode).find(x => x.type === 'button' && x.props['aria-expanded'] !== undefined).props.onClick(); await vue.nextTick()
    check(mobileToggles === 1 && JSON.stringify(filters) === beforeFilters, '窄屏展开只发送可见性事件，不改查询条件')
    recycle.unmount()

    // 执行页面实际配置与搜索函数，验证 schema 接入没有改掉字段值、路由范围或分页规则。
    function queryBindings(relative, names, bindings) {
        const source = compiler.parse(fs.readFileSync(path.join(root, 'admin/src/addon', relative), 'utf8')).descriptor.scriptSetup.content
        const ast = ts.createSourceFile(relative + '.ts', source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS)
        const selected = ast.statements.filter(x => ts.isFunctionDeclaration(x) ? names.includes(x.name?.text) : ts.isVariableStatement(x) && x.declarationList.declarations.some(d => names.includes(d.name.getText(ast))))
        const code = ts.transpileModule(selected.map(x => x.getText(ast)).join('\n'), { compilerOptions: { target: ts.ScriptTarget.ES2020 } }).outputText
        return new Function(...Object.keys(bindings), code + '; return { ' + names.join(',') + ' };')(...Object.values(bindings))
    }
    const stockSearch = { keyword: '', warehouse_id: '', status: 'counting', page: 8, limit: 15 }
    let stockQueries = 0
    const sq = queryBindings('hsx_erp/views/erp/stocktake/list.vue', ['queryModel', 'querySchema', 'searchStocktakes'], { computed: vue.computed, search: stockSearch, warehouses: vue.ref([{ id: 17, warehouse_name: '一号仓' }]), loadList: () => { stockQueries++ } })
    check(sq.querySchema.value[1].options[0].value === 17, '盘点 schema 使用真实仓库ID而不是显示名称')
    sq.searchStocktakes({ keyword: ' PO123 ', warehouse_id: 17 })
    check(stockSearch.keyword === 'PO123' && stockSearch.page === 1 && stockSearch.status === 'counting' && stockQueries === 1, '盘点搜索去首尾空格、重置页码并保留状态页签')
    sq.searchStocktakes({})
    check(stockSearch.keyword === '' && stockSearch.warehouse_id === '', '空 schema 查询清除旧输入，避免隐藏条件残留')
    const consignmentSearch = { keyword: '', status: '', source_order_id: '88', create_time: ['2026-09-01', '2026-09-07'] }
    let consignmentQueries = 0
    const cq = queryBindings('hsx_recycle/views/consignment_order/list.vue', ['queryModel', 'querySchema', 'searchConsignments', 'clearQueryScope'], { computed: vue.computed, search: consignmentSearch, statusOptions: vue.ref([{ label: '待售', value: 0 }]), handleSearch: () => { consignmentQueries++ } })
    cq.searchConsignments({ keyword: ' IMEI123 ', status: 0 })
    check(consignmentSearch.keyword === 'IMEI123' && consignmentSearch.status === 0 && consignmentSearch.source_order_id === '88' && consignmentQueries === 1, '代卖搜索保留数字状态与原订单范围，仅查询一次')
    cq.clearQueryScope(); cq.searchConsignments({})
    check(consignmentSearch.source_order_id === '' && consignmentSearch.create_time.length === 0 && consignmentSearch.status === '', '代卖重置清除路由带入的范围及输入')
    const countFilters = vue.reactive({ keyword: '', my_task: 0, party_scope: 'supplier', dateRange: [], min_cost: 0 })
    const inventoryConditions = queryBindings('hsx_erp/views/erp/stock/list.vue', ['advancedFilterCount', 'searchConditionCount'], { computed: vue.computed, search: countFilters })
    check(inventoryConditions.searchConditionCount.value === 1, '库存条件提示计入0元，不计默认责任范围与空日期')
    countFilters.keyword = 'IMEI123'; countFilters.my_task = 1
    check(inventoryConditions.searchConditionCount.value === 3, '库存收起后仍能看见基础及高级条件总数')
    const payableConditions = queryBindings('hsx_erp/views/erp/payable/list.vue', ['searchConditionCount'], { computed: vue.computed, search: { keyword: '', party_id: 18, party_name: '测试服务商', source_no: '' }, dateRange: vue.ref([]) })
    check(payableConditions.searchConditionCount.value === 1, '应付款不把同一主体的ID与姓名重复计数')
    const dashboard = queryBindings('hsx_erp/views/erp/workbench/index.vue', ['summaryCards', 'primarySummaryCards', 'secondarySummaryCards'], { computed: vue.computed, summary: vue.ref({ operating_profit_amount: 100, receipt_amount: 5000, payment_amount: 4500 }), money: value => String(Number(value || 0)) })
    check(dashboard.primarySummaryCards.value.length === 4 && dashboard.secondarySummaryCards.value.length === 8, '经营看板聚焦4项核心指标，其余8项保留在折叠内')
    check(dashboard.primarySummaryCards.value.find(item => item.label === '经营净利润').value === '100' && dashboard.primarySummaryCards.value.find(item => item.label === '净现金流').value === '500', '看板折叠不改变利润与现金流各自口径')
    let message, confirmation, mode = 'confirm'
    const { useFeedback } = load('hooks/useFeedback.ts', { 'element-plus': {
        ElMessage: options => { message = options }, ElNotification: () => {},
        ElMessageBox: { confirm: async (content, title, options) => { confirmation = { content, title, ...options }; if (mode === 'cancel') throw 'cancel' }, alert: async () => { if (mode === 'cancel' || mode === 'close') throw mode; if (mode === 'error') throw new Error('runtime failure') } },
    } })
    const feedback = useFeedback()
    feedback.success('已完成'); check(message.showClose && message.grouping && message.duration === 2400, '成功提示可关闭并合并重复消息')
    feedback.error('失败原因'); check(message.duration === 5200 && message.showClose, '失败提示有足够阅读时间且可关闭')
    check(await feedback.confirm({ message: '会新增应付，不在此转账', title: '确认调价', confirmText: '确认调整' }), '确认允许继续')
    check(confirmation.confirmButtonText === '确认调整' && !confirmation.closeOnClickModal && confirmation.content.includes('不在此转账'), '保留业务影响说明并禁止误触遮罩')
    mode = 'cancel'; check(await feedback.confirm('确认？') === false, '取消返回false，不能误执行后续操作')
    await feedback.alert('已完成'); mode = 'close'; await feedback.alert('已完成')
    check(true, '关闭或取消阅读说明不抛出保存失败')
    mode = 'error'; await assert.rejects(feedback.alert('说明'), /runtime failure/)
    check(true, '真实异常不会被当作普通关闭吞掉')

    const Drawer = load('components/HsxDrawer/index.vue').default
    let closeDone, cancellations = 0, confirmations = 0, drawerProps
    const d = mount(Drawer, { modelValue: true, title: '设备档案', size: 'lg', showFooter: true,
        beforeClose: done => { closeDone = done },
        onCancel: () => { cancellations++ }, onConfirm: () => { confirmations++ },
        'onUpdate:modelValue': value => { d.props.modelValue = value }
    }, {}, {
        'el-drawer': { setup(props, { attrs, slots }) { return () => { drawerProps = attrs; return vue.h('section', {}, [slots.header?.({ titleId: 'drawer-title' }), slots.default?.(), slots.footer?.()]) } } },
        'el-button': { setup(props, { attrs, slots }) { return () => vue.h('button', attrs, slots.default?.()) } }
    })
    check(drawerProps.size === 'min(1040px, 100vw)', '统一抽屉宽度不超出视口')
    const cancel = () => all(d.rootNode).find(x => x.type === 'button' && x.children.some(c => c.text === '取消')).props.onClick()
    cancel(); check(cancellations === 0 && d.props.modelValue, '关闭确认通过前不触发取消或改动可见状态')
    closeDone(); await vue.nextTick(); check(cancellations === 1 && !d.props.modelValue, '关闭确认通过后才取消并关闭')
    d.props.modelValue = true; d.props.confirmLoading = true; await vue.nextTick()
    closeDone = undefined; d.instance.value.close(); d.instance.value.confirm(); cancel()
    check(!closeDone && d.props.modelValue && cancellations === 1 && confirmations === 0, '提交中阻止重复提交、关闭和取消')
    check(!drawerProps.showClose && !drawerProps.closeOnPressEscape && !drawerProps.closeOnClickModal, '提交中屏蔽误触关闭入口')
    d.props.confirmLoading = false; d.props.confirmDisabled = true; await vue.nextTick()
    d.instance.value.confirm(); check(confirmations === 0, '禁用状态也约束暴露的确认方法')
    d.props.confirmDisabled = false; await vue.nextTick(); d.instance.value.confirm()
    check(confirmations === 1, '恢复可操作后正常确认')
    d.unmount()
    const Dialog = load('components/HsxDialog/index.vue').default
    let dialogProps, dialogDone, dialogCancels = 0, dialogConfirms = 0
    const dialog = mount(Dialog, { modelValue: true, title: '采购开单', size: 'xl', showFooter: true,
        beforeClose: done => { dialogDone = done },
        onCancel: () => { dialogCancels++ }, onConfirm: () => { dialogConfirms++ },
        'onUpdate:modelValue': value => { dialog.props.modelValue = value }
    }, { default: () => vue.h('input', { value: '尚未提交的设备' }) }, {
        'el-dialog': { setup(props, { attrs, slots }) { return () => { dialogProps = attrs; return vue.h('section', {}, [slots.header?.({ titleId: 'dialog-title' }), slots.default?.(), slots.footer?.()]) } } },
        'el-button': Button
    })
    const dialogButton = text => all(dialog.rootNode).find(x => x.type === 'button' && x.children.some(c => c.text.trim() === text))
    check(dialogProps.width === 'min(1160px, calc(100vw - 32px))', '大弹窗保留边距且不超出屏幕')
    dialogButton('取消').props.onClick()
    check(dialog.props.modelValue && dialogCancels === 0, '弹窗取消等待关闭确认')
    dialogDone(); await vue.nextTick()
    check(!dialog.props.modelValue && dialogCancels === 1, '关闭确认通过后才取消弹窗')
    dialog.props.modelValue = true; dialog.props.confirmLoading = true; await vue.nextTick()
    dialogDone = undefined
    dialog.instance.value.close(); dialogButton('取消').props.onClick(); dialogButton('确定').props.onClick()
    check(!dialogDone && dialog.props.modelValue && dialogConfirms === 0, '弹窗提交中阻止取消、关闭和重复提交')
    check(!dialogProps.showClose && !dialogProps.closeOnPressEscape && !dialogProps.closeOnClickModal, '弹窗提交中关闭入口一致受保护')
    dialog.props.confirmLoading = false; dialog.props.confirmDisabled = true; await vue.nextTick()
    dialogButton('确定').props.onClick(); check(dialogConfirms === 0, '弹窗禁用确认不能提交')
    dialog.props.confirmDisabled = false; await vue.nextTick()
    dialogButton('确定').props.onClick(); check(dialogConfirms === 1, '弹窗恢复后正常提交')
    const formNode = all(dialog.rootNode).find(x => x.type === 'input')
    dialog.instance.value.toggleFullscreen(); await vue.nextTick()
    check(dialogProps.fullscreen && !dialogProps.draggable && all(dialog.rootNode).includes(formNode), '全屏切换保留表单实例并暂停拖拽')
    dialog.props.fullscreen = true; await vue.nextTick()
    dialog.props.fullscreen = false; await vue.nextTick()
    check(!dialogProps.fullscreen, '外部全屏状态可复位')
    dialog.unmount()
    // 使用库存页实际函数校验提示适配，避免业务结果变量遮蔽公共反馈实例。
    const stockFile = path.join(root, 'admin/src/addon/hsx_erp/views/erp/stock/list.vue')
    const stockScript = compiler.parse(fs.readFileSync(stockFile, 'utf8')).descriptor.scriptSetup.content
    const stockAst = ts.createSourceFile(stockFile + '.ts', stockScript, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS)
    const listingFn = stockAst.statements.find(x => ts.isFunctionDeclaration(x) && x.name?.text === 'showListingFeedback')
    assert.ok(listingFn, '库存上架结果提示入口存在')
    const listingCode = ts.transpileModule(listingFn.getText(stockAst), { compilerOptions: { target: ts.ScriptTarget.ES2020 } }).outputText
    const feedbackCalls = []
    const showListingFeedback = new Function('feedback', 'erpListingFeedback', listingCode + '; return showListingFeedback;')({
        success: message => feedbackCalls.push(['success', message]),
        alert: async (message, title) => feedbackCalls.push(['alert', message, title])
    }, result => result)
    await showListingFeedback({ level: 'success', message: '保存成功', detail: '已交接商城' }, '已保存')
    check(feedbackCalls[0].join('|') === 'success|保存成功；已交接商城', '库存成功反馈保留后续业务结果')
    await showListingFeedback({ level: 'warning', message: '资料已保存', detail: '分类待补齐，请重试上架' }, '已保存')
    check(feedbackCalls[1].join('|') === 'alert|分类待补齐，请重试上架|资料已保存', '上架异常显示具体原因，不被成功提示遮盖')
    console.log('PASS shared interactions: ' + count + ' behavior assertions')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
