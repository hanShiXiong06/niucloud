import { ref } from 'vue'
import { getReturnOrderByOrderId } from '../api/return_order'

/**
 * 退货订单查询 + 跳转
 * 统一封装，避免 order/detail.vue 和 OrderActions.vue 重复
 */
export function useReturnOrder() {
  const returnOrderList = ref<any[]>([])
  const hasReturnOrder = ref(false)
  const loadingReturnOrder = ref(false)

  /** 查询某个原订单关联的退货订单 */
  const loadReturnOrders = async (orderId: number | string) => {
    if (!orderId) {
      returnOrderList.value = []
      hasReturnOrder.value = false
      return
    }

    loadingReturnOrder.value = true
    try {
      const res = await getReturnOrderByOrderId(Number(orderId))
      const data = res.data || []
      const list = Array.isArray(data) ? data : []
      returnOrderList.value = list
      hasReturnOrder.value = list.length > 0
    } catch (e) {
      returnOrderList.value = []
      hasReturnOrder.value = false
    } finally {
      loadingReturnOrder.value = false
    }
  }

  /** 根据退货订单数量跳转到对应页面 */
  const goToReturnOrder = (orderId?: number | string) => {
    if (returnOrderList.value.length === 1) {
      uni.navigateTo({
        url: `/addon/recycle/pages/return_order/detail?id=${returnOrderList.value[0].id}`
      })
    } else if (returnOrderList.value.length > 1) {
      uni.navigateTo({
        url: `/addon/recycle/pages/return_order/list?order_id=${orderId || ''}`
      })
    }
  }

  /** 检查设备列表是否有退货设备(status===6)，有则查询退货订单 */
  const checkReturnOrderByDevices = async (orderId: number | string, devices: any[]) => {
    const hasReturnDevice = devices?.some((d: any) => d.status === 6)
    if (hasReturnDevice) {
      await loadReturnOrders(orderId)
    } else {
      returnOrderList.value = []
      hasReturnOrder.value = false
    }
  }

  return {
    returnOrderList,
    hasReturnOrder,
    loadingReturnOrder,
    loadReturnOrders,
    goToReturnOrder,
    checkReturnOrderByDevices
  }
}
