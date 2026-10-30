import { onBeforeUnmount, reactive, ref } from 'vue'

export type Row = Record<string, any>
export const readyLabels: Record<string, string> = { member_required: '待绑会员', connection_required: '待接入 ERP', sync_required: '待首次同步', ready: '可派单', attention: '需要处理', paused: '已暂停' }
export const syncLabel = (status: string) => ({ idle: '尚未同步', pending: '等待处理', queued: '等待处理', running: '逐页同步中', completed: '同步完成', success: '同步完成', failed: '同步失败', cancelled: '任务已取消' }[status] || '尚未同步')
export const statusLabels: Record<string, string> = {
    active: '合作中', paused: '已暂停', pending: '待分配', assigned: '待接单', accepted: '跟进中',
    following: '跟进中', deal_pending: '成交待核', deal_submitted: '成交待核', verified: '已成交',
    completed: '已成交', closed: '已关闭', rejected: '未通过', unpaid: '待收佣', paid: '已收款',
    running: '同步中', queued: '等待同步', success: '同步完成', failed: '同步失败', idle: '未同步',
    cancelled: '已撤销成交', reassigned: '已转派', refund_pending: '待退佣', refunded: '已退款', void: '已作废'
}
export const label = (value: any) => statusLabels[String(value)] || String(value || '未设置')
export const money = (value: any) => `¥${Number(value || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
export function budget(row: Row) {
    const min = Number(row.budget_min || 0), max = Number(row.budget_max || 0)
    return min && max ? `${money(min)} – ${money(max)}` : max ? `最高 ${money(max)}` : min ? `${money(min)} 起` : '预算未确定'
}
export function demandHint(row: Row) {
    const count = Array.isArray(row.requirements) ? row.requirements.length : 0
    return count ? `${row.demand_mode === 'all' ? '分别要多台' : '任选一款'} · ${count} 款意向机型 · 查看逐项数量与预算` : budget(row)
}
export const time = (value: any) => value ? new Date(typeof value === 'number' || /^\d+$/.test(value) ? Number(value) * 1000 : value).toLocaleString('zh-CN', { hour12: false }) : '尚未记录'
export const region = (row: Row) => [row.province_name, row.city_name, row.district_name].filter(Boolean).join(' / ') || '未填写地区'
export function errorText(error: any, fallback = '操作未完成，请稍后重试。') {
    return String(error?.msg || error?.response?.data?.msg || error?.message || fallback)
}
export const tone = (status: any): 'success' | 'warning' | 'danger' | 'info' => {
    if (['active', 'success', 'verified', 'completed', 'paid'].includes(status)) return 'success'
    if (['failed', 'rejected'].includes(status)) return 'danger'
    if (['pending', 'assigned', 'deal_pending', 'deal_submitted', 'unpaid', 'queued'].includes(status)) return 'warning'
    return 'info'
}

/** Keep failed reads visibly distinct from a genuinely empty list. */
export function useLeadList(request: (params: Row) => Promise<any>, initial: Row = {}) {
    const rows = ref<Row[]>([]), total = ref(0), loading = ref(false), error = ref('')
    const query = reactive<Row>({ page: 1, limit: 20, keyword: '', ...initial })
    let sequence = 0, disposed = false
    async function load() {
        const current = ++sequence
        loading.value = true; error.value = ''
        try {
            const result = await request({ ...query })
            if (current !== sequence || disposed) return
            const page = result.data || {}
            rows.value = Array.isArray(page.data) ? page.data : []
            total.value = Number(page.total || 0)
        } catch (cause) {
            if (current === sequence && !disposed) { rows.value = []; total.value = 0; error.value = errorText(cause, '列表加载失败，请检查访问权限并重试。') }
        } finally { if (current === sequence && !disposed) loading.value = false }
    }
    function search() { query.page = 1; return load() }
    onBeforeUnmount(() => { disposed = true; sequence++ })
    return { rows, total, loading, error, query, load, search }
}
