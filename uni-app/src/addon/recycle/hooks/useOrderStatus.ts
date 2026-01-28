import type { OrderStatusInfo } from '../types/order'

/**
 * 订单状态管理
 * 提供状态映射、颜色、文本等信息
 *
 * 状态映射基于接口 recycle/recycle_order/status_count 返回的 status_dict
 * 订单状态: 1-待签收, 2-已签收, 3-质检中, 4-已质检, 5-待确认, 6-待打款, 7-已完成, 8-已关闭, 9-已取消
 */
export function useOrderStatus() {
  /**
   * 获取状态信息
   * @param status 状态值 (number 或 string)
   */
  const getStatusInfo = (status: number | string): OrderStatusInfo => {
    // 统一转换为字符串
    const statusStr = String(status)

    // 状态颜色和背景色映射（基于实际的9个状态）
    const statusMap: Record<string, OrderStatusInfo> = {
      '1': {
        text: '待签收',
        color: '#f59e0b',
        bgColor: '#fef3c7'
      },
      '2': {
        text: '已签收',
        color: '#06b6d4',
        bgColor: '#cffafe'
      },
      '3': {
        text: '质检中',
        color: '#3b82f6',
        bgColor: '#dbeafe'
      },
      '4': {
        text: '已质检',
        color: '#6366f1',
        bgColor: '#e0e7ff'
      },
      '5': {
        text: '待确认',
        color: '#8b5cf6',
        bgColor: '#ede9fe'
      },
      '6': {
        text: '待打款',
        color: '#ec4899',
        bgColor: '#fce7f3'
      },
      '7': {
        text: '已完成',
        color: '#10b981',
        bgColor: '#d1fae5'
      },
      '8': {
        text: '已关闭',
        color: '#6b7280',
        bgColor: '#f3f4f6'
      },
      '9': {
        text: '已取消',
        color: '#6b7280',
        bgColor: '#f3f4f6'
      }
    }

    return statusMap[statusStr] || {
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
   * 设备状态: 1-待质检, 2-质检中, 3-已质检, 4-待确认, 5-已回收, 6-已退回, 7-已定价
   */
  const getDeviceStatusInfo = (status: number | string): OrderStatusInfo => {
    const statusStr = String(status)

    const statusMap: Record<string, OrderStatusInfo> = {
      '1': {
        text: '待质检',
        color: '#f59e0b',
        bgColor: '#fef3c7'
      },
      '2': {
        text: '质检中',
        color: '#3b82f6',
        bgColor: '#dbeafe'
      },
      '3': {
        text: '已质检',
        color: '#6366f1',
        bgColor: '#e0e7ff'
      },
      '4': {
        text: '待确认',
        color: '#8b5cf6',
        bgColor: '#ede9fe'
      },
      '5': {
        text: '已回收',
        color: '#10b981',
        bgColor: '#d1fae5'
      },
      '6': {
        text: '已退回',
        color: '#ef4444',
        bgColor: '#fee2e2'
      },
      '7': {
        text: '已定价',
        color: '#06b6d4',
        bgColor: '#cffafe'
      }
    }

    return statusMap[statusStr] || {
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
