import { computed, ref } from 'vue'
import { searchQuoteSpiderModels, type QuoteSearchRow } from '@/addon/recycle_quote_spider/api/quotation'
import { normalizeSearchKeyword } from '@/addon/recycle_quote_spider/utils/quoteSearch'

export function useQuoteSearch(onSearched: (keyword: string, total: number) => void) {
    const keyword = ref('')
    const sourceId = ref(0)
    const rows = ref<QuoteSearchRow[]>([])
    const total = ref(0)
    const page = ref(0)
    const lastPage = ref(0)
    const loading = ref(false)
    const error = ref('')
    const hasMore = computed(() => page.value < lastPage.value && rows.value.length < total.value)
    let generation = 0

    function reset() {
        generation++
        keyword.value = ''
        rows.value = []
        total.value = 0
        page.value = 0
        lastPage.value = 0
        loading.value = false
        error.value = ''
    }

    async function fetchPage(nextPage: number, current: number) {
        loading.value = true
        error.value = ''
        const requestedKeyword = keyword.value
        try {
            const response = await searchQuoteSpiderModels({ keyword: requestedKeyword, source_id: sourceId.value, page: nextPage, limit: 12 })
            if (current !== generation) return
            const data = response.data
            if (!data || !Array.isArray(data.data)) throw new Error('报价数据格式异常，请重试')
            const incoming = data.data as QuoteSearchRow[]
            const existing = nextPage === 1 ? [] : rows.value
            const seen = new Set(existing.map(row => row.id))
            rows.value = [...existing, ...incoming.filter(row => !seen.has(row.id))]
            total.value = Math.max(0, Number(data.total) || 0)
            page.value = nextPage
            lastPage.value = incoming.length ? Number(data.last_page) || Math.ceil(total.value / 12) : nextPage
            if (nextPage === 1) onSearched(requestedKeyword, total.value)
        } catch (cause: any) {
            if (current === generation) error.value = cause?.msg || cause?.message || '暂时无法获取报价，请重试'
        } finally {
            if (current === generation) loading.value = false
        }
    }

    function search(value: string) {
        reset()
        keyword.value = normalizeSearchKeyword(value)
        if (!keyword.value) return Promise.resolve()
        return fetchPage(1, generation)
    }

    function loadMore() {
        if (loading.value || !hasMore.value) return Promise.resolve()
        return fetchPage(page.value + 1, generation)
    }

    function retry() {
        if (loading.value) return Promise.resolve()
        return fetchPage(page.value + 1, generation)
    }

    return { keyword, sourceId, rows, total, loading, error, hasMore, search, reset, loadMore, retry }
}
