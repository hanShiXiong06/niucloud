import type { OrderStatusInfo } from '../types/order'
import {
  getOrderStatusInfo,
  getDeviceStatusInfo,
  getReturnOrderStatusInfo,
  getDeliveryTypeInfo,
} from '../utils/theme'

/**
 * 订单状态管理
 * 提供状态映射、颜色、文本等信息
 *
 * 所有状态定义统一来自 utils/theme.ts，不再在此处硬编码
 */
export function useOrderStatus() {
  /** 获取订单状态信息 */
  const getStatusInfo = (status: number | string): OrderStatusInfo => {
    return getOrderStatusInfo(status)
  }

  /** 获取配送方式文本 */
  const getDeliveryTypeText = (type: string | number): string => {
    return getDeliveryTypeInfo(type).text
  }

  /** 获取配送方式颜色 */
  const getDeliveryTypeColor = (type: string | number): string => {
    return getDeliveryTypeInfo(type).color
  }

  /** 获取设备状态信息 */
  const getDeviceStatus = (status: number | string): OrderStatusInfo => {
    return getDeviceStatusInfo(status)
  }

  /** 获取退货订单状态信息 */
  const getReturnOrderStatus = (status: number | string): OrderStatusInfo => {
    return getReturnOrderStatusInfo(status)
  }

  return {
    getStatusInfo,
    getDeliveryTypeText,
    getDeliveryTypeColor,
    getDeviceStatusInfo: getDeviceStatus,
    getReturnOrderStatus
  }
}
