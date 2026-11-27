#!/usr/bin/env node
// Real mobile SFC logic with isolated API substitutes. No DB, printer or production calls.
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm'), assert = require('node:assert/strict')
const root = path.resolve(__dirname, '../../../..'), app = path.join(root, 'site-uniapp'), base = path.join(app, 'src/addon/hsx_recycle')
const vue = require(path.join(app, 'node_modules/vue')), ts = require(path.join(app, 'node_modules/typescript')), compiler = require(path.join(app, 'node_modules/@vue/compiler-sfc'))
let count = 0
const plain = value => value === undefined ? undefined : JSON.parse(JSON.stringify(value))
const eq = (value, expected, message) => { assert.deepEqual(plain(value), plain(expected), message); count++ }
const ok = (value, message) => { assert.ok(value, message); count++ }
const blocked = () => { throw Error('Unexpected production or printing operation') }
const defer = () => { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no }); return { promise, resolve, reject } }
function run(source, filename, dependencies = {}, extra = {}) {
    const module = { exports: {} }
    const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    vm.runInNewContext(code, { module, exports: module.exports, console, ...extra, require(id) {
        if (Object.hasOwn(dependencies, id)) return dependencies[id]
        if (id.endsWith('.vue')) return {}
        throw Error('Unexpected import ' + id)
    } }, { filename })
    return module.exports
}
const field = (key, extra = {}) => ({ field_key: key, field_name: key === 'battery' ? '电池健康度' : key, component: 'input', extra_config: { summary_visible: 1 }, ...extra })
const fields = [field('capacity'), field('battery', { unit: '%', default_value: '100', options: [{ value: 'old', label: '旧选项' }], selection_mode: 'multiple' }), field('parts', { component: 'checkbox', options: [{ value: 'box', label: '包装' }] }), field('activation_lock', { component: 'switch' })]
const schema = configured => ({ code: 1, data: { template: { id: 51 }, groups: [{ fields: configured || fields }], schema_status: 'ready' } })
const row = (extra = {}) => ({
    id: 101, order_id: 90, model: '手机/苹果/iPhone 14系列/苹果 iPhone 14 Pro', category_id: 8, imei: '353938205192374', imei2: '', sn: 'ORIGINALSN', user_sn: 'C001', check_template_id: 51,
    status: 7, confirm_status: 0, pay_status: 0, final_price: '5000.00', before_price: '4500.00', check_uid: 9,
    info: { sign_summary: { battery: '70' }, check_meta: { result_items: [{ field_key: 'battery', value: '94' }] } },
    info_correction: { allowed: true, reason: '', summary: { battery: '94', capacity: '256GB', parts: ['box'], activation_lock: false }, provenance: { initial_entry: { known: true, name: '首录员工', uid: 1 }, checker: { known: true, name: '质检员工', uid: 9 } } },
    ...extra
})
async function flush() { for (let i = 0; i < 10; i++) await vue.nextTick() }
function component(options = {}) {
    const calls = [], toasts = [], emitted = [], lifecycle = []
    const filename = path.join(base, 'pages/order/components/DeviceInfoCorrectionPopup.vue'), source = fs.readFileSync(filename, 'utf8')
    const { descriptor, errors } = compiler.parse(source, { filename }); eq(errors, [], 'correction SFC parses')
    const script = compiler.compileScript(descriptor, { id: 'correction-mobile' })
    const template = compiler.compileTemplate({ filename, id: 'correction-mobile', source: descriptor.template.content, compilerOptions: { bindingMetadata: script.bindings } })
    eq(template.errors, [], 'correction template compiles')
    const exports = run(script.content, filename, {
        vue: { ...vue, onBeforeUnmount: cb => lifecycle.push(cb) },
        '@/addon/hsx_recycle/api/order': {
            getDevice: async id => { calls.push(['get', id]); return options.get ? options.get(id) : { code: 1, data: row() } },
            updateDevice: async (id, payload) => { calls.push(['update', id, plain(payload)]); return options.save ? options.save(id, payload) : { code: 1, data: { changed: true, changed_fields: ['battery'] } } },
            searchDeviceModelDictOptions: async params => { calls.push(['search', plain(params)]); return options.search ? options.search(params) : { data: [{ id: 8, node_name: '苹果 iPhone 14 Pro', has_children: 0 }, { id: 9, node_name: '父分类', has_children: 1 }] } }
        },
        '@/addon/hsx_recycle/api/check-template': { getCheckTemplateSchema: async params => { calls.push(['schema', plain(params)]); return options.schema ? options.schema(params, calls.filter(item => item[0] === 'schema').length) : schema() } }
    }, { uni: { showToast: value => toasts.push(plain(value)), showModal: blocked, navigateTo: blocked } })
    const props = vue.reactive({ visible: false, deviceData: { id: 101 } }), scope = vue.effectScope()
    const state = scope.run(() => exports.default.setup(props, { expose() {}, emit: (name, value) => emitted.push([name, plain(value)]) }))
    return { state, props, calls, toasts, emitted, source, template: descriptor.template.content, async open() { props.visible = true; await flush() }, dispose() { lifecycle.forEach(fn => fn()); scope.stop() } }
}
async function main() {
    {
        const c = component(); await c.open(); const s = c.state
        eq(s.form.value.model, '苹果 iPhone 14 Pro', 'model display uses leaf name')
        eq(s.form.value.imei, '353938205192374', 'IMEI keeps exact string')
        eq(s.provenanceName(s.ability.value.provenance.initial_entry), '首录员工', 'initial recorder is shown')
        eq(s.provenanceName(s.ability.value.provenance.checker), '质检员工', 'checker is shown')
        eq(s.provenanceName({ known: false, name: '不得误认的人' }), '历史未记录', 'unknown recorder is not guessed')
        eq(s.summaryValues.value.battery, '94', 'backend canonical summary takes precedence over old signing values')
        eq(s.isOptionField(s.summaryFields.value[1]), false, 'input battery is not changed into options by stale data')
        eq(s.isMultiple(s.summaryFields.value[1]), false, 'input battery remains scalar despite stale multiple mode')
        eq(s.summaryValues.value.activation_lock, false, 'explicit false is retained')
        s.summaryValues.value.battery = '95'
        await s.save()
        const update = c.calls.find(item => item[0] === 'update')
        eq(update[1], 101, 'correction targets original device')
        eq(update[2].action, 'correct_info', 'uses separate correction action')
        eq(update[2].summary, { battery: '95' }, 'only changed summary is submitted')
        eq(Object.keys(update[2]).sort(), ['action', 'correction_reason', 'summary'], 'battery-only correction never resubmits stale identity or shortens original full model')
        for (const key of ['info', 'check_template_id', 'final_price', 'initial_price', 'before_price', 'status', 'pay_status', 'check_uid', 'check_at', 'next_assignee_uid', 'device_readings']) ok(!(key in update[2]), 'never submits forbidden field ' + key)
        ok(c.emitted.some(item => item[0] === 'saved'), 'success refreshes parent details')
        ok(c.emitted.some(item => item[0] === 'update:visible' && item[1] === false), 'success closes correction popup')
        c.dispose()
    }
    {
        const emptyRequired = field('new_required', { is_required: 1, default_value: '不能套默认值' })
        const c = component({ get: async () => ({ data: row({ info_correction: { allowed: true, summary: {}, provenance: {} } }) }), schema: async () => schema([emptyRequired, field('battery', { default_value: '100' })]) }); await c.open(); const s = c.state
        eq(s.summaryValues.value.battery, '', 'unknown battery does not become template default 100')
        eq(s.summaryValues.value.new_required, '', 'new required field does not get fabricated data')
        s.form.value.imei = '353938205192375'; await s.save()
        eq(c.calls.find(item => item[0] === 'update')[2].summary, {}, 'unchanged unknown historical summary does not block identity correction')
        c.dispose()
    }
    {
        const c = component({ get: async () => ({ data: row({ info_correction: { allowed: false, reason: '已确认，已进入ERP', provenance: {} } }) }) }); await c.open(); const s = c.state
        eq(s.ability.value.allowed, false, 'server gate is authoritative')
        await s.save(); ok(!c.calls.some(item => item[0] === 'update'), 'confirmed device cannot call correction API')
        ok(s.saveError.value.includes('已确认'), 'confirmed rejection is explicit')
        ok(!c.calls.some(item => item[0] === 'schema'), 'closed correction scope does not load editing schema')
        c.dispose()
    }
    {
        const c = component({ get: async () => ({ data: row({ info_correction: undefined }) }) }); await c.open()
        ok(c.state.loadError.value.includes('更新后端'), 'missing backend capability does not open an unrestricted editor')
        await c.state.save(); ok(!c.calls.some(item => item[0] === 'update'), 'unknown backend capability fails closed')
        c.dispose()
    }
    {
        const c = component({ schema: async () => { throw { msg: '模板暂不可用' } } }); await c.open(); const s = c.state
        ok(s.summaryError.value.includes('模板暂不可用'), 'schema error is shown')
        eq(s.canSaveIdentityOnly(), true, 'unchanged summary allows identity-only correction when schema temporarily fails')
        s.form.value.sn = 'NEW-SN'; await s.save()
        const payload = c.calls.find(item => item[0] === 'update')[2]
        eq(payload.sn, 'NEW-SN', 'identity correction is not blocked by unchanged unavailable summary')
        eq(Object.keys(payload).sort(), ['action', 'correction_reason', 'sn'], 'identity-only correction submits only changed SN, not old IMEI or category')
        ok(!Object.hasOwn(payload, 'summary'), 'identity-only save omits summary rather than clearing or reapplying it')
        ok(c.toasts.some(item => item.title.includes('原摘要保持不变')), 'identity-only success explicitly says summary was not changed')
        c.dispose()
    }
    {
        const c = component({ schema: async (_params, attempt) => { if (attempt === 2) throw { msg: '模板暂不可用' }; return schema() } }); await c.open(); const s = c.state
        s.form.value.sn = 'NEW-SN'; s.summaryValues.value.battery = '93'
        await s.retrySummary()
        eq(s.canSaveIdentityOnly(), false, 'edited summary cannot be silently ignored by identity-only fallback')
        await s.save()
        ok(!c.calls.some(item => item[0] === 'update'), 'failed reload cannot discard previously edited summary')
        await s.retrySummary()
        eq(s.form.value.sn, 'NEW-SN', 'retry preserves entered identity')
        eq(s.summaryValues.value.battery, '93', 'retry preserves entered battery')
        eq(s.summaryBaseline.value.battery, '94', 'retry baseline remains original value, not typed value')
        await s.save(); eq(c.calls.find(item => item[0] === 'update')[2].summary, { battery: '93' }, 'retried change still enters audit payload')
        c.dispose()
    }
    {
        const c = component({ schema: async (_params, attempt) => { if (attempt > 1) throw { msg: '新型号模板暂不可用' }; return schema() } }); await c.open(); const s = c.state
        s.chooseModel({ id: 10, node_name: '新型号', has_children: 0 }); await flush()
        eq(s.canSaveIdentityOnly(), false, 'model change cannot bypass new-template validation')
        await s.save(); ok(!c.calls.some(item => item[0] === 'update'), 'new model with failed schema remains blocked')
        c.dispose()
    }
    {
        const many = Array.from({ length: 12 }, (_, index) => field(index === 10 ? 'battery' : 'field_' + index))
        const c = component({ schema: async () => schema(many) }); await c.open()
        eq(c.state.summaryFields.value.length, 12, 'all configured summary fields remain visible')
        eq(c.state.summaryFields.value[10].field_key, 'battery', 'battery is not truncated after fifth item')
        c.dispose()
    }
    {
        const c = component(); await c.open(); const s = c.state
        s.chooseModel({ id: 10, node_name: '新型号', has_children: 0 }); await flush()
        await s.save()
        const payload = c.calls.find(item => item[0] === 'update')[2]
        eq(payload.category_id, 10, 'explicit model change submits selected category ID')
        eq(payload.model, '新型号', 'explicit model change includes matching display name')
        ok(!Object.hasOwn(payload, 'imei') && !Object.hasOwn(payload, 'sn'), 'model change does not resubmit unrelated identity fields')
        c.dispose()
    }
    {
        const current = row(), c = component({ get: async () => ({ data: plain(current) }), save: async (_id, payload) => { for (const key of ['model', 'category_id', 'imei', 'imei2', 'sn', 'user_sn']) if (Object.hasOwn(payload, key)) current[key] = payload[key]; return { data: { changed: true } } } }); await c.open()
        current.imei = '353938205192376'
        c.state.summaryValues.value.battery = '95'; await c.state.save()
        eq(current.imei, '353938205192376', 'editing battery does not overwrite another operator newer IMEI')
        c.dispose()
    }
    {
        const c = component(); await c.open(); const s = c.state
        s.form.value.imei = '123 456'; await s.save(); ok(!c.calls.some(item => item[0] === 'update'), 'invalid IMEI cannot save')
        ok(s.saveError.value.includes('空格'), 'IMEI validation explains format')
        await s.openModelPicker(); await flush(); eq(s.modelOptions.value.map(item => item.id), [8], 'model picker lists leaf models only')
        s.chooseModel({ id: 9, node_name: '父分类', has_children: 1 }); eq(s.form.value.category_id, 8, 'cannot select parent category')
        c.dispose()
    }
    {
        const old = defer(), newer = defer(); let attempt = 0
        const c = component({ schema: async () => ++attempt === 1 ? old.promise : newer.promise }); c.props.visible = true; await flush(); const s = c.state
        s.chooseModel({ id: 10, node_name: '新型号', has_children: 0 })
        newer.resolve(schema([field('new_battery')])); await flush()
        old.resolve(schema([field('old_battery')])); await flush()
        eq(s.summaryFields.value.map(item => item.field_key), ['new_battery'], 'stale schema cannot replace new model fields')
        c.dispose()
    }
    {
        const pending = defer(), c = component({ get: async () => pending.promise }); c.props.visible = true; await flush(); c.state.close()
        pending.resolve({ data: row() }); await flush()
        eq(c.state.device.value, null, 'closing invalidates in-flight device lookup')
        c.dispose()
    }
    {
        const wait = defer(), c = component({ save: async () => wait.promise }); await c.open()
        const first = c.state.save(), second = c.state.save()
        eq(c.calls.filter(item => item[0] === 'update').length, 1, 'rapid submit does not duplicate corrections')
        wait.resolve({ data: { changed: false } }); await Promise.all([first, second])
        ok(c.toasts.some(item => item.title === '资料没有变化'), 'no-op does not claim new log was written')
        c.dispose()
    }
    const detail = fs.readFileSync(path.join(base, 'pages/order/detail.vue'), 'utf8')
    const actionSource = detail.slice(detail.indexOf('const getDeviceActions ='), detail.indexOf('\nconst handleAction ='))
    const actions = run(actionSource + '\nmodule.exports = getDeviceActions', 'detail-actions.ts', {}, { isRejectable: () => false, canTransferConsignment: () => false, getVisibleDevicePrintActions: () => [] })
    for (const status of [3, 4, 7, 8]) ok(actions({ status, confirm_status: 0 }).some(item => item.type === 'correct_info'), 'unconfirmed state gets correction action ' + status)
    for (const denied of [{ status: 7, confirm_status: 1 }, { status: 5, confirm_status: 0 }, { status: 7, pay_status: 1 }, { status: 7, downstream_erp_asset_id: 9 }, { status: 7, info_correction: { allowed: false } }]) ok(!actions(denied).some(item => item.type === 'correct_info'), 'confirmed/paid/ERP cases do not expose correction action')
    ok(detail.includes('<DeviceInfoCorrectionPopup') && detail.includes('@saved="loadDetail"'), 'page mounts independent correction popup and refreshes')
    console.log(`[通过] ${count} 项移动资料纠错隔离回归；真实 SFC/状态入口，无真实API/数据库/打印。`)
}
main().catch(error => { console.error(error); process.exitCode = 1 })
