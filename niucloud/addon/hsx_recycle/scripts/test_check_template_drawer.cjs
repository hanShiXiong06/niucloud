// 单独测试抽屉的请求状态，不连网络、不改数据库。
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const { createRequire } = require('node:module')
const root = path.resolve(__dirname, '../../../..')
const fromAdmin = createRequire(path.join(root, 'admin/package.json'))
const compiler = fromAdmin('@vue/compiler-sfc')
const ts = fromAdmin('typescript')
const source = fs.readFileSync(path.join(root, 'niucloud/addon/hsx_recycle/admin/components/device-entry/CheckTemplateConfigDrawer.vue'), 'utf8')
const { descriptor } = compiler.parse(source)
const compiled = compiler.compileScript(descriptor, { id: 'drawer-state-test' })
const code = ts.transpileModule(compiled.content, { compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.CommonJS } }).outputText
let response
let saves = 0
const api = {
    getCheckTemplateSchema: (...args) => response(...args),
    saveTemplateBinding: async () => { saves++ }, saveCheckField: async () => { saves++ }
}
const context = { exports: {}, require(name) {
    if (name.startsWith('@/')) return api
    if (name === 'element-plus') return { ElMessage: { success() {}, warning() {} }, ElMessageBox: {} }
    if (name === '@element-plus/icons-vue') return { Iphone: {} }
    return fromAdmin(name)
} }
vm.runInNewContext(code, context)
const state = context.exports.default.setup({ visible: true, categoryId: 8, modelName: 'iPhone Air' }, { expose() {}, emit() {} })
const envelope = (id, groups = []) => ({ data: { template: { id, scene: 'pjt' }, groups } })
async function run() {
    state.selectedTemplateId.value = 1
    response = async () => { throw new Error('网络暂时不可用') }
    await state.loadSelectedTemplateSchema()
    assert.match(state.schemaError.value, /网络暂时不可用/)
    assert.equal(state.schemaWarning.value, '')
    await state.saveConfig()
    assert.equal(saves, 0)
    response = async () => undefined
    await state.loadSelectedTemplateSchema()
    assert.match(state.schemaError.value, /接口未返回/)
    response = async () => envelope(1)
    await state.loadSelectedTemplateSchema()
    assert.equal(state.schemaError.value, '')
    assert.match(state.schemaWarning.value, /没有可用的质检字段/)
    let completeOld
    response = () => new Promise(resolve => { completeOld = resolve })
    const oldRequest = state.loadSelectedTemplateSchema()
    state.selectedTemplateId.value = 2
    response = async () => envelope(2, [{ id: -1, fields: [{ id: -10001, field_name: '容量', extra_config: { summary_visible: 1 } }] }])
    await state.loadSelectedTemplateSchema()
    completeOld(envelope(1))
    await oldRequest
    assert.equal(state.selectedTemplate.value.id, 2)
    assert.equal(state.summaryCount.value, 1)
    assert.equal(state.schemaWarning.value, '')
    assert.equal(state.schemaLoading.value, false)
    response = async () => envelope(999)
    await state.loadSelectedTemplateSchema()
    assert.match(state.schemaError.value, /当前模板/)
    assert.equal(state.fieldGroups.value.length, 0)
    console.log('PASS: network failure, malformed response, missing content, retry, stale response isolation, template identity and save guard')
}
run().catch(error => { console.error(error); process.exitCode = 1 })
