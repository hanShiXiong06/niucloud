import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'

const plugin = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const require = createRequire(path.resolve(plugin, '../../../uni-app/package.json'))
const ts = require('typescript'), cache = new Map()

/** Execute the actual plugin-owned pure utility, never a replacement implementation. */
export function loadMobileUtility(name) {
    if (!/^[a-z0-9-]+$/.test(name)) throw new Error('Only named local mobile utilities may be loaded')
    if (cache.has(name)) return cache.get(name)
    const filename = path.join(plugin, 'uni-app/utils', name + '.ts')
    const source = fs.readFileSync(filename, 'utf8')
    const js = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    const module = { exports: {} }
    vm.runInNewContext('(function(require,module,exports){' + js + '})', { Date, Math, Number, String, Map, Set, JSON, console })(key => {
        if (!/^\.\/[a-z0-9-]+$/.test(key)) throw new Error('Unexpected pure utility dependency: ' + key)
        return loadMobileUtility(key.slice(2))
    }, module, module.exports)
    cache.set(name, module.exports)
    return module.exports
}
