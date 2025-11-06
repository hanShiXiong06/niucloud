import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { getQuotationDataAll } from '@/addon/recycle/api/quotation'

/**
 * 内存信息类型定义
 */
export interface CapacityInfo {
    id: number
    model_id: number
    capacity: string
    capacity_answer_id: number
}

/**
 * 报价数据类型定义
 */
export interface QuotationDataItem {
    id: number
    quotation_id: number
    price_name: string
    goods_id: number
    goods_name: string
    capacity: string
    capacity_id?: number
    capacity_answer_id?: number
    capacity_info?: CapacityInfo
    prices: Record<string, number> | null
    value_info?: string
    price_date: string
    is_current: number
    [key: string]: any
}

/**
 * 搜索表单类型
 */
export interface SearchForm {
    quotation_id: string | number
    price_name: string
    goods_name: string
    capacity: string
    price_date: string
    is_current: number | null
}

/**
 * 报价数据服务层 - 处理数据请求和业务逻辑
 */
export function useQuotationData() {
    const tableData = ref<QuotationDataItem[]>([])
    const loading = ref(false)
    const priceTypeOptions = ref<string[]>(['花机/内爆', '靓机/小花'])

    // 搜索表单
    const searchForm = reactive<SearchForm>({
        quotation_id: '',
        price_name: '花机/内爆',
        goods_name: '',
        capacity: '',
        price_date: getCurrentDate(),
        is_current: 1
    })

    /**
     * 获取当前日期
     */
    function getCurrentDate(): string {
        const today = new Date()
        const year = today.getFullYear()
        const month = String(today.getMonth() + 1).padStart(2, '0')
        const day = String(today.getDate()).padStart(2, '0')
        return `${year}-${month}-${day}`
    }

    /**
     * 加载数据
     */
    async function loadData(): Promise<void> {
        try {
            loading.value = true

            // 确保 price_name 必传，避免同型号同容量重复
            if (!searchForm.price_name || searchForm.price_name.trim() === '') {
                ElMessage.warning('请选择报价类型')
                loading.value = false
                return
            }

            // 构建请求参数 - price_name 必须传，确保后端按报价类型过滤
            const params: Record<string, any> = {
                price_name: searchForm.price_name // 必传参数
            }
            if (searchForm.quotation_id) params.quotation_id = searchForm.quotation_id
            if (searchForm.goods_name) params.goods_name = searchForm.goods_name
            if (searchForm.capacity) params.capacity = searchForm.capacity
            if (searchForm.price_date) params.price_date = searchForm.price_date
            if (searchForm.is_current !== null) params.is_current = searchForm.is_current

            const res = await getQuotationDataAll(params)

            if (res.data && Array.isArray(res.data)) {
                // 处理数据：确保 prices 是对象格式
                tableData.value = res.data.map(item => {
                    let prices = item.prices || {}
                    // 如果是数组，转换为空对象
                    if (Array.isArray(prices)) {
                        prices = {}
                    }
                    // 确保是对象格式
                    if (typeof prices !== 'object' || prices === null) {
                        prices = {}
                    }

                    return {
                        ...item,
                        prices
                    } as QuotationDataItem
                })
            } else {
                tableData.value = []
            }
        } catch (error) {
            console.error('加载数据失败:', error)
            ElMessage.error('加载数据失败')
            tableData.value = []
        } finally {
            loading.value = false
        }
    }

    /**
     * 重置搜索表单
     */
    function resetSearchForm(): void {
        searchForm.quotation_id = ''
        searchForm.price_name = '花机/内爆'
        searchForm.goods_name = ''
        searchForm.capacity = ''
        searchForm.price_date = getCurrentDate()
        searchForm.is_current = 1
    }

    /**
     * 搜索
     */
    function handleSearch(): void {
        loadData()
    }

    /**
     * 重置
     */
    function handleReset(): void {
        resetSearchForm()
        loadData()
    }

    return {
        tableData,
        loading,
        priceTypeOptions,
        searchForm,
        loadData,
        handleSearch,
        handleReset,
        getCurrentDate
    }
}

