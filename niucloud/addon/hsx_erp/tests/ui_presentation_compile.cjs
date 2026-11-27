// Read-only syntax regression check; does not build, publish, or connect to a database.
const fs = require('node:fs')
const path = require('node:path')
const { createRequire } = require('node:module')

const workspace = path.resolve(__dirname, '../../../..')
let checked = 0
const failures = []

function filesIn(directory) {
    return fs.readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
        const file = path.join(directory, entry.name)
        return entry.isDirectory() ? filesIn(file) : /\.(vue|ts)$/.test(file) && !file.endsWith('.d.ts') ? [file] : []
    })
}

for (const project of ['admin', 'site-uniapp']) {
    const projectRequire = createRequire(path.join(workspace, project, 'package.json'))
    const compiler = projectRequire('@vue/compiler-sfc')
    const ts = projectRequire('typescript')
    for (const file of filesIn(path.join(workspace, project, 'src/addon/hsx_erp'))) {
        const relative = path.relative(workspace, file)
        // Vendored chart source contains platform-specific script blocks and must be compiled by uni-app.
        if (relative.includes('/components/qiun-data-charts/components/qiun-data-charts/')) continue
        try {
            const source = fs.readFileSync(file, 'utf8')
            let script = source
            if (file.endsWith('.vue')) {
                const parsed = compiler.parse(source, { filename: file })
                if (parsed.errors.length) throw new Error(parsed.errors.map(String).join('; '))
                const descriptor = parsed.descriptor
                const compiled = descriptor.script || descriptor.scriptSetup
                    ? compiler.compileScript(descriptor, { id: relative })
                    : undefined
                script = compiled?.content || ''
                if (descriptor.template) {
                    const template = compiler.compileTemplate({
                        source: descriptor.template.content,
                        filename: file,
                        id: relative,
                        compilerOptions: { bindingMetadata: compiled?.bindings, expressionPlugins: ['typescript'] },
                    })
                    if (template.errors.length) throw new Error(template.errors.map(String).join('; '))
                }
            }
            const result = ts.transpileModule(script, {
                fileName: relative.replace(/\.vue$/, '.ts'),
                reportDiagnostics: true,
                compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.ESNext },
            })
            const errors = (result.diagnostics || []).filter(item => item.category === ts.DiagnosticCategory.Error)
            if (errors.length) throw new Error(errors.map(item => ts.flattenDiagnosticMessageText(item.messageText, '\n')).join('; '))
            checked++
        } catch (error) {
            failures.push(`${relative}: ${error.message}`)
        }
    }
}

if (failures.length) {
    console.error(failures.join('\n'))
    console.error(`[FAIL] ERP UI syntax: ${failures.length} failures, ${checked} files passed`)
    process.exitCode = 1
} else {
    console.log(`[PASS] ERP UI syntax: ${checked} Vue/TypeScript files (admin + mobile)`)
}
