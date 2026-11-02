#!/usr/bin/env node
// 规格分组前端隔离回归：真实 helper、筛选弹窗脚本及页面计算表达式；不连接业务API。
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), app = path.join(root, 'uni-app')
const base = path.join(app, 'src/addon/phone_shop'), mirror = path.join(root, 'niucloud/addon/phone_shop/uni-app')
const ts = require(path.join(app, 'node_modules/typescript')), vue = require(path.join(app, 'node_modules/vue'))
const { parse, compileScript, compileTemplate } = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
let checks = 0
const same = (actual, expected, label) => { assert.deepEqual(JSON.parse(JSON.stringify(actual)), expected, label); checks++ }
const yes = (actual, label) => { assert.ok(actual, label); checks++ }
function evaluate(source, globals = {}, resolver = () => ({})) {
    const exports = {}, module = { exports }
    const js = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020, esModuleInterop: true } }).outputText
    vm.runInNewContext(js, { exports, module, require: resolver, Set, Map, console, ...globals })
    return module.exports
}
const helperSource = fs.readFileSync(path.join(base, 'utils/goods-spec-filter.ts'), 'utf8')
const { goodsSpecFilterGroups } = evaluate(helperSource)
const fixture = { memory_groups: [
    { group_id: 9, group_name: '手表规格', sort: 0, items: [{ item_id: 91, value: '46MM', label: '46 毫米', sort: 0 }, { value: '42MM', label: '42 毫米', sort: 5 }] },
    { group_id: 2, group_name: '苹果内存规格', sort: 10, items: [{ value: '256G', label: '256G' }, { value: '128G', label: '128G' }] },
    { group_id: 3, group_name: '安卓内存规格', sort: 20, items: [{ value: '8+128', label: '8GB + 128GB' }] }
], memories: [{ value: '脏数据', label: '不该展示' }] }
function scriptFor(file) {
    const filename = path.join(base, file), source = fs.readFileSync(filename, 'utf8')
    const result = parse(source, { filename }); yes(!result.errors.length, `${file} SFC解析`)
    const script = compileScript(result.descriptor, { id: 'spec-filter-test' })
    const template = compileTemplate({ source: result.descriptor.template.content, filename, id: 'spec-filter-test', isTS: true,
        compilerOptions: { bindingMetadata: script.bindings, expressionPlugins: ['typescript'] } })
    yes(!template.errors.length, `${file} 模板编译`)
    return { source, script: script.content, raw: result.descriptor.scriptSetup.content }
}
function initializer(raw, name) {
    const tree = ts.createSourceFile('sfc.ts', raw, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS)
    for (const node of tree.statements) if (ts.isVariableStatement(node)) {
        for (const declaration of node.declarationList.declarations) if (declaration.name.getText(tree) === name) return declaration.initializer.getText(tree)
    }
    throw Error(`Missing actual declaration ${name}`)
}
async function main() {
    same(goodsSpecFilterGroups({}), [], '缺分组不回退旧数据')
    same(goodsSpecFilterGroups({ memory_groups: [], memories: fixture.memories }), [], '空分组不消费脏memories')
    same(goodsSpecFilterGroups({ memory_groups: {} }), [], '非法分组格式忽略')
    const before = JSON.stringify(fixture), groups = goodsSpecFilterGroups(fixture)
    same(groups.map(group => group.title), ['手表规格', '苹果内存规格', '安卓内存规格'], '服务端组顺序原样保留')
    same(groups[0].items.map(item => item.value), ['46MM', '42MM'], '规格值不按容量猜排序')
    same(groups[1].items.map(item => item.value), ['256G', '128G'], '人工顺序不被数字排序覆盖')
    same(groups[0].items[0], { value: '46MM', label: '46 毫米' }, '提交值与展示名分别保留')
    same(JSON.stringify(fixture), before, '不修改接口响应')
    same(goodsSpecFilterGroups({ memory_groups: [{ group_id: 1, label: '自定义笔记本规格', items: [{ value: '16+1T' }] }] })[0],
        { key: 'spec_1', title: '自定义笔记本规格', items: [{ value: '16+1T', label: '16+1T' }] }, '未来分组动态支持label字段')
    same(goodsSpecFilterGroups({ memory_groups: [...fixture.memory_groups, { group_id: 4, group_name: '游戏机规格', items: [{ value: '光驱版' }] }] }).length, 4, '不限定三个组')
    const duplicate = goodsSpecFilterGroups({ memory_groups: [
        { group_id: 1, group_name: '规格', items: [{ value: '128G' }, { value: ' 128G ' }, null, { value: '' }, { value: NaN }, { value: {} }, { value: 0 }] },
        { group_id: 2, group_name: '规格', items: [{ value: '128G' }] },
        { group_id: 3, group_name: '', items: [{ value: '隐藏' }] },
        { group_id: 4, group_name: '空规格', items: [] }
    ] })
    same(duplicate.length, 2, '空组和无名称组不展示')
    same(duplicate[0].items.map(item => item.value), ['128G', '0'], '组内去重与非法值防御')
    same(duplicate[1].items[0].value, '128G', '不同组允许相同实际筛选值')
    yes(duplicate[0].key !== duplicate[1].key, '重名组使用独立ID作为key')

    const list = scriptFor('pages/goods/list.vue'), category = scriptFor('pages/goods/components/category-template-three-one.vue')
    const popup = scriptFor('components/goods-filter/GoodsOptionFilterPopup.vue')
    for (const [page, options] of [[list, vue.reactive(structuredClone(fixture))], [category, vue.ref(structuredClone(fixture))]]) {
        const memory = evaluate('export const groups = ' + initializer(page.raw, 'memoryGroups'),
            { computed: vue.computed, filterOptions: options, goodsSpecFilterGroups }).groups
        same(memory.value.map(group => group.title), ['手表规格', '苹果内存规格', '安卓内存规格'], '真实页面计算表达式消费分组')
        if ('value' in options) options.value = { memory_groups: [], memories: fixture.memories }
        else options.memory_groups = []
        same(memory.value, [], '实际页面空配置不回退脏值')
    }
    yes(list.source.includes('title="选择规格"'), '列表入口用规格覆盖手机手表')
    yes(!list.raw.includes('内存容量'), '不再硬编码唯一内存标题')
    yes(category.source.includes('tmpMemory.includes(m.value)') && category.source.includes('toggleChip(tmpMemory, m.value)'), '分类按实际value选择')
    yes(category.source.includes('{{ m.label }}'), '分类按字典label展示')
    yes(!category.raw.includes("m.includes('+')"), '不再按加号猜苹果安卓分类')
    const popupComponent = evaluate(popup.script, {}, id => id === 'vue' ? vue : {}).default
    const emitted = [], props = vue.reactive({ show: true, title: '选择规格', modelValue: ['128G'], groups, multiple: true })
    const scope = vue.effectScope()
    const state = scope.run(() => popupComponent.setup(props, { expose() {}, emit: (...args) => emitted.push(args) }))
    same(state.selected.value, ['128G'], '打开弹窗反显已选实际值')
    state.toggle('46MM'); same(state.selected.value, ['128G', '46MM'], '跨分组多选')
    same(props.modelValue, ['128G'], '确认前不修改父筛选')
    state.confirm(); same(emitted[0], ['confirm', ['128G', '46MM']], '确认提交实际规格而不是group/item ID')
    same(emitted[1], ['update:show', false], '确认后正常关闭')
    emitted.length = 0; state.reset(); same(state.selected.value, [], '重置清空待提交选择')
    same(emitted, [], '重置后确认才生效')
    state.confirm(); same(emitted[0], ['confirm', []], '可确认清空筛选')
    emitted.length = 0; state.toggle('46MM'); state.close(); same(emitted, [['update:show', false]], '取消不提交临时选择')
    props.show = false; await vue.nextTick(); props.modelValue = ['8+128']; props.show = true; await vue.nextTick()
    same(state.selected.value, ['8+128'], '重开反显父状态，不保留取消草稿')
    state.toggle('8+128'); same(state.selected.value, [], '相同值在任意组再次点击为取消')
    props.multiple = false; state.toggle('46MM'); state.toggle('42MM'); same(state.selected.value, ['42MM'], '公共单选功能不回归')
    scope.stop()
    for (const file of ['utils/goods-spec-filter.ts','pages/goods/list.vue','pages/goods/components/category-template-three-one.vue']) {
        same(fs.readFileSync(path.join(base, file), 'utf8'), fs.readFileSync(path.join(mirror, file), 'utf8'), `${file} 发布镜像同步`)
    }
    console.log(`[通过] ${checks} 项规格分组前端隔离断言；真实helper、页面表达式及公共筛选弹窗，无业务API调用。`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
