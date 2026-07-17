import request from '@/utils/request'

export const getMobilePerformanceReports = (params: Record<string, any>) => request.get('performance/reports', params)
export const getMobilePerformanceReport = (id: number) => request.get(`performance/reports/${id}`)
