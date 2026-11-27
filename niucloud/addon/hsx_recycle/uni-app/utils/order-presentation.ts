import type { OrderDetailDevice } from '../types/order'

export const canDecideDevice = (device: Pick<OrderDetailDevice, 'status' | 'final_price'>) => {
  const price = Number(device.final_price)
  return Number.isFinite(price) && price > 0 && [4, 7].includes(Number(device.status))
}

export const orderProgress = (status: number | string) => {
  const value = Number(status)
  const descriptions: Record<number, string> = {
    1: '订单已提交，等待门店接收设备',
    2: '门店已签收，等待质检',
    3: '设备正在质检，请留意后续报价',
    4: '质检已完成，请查看各台设备的定价进度',
    5: '请核对设备报价，确认后安排回收',
    6: '等待商家打款，具体进度请联系门店',
    7: '回收订单已完成',
    8: '订单已关闭，可查看设备退回记录',
    9: '订单已取消',
    10: '订单已删除'
  }
  const step = ({ 1: 0, 2: 1, 3: 1, 4: 2, 5: 2, 6: 3, 7: 4 } as Record<number, number>)[value] ?? -1
  return {
    step, ended: [8, 9, 10].includes(value),
    description: descriptions[value] || '请查看设备明细或联系门店确认进度',
    icon: [8, 9, 10].includes(value) ? 'close-circle' : value === 7 ? 'checkmark-circle' : [2, 3].includes(value) ? 'search' : [4, 5].includes(value) ? 'file-text' : value === 6 ? 'rmb-circle' : 'clock'
  }
}

export const formatOrderMoney = (value: unknown) => {
  const amount = Number(value)
  return Number.isFinite(amount) ? amount.toFixed(2) : '0.00'
}
