import request from '@/utils/request'

export const getPerformanceConfig = () => request.get('performance/config')
export const savePerformanceConfig = (data: Record<string, any>) => request.post('performance/config', data)
export const getPerformanceReports = (params: Record<string, any>) => request.get('performance/reports', { params })
export const getPerformanceReport = (id: number) => request.get(`performance/reports/${id}`)
export const generatePerformanceReport = (report_type: string) => request.post('performance/reports/generate', { report_type })
export const retryPerformanceReport = (id: number) => request.post(`performance/reports/${id}/retry`)
