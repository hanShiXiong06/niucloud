import { reactive, ref } from 'vue'

/**
 * 列表页通用查询 composable —— 收敛各列表页重复的 search/loadList/排序/分页/日期区间样板。
 *
 * 用法：
 *   const { search, table, summary, loadList, handleSearch, reset, onPage, onSort } = useListQuery({
 *     api: getErpOutboundList,
 *     defaults: { keyword: '', outbound_type: '', dateRange: [], sort_field: '', sort_order: '' },
 *     dateRangeField: 'dateRange',          // 自动拆成 start_time / end_time
 *   })
 *
 * 约定：
 *  - 后端返回 { data: { data:[], total, summary? } }（与现有分页一致）。
 *  - 排序需在 defaults 里带 sort_field / sort_order；表格 @sort-change="onSort"。
 *  - 日期区间字段在 defaults 里给个 []，并用 dateRangeField 指定，提交时自动拆字段。
 */
export interface UseListQueryOptions {
    api: (params: Record<string, any>) => Promise<any>
    defaults?: Record<string, any>
    dateRangeField?: string
    dateFieldNames?: [string, string]
    immediate?: boolean
    pageSize?: number
}

export function useListQuery(opts: UseListQueryOptions) {
    const defaults = opts.defaults || {}
    const search = reactive<Record<string, any>>({ ...JSON.parse(JSON.stringify(defaults)) })
    const table = reactive({ data: [] as any[], total: 0, loading: false, page: 1, limit: opts.pageSize || 15 })
    const summary = ref<Record<string, any>>({})

    function buildParams(): Record<string, any> {
        const p: Record<string, any> = { ...search, page: table.page, limit: table.limit }
        if (opts.dateRangeField) {
            const r = search[opts.dateRangeField]
            const [s, e] = opts.dateFieldNames || ['start_time', 'end_time']
            if (Array.isArray(r) && r.length === 2) {
                p[s] = r[0]
                p[e] = r[1]
            }
            delete p[opts.dateRangeField]
        }
        return p
    }

    async function loadList() {
        table.loading = true
        try {
            const res: any = await opts.api(buildParams())
            const d = res?.data || {}
            table.data = d.data || []
            table.total = Number(d.total || 0)
            summary.value = d.summary || {}
        } finally {
            table.loading = false
        }
    }

    function handleSearch() {
        table.page = 1
        loadList()
    }

    function reset() {
        const fresh = JSON.parse(JSON.stringify(defaults))
        Object.keys(search).forEach((k) => { search[k] = k in fresh ? fresh[k] : '' })
        table.page = 1
        loadList()
    }

    function onPage(p: number) {
        table.page = p
        loadList()
    }

    function onSort({ prop, order }: { prop: string; order: string | null }) {
        search.sort_field = order ? prop : ''
        search.sort_order = order === 'ascending' ? 'asc' : order === 'descending' ? 'desc' : ''
        table.page = 1
        loadList()
    }

    if (opts.immediate !== false) loadList()

    return { search, table, summary, loadList, handleSearch, reset, onPage, onSort, buildParams }
}
