import { computed, onBeforeUnmount, ref, shallowRef, watch } from 'vue'

type Option = Record<string, any>

/** Remote identity candidates stay scoped to one dialog and never replace the selected label with an ID. */
export function useRemoteOptions<T extends Option>(settings: {
    request: (keyword: string) => Promise<T[]>
    key: (row: T) => number
    selectedKey: () => unknown
    scope: () => string
    errorText: (error: unknown, fallback: string) => string
    fallback: string
    delay?: number
}) {
    const rows = shallowRef<T[]>([]), selected = shallowRef<T | null>(null)
    const loading = ref(false), error = ref(''), keyword = ref('')
    let sequence = 0, disposed = false, timer: ReturnType<typeof setTimeout> | undefined
    const currentKey = () => Number(settings.selectedKey()) || 0
    const options = computed(() => {
        const pinned = selected.value
        return pinned && settings.key(pinned) === currentKey() && !rows.value.some(row => settings.key(row) === currentKey()) ? [pinned, ...rows.value] : rows.value
    })
    watch([settings.selectedKey, rows], () => {
        if (!currentKey()) selected.value = null
        else {
            const found = rows.value.find(row => settings.key(row) === currentKey())
            if (found) selected.value = found
            else if (selected.value && settings.key(selected.value) !== currentKey()) selected.value = null
        }
    }, { flush: 'sync' })
    function invalidate() {
        sequence++; if (timer !== undefined) clearTimeout(timer)
        timer = undefined; loading.value = false
    }
    function reset() {
        invalidate(); rows.value = []; selected.value = null; keyword.value = ''; error.value = ''
    }
    function remember(row: T) { selected.value = row }
    async function load(value = keyword.value) {
        invalidate()
        const token = sequence, scope = settings.scope()
        keyword.value = String(value || '').trim(); loading.value = true; error.value = ''
        const current = () => !disposed && token === sequence && scope === settings.scope()
        try {
            const result = await settings.request(keyword.value)
            if (!current()) return
            if (!Array.isArray(result)) throw new Error('候选列表格式不完整，请核对配套后端')
            rows.value = result
        } catch (cause) {
            if (current()) { rows.value = []; error.value = settings.errorText(cause, settings.fallback) }
        } finally { if (current()) loading.value = false }
    }
    function search(value: string) {
        invalidate(); keyword.value = String(value || '').trim(); error.value = ''; loading.value = true
        timer = setTimeout(() => { timer = undefined; void load(keyword.value) }, settings.delay ?? 250)
    }
    watch(settings.scope, reset, { flush: 'sync' })
    onBeforeUnmount(() => { disposed = true; invalidate() })
    return { options, loading, error, keyword, load, search, reset, remember }
}

/** An invalid response is not a successful empty search. IDs are validated before becoming selectable. */
export function remoteOptionRows<T extends Option>(response: any, key: (row: T) => unknown): T[] {
    const data = response?.data
    const rows = Array.isArray(data) ? data : data?.data
    if (!Array.isArray(rows) || rows.some(row => !row || typeof row !== 'object' || Array.isArray(row) || !['number', 'string'].includes(typeof key(row)) || !Number.isSafeInteger(Number(key(row))) || Number(key(row)) <= 0)) throw new Error('候选列表格式不完整，请核对配套后端')
    return rows
}
