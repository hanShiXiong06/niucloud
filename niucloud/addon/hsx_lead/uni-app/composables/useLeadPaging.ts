import { computed, ref } from 'vue'
import type { useCooperation } from './useCooperation'

type CooperationUi = ReturnType<typeof useCooperation>
type LeadPagingQuery = { page: number; size: number; from: string; readingList: boolean; signature: string }

/** What the paging needs from the workbench that owns it. All are read lazily, at call time. */
export interface LeadPagingHost {
    ui: CooperationUi
    /** Page is shown and not disposed (the workbench's pageActive). */
    active: () => boolean
    /** The role list may query (logged in, profile ready, managed deliveries view). */
    visible: () => boolean
    /** The workbench is showing a managed role list (找客户 / 找门店). */
    managed: () => boolean
    /** A write or confirmation is in progress. */
    locked: () => boolean
    /** Current session identity; part of the query signature. */
    identity: () => string
}

/**
 * z-paging orchestration for the role lists (找客户 / 找门店), moved out of CooperationWorkbench unchanged.
 *
 * - The request hook (useCooperation) owns rows; z-paging's delayed model emissions are never taken as data.
 * - A keyed pager (revision) is remounted for each new query; it issues its own first read from mounted.
 * - epoch / signature drop answers that belong to an older query, page visit or identity.
 * - pause() remembers an in-flight read; resumeInterrupted() completes or repeats it on the same query.
 */
export function useLeadPaging(host: LeadPagingHost) {
    const { ui } = host
    const pagingRef = ref<any>(null),
        revision = ref(0),
        keyword = ref(''),
        firstPageReady = ref(false)
    let epoch = 0,
        planned = false,
        pending: LeadPagingQuery | null = null,
        interrupted: LeadPagingQuery | null = null,
        appliedSignature = ''
    const signature = () => JSON.stringify([host.identity(), ui.direction.value, ui.query.keyword, ui.query.status])
    // The request hook owns rows. Do not accept z-paging's delayed model emissions as
    // business data; an old instance can emit after its nextTick/setTimeout boundary.
    const query = computed(() => {
        const current = revision.value
        return (page: number, size: number, from = '') => queryLeads(page, size, from, current)
    })
    async function finish(success: boolean) {
        if (success && !ui.listError.value) {
            firstPageReady.value = true
            await pagingRef.value?.completeByNoMore([...ui.rows.value], !ui.canLoadMore.value)
        }
        // With call-network-reject=false, z-paging intentionally leaves its failed
        // completion promise pending. The request lifecycle must still finish.
        else void pagingRef.value?.complete(false)
    }
    async function queryLeads(page: number, size: number, from = '', current = revision.value) {
        if (!host.active() || !host.visible() || current !== revision.value) return
        planned = false
        // z-paging's footer increments pageNo even after a first-page failure. Never
        // append until page one of this committed query has actually succeeded.
        if (page > 1 && !firstPageReady.value) return reload()
        if (page === 1) {
            firstPageReady.value = false
            if (appliedSignature !== signature()) {
                ui.rows.value = []
                ui.total.value = 0
                appliedSignature = signature()
            }
        }
        const turn = ++epoch,
            request: LeadPagingQuery = { page, size, from, readingList: false, signature: signature() }
        pending = request
        try {
            if (page === 1 && from === 'user-pull-down' && !(await ui.bootstrap())) {
                if (turn === epoch && host.active()) await finish(false)
                return
            }
            if (turn !== epoch || !host.active()) return
            ui.query.limit = size
            if (page > 1) ui.query.page = page - 1
            request.readingList = true
            const success = await ui.loadLeads(page > 1)
            if (turn !== epoch || !host.active() || request.signature !== signature()) return
            await finish(success)
        } finally {
            if (pending === request) pending = null
        }
    }
    function reload() {
        if (!host.active() || !host.managed()) return
        epoch++
        pending = null
        firstPageReady.value = false
        const next = signature(),
            changed = appliedSignature !== next
        if (changed) {
            ui.rows.value = []
            ui.total.value = 0
            ui.listError.value = ''
        }
        appliedSignature = next
        // Native component refs can arrive after the parent's nextTick. The keyed
        // pager owns its one initial query from mounted; never silently drop it via
        // an early optional ref.reload(), nor race that call against auto loading.
        planned = true
        revision.value++
    }
    function search(value?: string | number) {
        if (!host.visible() || host.locked()) return
        // u-search emits its submitted value before its watched v-model can flush
        // on H5/Weixin. Commit this event, not yesterday's draft, to the pager.
        const next = String(typeof value === 'string' || typeof value === 'number' ? value : keyword.value).trim()
        const unchanged = ui.query.keyword === next
        keyword.value = next
        ui.query.keyword = next
        if (unchanged && (planned || !!pending || ui.listLoading.value)) return
        return reload()
    }
    function clearSearch() {
        return search('')
    }
    function filterStatus(value: string) {
        if (!host.visible() || host.locked() || ui.query.status === value) return
        ui.query.status = value
        reload()
    }
    /** Page hidden: remember the in-flight read and invalidate its answer. */
    function pause() {
        if (pending) interrupted = { ...pending, readingList: ui.listLoading.value }
        pending = null
        planned = false
        epoch++
    }
    /** Take the read interrupted by the last pause (if any). Call before resuming the request hook. */
    function takeInterrupted() {
        const value = interrupted
        interrupted = null
        return value
    }
    /** Finish or repeat an interrupted read, only if it still belongs to the current query. */
    async function resumeInterrupted(value: LeadPagingQuery | null) {
        if (!value || !host.managed() || !host.active() || value.signature !== signature()) return
        if (value.readingList) await finish(!ui.listError.value)
        else await queryLeads(value.page, value.size, value.from)
    }
    // Bootstrap readiness is not list readiness. A page can be hidden before its
    // native pager mounts; resume that missing first read, but retain a completed
    // empty page and an already planned query.
    function ensureFirstPage() {
        if (
            host.managed() &&
            host.active() &&
            !firstPageReady.value &&
            !planned &&
            !pending &&
            !ui.listLoading.value &&
            !ui.listError.value
        )
            reload()
    }
    return {
        pagingRef,
        revision,
        keyword,
        firstPageReady,
        query,
        reload,
        search,
        clearSearch,
        filterStatus,
        pause,
        takeInterrupted,
        resumeInterrupted,
        ensureFirstPage
    }
}
