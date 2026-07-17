import request from '@/utils/request'

export function getErpConfig() {
    return request.get('erp/config')
}

export function saveErpConfig(data: Record<string, any>) {
    return request.post('erp/config', data)
}

export function getErpTaskAssignmentSettings() {
    return request.get('erp/config/task_assignment')
}

export function saveErpTaskAssignmentSettings(defaults: Record<string, number>) {
    return request.post('erp/config/task_assignment', { defaults })
}

export function dismissErpRefurbishReminder(mode: 'today' | 'forever' = 'today') {
    return request.post('erp/config/refurbish_reminder/dismiss', { mode })
}

export function dismissErpTurnoverReminder(mode: 'today' | 'forever' = 'today') {
    return request.post('erp/config/turnover_reminder/dismiss', { mode })
}

export function getErpSaleChannelOptions() {
    return request.get('erp/config/sale_channel_options')
}

export function saveErpSaleChannelOptions(channels: Record<string, any>[]) {
    return request.post('erp/config/sale_channel_options', { channels })
}

export function getErpFinanceCategories() {
    return request.get('erp/config/finance_categories')
}

export function getErpBusinessSourceOptions() {
    return request.get('erp/config/business_source_options')
}

export function saveErpFinanceCategories(categories: Record<string, any>[]) {
    return request.post('erp/config/finance_categories', { categories })
}
