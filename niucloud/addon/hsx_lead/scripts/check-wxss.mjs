import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { spawnSync } from 'node:child_process'

// Run after a real uni-app mp-weixin build. Does not launch the IDE, preview or upload.
const args = process.argv.slice(2)
if (args.length !== 2 || args[0] !== '--dir') throw new Error('Usage: node scripts/check-wxss.mjs --dir /path/to/mp-weixin')
const root = path.resolve(args[1])
const compiler = process.env.WXSS_COMPILER || '/Applications/wechatwebdevtools.app/Contents/Resources/app.asar.unpacked/node_modules/wcc-exec/wcsc'
if (!fs.existsSync(compiler)) throw new Error('Set WXSS_COMPILER to the local WeChat wcsc executable; native compilation was not verified')
const files = []
function walk(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const file = path.join(dir, entry.name)
        if (entry.isDirectory()) walk(file)
        else if (entry.isFile() && entry.name.endsWith('.wxss')) files.push('./' + path.relative(root, file).split(path.sep).join('/'))
    }
}
walk(root)
const targets = files.filter(file => file === './app.wxss' || file.startsWith('./addon/hsx_lead/')).sort()
if (!targets.length) throw new Error('No compiled hsx_lead WXSS found: ' + root)
function dependencies(file, seen = new Set()) {
    if (seen.has(file)) return seen
    seen.add(file)
    const source = fs.readFileSync(path.join(root, file), 'utf8')
    for (const match of source.matchAll(/@import\s+(?:"([^"]+)"|'([^']+)')/g)) {
        const imported = './' + path.posix.normalize(path.posix.join(path.posix.dirname(file), match[1] || match[2])).replace(/^\.\//, '')
        if (!files.includes(imported)) throw new Error('Missing compiled import ' + imported + ' in ' + file)
        dependencies(imported, seen)
    }
    return seen
}
const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'hsx-lead-wxss-'))
const failures = []
try {
    for (const [index, file] of targets.entries()) {
        // wcsc resolves @import from an explicit list; unrelated plugin styles are not dependencies.
        const imports = [...dependencies(file)].filter(imported => imported !== file)
        const result = spawnSync(compiler, ['-lc', '-pc', '1', '-o', path.join(temp, index + '.js'), file, ...imports], {
            cwd: root, encoding: 'utf8', timeout: 15000, maxBuffer: 1024 * 1024,
        })
        if (result.error || result.status !== 0) failures.push(file + ': ' + (result.error?.message || result.stderr || result.stdout || 'compiler failed').trim())
    }
} finally {
    fs.rmSync(temp, { recursive: true, force: true })
}
if (failures.length) throw new Error('Native WXSS compilation failed:\n' + failures.join('\n'))
console.log(JSON.stringify({ passed: true, wxss_files: targets.length, scope: 'Actual local WeChat parser; plugin artifact syntax only, no runtime or upload' }))
