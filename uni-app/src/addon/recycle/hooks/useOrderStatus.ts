import type { OrderStatusInfo } from '../types/order'

/**
 * 订单状态管理
 * 提供状态映射、颜色、文本等信息
 */
export function useOrderStatus() {
  /**
   * 获取状态信息
   * @param status 状态值 1-待签收 2-待检测 3-待确认 4-已完成 -1-已取消
   */
  const getStatusInfo = (status: number): OrderStatusInfo => {
    const statusMap: Record<number, OrderStatusInfo> = {
      1: {
        text: '待签收',
        color: '#f59e0b',
        bgColor: '#fef3c7'
      },
      2: {
        text: '待检测',
        color: '#3b82f6',
        bgColor: '#dbeafe'
      },
      3: {
        text: '待确认',
        color: '#8b5cf6',
        bgColor: '#ede9fe'
      },
      4: {
        text: '已完成',
        color: '#10b981',
        bgColor: '#d1fae5'
      },
      '-1': {
        text: '已取消',
        color: '#6b7280',
        bgColor: '#f3f4f6'
      }
    }

    return statusMap[status] || {
      text: '未知状态',
      color: '#6b7280',
      bgColor: '#f3f4f6'
    }
  }

  /**
   * 获取配送方式文本
   */
  const getDeliveryTypeText = (type: string | number): string => {
    return type === 1 || type === '1' ? '邮寄' : '自送'
  }

  /**
   * 获取配送方式颜色
   */
  const getDeliveryTypeColor = (type: string | number): string => {
    return type === 1 || type === '1' ? '#3b82f6' : '#10b981'
  }

  /**
   * 获取设备状态信息
   */
  const getDeviceStatusInfo = (status: number): OrderStatusInfo => {
    const statusMap: Record<number, OrderStatusInfo> = {
      1: {
        text: '待检测',
        color: '#3b82f6',
        bgColor: '#dbeafe'
      },
      2: {
        text: '已检测',
        color: '#10b981',
        bgColor: '#d1fae5'
      },
      3: {
        text: '已确认',
        color: '#8b5cf6',
        bgColor: '#ede9fe'
      }
    }

    return statusMap[status] || {
      text: '未知',
      color: '#6b7280',
      bgColor: '#f3f4f6'
    }
  }

  return {
    getStatusInfo,
    getDeliveryTypeText,
    getDeliveryTypeColor,
    getDeviceStatusInfo
  }
}
