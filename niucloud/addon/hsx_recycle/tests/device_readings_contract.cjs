// 无网络、无数据库：使用实际 TypeScript 逻辑和 Vue 渲染器验证读取/回填/档案抽屉。
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const { createRequire } = require('node:module')
const root = path.resolve(__dirname, '../../../..')
const req = createRequire(path.join(root, 'admin/package.json'))
const ts = req('typescript'), compiler = req('@vue/compiler-sfc'), vue = req('vue')
const cache = new Map()
const plain = value => JSON.parse(JSON.stringify(value))
let count = 0
function equal(actual, expected, message) { count++; assert.deepEqual(plain(actual), plain(expected), message) }
function load(file) {
    file = path.resolve(root, file)
    if (cache.has(file)) return cache.get(file)
    let source = fs.readFileSync(file, 'utf8')
    if (file.endsWith('.vue')) source = compiler.compileScript(compiler.parse(source, { filename: file }).descriptor, { id: file, inlineTemplate: true }).content
    const module = { exports: {} }
    cache.set(file, module.exports)
    vm.runInNewContext(ts.transpileModule(source, { compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.CommonJS } }).outputText, {
        exports: module.exports, module, console,
        require(name) {
            if (name === 'vue') return vue
            if (name === '@element-plus/icons-vue') return { ArrowRight: { render: () => vue.h('span') } }
            if (name.startsWith('@/')) return load(`admin/src/${name.slice(2)}.ts`)
            if (name.startsWith('.')) return load(path.resolve(path.dirname(file), name.endsWith('.vue') ? name : `${name}.ts`))
            throw new Error(`Unexpected import ${name}`)
        }
    }, { filename: file })
    return module.exports
}

const base = 'admin/src/addon/hsx_recycle/components/device-entry/'
const helper = load(`${base}deviceReadings.ts`)
const raw = {
    schema_version: 'hsx.device.snapshot.v1', captured_at: '2026-09-07T00:51:38+00:00', source: 'usb', platform: 'ios',
    identity: { imei: 'TEST-IMEI-01', imei2: 'TEST-IMEI-02', serial_number: 'TEST-SN-01', udid: 'TEST-UDID' },
    hardware: { product_type: 'iPhone18,4', model_number: 'MG364', model_number_full: 'MG364CH/A', color_code: '1' },
    display: { device_name: 'iPhone Air', model_hint: 'iPhone18,4', color: '', color_index: '1', capacity: '256GB' },
    system: { version: '26.6', activation_state: 'Activated' },
    battery: { health_percent: 100, calculated_health_percent: 100.5, cycle_count: 212, level_percent: 98, design_capacity_mah: 3116, nominal_capacity_mah: 3132 }
}
const mapped = helper.mapLocalDevice(raw)
equal(mapped.battery_health, '100', '优先真实健康度，不取 100.5 或电量 98')
equal(mapped.battery_health_source, 'battery.health_percent', '记录选值来源')
equal(mapped.battery_cycle_count, 212, '循环次数独立取值')
equal([mapped.imei, mapped.imei2, mapped.serial_number], ['TEST-IMEI-01', 'TEST-IMEI-02', 'TEST-SN-01'], '三组业务标识都保留')
equal([mapped.capacity, mapped.system_version, mapped.color], ['256GB', '26.6', ''], '颜色代码不猜颜色名称')
equal(mapped.model_candidates.includes('iPhone Air'), false, '可变设备名称不学习成硬件别名')
equal(mapped.model_candidates.includes('iPhone18,4'), true, '硬件标识可学习匹配')
for (const [battery, health, cycle] of [
    [{ health_percent: 0, cycle_count: 0, level_percent: 99 }, '0', 0],
    [{ health_percent: 85, calculated_health_percent: 101 }, '85', ''],
    [{ calculated_health_percent: 100.5 }, '100', ''],
    [{ calculated_health_percent: 92.4 }, '92', ''],
    [{ level_percent: 98 }, '', ''],
    [{ health_percent: 'error', calculated_health_percent: -1, cycle_count: 2.5 }, '', ''],
    [{ health_percent: '89%', cycle_count: '0' }, '89', 0],
]) {
    const result = helper.mapLocalDevice({ battery })
    equal([result.battery_health, result.battery_cycle_count], [health, cycle], '边界：零值/未知/计算回退')
}
const archive = helper.localReadingArchive(mapped)
equal(archive.local.raw, raw, '原始快照逐字段保存')
mapped.raw.battery.level_percent = 1
equal(archive.local.raw.battery.level_percent, 98, '原文为独立副本，后续对象修改不污染')

const field = (key, component = 'input', options = []) => ({ field_key: key, field_name: key, component, options })
const row = {
    model: 'iPhone Air', imei: mapped.imei, imei2: mapped.imei2, serial_number: mapped.serial_number, category_id: 77,
    battery_health: '100', battery_cycle_count: 212, device_readings: archive,
    summary_fields: [field('capacity', 'select', [{ label: '128G', value: 1 }, { label: '256G', value: 9 }]), field('color', 'select', [{ label: '黑色', value: 1 }, { label: '蓝色', value: 9 }]), field('battery', 'number'), field('battery_num', 'number'), field('system_version')],
    summary_values: { capacity: 1, color: 1, battery: 50 }, summary_default_keys: ['capacity', 'color', 'battery']
}
helper.prefillDeviceSummary(row, mapped)
equal(row.summary_values, { capacity: 9, color: '', battery: 100, battery_num: 212, system_version: '26.6' }, '实测覆盖模板默认值，无法确认颜色则清空默认色')
row.summary_values.battery = 87
row.local_prefilled_keys = [] // 用户在摘要中确认过
helper.prefillDeviceSummary(row, mapped)
equal(row.summary_values.battery, 87, '不覆盖人工确认的数据')
const colorRow = { summary_fields: [field('color', 'select', [{ label: '蓝色', value: 23 }])], summary_values: {} }
helper.prefillDeviceSummary(colorRow, { color: '蓝色', color_index: 0 })
equal(colorRow.summary_values.color, 23, '颜色按名称匹配，非代码或序号')
colorRow.summary_fields[0].options[0].value = 44
helper.prefillDeviceSummary(colorRow, { color: '蓝色' })
equal(colorRow.summary_values.color, 44, '切换模板时重新匹配自动填过的选项值')
colorRow.summary_fields[0].options.push({ label: '蓝色', value: 55 })
helper.prefillDeviceSummary(colorRow, { color: '蓝色' })
equal(colorRow.summary_values.color, '', '多个同名选项不擅自选择')
const multiple = { summary_fields: [{ ...field('capacity', 'checkbox', [{ label: '256G', value: 3 }]), selection_mode: 'multiple' }], summary_values: {} }
helper.prefillDeviceSummary(multiple, mapped)
equal(multiple.summary_values.capacity, [3], '多选值保持数组契约')
helper.recordModelMatch(row, 'manual')
equal(row.device_readings.model_match.category_id, 77, '记录管理员最终选择的分类')
for (const [value, expected] of [[{}, ''], [{ status: 'Not Activated' }, '未激活'], [{ status: 'In Warranty' }, '在保'], [{ status: 'In Warranty', date: '2027-09-01' }, '保 2027-09-01'], [{ status: 'Out Of Warranty' }, '过保'], [{ date: '2027-09-01' }, '2027-09-01']]) {
    equal(helper.parseCoverageStatus(value), expected, '保修不把空结果推断成未激活')
}
const payloads = load(`${base}deviceUtil.ts`)
for (const payload of [payloads.normalizeDevice(row), payloads.buildUpdatePayload(row)]) {
    equal(payload.device_readings.local.raw.battery.level_percent, 98, '新增/编辑保留原文')
    equal([payload.imei2, payload.serial_number], ['TEST-IMEI-02', 'TEST-SN-01'], '新增/编辑保留 IMEI2 与 SN')
    equal(payload.summary.battery, 87, '业务使用人工确认值，原文仍是 100')
}

// 真实 Vue 组件配合 Element Plus 边界桩，验证统一抽屉契约及原文的惰性、安全展示。
function node(type, text = '') { return { type, text, children: [], props: {}, style: {}, parent: null } }
const renderer = vue.createRenderer({
    createElement: type => node(type), createText: text => node('#text', text), createComment: text => node('#comment', text),
    setText: (n, text) => { n.text = text }, setElementText: (n, text) => { n.text = text; n.children = [] },
    parentNode: n => n.parent, nextSibling: n => n.parent?.children[n.parent.children.indexOf(n) + 1] || null,
    patchProp: (n, key, prev, next) => { n.props[key] = next },
    insert(n, parent, anchor) { if (n.parent) n.parent.children.splice(n.parent.children.indexOf(n), 1); n.parent = parent; const i = parent.children.indexOf(anchor); parent.children.splice(i < 0 ? parent.children.length : i, 0, n) },
    remove(n) { if (n.parent) n.parent.children.splice(n.parent.children.indexOf(n), 1) }
})
const collect = (n, type) => [...(n.type === type ? [n] : []), ...n.children.flatMap(child => collect(child, type))]
const textContent = n => n.text + n.children.map(textContent).join('')
async function main() {
    const container = node('root'), resetKey = vue.ref('one')
    const Component = load('admin/src/addon/hsx_components/components/HsxDataArchive/index.vue').default
    const archiveData = vue.ref({ version: 1, local: { raw: '<script>unsafe</script>' } })
    const beforeView = plain(archiveData.value)
    let drawerProps, stopped = 0
    const app = renderer.createApp({ render: () => vue.h(Component, { data: archiveData.value, resetKey: resetKey.value, labels: { local: '设备原始读取' } }) })
    app.directive('loading', {})
    app.component('el-button', {
        inheritAttrs: false,
        props: ['nativeType'],
        setup(props, { attrs, slots }) { return () => vue.h('button', { ...attrs, type: props.nativeType || 'button' }, slots.default?.()) }
    })
    app.component('el-drawer', {
        inheritAttrs: false,
        props: ['modelValue', 'size', 'appendToBody', 'closeOnClickModal', 'closeOnPressEscape', 'showClose', 'destroyOnClose'],
        setup(props, { attrs, slots }) {
            return () => {
                drawerProps = { ...attrs, ...props }
                return props.modelValue ? vue.h('section', { role: 'dialog' }, [slots.header?.({ titleId: 'archive-title' }), slots.default?.(), slots.footer?.()]) : null
            }
        }
    })
    app.mount(container)
    equal(collect(container, 'pre').length, 0, '默认只显示按钮，不渲染原文')
    equal(collect(container, 'section').length, 0, '默认不打开抽屉')
    equal(collect(container, 'button').length, 1, '不额外显示折叠面板和说明文字')
    equal(textContent(collect(container, 'button')[0]).trim(), '查看采集档案', '入口文案清晰')
    equal(collect(container, 'button')[0].props.type, 'button', '嵌入表单时不触发提交')
    const openArchive = async () => {
        collect(container, 'button')[0].props.onClick({ stopPropagation() { stopped++ } })
        await vue.nextTick()
    }
    await openArchive()
    equal(stopped, 1, '查看按钮不触发设备卡片的其他点击动作')
    equal(collect(container, 'section').length, 1, '点击后打开统一抽屉')
    equal(collect(container, 'button')[0].props['aria-expanded'], true, '同步入口的展开状态')
    equal(drawerProps.size, 'min(720px, 100vw)', '抽屉适配窄视口，不撑开页面')
    equal(drawerProps.appendToBody, true, '嵌套详情/质检弹窗时挂到 body，避免被父级裁切')
    equal([drawerProps.closeOnClickModal, drawerProps.closeOnPressEscape, drawerProps.showClose, drawerProps.destroyOnClose], [true, true, true, true], '只读抽屉支持遮罩、ESC 和关闭，关闭销毁内容')
    equal(collect(container, 'pre').length, 1, '打开后才渲染原文')
    equal(textContent(collect(container, 'summary')[0]), '设备原始读取', '保留分组查看')
    equal(collect(container, 'pre')[0].text.includes('<script>unsafe</script>'), true, '原文作为文字')
    equal(collect(container, 'script').length, 0, '原文不会生成脚本节点')
    collect(container, 'button').find(button => textContent(button).trim() === '关闭').props.onClick()
    await vue.nextTick()
    equal(collect(container, 'section').length, 0, '关闭按钮关闭抽屉')
    equal(collect(container, 'pre').length, 0, '关闭后不保留长原文节点')
    await openArchive()
    drawerProps['onUpdate:modelValue'](false); await vue.nextTick()
    equal(collect(container, 'section').length, 0, '透传抽屉自身的关闭事件')
    await openArchive()
    resetKey.value = 'two'; await vue.nextTick()
    equal(collect(container, 'pre').length, 0, '切换设备自动关闭，避免看错档案')
    equal(archiveData.value, beforeView, '查看与关闭不修改已填写的数据')
    await openArchive()
    archiveData.value = { version: 1 }; await vue.nextTick()
    equal(collect(container, 'button').length, 0, '没有档案时不留无效入口或占位')
    equal(collect(container, 'section').length, 0, '清空数据同时移除已打开的抽屉')
    archiveData.value = beforeView; await vue.nextTick()
    equal(collect(container, 'section').length, 0, '新档案出现后不会自动打开')
    app.unmount()

    const sourceFiles = [
        ...['deviceReadings.ts', 'types.ts', 'useLocalDevice.ts', 'deviceUtil.ts', 'DeviceEntryCard.vue', 'DeviceEntryList.vue', 'WarrantyQueryInput.vue', 'CheckSummaryFields.vue', 'CheckSummaryDialog.vue'].map(file => `${base}${file}`),
        ...['CheckDeviceDialog.vue', 'DeviceConfirmDialog.vue', 'DeviceDetailDialog.vue', 'composables/useCheckMeta.ts'].map(file => `admin/src/addon/hsx_recycle/views/recycle_order/components/${file}`),
        'admin/src/addon/hsx_components/components/HsxDataArchive/index.vue', 'admin/src/addon/hsx_components/core.ts', 'admin/src/addon/hsx_components/index.ts',
        'admin/src/addon/hsx_erp/views/erp/stock/list.vue'
    ]
    for (const file of sourceFiles) {
        let source = fs.readFileSync(path.join(root, file), 'utf8')
        const mirror = file.replace(/^admin\/src\/addon\/([^/]+)\//, 'niucloud/addon/$1/admin/')
        equal(source, fs.readFileSync(path.join(root, mirror), 'utf8'), `插件源码同步 ${file}`)
        if (file.endsWith('.vue')) {
            const parsed = compiler.parse(source, { filename: file }); equal(parsed.errors, [], `SFC 解析 ${file}`)
            const compiled = compiler.compileScript(parsed.descriptor, { id: file })
            const template = compiler.compileTemplate({ source: parsed.descriptor.template.content, filename: file, id: file, compilerOptions: { bindingMetadata: compiled.bindings, expressionPlugins: ['typescript'] } })
            equal(template.errors, [], `模板编译 ${file}`)
            source = compiled.content
        }
        const result = ts.transpileModule(source, { reportDiagnostics: true, compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.CommonJS } })
        equal((result.diagnostics || []).filter(d => d.category === ts.DiagnosticCategory.Error), [], `TS 编译 ${file}`)
    }
    console.log(`PASS device readings contract: ${count} assertions.`)
}
main().catch(error => { console.error(error); process.exit(1) })
