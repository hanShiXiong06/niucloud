import {
  DocumentChecked,
  Edit,
  PriceTag,
  Check,
  Close,
  Switch,
  Printer,
} from '@element-plus/icons-vue'

/**
 * 设备行操作的「主次」单一真源。
 *
 * 把原先散落在桌面表格 / 移动卡片模板里的状态 v-if 按钮链，收敛为：
 *  - 主行动（primary）：当前状态最该做的下一步，高亮展示
 *  - 更多动作（more）：重新定价 / 拒绝 / 转代卖 / 打印等次要操作，收进「更多」下拉
 *
 * 桌面与移动两个组件复用同一套逻辑，保证行为一致、改一处即可。
 */

export interface DeviceRowAction {
  key: string
  label: string
  icon: any
  /** el-button 的 type，仅主行动用 */
  type?: string
  /** 是否危险操作（更多下拉里标红 + 分隔） */
  danger?: boolean
  handler: () => void
}

export interface DeviceRowActionHandlers {
  checkDevice: (device: any) => void
  priceDevice: (device: any) => void
  batchRecycleDevice: (id: number | string) => void
  batchReturnDevice: (id: number | string) => void
  cancelRecycle: (device: any) => void
  transferConsignment: (device: any) => void
  getVisibleDevicePrintActions: (device: any) => any[]
  printDeviceByScene: (device: any, action: any) => void
  /** 订单状态查询：待质检按钮需订单已签收（状态 > 1）才出现 */
  findOrderStatus: (orderId: number | string) => number
}

export function useDeviceRowActions(handlers: DeviceRowActionHandlers) {
  /** 当前状态的主行动（最该做的下一步）。无主行动时返回 null。 */
  const getDevicePrimaryAction = (device: any): DeviceRowAction | null => {
    const status = Number(device.status)
    switch (status) {
      case 1: // 待质检：需订单已签收
        if (handlers.findOrderStatus(device.order_id) > 1) {
          return { key: 'check', label: '开始质检', type: 'primary', icon: DocumentChecked, handler: () => handlers.checkDevice(device) }
        }
        return null
      case 2: // 质检中
        return { key: 'edit_check', label: '编辑质检', type: 'primary', icon: Edit, handler: () => handlers.checkDevice(device) }
      case 3: // 已质检
        return { key: 'price', label: '定价', type: 'primary', icon: PriceTag, handler: () => handlers.priceDevice(device) }
      case 4: // 待确认
        return { key: 'confirm', label: '确认回收', type: 'primary', icon: Check, handler: () => handlers.batchRecycleDevice(device.id) }
      default:
        return null
    }
  }

  /** 次要动作列表（收进「更多」下拉）。 */
  const getDeviceMoreActions = (device: any): DeviceRowAction[] => {
    const status = Number(device.status)
    const actions: DeviceRowAction[] = []

    // 待确认状态下可重新定价 / 拒绝
    if (status === 4) {
      actions.push({ key: 'reprice', label: '重新定价', icon: Edit, handler: () => handlers.priceDevice(device) })
      actions.push({ key: 'reject', label: '拒绝回收', icon: Close, danger: true, handler: () => handlers.batchReturnDevice(device.id) })
    }

    // 已质检 / 待确认 / 已定价 且尚未转代卖：可转代卖
    if ([3, 4, 7, 8].includes(status) && !device.consignment_order_id) {
      actions.push({ key: 'consign', label: '转代卖', icon: Switch, handler: () => handlers.transferConsignment(device) })
    }

    // 已回收：客户反悔可撤销回收（退款），同时触发 ERP 退货出库冲销
    if (status === 5) {
      actions.push({ key: 'cancel_recycle', label: '撤销回收', icon: Close, danger: true, handler: () => handlers.cancelRecycle(device) })
    }

    // 打印场景（动态，可有多个）
    const printActions = handlers.getVisibleDevicePrintActions(device) || []
    printActions.forEach((p: any, idx: number) => {
      actions.push({
        key: 'print_' + (p.scene_key ?? idx),
        label: p.button_text || p.scene_name || '打印',
        icon: Printer,
        handler: () => handlers.printDeviceByScene(device, p),
      })
    })

    return actions
  }

  return { getDevicePrimaryAction, getDeviceMoreActions }
}
