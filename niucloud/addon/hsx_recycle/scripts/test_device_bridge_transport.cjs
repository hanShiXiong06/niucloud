const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '../../../..')
const ts = require(path.join(root, 'admin/node_modules/typescript'))
const source = fs.readFileSync(path.join(root, 'admin/src/addon/hsx_recycle/components/device-entry/useLocalDevice.ts'), 'utf8')
const compiled = ts.transpileModule(source, {
    compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.CommonJS }
}).outputText
const base = 'http://127.0.0.1:17890'
const flush = () => new Promise(resolve => setImmediate(resolve))

function fixture(get) {
    const timers = new Map()
    const cleanup = []
    let next = 0
    const sandbox = {
        exports: {},
        require(name) {
            if (name === 'vue') return { ref: value => ({ value }), onBeforeUnmount: fn => cleanup.push(fn) }
            if (name === 'axios') return { default: { get } }
            if (name === './deviceReadings') return { mapLocalDevice: data => data }
            if (name === './deviceBridgeSupport') return { BRIDGE_BASE_URL: base }
            throw new Error(name)
        },
        setTimeout(fn) { timers.set(++next, fn); return next },
        clearTimeout(id) { timers.delete(id) }
    }
    vm.runInNewContext(compiled, sandbox)
    return { hook: sandbox.exports.useLocalDevice(), timers, cleanup, async tick() {
        const [id, fn] = timers.entries().next().value || []
        assert.ok(fn, 'next polling tick exists')
        timers.delete(id)
        await fn()
        await flush()
    } }
}
const response = (data, warnings = []) => ({ data: { code: 0, data, warnings, partial: warnings.length > 0 } })

async function main() {
    let reads = 0
    let scanIds = ['mtp:a', 'mtp:b']
    const received = []
    const f = fixture(async url => {
        if (url.endsWith('/scan')) return response(scanIds)
        reads++
        return reads === 1 ? response([{ device_id: 'mtp:a' }], [{ message: 'phone b is busy' }])
            : response([{ device_id: 'mtp:a' }, { device_id: 'mtp:b' }])
    })
    f.hook.startAuto(devices => received.push(devices))
    await flush()
    assert.equal(reads, 1)
    assert.equal(received[0].length, 1)
    assert.equal(f.hook.readWarnings.value[0], 'phone b is busy')
    await f.tick()
    assert.equal(reads, 2, 'the failed phone must be retried')
    assert.equal(f.hook.readWarnings.value.length, 0)
    await f.tick()
    assert.equal(reads, 2, 'both successful phones are not repeatedly read')
    scanIds = []
    await f.tick()
    scanIds = ['mtp:a', 'mtp:b']
    await f.tick()
    assert.equal(reads, 3, 'reconnected phones are eligible again')
    f.hook.stopAuto()
    assert.equal(f.timers.size, 0)
    console.log('PASS partial reads, visible warnings, retry, deduplication and reconnect')

    const requested = []
    const denied = fixture(async url => {
        requested.push(url)
        throw { response: { status: 422, data: { message: 'Please unlock the phone' } } }
    })
    await assert.rejects(denied.hook.fetchConnected(), /Please unlock/)
    assert.equal(requested.length, 1, 'device errors cannot silently fall back to another service')
    assert.equal(denied.hook.fetching.value, false)
    assert.equal(denied.hook.readWarnings.value[0], 'Please unlock the phone')

    let finish
    let callbacks = 0
    const stopped = fixture(async url => url.endsWith('/scan') ? response(['mtp:a'])
        : await new Promise(resolve => { finish = resolve }))
    stopped.hook.startAuto(() => callbacks++)
    await flush()
    assert.equal(stopped.hook.fetching.value, true)
    stopped.hook.stopAuto()
    finish(response([{ device_id: 'mtp:a' }]))
    await flush()
    assert.equal(callbacks, 0, 'stopping detection must cancel the pending UI callback')
    assert.equal(stopped.timers.size, 0)
    console.log('PASS error recovery and stopping pending auto-detection')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
