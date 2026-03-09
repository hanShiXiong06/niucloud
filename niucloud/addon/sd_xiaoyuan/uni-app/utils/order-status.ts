/**
 * 订单状态相关的公共方法
 * 用于统一管理订单按钮文案和状态处理
 */

// 订单状态映射
export const statusMap: Record<number, string> = {
    0: '待支付',
    10: '待接单',
    20: '已接单',
    30: '进行中',
    40: '待确认',
    50: '已完成',
    90: '已取消',
    91: '已退款'
}

// 订单状态描述
export const statusDescMap: Record<number, string> = {
    0: '请尽快完成支付',
    10: '订单等待接单员接单',
    20: '接单员已接单，正在处理',
    30: '订单正在进行中',
    40: '等待确认完成',
    50: '订单已完成',
    90: '订单已取消',
    91: '订单已退款'
}

// 任务类型映射
export const taskTypeMap: Record<string, string> = {
    'EXPRESS': '代取快递',
    'BUY': '帮我买',
    'SEND': '帮我送',
    'ERRAND': '跑腿代办',
    'PRINT': '帮打印',
    'QUEUE': '代排队',
    'SEAT': '代占座',
    'CLEAN': '代清洁',
    'TRASH': '扔垃圾',
    'CARRY': '帮搬运',
    'HELP': '帮帮忙',
    'GAME': '游戏陪练',
    'GROUP': '拼单'
}

// 获取状态文本
export const getStatusText = (status: number): string => {
    return statusMap[status] || '未知'
}

// 获取状态描述
export const getStatusDesc = (status: number): string => {
    return statusDescMap[status] || ''
}

// 获取任务类型名称
export const getTaskTypeName = (type: string): string => {
    return taskTypeMap[type] || type
}

// 状态20时的按钮文案（接单后第一步操作）
export const getStatus20Text = (type: string): string => {
    const textMap: Record<string, string> = {
        'EXPRESS': '确认取件',
        'BUY': '确认购买',
        'SEND': '确认取件',
        'PRINT': '确认打印',
        'QUEUE': '开始排队',
        'SEAT': '开始占座',
        'CARRY': '确认到达',
        'TRASH': '确认到达',
        'CLEAN': '确认到达',
        'HELP': '开始服务',
        'GAME': '开始服务',
        'GROUP': '开始配送'
    }
    return textMap[type] || '开始服务'
}

// 状态30时的按钮文案（进行中）
export const getDeliveryText = (type: string): string => {
    const textMap: Record<string, string> = {
        'EXPRESS': '开始配送',
        'BUY': '开始配送',
        'SEND': '开始配送',
        'PRINT': '打印中',
        'QUEUE': '排队中',
        'SEAT': '占座中',
        'CARRY': '搬运中',
        'TRASH': '清理中',
        'CLEAN': '清洁中',
        'HELP': '处理中',
        'GAME': '服务中',
        'GROUP': '拼单中'
    }
    return textMap[type] || '进行中'
}

// 状态40时的按钮文案（完成确认）
export const getCompleteText = (type: string): string => {
    const textMap: Record<string, string> = {
        'EXPRESS': '确认送达',
        'BUY': '确认送达',
        'SEND': '确认送达',
        'PRINT': '打印完成',
        'QUEUE': '排队完成',
        'SEAT': '占座完成',
        'CARRY': '搬运完成',
        'TRASH': '清理完成',
        'CLEAN': '清洁完成',
        'HELP': '服务完成',
        'GAME': '服务完成',
        'GROUP': '配送完成'
    }
    return textMap[type] || '确认完成'
}

// 判断订单类型是否需要取货步骤
export const needPickupStep = (type: string): boolean => {
    const pickupTypes = ['EXPRESS', 'BUY', 'SEND']
    return pickupTypes.includes(type)
}
