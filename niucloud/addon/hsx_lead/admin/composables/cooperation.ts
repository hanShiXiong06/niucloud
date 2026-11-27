import { onBeforeUnmount, reactive, ref } from 'vue'
import useUserStore from '@/stores/modules/user'
import { errorText, timestampParts } from './useLeadUi'
import { hasLeadPagePermission } from './navigation'
import { demandNames } from './business'

export type CoopRow = Record<string, any>
export const cooperationStatuses = [
    { value: 'selecting', label: '待选门店' }, { value: 'assigned', label: '待接单' },
    { value: 'following', label: '跟进中' }, { value: 'unpaid', label: '成交待付佣' },
    { value: 'paid', label: '佣金已支付' }, { value: 'no_deal', label: '未成交' }, { value: 'cancelled', label: '已撤回' }
]
const labels: Record<string, string> = {
    ...Object.fromEntries(cooperationStatuses.map(item => [item.value, item.label])),
    pending: '待处理', review: '待复核', followup: '继续跟进', clear: '核查无违规', resolved: '已处理', confirmed_violation: '确认违规',
    rejected: '已驳回', closed: '已关闭', active: '已生效', none: '未开通', revoked: '已撤销', commission: '协作佣金', membership: '主体会员'
}
export const coopLabel = (value: unknown) => labels[String(value)] || String(value || '未设置')
export const coopMoney = (value: unknown) => value === undefined || value === null || value === '' ? '—' : `¥${Number(value).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
export const coopTime = (value: unknown) => timestampParts(value)?.full || '未记录'
export const coopTone = (value: unknown): 'success' | 'warning' | 'danger' | 'info' => ['paid', 'active', 'clear', 'resolved'].includes(String(value)) ? 'success' : ['confirmed_violation', 'revoked', 'rejected'].includes(String(value)) ? 'danger' : ['assigned', 'pending', 'review', 'unpaid'].includes(String(value)) ? 'warning' : 'info'
export const coopModels = demandNames
const businessLabels:Record<string,string>={purchase:'二手机',new_phone:'新机',rent:'租机',exchange:'二手机置换',broadband:'宽带',repair:'手机维修',home_recycle:'二手机上门回收'}
export const coopBusiness = (value: unknown, name?: unknown) => String(name || businessLabels[String(value)] || value || '未返回业务')
export const coopError = errorText
export function useCooperationAccess() {
    const user = useUserStore()
    const allows = (key: string) => hasLeadPagePermission(user,`hsx_lead_cooperation_${key}`)
    return { allows }
}

/** Every list owns its own failure and request generation; a forbidden finance/config read cannot blank the whole workspace. */
export function useCooperationList(request: (query: CoopRow) => Promise<any>, initial: CoopRow = {}) {
    const query = reactive<CoopRow>({ keyword: '', status: '', page: 1, limit: 20, ...initial })
    const rows = ref<CoopRow[]>([]), total = ref(0), loading = ref(false), error = ref('')
    let sequence = 0, disposed = false
    async function load() {
        const token = ++sequence
        loading.value = true; error.value = ''
        try {
            const result = await request({ ...query })
            if (disposed || token !== sequence) return
            const page = result.data
            if (!page || !Array.isArray(page.data)) throw new Error('返回的列表格式不完整，请核对配套后端')
            rows.value = page.data; total.value = Number(page.total || 0)
        } catch (cause) {
            if (!disposed && token === sequence) { rows.value = []; total.value = 0; error.value = coopError(cause, '读取失败，请核对权限或重新加载。') }
        } finally { if (!disposed && token === sequence) loading.value = false }
    }
    function search(patch: CoopRow = {}) { Object.assign(query, patch, { page: 1 }); return load() }
    function changePage(page: { page: number; limit: number }) { Object.assign(query, page); return load() }
    onBeforeUnmount(() => { disposed = true; sequence++ })
    return { query, rows, total, loading, error, load, search, changePage }
}
