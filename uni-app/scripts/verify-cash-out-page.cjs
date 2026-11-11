// Execute the real SFC setup with isolated stores/APIs. No network, DB or transfers.
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const { createRequire } = require('node:module')
const root = path.resolve(__dirname, '..')
const local = createRequire(path.join(root, 'package.json'))
const vue = local('vue')
const sfc = local('@vue/compiler-sfc')
const ts = local('typescript')
const filename = path.join(root, 'src/app/pages/member/apply_cash_out.vue')
const source = fs.readFileSync(filename, 'utf8')
const { descriptor, errors } = sfc.parse(source, { filename })
assert.deepEqual(errors, [])
const script = sfc.compileScript(descriptor, { id: 'cash-out-page-test' })
assert.deepEqual(sfc.compileTemplate({ source: descriptor.template.content, filename, id: 'cash-out-page-test', compilerOptions: { bindingMetadata: script.bindings } }).errors, [])
for (const style of descriptor.styles) assert.deepEqual(sfc.compileStyle({ source: style.content, filename, id: 'cash-out-page-test', preprocessLang: style.lang, preprocessOptions: { includePaths: [path.join(root, 'node_modules')] } }).errors, [])
const code = ts.transpileModule(script.content, { fileName: filename + '.ts', reportDiagnostics: true, compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } })
assert.deepEqual(code.diagnostics, [])
const helperFile = path.join(root, 'src/app/utils/cash-out-rules.ts')
const helperCode = ts.transpileModule(fs.readFileSync(helperFile, 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText
const helper = { exports: {} }
vm.runInNewContext(helperCode, helper, { filename: helperFile })

const fixtureConfig = { is_open: 1, min: '50.00', rate: '2.5', is_auto_verify: 0, is_auto_transfer: 0, transfer_type: ['bank'] }
const account = { account_id: 12, realname: '隔离样本', account_no: 'TEST_ONLY', bank_name: '测试银行' }
const calls = [], loads = [], shows = [], toasts = []
const memberStore = vue.reactive({ info: { money: '200.00', commission: '300.00' }, getMemberInfo(done) { calls.push('memberInfo'); if (done) done() } })
const copy = value => JSON.parse(JSON.stringify(value))
const api = {
    async cashOutConfig() { calls.push('config'); return { data: copy(fixtureConfig) } },
    async cashOutApply(data) { calls.push(['apply', copy(data)]); return { data: 901 } },
    async getFirstCashOutAccountInfo() { calls.push('account'); return { data: copy(account) } },
    async getCashoutAccountInfo() { calls.push('selectedAccount'); return { data: copy(account) } }
}
const sandbox = { exports: {}, console, setTimeout() { throw Error('Unexpected delayed browser operation') },
    getCurrentPages: () => [{}],
    uni: {
        getStorageSync(key) { return key === 'openid' ? 'ISOLATED_OPENID' : key === 'cashOutAccountType' ? 'money' : '' },
        showToast(value) { toasts.push(value.title) }, navigateBack() { calls.push('back') },
        $u: { test: { isEmpty: value => value === '' || value == null, amount: value => /^\d+(?:\.\d{1,2})?$/.test(String(value)) } }
    },
    require(id) {
        if (id === 'vue') return vue
        if (id === '@dcloudio/uni-app') return { onLoad(fn) { loads.push(fn) }, onShow(fn) { shows.push(fn) } }
        if (id === '@/locale') return { t: key => key === 'currency' ? '¥' : key }
        if (id === '@/utils/common') return { moneyFormat: value => Number(value || 0).toFixed(2), redirect: value => calls.push(['redirect', value]), getToken: () => true, img: value => value, deepClone: copy, getWinxinOpenId: () => ({}) }
        if (id === '@/stores/member') return { default: () => memberStore }
        if (id === '@/app/api/member') return api
        if (id === '@/app/utils/cash-out-rules') return helper.exports
        if (id.includes('/nc-contact/')) return { default: {} }
        throw Error('Unexpected dependency: ' + id)
    }
}
vm.runInNewContext(code.outputText, sandbox, { filename })
const state = sandbox.exports.default.setup({}, { expose() {} })
const flush = async () => { for (let i = 0; i < 8; i++) await Promise.resolve() }
let checks = 0
const check = (label, fn) => { fn(); checks++; console.log('PASS', label) }
const rulesText = () => JSON.stringify(state.cashOutRules.value)
const summaryText = () => JSON.stringify(state.cashOutRuleSummary.value)
const summaryValue = key => state.cashOutRuleSummary.value.find(item => item.key === key).value
const findTemplateElements = (node, parents = [], found = []) => {
    if (node.type === 1) found.push({ node, parents })
    for (const child of node.children || []) findTemplateElements(child, [...parents, node], found)
    return found
}
async function main() {
    check('实际Vue脚本、模板及SCSS均可编译', () => assert.ok(state))
    check('规则由v-for渲染，标题不再冒充最低金额', () => {
        assert.match(descriptor.template.content, /v-for=["'][^"']*cashOutRules/)
        assert.ok(!descriptor.template.content.includes('>最小提现金额为</view>'))
        assert.match(descriptor.template.content, /提现金额/)
    })
    check('六项关键规则默认可见且位于到账方式前，不受详情折叠开关控制', () => {
        const elements = findTemplateElements(descriptor.template.ast)
        const summary = elements.find(({ node }) => node.props.some(prop => prop.name === 'class' && /(?:^|\s)cash-out-rule-summary(?:\s|$)/.test(prop.value?.content || '')))
        assert.ok(summary, '必须存在独立关键规则卡片')
        assert.ok(!summary.node.props.some(prop => prop.type === 7 && ['if', 'show'].includes(prop.name)), '关键规则卡片自身不可条件隐藏')
        assert.ok(!summary.parents.some(node => (node.props || []).some(prop => prop.type === 7 && /showRuleDetails/.test(prop.exp?.content || ''))), '关键规则不可放进详情折叠容器')
        assert.ok(descriptor.template.content.indexOf('cash-out-rule-summary') < descriptor.template.content.indexOf('>到账方式<'))
        assert.match(descriptor.template.content, /v-for=["']rule in cashOutRuleSummary["']/)
        assert.deepEqual(Array.from(state.cashOutRuleSummary.value, item => item.label), ['可提现额度', '最低申请金额', '每日提现次数', '申请时间', '到账时间', '提现手续费'])
        assert.equal(state.showRuleDetails.value, false)
    })
    check('详细规则有独立展开收起动作，不新增同意、倒计时或提现按钮禁用门槛', () => {
        assert.match(descriptor.template.content, /@click=["']showRuleDetails = !showRuleDetails["']/)
        assert.match(descriptor.template.content, /v-if=["']showRuleDetails["']/)
        const submit = descriptor.template.content.match(/<button\b[^>]*@click=["']cashOut["'][^>]*>/)?.[0]
        assert.ok(submit)
        assert.doesNotMatch(submit, /showRuleDetails|agree|countdown|readRules/i)
        assert.match(descriptor.template.content, /更多提现说明/)
    })
    check('复用客服组件且没有增加同意规则的强制复选框', () => {
        assert.match(descriptor.template.content, /<nc-contact\b/)
        assert.ok(!/<(?:checkbox|up-checkbox|u-checkbox)\b/.test(descriptor.template.content))
        assert.match(descriptor.template.content, /@click=["']cashOut["']/)
    })
    assert.equal(loads.length, 1)
    await loads[0]({}); await flush()
    check('页面沿用配置接口及收款账户加载，不修改配置', () => {
        assert.equal(state.pageLoading.value, false)
        assert.equal(state.config.min, '50.00')
        assert.equal(state.applyData.transfer_type, 'bank')
        assert.equal(state.applyData.account_id, 12)
        assert.equal(calls.filter(item => item === 'config').length, 1)
    })
    check('最低金额和费率使用真实配置，长规则体现3自然日而非3工作日', () => {
        assert.match(rulesText(), /50(?:\.00)?/)
        assert.match(rulesText(), /2\.5%/)
        assert.match(rulesText(), /3\s*个?自然日/)
        assert.doesNotMatch(rulesText(), /3\s*个?工作日|每日最多.{0,5}3\s*次|9[:：]00.{0,3}21[:：]00/)
        assert.match(summaryValue('available'), /当前200\.00元/)
        assert.match(summaryValue('minimum'), /50\.00元/)
        assert.match(summaryValue('fee'), /2\.5%/)
        assert.match(summaryValue('frequency'), /不设额外次数限制/)
        assert.match(summaryValue('application-time'), /每天24小时/)
        assert.match(summaryValue('arrival-time'), /3个自然日/)
    })
    state.config.min = '0.30'; state.config.rate = '0'; await flush()
    check('后台最低金额和费率变化后规则会重算', () => {
        assert.match(rulesText(), /0\.30/)
        assert.doesNotMatch(rulesText(), /2\.5%/)
        assert.match(rulesText(), /不收取|免.*手续费|费率.*0%/)
        assert.match(summaryValue('minimum'), /0\.30元/)
        assert.match(summaryValue('fee'), /不收取提现手续费/)
        assert.doesNotMatch(summaryText(), /50\.00元|2\.5%/)
    })
    state.applyData.account_type = 'commission'; await flush()
    check('同一页面切换佣金提现时摘要与规则使用佣金账户金额', () => {
        assert.match(rulesText(), /佣金/)
        assert.match(summaryValue('available'), /当前300\.00元/)
        assert.doesNotMatch(summaryValue('available'), /200\.00元|500\.00元/)
    })
    state.applyData.account_type = 'money'; memberStore.info.money = '123.45'; await flush()
    check('账户刷新后可提现额度实时变化，不沿用旧余额', () => assert.match(summaryValue('available'), /当前123\.45元/))
    state.config.min = undefined; state.config.rate = undefined; memberStore.info.money = undefined; await flush()
    check('实际页面摘要缺失配置时不伪造默认金额或免手续费', () => {
        assert.match(summaryValue('available'), /读取中/)
        assert.match(summaryValue('minimum'), /未读取成功/)
        assert.match(summaryValue('fee'), /未读取成功/)
        assert.doesNotMatch(summaryText(), /当前0\.00元|1\.00元|不收取提现手续费/)
    })
    memberStore.info.money = '200.00'
    state.applyData.account_type = 'money'; state.config.min = '50'; state.config.rate = '2.5'
    state.applyData.apply_money = ''
    state.cashOut(); await flush()
    check('空金额不调用提现接口', () => { assert.equal(calls.filter(item => Array.isArray(item) && item[0] === 'apply').length, 0); assert.match(toasts.at(-1), /金额/) })
    state.applyData.apply_money = '10'
    state.cashOut(); await flush()
    check('低于后台最低金额仍会拦截，未为审核绕过业务门槛', () => { assert.equal(calls.filter(item => Array.isArray(item) && item[0] === 'apply').length, 0); assert.match(toasts.at(-1), /最低/) })
    state.applyData.apply_money = '201'
    state.cashOut(); await flush()
    check('超过可提现金额仍被拦截', () => { assert.equal(calls.filter(item => Array.isArray(item) && item[0] === 'apply').length, 0); assert.match(toasts.at(-1), /超出/) })
    state.applyData.apply_money = '100'
    state.showRuleDetails.value = false
    state.cashOut(); await flush()
    check('合法金额仍走原cashOutApply并跳转详情，所有调用均为spy', () => {
        const applies = calls.filter(item => Array.isArray(item) && item[0] === 'apply')
        assert.equal(applies.length, 1); assert.equal(applies[0][1].apply_money, '100'); assert.equal(applies[0][1].account_id, 12)
        assert.ok(calls.some(item => Array.isArray(item) && item[0] === 'redirect' && item[1].url === '/app/pages/member/cash_out_detail'))
        assert.equal(state.loading.value, false)
    })
    state.showRuleDetails.value = true; await flush()
    check('展开详情不会隐藏或改变六项关键规则', () => {
        assert.equal(state.cashOutRuleSummary.value.length, 6)
        assert.match(summaryValue('available'), /当前200\.00元/)
        assert.match(summaryValue('minimum'), /50\.00元/)
    })
    state.cashOut(); await flush()
    check('展开与收起详情都可正常申请，不要求阅读或勾选规则', () => {
        const applies = calls.filter(item => Array.isArray(item) && item[0] === 'apply')
        assert.equal(applies.length, 2)
        assert.equal(applies[1][1].apply_money, '100')
        assert.equal(state.loading.value, false)
    })
    console.log('完成：' + checks + '项实际页面隔离检查；未联网、未访问数据库或发起真实提现。')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
