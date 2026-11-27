import { computed, ref } from 'vue'
import { getSiteId, getToken } from '@/utils/common'
import { coopError, type CoopRow } from '../api/cooperation'
import { promotionIncome } from '../api/promotion'
import { cooperationIncomeKey, cooperationIncomeType } from '../utils/cooperation-income'

/** One read-only feed over existing ledgers; it never creates a second wallet. */
export function useCooperationIncome() {
    const rows = ref<CoopRow[]>([]),
        account = ref<CoopRow>({}),
        ready = ref(false),
        loading = ref(false),
        error = ref(''),
        total = ref(0),
        incomeType = ref('all'),
        status = ref('')
    let active = false,
        owner = '',
        sequence = 0
    const identity = () => `${getSiteId(uni.getStorageSync('wap_site_id') || '')}:${getToken() || ''}`
    const current = (who: string) => active && !!getToken() && who === owner && who === identity()
    const hasMore = computed(() => rows.value.length < total.value)
    function resume() {
        active = true
        const who = identity()
        if (owner !== who) {
            sequence++
            owner = who
            rows.value = []
            account.value = {}
            ready.value = false
            total.value = 0
            error.value = ''
        }
        return !!getToken()
    }
    function pause() {
        active = false
        sequence++
        loading.value = false
    }
    function filter(value: unknown) {
        const next = cooperationIncomeType(value)
        if (next === incomeType.value) return false
        sequence++
        loading.value = false
        incomeType.value = next
        rows.value = []
        total.value = 0
        error.value = ''
        return true
    }
    async function read(page: number, limit: number) {
        if (!resume()) return false
        const who = owner,
            turn = ++sequence,
            type = incomeType.value,
            state = status.value
        loading.value = true
        error.value = ''
        try {
            const response: any = await promotionIncome({ income_type: type, status: state, page, limit }),
                value = response?.data
            if (!current(who) || turn !== sequence || type !== incomeType.value || state !== status.value) return false
            if (!value?.account || !Array.isArray(value.entries?.data) || !Number.isFinite(Number(value.entries.total)))
                throw new Error('收益资料未完整返回，请刷新核对')
            account.value = value.account
            const incoming = value.entries.data
            rows.value =
                page > 1
                    ? [...new Map([...rows.value, ...incoming].map((row) => [cooperationIncomeKey(row), row])).values()]
                    : incoming
            total.value = Number(value.entries.total)
            ready.value = true
            return true
        } catch (cause) {
            if (current(who) && turn === sequence) error.value = coopError(cause, '收益读取失败，请重试')
            return false
        } finally {
            if (turn === sequence) loading.value = false
        }
    }
    return { rows, account, ready, loading, error, total, incomeType, status, hasMore, resume, pause, filter, read }
}
