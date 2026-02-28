import { ref, computed, onMounted } from 'vue'
import type { OrderFilters } from '../types/order'
import { getOrderStatusCount } from '../api/order'

// 状态选项接口
interface StatusOption {
  key: string
  text: string
  count: number
  actions?: number[]
}

/**
 * 订单筛选管理
 * 管理状态筛选、配送方式筛选、搜索关键词
 * 状态数据从接口动态获取: recycle/recycle_order/status_count
 */
export function useOrderFilters() {
  // 当前选中的状态 ('all', '1', '2', '3', '4', '5', '6', '7', '8', '9')
  const currentStatus = ref('all')

  // 配送方式 0-全部 1-邮寄 2-自送
  const deliveryType = ref(0)

  // 搜索关键词
  const searchKeyword = ref('')

  // 从接口获取的状态列表
  const statusList = ref<StatusOption[]>([])

  // 状态字典（设备状态和订单状态）
  const statusDict = ref<{
    device?: Record<string, string>
    order?: Record<string, string>
  }>({})

  // 状态选项列表（从接口动态生成）
  const statusOptions = computed(() =>
    statusList.value.map(item => ({
      label: item.text,
      value: item.key,
      count: item.count,
      actions: item.actions || []
    }))
  )

  // 配送方式选项
  const deliveryOptions = [
    { label: '全部', value: 0 },
    { label: '邮寄', value: 1 },
    { label: '自送', value: 2 }
  ]

  // 计算当前筛选条件
  const filters = computed<OrderFilters>(() => ({
    status: currentStatus.value,
    delivery_type: deliveryType.value,
    search_keyword: searchKeyword.value
  }))

  // 获取订单状态统计
  const fetchStatusCounts = async () => {
    try {
      const res: any = await getOrderStatusCount()
      if (res.code === 1 && res.data) {
        // 更新状态列表
        statusList.value = res.data.list || []
        // 更新状态字典
        statusDict.value = res.data.status_dict || {}
      }
    } catch (error) {
      console.error('获取订单状态统计失败：', error)
    }
  }

  // 切换状态
  const switchStatus = (status: string) => {
    currentStatus.value = status
  }

  // 切换配送方式
  const switchDeliveryType = (type: number) => {
    deliveryType.value = type
  }

  // 更新搜索关键词
  const updateSearchKeyword = (keyword: string) => {
    searchKeyword.value = keyword
  }

  // 重置筛选条件
  const resetFilters = () => {
    currentStatus.value = 'all'
    deliveryType.value = 0
    searchKeyword.value = ''
  }

  // 检查是否有筛选条件
  const hasFilters = computed(() => {
    return currentStatus.value !== 'all' ||
           deliveryType.value !== 0 ||
           searchKeyword.value !== ''
  })

  // 初始化时获取状态统计
  onMounted(() => {
    fetchStatusCounts()
  })

  return {
    currentStatus,
    deliveryType,
    searchKeyword,
    statusOptions,
    statusDict,
    deliveryOptions,
    filters,
    switchStatus,
    switchDeliveryType,
    updateSearchKeyword,
    resetFilters,
    hasFilters,
    fetchStatusCounts
  }
}
