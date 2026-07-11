import { ref } from 'vue'
import { getErpBusinessSourceOptions, getErpFinanceCategories } from '@/addon/hsx_erp/api/erp'

export type ErpMoneyDirection = 'income' | 'expense'

const categories = ref<any[]>([])
const businessSources = ref<any[]>([])
const loading = ref(false)
let loaded = false

/**
 * 应收/应付共用的动态收支分类与业务来源 Hook。
 * 模块级缓存让页面、筛选弹层和收付款组件共用同一份当前站点套餐装配结果。
 */
export function useErpFinanceOptions() {
    const load = async (force = false) => {
        if (loading.value || (loaded && !force)) return
        loading.value = true
        try {
            const [categoryResult, sourceResult] = await Promise.allSettled([
                getErpFinanceCategories(),
                getErpBusinessSourceOptions(),
            ])
            if (categoryResult.status === 'fulfilled') categories.value = responseRows(categoryResult.value)
            if (sourceResult.status === 'fulfilled') businessSources.value = responseRows(sourceResult.value)
            loaded = categoryResult.status === 'fulfilled' || sourceResult.status === 'fulfilled'
        } finally {
            loading.value = false
        }
    }

    const categoryOptions = (direction: ErpMoneyDirection) => categories.value
        .filter(item => Number(item?.enabled ?? 1) === 1 && String(item?.direction) === direction)
        .map(item => ({ label: String(item?.name || item?.key), value: String(item?.key || '') }))
        .filter(item => item.value)

    const sourceOptions = (direction: ErpMoneyDirection) => businessSources.value
        .filter(item => Number(item?.enabled ?? 1) === 1 && String(item?.direction) === direction)
        .map(item => ({ label: String(item?.name || item?.key), value: String(item?.key || '') }))
        .filter(item => item.value)

    return { categories, businessSources, loading, load, categoryOptions, sourceOptions }
}

function responseRows(response: any) {
    const data = response?.data
    if (Array.isArray(data)) return data
    if (Array.isArray(data?.list)) return data.list
    return []
}
