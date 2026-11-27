const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const test = require('node:test')
const ts = require('typescript')
const { parse, compileScript, compileTemplate } = require('@vue/compiler-sfc')

const root = path.resolve(__dirname, '..')

function loadTs(relative, mocks = {}, cache = new Map()) {
    const filename = path.join(root, relative)
    if (cache.has(filename)) return cache.get(filename).exports
    const module = { exports: {} }
    cache.set(filename, module)
    const source = ts.transpileModule(fs.readFileSync(filename, 'utf8'), {
        compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 },
        fileName: filename,
    }).outputText
    const localRequire = id => {
        if (Object.hasOwn(mocks, id)) return mocks[id]
        const prefix = '@/addon/hsx_erp/'
        if (id.startsWith(prefix)) return loadTs(id.slice(prefix.length) + '.ts', mocks, cache)
        return require(id)
    }
    new Function('module', 'exports', 'require', source)(module, module.exports, localRequire)
    return module.exports
}

const display = loadTs('utils/display.ts')

test('enum labels never expose unknown technical keys', () => {
    assert.equal(display.erpEnumLabel('pending', { pending: '待付款' }), '待付款')
    assert.equal(display.erpEnumLabel('unknown_state', {}), '状态待确认')
    assert.equal(display.erpEnumLabel('queued', {}), '状态待确认')
    assert.equal(display.erpEnumLabel('ready', { ready: 'ready' }), '状态待确认')
    assert.equal(display.erpEnumLabel('ready', { ready: 'internal_state' }), '状态待确认')
    assert.equal(display.erpEnumLabel('已确认', {}), '已确认')
})

test('real names are preserved and missing option names do not fall back to IDs', () => {
    for (const name of ['iPhone 17 Pro Max', 'admin', 'Alice', 'eBay', 'My_Channel']) {
        assert.equal(display.erpOptionLabel(name, 'custom_channel'), name)
    }
    assert.equal(display.erpOptionLabel('', 'channel_42', '未命名渠道'), '未命名渠道')
    assert.equal(display.erpOptionLabel('channel_42', 'channel_42', '未命名渠道'), '未命名渠道')
    assert.equal(display.erpOptionLabel('hsx_recycle', '', '其他来源'), '其他来源')
})

test('source names retain business meaning without exposing plugin identifiers', () => {
    assert.equal(display.erpSourceLabel('hsx_recycle'), '回收业务')
    assert.equal(display.erpSourceLabel('回收插件采购'), '回收入库')
    assert.equal(display.erpSourceLabel('ERP采购'), 'ERP采购')
    assert.equal(display.erpSourceLabel('回收业务 · hsx_recycle'), '回收业务')
    assert.equal(display.erpSourceLabel('unknown_source'), '其他来源')
    assert.equal(display.erpSourceLabel('eBay'), 'eBay')
})

test('dictionary fallback and tabs remain readable while filter values remain unchanged', () => {
    const dict = loadTs('api/dict.ts', { '@/utils/request': { default: {} } })
    assert.equal(dict.dictLabel({}, 'asset_status', 'in_stock'), '在库')
    assert.equal(dict.dictLabel({}, 'asset_status', 'future_status'), '状态待确认')
    const rows = { custom: [{ value: 'future_status', label: 'future_status' }] }
    assert.deepEqual(dict.dictTabs(rows, 'custom', false), [{ value: 'future_status', label: '状态待确认', name: '状态待确认' }])
})

test('sale channel names change only for display and preserve API metadata', async () => {
    const rows = [
        { key: 'custom_1', name: 'eBay', channel_type: 'platform', source_plugin: 'hsx_recycle', source_key: 'source_42' },
        { key: 'channel_42', channel_type: 'plugin', source_plugin: 'hsx_partner' },
    ]
    const api = { getErpSaleChannelOptions: async () => ({ data: rows }) }
    const channels = loadTs('hooks/useErpSaleChannels.ts', { '@/addon/hsx_erp/api/erp': api })
    const result = await channels.useErpSaleChannels().load(true)
    assert.equal(result[0].name, 'eBay')
    assert.equal(result[0].source_plugin, 'hsx_recycle')
    assert.equal(result[0].source_key, 'source_42')
    assert.equal(result[1].key, 'channel_42')
    assert.equal(result[1].name, '未命名渠道')
    assert.equal(result[1].channel_type_name, '业务渠道')
})

test('finance source labels hide technical fallbacks and keep business document numbers', () => {
    const finance = loadTs('hooks/useErpFinanceSource.ts')
    const row = { source_no: 'CG-20260905-001', source_meta: { finance_type_name: 'unknown_payable', business_source_name: 'hsx_recycle', source_plugin: 'hsx_recycle', channel_name: 'eBay', channel_code: 'custom_1' } }
    const meta = finance.erpFinanceSourceMeta(row, 'payable')
    assert.equal(meta.finance_type_name, '采购应付')
    assert.equal(meta.business_source_name, '回收业务')
    assert.equal(meta.channel_name, 'eBay')
    assert.equal(meta.source_no, 'CG-20260905-001')
    assert.equal(meta.source_plugin, 'hsx_recycle')
})

test('unknown required fields are described without exposing form keys', () => {
    const form = loadTs('hooks/useErpListingForm.ts')
    const message = form.validateErpListingForm({}, { forms: { material: { required_fields: ['internal_field'] } } }, 'material')
    assert.equal(message, '请先完善必填资料')
})

function vueFiles(dir) {
    return fs.readdirSync(dir, { withFileTypes: true }).flatMap(entry => {
        if (entry.name === 'qiun-data-charts') return []
        const filename = path.join(dir, entry.name)
        return entry.isDirectory() ? vueFiles(filename) : entry.name.endsWith('.vue') ? [filename] : []
    })
}

test('ERP templates do not render internal source fields or asset identifiers', () => {
    for (const filename of vueFiles(root)) {
        const source = fs.readFileSync(filename, 'utf8')
        const { descriptor, errors } = parse(source, { filename })
        assert.deepEqual(errors, [], filename)
        const template = descriptor.template?.content || ''
        assert.doesNotMatch(template, /\{\{[^}]*\b(?:asset_no|source_plugin|source_plugin_name|origin_plugin_name|channel_source_plugin|deviceId)\b[^}]*\}\}/, filename)
        assert.doesNotMatch(template, /来源插件[：:]|员工\s*#|['"]UID\s/, filename)
    }
})

test('ERP Vue scripts and templates compile after display cleanup', () => {
    for (const filename of vueFiles(root)) {
        const source = fs.readFileSync(filename, 'utf8')
        const { descriptor } = parse(source, { filename })
        const id = path.relative(root, filename)
        if (descriptor.scriptSetup || descriptor.script) compileScript(descriptor, { id })
        if (descriptor.template) {
            const result = compileTemplate({ source: descriptor.template.content, filename, id })
            assert.deepEqual(result.errors, [], filename)
        }
    }
})
