import { ref, reactive } from 'vue'

/**
 * ERP 通用分页列表 Composable
 *
 * 用法：
 *   const { listData, loading, pagination, where, loadList, reload } =
 *     useErpList(getErpPurchaseList, { status: '', keyword: '' })
 */
export function useErpList<T = any>(
    fetchFn: (params: Record<string, any>) => Promise<any>,
    defaultWhere: Record<string, any> = {}
) {
    const loading = ref(false)
    const listData = ref<T[]>([])
    const pagination = reactive({ page: 1, limit: 15, total: 0 })
    const where = reactive<Record<string, any>>({ ...defaultWhere })

    async function loadList() {
        loading.value = true
        try {
            const res = await fetchFn({
                ...where,
                page: pagination.page,
                limit: pagination.limit,
            })
            listData.value = res.data?.data || []
            pagination.total = res.data?.total || 0
        } finally {
            loading.value = false
        }
    }

    /** 重置到第1页并重新加载 */
    function reload() {
        pagination.page = 1
        return loadList()
    }

    /** 切换筛选条件并 reload */
    function filter(patch: Record<string, any>) {
        Object.assign(where, patch)
        return reload()
    }

    return {
        loading,
        listData,
        pagination,
        where,
        loadList,
        reload,
        filter,
    }
}
