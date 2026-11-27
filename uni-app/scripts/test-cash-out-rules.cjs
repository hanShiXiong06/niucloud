const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const ts = require('../node_modules/typescript')
const file = path.resolve(__dirname, '../src/app/utils/cash-out-rules.ts')
const source = fs.readFileSync(file, 'utf8')
const result = ts.transpileModule(source, { compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.CommonJS }, reportDiagnostics: true })
assert.deepEqual(result.diagnostics, [])
const sandbox = { exports: {} }
vm.runInNewContext(result.outputText, sandbox, { filename: file })
const build = sandbox.exports.buildCashOutRules
const buildSummary = sandbox.exports.buildCashOutRuleSummary
const base = { min: '1', rate: '1', isAutoVerify: '0', transferTypes: ['wechatpay', 'bank'], accountType: 'money' }
const section = (options, key) => build({ ...base, ...options }).find(item => item.key === key).paragraphs.join('\n')
const all = options => build({ ...base, ...options }).map(item => `${item.title}\n${item.paragraphs.join('\n')}`).join('\n')
const summary = options => buildSummary({ ...base, availableMoney: '200.00', ...options })
const summaryValue = (options, key) => summary(options).find(item => item.key === key).value
let checks = 0
const check = (title, test) => { test(); checks++; console.log('PASS', title) }
check('默认摘要包含六项审核关键规则，标签与key稳定且不重复', () => {
    const items = summary({})
    assert.deepEqual(Array.from(items, item => item.key), ['available', 'minimum', 'frequency', 'application-time', 'arrival-time', 'fee'])
    assert.deepEqual(Array.from(items, item => item.label), ['可提现额度', '最低申请金额', '每日提现次数', '申请时间', '到账时间', '提现手续费'])
    assert.equal(new Set(items.map(item => item.key)).size, 6)
    assert.ok(items.every(item => typeof item.value === 'string' && item.value.length > 0))
})
check('摘要可提现额度与最低金额动态读取，不写死额度或1元门槛', () => {
    assert.match(summaryValue({ availableMoney: '13.47', min: '0.30' }, 'available'), /当前13\.47元/)
    assert.match(summaryValue({ availableMoney: '309.99' }, 'available'), /当前309\.99元/)
    assert.match(summaryValue({ min: '20.50' }, 'minimum'), /20\.50元/)
    assert.match(summaryValue({ min: 0 }, 'minimum'), /金额须大于0，未设额外最低门槛/)
    assert.match(summaryValue({ availableMoney: 0 }, 'available'), /当前0\.00元/)
})
check('摘要随费率变化且明确费用从本次申请金额扣除', () => {
    assert.match(summaryValue({ rate: '2.5' }, 'fee'), /2\.5%.*从本次申请金额中扣除/)
    assert.match(summaryValue({ rate: '0.6' }, 'fee'), /0\.6%/)
    assert.match(summaryValue({ rate: 0 }, 'fee'), /不收取提现手续费/)
})
check('余额与佣金摘要分别接受对应可提金额，不把两个账户合并', () => {
    assert.match(summaryValue({ accountType: 'money', availableMoney: '200.00' }, 'available'), /当前200\.00元/)
    assert.match(summaryValue({ accountType: 'commission', availableMoney: '300.00' }, 'available'), /当前300\.00元/)
    assert.doesNotMatch(summaryValue({ accountType: 'commission', availableMoney: '300.00' }, 'available'), /500\.00元|200\.00元/)
})
check('摘要缺失或非法金额配置提示重试，不虚构余额、门槛或费率', () => {
    for (const invalid of [undefined, null, '', false, -1, NaN, Infinity, 'not-a-number']) {
        assert.match(summaryValue({ availableMoney: invalid }, 'available'), /读取中/)
        assert.match(summaryValue({ min: invalid }, 'minimum'), /未读取成功/)
        assert.match(summaryValue({ rate: invalid }, 'fee'), /未读取成功/)
    }
    assert.match(summaryValue({ rate: 101 }, 'fee'), /未读取成功/)
    assert.doesNotMatch(summaryValue({ availableMoney: undefined }, 'available'), /当前0\.00元/)
    assert.doesNotMatch(summaryValue({ min: undefined }, 'minimum'), /1\.00元|0\.00元/)
    assert.doesNotMatch(summaryValue({ rate: undefined }, 'fee'), /不收取|0%/)
})
check('摘要直出无额外次数限制与全天申请，不捏造每日3次或固定营业时段', () => {
    assert.match(summaryValue({}, 'frequency'), /不设额外次数限制/)
    assert.match(summaryValue({}, 'application-time'), /每天24小时可提交申请/)
    const text = summary({}).map(item => item.value).join('\n')
    assert.doesNotMatch(text, /每日最多.{0,4}3次|9[:：]00|21[:：]00|9[—-]21/)
})
check('摘要到账承诺使用既有3自然日共用周期，不承诺自动或即时到账', () => {
    const text = summaryValue({}, 'arrival-time')
    assert.match(text, /正常情况下，申请成功后3个自然日内完成审核及打款/)
    assert.doesNotMatch(text, /工作日|秒到账|立即到账|自动到账|再.{0,3}3/)
})
check('完整18节，每节具有稳定key、标题与正文', () => {
    const rules = build(base)
    assert.equal(rules.length, 18)
    assert.equal(new Set(rules.map(item => item.key)).size, 18)
    assert.ok(rules.every(item => item.title && item.paragraphs.length && item.paragraphs.every(text => typeof text === 'string' && text.length > 10)))
})
check('最低金额随站点配置变化，不写死1元', () => {
    assert.match(section({ min: 0.3 }, 'minimum'), /0\.30元/)
    assert.match(section({ min: '20.50' }, 'minimum'), /20\.50元/)
    assert.doesNotMatch(section({ min: '20.50' }, 'minimum'), /最低申请金额为1\.00元/)
})
check('零门槛与缺失配置不会被文案错误转成1元', () => {
    assert.match(section({ min: 0 }, 'minimum'), /未设置额外的最低金额门槛/)
    assert.match(section({ min: '' }, 'minimum'), /尚未成功读取/)
    assert.match(section({ min: -1 }, 'minimum'), /尚未成功读取/)
})
check('手续费按配置披露，0费率明确免该项手续费', () => {
    assert.match(section({ rate: 0 }, 'fee'), /0%/)
    assert.match(section({ rate: '0.6' }, 'fee'), /0\.6%/)
    assert.match(section({ rate: '2.5' }, 'fee'), /2\.5%/)
    assert.match(section({ rate: '' }, 'fee'), /尚未成功读取/)
    assert.match(section({ rate: 101 }, 'fee'), /尚未成功读取/)
})
check('只有真实开放的方式展示，去重并排除未知类型', () => {
    const text = section({ transferTypes: ['bank', 'bank', 'unknown'] }, 'methods')
    assert.match(text, /银行卡（人工转账）/)
    assert.doesNotMatch(text, /微信零钱|支付宝|unknown/)
    assert.equal((text.match(/银行卡（人工转账）/g) || []).length, 1)
    assert.match(section({ transferTypes: [] }, 'methods'), /没有可用的收款方式/)
})
check('微信在线确认段仅在开通wechatpay时出现', () => {
    assert.match(section({ transferTypes: ['wechatpay'] }, 'confirmation'), /微信侧操作/)
    assert.doesNotMatch(section({ transferTypes: ['wechat_code', 'alipay'] }, 'confirmation'), /审核通过后还需要进入该笔提现详情/)
    assert.match(section({ transferTypes: ['wechat_code', 'alipay'] }, 'confirmation'), /人工转账/)
})
check('自动审核与人工审核动态区分，不把审核通过写成已到账', () => {
    assert.match(section({ isAutoVerify: 1 }, 'audit'), /自动审核设置/)
    assert.match(section({ isAutoVerify: '0' }, 'audit'), /人工审核设置/)
    assert.match(section({ isAutoVerify: 1 }, 'audit'), /不等于已经转账/)
})
check('只解释本次资金账户，不把积分或储值混为可提现现金', () => {
    assert.match(section({ accountType: 'money' }, 'scope'), /可提现余额账户/)
    assert.match(section({ accountType: 'commission' }, 'scope'), /佣金账户/)
    assert.match(section({}, 'scope'), /积分、优惠券等不等同于/)
})
check('按用户确认的3个自然日共用周期，不另加工作日或第二轮3天', () => {
    const text = section({}, 'time')
    assert.match(text, /3个自然日内完成审核及打款处理/)
    assert.match(text, /共用这一处理周期/)
    assert.doesNotMatch(text, /工作日|72小时|秒到账/)
})
check('审核拒绝及成功取消返还原申请金额，转账失败不虚构自动返还', () => {
    assert.match(section({}, 'rejected'), /原申请金额会返还/)
    assert.match(section({}, 'cancel'), /取消成功后，原申请金额返还/)
    assert.match(section({}, 'failure'), /并不代表系统已经自动返还/)
    assert.doesNotMatch(section({}, 'failure'), /转账失败后自动退回|失败秒退/)
})
check('低额余额不承诺未实现自动结清，也不要求充值拉新', () => {
    const text = section({}, 'small-balance')
    assert.match(text, /联系商家/)
    assert.match(text, /不会自动将低于门槛的余额转出/)
    assert.match(text, /请勿为凑提现金额而额外充值、消费或邀请他人/)
})
check('移除虚构每日3次、9-21点、1-3工作日及最终解释权', () => {
    const text = all({ min: 0.3, rate: 0.6 })
    assert.doesNotMatch(text, /每日最多.{0,4}3次|9:00|21:00|9[—-]21|1[—-]3.{0,3}工作日|最终解释权|逾期清零|无条件取消/)
    assert.match(text, /没有额外设置每日提现次数或固定提交时段限制/)
})
check('生成规则不修改传入的配置或通道数组', () => {
    const value = { ...base, transferTypes: ['bank', 'bank'] }
    const before = JSON.stringify(value)
    build(value)
    buildSummary(value)
    assert.equal(JSON.stringify(value), before)
})
console.log(`完成：${checks}项提现规则检查；只执行纯文案函数，无网络、数据库或真实提现。`)
