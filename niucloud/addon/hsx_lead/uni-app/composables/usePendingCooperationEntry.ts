import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { cooperationLeads, type CoopRow } from '../api/cooperation'

export interface PendingCooperationEntry {
    id: number
    assignmentVersion: number
    businessName: string
    publisherName: string
    region: string
}
interface PendingEntryContext {
    active: () => boolean
    siteId: () => string
    memberId: () => number
    token: () => string
}
// Prompt acknowledgement belongs to this app session, not the business order.
// Never put tokens, customer details or a permanent dismissal in device storage.
const sessions = new Map<string, { token: string; seen: Set<string> }>()
let promptOwner: symbol | null = null
const positiveId = (value: unknown) => {
    const id = Number(value)
    return Number.isSafeInteger(id) && id > 0 && id <= 4294967295 ? id : 0
}
const assignmentKey = (item: PendingCooperationEntry) => `${item.id}:${item.assignmentVersion}`
const text = (value: unknown, limit: number) => (typeof value === 'string' ? value.trim().slice(0, limit) : '')

/** One read-only prompt slot, backed by the same receiver/team scope as 找客户. */
export function usePendingCooperationEntry(context: PendingEntryContext) {
    const visible = ref(false),
        loading = ref(false),
        total = ref(0),
        items = ref<PendingCooperationEntry[]>([]),
        selectedId = ref(0)
    const owner = Symbol('cooperation-pending-entry')
    let disposed = false,
        generation = 0
    const scope = () => {
        const member = positiveId(context.memberId()),
            site = String(context.siteId() || ''),
            token = String(context.token() || '')
        return context.active() && member && /^\d+$/.test(site) && token ? { key: `${site}:${member}`, token } : null
    }
    const signature = () => {
        const value = scope()
        return value ? `${value.key}:${value.token}` : ''
    }
    const selected = computed(() => items.value.find((item) => item.id === selectedId.value) || items.value[0])
    function clear() {
        generation++
        visible.value = false
        loading.value = false
        items.value = []
        total.value = 0
        selectedId.value = 0
        if (promptOwner === owner) promptOwner = null
    }
    function acknowledge(onlySelected = false) {
        const who = scope(),
            session = who && sessions.get(who.key)
        if (session && session.token === who?.token)
            for (const item of onlySelected ? (selected.value ? [selected.value] : []) : items.value)
                session.seen.add(assignmentKey(item))
        // Keep this non-sensitive presentation snapshot for the framework's
        // leave transition; clearing it now briefly paints a false "0 tasks".
        // A hidden page, changed identity, next read or unmount still clears it.
        generation++
        visible.value = false
        loading.value = false
        if (promptOwner === owner) promptOwner = null
    }
    async function refresh() {
        clear()
        const who = scope()
        if (disposed || !who || promptOwner) return
        const turn = generation,
            identity = signature(),
            current = () => !disposed && turn === generation && identity === signature()
        let session = sessions.get(who.key)
        if (!session || session.token !== who.token) {
            session = { token: who.token, seen: new Set() }
            sessions.set(who.key, session)
        }
        loading.value = true
        try {
            const found: PendingCooperationEntry[] = [],
                known = new Set<number>()
            let page = 1,
                count = 0
            // Reassignment changes assignment_version but may keep an old id.
            // Walk only past already acknowledged rows, never infer an empty
            // queue from the first three ids or from cached bootstrap counters.
            do {
                const response: any = await cooperationLeads({
                    direction: 'incoming',
                    status: 'assigned',
                    page,
                    limit: 20
                })
                if (!current()) return
                const data = response?.data
                if (
                    !data ||
                    !Array.isArray(data.data) ||
                    !Number.isSafeInteger(Number(data.total)) ||
                    Number(data.total) < 0
                )
                    throw Error('Invalid pending queue')
                count = Number(data.total)
                let added = 0
                for (const row of data.data as CoopRow[]) {
                    const id = positiveId(row?.id),
                        version = Number(row?.assignment_version)
                    if (
                        !id ||
                        known.has(id) ||
                        row.status !== 'assigned' ||
                        !Number.isSafeInteger(version) ||
                        version < 0
                    )
                        continue
                    known.add(id)
                    added++
                    const item = {
                        id,
                        assignmentVersion: version,
                        businessName: text(row.business_name, 30) || '客户交付',
                        publisherName: text(row.publisher?.name, 40) || '协作门店',
                        region: text(row.region_text, 80)
                    }
                    if (!session.seen.has(assignmentKey(item))) found.push(item)
                    if (found.length === 3) break
                }
                if (found.length >= 3 || !data.data.length || !added || page * 20 >= count) break
                page++
            } while (current())
            if (!current() || !found.length || promptOwner) return
            promptOwner = owner
            total.value = count
            items.value = found
            selectedId.value = found[0].id
            visible.value = true
        } catch (_) {
            // An automatic, optional entry prompt must not block the real page
            // or turn an unavailable private read into a false “no tasks”.
            if (current()) {
                visible.value = false
                items.value = []
            }
        } finally {
            if (current()) loading.value = false
        }
    }
    const stop = watch(
        () => [context.active(), context.siteId(), context.memberId(), context.token()],
        () => {
            void refresh()
        },
        { immediate: true }
    )
    onBeforeUnmount(() => {
        disposed = true
        stop()
        clear()
    })
    return {
        visible,
        loading,
        total,
        items,
        selectedId,
        selected,
        dismiss: () => acknowledge(),
        opened: () => acknowledge(true),
        pause: clear,
        refresh
    }
}
