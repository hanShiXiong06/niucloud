import request from '@/utils/request'

// 设备追溯: 搜索生命周期列表(IMEI/SN/资产号/回收单号)
export function searchDeviceTrace(keyword: string) {
    return request.get('erp/device_trace/search', { params: { keyword } })
}
// 设备追溯: 某次生命周期详情(概览+时间线)
export function getDeviceTraceDetail(params: Record<string, any>) {
    return request.get('erp/device_trace/detail', { params })
}
