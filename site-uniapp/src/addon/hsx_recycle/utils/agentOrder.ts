export interface AgentOrderState {
    draftId: number
    orderNo: string
    creatingUnknown: boolean
    busy: boolean
    lastSignPayload: string
}

export const newAgentOrderState = (): AgentOrderState => ({ draftId: 0, orderNo: '', creatingUnknown: false, busy: false, lastSignPayload: '' })

export const deviceEntryError = (device: any): string => {
    if (!/^[a-zA-Z0-9]{6,15}$/.test(String(device.imei || ''))) return 'IMEI / SN 须为 6–15 位字母或数字'
    if (!String(device.model || '').trim()) return '请选择设备型号'
    return ''
}

export const agentOrderError = (memberId: number, deliveryType: string, expressNo: string, devices: any[]): string => {
    if (!memberId) return '请先选择客户'
    if (deliveryType === '1' && !expressNo.trim()) return '请填写快递单号'
    if (!devices.length) return '请至少添加一台设备'
    const seen = new Set<string>()
    for (let index = 0; index < devices.length; index++) {
        const device = devices[index]
        const error = deviceEntryError(device)
        if (error) return `设备 ${index + 1}：${error}`
        if (!Number(device.category_id)) return `设备 ${index + 1}：请选择设备型号`
        const serial = String(device.imei).toUpperCase()
        if (seen.has(serial)) return `设备 ${index + 1}：串号与本单其他设备重复`
        seen.add(serial)
    }
    return ''
}

interface AgentOrderApi {
    create(data: Record<string, any>): Promise<any>
    detail(id: number): Promise<any>
    sign(id: number, data: Record<string, any>): Promise<any>
}

/** 保留草稿 ID。创建响应丢失不自动重发；签收重试前先核对原单，避免重复录入/打印。 */
export async function submitAgentOrder(state: AgentOrderState, api: AgentOrderApi, input: {
    memberId: number; deliveryType: string; expressNo: string; devices: any[]; nextAssigneeUid: number
}): Promise<number> {
    if (state.busy) throw new Error('正在提交，请稍候')
    if (state.creatingUnknown) throw new Error('创建结果尚未确认，请先核对订单列表，不要重复下单')
    const error = agentOrderError(input.memberId, input.deliveryType, input.expressNo, input.devices)
    if (error) throw new Error(error)
    const fingerprint = JSON.stringify({ devices: input.devices, nextAssigneeUid: input.nextAssigneeUid })
    state.busy = true
    try {
        if (!state.draftId) {
            try {
                const result = await api.create({
                    member_id: input.memberId, delivery_type: input.deliveryType,
                    express_no: input.deliveryType === '1' ? input.expressNo.trim() : '',
                    count: 0, devices: [], order_source: 'agent', draft_device_entry: true, sign_after_create: false
                })
                const id = Number(result?.data?.id)
                if (!Number.isSafeInteger(id) || id <= 0) throw new Error('创建响应缺少订单编号')
                state.draftId = id
                state.orderNo = String(result.data.order_no || '')
            } catch (cause: any) {
                // 明确的业务拒绝可以修正后重试；超时/断网/异常响应不能断言没有创建。
                if (!cause || typeof cause.code !== 'number' || cause.code === 1) state.creatingUnknown = true
                throw cause
            }
        }

        const response = await api.detail(state.draftId)
        const order = response?.data
        if (!order || Number(order.id) !== state.draftId || Number(order.member_id) !== input.memberId) {
            throw new Error('原订单信息未核对成功，请从订单列表查看，不会重复创建')
        }
        if (Number(order.status) !== 1) {
            if ([2, 3, 4, 5, 6, 7].includes(Number(order.status)) && Number(order.sign_at) > 0) {
                const serverSerials = (order.devices || []).map((device: any) => String(device.imei || '').toUpperCase()).sort()
                const currentSerials = input.devices.map(device => String(device.imei).toUpperCase()).sort()
                // 只恢复原次提交。超时后增加设备/改价，不可把新填写内容当成已经保存。
                if (state.lastSignPayload === fingerprint && JSON.stringify(serverSerials) === JSON.stringify(currentSerials)) return state.draftId
                throw new Error('原单已签收，但与当前填写不一致。请从订单详情核对；当前修改未再次保存，不会重复建单')
            }
            throw new Error('原订单已不在待签收状态，请从订单列表核对')
        }
        const existing = new Map<string, number>((order.devices || []).map((device: any) => [String(device.imei || '').toUpperCase(), Number(device.id)]))
        state.lastSignPayload = fingerprint
        await api.sign(state.draftId, {
            action: 'order_sign', next_assignee_uid: input.nextAssigneeUid,
            devices: input.devices.map(device => ({ ...device, id: existing.get(String(device.imei).toUpperCase()) || undefined }))
        })
        return state.draftId
    } finally {
        state.busy = false
    }
}
