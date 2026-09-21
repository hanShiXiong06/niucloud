export type ReturnAction = 'ship' | 'receive'
export type BatchRow = Record<string, any> & {
    id: number; status: number; mode: string; express_company: string; express_no: string
    result: 'pending' | 'success' | 'failed' | 'skipped'; message: string
}

export const returnStatusLabel = (status: unknown) => ['待寄回', '待签收', '已完成', '已取消'][Number(status)] || '未知状态'
export const returnDevices = (row: Record<string, any>) => row.returnDevices || row.return_devices || []

/** 兼容现有核心服务的嵌套结果，但绝不把“外层成功、内层失败”算作完成。 */
export function returnResponseError(res: any, fallback = '操作未完成'): string {
    if (Number(res?.code) !== 1) return res?.msg || fallback
    if (res?.data && res.data.code !== undefined && ![0, 1].includes(Number(res.data.code))) return res.data.msg || fallback
    return ''
}

export function prepareReturnRows(rows: Record<string, any>[], action: ReturnAction): BatchRow[] {
    const expected = action === 'ship' ? 0 : 1
    return [...new Map(rows.map(row => [Number(row.id), row])).values()].map(row => {
        const company = String(row.express_company || '')
        return { ...row, id: Number(row.id), status: Number(row.status), express_company: company, express_no: String(row.express_no || ''),
            mode: company === '自取' ? 'self_pickup' : ['物流车', '物流车/自取', 'express_car'].includes(company) ? 'logistics_car' : 'manual',
            result: Number(row.status) === expected ? 'pending' : 'skipped',
            message: Number(row.status) === expected ? '' : `当前${returnStatusLabel(row.status)}，本次不处理` }
    })
}

export function returnShipmentPayload(row: BatchRow, comment: string): Record<string, string> {
    if (row.mode === 'manual') {
        if (!row.express_company.trim() || !row.express_no.trim()) throw new Error('请填写本单快递公司和单号')
        if (row.express_company.length > 100 || row.express_no.length > 100) throw new Error('快递公司或单号不能超过100字')
        return { express_company: row.express_company.trim(), express_no: row.express_no.trim(), comment }
    }
    if (!['self_pickup', 'logistics_car'].includes(row.mode)) throw new Error('请选择退回方式')
    return { express_company: row.mode === 'self_pickup' ? '自取' : '物流车', express_no: '', comment }
}
