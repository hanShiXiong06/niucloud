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
