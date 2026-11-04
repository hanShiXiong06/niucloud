import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { createRequire } from 'node:module'

// Source-only acceptance after uninstall: never remount or install the plugin.
const plugin = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const workspace = path.resolve(plugin, '../../..')
const require = createRequire(path.join(workspace, 'admin/package.json'))
const compiler = require('@vue/compiler-sfc'), ts = require('typescript'), sass = require('sass')
let sfc = 0, scripts = 0, styles = 0, imports = 0
const files = directory => fs.readdirSync(directory, { withFileTypes: true }).flatMap(entry => entry.isDirectory() ? files(path.join(directory, entry.name)) : [path.join(directory, entry.name)])
function checkImports(source, filename) {
    for (const match of source.matchAll(/(?:from\s*|import\s*)['"]([^'"]+)['"]/g)) {
        const specifier = match[1]
        if (!specifier.startsWith('.')) continue
        const target = path.resolve(path.dirname(filename), specifier)
        assert.ok([target, target + '.ts', target + '.js', path.join(target, 'index.ts')].some(candidate => fs.existsSync(candidate)), `Missing relative import ${specifier} in ${path.relative(plugin, filename)}`)
        imports++
    }
}
for (const directory of ['admin', 'uni-app']) {
    for (const filename of files(path.join(plugin, directory))) {
        const source = fs.readFileSync(filename, 'utf8')
        if (filename.endsWith('.ts')) {
            const result = ts.transpileModule(source, { fileName: filename, reportDiagnostics: true, compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.ESNext } })
            assert.equal((result.diagnostics || []).filter(row => row.category === ts.DiagnosticCategory.Error).length, 0, filename)
            checkImports(source, filename); scripts++
        } else if (filename.endsWith('.vue')) {
            const { descriptor, errors } = compiler.parse(source, { filename })
            assert.deepEqual(errors, [], filename)
            const script = descriptor.scriptSetup ? compiler.compileScript(descriptor, { id: `source-${sfc}` }) : null
            const result = compiler.compileTemplate({ id: `source-${sfc}`, source: descriptor.template?.content || '', filename, compilerOptions: { bindingMetadata: script?.bindings, expressionPlugins: ['typescript'] } })
            assert.deepEqual(result.errors, [], filename)
            checkImports(descriptor.scriptSetup?.content || descriptor.script?.content || '', filename)
            for (const style of descriptor.styles) {
                const compiled = style.lang === 'scss' ? sass.compileString(style.content, { loadPaths: [path.dirname(filename)] }).css : style.content
                assert.ok(style.scoped || !/:deep\s*\(/.test(compiled), `Unscoped :deep selector will not render correctly: ${filename}`)
                const result = compiler.compileStyle({ source: compiled, filename, id: `source-${sfc}`, scoped: style.scoped })
                assert.deepEqual(result.errors, [], filename)
                assert.ok(!/:deep\s*\(/.test(result.code), `Uncompiled deep selector in ${filename}`); styles++
            }
            sfc++
        } else if (filename.endsWith('.css')) {
            assert.deepEqual(compiler.compileStyle({ source, filename, id: 'source-css' }).errors, [], filename); styles++
        }
    }
}
const metadata = JSON.parse(fs.readFileSync(path.join(plugin, 'info.json'), 'utf8'))
assert.equal(metadata.version, '0.0.1')
assert.equal(fs.readFileSync(path.join(plugin, 'install.sql'), 'utf8'), fs.readFileSync(path.join(plugin, 'sql/install.sql'), 'utf8'), 'Both installer entry points must use the same fresh schema')
assert.ok(!fs.existsSync(path.join(plugin, 'app/support/Schema.php')), 'No historical schema migrator')
console.log(JSON.stringify({ passed: true, sfc, scripts, styles, relative_imports: imports, version: metadata.version, scope: 'Plugin source only; no install, API or database action' }))
