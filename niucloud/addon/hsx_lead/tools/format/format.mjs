import { lstat, readFile, realpath, writeFile } from 'node:fs/promises'
import { dirname, extname, isAbsolute, relative, resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'
import glob from 'fast-glob'
import prettier from 'prettier'
import { formatVue } from './vue-layout.mjs'

const entry = fileURLToPath(import.meta.url)
const pluginRoot = resolve(dirname(entry), '../..')
const supported = new Set(['.vue', '.ts', '.css', '.scss'])
const globOptions = { onlyFiles: true, absolute: true, followSymbolicLinks: false, ignore: ['**/node_modules/**', '**/.git/**'] }

function within(root, file) {
    const name = relative(root, file)
    return name !== '..' && !name.startsWith('..' + sep) && !isAbsolute(name)
}

async function findFiles(patterns, root, cwd) {
    const paths = new Set()
    for (const pattern of patterns) {
        const path = resolve(cwd, pattern)
        const stat = await lstat(path).catch(error => {
            if (error.code !== 'ENOENT' && error.code !== 'ENOTDIR') throw error
            return null
        })
        let matches
        if (stat) {
            if (!within(root, await realpath(path)) || stat.isSymbolicLink()) throw new Error(`Not a local plugin path: ${pattern}`)
            if (stat.isDirectory()) {
                matches = await glob('**/*.{vue,ts,css,scss}', { ...globOptions, cwd: path })
            } else if (stat.isFile()) {
                matches = [path]
            } else throw new Error(`Not a regular file: ${pattern}`)
        } else {
            if (!glob.isDynamicPattern(pattern)) throw new Error(`Path not found: ${pattern}`)
            matches = await glob(pattern, { ...globOptions, cwd })
            if (!matches.length) throw new Error(`No matches: ${pattern}`)
        }
        for (const file of matches) {
            if (!supported.has(extname(file))) continue
            const canonical = await realpath(file)
            if (!within(root, canonical)) throw new Error(`Path outside plugin: ${file}`)
            const local = relative(root, canonical).split(sep)
            if (!['uni-app', 'admin'].includes(local[0]) || (local[0] === 'admin' && extname(canonical) === '.scss')) continue
            paths.add(canonical)
        }
    }
    return [...paths].sort()
}

export async function formatFiles(mode, patterns, { root = pluginRoot, cwd = process.cwd(), report = console.log } = {}) {
    if (!['check', 'write'].includes(mode) || !patterns.length) throw new Error('Usage: npm run check|write -- <directory, file or quoted glob> ...')
    root = await realpath(root)
    const files = await findFiles(patterns, root, cwd)
    const changes = new Map()
    let checked = 0
    for (const file of files) {
        const { ignored } = await prettier.getFileInfo(file, { ignorePath: resolve(root, '.prettierignore'), withNodeModules: false })
        if (ignored) continue
        const config = await prettier.resolveConfig(file, { config: resolve(root, '.prettierrc.json'), editorconfig: false })
        const options = { ...config, filepath: file }
        const source = await readFile(file, 'utf8')
        const output = extname(file) === '.vue' ? await formatVue(source, options) : await prettier.format(source, options)
        if (output !== source) changes.set(file, output)
        checked++
    }
    // No partial write for a parse or convergence failure on a later file in the batch.
    for (const [file, output] of changes) {
        if (mode === 'write') await writeFile(file, output)
        report(relative(root, file))
    }
    report(`${mode}: ${checked} checked, ${changes.size} ${mode === 'check' ? 'would change' : 'changed'}`)
    return { checked, changed: changes.size }
}

if (process.argv[1] && resolve(process.argv[1]) === entry) {
    const [mode, ...patterns] = process.argv.slice(2)
    try {
        const result = await formatFiles(mode, patterns)
        if (mode === 'check' && result.changed) process.exitCode = 1
    } catch (error) {
        console.error(error.message)
        process.exitCode = 2
    }
}
