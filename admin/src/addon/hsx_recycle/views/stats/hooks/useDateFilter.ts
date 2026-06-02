import { ref } from 'vue'

/**
 * 日期筛选 Hook
 */
export function useDateFilter() {
  const dateRange = ref<string[]>([])
  const activePeriod = ref('today')

  const quickPeriods = ref([
    { key: 'today', label: '今日' },
    { key: 'yesterday', label: '昨日' },
    { key: 'week', label: '近7天' },
    { key: 'month', label: '近30天' },
    { key: 'custom', label: '自定义' }
  ])

  // 格式化日期
  const formatDate = (date: Date) => {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
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
      case 'yesterday':
        startDate = new Date(today)
        startDate.setDate(today.getDate() - 1)
        endDate = new Date(startDate)
        break
      case 'week':
        startDate = new Date(today)
        endDate = new Date(today)
        startDate.setDate(today.getDate() - 6)
        break
      case 'month':
        startDate = new Date(today)
        startDate.setDate(today.getDate() - 29)
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
      order: { yesterday: '昨日订单', week: '近7天订单', month: '近30天订单', default: '今日订单' },
      check: { yesterday: '昨日质检', week: '近7天质检', month: '近30天质检', default: '今日质检' },
      payment: { yesterday: '昨日打款', week: '近7天打款', month: '近30天打款', default: '今日打款' },
      return: { yesterday: '昨日退货', week: '近7天退货', month: '近30天退货', default: '今日退货' }
    }

    const periodLabels = labels[type]
    switch (activePeriod.value) {
      case 'yesterday':
        return periodLabels.yesterday
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
