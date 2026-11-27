import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import useUserStore from '@/stores/modules/user'
import storage from '@/utils/storage'
import { tradeDirectory, tradeScope, type TradeDirectory } from '../utils/trade-workspace'

const names: Record<TradeDirectory, string> = { 'warehouse-options':'仓库与库位', 'spec-options':'规格', dicts:'基础字典' }
export function useTradeDirectory() {
    const user = useUserStore(), errors = ref<string[]>([]), sessionChanged = ref(false)
    const scope = computed(() => tradeScope(user.siteInfo?.site_id, (user.userInfo as {uid?: number})?.uid))
    let version = 0
    const liveSeq = new Map<string, number>()
    function clear() { ++version; errors.value = []; tradeDirectory.clear(scope.value) }
    // The HTTP client reads siteId from shared browser storage for every request.
    // A sibling tab can change it while the old Pinia store / open confirmation still has the first site.
    function sessionOK() {
        if (sessionChanged.value || !scope.value) return false
        if (scope.value === tradeScope(storage.get('siteId'), storage.get('userinfo')?.uid)) return true
        sessionChanged.value = true; clear(); return false
    }
    function onStorage(e: StorageEvent) {
        if (!e.key || e.key === 'site.siteId' || e.key === 'site.userinfo' || e.key === 'site.token') sessionOK()
    }
    function onFocus() { sessionOK() }
    onMounted(() => { window.addEventListener('storage', onStorage); window.addEventListener('focus', onFocus); sessionOK() })
    onBeforeUnmount(() => { window.removeEventListener('storage', onStorage); window.removeEventListener('focus', onFocus) })
    watch(scope, (_current, previous) => {
        ++version; errors.value = []; tradeDirectory.clear(previous)
        if (previous) sessionChanged.value = true
    })
    async function read(kind: TradeDirectory, query: () => Promise<any>, valid: (v: any) => boolean) {
        if (!sessionOK()) return
        const id = scope.value, n = version
        try {
            const res = await tradeDirectory.read(id, kind, async () => {
                const response = await query()
                if (!sessionOK() || !valid(response?.data)) throw new Error('incomplete directory')
                return response
            })
            if (!sessionOK() || id !== scope.value || n !== version) return
            errors.value = errors.value.filter(v => v !== names[kind])
            return res
        } catch {
            if (id === scope.value && n === version && !errors.value.includes(names[kind])) errors.value.push(names[kind])
        }
    }
    // Staff, channels and workflow rules can change permissions or validation. Never cache them.
    async function live(label: string, query: () => Promise<any>, valid: (v: any) => boolean) {
        if (!sessionOK()) return
        const id = scope.value, epoch = version, seq = (liveSeq.get(label) || 0) + 1
        liveSeq.set(label, seq)
        const current = () => sessionOK() && id === scope.value && epoch === version && liveSeq.get(label) === seq
        try {
            const response = await query()
            if (!current()) return
            if (!valid(response?.data)) throw new Error('incomplete response')
            errors.value = errors.value.filter(v => v !== label)
            return response
        } catch {
            if (current() && !errors.value.includes(label)) errors.value.push(label)
        }
    }
    return { scope, read, live, clear, errors, sessionOK, sessionChanged, reopen: () => window.location.reload() }
}

/** Fetch balances only on the cash settlement step; discard results after close, tenant switch or retry. */
export function useTradeAccounts(active: () => boolean, scope: () => string, query: () => Promise<any>) {
    const accounts = ref<any[]>([]), accountError = ref(false), accountLoading = ref(false)
    let version = 0
    async function reloadAccounts() {
        const n = ++version
        accounts.value = []; accountError.value = false; accountLoading.value = active()
        if (!active()) return
        const id = scope()
        const current = () => n === version && active() && id === scope()
        try {
            const res = await query()
            const rows = Array.isArray(res?.data) ? res.data : res?.data?.list
            if (!Array.isArray(rows)) throw new Error('incomplete account list')
            if (current()) accounts.value = rows
        } catch {
            if (current()) accountError.value = true
        } finally {
            if (n === version) accountLoading.value = false
        }
    }
    watch([active, scope], reloadAccounts)
    return { accounts, accountError, accountLoading, reloadAccounts }
}
