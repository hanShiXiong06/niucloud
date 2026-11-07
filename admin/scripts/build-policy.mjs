// Shared, deterministic resource policy. Linux enforcement is checked in the
// worker's real cgroup before Vite or publishing code is imported.
export const MiB = 1024 * 1024
export const POLICY_VERSION = 'niucloud.admin.build.v1'
export const NATIVE_ALLOWANCE_MIB = 512

export function planResources({ totalMiB, availableMiB, cpus = 1, memoryMiB }) {
    if (![totalMiB, availableMiB, cpus].every(value => Number.isFinite(value) && value > 0)) throw Error('无法读取有效的内存／CPU 信息，未启动编译')
    const reserveMiB = Math.max(512, Math.ceil(totalMiB * 0.075))
    const maximumMiB = Math.floor(Math.min(4096, totalMiB * 0.5, availableMiB - reserveMiB) / 64) * 64
    if (maximumMiB < 3072) throw Error(`当前最多可安全分配 ${Math.max(0, maximumMiB)} MB，低于本管理端构建所需的 3072 MB；未启动编译，请在业务低峰或释放其他占用后重试`)
    const requested = memoryMiB === undefined ? maximumMiB : Number(memoryMiB)
    if (!Number.isInteger(requested) || requested < 3072 || requested > maximumMiB) throw Error(`构建总内存必须为 3072～${maximumMiB} MB，不能突破为业务服务保留的内存`)
    return {
        memoryMiB: requested,
        highMiB: Math.floor(requested * 0.9),
        // Allocate within the same cgroup cap, not by raising MemoryMax.
        // The kernel still counts native/esbuild/page-cache memory in that cap.
        heapMiB: Math.floor((requested - NATIVE_ALLOWANCE_MIB) / 64) * 64,
        cpuPercent: Math.min(200, Math.max(25, Math.floor(cpus * 50))),
        readBps: 50 * MiB,
        writeBps: 20 * MiB,
        readIops: 500,
        writeIops: 250,
        runtimeSeconds: 1800,
    }
}

export function cleanBuildEnv(env, policy) {
    // Do not inherit --require, tracing flags, or a larger heap from npm/SSH.
    return {
        PATH: env.PATH || '',
        LANG: env.LANG || 'C.UTF-8',
        NODE_ENV: 'production',
        NODE_OPTIONS: `--max-old-space-size=${policy.heapMiB}`,
        GOMAXPROCS: String(Math.max(1, Math.min(2, Math.floor(policy.cpuPercent / 100) || 1))),
        UV_THREADPOOL_SIZE: '2',
        CI: '1',
        ...Object.fromEntries(Object.entries(env).filter(([key, value]) => /^VITE_[A-Z0-9_]+$/.test(key) && typeof value === 'string')),
    }
}

export function systemdProperties(policy, devices) {
    if (!devices.length) throw Error('无法确认构建目录的磁盘设备，未启动无限制编译')
    return [
        'MemoryAccounting=yes', `MemoryHigh=${policy.highMiB * MiB}`, `MemoryMax=${policy.memoryMiB * MiB}`, 'MemorySwapMax=0',
        'CPUAccounting=yes', `CPUQuota=${policy.cpuPercent}%`, 'CPUWeight=10',
        'IOAccounting=yes', 'IOWeight=10',
        'OOMPolicy=kill', 'OOMScoreAdjust=500', 'KillMode=control-group', 'Restart=no',
        'TasksMax=128', 'Nice=10', 'LimitCORE=0', 'CoredumpFilter=0x0', 'UMask=0022',
        `RuntimeMaxSec=${policy.runtimeSeconds}s`, 'TimeoutStopSec=15s',
        ...devices.flatMap(device => [
            `IOReadBandwidthMax=${device.path} ${policy.readBps}`,
            `IOWriteBandwidthMax=${device.path} ${policy.writeBps}`,
            `IOReadIOPSMax=${device.path} ${policy.readIops}`,
            `IOWriteIOPSMax=${device.path} ${policy.writeIops}`,
        ]),
    ]
}

export function verifyLimits(policy, devices, actual) {
    const bounded = (value, max) => /^\d+$/.test(String(value).trim()) && Number(value) > 0 && Number(value) <= max
    if (!bounded(actual.memoryMax, policy.memoryMiB * MiB)) throw Error('memory.max 未实际生效，拒绝编译')
    if (!bounded(actual.memoryHigh, policy.highMiB * MiB)) throw Error('memory.high 未实际生效，拒绝编译')
    if (String(actual.swapMax).trim() !== '0') throw Error('memory.swap.max 不是 0，拒绝编译')
    const cpu = String(actual.cpuMax).trim().split(/\s+/)
    if (cpu.length !== 2 || !/^\d+$/.test(cpu[0]) || !/^\d+$/.test(cpu[1]) || Number(cpu[1]) <= 0 || Number(cpu[0]) <= 0 || Number(cpu[0]) / Number(cpu[1]) > policy.cpuPercent / 100 + 0.0001) throw Error('cpu.max 未实际生效，拒绝编译')
    if (String(actual.oomGroup).trim() !== '1') throw Error('构建进程组 OOM 隔离未生效，拒绝编译')
    if (!Number.isFinite(Number(actual.oomScore)) || Number(actual.oomScore) < 500) throw Error('构建 OOM 优先退出保护未生效，拒绝编译')
    if (!/^[0-9a-f]+$/i.test(String(actual.coreFilter).trim()) || Number.parseInt(String(actual.coreFilter).trim(), 16) !== 0) throw Error('构建崩溃转储过滤未生效，拒绝编译')
    const ioRows = new Map(String(actual.ioMax).trim().split('\n').filter(Boolean).map(line => {
        const [device, ...fields] = line.trim().split(/\s+/)
        return [device, Object.fromEntries(fields.map(field => field.split('=')))]
    }))
    for (const device of devices) {
        const limits = ioRows.get(device.id) || {}
        for (const [key, max] of [['rbps', policy.readBps], ['wbps', policy.writeBps], ['riops', policy.readIops], ['wiops', policy.writeIops]]) {
            if (!bounded(limits[key], max)) throw Error(`磁盘 ${device.id} 的 ${key} 限额未生效，拒绝编译`)
        }
    }
    return true
}

export function parseOptions(args) {
    const options = { check: false, compileOnly: false, worker: false }
    for (let index = 0; index < args.length; index++) {
        const arg = args[index]
        if (arg === '--check') options.check = true
        else if (arg === '--compile-only') options.compileOnly = true
        else if (arg === '--worker') options.worker = true
        else if (arg === '--plan') options.plan = args[++index]
        else if (arg === '--memory-mb') {
            const value = args[++index]
            if (!value || !/^\d+$/.test(value)) throw Error('--memory-mb 后必须填写整数 MB，未启动编译')
            options.memoryMiB = value
        }
        else if (arg === '--help') options.help = true
        else throw Error(`不支持的构建参数：${arg}`)
    }
    if (options.worker !== Boolean(options.plan)) throw Error('内部 worker 必须使用本次构建计划')
    return options
}
