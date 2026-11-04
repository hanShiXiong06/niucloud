import { readFile, readdir, mkdir, writeFile, lstat, realpath, rename, rm } from 'node:fs/promises'
import { createHash, randomUUID } from 'node:crypto'
import { spawnSync } from 'node:child_process'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

// A clean source installer, not an upgrader, compiler or database installer.
// Only this plugin's installation sources are read. Mounted application sources
// may not exist after uninstall and are deliberately not a packaging dependency.
const plugin = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const root = path.resolve(plugin, '../../..')
const prefix = 'niucloud/addon/hsx_lead'
if (path.join(root, prefix) !== plugin) throw new Error('Unexpected plugin location')
const args = process.argv.slice(2)
if (args.some(arg => arg !== '--check')) throw new Error('Usage: node scripts/package.mjs [--check]')

const info = JSON.parse(await readFile(path.join(plugin, 'info.json'), 'utf8'))
const version = info.version
if (info.key !== 'hsx_lead' || typeof version !== 'string' || !/^\d+\.\d+\.\d+$/.test(version)) {
    throw new Error('Invalid plugin identity or version')
}
const releaseDate = process.env.HSX_LEAD_RELEASE_DATE || new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Shanghai', year: 'numeric', month: '2-digit', day: '2-digit'
}).format(new Date()).replaceAll('-', '')
if (!/^\d{8}$/.test(releaseDate)) throw new Error('Release date must be YYYYMMDD')

const runtimeDirectories = ['app', 'admin', 'uni-app', 'package', 'resource']
const installationFiles = ['Addon.php', 'info.json', 'README.md', 'install.sql', 'uninstall.sql', 'sql/install.sql', 'sql/uninstall.sql']
const guideFiles = ['docs/COOPERATION_GUIDE.md', 'docs/ERP_INTEGRATION.md', 'docs/CONFIGURATION.md', 'docs/DELIVERY_EXPERIENCE.md', 'docs/PROMOTION_INTEGRATION.md', 'docs/TEST_REPORT.md']
const bannedDirectories = new Set(['.git', 'node_modules', 'dist', 'build', 'output', 'tests', 'scripts', 'runtime', '.vite', '__MACOSX'])
const allowedExtensions = new Set(['.php', '.vue', '.ts', '.js', '.mjs', '.json', '.css', '.scss', '.sass', '.less', '.png', '.svg', '.jpg', '.jpeg', '.webp', '.gif', '.ico', '.woff', '.woff2', '.ttf'])
const files = []

function safeName(relative) {
    if (relative.split('/').some(name => !name || name === '.' || name === '..' || /[\r\n\\\x00]/.test(name))) {
        throw new Error('Unsafe release path')
    }
    if (/\.env(?:\.|$)|curl\.txt|\.(pem|key|zip|log|bak|tmp|sqlite|db)$|~$/i.test(relative)) {
        throw new Error('Unexpected release file: ' + relative)
    }
}

async function inspect(relative, expectedDirectory = false) {
    safeName(relative)
    const absolute = path.join(plugin, relative)
    const stats = await lstat(absolute)
    if (stats.isSymbolicLink()) throw new Error('Symlink rejected: ' + relative)
    if (expectedDirectory && !stats.isDirectory()) throw new Error('Missing runtime directory: ' + relative)
    if (stats.isDirectory()) {
        const entries = await readdir(absolute, { withFileTypes: true })
        for (const entry of entries) {
            if (entry.name === '.DS_Store') continue
            if (entry.name.startsWith('.') || bannedDirectories.has(entry.name)) {
                throw new Error('Unexpected runtime directory or file: ' + path.posix.join(relative, entry.name))
            }
            await inspect(path.posix.join(relative, entry.name))
        }
    } else if (stats.isFile()) {
        if (!installationFiles.includes(relative) && !guideFiles.includes(relative) && !allowedExtensions.has(path.extname(relative).toLowerCase())) {
            throw new Error('Unexpected runtime extension: ' + relative)
        }
        files.push(relative)
    } else throw new Error('Unsupported release object: ' + relative)
}

for (const folder of runtimeDirectories) await inspect(folder, true)
for (const file of [...installationFiles, ...guideFiles]) await inspect(file)
files.sort()
if (new Set(files).size !== files.length) throw new Error('Duplicate release source')

const pageSource = await readFile(path.join(plugin, 'package/uni-app-pages.php'), 'utf8')
const mobilePages = ['pages/workbench/index', 'pages/stores/index', 'pages/wallet/index', 'pages/profile/index']
const declaredPages = [...pageSource.matchAll(/"path"\s*:\s*"([^"]+)"/g)].map(match => match[1])
if (JSON.stringify([...declaredPages].sort()) !== JSON.stringify([...mobilePages].sort())) {
    throw new Error('The installer must register exactly the four current mobile pages')
}
for (const page of mobilePages) if (!files.includes('uni-app/' + page + '.vue')) {
    throw new Error('Registered mobile page is missing: ' + page)
}
for (const name of ['install.sql', 'uninstall.sql']) {
    const [entry, canonical] = await Promise.all([readFile(path.join(plugin, name)), readFile(path.join(plugin, 'sql', name))])
    if (!entry.equals(canonical)) throw new Error('SQL installation entries differ: ' + name)
}

const content = new Map()
const fileManifest = []
for (const relative of files) {
    const buffer = await readFile(path.join(plugin, relative))
    const source = buffer.toString('utf8')
    if (/eyJ[A-Za-z0-9_-]{40,}\.[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}/.test(source)
        || /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/.test(source)) {
        throw new Error('Possible inline authentication secret: ' + relative)
    }
    content.set(relative, buffer)
    fileManifest.push({ path: prefix + '/' + relative, bytes: buffer.length, sha256: createHash('sha256').update(buffer).digest('hex') })
}
const manifest = {
    plugin: 'hsx_lead', version, package_type: 'fresh-install-source', built_at: new Date().toISOString(),
    archive_root: prefix, framework_files: [],
    source_directories: runtimeDirectories.map(directory => prefix + '/' + directory),
    mobile_pages: mobilePages.map(page => 'addon/hsx_lead/' + page),
    generated_configuration_files: [{
        path: 'uni-app/src/pages.json', source: prefix + '/package/uni-app-pages.php',
        reason: 'Four mobile pages are registered by the framework plugin page compiler. Do not overlay the shared page manifest.'
    }],
    installation: [
        'Copy the plugin directory into the target project and use the framework application installation and site authorization workflow.',
        'Integrate plugin admin/mobile sources and resources with the framework compiler, then build and publish the frontends.',
        'Configure site business rules, payment, notification, withdrawal and the existing scheduler before accepting real business.'
    ],
    excluded: ['mounted frontend directories', 'framework source', 'tests', 'development scripts', 'temporary tools', 'historical upgrades', 'credentials', 'database data', 'compiled frontend output'],
    files: fileManifest
}
if (args.includes('--check')) {
    console.log(JSON.stringify({ version, package_type: manifest.package_type, source_files: files.length, mobile_pages: mobilePages.length, framework_files: 0, writes: false }))
} else {
    const outputRoot = path.join(root, 'output')
    await mkdir(outputRoot, { recursive: true })
    if (await realpath(outputRoot) !== outputRoot) throw new Error('Symlink output directory rejected')
    const output = path.join(outputRoot, 'hsx-lead-' + version + '-release-' + releaseDate)
    await mkdir(output, { recursive: true })
    if (await realpath(output) !== output) throw new Error('Symlink release directory rejected')
    const nonce = randomUUID()
    const stage = path.join(output, '.source-' + nonce)
    const temporary = path.join(output, '.package-' + nonce + '.zip')
    const archive = path.join(output, 'hsx-lead-' + version + '-source.zip')
    try {
        // Archive the exact bytes that were hashed, not files that may change
        // between manifest generation and zip reading the working tree.
        for (const [relative, buffer] of content) {
            const target = path.join(stage, prefix, relative)
            await mkdir(path.dirname(target), { recursive: true })
            await writeFile(target, buffer, { flag: 'wx' })
        }
        const result = spawnSync('zip', ['-q', '-X', temporary, '-@'], {
            cwd: stage, input: fileManifest.map(file => file.path).join('\n') + '\n', encoding: 'utf8'
        })
        if (result.error || result.status !== 0) throw new Error('Archive creation failed; zip command must be available')
        const zip = await readFile(temporary)
        const hash = createHash('sha256').update(zip).digest('hex')
        await rename(temporary, archive)
        await writeFile(path.join(output, 'manifest.json'), JSON.stringify({ ...manifest, archive: { file: path.basename(archive), bytes: zip.length, sha256: hash } }, null, 2) + '\n')
        await writeFile(path.join(output, 'SHA256SUMS'), hash + '  ' + path.basename(archive) + '\n')
        console.log(JSON.stringify({ archive, bytes: zip.length, source_files: files.length, sha256: hash, mobile_pages: mobilePages.length, framework_files: 0 }))
    } finally {
        await rm(stage, { recursive: true, force: true })
        await rm(temporary, { force: true })
    }
}
