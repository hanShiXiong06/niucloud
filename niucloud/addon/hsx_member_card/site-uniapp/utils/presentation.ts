const isNumeric = (value: unknown) =>
    value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value))
export const money = (value: unknown) => (isNumeric(value) ? Number(value).toFixed(2) : '—')
export const quantity = (value: unknown) => (isNumeric(value) ? String(Number(Number(value).toFixed(3))) : '—')
/** ERP 选项接口只返回启用账户且不含 status；本地选项包含停用账户，不能混用。 */
export const accountOptions = (config: any, includeDisabled = false): any[] => {
    const rows = (config?.capital_account_options || []).map((row: any) => ({
        ...row,
        status: row.status == null && config.finance_provider === 'erp' ? 1 : Number(row.status)
    }))
    return includeDisabled ? rows : rows.filter((row: any) => row.status === 1)
}
export const phone = (value: string) =>
    /^\d{11}$/.test(value || '') ? value.slice(0, 3) + '****' + value.slice(-4) : value || '未留手机号'
export const dateTime = (value: unknown) => {
    const date = new Date(Number(value) * 1000)
    if (!value || !Number.isFinite(date.getTime())) return '—'
    const pad = (v: number) => String(v).padStart(2, '0')
    return (
        date.getFullYear() +
        '-' +
        pad(date.getMonth() + 1) +
        '-' +
        pad(date.getDate()) +
        ' ' +
        pad(date.getHours()) +
        ':' +
        pad(date.getMinutes())
    )
}
const finance: Record<string, [string, string]> = {
    pending: ['待收款', 'warning'],
    partial: ['部分收款', 'warning'],
    settled: ['已结清', 'success'],
    processing: ['处理中', 'primary'],
    failed: ['待处理', 'error'],
    refunded: ['已退款', 'muted'],
    void: ['已作废', 'muted']
}
export const financeLabel = (status: string) => finance[status]?.[0] || '状态待确认'
export const financeTone = (status: string) => finance[status]?.[1] || 'muted'
const stock: Record<string, string> = {
    deducted: '已扣库存',
    negative: '库存不足',
    failed: '库存待补记',
    restored: '已返库',
    restore_failed: '返库待处理',
    not_managed: '不管理库存'
}
export const stockLabel = (status: string) => stock[status] || '库存状态待确认'
export const errorText = (error: any, fallback = '暂时无法完成，请重试') =>
    typeof error?.msg === 'string' && error.msg.length < 100 ? error.msg : fallback
export const copyText = (value: unknown) => {
    if (value) uni.setClipboardData({ data: String(value) })
}
let version = 0
export const markMemberCardChanged = () => {
    version++
}
export const memberCardVersion = () => version
