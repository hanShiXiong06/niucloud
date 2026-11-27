import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'

/** Execute the current plugin API wrappers with an in-memory transport only. */
export function createAdminApiSourceFixture(root, ts) {
    const file = path.join(root, 'niucloud/addon/hsx_lead/admin/api/index.ts')
    const source = fs.readFileSync(file, 'utf8')
    const js = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    const calls = [], transport = {}
    for (const method of ['get', 'post', 'put']) {
        transport[method] = (...args) => {
            calls.push({ method, args: structuredClone(args) })
            return Promise.resolve({ data: { synthetic: true } })
        }
    }
    const module = { exports: {} }
    vm.runInNewContext(`(function(require,module,exports){${js}\n})`)(name => {
        if (name === '@/utils/request') return { default: transport }
        throw Error(`Unexpected API source dependency: ${name}`)
    }, module, module.exports)
    return { api: module.exports, calls }
}
