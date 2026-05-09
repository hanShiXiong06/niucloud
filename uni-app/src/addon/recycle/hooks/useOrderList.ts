import { ref } from 'vue'
import type { OrderListItem, OrderFilters } from '../types/order'
import { getOrderList } from '../api/order'

/**
 * 订单列表管理
 * 订单列表数据加载
 */
export function useOrderList() {
  // 订单列表
  const orderList = ref<OrderListItem[]>([])

  // z-paging 实例
  const pagingRef = ref<any>(null)

  // 加载状态
  const loading = ref(false)

  /**
   * 分页加载回调
   */
  const queryList = async (pageNo: number, pageSize: number, filters: OrderFilters) => {
    try {
      loading.value = true

      const params: any = {
        page: pageNo,
        limit: pageSize
      }

      // 添加筛选条件
      if (filters.status !== 'all') {
        params.status = filters.status
      }
      if (filters.delivery_type !== 0) {
        params.delivery_type = filters.delivery_type
      }
      if (filters.search_keyword) {
        params.search = filters.search_keyword
      }

      const res: any = await getOrderList(params)

      if (res.code === 1) {
        const list = res.data.data || []

        pagingRef.value?.complete(list)
      } else {
        pagingRef.value?.complete(false)
        uni.showToast({
          title: res.msg || '加载失败',
          icon: 'none'
        })
      }
    } catch (error) {
      console.error('加载订单列表失败：', error)
      pagingRef.value?.complete(false)
      uni.showToast({
        title: '加载失败',
        icon: 'none'
      })
    } finally {
      loading.value = false
    }
  }

  /**
   * 刷新列表
   */
  const refreshList = () => {
    pagingRef.value?.reload()
  }

  /**
   * 从列表中移除订单
   */
  const removeOrder = (orderId: number) => {
    const index = orderList.value.findIndex(item => item.id === orderId)
    if (index > -1) {
      orderList.value.splice(index, 1)
    }
  }

  /**
   * 更新订单状态
   */
  const updateOrderStatus = (orderId: number, status: number, statusName: string) => {
    const order = orderList.value.find(item => item.id === orderId)
    if (order) {
      order.status = status
      order.status_name = statusName
    }
  }

  return {
    orderList,
    pagingRef,
    loading,
    queryList,
    refreshList,
    removeOrder,
    updateOrderStatus
  }
}
