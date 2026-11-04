import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import vm from 'node:vm'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'

// Execute the actual admin SFC setup with isolated API doubles; no HTTP or DB writes.
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../../..')
const require = createRequire(path.join(root, 'admin/package.json'))
const Vue = require('vue'), compiler = require('@vue/compiler-sfc'), ts = require('typescript')
let checks = 0
const check = (value, label) => { assert.ok(value, label); checks++ }
const writes = []
const config = { enabled: true, publishing_required: true, publishing_price: 99, certification_price: 299, certification_months: 12, commission_minimum: 50, fee_percent: 10, fee_minimum: 9.9, fee_confirmed: true, grades: [{ id: 'S', name: 'S级', rank: 1, status: true, min_inventory: 0, all_models: true }] }
function setup(name) {
    const file = path.join(root, 'niucloud/addon/hsx_lead/admin/views/cooperation', name + '.vue')
    const source = fs.readFileSync(file, 'utf8')
    const parsed = compiler.parse(source, { filename: file })
    assert.deepEqual(parsed.errors, [])
    const template = compiler.compileTemplate({ source: parsed.descriptor.template.content, filename: file, id: name })
    assert.deepEqual(template.errors, [])
    check(source.includes('永久有效') && !/填\s*0\s*为永久|按\s*0\s*保存/.test(source), name + ' exposes a named permanent option, not numeric implementation details')
    const compiled = compiler.compileScript(parsed.descriptor, { id: name })
    const js = ts.transpileModule(compiled.content, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
    const module = { exports: {} }
    const api = { getCooperationConfig: async () => ({ data: config }), saveCooperationConfig: async data => { writes.push(data); return { data } }, getCooperationPartners: async () => ({ data: { data: [], grades: [], total: 0 } }), saveCooperationPartner: async (id, data) => { writes.push({ id, ...data }); return { data } }, blockCooperationPartner() {} }
    const common = { coopError: cause => String(cause?.message || cause), coopMoney: String, coopTime: String, useCooperationList: () => ({ rows: Vue.ref([]), total: Vue.ref(0), loading: Vue.ref(false), error: Vue.ref(''), query: Vue.reactive({ page: 1, limit: 20 }), load: async () => {}, search: async () => {}, changePage() {} }) }
    const deps = { vue: { ...Vue, onMounted() {}, onBeforeUnmount() {} }, 'element-plus': { ElMessage: { success() {} }, ElMessageBox: {} }, '@element-plus/icons-vue': { Plus: {}, Refresh: {} }, '../../api/cooperation': api, '../../composables/cooperation': common, '@/stores/modules/user': { default: () => ({ rules: [] }) }, '../../composables/navigation': { hasLeadPagePermission: (user, key) => user.rules.includes(key) } }
    const dependency = key => key.endsWith('.vue') ? { default: {} } : key in deps ? deps[key] : (() => { throw Error('Unexpected dependency ' + key) })()
    vm.runInNewContext(`(function(require,module,exports){${js}\n})`, { Date, Number, String, Math, Set, JSON, console })(dependency, module, module.exports)
    return module.exports.default.setup({}, { expose() {} })
}

const settings = setup('Settings')
Object.assign(settings.form, structuredClone(config))
check(!settings.permanentCertification.value, 'finite configured duration selects the named limited option')
settings.permanentCertification.value = true
check(settings.form.certification_months === 0, 'permanent UI choice maps to existing server representation')
settings.ready.value = true
await settings.save()
check(writes.at(-1).certification_months === 0, 'saving permanent business configuration sends its duration without changing fees')
settings.permanentCertification.value = false
check(settings.form.certification_months === 12 && !settings.permanentCertification.value, 'switching back to a finite duration starts with an editable valid month count')

const partners = setup('Partners')
partners.edit({ id: 7, name: '合成商户', certification_status: 'active', cert_expires_at: 0 })
check(partners.certPermanent.value === true, 'existing permanent merchant opens with permanent selected')
partners.form.remark = '合成资格测试'
await partners.save()
check(writes.at(-1).id === 7 && writes.at(-1).cert_expires_at === 0, 'permanent merchant save does not require an expiry date')
partners.edit({ id: 7, certification_status: 'active', cert_expires_at: Math.floor(Date.now() / 1000) + 86400 })
check(!partners.certPermanent.value && partners.certUntil.value instanceof Date, 'finite merchant preserves its real date')
partners.form.remark = '合成资格测试'
partners.certUntil.value = undefined
const count = writes.length
await partners.save()
check(writes.length === count && partners.saveError.value.includes('截止时间'), 'limited selection without a date cannot silently become permanent')
partners.certPermanent.value = true
partners.certUntil.value = new Date(Date.now() - 86400000)
await partners.save()
check(writes.at(-1).cert_expires_at === 0, 'permanent selection ignores the hidden prior finite date')
console.log(`PASS ${checks} permanent admin UI checks; actual SFC setup with synthetic APIs, no HTTP or database mutations.`)
