import { ref, computed, onMounted } from 'vue'
import type { OrderFilters } from '../types/order'
import { getOrderStatusCount } from '../api/order'

/**
 * 订单筛选管理
 * 管理状态筛选、配送方式筛选、搜索关键词
 */
export function useOrderFilters() {
  // 当前选中的状态 0-全部 1-待签收 2-待检测 3-待确认 4-已完成 -1-已取消
  const currentStatus = ref(0)

  // 配送方式 0-全部 1-邮寄 2-自送
  const deliveryType = ref(0)

  // 搜索关键词
  const searchKeyword = ref('')

  // 状态计数
  const statusCounts = ref<Record<number, number>>({})

  // 状态选项列表（带计数）
  const statusOptions = computed(() => [
    { label: '全部', value: 0, count: statusCounts.value[0] },
    { label: '待签收', value: 1, count: statusCounts.value[1] },
    { label: '待检测', value: 2, count: statusCounts.value[2] },
    { label: '待确认', value: 3, count: statusCounts.value[3] },
    { label: '已完成', value: 4, count: statusCounts.value[4] },
    { label: '已取消', value: -1, count: statusCounts.value[-1] }
  ])

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
      const res = await getOrderStatusCount()
      if (res.code === 1 && res.data) {
        // 更新状态计数
        statusCounts.value = res.data
      }
    } catch (error) {
      console.error('获取订单状态统计失败：', error)
    }
  }

  // 切换状态
  const switchStatus = (status: number) => {
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
    currentStatus.value = 0
    deliveryType.value = 0
    searchKeyword.value = ''
  }

  // 检查是否有筛选条件
  const hasFilters = computed(() => {
    return currentStatus.value !== 0 ||
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
