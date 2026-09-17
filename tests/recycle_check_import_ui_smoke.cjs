// 定向编译 + 运行真实页面 setup；接口替身不上传文件、不修改业务数据库。
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '..')
const compiler = require(path.join(root, 'admin/node_modules/@vue/compiler-sfc'))
const ts = require(path.join(root, 'admin/node_modules/typescript'))
const vue = require(path.join(root, 'admin/node_modules/vue'))
const filename = path.join(root, 'admin/src/addon/hsx_recycle/views/check/catalog.vue')
const source = fs.readFileSync(filename, 'utf8')
assert.equal(source, fs.readFileSync(path.join(root, 'niucloud/addon/hsx_recycle/admin/views/check/catalog.vue'), 'utf8'))
const { descriptor, errors } = compiler.parse(source, { filename })
assert.deepEqual(errors, [])
const script = compiler.compileScript(descriptor, { id: 'catalog-import' })
const compiled = ts.transpileModule(script.content, {
  fileName: filename + '.ts', reportDiagnostics: true,
  compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.CommonJS }
})
assert.deepEqual(compiled.diagnostics || [], [])
assert.deepEqual(compiler.compileTemplate({ source: descriptor.template.content, filename, id: 'catalog-import',
  compilerOptions: { bindingMetadata: script.bindings, expressionPlugins: ['typescript'] } }).errors, [])
for (const style of descriptor.styles) assert.deepEqual(compiler.compileStyle({ source: style.content, filename, id: 'catalog-import' }).errors, [])
console.log('PASS Vue/TypeScript/模板/样式定向编译与插件发布镜像一致')

function mount(chunks, uploadError) {
  let calls = 0
  const messages = { success: [], error: [] }
  const sandbox = {
    exports: {}, console,
    FormData: class { append() {} },
    require: name => {
      if (name === 'vue') return { ...vue, onMounted() {} }
      if (name.endsWith('hsx_components/core')) return { useFeedback: () => ({
        success: msg => messages.success.push(msg), error: msg => messages.error.push(msg)
      }) }
      if (name.endsWith('api/check_catalog')) return {
        uploadCheckCatalog: async () => {
          if (uploadError) throw uploadError
          return { data: { batch_id: 3, token: 'fixture.xlsx', total_rows: 13349 } }
        },
        importChunkCheckCatalog: async () => {
          const chunk = chunks[calls++]
          if (!chunk) throw new Error('不应继续请求')
          if (chunk.throw) throw chunk.throw
          return { data: chunk }
        }
      }
      return {}
    }
  }
  vm.runInNewContext(compiled.outputText, sandbox)
  const state = sandbox.exports.default.setup({}, { expose() {} })
  return { state, messages, calls: () => calls }
}
const event = () => ({ target: { value: 'fixture.xlsx', files: [{ name: 'fixture.xlsx' }] } })
async function main() {
  let test = mount([{ done: true, next_offset: 13350, templates: 0, bindings: 0, rows_done: 0, skipped_exists: 0 }])
  await test.state.onFileChange(event())
  assert.equal(test.state.imp.done, false)
  assert.equal(test.state.imp.running, false)
  assert.equal(test.messages.success.length, 0)
  assert.match(test.state.imp.error, /未读取到有效检测项/)
  console.log('PASS 用户提供的零条 done=true 返回不再被误报成功')

  test = mount([{ done: false, next_offset: 0, rows_done: 0 }])
  await test.state.onFileChange(event())
  assert.equal(test.calls(), 1)
  assert.match(test.state.imp.error, /进度未推进/)
  console.log('PASS 游标不前进停止请求，不会无限循环')

  test = mount([], { msg: '检测表格式不匹配：请到选项级别导入' })
  await test.state.onFileChange(event())
  assert.equal(test.state.imp.error, '检测表格式不匹配：请到选项级别导入')
  assert.equal(test.messages.success.length, 0)
  assert.equal(test.state.imp.running, false)
  console.log('PASS 后端具体错误在弹窗保留，不被“请重试”吞掉')

  test = mount([{ done: true, next_offset: 3, templates: 0, bindings: 0, rows_done: 1, skipped_exists: 1 }])
  await test.state.onFileChange(event())
  assert.equal(test.state.imp.done, true)
  assert.match(test.state.imp.resultMessage, /已有模板.*没有新增/)
  assert.equal(test.state.impPercent.value, 100)
  console.log('PASS 合法重复追加提示已跳过，不误报空导入')

  test = mount([
    { done: false, next_offset: 3, templates: 1, bindings: 1, rows_done: 1 },
    { done: true, next_offset: 13351, templates: 2, bindings: 2, rows_done: 2 }
  ])
  await test.state.onFileChange(event())
  assert.equal(test.calls(), 2)
  assert.equal(test.state.imp.done, true)
  assert.equal(test.state.imp.rows, 2)
  assert.equal(test.state.impPercent.value, 100)
  assert.equal(test.state.imp.scanned, 13349)
  assert.match(test.state.imp.resultMessage, /生成 2 个模板，绑定 2 个型号/)
  console.log('PASS 稀疏工作表成功时进度为100%，有效处理行数不冒充扫描行数')

  test = mount([{ done: true, next_offset: 3, templates: 1, bindings: 0, rows_done: 1 }])
  await test.state.onFileChange(event())
  assert.match(test.state.imp.resultMessage, /尚未绑定型号.*产品ID/)
  console.log('PASS 已生成但未绑定型号时提供下一步核对提示')

  assert.match(source, /label="失败原因"/)
  assert.match(source, /imp.error \? 'exception'/)
  assert.match(source, /role="alert"/)
  assert.match(source, /级别标注表请到“选项级别”页面导入/)
  console.log('PASS 批次失败原因、红色进度状态及两个入口用途提示存在')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
