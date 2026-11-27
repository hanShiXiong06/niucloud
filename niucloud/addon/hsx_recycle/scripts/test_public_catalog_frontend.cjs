// 仅编译本次资料库页面，并检查插件源文件与实际管理端副本一致；不构建或发布。
const fs = require('node:fs')
const path = require('node:path')
const { createRequire } = require('node:module')
const root = path.resolve(__dirname, '../../../..')
const fromAdmin = createRequire(path.join(root, 'admin/package.json'))
const vue = fromAdmin('@vue/compiler-sfc')
const ts = fromAdmin('typescript')
const files = [
    'api/printer_template.ts', 'views/printer_template/list.vue', 'views/printer_template/visual_editor.vue',
    'views/printer_template/public_list.vue', 'views/printer_template/public_edit.vue',
    'api/device_catalog.ts', 'composables/useDeviceCatalog.ts',
    'components/device-entry/CatalogSourceNotice.vue',
    'components/device-entry/QuickAddModelDialog.vue',
    'components/device-entry/CheckTemplateConfigDrawer.vue',
    'views/device_catalog/platform.vue', 'views/device_catalog/site.vue', 'views/device_catalog/sites.vue',
    'views/recycle_device_model_dict/list.vue',
    'views/check/template.vue', 'views/check/catalog.vue', 'views/check/severity.vue'
]
for (const file of files) {
    const canonical = path.join(root, 'niucloud/addon/hsx_recycle/admin', file)
    const source = fs.readFileSync(canonical, 'utf8')
    const mirror = fs.readFileSync(path.join(root, 'admin/src/addon/hsx_recycle', file), 'utf8')
    if (source !== mirror) throw new Error(`管理端副本不一致：${file}`)
    let script = source
    if (file.endsWith('.vue')) {
        const { descriptor, errors } = vue.parse(source, { filename: canonical })
        if (errors.length) throw new Error(`${file}: ${errors.join('\n')}`)
        const compiled = vue.compileScript(descriptor, { id: 'public-catalog-test' })
        script = compiled.content
        if (descriptor.template) {
            const template = vue.compileTemplate({
                source: descriptor.template.content, filename: canonical, id: 'public-catalog-test',
                compilerOptions: { bindingMetadata: compiled.bindings, expressionPlugins: ['typescript'] }
            })
            if (template.errors.length) throw new Error(`${file}: ${template.errors.join('\n')}`)
        }
        for (const style of descriptor.styles) {
            if (style.lang && style.lang !== 'css') continue
            const result = vue.compileStyle({ source: style.content, filename: canonical, id: 'public-catalog-test', scoped: style.scoped })
            if (result.errors.length) throw new Error(`${file}: ${result.errors.join('\n')}`)
        }
    }
    const result = ts.transpileModule(script, {
        fileName: file.replace(/\.vue$/, '.ts'), reportDiagnostics: true,
        compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext }
    })
    const errors = (result.diagnostics || []).filter(item => item.category === ts.DiagnosticCategory.Error)
    if (errors.length) throw new Error(`${file}: ${errors.map(item => ts.flattenDiagnosticMessageText(item.messageText, '\n')).join('\n')}`)
    console.log(`PASS: ${file}`)
}
console.log(`PASS ${files.length} frontend files: mirror parity, Vue/TS syntax and plain CSS compilation; not full typecheck or browser E2E`)
