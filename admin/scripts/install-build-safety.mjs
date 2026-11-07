import fs from 'node:fs'
import path from 'node:path'
import crypto from 'node:crypto'
import { fileURLToPath, pathToFileURL } from 'node:url'

const admin = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
export const safeScripts = {
    build: 'node scripts/build-safe.mjs',
    'build:check': 'node scripts/build-safe.mjs --check',
    'build:compile': 'node scripts/build-safe.mjs --compile-only',
    'test:build-safety': 'node --test tests/build-policy.test.mjs tests/build-safe.test.mjs tests/install-build-safety.test.mjs tests/publish-safe.test.cjs',
}

export function configureScripts(packageFile = path.join(admin, 'package.json')) {
    const root = path.dirname(packageFile), state = path.join(root, '.build-safe')
    if (fs.lstatSync(packageFile).isSymbolicLink()) throw Error('package.json 不允许是符号链接')
    if (fs.existsSync(state) && (fs.lstatSync(state).isSymbolicLink() || !fs.statSync(state).isDirectory())) throw Error('构建状态目录不是普通目录')
    if (fs.existsSync(path.join(state, 'build.lock'))) throw Error('存在构建锁，请先结束该构建并核实状态；安装未执行')
    const original = fs.readFileSync(packageFile, 'utf8'), metadata = JSON.parse(original)
    const lifecycle = ['prebuild', 'postbuild', 'prebuild:check', 'postbuild:check', 'prebuild:compile', 'postbuild:compile'].filter(key => metadata.scripts?.[key])
    if (lifecycle.length) throw Error(`检测到会在保护边界外执行的 npm 生命周期命令：${lifecycle.join(', ')}；未覆盖，请先核对并纳入受限流程`)
    if (metadata.scripts && Object.entries(safeScripts).every(([key, value]) => metadata.scripts[key] === value)) return { changed: false, backup: null }
    metadata.scripts = { ...metadata.scripts, ...safeScripts }
    fs.mkdirSync(state, { recursive: true, mode: 0o700 })
    if (!fs.existsSync(path.join(state, '.gitignore'))) fs.writeFileSync(path.join(state, '.gitignore'), '*\n', { flag: 'wx', mode: 0o600 })
    const backups = path.join(state, 'setup-backups')
    if (fs.existsSync(backups) && (fs.lstatSync(backups).isSymbolicLink() || !fs.statSync(backups).isDirectory())) throw Error('备份目录不是普通目录')
    fs.mkdirSync(backups, { recursive: true, mode: 0o700 })
    const backup = path.join(backups, `package-${new Date().toISOString().replace(/[:.]/g, '-')}-${crypto.randomBytes(4).toString('hex')}.json`)
    fs.writeFileSync(backup, original, { flag: 'wx', mode: 0o600 })
    const temporary = packageFile + '.safe-' + crypto.randomBytes(8).toString('hex')
    try {
        const indentation = original.match(/\n([ \t]+)"/)?.[1] || '    '
        fs.writeFileSync(temporary, JSON.stringify(metadata, null, indentation) + '\n', { flag: 'wx', mode: fs.statSync(packageFile).mode & 0o777 })
        if (fs.readFileSync(packageFile, 'utf8') !== original) throw Error('package.json 同时被其他操作修改，已保留原文件，未覆盖')
        fs.renameSync(temporary, packageFile)
    } finally { if (fs.existsSync(temporary)) fs.unlinkSync(temporary) }
    return { changed: true, backup }
}

if (process.argv[1] && pathToFileURL(path.resolve(process.argv[1])).href === import.meta.url) {
    try {
        for (const file of ['scripts/build-safe.mjs', 'scripts/build-policy.mjs', 'scripts/publish-safe.cjs']) if (!fs.existsSync(path.join(admin, file))) throw Error(`缺少 ${file}，请先完整上传安全构建文件`)
        const result = configureScripts()
        console.log(result.changed ? `[完成] 已更新 package.json 的构建命令，原文件备份：${result.backup}` : '[完成] 构建命令已是安全版本，无需重复修改')
        console.log('保留原有 dependencies、其他 scripts 和 Vite 自定义配置。未编译、未发布。\n下一步：npm run build:check')
    } catch (error) { console.error('[安装未执行] ' + error.message); process.exitCode = 1 }
}
