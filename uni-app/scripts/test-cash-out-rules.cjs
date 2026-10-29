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
const base = { min: '1', rate: '1', isAutoVerify: '0', transferTypes: ['wechatpay', 'bank'], accountType: 'money' }
const section = (options, key) => build({ ...base, ...options }).find(item => item.key === key).paragraphs.join('\n')
const all = options => build({ ...base, ...options }).map(item => `${item.title}\n${item.paragraphs.join('\n')}`).join('\n')
let checks = 0
const check = (title, test) => { test(); checks++; console.log('PASS', title) }
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
    assert.equal(JSON.stringify(value), before)
})
console.log(`完成：${checks}项提现规则检查；只执行纯文案函数，无网络、数据库或真实提现。`)
