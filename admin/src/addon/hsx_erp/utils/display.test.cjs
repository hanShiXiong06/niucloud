const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const ts = require(require.resolve('typescript', { paths: [process.cwd(), path.resolve(process.cwd(), 'admin')] }))

const source = fs.readFileSync(path.join(__dirname, 'display.ts'), 'utf8')
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const context = { exports: {} }
vm.runInNewContext(compiled, context)
const { erpSourceLabel, erpNamedLabel, erpEnumLabel, erpSerialText, isInternalErpColumn } = context.exports

assert.equal(erpSourceLabel('hsx_recycle'), '回收业务')
assert.equal(erpSourceLabel('phone_shop'), '商城业务')
assert.equal(erpSourceLabel('hsx_unknown_provider'), '其他业务')
assert.equal(erpSourceLabel('unknown_provider', '来源未登记'), '来源未登记')
assert.equal(erpSourceLabel('回收入库'), '回收入库')
assert.equal(erpSourceLabel('回收插件采购'), '回收入库')
assert.equal(erpNamedLabel('', 'raw_custom_key', '其他渠道'), '其他渠道')
assert.equal(erpNamedLabel('hsx_recycle', 'hsx_recycle'), '回收业务')
assert.equal(erpNamedLabel('purchase_return', 'purchase_return'), '采购退货')
assert.equal(erpNamedLabel('custom_key', 'custom_key', '其他渠道'), '其他渠道')
assert.equal(erpNamedLabel('Retail_US', 'custom_channel'), 'Retail_US')
assert.equal(erpNamedLabel('eBay', 'custom_channel'), 'eBay')
assert.equal(erpNamedLabel('VIP Channel', ''), 'VIP Channel')
assert.equal(erpEnumLabel('pending', { pending: '待处理' }), '待处理')
assert.equal(erpEnumLabel('unknown_state', {}), '状态待确认')
assert.equal(erpEnumLabel('unknown', {}), '状态待确认')
assert.equal(erpEnumLabel('ready', { ready: 'ready' }), '状态待确认')
assert.equal(erpEnumLabel('已完成', {}), '已完成')
assert.equal(erpSerialText({ imei: '123456789012345', sn: 'SN-A', asset_no: 'ASSET-42' }), 'IMEI 123456789012345 · SN SN-A')
assert.equal(erpSerialText({ sn: 'SN-A', asset_no: 'ASSET-42' }), 'SN SN-A')
assert.equal(erpSerialText({ asset_no: 'ASSET-42', asset_id: 42 }), '未录入 IMEI / SN')
for (const key of ['id', 'uid', 'asset_id', 'party_id', 'member_id', 'asset_no', 'source_plugin', 'origin_plugin_name']) assert.equal(isInternalErpColumn(key), true, key)
for (const key of ['imei', 'sn', 'serial_no', 'purchase_no', 'sale_no', 'source_no', 'm_no', 'member_no']) assert.equal(isInternalErpColumn(key), false, key)
const report = fs.readFileSync(path.join(__dirname, '../components/ErpSaleProfitReport.vue'), 'utf8')
const moveColumn = report.match(/function moveColumn\(index: number, offset: number\) \{[\s\S]*?\n\}/)[0]
const columns = [{ key: 'model' }, { key: 'asset_id', export: 1 }, { key: 'profit' }]
const movement = { viewColumns: { value: columns }, publicViewColumns: { value: [columns[0], columns[2]] } }
vm.runInNewContext(ts.transpileModule(moveColumn, { compilerOptions: { target: ts.ScriptTarget.ES2020 } }).outputText + '\nmoveColumn(0, 1)', movement)
assert.equal(movement.viewColumns.value.map(item => item.key).join(','), 'profit,asset_id,model')
assert.equal(movement.viewColumns.value[1].export, 1)
process.stdout.write('ERP admin display tests passed (39 assertions)\n')
