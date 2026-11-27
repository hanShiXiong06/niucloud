#!/usr/bin/env node
// 自由规格组名称的现有管理端消费点；只执行真实函数及SFC编译，不调用业务API。
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), app = path.join(root, 'admin')
const base = path.join(app, 'src/addon/phone_shop'), mirror = path.join(root, 'niucloud/addon/phone_shop/admin')
const ts = require(path.join(app, 'node_modules/typescript')), vue = require(path.join(app, 'node_modules/vue'))
const compiler = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
let checks = 0
const same = (actual, expected, label) => { assert.deepEqual(JSON.parse(JSON.stringify(actual)), expected, label); checks++ }
const yes = (actual, label) => { assert.ok(actual, label); checks++ }
const fixture = [
    { label: '苹果内存规格', items: [{ item_value: '512G' }, { item_value: '128G' }] },
    { label: '手表规格', items: [{ item_value: '49MM' }, { item_value: '41MM' }] },
    { label: '笔记本规格', items: [{ item_value: '32+1T' }, { item_value: '16+512' }] },
    { label: '任意名字', items: [{ item_value: '128G' }, { item_value: '' }] }
]
const cases = [
    ['views/goods/list.vue', 'loadMemGradeOptions', 'memOptions'],
    ['views/goods/real_edit.vue', 'loadMemGradeOptions', 'memOptions'],
    ['views/cashier/index.vue', 'loadFilters', 'memOptions'],
    ['views/goods/components/goods-batch-settings-popup.vue', 'refreshStructuredOptions', 'memoryOptions']
]
function readFunction(file, name) {
    const filename = path.join(base, file), source = fs.readFileSync(filename, 'utf8')
    const { descriptor, errors } = compiler.parse(source, { filename }); yes(!errors.length, `${file} SFC解析`)
    const script = compiler.compileScript(descriptor, { id: 'spec-consumer-test' })
    const template = compiler.compileTemplate({ source: descriptor.template.content, filename, id: 'spec-consumer-test', isTS: true,
        compilerOptions: { bindingMetadata: script.bindings, expressionPlugins: ['typescript'] } })
    yes(!template.errors.length, `${file} SFC脚本与模板编译`)
    const tree = ts.createSourceFile(filename, descriptor.scriptSetup.content, ts.ScriptTarget.Latest, true, ts.ScriptKind.TS)
    for (const statement of tree.statements) if (ts.isVariableStatement(statement)) {
        for (const declaration of statement.declarationList.declarations) if (declaration.name.getText(tree) === name) {
            return { source, body: declaration.initializer.getText(tree) }
        }
    }
    throw Error(`Missing ${file}:${name}`)
}
function evaluate(body, context) {
    const exports = {}, module = { exports }
    const js = ts.transpileModule(`export const run = ${body}`, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    vm.runInNewContext(js, { exports, module, Set, ...context })
    return module.exports.run
}
async function main() {
    for (const [file, name, valueKey] of cases) {
        const { source, body } = readFunction(file, name), calls = [], values = vue.ref([]), gradeOptions = vue.ref([])
        let response = fixture, failing = false
        const run = evaluate(body, {
            [valueKey]: values, gradeOptions, categoryId: vue.ref(23),
            getSpecGroups: async params => { calls.push(params); if (failing) throw Error('isolated failure'); return { data: response } },
            getGrades: async () => ({ data: [{ grade_name: '优良' }] }),
            deriveOptions: key => { if (key === 'memory_group') throw Error('Must not use stock-derived specifications'); return ['原成色兜底'] }
        })
        await run()
        same(values.value, ['512G', '128G', '49MM', '41MM', '32+1T', '16+512'], `${file} 任意名字均消费且保持人工序去重`)
        same(calls[0], { category_id: file.includes('cashier') ? 23 : 0 }, `${file} 分类请求不改变`)
        same(gradeOptions.value, ['优良'], `${file} 成色仍取原字典`)
        response = []
        await run(); same(values.value, [], `${file} 空字典不保留旧值、不库存兜底`)
        response = fixture; await run(); failing = true
        await run(); same(values.value, [], `${file} 读取失败清空规格`)
        yes(!body.includes("includes('内存')"), `${file} 不凭名字排除规格组`)
        yes(!body.includes('.sort()'), `${file} 不擅自按字符串重排`)
        yes(!source.includes("deriveOptions('memory_group')"), `${file} 无库存规格兜底调用`)
        same(source, fs.readFileSync(path.join(mirror, file), 'utf8'), `${file} 发布镜像一致`)
    }
    console.log(`[通过] ${checks} 项管理端规格消费回归；四个真实SFC与实际加载函数，模拟API，无数据库调用。`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
