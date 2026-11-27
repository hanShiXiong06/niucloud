const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const assert = require('node:assert/strict')
const root = path.resolve(__dirname, '..')
const dep = (name) => require(require.resolve(name, { paths: [path.join(root, 'site-uniapp'), path.join(root, 'uni-app')] }))
const sfc = dep('@vue/compiler-sfc')
const ts = dep('typescript')
const vue = dep('vue')
let checks = 0
const check = (test, name) => { assert.ok(test, name); checks++; console.log('PASS ' + name) }
const filename = path.join(root, 'site-uniapp/src/addon/hsx_recycle/pages/stats/index.vue')
const source = fs.readFileSync(filename, 'utf8')
const parsed = sfc.parse(source, { filename })
check(parsed.errors.length === 0, '移动统计页 SFC 解析通过')
const script = sfc.compileScript(parsed.descriptor, { id: 'recycle-stats' })
const template = sfc.compileTemplate({ source: parsed.descriptor.template.content, filename,
    id: 'recycle-stats', compilerOptions: { bindingMetadata: script.bindings } })
check(template.errors.length === 0, '统计说明、错误状态、单位和模板表达式编译通过')

const requests = []
let showHook, modal
const request = (type, params) => new Promise((resolve, reject) => requests.push({ type, params, resolve, reject }))
const context = {
    exports: {}, console,
    uni: { stopPullDownRefresh() {}, showModal(value) { modal = value } },
    require(id) {
        if (id === 'vue') return vue
        if (id === '@dcloudio/uni-app') return { onShow(fn) { showHook = fn }, onPullDownRefresh() {} }
        if (id.endsWith('/api/stats')) return { getDashboardOverview: (p) => request('overview', p), getDashboardTrend: (p) => request('trend', p) }
        if (id.endsWith('/api/task')) return { getStatBoard: (p) => request('board', p) }
        if (id === '@/utils/common') return { redirect() {} }
        if (id.endsWith('.vue')) return {}
        throw new Error('Unexpected import ' + id)
    }
}
const testScript = parsed.descriptor.scriptSetup.content + '\nexport { loadStats, switchDate, currentDate, statsData, trendData, stageBoard, loading, loadError, showStageExplain, showFinanceExplain };'
const compiled = ts.transpileModule(testScript, { reportDiagnostics: true,
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } })
check((compiled.diagnostics || []).length === 0, 'TypeScript 编译通过')
vm.runInNewContext(compiled.outputText, context, { filename })
const api = context.exports
const flush = () => new Promise((resolve) => setImmediate(resolve))
const resolveBatch = (start, marker, board = { stages: [{ stage_key: 'check', count: 3, unit: '台' }], explain: '当前有效任务' }) => {
    requests.slice(start, start + 3).forEach((entry) => entry.resolve({ data: entry.type === 'board' ? board : { marker } }))
}

;(async () => {
    const first = api.loadStats()
    check(requests.length === 3 && api.loading.value, '首次加载请求三类统计并显示加载态')
    api.switchDate('month')
    check(requests.length === 6 && api.currentDate.value === 'month', '快速切换日期会发起新查询，不被旧请求吞掉')
    check(requests[3].params.start_time !== requests[0].params.start_time, '新请求使用新日期区间')
    resolveBatch(3, 'month')
    await flush()
    check(api.statsData.value.marker === 'month' && !api.loading.value, '新日期结果正常展示')
    resolveBatch(0, 'old-week')
    await first
    check(api.statsData.value.marker === 'month', '旧响应晚到不能覆盖当前日期数据')

    const failure = api.loadStats()
    requests[6].resolve({ data: { marker: 'unused' } })
    requests[7].resolve({ data: {} })
    requests[8].reject({ msg: '网络异常，请重试' })
    await failure
    check(api.stageBoard.value.stages.length === 0 && Object.keys(api.statsData.value).length === 0,
        '请求失败不残留上一次在途及经营数字')
    check(api.loadError.value === '网络异常，请重试' && !api.loading.value, '失败明确提示并结束加载，不冒充零业务')
    const retry = api.loadStats()
    resolveBatch(9, 'retry')
    await retry
    check(api.loadError.value === '' && api.statsData.value.marker === 'retry', '重试后恢复数据并清除失败提示')

    const bad = api.loadStats()
    resolveBatch(12, 'bad', { stages: [{ stage_key: 'pay', count: -2 }] })
    await bad
    check(api.loadError.value.includes('在途统计返回异常') && api.stageBoard.value.stages.length === 0,
        '异常接口负数不会被显示或静默截成零')
    for (const [board, name] of [
        [{ stages: [{ stage_key: 'pay', count: null }] }, '空计数不会被转换成零'],
        [{ stages: [] }, '空环节列表不会冒充统计正常'],
    ]) {
        const start = requests.length
        const invalid = api.loadStats()
        resolveBatch(start, 'invalid', board)
        await invalid
        check(api.loadError.value.includes('在途统计返回异常'), name)
    }
    const beforeShow = requests.length
    showHook()
    check(requests.length === beforeShow + 3, '从任务处理页返回时重新读取统计')
    resolveBatch(beforeShow, 'on-show')
    await flush()
    api.showStageExplain()
    check(modal.title === '各环节在途统计口径' && modal.content === '当前有效任务' && modal.showCancel === false,
        '统计口径通过轻量弹窗查看，不堆叠页面信息')
    api.showFinanceExplain()
    check(modal.title === '资金统计口径' && modal.content.includes('折账') && modal.content.includes('差额'),
        '资金说明明确包含折账与未结清差额，避免把结算额误认为现金支出')
    console.log(`\n${checks} PASS — SFC/TS 编译 + 真实页面脚本模拟请求，无网络和业务写入`)
})().catch((error) => { console.error(error); process.exitCode = 1 })
