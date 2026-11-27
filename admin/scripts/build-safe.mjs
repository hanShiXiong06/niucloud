import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import crypto from 'node:crypto'
import { spawn, spawnSync } from 'node:child_process'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { POLICY_VERSION, MiB, NATIVE_ALLOWANCE_MIB, cleanBuildEnv, parseOptions, planResources, systemdProperties, verifyLimits } from './build-policy.mjs'

const script = fileURLToPath(import.meta.url)
const admin = fs.realpathSync(path.resolve(path.dirname(script), '..'))
const state = path.join(admin, '.build-safe')
const prefix = `niucloud-admin-build-${crypto.createHash('sha256').update(admin).digest('hex').slice(0, 10)}-`
const linux = process.platform === 'linux'
const userMode = typeof process.getuid === 'function' && process.getuid() !== 0
const MAX_LOG_BYTES = 32 * MiB

const read = file => fs.readFileSync(file, 'utf8').trim()
const writeJson = (file, value) => {
    const temporary = `${file}.${crypto.randomBytes(5).toString('hex')}.tmp`
    fs.writeFileSync(temporary, JSON.stringify(value, null, 2) + '\n', { flag: 'wx', mode: 0o600 })
    fs.renameSync(temporary, file)
}
function directory(dir) {
    if (!dir.startsWith(admin + path.sep)) throw Error('拒绝在管理端目录以外创建构建状态')
    for (const part of path.relative(admin, dir).split(path.sep)) {
        if (!part || part === '..') throw Error('无效的构建状态路径')
    }
    let current = admin
    for (const part of path.relative(admin, dir).split(path.sep)) {
        current = path.join(current, part)
        if (fs.existsSync(current) && (fs.lstatSync(current).isSymbolicLink() || !fs.statSync(current).isDirectory())) throw Error(`构建状态不能使用符号链接或普通文件：${current}`)
        if (!fs.existsSync(current)) fs.mkdirSync(current, { mode: 0o700 })
    }
}
function command(name) {
    for (const dir of (process.env.PATH || '').split(path.delimiter)) {
        const candidate = path.resolve(dir, name)
        try { fs.accessSync(candidate, fs.constants.X_OK); if (fs.statSync(candidate).isFile()) return candidate } catch {}
    }
    throw Error(`缺少 ${name}，不会降级为无资源保护的服务器构建`)
}
function runSync(file, args) {
    const result = spawnSync(file, args, { encoding: 'utf8', timeout: 10000, maxBuffer: 256 * 1024 })
    if (result.error) throw result.error
    return result
}
function ownCgroup() {
    const row = read('/proc/self/cgroup').split('\n').find(line => line.startsWith('0::'))
    if (!row) throw Error('服务器未启用 cgroup v2，不能提供构建硬限额，未启动编译')
    const relative = row.slice(3)
    if (!relative.startsWith('/') || relative.split('/').includes('..')) throw Error('cgroup 路径无效')
    const root = '/sys/fs/cgroup'
    const dir = path.join(root, relative)
    if (!fs.existsSync(path.join(dir, 'memory.max'))) throw Error('cgroup memory 控制器不可用，未启动编译')
    return { root, dir }
}
function hostMemory() {
    const info = Object.fromEntries(read('/proc/meminfo').split('\n').map(line => { const [key, value] = line.split(':'); return [key, Number.parseInt(value, 10)] }))
    let totalMiB = info.MemTotal / 1024, availableMiB = info.MemAvailable / 1024
    const group = ownCgroup()
    for (let dir = group.dir; dir.startsWith(group.root); dir = path.dirname(dir)) {
        const maximumFile = path.join(dir, 'memory.max')
        if (fs.existsSync(maximumFile)) {
            const maximum = Number(read(maximumFile)), used = Number(read(path.join(dir, 'memory.current')))
            if (Number.isFinite(maximum)) { totalMiB = Math.min(totalMiB, maximum / MiB); availableMiB = Math.min(availableMiB, (maximum - used) / MiB) }
        }
        if (dir === group.root) break
    }
    return { totalMiB, availableMiB }
}
function backingDevices(locations) {
    const devices = new Map()
    for (const location of locations) {
        const actual = fs.realpathSync(location)
        if (Number(fs.statfsSync(actual).type) === 0x01021994) continue // tmpfs is covered by MemoryMax.
        const dev = BigInt(fs.statSync(actual).dev)
        const major = ((dev >> 8n) & 0xfffn) | ((dev >> 32n) & 0xfffff000n)
        const minor = (dev & 0xffn) | ((dev >> 12n) & 0xffffff00n)
        let id = `${major}:${minor}`, sys = `/sys/dev/block/${id}`
        if (!fs.existsSync(sys)) throw Error(`无法为 ${actual} 确认磁盘硬限速设备（可能是容器／网络文件系统），未启动编译`)
        if (fs.existsSync(path.join(sys, 'partition'))) { sys = path.dirname(fs.realpathSync(sys)); id = read(path.join(sys, 'dev')) }
        const name = read(path.join(sys, 'uevent')).split('\n').find(line => line.startsWith('DEVNAME='))?.slice(8)
        if (!name || !/^[a-zA-Z0-9_./-]+$/.test(name) || name.split('/').includes('..')) throw Error('磁盘设备名称无效')
        const devicePath = `/dev/${name}`
        if (!fs.statSync(devicePath).isBlockDevice()) throw Error(`不是有效磁盘设备：${devicePath}`)
        devices.set(id, { id, path: devicePath })
    }
    return [...devices.values()]
}
export function readUnitState(unit, systemctl) {
    if (!unit.startsWith(prefix) || !/^niucloud-admin-build-[a-f0-9]+-[a-f0-9-]+\.service$/.test(unit)) throw Error('锁中的构建单元标识无效，拒绝清理')
    const result = runSync(systemctl, [...(userMode ? ['--user'] : []), 'show', unit, '--property=LoadState', '--property=ActiveState'])
    const values = Object.fromEntries((result.stdout || '').trim().split('\n').map(line => line.split('=')))
    if (values.LoadState === 'not-found') return 'stopped'
    if (['inactive', 'failed'].includes(values.ActiveState)) return 'stopped'
    if (['active', 'activating', 'deactivating', 'reloading'].includes(values.ActiveState)) return 'active'
    throw Error(`无法核实原构建单元状态，保留锁且不启动另一份构建：${result.stderr || unit}`)
}
export function acquireLock(lock, owner, { alive = pid => { try { process.kill(pid, 0); return true } catch (error) { return error.code !== 'ESRCH' } }, unitState } = {}) {
    try { fs.mkdirSync(lock, { mode: 0o700 }) } catch (error) {
        if (error.code !== 'EEXIST') throw error
        if (fs.lstatSync(lock).isSymbolicLink()) throw Error('构建锁不允许是符号链接')
        let previous
        try { previous = JSON.parse(read(path.join(lock, 'owner.json'))) } catch { throw Error('已有构建锁（或锁尚在初始化），未启动第二份编译') }
        if (!Number.isInteger(previous.pid) || previous.pid <= 0 || !previous.token) throw Error('构建锁信息异常，未启动第二份编译')
        if (alive(previous.pid) || previous.workerPid && alive(previous.workerPid)) throw Error(`已有构建任务 PID=${previous.pid}，未启动第二份编译`)
        // A dead client does not prove the systemd-managed worker has stopped.
        if (!previous.unit || !unitState || unitState(previous.unit) !== 'stopped') throw Error('原编译可能仍在运行，保留锁；请先核实日志中的构建单元')
        const recovery = `${lock}.recover`
        try { fs.mkdirSync(recovery, { mode: 0o700 }) } catch { throw Error('另一个进程正在核实旧构建锁，未启动第二份编译') }
        try {
            const current = JSON.parse(read(path.join(lock, 'owner.json')))
            if (current.token !== previous.token || current.pid !== previous.pid) throw Error('构建锁已被其他任务接管，未启动第二份编译')
            if (alive(current.pid) || current.workerPid && alive(current.workerPid) || unitState(current.unit) !== 'stopped') throw Error('原构建状态已变化，保留锁')
            const stale = `${lock}.stale-${crypto.randomBytes(5).toString('hex')}`
            fs.renameSync(lock, stale)
            fs.rmSync(stale, { recursive: true })
            fs.mkdirSync(lock, { mode: 0o700 })
            writeJson(path.join(lock, 'owner.json'), owner)
        } finally { fs.rmdirSync(recovery) }
        return
    }
    writeJson(path.join(lock, 'owner.json'), owner)
}
export function promoteDist(source, destination, backup) {
    if (!fs.existsSync(path.join(source, 'index.html'))) throw Error('构建未生成 index.html，不替换原 dist')
    if (fs.existsSync(destination) && (fs.lstatSync(destination).isSymbolicLink() || !fs.statSync(destination).isDirectory())) throw Error('原 dist 不是普通目录，拒绝覆盖')
    const previous = fs.existsSync(destination)
    if (previous) fs.renameSync(destination, backup)
    try { fs.renameSync(source, destination) } catch (error) { if (previous) fs.renameSync(backup, destination); throw error }
}
function verifyOwnLimits(plan) {
    const group = ownCgroup()
    if (path.basename(group.dir) !== plan.unit) throw Error('当前进程不在本次 systemd 构建单元内，拒绝编译')
    const actual = {
        memoryMax: read(path.join(group.dir, 'memory.max')), memoryHigh: read(path.join(group.dir, 'memory.high')),
        swapMax: read(path.join(group.dir, 'memory.swap.max')), cpuMax: read(path.join(group.dir, 'cpu.max')),
        oomGroup: read(path.join(group.dir, 'memory.oom.group')), oomScore: read('/proc/self/oom_score_adj'), coreFilter: read('/proc/self/coredump_filter'), ioMax: read(path.join(group.dir, 'io.max')),
    }
    verifyLimits(plan.policy, plan.devices, actual)
    return { dir: group.dir, actual }
}
function readOwnPlan(file) {
    const actual = fs.realpathSync(file)
    if (!actual.startsWith(path.join(state, 'runs') + path.sep) || path.basename(actual) !== 'plan.json') throw Error('构建计划不属于本项目')
    const plan = JSON.parse(read(actual))
    if (plan.schema !== POLICY_VERSION || plan.admin !== admin || plan.run !== path.dirname(actual) || plan.out !== path.join(plan.run, 'dist') || !/^[a-f0-9-]+$/.test(plan.token) || plan.unit !== `${prefix}${plan.token}.service` || plan.target !== path.resolve(admin, '../niucloud/public/admin') || typeof plan.check !== 'boolean' || typeof plan.compileOnly !== 'boolean') throw Error('构建计划与当前项目不一致')
    const p = plan.policy
    if (!p || ![p.memoryMiB, p.highMiB, p.heapMiB, p.cpuPercent, p.runtimeSeconds, p.readBps, p.writeBps, p.readIops, p.writeIops].every(value => Number.isInteger(value) && value > 0) || p.memoryMiB > 4096 || p.memoryMiB < 3072 || p.highMiB > p.memoryMiB || p.heapMiB > p.memoryMiB - NATIVE_ALLOWANCE_MIB || p.runtimeSeconds > 1800 || p.cpuPercent > 200 || p.readBps > 50 * MiB || p.writeBps > 20 * MiB || p.readIops > 500 || p.writeIops > 250) throw Error('构建计划资源额度无效')
    return plan
}
async function worker(planFile) {
    const plan = readOwnPlan(planFile)
    const group = linux ? verifyOwnLimits(plan) : null
    // Log parents/files are explicitly 0700/0600; web assets must stay readable.
    process.umask(0o022)
    const started = Date.now(), controller = new AbortController()
    let phase = 'checked', compiled = false, published = false
    const snapshot = () => {
        const usage = process.resourceUsage()
        const metrics = { phase, elapsedSeconds: (Date.now() - started) / 1000, nodeMaxRssKiB: usage.maxRSS }
        if (group) for (const name of ['memory.current', 'memory.peak', 'memory.events', 'io.stat']) {
            try { metrics[name] = read(path.join(group.dir, name)) } catch {}
        }
        writeJson(path.join(plan.run, 'progress.json'), metrics)
        return metrics
    }
    for (const signal of ['SIGTERM', 'SIGINT', 'SIGHUP']) process.on(signal, () => {
        controller.abort(Error(`收到 ${signal}，取消本次构建`))
        if (phase === 'compile') process.exit(130)
    })
    writeJson(path.join(plan.run, 'limits.json'), { guarded: Boolean(group), observed: group?.actual || null, policy: plan.policy, devices: plan.devices })
    console.log(`[保护检查] ${group ? 'Linux 内存、CPU、Swap、磁盘限额已逐项读回验证' : '本地非 Linux 构建：仅限制 Node 堆和并发，不提供 Linux cgroup 硬保护'}`)
    if (plan.check) {
        let childInherited = null
        if (group) {
            const child = runSync(process.execPath, ['-e', "process.stdout.write(require('node:fs').readFileSync('/proc/self/cgroup','utf8'))"])
            childInherited = child.status === 0 && child.stdout.split('\n').some(line => line.startsWith('0::') && path.basename(line.slice(3)) === plan.unit)
            if (!childInherited) throw Error('子进程未继承本次资源隔离，未启动编译')
        }
        writeJson(path.join(plan.run, 'result.json'), { schema: POLICY_VERSION, token: plan.token, checked: true, guarded: Boolean(group), childInherited, compiled: false, published: false, ...snapshot() })
        return
    }
    const timer = setInterval(snapshot, 10000)
    timer.unref()
    try {
        phase = 'compile'; snapshot()
        console.log('[1/3] 编译管理端；失败不会替换线上后台')
        const { build } = await import('vite')
        await build({ root: admin, configFile: path.join(admin, 'vite.config.ts'), build: { outDir: plan.out, emptyOutDir: true, reportCompressedSize: false, sourcemap: false, minify: 'esbuild' } })
        if (controller.signal.aborted) throw controller.signal.reason
        compiled = true; phase = 'dist'; snapshot()
        promoteDist(plan.out, path.join(admin, 'dist'), path.join(plan.run, 'previous-dist'))
        console.log('[2/3] 新 dist 已就绪；原 dist 保留在本次日志目录的 previous-dist')
        if (!plan.compileOnly) {
            phase = 'publish'; snapshot()
            const publisher = await import('./publish-safe.cjs')
            await publisher.publishSafe({ source: path.join(admin, 'dist'), target: plan.target, signal: controller.signal })
            published = true
            console.log('[3/3] 资源已校验并准备完成，后台入口已切换；旧 hash 资源保留')
        }
        phase = 'complete'
        writeJson(path.join(plan.run, 'result.json'), { schema: POLICY_VERSION, token: plan.token, checked: true, guarded: Boolean(group), compiled, published, output: path.join(admin, 'dist'), ...snapshot() })
    } catch (error) {
        writeJson(path.join(plan.run, 'result.json'), { schema: POLICY_VERSION, token: plan.token, checked: true, guarded: Boolean(group), compiled, published, error: error.message, ...snapshot() })
        throw error
    } finally { clearInterval(timer) }
}
function loggedChild(file, args, env, logFile, onCancel) {
    return new Promise((resolve, reject) => {
        const log = fs.openSync(logFile, 'wx', 0o600)
        const child = spawn(file, args, { cwd: admin, env, stdio: ['ignore', 'pipe', 'pipe'] })
        let bytes = 0, cancelled = false, logLimited = false, error
        const cancel = () => { if (!cancelled) { cancelled = true; onCancel(child) } }
        const signals = ['SIGTERM', 'SIGINT', 'SIGHUP']
        for (const signal of signals) process.on(signal, cancel)
        const outputError = () => cancel()
        process.stdout.on('error', outputError)
        process.stderr.on('error', outputError)
        for (const [stream, output] of [[child.stdout, process.stdout], [child.stderr, process.stderr]]) stream.on('data', chunk => {
            const remaining = MAX_LOG_BYTES - bytes
            if (remaining <= 0) { logLimited = true; cancel(); return }
            const saved = chunk.subarray(0, remaining)
            fs.writeSync(log, saved); bytes += saved.length
            if (!output.write(saved)) { stream.pause(); output.once('drain', () => stream.resume()) }
            if (bytes >= MAX_LOG_BYTES) { logLimited = true; cancel() }
        })
        child.on('error', cause => { error = cause })
        child.on('close', (code, signal) => {
            for (const name of signals) process.off(name, cancel)
            fs.closeSync(log)
            if (error) reject(error)
            else resolve({ code: cancelled ? 130 : code ?? 1, signal, cancelled, logLimited, logBytes: bytes })
        })
    })
}
async function coordinator(options) {
    const [nodeMajor, nodeMinor] = process.versions.node.split('.').map(Number)
    if (nodeMajor < 18 || nodeMajor === 18 && nodeMinor < 17) throw Error('安全构建需要 Node.js 18.17 或以上版本')
    if (process.env.NODE_OPTIONS && !options.worker) console.log('[提示] 编译 worker 将重建 NODE_OPTIONS，不继承 SSH／npm 中的超大堆配置')
    const memory = linux ? hostMemory() : { totalMiB: Math.max(os.totalmem() / MiB, 8192), availableMiB: Math.max(os.freemem() / MiB, 5120) }
    const policy = planResources({ ...memory, cpus: os.cpus().length, memoryMiB: options.memoryMiB })
    let systemdRun, systemctl, devices = []
    if (linux) {
        systemdRun = command('systemd-run'); systemctl = command('systemctl')
        const controls = read('/sys/fs/cgroup/cgroup.controllers').split(/\s+/)
        if (!['cpu', 'memory', 'io'].every(name => controls.includes(name))) throw Error('cgroup v2 缺少 cpu／memory／io 控制器，不启动无保护编译')
        const publicParent = path.resolve(admin, '../niucloud/public')
        devices = backingDevices([admin, path.join(admin, 'src'), path.join(admin, 'node_modules'), os.tmpdir(), path.dirname(process.execPath), publicParent,
            ...(fs.existsSync(path.join(publicParent, 'admin')) ? [path.join(publicParent, 'admin')] : []),
            ...(fs.existsSync(state) ? [state] : [])])
    }
    directory(state); directory(path.join(state, 'runs'))
    if (!fs.existsSync(path.join(state, '.gitignore'))) fs.writeFileSync(path.join(state, '.gitignore'), '*\n', { flag: 'wx', mode: 0o600 })
    const space = fs.statfsSync(admin)
    if (Number(space.bavail) * Number(space.bsize) < 1024 * MiB) throw Error('磁盘可用空间不足 1 GB，未启动编译；不会自动删除旧资源或业务数据')
    const token = crypto.randomUUID(), id = `${new Date().toISOString().replace(/[:.]/g, '-')}-${token.slice(0, 8)}`
    const unit = `${prefix}${token}.service`, run = path.join(state, 'runs', id), lock = path.join(state, 'build.lock')
    const owner = { pid: process.pid, token, unit: linux ? unit : null, started: new Date().toISOString() }
    acquireLock(lock, owner, { unitState: previous => readUnitState(previous, systemctl) })
    let mayUnlock = true
    try {
        directory(run)
        const plan = { schema: POLICY_VERSION, token, unit, admin, run, out: path.join(run, 'dist'), target: path.resolve(admin, '../niucloud/public/admin'), policy, devices, check: options.check, compileOnly: options.compileOnly }
        const planFile = path.join(run, 'plan.json')
        writeJson(planFile, plan)
        console.log(`[构建日志] ${run}\n[资源预算] 总内存 ${policy.memoryMiB} MB，Node 堆 ${policy.heapMiB} MB，CPU ${policy.cpuPercent}%${linux ? '，禁止构建使用 Swap' : '（非 Linux：无硬限额）'}`)
        let file = process.execPath, args = [script, '--worker', '--plan', planFile]
        const env = cleanBuildEnv(process.env, policy)
        if (linux) {
            file = systemdRun
            args = [...(userMode ? ['--user'] : []), `--unit=${unit}`, '--wait', '--pipe', '--quiet', '--service-type=exec', `--working-directory=${admin}`,
                ...systemdProperties(policy, devices).map(value => `--property=${value}`),
                ...Object.entries(env).map(([key, value]) => key.startsWith('VITE_') ? `--setenv=${key}` : `--setenv=${key}=${value}`),
                process.execPath, script, '--worker', '--plan', planFile]
        }
        const outcome = await loggedChild(file, args, linux ? process.env : env, path.join(run, 'build.log'), child => {
            console.error('[中止] 正在停止本次构建进程组，不操作 PHP／MySQL')
            if (linux) {
                const stop = spawn(systemctl, [...(userMode ? ['--user'] : []), 'stop', unit], { stdio: 'ignore' })
                stop.on('error', cause => console.error(cause.message))
            } else child.kill('SIGTERM')
        })
        if (linux) {
            const report = runSync(systemctl, [...(userMode ? ['--user'] : []), 'show', unit, '--property=Result', '--property=ExecMainStatus', '--property=MemoryPeak'])
            fs.writeFileSync(path.join(run, 'unit-result.txt'), (report.stdout || '') + (report.stderr || ''), { mode: 0o600 })
            if (readUnitState(unit, systemctl) !== 'stopped') { mayUnlock = false; throw Error(`构建单元尚未停止，保留锁：${unit}`) }
            runSync(systemctl, [...(userMode ? ['--user'] : []), 'reset-failed', unit])
        }
        writeJson(path.join(run, 'process-result.json'), outcome)
        if (outcome.code !== 0) throw Error(`本次任务未完整结束（退出码 ${outcome.code}${outcome.logLimited ? '，日志超过32MiB已中止' : ''}）；不可视为发布成功。请查看 ${path.join(run, 'build.log')}、result.json 与 progress.json`)
        const result = JSON.parse(read(path.join(run, 'result.json')))
        if (result.schema !== POLICY_VERSION || result.token !== token || !result.checked || linux && !result.guarded || options.check && result.compiled || !options.check && !result.compiled || !options.check && !options.compileOnly && !result.published) throw Error('未收到本次 worker 的完整验收结果，不将启动成功视为构建成功')
        console.log(options.check ? '[通过] 保护检查已完成；没有编译或发布。' : `[完成] 管理端构建成功${result.published ? '，并已安全发布' : '；未发布'}。`)
        console.log(`[结果] ${path.join(run, 'result.json')}`)
    } catch (error) {
        // On an unexpected client-side error, never leave a Linux worker behind.
        if (linux) {
            try {
                runSync(systemctl, [...(userMode ? ['--user'] : []), 'stop', unit])
                if (readUnitState(unit, systemctl) !== 'stopped') mayUnlock = false
            } catch { mayUnlock = false }
        }
        throw error
    } finally {
        if (mayUnlock) {
            const current = JSON.parse(read(path.join(lock, 'owner.json')))
            if (current.token === token) fs.rmSync(lock, { recursive: true })
        } else console.error(`[保护] 构建状态未确认，保留锁：${lock}`)
    }
}

export async function main(args = process.argv.slice(2)) {
    const options = parseOptions(args)
    if (options.help) { console.log('npm run build             受保护编译并发布\nnpm run build:check       只核实保护是否实际生效\nnpm run build:compile     受保护编译，不发布\n可选 --memory-mb 数字：只能在安全预算内降低额度；Linux 无保护时拒绝执行。'); return }
    if (options.worker) await worker(options.plan)
    else await coordinator(options)
}
if (process.argv[1] && pathToFileURL(path.resolve(process.argv[1])).href === import.meta.url) main().catch(error => { console.error(`[失败] ${error.message}`); process.exitCode = 1 })
