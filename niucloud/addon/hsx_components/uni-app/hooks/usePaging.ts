import { computed, nextTick, reactive, ref, shallowRef, watch } from 'vue'
import type { AnyRecord, MobilePageAdapter, MobilePageRequest, MobilePageResult } from '../types'
import { cleanParams } from '../utils'

export function defaultMobilePageAdapter<T>(response: any): MobilePageResult<T> {
    const payload = response?.data?.data ?? response?.data ?? response ?? {}
    const list = payload.list ?? payload.data ?? payload.rows ?? []
    return {
        list: Array.isArray(list) ? list : [],
        total: Number(payload.total ?? payload.count ?? list.length ?? 0)
    }
}

export interface UsePagingOptions<T, S extends AnyRecord> {
    request: MobilePageRequest<T>
    search?: S
    pageSize?: number
    adapter?: MobilePageAdapter<T>
}

export interface UseZPagingBridgeOptions<T> {
    getRequest: () => MobilePageRequest<T>
    getQuery?: () => AnyRecord
    getAdapter?: () => MobilePageAdapter<T> | undefined
    onLoad?: (list: T[], total: number) => void
    onError?: (error: unknown) => void
    watchQuery?: boolean
}

/**
 * 统一 z-paging 的请求、竞态保护与回写协议。
 * 业务组合组件直接使用该桥接层渲染列表，避免小程序跨多层 scoped slot 丢失行内容。
 */
export function useZPagingBridge<T = AnyRecord>(options: UseZPagingBridgeOptions<T>) {
    const pagingRef = ref<ZPagingRef<T>>()
    const list = shallowRef<T[]>([])
    const total = ref(0)
    const loading = ref(false)
    const error = ref<unknown>()
    let requestVersion = 0

    async function queryList(page: number, limit: number) {
        const version = ++requestVersion
        loading.value = true
        error.value = undefined

        try {
            const response = await options.getRequest()({
                ...cleanParams(options.getQuery?.() || {}),
                page,
                limit
            })
            if (version !== requestVersion) return

            const result = (options.getAdapter?.() || defaultMobilePageAdapter<T>)(response)
            total.value = result.total
            await pagingRef.value?.completeByTotal(result.list, result.total)
            await nextTick()
            options.onLoad?.(list.value, result.total)
        } catch (reason) {
            if (version !== requestVersion) return
            error.value = reason
            await pagingRef.value?.complete(false)
            options.onError?.(reason)
        } finally {
            if (version === requestVersion) loading.value = false
        }
    }

    const reload = () => pagingRef.value?.reload()
    const refresh = () => pagingRef.value?.refresh()
    const loadMore = () => pagingRef.value?.doLoadMore()

    if (options.watchQuery !== false && options.getQuery) {
        watch(options.getQuery, () => { void reload() }, { deep: true })
    }

    return { pagingRef, list, total, loading, error, queryList, reload, refresh, loadMore }
}

export function usePaging<T = AnyRecord, S extends AnyRecord = AnyRecord>(options: UsePagingOptions<T, S>) {
    const list = shallowRef<T[]>([])
    const loading = ref(false)
    const refreshing = ref(false)
    const error = ref<unknown>()
    const total = ref(0)
    const page = reactive({ current: 1, limit: options.pageSize || 20 })
    const search = reactive<S>({ ...(options.search || ({} as S)) })
    const finished = computed(() => list.value.length >= total.value && page.current > 1)
    const adapter: MobilePageAdapter<T> = options.adapter || defaultMobilePageAdapter
    let requestVersion = 0

    const fetchPage = async (reset = false) => {
        if (loading.value || (!reset && finished.value)) return
        if (reset) page.current = 1
        const version = ++requestVersion
        loading.value = true
        error.value = undefined
        try {
            const response = await options.request({
                ...cleanParams(search),
                page: page.current,
                limit: page.limit
            })
            if (version !== requestVersion) return
            const result = adapter(response)
            list.value = reset ? result.list : [...list.value, ...result.list]
            total.value = result.total
            page.current += 1
            return result
        } catch (reason) {
            error.value = reason
            throw reason
        } finally {
            if (version === requestVersion) loading.value = false
        }
    }

    const refresh = async () => {
        refreshing.value = true
        try {
            return await fetchPage(true)
        } finally {
            refreshing.value = false
        }
    }

    const loadMore = () => fetchPage(false)
    const reset = () => {
        list.value = []
        total.value = 0
        page.current = 1
        error.value = undefined
    }

    return { list, total, page, search, loading, refreshing, finished, error, refresh, loadMore, reset }
}
