const fs = require('node:fs')
const path = require('node:path')
const assert = require('node:assert/strict')
const vm = require('node:vm')
const root = path.resolve(__dirname, '../../../..')
const sourceRoot = path.join(root, 'admin/src/addon/hsx_recycle')
const mirrorRoot = path.join(root, 'niucloud/addon/hsx_recycle/admin')
const compiler = require(path.join(root, 'admin/node_modules/@vue/compiler-sfc'))
const ts = require(path.join(root, 'admin/node_modules/typescript'))
const files = [
    'api/device_bridge.ts', 'api/order_config.ts',
    'components/device-entry/deviceBridgeSupport.ts', 'components/device-entry/useLocalDevice.ts',
    'components/device-entry/DeviceBridgeHelpDialog.vue', 'components/device-entry/DeviceEntryList.vue',
    'views/order_config/submit.vue', 'views/order_config/components/DeviceBridgeDownloadSettings.vue',
    'views/device_bridge/settings.vue'
]
for (const relative of files) {
    const filename = path.join(sourceRoot, relative)
    const source = fs.readFileSync(filename, 'utf8')
    assert.equal(source, fs.readFileSync(path.join(mirrorRoot, relative), 'utf8'), `${relative}: release copy`)
    if (!relative.endsWith('.vue')) continue
    const { descriptor, errors } = compiler.parse(source, { filename })
    assert.deepEqual(errors, [])
    const script = compiler.compileScript(descriptor, { id: 'bridge-help' })
    assert.deepEqual(ts.transpileModule(script.content, {
        fileName: filename + '.ts', reportDiagnostics: true,
        compilerOptions: { module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ES2020 }
    }).diagnostics, [])
    assert.deepEqual(compiler.compileTemplate({ source: descriptor.template.content, filename, id: 'bridge-help',
        compilerOptions: { bindingMetadata: script.bindings, expressionPlugins: ['typescript'] }
    }).errors, [])
    for (const style of descriptor.styles) assert.deepEqual(compiler.compileStyle({
        source: style.content, filename, id: 'bridge-help', preprocessLang: style.lang
    }).errors, [])
}
console.log('PASS components compile and all addon release copies match')

const filename = path.join(sourceRoot, 'components/device-entry/deviceBridgeSupport.ts')
const program = ts.createProgram([filename], {
    target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.ESNext, strict: true, skipLibCheck: true, noEmit: true, types: []
})
assert.deepEqual(ts.getPreEmitDiagnostics(program).map(diagnostic => ts.flattenDiagnosticMessageText(diagnostic.messageText, '\n')), [])
const code = ts.transpileModule(fs.readFileSync(filename, 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 }
}).outputText
const sandbox = { exports: {}, URL }
vm.runInNewContext(code, sandbox, { filename })
const support = sandbox.exports
assert.equal(support.safeBridgeUrl('/upload/bridge.pkg'), '/upload/bridge.pkg')
assert.equal(support.safeBridgeUrl('https://example.com/bridge.exe?x=1'), 'https://example.com/bridge.exe?x=1')
for (const url of ['javascript:alert(1)', 'data:text/html,hello', 'file:///tmp/test', '//evil.example/a', '/\\evil.example/a', 'http:evil.example', 'https://user:pass@example.com/file', 'https://example.com/\nfile', '<script>']) {
    assert.equal(support.safeBridgeUrl(url), '', `unsafe URL ${url}`)
}
assert.equal(support.bridgeUpdateAvailable('0.1.9', '0.1.10'), true)
assert.equal(support.bridgeUpdateAvailable('0.2.0', '0.1.10'), false)
assert.equal(support.bridgeUpdateAvailable('0.2', '0.2.0'), false)
assert.equal(support.bridgeUpdateAvailable('unknown', '0.2.0'), false)
assert.equal(support.bridgeUpdateAvailable(undefined, '0.2.0'), false)
assert.equal(support.detectBridgeComputer('Mozilla Windows NT 10.0 Win64'), 'windows')
assert.equal(support.detectBridgeComputer('Mozilla Macintosh Mac OS X'), 'macos_arm64')
assert.equal(support.detectBridgeComputer('Mozilla iPhone like Mac OS X Mobile'), 'other')
assert.equal(support.detectBridgeComputer('Mozilla Linux Android'), 'other')
assert.match(support.bridgeConnectionError({ response: { status: 403 } }), /白名单/)
assert.match(support.bridgeConnectionError({ code: 'ECONNABORTED' }), /超时/)
assert.match(support.bridgeConnectionError({ code: 'ERR_NETWORK' }), /未连接/)
const normalized = support.normalizeBridgeDownloads({ windows_url: 'javascript:alert(1)', windows_version: 'latest', tutorial_url: 'https://docs.example.com' })
assert.equal(normalized.windows_url, '')
assert.equal(normalized.windows_version, '')
assert.equal(normalized.tutorial_url, 'https://docs.example.com')
console.log('PASS download URL safety, numeric versions, OS detection and connection errors')

const help = fs.readFileSync(path.join(sourceRoot, 'components/device-entry/DeviceBridgeHelpDialog.vue'), 'utf8')
assert.match(help, /data\?\.service !== 'hsx_device_bridge'/)
assert.match(help, /onBeforeUnmount\(cancelRequests\)/)
assert.match(help, /@close="cancelRequests"/)
assert.match(help, /health\.value\?\.capabilities\?\.android_mtp/)
assert.match(help, /android_mtp_scope === 'generic'/)
assert.match(help, /android_mtp_backend === 'windows_wpd'/)
assert.match(help, /Windows 内测/)
assert.doesNotMatch(help, /现有 Windows 包仅支持 iPhone/)
assert.match(help, /v-if="item.url"/)
assert.match(help, /rel="noopener noreferrer"/)
const list = fs.readFileSync(path.join(sourceRoot, 'components/device-entry/DeviceEntryList.vue'), 'utf8')
assert.match(list, /安装与帮助/)
assert.match(list, /<DeviceBridgeHelpDialog v-model="bridgeHelpVisible" @read="readLocalDevices"/)
assert.match(list, /localReadError.value = describeError\(error\)/)
console.log('PASS installation entry, error recovery, cancelled probes and honest Android scope')

const mappingCode = ts.transpileModule(fs.readFileSync(path.join(sourceRoot, 'components/device-entry/deviceReadings.ts'), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 }
}).outputText
const mappingSandbox = { exports: {} }
vm.runInNewContext(mappingCode, mappingSandbox)
const mapped = mappingSandbox.exports.mapLocalDevice({
    schema_version: 'hsx.device.snapshot.v1', platform: 'android', source: 'usb_mtp',
    identity: { serial_number: 'USB-SN', imei: '', mtp_serial_number: 'MTP-UUID' },
    hardware: { product_type: 'SM-G9910', model_number: 'SM-G9910' },
    display: { device_name: 'Galaxy S21 5G', model_hint: 'SM-G9910', capacity: '', color: '' },
    system: { version: '', firmware_raw: 'FIRMWARE' }, battery: { level_percent: 100 }
})
assert.equal(mapped.serial_number, 'USB-SN')
assert.equal(mapped.imei, '')
assert.equal(mapped.battery_health, '')
assert.equal(mapped.battery_cycle_count, '')
assert.equal(mapped.system_version, '')
assert.equal(mapped.capacity, '')
assert.equal(mapped.color, '')
assert.deepEqual(Array.from(mapped.model_candidates), ['SM-G9910'])
console.log('PASS Android model/SN mapping without guessed IMEI, health, firmware version or capacity')
