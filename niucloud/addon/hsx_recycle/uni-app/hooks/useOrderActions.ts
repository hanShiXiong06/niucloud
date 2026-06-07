import type { OrderListItem } from '../types/order'
import { updateOrderStatus } from '../api/order'
import { copyOrderNo as copyOrderNoText, copyExpressNo as copyExpressNoText } from '../utils/clipboard'

/**
 * 订单操作管理
 * 处理取消订单、确认收货、删除订单等操作
 */
export function useOrderActions() {
  /**
   * 取消订单
   */
  const cancelOrder = async (order: OrderListItem): Promise<boolean> => {
    return new Promise((resolve) => {
      uni.showModal({
        title: '提示',
        content: '确定要取消该订单吗？',
        success: async (res) => {
          if (res.confirm) {
            try {
              uni.showLoading({ title: '处理中...' })
              const result = await updateOrderStatus({
                order_id: order.id,
                status: '-1',
                action: 'cancel'
              })

              if (result.code === 1) {
                uni.showToast({
                  title: '订单已取消',
                  icon: 'success'
                })
                resolve(true)
              } else {
                uni.showToast({
                  title: result.msg || '取消失败',
                  icon: 'none'
                })
                resolve(false)
              }
            } catch (error) {
              console.error('取消订单失败：', error)
              uni.showToast({
                title: '取消失败',
                icon: 'none'
              })
              resolve(false)
            } finally {
              uni.hideLoading()
            }
          } else {
            resolve(false)
          }
        }
      })
    })
  }

  /**
   * 确认收货
   */
  const confirmOrder = async (order: OrderListItem): Promise<boolean> => {
    return new Promise((resolve) => {
      uni.showModal({
        title: '提示',
        content: '确认已收到货物吗？',
        success: async (res) => {
          if (res.confirm) {
            try {
              uni.showLoading({ title: '处理中...' })
              const result = await updateOrderStatus({
                order_id: order.id,
                status: '2',
                action: 'confirm'
              })

              if (result.code === 1) {
                uni.showToast({
                  title: '确认成功',
                  icon: 'success'
                })
                resolve(true)
              } else {
                uni.showToast({
                  title: result.msg || '确认失败',
                  icon: 'none'
                })
                resolve(false)
              }
            } catch (error) {
              console.error('确认收货失败：', error)
              uni.showToast({
                title: '确认失败',
                icon: 'none'
              })
              resolve(false)
            } finally {
              uni.hideLoading()
            }
          } else {
            resolve(false)
          }
        }
      })
    })
  }

  /**
   * 删除订单
   */
  const deleteOrder = async (order: OrderListItem): Promise<boolean> => {
    return new Promise((resolve) => {
      uni.showModal({
        title: '提示',
        content: '确定要删除该订单吗？删除后无法恢复。',
        success: async (res) => {
          if (res.confirm) {
            try {
              uni.showLoading({ title: '删除中...' })
              const result = await updateOrderStatus({
                order_id: order.id,
                status: order.status.toString(),
                action: 'delete'
              })

              if (result.code === 1) {
                uni.showToast({
                  title: '删除成功',
                  icon: 'success'
                })
                resolve(true)
              } else {
                uni.showToast({
                  title: result.msg || '删除失败',
                  icon: 'none'
                })
                resolve(false)
              }
            } catch (error) {
              console.error('删除订单失败：', error)
              uni.showToast({
                title: '删除失败',
                icon: 'none'
              })
              resolve(false)
            } finally {
              uni.hideLoading()
            }
          } else {
            resolve(false)
          }
        }
      })
    })
  }

  /**
   * 查看订单详情
   */
  const viewOrderDetail = (order: OrderListItem) => {
    uni.navigateTo({
      url: `/addon/hsx_recycle/pages/order/detail?id=${order.id}`
    })
  }

  /**
   * 复制订单号
   */
  const copyOrderNo = (orderNo: string) => {
    copyOrderNoText(orderNo)
  }

  /**
   * 复制快递单号
   */
  const copyExpressNo = (expressNo: string) => {
    copyExpressNoText(expressNo)
  }

  return {
    cancelOrder,
    confirmOrder,
    deleteOrder,
    viewOrderDetail,
    copyOrderNo,
    copyExpressNo
  }
}
