const now = Math.floor(Date.now() / 1000)
const config: Record<string, any> = { mode: 'disabled', client_id: '', app_key: '', account_id: '', domain: '', assignee_uid: 0, revision: 0, client_secret_configured: false, app_secret_configured: false, write_enabled: false }
const tasks: any[] = [
    { id: 1, business_no: 'DEMO-CG-1001', kind: 'asset', status: 'blocked', reason: '请补充商品映射与计量单位ID', notice_status: 'unavailable', update_at: now, create_at: now,
        plan: { label: '购货入库', proposed_number: 'HSXJTESTA1', requirements: ['请补充商品映射', '请填写精斗云商品计量单位ID'], references: [{ kind: 'asset_material', label: '商品', local_id: 101, external_number: '' }], write_enabled: false } },
    { id: 2, business_no: 'DEMO-XS-1002', kind: 'quantity', status: 'prepared', reason: '', notice_status: 'pending', update_at: now, create_at: now,
        plan: { label: '销货出库', proposed_number: 'HSXJTESTQ2', requirements: [], references: [], remote_acceptance: ['计量单位、税金与金额精度', '库存更新时点', '审核权限与应收应付结果'], write_enabled: false } },
    { id: 3, business_no: 'DEMO-TH-1003', kind: 'asset', status: 'blocked', reason: '待核对原销货单与原分录ID', notice_status: 'queued', update_at: now, create_at: now,
        plan: { label: '销售退货', proposed_number: 'HSXJTESTA3', requirements: ['待核对已同步原单、原分录ID、剩余可退数量和退款核销'], references: [], write_enabled: false } }
]
let mappings: any[] = []
const logs: any[] = [{ id: 1, task_id: 1, action: 'preflight', outcome: 'blocked', message: '模拟：资料缺失，任务转人工待处理，未写单', operator_uid: 0, create_at: now }]
const reply = (data: any) => Promise.resolve({ data: structuredClone(data) })
const addLog = (id: number, action: string, message: string) => logs.unshift({ id: logs.length + 1, task_id: id, action, outcome: 'fixture', message, operator_uid: 1, create_at: now })
export const getConfig = () => reply(config)
export const saveConfig = (input: any) => {
    const { client_secret, app_secret, ...safe } = input
    Object.assign(config, safe, { revision: config.revision + 1, client_secret_configured: !!client_secret || config.client_secret_configured, app_secret_configured: !!app_secret || config.app_secret_configured })
    return reply(config)
}
export const getStaff = () => reply([{ uid: 1, real_name: '模拟负责人', username: 'fixture' }])
export const probe = () => reply({ status: 'simulation', message: '模拟预览不执行授权检查，也不连接外部账套' })
export const getTasks = (params: any) => {
    const rows = tasks.filter(row => (!params.status || row.status === params.status) && (!params.keyword || row.business_no.includes(params.keyword)))
    return reply({ data: rows, total: rows.length, counts: [...new Set(tasks.map(row => row.status))].map(status => ({ status, total: tasks.filter(row => row.status === status).length })) })
}
export const getTask = (id: number) => reply({ ...tasks.find(row => row.id === id), logs: logs.filter(row => row.task_id === id) })
export const retry = (id: number) => {
    const row = tasks.find(row => row.id === id)
    if (id === 1 && mappings.some(map => map.kind === 'asset_material' && map.local_id === 101 && map.unit_id)) {
        row.status = 'prepared'; row.reason = ''; row.plan.requirements = []; row.plan.references[0].external_number = mappings[0].external_number
    }
    addLog(id, 'retry', '模拟重新预检，未写单'); return reply({})
}
export const notice = (id: number) => { tasks.find(row => row.id === id).notice_status = 'queued'; addLog(id, 'notice', '模拟提醒已入队，不发送真实消息'); return reply({}) }
export const resolve = (id: number, data: any) => { Object.assign(tasks.find(row => row.id === id), { status: 'manual_closed', resolution: data.note, external_no: data.external_no }); addLog(id, 'resolve', '模拟人工结案，未验证外部单据'); return reply({}) }
export const getMappings = (params: any) => { const rows = mappings.filter(row => !params.kind || row.kind === params.kind); return reply({ data: rows, total: rows.length }) }
export const saveMapping = (input: any) => { mappings = mappings.filter(row => !(row.kind === input.kind && row.local_id === input.local_id)); mappings.push({ ...input, id: mappings.length + 1 }); return reply({}) }
export const getLogs = () => reply({ data: logs, total: logs.length })
