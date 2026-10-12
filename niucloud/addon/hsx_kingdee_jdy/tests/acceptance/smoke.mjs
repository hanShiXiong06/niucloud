import assert from 'node:assert/strict'
import { writeFileSync } from 'node:fs'
const origin = 'http://127.0.0.1:5201'
let checks = 0
const check = (condition, label) => { assert.ok(condition, label); checks++ }
async function call(path, data, allowFailure = false) {
    const response = await fetch(`${origin}/api/${path}`, { method: data === undefined ? 'GET' : 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Acceptance-UI': '1' }, body: data === undefined ? undefined : JSON.stringify(data) })
    const result = await response.json()
    if (!allowFailure) assert.equal(result.code, 1, `${path}: ${result.msg} ${result.error_location || ''}`)
    return result
}
try {
    check((await fetch(`${origin}/api/acceptance/summary`)).status === 403, 'API rejects requests without UI header')
    check((await fetch(`${origin}/api/acceptance/summary`, { headers: { 'X-Acceptance-UI': '1', Origin: 'https://untrusted.example' } })).status === 403, 'Foreign origins rejected')
    check((await fetch('http://127.0.0.1:5202/api/acceptance/summary')).status === 403, 'Direct backend needs server-only key')
    const initial = (await call('acceptance/summary')).data
    check(initial.schedule_count === 1 && !initial.external_http_enabled, 'Plugin installed; external HTTP disabled')
    const id = (await call('acceptance/cases', {})).data.id
    const expected = [20000, 15500, 15500, 20500, 20500, 15500, 15500, 20000]
    let current
    for (let step = 0; step < 8; step++) {
        await call(`acceptance/cases/${id}/step`, { step })
        let summary = (await call('acceptance/summary')).data
        current = summary.cases.find(row => row.id === id)
        check(current.step === step + 1 && Number(current.balance) === expected[step], `ERP step ${step + 1} balance`)
        const taskCount = summary.task_count
        const repeated = await call(`acceptance/cases/${id}/step`, { step })
        summary = (await call('acceptance/summary')).data
        check(repeated.data.duplicate && summary.task_count === taskCount && summary.cases.find(row => row.id === id).history.length === step + 1, `step ${step + 1} duplicate has no extra business or task`)
    }
    check(current.asset_status === 'returned', 'asset returned after both return workflows')
    const noUnit = await call('kingdee_jdy/mappings', { kind: 'asset_material', local_id: current.data.asset, external_number: 'LOCAL-PHONE', unit_id: '' }, true)
    check(noUnit.code === 0 && noUnit.msg.includes('单位'), 'Mapping validates real unit requirement')
    await call('kingdee_jdy/mappings', { kind: 'asset_material', local_id: current.data.asset, external_number: `LOCAL-PHONE-${current.data.asset}`, unit_id: '1', remark: 'HTTP smoke fixture' })
    const maps = (await call('kingdee_jdy/mappings?kind=asset_material')).data.data
    check(maps.some(row => row.local_id === current.data.asset), 'Mapping persists through real controller')
    const tasks = (await call(`kingdee_jdy/tasks?keyword=${encodeURIComponent(current.purchase_no)}`)).data.data
    const task = tasks.find(row => row.kind === 'asset')
    check(!!task, 'real ERP purchase delivered a plugin task')
    await call(`kingdee_jdy/tasks/${task.id}/retry`, {})
    const prepared = (await call(`kingdee_jdy/tasks/${task.id}`)).data
    check(prepared.status === 'prepared' && !prepared.plan.write_enabled && !prepared.plan.draft && !prepared.snapshot_json, 'prepared is not remote success; sensitive snapshots omitted')
    const others = (await call('kingdee_jdy/tasks?status=blocked&limit=100')).data.data
    const blocked = others.find(row => row.kind === 'asset' && row.id !== task.id)
    await call(`kingdee_jdy/tasks/${blocked.id}/notice`, {})
    const notices = (await call('acceptance/notices')).data
    check(notices.some(row => row.task_id === blocked.id), 'real notice service reaches local-only inbox')
    check((await call(`kingdee_jdy/tasks/${blocked.id}/resolve`, { note: '' }, true)).code === 0, 'empty manual closure rejected')
    await call(`kingdee_jdy/tasks/${blocked.id}/resolve`, { note: '本地HTTP回归，人工结案不代表远端同步', external_no: '' })
    check((await call(`kingdee_jdy/tasks/${blocked.id}`)).data.status === 'manual_closed', 'manual closure persists')
    check((await call(`kingdee_jdy/tasks/${blocked.id}/retry`, {}, true)).code === 0, 'closed tasks cannot be retried')
    let config = (await call('kingdee_jdy/config')).data
    check(config.client_secret_configured && config.app_secret_configured && !config.client_secret_cipher && !config.app_secret_cipher, 'public configuration hides secrets')
    check((await call('kingdee_jdy/config', { ...config, client_secret: 'not-a-real-secret' }, true)).code === 0, 'acceptance rejects non-fixture credentials')
    check((await call('kingdee_jdy/config', { ...config, revision: -1 }, true)).code === 0, 'stale config rejected')
    for (const fault of ['auth', 'timeout', 'none']) {
        await call('acceptance/fault', { fault })
        config = (await call('kingdee_jdy/config')).data
        const probe = await call('kingdee_jdy/connection/probe', { revision: config.revision }, true)
        check(fault === 'none' ? probe.code === 1 && probe.data.status === 'simulation' : probe.code === 0, `authorization scenario ${fault}`)
    }
    check(!(await call('kingdee_jdy/config')).data.probe_at, 'simulated probe never displayed as verified remote connection')
    const logs = (await call('kingdee_jdy/logs?limit=100')).data.data
    check(logs.some(row => row.action === 'probe') && logs.some(row => row.action === 'resolve'), 'readable operation audits recorded')
    check(!JSON.stringify(logs).includes('local-fixture-client'), 'audit logs do not contain credentials')
    const report = { status: 'passed', checks, case_id: id, balance: current.balance, database_isolated: true, external_http_enabled: false, remote_write_verified: false, at: new Date().toISOString() }
    writeFileSync('/tmp/hsx-jdy-acceptance-report.json', JSON.stringify(report, null, 2))
    console.log(JSON.stringify(report, null, 2))
} finally {
    await call('acceptance/fault', { fault: 'none' })
}
