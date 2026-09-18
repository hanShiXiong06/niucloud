// 只读前端定向编译检查，不运行 publish.cjs，不覆盖打包目录。
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '..')
const compiler = require(path.join(root, 'admin/node_modules/@vue/compiler-sfc'))
const ts = require(path.join(root, 'admin/node_modules/typescript'))
const files = [
    'niucloud/addon/phone_shop/admin/views/intake/list.vue',
    'niucloud/addon/phone_shop/admin/views/intake/components/IntakeMaterialDrawer.vue',
    'niucloud/addon/phone_shop/admin/views/intake/components/IntakeBuildDialog.vue',
    'niucloud/addon/hsx_erp/admin/views/erp/config/rules.vue',
    'niucloud/addon/hsx_erp/admin/views/erp/stock/list.vue',
    'site-uniapp/src/addon/hsx_erp/pages/stock/detail.vue'
]
for (const file of files) {
    const filename = path.join(root, file)
    const { descriptor, errors } = compiler.parse(fs.readFileSync(filename, 'utf8'), { filename })
    if (errors.length) throw errors[0]
    const script = compiler.compileScript(descriptor, { id: 'erp-mall-smoke' })
    const diagnostics = ts.transpileModule(script.content, {
        compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.ESNext }, reportDiagnostics: true, fileName: filename + '.ts'
    }).diagnostics || []
    if (diagnostics.length) throw new Error(diagnostics.map(item => ts.flattenDiagnosticMessageText(item.messageText, '\n')).join('\n'))
    const template = compiler.compileTemplate({ source: descriptor.template.content, filename, id: 'erp-mall-smoke', compilerOptions: { bindingMetadata: script.bindings } })
    if (template.errors.length) throw template.errors[0]
    const src = path.join(root, file.startsWith('site-uniapp/') ? 'site-uniapp/src' : 'admin/src')
    for (const style of descriptor.styles) {
        const result = compiler.compileStyle({ source: style.content, filename, id: 'data-v-smoke', preprocessLang: style.lang,
            preprocessOptions: { importer: url => url.startsWith('@/') ? { file: path.join(src, url.slice(2)) } : null } })
        if (result.errors.length) throw result.errors[0]
    }
    console.log('PASS Vue script/template/style: ' + file)
}
const mirrors = [
    ['phone_shop', 'api/device_intake.ts'], ['phone_shop', 'views/intake/list.vue'],
    ['phone_shop', 'views/intake/components/IntakeMaterialDrawer.vue'],
    ['phone_shop', 'views/intake/components/IntakeBuildDialog.vue'],
    ['hsx_erp', 'views/erp/config/rules.vue'], ['hsx_erp', 'views/erp/stock/list.vue']
]
for (const [addon, file] of mirrors) {
    if (!fs.readFileSync(path.join(root, `niucloud/addon/${addon}/admin/${file}`)).equals(fs.readFileSync(path.join(root, `admin/src/addon/${addon}/${file}`)))) {
        throw new Error('Plugin/source mismatch: ' + addon + '/' + file)
    }
}
console.log('PASS ' + mirrors.length + ' plugin/source mirrors. No publishing or database writes.')
