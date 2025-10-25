import { ref } from 'vue'

/**
 * 日期筛选 Hook
 */
export function useDateFilter() {
  const dateRange = ref<string[]>([])
  const activePeriod = ref('today')

  const quickPeriods = ref([
    { key: 'today', label: '今日' },
    { key: 'week', label: '本周' },
    { key: 'month', label: '本月' },
    { key: 'custom', label: '自定义' }
  ])

  // 格式化日期
  const formatDate = (date: Date) => {
    return date.toISOString().split('T')[0]
  }

  // 处理快速时间筛选
  const handleQuickPeriod = (period: string, callback: (start: string, end: string) => void) => {
    activePeriod.value = period
    const today = new Date()
    let startDate: Date
    let endDate: Date = today

    switch (period) {
      case 'today':
        startDate = today
        endDate = today
        break
      case 'week':
        startDate = new Date(today)
        startDate.setDate(today.getDate() - today.getDay())
        break
      case 'month':
        startDate = new Date(today.getFullYear(), today.getMonth(), 1)
        break
      case 'custom':
        return
      default:
        startDate = today
    }

    if (period !== 'custom') {
      const start = formatDate(startDate)
      const end = formatDate(endDate)
      dateRange.value = [start, end]
      callback(start, end)
    }
  }

  // 处理日期范围变化
  const handleDateChange = (dates: string[], callback: (start: string, end: string) => void) => {
    if (dates && dates.length === 2) {
      activePeriod.value = 'custom'
      callback(dates[0], dates[1])
    } else {
      callback('', '')
    }
  }

  // 获取时间段文本
  const getTimePeriodText = (startTime: string, endTime: string) => {
    if (startTime && endTime) {
      if (startTime === endTime) {
        return startTime
      }
      return `${startTime} 至 ${endTime}`
    }
    return '今日'
  }

  // 获取标签文字（根据时间段动态变化）
  const getPeriodLabel = (type: 'order' | 'check' | 'payment' | 'return') => {
    const labels = {
      order: { week: '本周订单', month: '本月订单', default: '今日订单' },
      check: { week: '本周质检', month: '本月质检', default: '今日质检' },
      payment: { week: '本周打款', month: '本月打款', default: '今日打款' },
      return: { week: '本周退货', month: '本月退货', default: '今日退货' }
    }

    const periodLabels = labels[type]
    switch (activePeriod.value) {
      case 'week':
        return periodLabels.week
      case 'month':
        return periodLabels.month
      default:
        return periodLabels.default
    }
  }

  return {
    // 状态
    dateRange,
    activePeriod,
    quickPeriods,
    // 方法
    handleQuickPeriod,
    handleDateChange,
    getTimePeriodText,
    getPeriodLabel
  }
}


