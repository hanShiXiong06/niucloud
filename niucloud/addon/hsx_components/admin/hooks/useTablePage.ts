import { reactive, ref, shallowRef } from 'vue'
import type { AnyRecord, PageResult, TableRequest, TableResponseAdapter } from '../types'
import { removeEmptyValues } from '../utils'

export function defaultTableResponseAdapter<T>(response: any): PageResult<T> {
    const payload = response?.data?.data ?? response?.data ?? response ?? {}
    const list = payload.list ?? payload.data ?? payload.rows ?? []
    const total = Number(payload.total ?? payload.count ?? list.length ?? 0)
    return { list: Array.isArray(list) ? list : [], total }
}

export interface UseTablePageOptions<T, S extends AnyRecord> {
    request: TableRequest<T>
    initialSearch?: S
    pageSize?: number
    responseAdapter?: TableResponseAdapter<T>
    immediate?: boolean
}

export function useTablePage<T = AnyRecord, S extends AnyRecord = AnyRecord>(options: UseTablePageOptions<T, S>) {
    const loading = ref(false)
    const rows = shallowRef<T[]>([])
    const total = ref(0)
    const pagination = reactive({ page: 1, limit: options.pageSize || 20 })
    const search = reactive<S>({ ...(options.initialSearch || ({} as S)) })
    const adapter = options.responseAdapter || defaultTableResponseAdapter<T>

    const load = async () => {
        loading.value = true
        try {
            const response = await options.request({
                ...removeEmptyValues(search),
                page: pagination.page,
                limit: pagination.limit
            })
            const result = adapter(response)
            rows.value = result.list
            total.value = result.total
            return result
        } finally {
            loading.value = false
        }
    }

    const reload = async (resetPage = false) => {
        if (resetPage) pagination.page = 1
        return load()
    }

    const resetSearch = async () => {
        Object.keys(search).forEach((key) => delete search[key])
        Object.assign(search, options.initialSearch || {})
        pagination.page = 1
        return load()
    }

    if (options.immediate !== false) void load()

    return { loading, rows, total, pagination, search, load, reload, resetSearch }
}
